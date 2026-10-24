<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Mail\CodeMail;
use Jtargosz\ActionOtp\Support\OtpStatus;
use Jtargosz\ActionOtp\Tests\Fixtures\AlphaEightAction;
use Jtargosz\ActionOtp\Tests\Fixtures\AnyDeviceAction;
use Jtargosz\ActionOtp\Tests\Fixtures\BrokenSendWithAction;
use Jtargosz\ActionOtp\Tests\Fixtures\ConfirmLoginAction;
use Jtargosz\ActionOtp\Tests\Fixtures\FixtureSms;
use Jtargosz\ActionOtp\Tests\Fixtures\InheritsAlphaEightAction;
use Jtargosz\ActionOtp\Tests\Fixtures\ShortLivedAction;
use Jtargosz\ActionOtp\Tests\Fixtures\SmsAction;
use Jtargosz\ActionOtp\Tests\TestCase;

class ActionAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('action-otp.send_cooldown', 0);
        $this->browser();
    }

    public function test_ttl_attribute_overrides_config(): void
    {
        $this->freezeTime();
        $this->fixCode(self::CODE);

        $this->sendCode('ttl@example.com', new ShortLivedAction);

        $this->assertSame(now()->addMinute()->getTimestamp(), $this->lastMessage()->expiresAt->getTimestamp());

        $this->travel(61)->seconds();

        $this->assertSame(OtpStatus::Expired, ActionOtp::to('ttl@example.com')->verify(self::CODE)->status);
    }

    public function test_format_and_length_attributes_are_inherited(): void
    {
        $this->sendCode('alpha@example.com', new AlphaEightAction);
        $this->assertMatchesRegularExpression('/^[A-Z]{8}$/', $this->lastMessage()->code);

        $this->sendCode('child@example.com', new InheritsAlphaEightAction);
        $this->assertMatchesRegularExpression('/^[A-Z]{8}$/', $this->lastMessage()->code);

        $this->sendCode('plain@example.com', new ConfirmLoginAction);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $this->lastMessage()->code);
    }

    public function test_send_with_attribute_picks_the_notification(): void
    {
        ActionOtp::to('+48500100200')->send(new SmsAction, Notification::route('vonage', '+48500100200'));

        Notification::assertSentOnDemand(FixtureSms::class);
        Notification::assertNotSentTo(Notification::route('vonage', '+48500100200'), CodeMail::class);
    }

    public function test_send_with_must_be_a_notification(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sendCode('bad@example.com', new BrokenSendWithAction);
    }

    public function test_any_device_action_verifies_without_session_or_token(): void
    {
        $this->fixCode(self::CODE);

        $this->sendCode('any@example.com', new AnyDeviceAction);

        $this->noBrowser();

        $result = ActionOtp::to('any@example.com')->verify(self::CODE);

        $this->assertTrue($result->ok());
        $this->assertSame('any-device', $result->payload);
        $this->assertSame(OtpStatus::Empty, ActionOtp::to('any@example.com')->verify(self::CODE)->status);
    }

    public function test_new_any_device_send_replaces_the_previous_one(): void
    {
        $this->fixCode('111111');
        $this->sendCode('any@example.com', new AnyDeviceAction);

        $this->browser('b');
        $this->fixCode('222222');
        $this->sendCode('any@example.com', new AnyDeviceAction);

        $this->noBrowser();
        $this->assertSame(OtpStatus::Mismatch, ActionOtp::to('any@example.com')->peek('111111')->status);
        $this->assertTrue(ActionOtp::to('any@example.com')->verify('222222')->ok());
    }
}
