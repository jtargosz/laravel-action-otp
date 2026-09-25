<?php

namespace Jtargosz\ActionOtp\Tests;

use Jtargosz\ActionOtp\ActionOtpServiceProvider;
use Orchestra\Testbench\TestCase as Base;

abstract class TestCase extends Base
{
    protected function getPackageProviders($app): array
    {
        return [ActionOtpServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('action-otp.code_length', 6);
        $app['config']->set('action-otp.max_attempts', 3);
        $app['config']->set('action-otp.throttle_seconds', 60);
    }
}
