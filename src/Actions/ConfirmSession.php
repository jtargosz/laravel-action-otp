<?php

namespace Jtargosz\ActionOtp\Actions;

use Jtargosz\ActionOtp\Contracts\VerifiableAction;

/**
 * Built-in action behind the otp.confirm middleware. The package controllers
 * mark the session after it is verified.
 */
final class ConfirmSession implements VerifiableAction
{
    public const PURPOSE = 'action-otp.confirm';

    public const SESSION_KEY = 'action-otp.confirmed_at';

    public function handle(): mixed
    {
        return true;
    }
}
