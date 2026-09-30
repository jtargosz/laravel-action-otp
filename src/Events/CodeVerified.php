<?php

namespace Jtargosz\ActionOtp\Events;

class CodeVerified
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $purpose,
        public readonly mixed $payload = null,
    ) {}
}
