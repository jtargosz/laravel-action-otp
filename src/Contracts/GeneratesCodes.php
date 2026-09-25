<?php

namespace Jtargosz\ActionOtp\Contracts;

interface GeneratesCodes
{
    public function make(string $format, int $length): string;
}
