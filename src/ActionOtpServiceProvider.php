<?php

namespace Jtargosz\ActionOtp;

use Illuminate\Support\ServiceProvider;
use Jtargosz\ActionOtp\Console\MakeOtpActionCommand;
use Jtargosz\ActionOtp\Contracts\GeneratesCodes;
use Jtargosz\ActionOtp\Contracts\ManagesCodes;
use Jtargosz\ActionOtp\Contracts\StoresCodes;
use Jtargosz\ActionOtp\Services\CacheCodeVault;
use Jtargosz\ActionOtp\Services\CodeManager;
use Jtargosz\ActionOtp\Services\SecureCodeGenerator;
use Jtargosz\ActionOtp\Services\SessionChallenges;
use Jtargosz\ActionOtp\Services\Throttle;

class ActionOtpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/action-otp.php', 'action-otp');

        $this->app->singleton(GeneratesCodes::class, SecureCodeGenerator::class);
        $this->app->singleton(StoresCodes::class, CacheCodeVault::class);
        $this->app->singleton(Throttle::class);
        $this->app->singleton(SessionChallenges::class);

        $this->app->singleton('action-otp', function ($app) {
            return new CodeManager(
                $app->make(StoresCodes::class),
                $app->make(GeneratesCodes::class),
                $app->make(Throttle::class),
                $app->make(SessionChallenges::class),
            );
        });

        $this->app->alias('action-otp', CodeManager::class);
        $this->app->alias('action-otp', ManagesCodes::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'action-otp');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'action-otp');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/action-otp.php' => config_path('action-otp.php'),
            ], 'action-otp-config');

            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/action-otp'),
            ], 'action-otp-lang');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/action-otp'),
            ], 'action-otp-views');

            $this->commands([MakeOtpActionCommand::class]);
        }
    }
}
