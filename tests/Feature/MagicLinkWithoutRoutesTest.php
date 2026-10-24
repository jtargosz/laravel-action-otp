<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Jtargosz\ActionOtp\Tests\TestCase;
use LogicException;

class MagicLinkWithoutRoutesTest extends TestCase
{
    public function test_enabled_link_without_routes_throws(): void
    {
        config()->set('action-otp.link.enabled', true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ActionOtp::routes()');

        $this->sendCode('x@example.com');
    }
}
