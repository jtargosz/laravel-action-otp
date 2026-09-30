<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Jtargosz\ActionOtp\Contracts\StoresCodes;
use Jtargosz\ActionOtp\Exceptions\MissingIdentifier;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Mail\CodeMail;
use Jtargosz\ActionOtp\Support\Keys;
use Jtargosz\ActionOtp\Support\OtpStatus;
use Jtargosz\ActionOtp\Tests\Fixtures\AlphanumericAction;
use Jtargosz\ActionOtp\Tests\Fixtures\ConfirmLoginAction;
use Jtargosz\ActionOtp\Tests\Fixtures\SecretHoldingAction;
use Jtargosz\ActionOtp\Tests\Fixtures\ThrowingAction;
use Jtargosz\ActionOtp\Tests\TestCase;
use RuntimeException;

/**
 * Core flow over the explicit challenge token (API style, no session).
 */
class CodeFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->noBrowser();
    }

    public function test_api_flow_with_challenge_token(): void
    {
        $this->fixCode('482913');

        $send = ActionOtp::to('user@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'user@example.com')
        );

        $this->assertSame(OtpStatus::Sent, $send->status);
        $this->assertIsString($send->challenge);
        $this->assertSame(40, strlen($send->challenge));

        $otp = ActionOtp::to('user@example.com')->withChallenge($send->challenge);

        $this->assertSame(OtpStatus::Mismatch, $otp->peek('000000')->status);
        $this->assertSame(OtpStatus::Matched, $otp->peek('482913')->status);

        $done = $otp->verify('482913');
        $this->assertTrue($done->ok());
        $this->assertSame('logged-in', $done->payload);

        $this->assertSame(OtpStatus::Empty, $otp->peek('482913')->status);
    }

    public function test_missing_or_wrong_challenge_is_empty_and_not_an_attempt(): void
    {
        $this->fixCode('482913');

        $send = ActionOtp::to('token@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'token@example.com')
        );

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(OtpStatus::Empty, ActionOtp::to('token@example.com')->verify('482913')->status);
            $this->assertSame(
                OtpStatus::Empty,
                ActionOtp::to('token@example.com')->withChallenge('forged')->verify('482913')->status
            );
        }

        $this->assertTrue(
            ActionOtp::to('token@example.com')->withChallenge($send->challenge)->verify('482913')->ok()
        );
    }

    public function test_resend_keeps_the_challenge_and_replaces_the_code(): void
    {
        config()->set('action-otp.send_cooldown', 0);

        $this->fixCode('111111');
        $send = ActionOtp::to('again@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'again@example.com')
        );

        $this->fixCode('222222');
        $otp = ActionOtp::to('again@example.com')->withChallenge($send->challenge);

        $this->assertSame(OtpStatus::Sent, $otp->resend()->status);
        $this->assertSame(OtpStatus::Mismatch, $otp->peek('111111')->status);
        $this->assertTrue($otp->verify('222222')->ok());
    }

    public function test_code_with_leading_zero_matches(): void
    {
        $this->fixCode('012345');

        $send = ActionOtp::to('zero@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'zero@example.com')
        );

        $this->assertTrue(
            ActionOtp::to('zero@example.com')->withChallenge($send->challenge)->verify('012345')->ok()
        );
    }

    public function test_letter_codes_are_trimmed_and_case_insensitive(): void
    {
        $this->fixCode('AB12CD');

        $send = ActionOtp::to('alnum@example.com')->send(
            new AlphanumericAction,
            Notification::route('mail', 'alnum@example.com')
        );

        $this->assertTrue(
            ActionOtp::to('alnum@example.com')->withChallenge($send->challenge)->verify(' ab12cd ')->ok()
        );
    }

    public function test_code_is_consumed_before_a_throwing_handle(): void
    {
        $this->fixCode('482913');

        $send = ActionOtp::to('boom@example.com')->send(
            new ThrowingAction,
            Notification::route('mail', 'boom@example.com')
        );

        $otp = ActionOtp::to('boom@example.com')->withChallenge($send->challenge);

        try {
            $otp->verify('482913');
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException) {
        }

        $this->assertSame(OtpStatus::Empty, $otp->verify('482913')->status);
    }

    public function test_expired_code_is_rejected_and_removed(): void
    {
        $this->fixCode('482913');

        $send = ActionOtp::to('old@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'old@example.com')
        );

        $this->travel(16)->minutes();

        $otp = ActionOtp::to('old@example.com')->withChallenge($send->challenge);

        $this->assertSame(OtpStatus::Expired, $otp->peek('482913')->status);
        $this->assertSame(OtpStatus::Empty, $otp->peek('482913')->status);
    }

    public function test_malformed_record_fails_closed(): void
    {
        Cache::put(Keys::record(Keys::hash('junk@example.com'), Keys::hash('token')), ['broken' => true], 60);

        $this->assertSame(
            OtpStatus::Empty,
            ActionOtp::to('junk@example.com')->withChallenge('token')->peek('anything')->status
        );
        $this->assertNull(app(StoresCodes::class)->get(Keys::hash('junk@example.com'), Keys::hash('token')));
    }

    public function test_missing_identifier_throws(): void
    {
        $this->expectException(MissingIdentifier::class);

        ActionOtp::verify('123456');
    }

    public function test_empty_purpose_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ActionOtp::to('user@example.com')->for('  ');
    }

    public function test_invalid_notification_class_throws(): void
    {
        config()->set('action-otp.notification', \stdClass::class);

        $this->expectException(InvalidArgumentException::class);

        ActionOtp::to('bad@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'bad@example.com')
        );
    }

    public function test_notifiable_without_notify_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ActionOtp::to('bad@example.com')->send(new ConfirmLoginAction, new \stdClass);
    }

    public function test_messages_are_translated(): void
    {
        $send = ActionOtp::to('lang@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'lang@example.com')
        );

        $this->assertSame('We sent a verification code.', $send->message);

        app()->setLocale('pl');

        $this->assertSame(
            'Kod niezgodny.',
            ActionOtp::to('lang@example.com')->withChallenge($send->challenge)->peek('bad-code')->message
        );
    }

    public function test_queued_mail_carries_only_the_message(): void
    {
        $this->fixCode('482913');

        ActionOtp::to('queue@example.com')->send(
            new SecretHoldingAction,
            Notification::route('mail', 'queue@example.com')
        );

        Notification::assertSentOnDemand(CodeMail::class, function (CodeMail $mail) {
            $payload = serialize($mail);

            $this->assertInstanceOf(ShouldBeEncrypted::class, $mail);
            $this->assertStringNotContainsString('top-secret-value', $payload);
            $this->assertStringNotContainsString(SecretHoldingAction::class, $payload);
            $this->assertSame('482913', $mail->otp->code);

            return true;
        });
    }

    public function test_integer_identifier(): void
    {
        $this->fixCode('482913');

        $send = ActionOtp::to(12345)->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'int@example.com')
        );

        $this->assertTrue(ActionOtp::to('12345')->withChallenge($send->challenge)->verify('482913')->ok());
    }

    public function test_result_to_array_contains_the_challenge(): void
    {
        $send = ActionOtp::to('array@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'array@example.com')
        );

        $this->assertSame(
            ['status' => 'sent', 'message' => 'We sent a verification code.', 'payload' => null, 'challenge' => $send->challenge],
            $send->toArray()
        );
    }
}
