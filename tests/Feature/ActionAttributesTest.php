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

        Notification::fake();
        config()->set('action-otp.send_cooldown', 0);
        $this->browser();
    }

    public function test_ttl_attribute_overrides_config(): void
    {
        $this->fixCode('482913');

        ActionOtp::to('ttl@example.com')->send(new ShortLivedAction, Notification::route('mail', 'ttl@example.com'));

        $this->assertEqualsWithDelta(now()->addMinute()->getTimestamp(), $this->lastMessage()->expiresAt->getTimestamp(), 3);

        $this->travel(61)->seconds();

        $this->assertSame(OtpStatus::Expired, ActionOtp::to('ttl@example.com')->verify('482913')->status);
    }

    public function test_format_and_length_attributes_are_inherited(): void
    {
        ActionOtp::to('alpha@example.com')->send(new AlphaEightAction, Notification::route('mail', 'alpha@example.com'));
        $this->assertMatchesRegularExpression('/^[A-Z]{8}$/', $this->lastMessage()->code);

        ActionOtp::to('child@example.com')->send(new InheritsAlphaEightAction, Notification::route('mail', 'child@example.com'));
        $this->assertMatchesRegularExpression('/^[A-Z]{8}$/', $this->lastMessage()->code);

        ActionOtp::to('plain@example.com')->send(new ConfirmLoginAction, Notification::route('mail', 'plain@example.com'));
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

        ActionOtp::to('bad@example.com')->send(new BrokenSendWithAction, Notification::route('mail', 'bad@example.com'));
    }

    public function test_any_device_action_verifies_without_session_or_token(): void
    {
        $this->fixCode('482913');

        ActionOtp::to('any@example.com')->send(new AnyDeviceAction, Notification::route('mail', 'any@example.com'));

        $this->noBrowser();

        $result = ActionOtp::to('any@example.com')->verify('482913');

        $this->assertTrue($result->ok());
        $this->assertSame('any-device', $result->payload);
        $this->assertSame(OtpStatus::Empty, ActionOtp::to('any@example.com')->verify('482913')->status);
    }

    public function test_new_any_device_send_replaces_the_previous_one(): void
    {
        $this->fixCode('111111');
        ActionOtp::to('any@example.com')->send(new AnyDeviceAction, Notification::route('mail', 'any@example.com'));

        $this->browser('b');
        $this->fixCode('222222');
        ActionOtp::to('any@example.com')->send(new AnyDeviceAction, Notification::route('mail', 'any@example.com'));

        $this->noBrowser();
        $this->assertSame(OtpStatus::Mismatch, ActionOtp::to('any@example.com')->peek('111111')->status);
        $this->assertTrue(ActionOtp::to('any@example.com')->verify('222222')->ok());
    }

    public function test_action_without_attribute_still_needs_the_session(): void
    {
        $this->fixCode('482913');

        ActionOtp::to('plain@example.com')->send(new ConfirmLoginAction, Notification::route('mail', 'plain@example.com'));

        $this->noBrowser();

        $this->assertSame(OtpStatus::Empty, ActionOtp::to('plain@example.com')->verify('482913')->status);
    }
}
