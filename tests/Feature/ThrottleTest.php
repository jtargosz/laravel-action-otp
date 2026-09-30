<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Support\Keys;
use Jtargosz\ActionOtp\Support\OtpResult;
use Jtargosz\ActionOtp\Support\OtpStatus;
use Jtargosz\ActionOtp\Tests\Fixtures\ConfirmLoginAction;
use Jtargosz\ActionOtp\Tests\TestCase;
use RuntimeException;

class ThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->noBrowser();
        $this->fixCode('482913');
    }

    private function send(string $identifier): OtpResult
    {
        return ActionOtp::to($identifier)->send(new ConfirmLoginAction, Notification::route('mail', $identifier));
    }

    public function test_lock_after_too_many_attempts(): void
    {
        $otp = ActionOtp::to('locked@example.com')->withChallenge($this->send('locked@example.com')->challenge);

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(OtpStatus::Mismatch, $otp->peek('bad-code')->status);
        }

        $this->assertSame(OtpStatus::Throttled, $otp->peek('482913')->status);
        $this->assertSame(OtpStatus::Throttled, $otp->verify('482913')->status);
    }

    public function test_send_cooldown_blocks_immediate_second_send(): void
    {
        $this->assertSame(OtpStatus::Sent, $this->send('cool@example.com')->status);
        $this->assertSame(OtpStatus::Throttled, $this->send('cool@example.com')->status);

        $this->travel(31)->seconds();

        $this->assertSame(OtpStatus::Sent, $this->send('cool@example.com')->status);
    }

    public function test_send_from_inside_notify_sees_the_cooldown(): void
    {
        // notify() runs outside the slot lock, like a parallel request would.
        $notifiable = new class
        {
            public ?OtpResult $nested = null;

            public function notify(mixed $notification): void
            {
                $this->nested = ActionOtp::to('race@example.com')->send(
                    new ConfirmLoginAction,
                    Notification::route('mail', 'race@example.com')
                );
            }
        };

        $this->assertSame(
            OtpStatus::Sent,
            ActionOtp::to('race@example.com')->send(new ConfirmLoginAction, $notifiable)->status
        );
        $this->assertInstanceOf(OtpResult::class, $notifiable->nested);
        $this->assertSame(OtpStatus::Throttled, $notifiable->nested->status);
    }

    public function test_failed_send_does_not_trigger_cooldown(): void
    {
        $broken = new class
        {
            public function notify(mixed $notification): void
            {
                throw new RuntimeException('mail down');
            }
        };

        try {
            ActionOtp::to('down@example.com')->send(new ConfirmLoginAction, $broken);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException) {
        }

        $this->assertSame(OtpStatus::Sent, $this->send('down@example.com')->status);
    }

    public function test_new_code_does_not_lift_the_lockout(): void
    {
        config()->set('action-otp.send_cooldown', 0);

        $otp = ActionOtp::to('lockout@example.com')->withChallenge($this->send('lockout@example.com')->challenge);

        for ($i = 0; $i < 3; $i++) {
            $otp->peek('bad-code');
        }

        $this->assertSame(OtpStatus::Throttled, $this->send('lockout@example.com')->status);
        $this->assertSame(OtpStatus::Throttled, $otp->resend()->status);
        $this->assertSame(OtpStatus::Throttled, $otp->verify('482913')->status);
    }

    public function test_wrong_attempts_carry_over_to_a_new_code(): void
    {
        config()->set('action-otp.send_cooldown', 0);

        $otp = ActionOtp::to('carry@example.com')->withChallenge($this->send('carry@example.com')->challenge);

        $otp->peek('bad-code');
        $otp->peek('bad-code');

        $this->assertSame(OtpStatus::Sent, $otp->resend()->status);

        // Third wrong try overall hits max_attempts (3) although the code is new.
        $otp->peek('bad-code');

        $this->assertSame(OtpStatus::Throttled, $otp->peek('482913')->status);
    }

    public function test_clear_does_not_lift_lockout_or_cooldown(): void
    {
        $otp = ActionOtp::to('cancel@example.com')->withChallenge($this->send('cancel@example.com')->challenge);
        $otp->clear();

        $this->assertSame(OtpStatus::Empty, $otp->peek('482913')->status);
        $this->assertSame(OtpStatus::Throttled, $this->send('cancel@example.com')->status);

        $this->travel(31)->seconds();
        $otp = ActionOtp::to('cancel@example.com')->withChallenge($this->send('cancel@example.com')->challenge);

        for ($i = 0; $i < 3; $i++) {
            $otp->peek('bad-code');
        }

        $otp->clear();
        $this->travel(31)->seconds();

        $this->assertSame(OtpStatus::Throttled, $this->send('cancel@example.com')->status);
    }

    public function test_successful_verify_resets_throttling(): void
    {
        $otp = ActionOtp::to('reset@example.com')->withChallenge($this->send('reset@example.com')->challenge);
        $otp->peek('bad-code');
        $otp->peek('bad-code');

        $this->assertTrue($otp->verify('482913')->ok());

        $send = $this->send('reset@example.com');
        $this->assertSame(OtpStatus::Sent, $send->status);

        $otp = ActionOtp::to('reset@example.com')->withChallenge($send->challenge);
        $otp->peek('bad-code');
        $otp->peek('bad-code');

        $this->assertSame(OtpStatus::Mismatch, $otp->peek('bad-code')->status);
    }

    public function test_operations_are_serialized_with_the_identifier_slot(): void
    {
        $otp = ActionOtp::to('busy@example.com')->withChallenge($this->send('busy@example.com')->challenge);

        $slot = Cache::lock(Keys::identifier(Keys::hash('busy@example.com'), ':verify'), 10);
        $this->assertTrue($slot->get());

        try {
            $this->assertSame(OtpStatus::Throttled, $otp->peek('482913')->status);
            $this->assertSame(OtpStatus::Throttled, $otp->verify('482913')->status);
        } finally {
            $slot->release();
        }

        $this->assertSame(OtpStatus::Matched, $otp->peek('482913')->status);
    }
}
