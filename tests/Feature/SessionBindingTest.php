<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Support\OtpStatus;
use Jtargosz\ActionOtp\Tests\Fixtures\ConfirmLoginAction;
use Jtargosz\ActionOtp\Tests\TestCase;

class SessionBindingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('action-otp.send_cooldown', 0);
    }

    private function sendFrom(string $browser, string $code, string $result = 'logged-in'): ?string
    {
        $this->browser($browser);
        $this->fixCode($code);

        return ActionOtp::to('victim@example.com')->send(
            new ConfirmLoginAction($result),
            Notification::route('mail', 'victim@example.com')
        )->challenge;
    }

    public function test_same_session_verifies_without_a_token(): void
    {
        $this->sendFrom('a', '111111');

        $result = ActionOtp::to('victim@example.com')->verify('111111');

        $this->assertTrue($result->ok());
        $this->assertSame('logged-in', $result->payload);
    }

    public function test_other_browser_gets_empty(): void
    {
        $this->sendFrom('a', '111111');

        $this->browser('b');
        $this->assertSame(OtpStatus::Empty, ActionOtp::to('victim@example.com')->verify('111111')->status);

        $this->noBrowser();
        $this->assertSame(OtpStatus::Empty, ActionOtp::to('victim@example.com')->verify('111111')->status);

        $this->browser('a');
        $this->assertTrue(ActionOtp::to('victim@example.com')->verify('111111')->ok());
    }

    public function test_flushed_session_loses_the_code(): void
    {
        $this->sendFrom('a', '111111');

        $this->browser('a')->flush();

        $this->assertSame(OtpStatus::Empty, ActionOtp::to('victim@example.com')->verify('111111')->status);
    }

    public function test_attacker_send_cannot_replace_the_victims_action(): void
    {
        $this->sendFrom('a', '111111', 'victim-action');

        // Attacker in another browser starts a flow for the victim's email.
        $this->sendFrom('b', '222222', 'attacker-action');

        // The victim's browser still holds only the victim's own action.
        $this->browser('a');
        $this->assertSame(OtpStatus::Mismatch, ActionOtp::to('victim@example.com')->verify('222222')->status);

        $result = ActionOtp::to('victim@example.com')->verify('111111');
        $this->assertTrue($result->ok());
        $this->assertSame('victim-action', $result->payload);
    }

    public function test_new_send_in_the_same_session_replaces_the_previous_code(): void
    {
        $first = $this->sendFrom('a', '111111');
        $this->sendFrom('a', '222222');

        $this->assertSame(OtpStatus::Mismatch, ActionOtp::to('victim@example.com')->peek('111111')->status);
        $this->assertSame(
            OtpStatus::Empty,
            ActionOtp::to('victim@example.com')->withChallenge($first)->peek('111111')->status
        );
        $this->assertTrue(ActionOtp::to('victim@example.com')->verify('222222')->ok());
    }

    public function test_resend_and_clear_use_the_session(): void
    {
        $this->sendFrom('a', '111111');
        $this->fixCode('333333');

        $this->assertSame(OtpStatus::Sent, ActionOtp::to('victim@example.com')->resend()->status);
        $this->assertSame(OtpStatus::Matched, ActionOtp::to('victim@example.com')->peek('333333')->status);

        ActionOtp::to('victim@example.com')->clear();

        $this->assertSame(OtpStatus::Empty, ActionOtp::to('victim@example.com')->peek('333333')->status);
    }
}
