<?php

namespace Jtargosz\ActionOtp\Events;

class CodeFailed
{
    public function __construct(public readonly string $identifier) {}
}
