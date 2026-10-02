<?php

namespace Jtargosz\ActionOtp\Actions;

use Illuminate\Support\Facades\Date;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;

/**
 * Built-in action behind the otp.confirm middleware: marks the current
 * session as confirmed.
 */
final class ConfirmSession implements VerifiableAction
{
    public const PURPOSE = 'action-otp.confirm';

    public const SESSION_KEY = 'action-otp.confirmed_at';

    public function handle(): mixed
    {
        request()->session()->put(self::SESSION_KEY, Date::now()->unix());

        return true;
    }
}
