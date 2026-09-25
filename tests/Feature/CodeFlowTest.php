<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Contracts\StoresCodes;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use Jtargosz\ActionOtp\Exceptions\MissingIdentifier;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Support\OtpStatus;
use Jtargosz\ActionOtp\Tests\TestCase;

class ConfirmLoginAction implements VerifiableAction
{
    public function handle(): mixed
    {
        return 'logged-in';
    }
}

class CodeFlowTest extends TestCase
{
    public function test_send_verify_flow(): void
    {
        Notification::fake();

        $send = ActionOtp::to('user@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'user@example.com')
        );

        $this->assertSame(OtpStatus::Sent, $send->status);

        $record = app(StoresCodes::class)
            ->scope('user@example.com')->get();

        $this->assertNotEmpty($record['code']);

        $wrong = ActionOtp::to('user@example.com')->peek('000000');
        $this->assertSame(OtpStatus::Mismatch, $wrong->status);

        $good = ActionOtp::to('user@example.com')->peek($record['code']);
        $this->assertSame(OtpStatus::Matched, $good->status);

        $done = ActionOtp::to('user@example.com')->verify($record['code']);
        $this->assertTrue($done->ok());
        $this->assertSame('logged-in', $done->payload);

        $empty = ActionOtp::to('user@example.com')->peek($record['code']);
        $this->assertSame(OtpStatus::Empty, $empty->status);
    }

    public function test_throttle_after_too_many_attempts(): void
    {
        Notification::fake();

        ActionOtp::to('locked@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'locked@example.com')
        );

        for ($i = 0; $i < 3; $i++) {
            ActionOtp::to('locked@example.com')->peek('bad-code');
        }

        $blocked = ActionOtp::to('locked@example.com')->peek('bad-code');

        $this->assertSame(OtpStatus::Throttled, $blocked->status);
    }

    public function test_send_cooldown_blocks_immediate_second_send(): void
    {
        Notification::fake();

        $notifiable = Notification::route('mail', 'cool@example.com');

        $first = ActionOtp::to('cool@example.com')->send(new ConfirmLoginAction, $notifiable);
        $this->assertSame(OtpStatus::Sent, $first->status);

        $second = ActionOtp::to('cool@example.com')->send(new ConfirmLoginAction, $notifiable);
        $this->assertSame(OtpStatus::Throttled, $second->status);

        ActionOtp::to('cool@example.com')->clear();

        $third = ActionOtp::to('cool@example.com')->send(new ConfirmLoginAction, $notifiable);
        $this->assertSame(OtpStatus::Sent, $third->status);
    }

    public function test_expired_code_is_rejected_and_removed(): void
    {
        config()->set('action-otp.ttl_minutes', 15);
        Notification::fake();

        ActionOtp::to('old@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'old@example.com')
        );

        $code = app(StoresCodes::class)
            ->scope('old@example.com')->get()['code'];

        Carbon::setTestNow(Carbon::now()->addMinutes(16));

        try {
            $result = ActionOtp::to('old@example.com')->peek($code);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(OtpStatus::Expired, $result->status);
        $this->assertSame(
            OtpStatus::Empty,
            ActionOtp::to('old@example.com')->peek($code)->status
        );
    }

    public function test_resend_issues_a_new_code(): void
    {
        config()->set('action-otp.send_cooldown', 0);
        Notification::fake();

        ActionOtp::to('again@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'again@example.com')
        );

        $first = app(StoresCodes::class)
            ->scope('again@example.com')->get()['code'];

        $resend = ActionOtp::to('again@example.com')->resend();
        $this->assertSame(OtpStatus::Sent, $resend->status);

        $second = app(StoresCodes::class)
            ->scope('again@example.com')->get()['code'];

        $this->assertNotSame($first, $second);
        $this->assertSame(
            OtpStatus::Mismatch,
            ActionOtp::to('again@example.com')->peek($first)->status
        );
    }

    public function test_clear_removes_the_code(): void
    {
        Notification::fake();

        ActionOtp::to('gone@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'gone@example.com')
        );

        ActionOtp::to('gone@example.com')->clear();

        $this->assertSame(
            OtpStatus::Empty,
            ActionOtp::to('gone@example.com')->peek('whatever')->status
        );
    }

    public function test_missing_identifier_throws(): void
    {
        $this->expectException(MissingIdentifier::class);

        ActionOtp::verify('123456');
    }

    public function test_invalid_notification_class_throws(): void
    {
        config()->set('action-otp.notification', \stdClass::class);
        Notification::fake();

        $this->expectException(\InvalidArgumentException::class);

        ActionOtp::to('bad@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'bad@example.com')
        );
    }

    public function test_malformed_record_fails_closed(): void
    {
        app(StoresCodes::class)
            ->scope('junk@example.com')->put(['broken' => true]);

        $this->assertSame(
            OtpStatus::Empty,
            ActionOtp::to('junk@example.com')->peek('anything')->status
        );
    }

    public function test_failed_send_does_not_trigger_cooldown(): void
    {
        Notification::fake();

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

        $retry = ActionOtp::to('down@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'down@example.com')
        );

        $this->assertSame(OtpStatus::Sent, $retry->status);
    }

    public function test_integer_identifier_and_code(): void
    {
        Notification::fake();

        $send = ActionOtp::to(12345)->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'int@example.com')
        );

        $this->assertSame(OtpStatus::Sent, $send->status);

        $record = app(StoresCodes::class)->scope('12345')->get();
        $this->assertNotEmpty($record['code']);

        $wrong = ActionOtp::to(12345)->verify(0);
        $this->assertSame(OtpStatus::Mismatch, $wrong->status);

        $done = ActionOtp::to(12345)->verify($record['code']);

        $this->assertTrue($done->ok());
        $this->assertSame('logged-in', $done->payload);
    }
}
