<?php

namespace Jtargosz\ActionOtp\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Jtargosz\ActionOtp\Support\OtpMessage;

/**
 * Default notification. Gets only the OtpMessage (code, expiry, purpose,
 * link), never the pending action, and is encrypted on the queue.
 *
 * Publish the view with `php artisan vendor:publish --tag=action-otp-views`.
 */
class CodeMail extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public OtpMessage $otp) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return (array) config('action-otp.channels', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject((string) __('action-otp::action-otp.mail.subject', ['app' => (string) config('app.name', 'Laravel')]))
            ->markdown('action-otp::mail.code', [
                'code' => $this->otp->code,
                'expires' => Carbon::instance($this->otp->expiresAt)
                    ->setTimezone((string) config('app.timezone', 'UTC'))
                    ->locale(app()->getLocale())
                    ->isoFormat('LLL'),
                'link' => $this->otp->link,
            ]);
    }
}
