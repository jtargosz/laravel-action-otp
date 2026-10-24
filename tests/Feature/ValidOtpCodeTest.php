<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Tests\TestCase;
use Jtargosz\ActionOtp\Validation\ValidOtpCode;

class ValidOtpCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->browser();
        $this->fixCode(self::CODE);

        $this->sendCode('rule@example.com', purpose: 'signup');
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function passes(mixed $code, array $rules): bool
    {
        return Validator::make(['code' => $code], ['code' => $rules])->passes();
    }

    public function test_rule_passes_for_correct_code_and_keeps_it(): void
    {
        $this->assertTrue($this->passes(self::CODE, [new ValidOtpCode('rule@example.com', 'signup')]));
        $this->assertTrue(ActionOtp::to('rule@example.com')->for('signup')->verify(self::CODE)->ok());
    }

    public function test_rule_fails_for_wrong_code_and_counts_the_attempt(): void
    {
        $this->assertFalse($this->passes('000000', [new ValidOtpCode('rule@example.com', 'signup')]));
        $this->assertSame(1, $this->tries('rule@example.com'));
    }

    public function test_rule_respects_the_purpose(): void
    {
        $this->assertFalse($this->passes(self::CODE, [new ValidOtpCode('rule@example.com')]));
    }

    public function test_rule_fails_without_identifier_and_without_counting(): void
    {
        $this->assertFalse($this->passes(self::CODE, [new ValidOtpCode(null, 'signup')]));
        $this->assertFalse($this->passes(self::CODE, [new ValidOtpCode('  ', 'signup')]));
        $this->assertFalse($this->passes(self::CODE, [new ValidOtpCode(['rule@example.com'], 'signup')]));
        $this->assertSame(0, $this->tries('rule@example.com'));
    }

    public function test_rule_fails_for_a_blank_purpose_without_counting(): void
    {
        $validator = Validator::make(['code' => self::CODE], ['code' => [new ValidOtpCode('rule@example.com', '')]]);

        $this->assertTrue($validator->fails());
        $this->assertSame('No code found.', $validator->errors()->first('code'));
        $this->assertFalse($this->passes(self::CODE, [new ValidOtpCode('rule@example.com', '   ')]));
        $this->assertSame(0, $this->tries('rule@example.com'));
    }

    public function test_rule_fails_for_array_input_without_counting(): void
    {
        $this->assertFalse($this->passes([self::CODE], [new ValidOtpCode('rule@example.com', 'signup')]));
        $this->assertFalse($this->passes((int) self::CODE, [new ValidOtpCode('rule@example.com', 'signup')]));
        $this->assertSame(0, $this->tries('rule@example.com'));
    }

    public function test_rule_accepts_an_explicit_challenge(): void
    {
        $this->noBrowser();
        $this->fixCode('111111');

        $token = $this->sendCode('api@example.com')->challenge;

        $this->assertTrue($this->passes('111111', [new ValidOtpCode('api@example.com', 'default', $token)]));
        $this->assertFalse($this->passes('111111', [new ValidOtpCode('api@example.com')]));
    }
}
