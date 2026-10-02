<?php

namespace Jtargosz\ActionOtp\Http;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Routing\Router;
use Jtargosz\ActionOtp\Http\Controllers\ConfirmController;
use Jtargosz\ActionOtp\Http\Controllers\LinkController;
use Jtargosz\ActionOtp\Http\Controllers\ResendController;
use Jtargosz\ActionOtp\Http\Controllers\VerifyController;

/**
 * @internal Use ActionOtp::routes().
 */
final class RouteRegistrar
{
    /**
     * @param  array<int, string>  $middleware
     */
    public static function register(Router $router, string $prefix = 'otp', array $middleware = ['web']): void
    {
        $router->group([
            'prefix' => $prefix,
            'as' => 'action-otp.',
            'middleware' => [...$middleware, ThrottleRequests::class.':action-otp'],
        ], function (Router $router) {
            $router->post('verify', VerifyController::class)->name('verify');
            $router->post('resend', ResendController::class)->name('resend');

            $router->get('link', [LinkController::class, 'show'])
                ->middleware(ValidateSignature::class)
                ->name('link');
            $router->post('link', [LinkController::class, 'store'])
                ->middleware(ValidateSignature::class)
                ->name('link.store');

            $router->middleware('auth')->group(function (Router $router) {
                $router->get('confirm', [ConfirmController::class, 'show'])->name('confirm');
                $router->post('confirm', [ConfirmController::class, 'store'])->name('confirm.store');
                $router->post('confirm/resend', [ConfirmController::class, 'resend'])->name('confirm.resend');
            });
        });

        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();
    }
}
