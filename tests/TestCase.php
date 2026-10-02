<?php

namespace Jtargosz\ActionOtp\Tests;

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\ActionOtpServiceProvider;
use Jtargosz\ActionOtp\Contracts\GeneratesCodes;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Support\ActionPolicy;
use Jtargosz\ActionOtp\Support\OtpMessage;
use Jtargosz\ActionOtp\Testing\FixedCodeGenerator;
use Orchestra\Testbench\TestCase as Base;
use RuntimeException;

abstract class TestCase extends Base
{
    /**
     * @var array<string, Store>
     */
    private array $browsers = [];

    protected function tearDown(): void
    {
        ActionPolicy::resetAttributes();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [ActionOtpServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.com');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('action-otp.code_length', 6);
        $app['config']->set('action-otp.max_attempts', 3);
        $app['config']->set('action-otp.throttle_seconds', 60);
    }

    /**
     * Makes every following send() use this code.
     */
    protected function fixCode(string $code): void
    {
        $this->app->instance(GeneratesCodes::class, new FixedCodeGenerator($code));
        $this->app->forgetInstance('action-otp');
        ActionOtp::clearResolvedInstance('action-otp');
    }

    /**
     * Switches the current request to a named browser session.
     */
    protected function browser(string $name = 'a'): Store
    {
        if (! isset($this->browsers[$name])) {
            $store = new Store('session-'.$name, new ArraySessionHandler(120));
            $store->start();
            $this->browsers[$name] = $store;
        }

        $this->app['request']->setLaravelSession($this->browsers[$name]);

        return $this->browsers[$name];
    }

    /**
     * Switches to a request without any session, like an API client.
     */
    protected function noBrowser(): void
    {
        $this->app->instance('request', Request::create('/'));
    }

    /**
     * The OtpMessage of the last notification sent through Notification::fake().
     */
    protected function lastMessage(): OtpMessage
    {
        $last = null;

        foreach (Notification::sentNotifications() as $byKey) {
            foreach ($byKey as $byClass) {
                foreach ($byClass as $sent) {
                    foreach ($sent as $entry) {
                        $last = $entry['notification'];
                    }
                }
            }
        }

        if (! is_object($last) || ! isset($last->otp) || ! $last->otp instanceof OtpMessage) {
            throw new RuntimeException('No OTP notification was sent.');
        }

        return $last->otp;
    }
}
