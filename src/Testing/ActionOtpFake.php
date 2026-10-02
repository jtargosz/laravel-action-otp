<?php

namespace Jtargosz\ActionOtp\Testing;

use Jtargosz\ActionOtp\Contracts\GeneratesCodes;
use Jtargosz\ActionOtp\Contracts\StoresCodes;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use Jtargosz\ActionOtp\Services\CodeManager;
use Jtargosz\ActionOtp\Services\SessionChallenges;
use Jtargosz\ActionOtp\Services\Throttle;
use Jtargosz\ActionOtp\Support\ActionPolicy;
use Jtargosz\ActionOtp\Support\OtpMessage;
use PHPUnit\Framework\Assert as PHPUnit;
use RuntimeException;

/**
 * Real manager (storage, sessions, throttling, verify) that records sends
 * instead of delivering notifications. Enable with ActionOtp::fake().
 *
 * @phpstan-import-type OtpRecord from StoresCodes
 */
class ActionOtpFake extends CodeManager
{
    protected Recorder $recorder;

    public function __construct(
        StoresCodes $vault,
        GeneratesCodes $codes,
        Throttle $throttle,
        SessionChallenges $sessions,
        ?Recorder $recorder = null,
    ) {
        parent::__construct($vault, $codes, $throttle, $sessions);

        $this->recorder = $recorder ?? new Recorder;
    }

    /**
     * @param  OtpRecord  $record
     */
    protected function deliver(array $record, ActionPolicy $policy, OtpMessage $message, ?string $challenge): void
    {
        $this->recorder->sent[] = [
            'identifier' => $record['identifier'],
            'purpose' => $record['purpose'],
            'action' => $record['action'],
            'message' => $message,
            // resend() keeps the challenge of the original send.
            'challenge' => $challenge ?? $this->lastSent($record['identifier'], $record['purpose'])['challenge'] ?? null,
        ];
    }

    /**
     * @param  OtpRecord  $record
     */
    protected function verified(array $record, mixed $payload): void
    {
        parent::verified($record, $payload);

        $this->recorder->verified[] = [
            'identifier' => $record['identifier'],
            'purpose' => $record['purpose'],
            'action' => $record['action'],
            'payload' => $payload,
        ];
    }

    /**
     * @param  class-string<VerifiableAction>  $action
     * @param  (callable(VerifiableAction, OtpMessage, string, string): bool)|null  $callback  receives action, message, identifier, purpose
     */
    public function assertSent(string $action, ?callable $callback = null): void
    {
        PHPUnit::assertNotEmpty(
            $this->sentMatching($action, $callback),
            "The expected OTP action [{$action}] was not sent."
        );
    }

    /**
     * @param  class-string<VerifiableAction>  $action
     */
    public function assertSentTimes(string $action, int $times): void
    {
        $count = count($this->sentMatching($action));

        PHPUnit::assertSame(
            $times,
            $count,
            "The OTP action [{$action}] was sent {$count} times instead of {$times} times."
        );
    }

    /**
     * @param  class-string<VerifiableAction>  $action
     * @param  (callable(VerifiableAction, OtpMessage, string, string): bool)|null  $callback
     */
    public function assertNotSent(string $action, ?callable $callback = null): void
    {
        PHPUnit::assertEmpty(
            $this->sentMatching($action, $callback),
            "The unexpected OTP action [{$action}] was sent."
        );
    }

    public function assertNothingSent(): void
    {
        $classes = implode(', ', array_map(fn (array $sent) => $sent['action']::class, $this->recorder->sent));

        PHPUnit::assertEmpty($this->recorder->sent, "OTP actions were sent unexpectedly: {$classes}.");
    }

    /**
     * @param  class-string<VerifiableAction>  $action
     * @param  (callable(VerifiableAction, mixed, string, string): bool)|null  $callback  receives action, payload, identifier, purpose
     */
    public function assertVerified(string $action, ?callable $callback = null): void
    {
        $matching = array_filter(
            $this->recorder->verified,
            fn (array $verified) => $verified['action'] instanceof $action
                && ($callback === null || $callback($verified['action'], $verified['payload'], $verified['identifier'], $verified['purpose']))
        );

        PHPUnit::assertNotEmpty($matching, "The expected OTP action [{$action}] was not verified.");
    }

    /**
     * @param  class-string<VerifiableAction>  $action
     */
    public function assertNotVerified(string $action): void
    {
        $matching = array_filter($this->recorder->verified, fn (array $verified) => $verified['action'] instanceof $action);

        PHPUnit::assertEmpty($matching, "The unexpected OTP action [{$action}] was verified.");
    }

    /**
     * The code of the last send (or resend) for an identifier and purpose.
     */
    public function codeFor(string|int $identifier, string $purpose = 'default'): string
    {
        return $this->messageFor($identifier, $purpose)->code;
    }

    /**
     * The challenge token of the last send for an identifier and purpose.
     */
    public function challengeFor(string|int $identifier, string $purpose = 'default'): string
    {
        $challenge = $this->lastSentOrFail($identifier, $purpose)['challenge'];

        if ($challenge === null) {
            throw new RuntimeException('No challenge recorded for this identifier.');
        }

        return $challenge;
    }

    public function messageFor(string|int $identifier, string $purpose = 'default'): OtpMessage
    {
        return $this->lastSentOrFail($identifier, $purpose)['message'];
    }

    /**
     * @param  class-string<VerifiableAction>  $action
     * @param  (callable(VerifiableAction, OtpMessage, string, string): bool)|null  $callback
     * @return list<array{identifier: string, purpose: string, action: VerifiableAction, message: OtpMessage, challenge: string|null}>
     */
    protected function sentMatching(string $action, ?callable $callback = null): array
    {
        return array_values(array_filter(
            $this->recorder->sent,
            fn (array $sent) => $sent['action'] instanceof $action
                && ($callback === null || $callback($sent['action'], $sent['message'], $sent['identifier'], $sent['purpose']))
        ));
    }

    /**
     * @return array{identifier: string, purpose: string, action: VerifiableAction, message: OtpMessage, challenge: string|null}|null
     */
    protected function lastSent(string|int $identifier, string $purpose): ?array
    {
        $identifier = trim((string) $identifier);

        foreach (array_reverse($this->recorder->sent) as $sent) {
            if ($sent['identifier'] === $identifier && $sent['purpose'] === $purpose) {
                return $sent;
            }
        }

        return null;
    }

    /**
     * @return array{identifier: string, purpose: string, action: VerifiableAction, message: OtpMessage, challenge: string|null}
     */
    protected function lastSentOrFail(string|int $identifier, string $purpose): array
    {
        return $this->lastSent($identifier, $purpose)
            ?? throw new RuntimeException("No OTP was sent to [{$identifier}] for purpose [{$purpose}].");
    }
}
