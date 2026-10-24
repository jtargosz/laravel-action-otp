<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Actions\ConfirmSession;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Http\Middleware\RequireOtpConfirmation;
use Jtargosz\ActionOtp\Mail\CodeMail;
use Jtargosz\ActionOtp\Tests\Fixtures\FixtureUser;
use Jtargosz\ActionOtp\Tests\HttpTestCase;

class ConfirmMiddlewareTest extends HttpTestCase
{
    private FixtureUser $user;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('action-otp.link.enabled', false);
    }

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->middleware(['web', 'auth', 'otp.confirm'])->delete('/account', fn () => 'deleted');
        $router->middleware(['web', 'auth', 'otp.confirm'])->get('/settings', fn () => 'settings');
        $router->middleware(['web', 'auth', RequireOtpConfirmation::using(null, 60)])->get('/short', fn () => 'short');
    }

    protected function setUp(): void
    {
        parent::setUp();

        ActionOtp::confirmView(fn (Request $request, array $data) => response('Enter code, POST '.$data['submitUrl'].' resend '.$data['resendUrl']));

        $this->user = new FixtureUser(['id' => 7, 'email' => 'owner@example.com']);
        $this->actingAs($this->user);
    }

    public function test_unconfirmed_request_redirects_or_returns_423(): void
    {
        $this->delete('/account')->assertRedirect('/otp/confirm');

        $this->deleteJson('/account')
            ->assertStatus(423)
            ->assertExactJson(['message' => 'Confirmation with a verification code is required.']);
    }

    public function test_full_confirmation_flow(): void
    {
        $this->get('/settings')->assertRedirect('/otp/confirm');

        $this->get('/otp/confirm')
            ->assertOk()
            ->assertSee('Enter code, POST http://localhost/otp/confirm resend http://localhost/otp/confirm/resend', false);

        Notification::assertSentTo($this->user, CodeMail::class, fn (CodeMail $mail) => $mail->otp->purpose === ConfirmSession::PURPOSE);

        $this->post('/otp/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/otp/confirm', ['code' => self::CODE])->assertRedirect('/settings');

        $this->get('/settings')->assertOk()->assertSee('settings');
        $this->delete('/account')->assertOk()->assertSee('deleted');
    }

    public function test_magic_link_confirms_the_session(): void
    {
        config()->set('action-otp.link.enabled', true);

        $this->get('/otp/confirm')->assertOk();

        $link = null;
        Notification::assertSentTo($this->user, CodeMail::class, function (CodeMail $mail) use (&$link) {
            $link = $mail->otp->link;

            return true;
        });
        $this->assertIsString($link);

        $this->postJson($link)->assertOk()->assertJson(['status' => 'verified']);

        $this->get('/settings')->assertOk()->assertSee('settings');
    }

    public function test_confirm_action_runs_without_a_request_session(): void
    {
        $this->noBrowser();

        $send = $this->sendCode('owner@example.com', new ConfirmSession, ConfirmSession::PURPOSE);

        $result = ActionOtp::to('owner@example.com')
            ->for(ConfirmSession::PURPOSE)
            ->withChallenge($send->challenge)
            ->verify(self::CODE);

        $this->assertTrue($result->ok());
        $this->assertTrue($result->payload);
    }

    public function test_code_is_sent_once_per_session(): void
    {
        $this->get('/otp/confirm')->assertOk();
        $this->get('/otp/confirm')->assertOk();
        $this->get('/otp/confirm')->assertOk();

        Notification::assertSentToTimes($this->user, CodeMail::class, 1);
    }

    public function test_confirmation_expires_after_the_timeout(): void
    {
        $this->get('/otp/confirm');
        $this->postJson('/otp/confirm', ['code' => self::CODE])->assertOk();

        $this->delete('/account')->assertOk();
        $this->get('/short')->assertOk();

        $this->travel(61)->seconds();
        $this->get('/short')->assertRedirect('/otp/confirm');
        $this->delete('/account')->assertOk();

        $this->travel(900)->seconds();
        $this->delete('/account')->assertRedirect('/otp/confirm');
    }

    public function test_confirm_resend_honors_the_cooldown(): void
    {
        $this->get('/otp/confirm');

        $this->postJson('/otp/confirm/resend')->assertStatus(429);

        $this->travel(31)->seconds();
        $this->fixCode('777777');

        $this->postJson('/otp/confirm/resend')->assertOk()->assertJson(['status' => 'sent']);
        $this->postJson('/otp/confirm', ['code' => '777777'])->assertOk();
    }

    public function test_confirm_resend_without_a_pending_code_is_empty(): void
    {
        $this->postJson('/otp/confirm/resend')
            ->assertStatus(422)
            ->assertJson(['status' => 'empty']);

        Notification::assertNothingSent();
    }

    public function test_identifier_comes_from_the_user_or_the_resolver(): void
    {
        $fake = ActionOtp::fake(self::CODE);

        $this->get('/otp/confirm');
        $fake->assertSent(ConfirmSession::class, fn ($action, $message, string $identifier) => $identifier === 'owner@example.com');

        ActionOtp::confirmUsing(fn (FixtureUser $user) => 'user-'.$user->getAuthIdentifier());
        $this->flushSession();
        $this->actingAs($this->user);

        $this->get('/otp/confirm');
        $fake->assertSent(ConfirmSession::class, fn ($action, $message, string $identifier) => $identifier === 'user-7');
    }

    public function test_other_confirmation_flows_do_not_mark_the_session(): void
    {
        $this->postJson('/start', ['email' => 'owner@example.com']);
        $this->postJson('/otp/verify', ['identifier' => 'owner@example.com', 'code' => self::CODE])->assertOk();

        $this->delete('/account')->assertRedirect('/otp/confirm');
    }

    public function test_using_builds_the_middleware_string(): void
    {
        $this->assertSame(RequireOtpConfirmation::class.':custom.confirm,120', RequireOtpConfirmation::using('custom.confirm', 120));
        $this->assertSame(RequireOtpConfirmation::class.':,', RequireOtpConfirmation::using());
    }
}
