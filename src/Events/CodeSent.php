<?php

namespace Jtargosz\ActionOtp\Events;

use DateTimeInterface;

/**
 * Carries no code and no action, so it is safe to log.
 */
class CodeSent
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $purpose,
        public readonly DateTimeInterface $expiresAt,
    ) {}
}
