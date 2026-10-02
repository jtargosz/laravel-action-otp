<?php

namespace Jtargosz\ActionOtp\Tests\Fixtures;

use Illuminate\Notifications\Notification;
use Jtargosz\ActionOtp\Support\OtpMessage;

class FixtureSms extends Notification
{
    public function __construct(public OtpMessage $otp) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['vonage'];
    }
}
