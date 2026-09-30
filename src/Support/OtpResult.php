<?php

namespace Jtargosz\ActionOtp\Support;

final class OtpResult
{
    /**
     * @param  string|null  $challenge  Token returned by send(). API clients keep it and pass it back with withChallenge().
     */
    public function __construct(
        public readonly OtpStatus $status,
        public readonly string $message,
        public readonly mixed $payload = null,
        public readonly ?string $challenge = null,
    ) {}

    public static function of(OtpStatus $status, mixed $payload = null, ?string $challenge = null): self
    {
        return new self($status, (string) __('action-otp::action-otp.'.$status->value), $payload, $challenge);
    }

    public function ok(): bool
    {
        return $this->status === OtpStatus::Verified;
    }

    public function found(): bool
    {
        return $this->status === OtpStatus::Matched
            || $this->status === OtpStatus::Verified;
    }

    /**
     * @return array{status: string, message: string, payload: mixed, challenge: string|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'message' => $this->message,
            'payload' => $this->payload,
            'challenge' => $this->challenge,
        ];
    }
}
