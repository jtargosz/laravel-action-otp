<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Events\CodeFailed;
use Jtargosz\ActionOtp\Events\CodeSent;
use Jtargosz\ActionOtp\Events\CodeVerified;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Mail\CodeMail;
use Jtargosz\ActionOtp\Support\OtpMessage;
use Jtargosz\ActionOtp\Tests\Fixtures\SecretHoldingAction;
use Jtargosz\ActionOtp\Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_mail_renders_in_the_request_locale(): void
    {
        $this->browser();
        $this->fixCode(self::CODE);
        app()->setLocale('pl');

        $this->sendCode('pl@example.com');

        Notification::assertSentOnDemand(CodeMail::class, function (CodeMail $mail, array $channels, object $notifiable, ?string $locale) {
            $this->assertSame('pl', $locale);

            return true;
        });

        $mail = (new CodeMail($this->lastMessage()))->toMail(new AnonymousNotifiable);
        $html = (string) $mail->render();

        $this->assertSame('Twój kod weryfikacyjny do Laravel', $mail->subject);
        $this->assertStringContainsString(self::CODE, $html);
        $this->assertStringContainsString('Kod jest ważny do', $html);
        $this->assertStringContainsString(
            Carbon::instance($this->lastMessage()->expiresAt)->locale('pl')->isoFormat('LLL'),
            $html
        );
        $this->assertStringNotContainsString('action-otp::', $html);
    }

    public function test_mail_shows_the_link_button_only_with_a_link(): void
    {
        $withLink = new OtpMessage(self::CODE, now()->addMinutes(5), 'default', 'https://example.com/otp/link?x=1');
        $without = new OtpMessage(self::CODE, now()->addMinutes(5));

        $this->assertStringContainsString(
            'https://example.com/otp/link?x=1',
            (string) (new CodeMail($withLink))->toMail(new AnonymousNotifiable)->render()
        );
        $this->assertStringNotContainsString(
            'otp/link',
            (string) (new CodeMail($without))->toMail(new AnonymousNotifiable)->render()
        );
    }

    public function test_sms_text_ends_with_the_origin_bound_line(): void
    {
        $message = new OtpMessage(self::CODE, now()->addMinutes(5));

        $lines = explode("\n", $message->smsText());

        $this->assertSame(self::CODE.' is your Laravel verification code.', $lines[0]);
        $this->assertSame('', $lines[1]);
        $this->assertSame('@example.com #'.self::CODE, end($lines));

        config()->set('action-otp.sms_host', 'app.example.org');
        $this->assertStringEndsWith("\n\n@app.example.org #".self::CODE, $message->smsText());
        $this->assertStringEndsWith("\n\n@shop.example #".self::CODE, $message->smsText('shop.example'));

        app()->setLocale('pl');
        $this->assertStringStartsWith(self::CODE.' to Twój kod weryfikacyjny do Laravel.', $message->smsText());
    }

    public function test_sms_text_without_any_host_is_plain(): void
    {
        config()->set('app.url', '');

        $this->assertSame(
            self::CODE.' is your Laravel verification code.',
            (new OtpMessage(self::CODE, now()->addMinutes(5)))->smsText()
        );
    }

    public function test_events_carry_no_code_or_action(): void
    {
        Event::fake([CodeSent::class, CodeVerified::class, CodeFailed::class]);
        $this->browser();
        $this->fixCode(self::CODE);

        $this->sendCode('events@example.com', new SecretHoldingAction, 'signup');
        ActionOtp::to('events@example.com')->for('signup')->peek('000000');
        ActionOtp::to('events@example.com')->for('signup')->verify(self::CODE);

        Event::assertDispatched(CodeSent::class, function (CodeSent $event) {
            $this->assertSame('events@example.com', $event->identifier);
            $this->assertSame('signup', $event->purpose);
            $this->assertStringNotContainsString(self::CODE, serialize($event));
            $this->assertStringNotContainsString('top-secret-value', serialize($event));

            return true;
        });
        Event::assertDispatched(CodeFailed::class, fn (CodeFailed $e) => $e->purpose === 'signup');
        Event::assertDispatched(
            CodeVerified::class,
            fn (CodeVerified $e) => $e->identifier === 'events@example.com' && $e->payload === 'top-secret-value'
        );
    }
}
