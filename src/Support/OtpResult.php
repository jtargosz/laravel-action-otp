<?php

namespace Jtargosz\ActionOtp\Support;

final class OtpResult
{
    public function __construct(
        public readonly OtpStatus $status,
        public readonly string $message,
        public readonly mixed $payload = null,
    ) {}

    public function ok(): bool
    {
        return $this->status === OtpStatus::Verified;
    }

    public function found(): bool
    {
        return $this->status === OtpStatus::Matched
            || $this->status === OtpStatus::Verified;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'message' => $this->message,
            'payload' => $this->payload,
        ];
    }
}
