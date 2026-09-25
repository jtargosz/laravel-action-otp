<?php

namespace Jtargosz\ActionOtp\Contracts;

use Jtargosz\ActionOtp\Support\OtpResult;

interface ManagesCodes
{
    public function to(string|int $identifier): static;

    public function send(VerifiableAction $action, mixed $notifiable): OtpResult;

    public function verify(string|int $code): OtpResult;

    public function peek(string|int $code): OtpResult;

    public function resend(): OtpResult;

    public function clear(): void;
}
