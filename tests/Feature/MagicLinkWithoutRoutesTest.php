<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Tests\Fixtures\ConfirmLoginAction;
use Jtargosz\ActionOtp\Tests\TestCase;
use LogicException;

class MagicLinkWithoutRoutesTest extends TestCase
{
    public function test_enabled_link_without_routes_throws(): void
    {
        config()->set('action-otp.link.enabled', true);
        Notification::fake();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ActionOtp::routes()');

        ActionOtp::to('x@example.com')->send(new ConfirmLoginAction, Notification::route('mail', 'x@example.com'));
    }
}
