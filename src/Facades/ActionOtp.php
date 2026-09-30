<?php

namespace Jtargosz\ActionOtp\Facades;

use Illuminate\Support\Facades\Facade;
use Jtargosz\ActionOtp\Contracts\ManagesCodes;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use Jtargosz\ActionOtp\Services\CodeManager;
use Jtargosz\ActionOtp\Support\OtpResult;

/**
 * @method static ManagesCodes to(string|int $identifier)
 * @method static ManagesCodes for(string $purpose)
 * @method static ManagesCodes withChallenge(?string $challenge)
 * @method static OtpResult send(VerifiableAction $action, object $notifiable)
 * @method static OtpResult verify(string $code)
 * @method static OtpResult peek(string $code)
 * @method static OtpResult resend()
 * @method static void clear()
 *
 * @see CodeManager
 */
class ActionOtp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'action-otp';
    }
}
