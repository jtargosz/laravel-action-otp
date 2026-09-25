<?php

namespace Jtargosz\ActionOtp\Events;

class CodeSent
{
    public function __construct(
        public readonly string $identifier,
        public readonly array $record,
    ) {}
}
