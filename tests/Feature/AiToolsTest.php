<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Ai\CheckOtpTool;
use Jtargosz\ActionOtp\Ai\ResendOtpTool;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Tests\Fixtures\ConfirmLoginAction;
use Jtargosz\ActionOtp\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class AiToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('action-otp.send_cooldown', 0);
        $this->browser();
        $this->fixCode('482913');

        ActionOtp::to('owner@example.com')->for('login')->send(
            new ConfirmLoginAction,
            Notification::route('mail', 'owner@example.com')
        );
    }

    public function test_check_tool_uses_the_injected_identifier_and_ignores_model_input(): void
    {
        $tool = new CheckOtpTool('owner@example.com', 'login');

        $this->assertSame('mismatch: Code does not match.', (string) $tool->handle(new Request(['code' => '000000'])));
        $this->assertSame('matched: Code matches.', (string) $tool->handle(new Request(['code' => '482913'])));

        // An identifier smuggled in by the model changes nothing.
        $this->assertSame(
            'matched: Code matches.',
            (string) $tool->handle(new Request(['code' => '482913', 'identifier' => 'victim@example.com']))
        );

        // peek() keeps the code for the real verify().
        $this->assertTrue(ActionOtp::to('owner@example.com')->for('login')->verify('482913')->ok());
    }

    public function test_check_tool_rejects_non_string_codes(): void
    {
        $tool = new CheckOtpTool('owner@example.com', 'login');

        $this->assertStringStartsWith('mismatch:', (string) $tool->handle(new Request(['code' => ['482913']])));
    }

    public function test_resend_tool_resends_only_for_the_injected_identifier(): void
    {
        $this->fixCode('777777');

        $this->assertSame(
            'sent: We sent a verification code.',
            (string) (new ResendOtpTool('owner@example.com', 'login'))->handle(new Request(['identifier' => 'victim@example.com']))
        );
        $this->assertSame(
            'empty: No code found.',
            (string) (new ResendOtpTool('victim@example.com', 'login'))->handle(new Request)
        );

        $this->assertTrue(ActionOtp::to('owner@example.com')->for('login')->verify('777777')->ok());
    }

    public function test_schemas_expose_only_the_code(): void
    {
        $schema = new JsonSchemaTypeFactory;

        $this->assertSame(['code'], array_keys((new CheckOtpTool('owner@example.com'))->schema($schema)));
        $this->assertSame([], (new ResendOtpTool('owner@example.com'))->schema($schema));
    }
}
