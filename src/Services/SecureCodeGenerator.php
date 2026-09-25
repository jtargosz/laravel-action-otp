<?php

namespace Jtargosz\ActionOtp\Services;

use InvalidArgumentException;
use Jtargosz\ActionOtp\Contracts\GeneratesCodes;

class SecureCodeGenerator implements GeneratesCodes
{
    public function make(string $format, int $length): string
    {
        if ($length < 1 || $length > 64) {
            throw new InvalidArgumentException('Code length must be between 1 and 64.');
        }

        $alphabet = match ($format) {
            'numeric' => '0123456789',
            'alpha' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'alphanumeric' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
            default => throw new InvalidArgumentException('Unknown code format.'),
        };

        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
