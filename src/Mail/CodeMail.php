<?php

namespace Jtargosz\ActionOtp\Mail;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodeMail extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * Only the code and its expiry. The pending action and the notifiable are
     * dropped on purpose so they never land in the queue payload or failed_jobs.
     *
     * @var array{code: string, expires_at: DateTimeInterface}
     */
    protected array $record;

    /**
     * @param  array{action: mixed, notifiable: mixed, code: string, expires_at: DateTimeInterface}  $record
     */
    public function __construct(array $record)
    {
        $this->record = [
            'code' => $record['code'],
            'expires_at' => $record['expires_at'],
        ];
    }

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
            ->subject('Verification code')
            ->line('Your code: '.$this->record['code'])
            ->line('Valid until '.$this->record['expires_at']->format('Y-m-d H:i T'))
            ->line('If this was not you, ignore this message.');
    }
}
