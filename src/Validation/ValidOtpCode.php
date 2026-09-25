<?php

namespace Jtargosz\ActionOtp\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Jtargosz\ActionOtp\Facades\ActionOtp;

class ValidOtpCode implements ValidationRule
{
    public function __construct(protected mixed $identifier) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($this->identifier) && ! is_int($this->identifier)) {
            $fail(__('action-otp.empty'));

            return;
        }

        $result = ActionOtp::to($this->identifier)->peek((string) $value);

        if (! $result->found()) {
            $fail($result->message);
        }
    }
}
