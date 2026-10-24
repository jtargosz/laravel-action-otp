<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Http\Request;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Tests\HttpTestCase;

class IdentityResolverTest extends HttpTestCase
{
    public function test_without_a_resolver_the_identifier_is_required(): void
    {
        $this->postJson('/otp/verify', ['code' => self::CODE])
            ->assertStatus(422)
            ->assertJsonValidationErrors('identifier');

        $this->postJson('/otp/resend')
            ->assertStatus(422)
            ->assertJsonValidationErrors('identifier');
    }

    public function test_resolver_overrides_the_identifier_from_the_request(): void
    {
        ActionOtp::identifyUsing(fn (Request $request) => $request->session()->get('login.email'));

        $this->start(['email' => 'owner@example.com']);
        $this->session(['login.email' => 'owner@example.com']);

        $this->postJson('/otp/verify', ['identifier' => 'other@example.com', 'code' => self::CODE])
            ->assertOk()
            ->assertJson(['status' => 'verified']);
    }

    public function test_resolver_without_an_identifier_is_empty_and_counts_nothing(): void
    {
        ActionOtp::identifyUsing(fn () => null);

        $this->start(['email' => 'owner@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/otp/verify', ['identifier' => 'owner@example.com', 'code' => '000000'])
                ->assertStatus(422)
                ->assertJson(['status' => 'empty']);
        }

        $this->postJson('/otp/resend', ['identifier' => 'owner@example.com'])
            ->assertStatus(422)
            ->assertJson(['status' => 'empty']);

        $this->assertSame(0, $this->tries('owner@example.com'));
    }

    public function test_blank_resolver_result_is_empty(): void
    {
        ActionOtp::identifyUsing(fn () => '  ');

        $this->postJson('/otp/verify', ['code' => self::CODE])
            ->assertStatus(422)
            ->assertJson(['status' => 'empty']);
    }

    public function test_resend_with_a_resolver_honors_the_cooldown(): void
    {
        ActionOtp::identifyUsing(fn () => 'owner@example.com');

        $this->start(['email' => 'owner@example.com']);

        $this->postJson('/otp/resend')->assertStatus(429)->assertJson(['status' => 'throttled']);

        $this->travel(31)->seconds();
        $this->fixCode('777777');

        $this->postJson('/otp/resend')->assertOk()->assertJson(['status' => 'sent']);
        $this->postJson('/otp/verify', ['code' => '777777'])->assertOk();
    }
}
