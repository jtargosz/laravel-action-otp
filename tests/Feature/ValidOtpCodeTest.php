<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Support\Keys;
use Jtargosz\ActionOtp\Tests\Fixtures\ConfirmLoginAction;
use Jtargosz\ActionOtp\Tests\TestCase;
use Jtargosz\ActionOtp\Validation\ValidOtpCode;

class ValidOtpCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->browser();
        $this->fixCode('482913');

        ActionOtp::to('rule@example.com')->for('signup')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'rule@example.com')
        );
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function passes(mixed $code, array $rules): bool
    {
        return Validator::make(['code' => $code], ['code' => $rules])->passes();
    }

    private function tries(): int
    {
        return (int) Cache::get(Keys::identifier(Keys::hash('rule@example.com'), ':tries'), 0);
    }

    public function test_rule_passes_for_correct_code_and_keeps_it(): void
    {
        $this->assertTrue($this->passes('482913', [new ValidOtpCode('rule@example.com', 'signup')]));
        $this->assertTrue(ActionOtp::to('rule@example.com')->for('signup')->verify('482913')->ok());
    }

    public function test_rule_fails_for_wrong_code_and_counts_the_attempt(): void
    {
        $this->assertFalse($this->passes('000000', [new ValidOtpCode('rule@example.com', 'signup')]));
        $this->assertSame(1, $this->tries());
    }

    public function test_rule_respects_the_purpose(): void
    {
        $this->assertFalse($this->passes('482913', [new ValidOtpCode('rule@example.com')]));
    }

    public function test_rule_fails_without_identifier_and_without_counting(): void
    {
        $this->assertFalse($this->passes('482913', [new ValidOtpCode(null, 'signup')]));
        $this->assertFalse($this->passes('482913', [new ValidOtpCode('  ', 'signup')]));
        $this->assertFalse($this->passes('482913', [new ValidOtpCode(['rule@example.com'], 'signup')]));
        $this->assertSame(0, $this->tries());
    }

    public function test_rule_fails_for_array_input_without_counting(): void
    {
        $this->assertFalse($this->passes(['482913'], [new ValidOtpCode('rule@example.com', 'signup')]));
        $this->assertFalse($this->passes(482913, [new ValidOtpCode('rule@example.com', 'signup')]));
        $this->assertSame(0, $this->tries());
    }

    public function test_rule_accepts_an_explicit_challenge(): void
    {
        $this->noBrowser();
        $this->fixCode('111111');

        $token = ActionOtp::to('api@example.com')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'api@example.com')
        )->challenge;

        $this->assertTrue($this->passes('111111', [new ValidOtpCode('api@example.com', 'default', $token)]));
        $this->assertFalse($this->passes('111111', [new ValidOtpCode('api@example.com')]));
    }
}
