<?php

namespace Jtargosz\ActionOtp\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Tests\Fixtures\AnyDeviceAction;
use Jtargosz\ActionOtp\Tests\Fixtures\ConfirmLoginAction;
use Jtargosz\ActionOtp\Tests\Fixtures\ResponseAction;

/**
 * Registers the package routes plus a /start route that sends a code from
 * inside a real HTTP request, so the code is bound to the test session.
 */
abstract class HttpTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('action-otp.link.enabled', true);
        $app['config']->set('action-otp.rate_limit', 100);
    }

    protected function defineRoutes($router): void
    {
        ActionOtp::routes();

        $router->middleware('web')->post('/start', function (Request $request) {
            $email = (string) $request->input('email', 'user@example.com');

            $action = match ($request->input('action')) {
                'any' => new AnyDeviceAction,
                'response' => new ResponseAction,
                default => new ConfirmLoginAction,
            };

            $result = ActionOtp::to($email)
                ->for((string) $request->input('purpose', 'default'))
                ->send($action, Notification::route('mail', $email));

            return response()->json($result->toArray());
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->fixCode('482913');
    }

    /**
     * @param  array<string, string>  $input
     */
    protected function start(array $input = []): ?string
    {
        return $this->postJson('/start', $input)->assertOk()->json('challenge');
    }
}
