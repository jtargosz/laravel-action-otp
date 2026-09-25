<?php

namespace Jtargosz\ActionOtp\Events;

class CodeSent
{
    /**
     * @param  array{action: mixed, notifiable: mixed, code: string, expires_at: \DateTimeInterface}  $record
     */
    public function __construct(
        public readonly string $identifier,
        public readonly array $record,
    ) {}
}
