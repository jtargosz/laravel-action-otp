<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Jtargosz\ActionOtp\Contracts\StoresCodes;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Tests\TestCase;
use Jtargosz\ActionOtp\Validation\ValidOtpCode;

class OkAction implements VerifiableAction
{
    public function handle(): mixed
    {
        return true;
    }
}

class ValidOtpCodeTest extends TestCase
{
    public function test_rule_passes_for_correct_code(): void
    {
        Notification::fake();

        ActionOtp::to('rule@example.com')->send(
            new OkAction,
            Notification::route('mail', 'rule@example.com')
        );

        $code = app(StoresCodes::class)
            ->scope('rule@example.com')->get()['code'];

        $validator = Validator::make(
            ['code' => $code],
            ['code' => [new ValidOtpCode('rule@example.com')]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_rule_fails_for_wrong_code(): void
    {
        Notification::fake();

        ActionOtp::to('rule@example.com')->send(
            new OkAction,
            Notification::route('mail', 'rule@example.com')
        );

        $validator = Validator::make(
            ['code' => 'wrong'],
            ['code' => [new ValidOtpCode('rule@example.com')]]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_rule_fails_gracefully_without_identifier(): void
    {
        $validator = Validator::make(
            ['code' => '123456'],
            ['code' => [new ValidOtpCode(null)]]
        );

        $this->assertTrue($validator->fails());
    }
}
