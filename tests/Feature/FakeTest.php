<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Contracts\ManagesCodes;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Support\OtpMessage;
use Jtargosz\ActionOtp\Testing\ActionOtpFake;
use Jtargosz\ActionOtp\Tests\Fixtures\AnyDeviceAction;
use Jtargosz\ActionOtp\Tests\Fixtures\ConfirmLoginAction;
use Jtargosz\ActionOtp\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;

class FakeTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->post('/register', function (Request $request) {
            $email = (string) $request->input('email');

            ActionOtp::to($email)->for('register')->send(
                new ConfirmLoginAction('registered:'.$email),
                Notification::route('mail', $email)
            );

            return response()->json(['message' => 'Code sent.']);
        });

        $router->middleware('web')->post('/register/verify', function (Request $request) {
            $result = ActionOtp::to((string) $request->input('email'))
                ->for('register')
                ->verify((string) $request->input('code'));

            return response()->json(['status' => $result->status->value], $result->ok() ? 200 : 422);
        });
    }

    public function test_http_flow_without_touching_the_vault(): void
    {
        Notification::fake();
        ActionOtp::fake('123456');

        $this->postJson('/register', ['email' => 'new@example.com'])->assertOk();

        ActionOtp::assertSent(
            ConfirmLoginAction::class,
            fn (ConfirmLoginAction $action, OtpMessage $message, string $identifier, string $purpose) => $identifier === 'new@example.com' && $purpose === 'register'
        );
        ActionOtp::assertSentTimes(ConfirmLoginAction::class, 1);
        ActionOtp::assertNotVerified(ConfirmLoginAction::class);
        Notification::assertNothingSent();

        $this->postJson('/register/verify', ['email' => 'new@example.com', 'code' => '000000'])->assertStatus(422);
        $this->postJson('/register/verify', [
            'email' => 'new@example.com',
            'code' => ActionOtp::codeFor('new@example.com', 'register'),
        ])->assertOk();

        ActionOtp::assertVerified(
            ConfirmLoginAction::class,
            fn (ConfirmLoginAction $action, mixed $payload) => $payload === 'registered:new@example.com'
        );
    }

    public function test_fake_replaces_the_container_binding(): void
    {
        $fake = ActionOtp::fake();

        $this->assertInstanceOf(ActionOtpFake::class, $fake);
        $this->assertSame($fake, app(ManagesCodes::class));
    }

    public function test_fake_without_code_generates_random_codes(): void
    {
        $this->browser();
        ActionOtp::fake();

        ActionOtp::to('rnd@example.com')->send(new ConfirmLoginAction, Notification::route('mail', 'rnd@example.com'));

        $code = ActionOtp::codeFor('rnd@example.com');

        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $this->assertTrue(ActionOtp::to('rnd@example.com')->verify($code)->ok());
    }

    public function test_challenge_for_supports_api_flows_and_resend(): void
    {
        $this->noBrowser();
        config()->set('action-otp.send_cooldown', 0);
        ActionOtp::fake();

        ActionOtp::to('api@example.com')->send(new ConfirmLoginAction, Notification::route('mail', 'api@example.com'));

        $token = ActionOtp::challengeFor('api@example.com');

        ActionOtp::to('api@example.com')->withChallenge($token)->resend();

        ActionOtp::assertSentTimes(ConfirmLoginAction::class, 2);
        $this->assertSame($token, ActionOtp::challengeFor('api@example.com'));
        $this->assertTrue(
            ActionOtp::to('api@example.com')->withChallenge($token)->verify(ActionOtp::codeFor('api@example.com'))->ok()
        );
    }

    public function test_assert_sent_fails_when_nothing_matches(): void
    {
        ActionOtp::fake();

        $this->assertFails(fn () => ActionOtp::assertSent(ConfirmLoginAction::class));

        $this->browser();
        ActionOtp::to('x@example.com')->send(new ConfirmLoginAction, Notification::route('mail', 'x@example.com'));

        $this->assertFails(fn () => ActionOtp::assertSent(AnyDeviceAction::class));
        $this->assertFails(fn () => ActionOtp::assertSent(
            ConfirmLoginAction::class,
            fn ($action, $message, string $identifier) => $identifier === 'other@example.com'
        ));
    }

    public function test_negative_assertions_fail_when_they_should(): void
    {
        $this->browser();
        ActionOtp::fake('123456');

        ActionOtp::assertNothingSent();
        ActionOtp::assertNotSent(ConfirmLoginAction::class);

        ActionOtp::to('x@example.com')->send(new ConfirmLoginAction, Notification::route('mail', 'x@example.com'));

        $this->assertFails(fn () => ActionOtp::assertNothingSent());
        $this->assertFails(fn () => ActionOtp::assertNotSent(ConfirmLoginAction::class));
        $this->assertFails(fn () => ActionOtp::assertSentTimes(ConfirmLoginAction::class, 2));
        $this->assertFails(fn () => ActionOtp::assertVerified(ConfirmLoginAction::class));

        ActionOtp::to('x@example.com')->verify('123456');

        $this->assertFails(fn () => ActionOtp::assertNotVerified(ConfirmLoginAction::class));
        $this->assertFails(fn () => ActionOtp::assertVerified(ConfirmLoginAction::class, fn ($action, $payload) => $payload === 'nope'));
    }

    public function test_code_for_throws_when_nothing_was_sent(): void
    {
        ActionOtp::fake();

        $this->expectException(RuntimeException::class);

        ActionOtp::codeFor('nobody@example.com');
    }

    private function assertFails(callable $assertion): void
    {
        try {
            $assertion();
        } catch (AssertionFailedError) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Assertion was expected to fail.');
    }
}
