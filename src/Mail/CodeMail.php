<?php

namespace Jtargosz\ActionOtp\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodeMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected array $record) {}

    public function via(object $notifiable): array
    {
        return (array) config('action-otp.channels', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verification code')
            ->line('Your code: '.$this->record['code'])
            ->line('Valid until '.$this->record['expires_at']->format('Y-m-d H:i T'))
            ->line('If this was not you, ignore this message.');
    }
}
