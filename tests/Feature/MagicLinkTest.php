<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Support\Keys;
use Jtargosz\ActionOtp\Tests\HttpTestCase;
use LogicException;

class MagicLinkTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ActionOtp::linkView(fn (Request $request, array $data) => response('Confirm page for '.$data['purpose'].' POST '.$data['url']));
    }

    private function link(): string
    {
        $link = $this->lastMessage()->link;

        $this->assertIsString($link);

        return $link;
    }

    public function test_mail_contains_a_signed_link(): void
    {
        $this->start(['purpose' => 'signup']);

        $link = $this->link();

        $this->assertStringStartsWith('http://localhost/otp/link?', $link);
        $this->assertStringContainsString('signature=', $link);
        $this->assertStringContainsString('p=signup', $link);
        $this->assertStringNotContainsString('user%40example.com', $link);
        $this->assertStringNotContainsString('user@example.com', $link);
    }

    public function test_get_only_shows_the_page_and_consumes_nothing(): void
    {
        $this->start();
        $link = $this->link();

        $this->get($link)->assertOk()->assertSee('Confirm page for default POST '.$link, false);
        $this->get($link)->assertOk();

        $this->postJson('/otp/verify', ['identifier' => 'user@example.com', 'code' => '482913'])->assertOk();
    }

    public function test_post_in_the_same_session_verifies(): void
    {
        $this->start();

        $this->postJson($this->link())->assertOk()->assertJson(['status' => 'verified']);
        $this->postJson($this->link())->assertStatus(409);
    }

    public function test_web_post_redirects_to_the_intended_url(): void
    {
        $this->start();

        $this->post($this->link())->assertRedirect('/');
    }

    public function test_post_from_another_session_is_a_device_mismatch_and_keeps_the_record(): void
    {
        $challenge = $this->start();
        $link = $this->link();

        $this->flushSession();

        $this->postJson($link)
            ->assertStatus(409)
            ->assertJson(['status' => 'device_mismatch', 'message' => 'Open this link in the browser where you requested the code, or enter the code from the message there.']);

        // The record survives, so the code still works for whoever holds the challenge.
        $this->postJson('/otp/verify', ['identifier' => 'user@example.com', 'code' => '482913', 'challenge' => $challenge])
            ->assertOk();
    }

    public function test_any_device_link_works_from_any_session(): void
    {
        $this->start(['action' => 'any']);
        $link = $this->link();

        $this->flushSession();

        $this->postJson($link)->assertOk()->assertJson(['status' => 'verified']);
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $this->start();

        $this->get($this->link().'x')->assertForbidden();
        $this->postJson(str_replace('t=', 't=x', $this->link()))->assertForbidden();
    }

    public function test_wrong_link_token_counts_as_an_attempt(): void
    {
        $this->start();

        $forged = URL::temporarySignedRoute('action-otp.link', now()->addMinutes(5), [
            'i' => Keys::hash('user@example.com'),
            'p' => 'default',
            't' => 'wrong-token',
        ]);

        $this->postJson($forged)->assertStatus(422)->assertJson(['status' => 'mismatch']);

        $this->assertSame(1, (int) Cache::get(Keys::identifier(Keys::hash('user@example.com'), ':tries')));
    }

    public function test_resend_issues_a_new_link(): void
    {
        config()->set('action-otp.send_cooldown', 0);
        $this->start();
        $old = $this->link();

        $this->postJson('/otp/resend', ['identifier' => 'user@example.com'])->assertOk();

        $this->assertNotSame($old, $this->link());
        $this->postJson($old)->assertStatus(422);
        $this->postJson($this->link())->assertOk();
    }

    public function test_link_page_without_a_view_explains_itself(): void
    {
        $this->app->forgetInstance('action-otp.view.link');
        $this->withoutExceptionHandling();
        $this->start();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ActionOtp::linkView()');

        $this->get($this->link());
    }

    public function test_string_view_name_is_rendered(): void
    {
        $this->app['view']->addNamespace('fixtures', __DIR__.'/../Fixtures/views');
        ActionOtp::linkView('fixtures::otp-link');
        $this->start();

        $this->get($this->link())->assertOk()->assertSee('Fixture link page', false);
    }
}
