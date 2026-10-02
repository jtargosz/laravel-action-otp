<?php

namespace Jtargosz\ActionOtp\Services;

use DateTimeInterface;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Jtargosz\ActionOtp\Contracts\GeneratesCodes;
use Jtargosz\ActionOtp\Contracts\ManagesCodes;
use Jtargosz\ActionOtp\Contracts\StoresCodes;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use Jtargosz\ActionOtp\Events\CodeFailed;
use Jtargosz\ActionOtp\Events\CodeSent;
use Jtargosz\ActionOtp\Events\CodeVerified;
use Jtargosz\ActionOtp\Exceptions\MissingIdentifier;
use Jtargosz\ActionOtp\Support\ActionPolicy;
use Jtargosz\ActionOtp\Support\Keys;
use Jtargosz\ActionOtp\Support\OtpMessage;
use Jtargosz\ActionOtp\Support\OtpResult;
use Jtargosz\ActionOtp\Support\OtpStatus;
use LogicException;
use Throwable;

/**
 * Every operation on one identifier runs under the identifier's slot lock.
 * Records are keyed by challenge, so a send from one browser (or API client)
 * never replaces or unlocks the pending action of another.
 *
 * Challenge lookup order: withChallenge() token, then the browser session,
 * then the AnyDevice pointer.
 *
 * @phpstan-import-type OtpRecord from StoresCodes
 */
class CodeManager implements ManagesCodes
{
    protected ?string $identifier = null;

    protected ?string $identifierHash = null;

    protected string $purpose = 'default';

    protected ?string $challenge = null;

    public function __construct(
        protected StoresCodes $vault,
        protected GeneratesCodes $codes,
        protected Throttle $throttle,
        protected SessionChallenges $sessions,
    ) {}

    public function to(string|int $identifier): static
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            throw new MissingIdentifier('Identifier is empty.');
        }

        $clone = clone $this;
        $clone->identifier = $identifier;
        $clone->identifierHash = Keys::hash($identifier);

        return $clone;
    }

    public function forIdentifierHash(string $identifierHash): static
    {
        if (! Keys::isHash($identifierHash)) {
            throw new MissingIdentifier('Invalid identifier hash.');
        }

        $clone = clone $this;
        $clone->identifier = null;
        $clone->identifierHash = $identifierHash;

        return $clone;
    }

    public function for(string $purpose): static
    {
        $purpose = trim($purpose);

        if ($purpose === '') {
            throw new InvalidArgumentException('Purpose is empty.');
        }

        $clone = clone $this;
        $clone->purpose = $purpose;

        return $clone;
    }

    public function withChallenge(?string $challenge): static
    {
        $clone = clone $this;
        $clone->challenge = $challenge === null || $challenge === '' ? null : $challenge;

        return $clone;
    }

    public function send(VerifiableAction $action, object $notifiable): OtpResult
    {
        $identifier = $this->needIdentifier();
        $identifierHash = $this->needHash();
        $policy = ActionPolicy::from($action);

        $this->assertNotifiable($notifiable);
        $this->assertLinkRoutes();

        $lock = $this->throttle->lock($identifierHash);

        if ($lock === null) {
            return OtpResult::of(OtpStatus::Throttled);
        }

        try {
            // A new code never lifts the lockout or skips the cooldown.
            if ($this->throttle->locked($identifierHash) || $this->throttle->coolingDown($identifierHash)) {
                return OtpResult::of(OtpStatus::Throttled);
            }

            $previous = $this->previousChallenge($policy->anyDevice);

            $challenge = Str::random(40);
            $challengeHash = Keys::hash($challenge);
            $linkToken = $this->linksEnabled() ? Str::random(40) : null;

            $record = [
                'identifier' => $identifier,
                'purpose' => $this->purpose,
                'action' => $action,
                'notifiable' => $notifiable,
                'code' => $this->normalize($this->codes->make($policy->format, $policy->length), $policy->format),
                'format' => $policy->format,
                'link' => $linkToken === null ? null : Keys::hash($linkToken),
                'expires_at' => Carbon::now()->addMinutes($policy->ttlMinutes),
                'any_device' => $policy->anyDevice,
            ];

            $this->vault->put($identifierHash, $challengeHash, $record);

            if ($previous !== null && $previous !== $challengeHash) {
                $this->vault->forget($identifierHash, $previous);
            }

            $this->bind($challengeHash, $record);

            // Set before notify() runs outside the lock, so a parallel send
            // already sees the cooldown.
            $this->throttle->markSent($identifierHash);
        } finally {
            $lock->release();
        }

        $this->transmit($record, $policy, $linkToken, $challenge);

        return OtpResult::of(OtpStatus::Sent, challenge: $challenge);
    }

    public function peek(string $code): OtpResult
    {
        $lock = $this->throttle->lock($this->needHash());

        if ($lock === null) {
            return OtpResult::of(OtpStatus::Throttled);
        }

        try {
            return $this->match($code)[0];
        } finally {
            $lock->release();
        }
    }

    public function verify(string $code): OtpResult
    {
        $lock = $this->throttle->lock($this->needHash());

        if ($lock === null) {
            return OtpResult::of(OtpStatus::Throttled);
        }

        try {
            [$result, $record, $challengeHash] = $this->match($code);

            if ($record === null || $challengeHash === null) {
                return $result;
            }

            $this->consume($challengeHash);
        } finally {
            $lock->release();
        }

        return $this->run($record);
    }

    public function verifyLink(string $token): OtpResult
    {
        $lock = $this->throttle->lock($this->needHash());

        if ($lock === null) {
            return OtpResult::of(OtpStatus::Throttled);
        }

        try {
            [$result, $record, $challengeHash] = $this->lookup(link: true);

            if ($record === null || $challengeHash === null) {
                return $result ?? OtpResult::of(OtpStatus::Empty);
            }

            if ($record['link'] === null || ! hash_equals($record['link'], Keys::hash($token))) {
                $this->fail($record);

                return OtpResult::of(OtpStatus::Mismatch);
            }

            $this->consume($challengeHash);
        } finally {
            $lock->release();
        }

        return $this->run($record);
    }

    public function resend(): OtpResult
    {
        $identifierHash = $this->needHash();

        $lock = $this->throttle->lock($identifierHash);

        if ($lock === null) {
            return OtpResult::of(OtpStatus::Throttled);
        }

        try {
            $challengeHash = $this->resolveChallenge();

            if ($challengeHash === null) {
                return OtpResult::of(OtpStatus::Empty);
            }

            $record = $this->validRecord($this->vault->get($identifierHash, $challengeHash));

            if ($record === null) {
                $this->forget($challengeHash);

                return OtpResult::of(OtpStatus::Empty);
            }

            if ($record['purpose'] !== $this->purpose) {
                return OtpResult::of(OtpStatus::Empty);
            }

            $policy = ActionPolicy::from($record['action']);

            $this->assertNotifiable($record['notifiable']);
            $this->assertLinkRoutes();

            if ($this->throttle->locked($identifierHash) || $this->throttle->coolingDown($identifierHash)) {
                return OtpResult::of(OtpStatus::Throttled);
            }

            $linkToken = $this->linksEnabled() ? Str::random(40) : null;

            $record['code'] = $this->normalize($this->codes->make($policy->format, $policy->length), $policy->format);
            $record['format'] = $policy->format;
            $record['link'] = $linkToken === null ? null : Keys::hash($linkToken);
            $record['expires_at'] = Carbon::now()->addMinutes($policy->ttlMinutes);

            $this->vault->put($identifierHash, $challengeHash, $record);
            $this->bind($challengeHash, $record);
            $this->throttle->markSent($identifierHash);
        } finally {
            $lock->release();
        }

        $this->transmit($record, $policy, $linkToken, null);

        return OtpResult::of(OtpStatus::Sent);
    }

    /**
     * Deletes the pending code. Attempt counters, lockout and cooldown stay.
     */
    public function clear(): void
    {
        $lock = $this->throttle->lock($this->needHash());

        try {
            $challengeHash = $this->resolveChallenge();

            if ($challengeHash !== null) {
                $this->forget($challengeHash);
            }
        } finally {
            $lock?->release();
        }
    }

    /**
     * Compares a code against the resolved record. Caller holds the slot lock.
     *
     * @return array{0: OtpResult, 1: OtpRecord|null, 2: string|null}
     */
    protected function match(string $code): array
    {
        [$result, $record, $challengeHash] = $this->lookup();

        if ($record === null || $challengeHash === null) {
            return [$result ?? OtpResult::of(OtpStatus::Empty), null, null];
        }

        if (! hash_equals($record['code'], $this->normalize($code, $record['format']))) {
            $this->fail($record);

            return [OtpResult::of(OtpStatus::Mismatch), null, null];
        }

        return [OtpResult::of(OtpStatus::Matched), $record, $challengeHash];
    }

    /**
     * Finds the record for the current identifier, purpose and challenge.
     * Returns a result when there is nothing to compare against. A missing
     * challenge never counts as an attempt.
     *
     * @return array{0: OtpResult|null, 1: OtpRecord|null, 2: string|null}
     */
    protected function lookup(bool $link = false): array
    {
        $identifierHash = $this->needHash();

        if ($this->throttle->locked($identifierHash)) {
            return [OtpResult::of(OtpStatus::Throttled), null, null];
        }

        $challengeHash = $this->resolveChallenge(explicit: ! $link);

        if ($challengeHash === null) {
            return [OtpResult::of($link ? OtpStatus::DeviceMismatch : OtpStatus::Empty), null, null];
        }

        $record = $this->validRecord($this->vault->get($identifierHash, $challengeHash));

        if ($record === null) {
            $this->forget($challengeHash);

            return [OtpResult::of(OtpStatus::Empty), null, null];
        }

        if ($record['purpose'] !== $this->purpose) {
            return [OtpResult::of(OtpStatus::Empty), null, null];
        }

        if (Carbon::now()->greaterThan($record['expires_at'])) {
            $this->forget($challengeHash);

            return [OtpResult::of(OtpStatus::Expired), null, null];
        }

        return [null, $record, $challengeHash];
    }

    /**
     * @param  OtpRecord  $record
     */
    protected function run(array $record): OtpResult
    {
        $payload = $record['action']->handle();

        $this->verified($record, $payload);

        return OtpResult::of(OtpStatus::Verified, $payload);
    }

    /**
     * @param  OtpRecord  $record
     */
    protected function verified(array $record, mixed $payload): void
    {
        event(new CodeVerified($record['identifier'], $record['purpose'], $payload));
    }

    /**
     * @param  OtpRecord  $record
     */
    protected function fail(array $record): void
    {
        $this->throttle->hit($this->needHash());

        event(new CodeFailed($record['identifier'], $record['purpose']));
    }

    /**
     * The code is consumed before handle() runs, so a throwing action never
     * leaves a reusable code behind.
     */
    protected function consume(string $challengeHash): void
    {
        $this->forget($challengeHash);
        $this->throttle->reset($this->needHash());
    }

    /**
     * Deletes the record and whichever binding points to it.
     */
    protected function forget(string $challengeHash): void
    {
        $identifierHash = $this->needHash();
        $purposeHash = Keys::hash($this->purpose);

        $this->vault->forget($identifierHash, $challengeHash);

        if ($this->sessions->get($identifierHash, $purposeHash) === $challengeHash) {
            $this->sessions->forget($identifierHash, $purposeHash);
        }

        $pointer = Keys::pointer($identifierHash, $purposeHash);

        if (Cache::get($pointer) === $challengeHash) {
            Cache::forget($pointer);
        }
    }

    /**
     * @param  OtpRecord  $record
     */
    protected function bind(string $challengeHash, array $record): void
    {
        $identifierHash = $this->needHash();
        $purposeHash = Keys::hash($this->purpose);

        if ($record['any_device']) {
            Cache::put(
                Keys::pointer($identifierHash, $purposeHash),
                $challengeHash,
                CacheCodeVault::ttl($record['expires_at'])
            );

            return;
        }

        $this->sessions->put($identifierHash, $purposeHash, $challengeHash);
    }

    protected function resolveChallenge(bool $explicit = true): ?string
    {
        if ($explicit && $this->challenge !== null) {
            return Keys::hash($this->challenge);
        }

        $identifierHash = $this->needHash();
        $purposeHash = Keys::hash($this->purpose);

        return $this->sessions->get($identifierHash, $purposeHash)
            ?? $this->pointer($identifierHash, $purposeHash);
    }

    /**
     * The record a new send replaces: only one from the same token, session
     * or (for AnyDevice actions) the same pointer. Never someone else's.
     */
    protected function previousChallenge(bool $anyDevice): ?string
    {
        if ($this->challenge !== null) {
            return Keys::hash($this->challenge);
        }

        $identifierHash = $this->needHash();
        $purposeHash = Keys::hash($this->purpose);

        return $anyDevice
            ? $this->pointer($identifierHash, $purposeHash)
            : $this->sessions->get($identifierHash, $purposeHash);
    }

    protected function pointer(string $identifierHash, string $purposeHash): ?string
    {
        $value = Cache::get(Keys::pointer($identifierHash, $purposeHash));

        return is_string($value) ? $value : null;
    }

    /**
     * @return OtpRecord|null
     */
    protected function validRecord(mixed $record): ?array
    {
        if (! is_array($record)
            || ! is_string($record['identifier'] ?? null)
            || ! is_string($record['purpose'] ?? null)
            || ! ($record['action'] ?? null) instanceof VerifiableAction
            || ! is_object($record['notifiable'] ?? null)
            || ! is_string($record['code'] ?? null)
            || ! is_string($record['format'] ?? null)
            || ! (is_string($record['link'] ?? null) || ($record['link'] ?? null) === null)
            || ! ($record['expires_at'] ?? null) instanceof DateTimeInterface
            || ! is_bool($record['any_device'] ?? null)) {
            return null;
        }

        /** @var OtpRecord $record */
        return $record;
    }

    /**
     * @param  OtpRecord  $record
     */
    protected function transmit(array $record, ActionPolicy $policy, ?string $linkToken, ?string $challenge): void
    {
        $message = new OtpMessage(
            $record['code'],
            $record['expires_at'],
            $record['purpose'],
            $linkToken === null ? null : $this->linkUrl($record, $linkToken),
        );

        try {
            $this->deliver($record, $policy, $message, $challenge);
        } catch (Throwable $e) {
            // A failed delivery must not block the retry.
            $this->throttle->forgetSent($this->needHash());

            throw $e;
        }

        event(new CodeSent($record['identifier'], $record['purpose'], $record['expires_at']));
    }

    /**
     * @param  OtpRecord  $record
     */
    protected function deliver(array $record, ActionPolicy $policy, OtpMessage $message, ?string $challenge): void
    {
        /** @var class-string $class */
        $class = $policy->notification;

        /** @var Notification $notification */
        $notification = new $class($message);

        // Queued mails render in the request locale unless the notifiable has its own.
        if (! $record['notifiable'] instanceof HasLocalePreference) {
            $notification->locale(app()->getLocale());
        }

        $record['notifiable']->notify($notification);
    }

    /**
     * @param  OtpRecord  $record
     */
    protected function linkUrl(array $record, string $token): string
    {
        return URL::temporarySignedRoute('action-otp.link', $record['expires_at'], [
            'i' => $this->needHash(),
            'p' => $record['purpose'],
            't' => $token,
        ]);
    }

    protected function linksEnabled(): bool
    {
        return (bool) config('action-otp.link.enabled', false);
    }

    protected function assertLinkRoutes(): void
    {
        if ($this->linksEnabled() && ! Route::has('action-otp.link')) {
            throw new LogicException('action-otp.link.enabled needs the package routes. Call ActionOtp::routes() in your routes file.');
        }
    }

    protected function assertNotifiable(object $notifiable): void
    {
        if (! method_exists($notifiable, 'notify')) {
            throw new InvalidArgumentException('Notifiable must use the Notifiable trait.');
        }
    }

    protected function normalize(string $code, string $format): string
    {
        $code = trim($code);

        return $format === 'numeric' ? $code : strtoupper($code);
    }

    protected function needIdentifier(): string
    {
        if ($this->identifier === null) {
            throw new MissingIdentifier('No identifier set. Call to() first.');
        }

        return $this->identifier;
    }

    protected function needHash(): string
    {
        if ($this->identifierHash === null) {
            throw new MissingIdentifier('No identifier set. Call to() first.');
        }

        return $this->identifierHash;
    }
}
