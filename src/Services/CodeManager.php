<?php

namespace Jtargosz\ActionOtp\Services;

use DateTimeInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Jtargosz\ActionOtp\Contracts\GeneratesCodes;
use Jtargosz\ActionOtp\Contracts\ManagesCodes;
use Jtargosz\ActionOtp\Contracts\StoresCodes;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use Jtargosz\ActionOtp\Events\CodeFailed;
use Jtargosz\ActionOtp\Events\CodeSent;
use Jtargosz\ActionOtp\Events\CodeVerified;
use Jtargosz\ActionOtp\Exceptions\MissingIdentifier;
use Jtargosz\ActionOtp\Support\OtpResult;
use Jtargosz\ActionOtp\Support\OtpStatus;

class CodeManager implements ManagesCodes
{
    protected string $identifier = '';

    public function __construct(
        protected StoresCodes $vault,
        protected GeneratesCodes $codes,
    ) {}

    public function to(string|int $identifier): static
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            throw new MissingIdentifier('Identifier is empty.');
        }

        $clone = clone $this;
        $clone->identifier = $identifier;
        $clone->vault = $this->vault->scope($identifier);

        return $clone;
    }

    public function send(VerifiableAction $action, mixed $notifiable): OtpResult
    {
        $this->needIdentifier();
        $this->assertTransmittable($notifiable);

        $lock = $this->acquireSlot();

        if ($lock === null) {
            return new OtpResult(OtpStatus::Throttled, __('action-otp.throttled'));
        }

        try {
            if ($this->coolingDown()) {
                return new OtpResult(OtpStatus::Throttled, __('action-otp.throttled'));
            }

            $record = $this->freshRecord($action, $notifiable);

            $this->vault->put($record);
            $this->resetTries();
        } finally {
            $lock->release();
        }

        return $this->transmit($record);
    }

    public function peek(string|int $code): OtpResult
    {
        $this->needIdentifier();

        $code = (string) $code;

        if ($this->locked()) {
            return new OtpResult(OtpStatus::Throttled, __('action-otp.throttled'));
        }

        $record = $this->vault->get();

        if (! $record) {
            return new OtpResult(OtpStatus::Empty, __('action-otp.empty'));
        }

        if (! isset($record['code'], $record['expires_at'])
            || ! is_string($record['code'])
            || ! $record['expires_at'] instanceof DateTimeInterface) {
            $this->vault->flush();

            return new OtpResult(OtpStatus::Empty, __('action-otp.empty'));
        }

        if (Carbon::now()->greaterThan($record['expires_at'])) {
            $this->vault->flush();

            return new OtpResult(OtpStatus::Expired, __('action-otp.expired'));
        }

        if (! hash_equals((string) $record['code'], $code)) {
            $this->hit();

            event(new CodeFailed($this->identifier));

            return new OtpResult(OtpStatus::Mismatch, __('action-otp.mismatch'));
        }

        return new OtpResult(OtpStatus::Matched, __('action-otp.matched'));
    }

    public function verify(string|int $code): OtpResult
    {
        $this->needIdentifier();

        $code = (string) $code;

        $lock = $this->acquireSlot();

        if ($lock === null) {
            return new OtpResult(OtpStatus::Throttled, __('action-otp.throttled'));
        }

        try {
            $check = $this->peek($code);

            if (! $check->found()) {
                return $check;
            }

            $record = $this->vault->get();

            if (! isset($record['action'])) {
                $this->vault->flush();

                return new OtpResult(OtpStatus::Empty, __('action-otp.empty'));
            }

            $action = $record['action'];

            if (! is_object($action) || ! method_exists($action, 'handle')) {
                $this->vault->flush();

                return new OtpResult(OtpStatus::Empty, __('action-otp.empty'));
            }

            $this->vault->flush();
        } finally {
            $lock->release();
        }

        $payload = $action->handle();

        event(new CodeVerified($this->identifier, $payload));

        return new OtpResult(OtpStatus::Verified, __('action-otp.verified'), $payload);
    }

    public function resend(): OtpResult
    {
        $this->needIdentifier();

        $lock = $this->acquireSlot();

        if ($lock === null) {
            return new OtpResult(OtpStatus::Throttled, __('action-otp.throttled'));
        }

        try {
            $current = $this->vault->get();

            if ($current === null) {
                return new OtpResult(OtpStatus::Empty, __('action-otp.empty'));
            }

            if (! isset($current['action'], $current['notifiable'])) {
                $this->vault->flush();

                return new OtpResult(OtpStatus::Empty, __('action-otp.empty'));
            }

            $this->assertTransmittable($current['notifiable']);

            if ($this->coolingDown()) {
                return new OtpResult(OtpStatus::Throttled, __('action-otp.throttled'));
            }

            $record = $this->freshRecord($current['action'], $current['notifiable']);

            $this->vault->put($record);
            $this->resetTries();
        } finally {
            $lock->release();
        }

        return $this->transmit($record);
    }

    public function clear(): void
    {
        $this->needIdentifier();

        $lock = $this->acquireSlot();

        try {
            $this->vault->flush();
        } finally {
            if ($lock !== null) {
                $lock->release();
            }
        }
    }

    protected function needIdentifier(): void
    {
        if ($this->identifier === '') {
            throw new MissingIdentifier('No identifier set.');
        }
    }

    protected function vaultKey(string $suffix): string
    {
        return (string) config('action-otp.store_prefix', 'action-otp:')
            .hash('sha256', $this->identifier).$suffix;
    }

    protected function acquireSlot(): ?Lock
    {
        $lock = Cache::lock($this->vaultKey(':verify'), 10);

        try {
            $lock->block(2);
        } catch (LockTimeoutException) {
            return null;
        }

        return $lock;
    }

    /**
     * @return array{action: mixed, notifiable: mixed, code: string, expires_at: DateTimeInterface}
     */
    protected function freshRecord(mixed $action, mixed $notifiable): array
    {
        return [
            'action' => $action,
            'notifiable' => $notifiable,
            'code' => $this->codes->make(
                (string) config('action-otp.code_format', 'numeric'),
                (int) config('action-otp.code_length', 6),
            ),
            'expires_at' => Carbon::now()->addMinutes((int) config('action-otp.ttl_minutes', 15)),
        ];
    }

    /**
     * @param  array{action: mixed, notifiable: mixed, code: string, expires_at: DateTimeInterface}  $record
     */
    protected function transmit(array $record): OtpResult
    {
        /** @var class-string $notification */
        $notification = (string) config('action-otp.notification');

        $record['notifiable']->notify(new $notification($record));
        $this->markSent();

        event(new CodeSent($this->identifier, $record));

        return new OtpResult(OtpStatus::Sent, __('action-otp.sent'));
    }

    protected function assertTransmittable(mixed $notifiable): void
    {
        /** @var class-string $notification */
        $notification = (string) config('action-otp.notification');

        if (! is_a($notification, Notification::class, true)) {
            throw new InvalidArgumentException('OTP notification must extend Illuminate\Notifications\Notification.');
        }

        if (! is_object($notifiable) || ! method_exists($notifiable, 'notify')) {
            throw new InvalidArgumentException('Notifiable must use the Notifiable trait.');
        }
    }

    protected function locked(): bool
    {
        return Cache::has($this->vaultKey(':locked'));
    }

    protected function hit(): void
    {
        $key = $this->vaultKey(':tries');
        $max = (int) config('action-otp.max_attempts', 5);

        $tries = Cache::has($key) ? Cache::increment($key) : false;

        if ($tries === false) {
            $tries = 1;
            Cache::put($key, 1, 3600);
        }

        if ($tries >= $max) {
            Cache::put(
                $this->vaultKey(':locked'),
                true,
                Carbon::now()->addSeconds((int) config('action-otp.throttle_seconds', 60))
            );
        }
    }

    protected function resetTries(): void
    {
        Cache::forget($this->vaultKey(':tries'));
        Cache::forget($this->vaultKey(':locked'));
    }

    protected function coolingDown(): bool
    {
        return Cache::has($this->vaultKey(':sent_at'));
    }

    protected function markSent(): void
    {
        $cooldown = (int) config('action-otp.send_cooldown', 30);

        if ($cooldown > 0) {
            Cache::put($this->vaultKey(':sent_at'), true, Carbon::now()->addSeconds($cooldown));
        }
    }
}
