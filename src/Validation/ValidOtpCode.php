<?php

namespace Jtargosz\ActionOtp\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Jtargosz\ActionOtp\Facades\ActionOtp;

/**
 * Checks the code with peek(): it stays valid for verify(), but a wrong code
 * counts as an attempt. Invalid input fails without counting.
 */
class ValidOtpCode implements ValidationRule
{
    public function __construct(
        protected mixed $identifier,
        protected string $purpose = 'default',
        protected ?string $challenge = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_string($this->identifier) && ! is_int($this->identifier))
            || trim((string) $this->identifier) === '') {
            $fail((string) __('action-otp::action-otp.empty'));

            return;
        }

        if (! is_string($value)) {
            $fail((string) __('action-otp::action-otp.mismatch'));

            return;
        }

        $result = ActionOtp::to($this->identifier)
            ->for($this->purpose)
            ->withChallenge($this->challenge)
            ->peek($value);

        if (! $result->found()) {
            $fail($result->message);
        }
    }
}
