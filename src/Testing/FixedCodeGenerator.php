<?php

namespace Jtargosz\ActionOtp\Testing;

use Jtargosz\ActionOtp\Contracts\GeneratesCodes;

/**
 * Always returns the same code. For tests only.
 */
class FixedCodeGenerator implements GeneratesCodes
{
    public function __construct(protected string $code = '123456') {}

    public function make(string $format, int $length): string
    {
        return $this->code;
    }
}
