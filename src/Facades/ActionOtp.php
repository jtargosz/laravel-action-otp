<?php

namespace Jtargosz\ActionOtp\Facades;

use Illuminate\Support\Facades\Facade;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use Jtargosz\ActionOtp\Support\OtpResult;

/**
 * @method static static to(string|int $identifier)
 * @method static OtpResult send(VerifiableAction $action, mixed $notifiable)
 * @method static OtpResult verify(string|int $code)
 * @method static OtpResult peek(string|int $code)
 * @method static OtpResult resend()
 * @method static void clear()
 */
class ActionOtp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'action-otp';
    }
}
