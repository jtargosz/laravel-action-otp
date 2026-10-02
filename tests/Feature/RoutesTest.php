<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Jtargosz\ActionOtp\Contracts\VerifyResponse;
use Jtargosz\ActionOtp\Support\OtpResult;
use Jtargosz\ActionOtp\Tests\HttpTestCase;
use Symfony\Component\HttpFoundation\Response;

class RoutesTest extends HttpTestCase
{
    private function verify(string $code, array $extra = []): TestResponse
    {
        return $this->postJson('/otp/verify', ['identifier' => 'user@example.com', 'code' => $code, ...$extra]);
    }

    public function test_json_verify_returns_status_and_message_without_payload(): void
    {
        $this->start();

        $this->verify('000000')
            ->assertStatus(422)
            ->assertExactJson(['status' => 'mismatch', 'message' => 'Code does not match.']);

        $this->verify('482913')
            ->assertOk()
            ->assertExactJson(['status' => 'verified', 'message' => 'Code accepted.']);

        $this->verify('482913')->assertStatus(422)->assertJson(['status' => 'empty']);
    }

    public function test_json_statuses_for_throttled_expired_and_other_session(): void
    {
        $this->start();

        $this->flushSession();
        $this->verify('482913')->assertStatus(422)->assertJson(['status' => 'empty']);

        $this->start(['email' => 'late@example.com']);
        $this->travel(16)->minutes();
        $this->postJson('/otp/verify', ['identifier' => 'late@example.com', 'code' => '482913'])
            ->assertStatus(422)
            ->assertJson(['status' => 'expired']);

        $this->start(['email' => 'locked@example.com']);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/otp/verify', ['identifier' => 'locked@example.com', 'code' => '000000']);
        }
        $this->postJson('/otp/verify', ['identifier' => 'locked@example.com', 'code' => '482913'])
            ->assertStatus(429)
            ->assertJson(['status' => 'throttled']);
    }

    public function test_api_client_verifies_with_the_challenge(): void
    {
        $challenge = $this->start(['purpose' => 'signup']);
        $this->flushSession();

        $this->verify('482913', ['purpose' => 'signup'])->assertStatus(422);
        $this->verify('482913', ['purpose' => 'signup', 'challenge' => $challenge])->assertOk();
    }

    public function test_web_verify_redirects(): void
    {
        $this->start();

        $this->from('/code')
            ->post('/otp/verify', ['identifier' => 'user@example.com', 'code' => '000000'])
            ->assertRedirect('/code')
            ->assertSessionHasErrors(['code' => 'Code does not match.']);

        $this->session(['url.intended' => '/dashboard']);

        $this->post('/otp/verify', ['identifier' => 'user@example.com', 'code' => '482913'])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Code accepted.');
    }

    public function test_web_verify_uses_the_configured_redirect(): void
    {
        config()->set('action-otp.redirects.verified', '/welcome');
        $this->start();

        $this->post('/otp/verify', ['identifier' => 'user@example.com', 'code' => '482913'])
            ->assertRedirect('/welcome');
    }

    public function test_responsable_payload_is_returned_as_is(): void
    {
        $this->start(['action' => 'response']);

        $this->verify('482913')->assertStatus(201)->assertExactJson(['custom' => true]);
    }

    public function test_validation_errors(): void
    {
        $this->postJson('/otp/verify', ['identifier' => 'user@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->postJson('/otp/verify', ['identifier' => ['x'], 'code' => '1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('identifier');

        $this->postJson('/otp/verify', ['identifier' => 'user@example.com', 'code' => '1', 'purpose' => ' x'])
            ->assertStatus(422);
    }

    public function test_resend_route(): void
    {
        config()->set('action-otp.send_cooldown', 0);
        $this->start();
        $this->fixCode('777777');

        $this->postJson('/otp/resend', ['identifier' => 'user@example.com'])
            ->assertOk()
            ->assertJson(['status' => 'sent']);

        $this->verify('482913')->assertStatus(422);
        $this->verify('777777')->assertOk();

        $this->postJson('/otp/resend', ['identifier' => 'user@example.com'])
            ->assertStatus(422)
            ->assertJson(['status' => 'empty']);
    }

    public function test_resend_route_honors_the_cooldown(): void
    {
        $this->start();

        $this->postJson('/otp/resend', ['identifier' => 'user@example.com'])
            ->assertStatus(429)
            ->assertJson(['status' => 'throttled']);
    }

    public function test_routes_are_rate_limited_per_ip(): void
    {
        config()->set('action-otp.rate_limit', 2);

        $this->verify('000000');
        $this->verify('000000');

        $this->verify('000000')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_verify_response_can_be_replaced(): void
    {
        $this->app->instance(VerifyResponse::class, new class implements VerifyResponse
        {
            public function toResponse(Request $request, OtpResult $result): Response
            {
                return new JsonResponse(['custom' => $result->status->value], 299);
            }
        });

        $this->start();

        $this->verify('482913')->assertStatus(299)->assertExactJson(['custom' => 'verified']);
    }
}
