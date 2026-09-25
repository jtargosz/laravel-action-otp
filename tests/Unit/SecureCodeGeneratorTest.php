<?php

namespace Jtargosz\ActionOtp\Tests\Unit;

use Jtargosz\ActionOtp\Services\SecureCodeGenerator;
use PHPUnit\Framework\TestCase;

class SecureCodeGeneratorTest extends TestCase
{
    public function test_numeric_codes(): void
    {
        $code = (new SecureCodeGenerator)->make('numeric', 6);

        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
    }

    public function test_alpha_codes(): void
    {
        $code = (new SecureCodeGenerator)->make('alpha', 8);

        $this->assertMatchesRegularExpression('/^[A-Z]{8}$/', $code);
    }

    public function test_rejects_unknown_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SecureCodeGenerator)->make('emoji', 6);
    }

    public function test_rejects_invalid_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SecureCodeGenerator)->make('numeric', 0);
    }
}
