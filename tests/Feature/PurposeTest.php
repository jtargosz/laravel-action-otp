<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Support\OtpStatus;
use Jtargosz\ActionOtp\Tests\Fixtures\ConfirmLoginAction;
use Jtargosz\ActionOtp\Tests\TestCase;

class PurposeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('action-otp.send_cooldown', 0);
        $this->browser();
    }

    private function send(string $purpose, string $code, string $result): ?string
    {
        $this->fixCode($code);

        return ActionOtp::to('user@example.com')->for($purpose)->send(
            new ConfirmLoginAction($result),
            Notification::route('mail', 'user@example.com')
        )->challenge;
    }

    public function test_one_identifier_can_have_several_pending_actions(): void
    {
        $this->send('register', '111111', 'registered');
        $this->send('password-reset', '222222', 'reset');

        $this->assertSame('reset', ActionOtp::to('user@example.com')->for('password-reset')->verify('222222')->payload);
        $this->assertSame('registered', ActionOtp::to('user@example.com')->for('register')->verify('111111')->payload);
    }

    public function test_default_purpose_is_separate_from_named_ones(): void
    {
        $this->send('register', '111111', 'registered');

        $this->assertSame(OtpStatus::Empty, ActionOtp::to('user@example.com')->verify('111111')->status);
        $this->assertSame(OtpStatus::Empty, ActionOtp::to('user@example.com')->for('default')->verify('111111')->status);
    }

    public function test_token_from_one_purpose_does_not_work_for_another(): void
    {
        $token = $this->send('register', '111111', 'registered');
        $this->noBrowser();

        $this->assertSame(
            OtpStatus::Empty,
            ActionOtp::to('user@example.com')->for('password-reset')->withChallenge($token)->verify('111111')->status
        );
        $this->assertTrue(
            ActionOtp::to('user@example.com')->for('register')->withChallenge($token)->verify('111111')->ok()
        );
    }

    public function test_attempt_limit_is_shared_across_purposes(): void
    {
        $this->send('register', '111111', 'registered');
        $this->send('password-reset', '222222', 'reset');

        ActionOtp::to('user@example.com')->for('register')->peek('bad-code');
        ActionOtp::to('user@example.com')->for('password-reset')->peek('bad-code');
        ActionOtp::to('user@example.com')->for('register')->peek('bad-code');

        $this->assertSame(
            OtpStatus::Throttled,
            ActionOtp::to('user@example.com')->for('password-reset')->peek('222222')->status
        );
    }
}
