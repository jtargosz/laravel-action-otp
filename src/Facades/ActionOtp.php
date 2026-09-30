<?php

namespace Jtargosz\ActionOtp\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Jtargosz\ActionOtp\Contracts\GeneratesCodes;
use Jtargosz\ActionOtp\Contracts\ManagesCodes;
use Jtargosz\ActionOtp\Contracts\StoresCodes;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use Jtargosz\ActionOtp\Http\RouteRegistrar;
use Jtargosz\ActionOtp\Services\CodeManager;
use Jtargosz\ActionOtp\Services\SessionChallenges;
use Jtargosz\ActionOtp\Services\Throttle;
use Jtargosz\ActionOtp\Support\OtpMessage;
use Jtargosz\ActionOtp\Support\OtpResult;
use Jtargosz\ActionOtp\Testing\ActionOtpFake;
use Jtargosz\ActionOtp\Testing\FixedCodeGenerator;

/**
 * @method static ManagesCodes to(string|int $identifier)
 * @method static ManagesCodes for(string $purpose)
 * @method static ManagesCodes withChallenge(?string $challenge)
 * @method static OtpResult send(VerifiableAction $action, object $notifiable)
 * @method static OtpResult verify(string $code)
 * @method static OtpResult peek(string $code)
 * @method static OtpResult resend()
 * @method static void clear()
 * @method static void assertSent(string $action, ?callable $callback = null)
 * @method static void assertSentTimes(string $action, int $times)
 * @method static void assertNotSent(string $action, ?callable $callback = null)
 * @method static void assertNothingSent()
 * @method static void assertVerified(string $action, ?callable $callback = null)
 * @method static void assertNotVerified(string $action)
 * @method static string codeFor(string|int $identifier, string $purpose = 'default')
 * @method static string challengeFor(string|int $identifier, string $purpose = 'default')
 * @method static OtpMessage messageFor(string|int $identifier, string $purpose = 'default')
 *
 * @see CodeManager
 * @see ActionOtpFake
 */
class ActionOtp extends Facade
{
    /**
     * Registers the optional routes: verify, resend, magic link and the
     * otp.confirm pages. Call it from a routes file.
     *
     * @param  array<int, string>  $middleware  "web" for session based apps, e.g. ["api"] for token clients
     */
    public static function routes(string $prefix = 'otp', array $middleware = ['web']): void
    {
        RouteRegistrar::register(app('router'), $prefix, $middleware);
    }

    /**
     * View for the magic link page. A string is a view name, a callable gets
     * (Request $request, array $data). Data: url (POST target), purpose.
     */
    public static function linkView(string|Closure $view): void
    {
        app()->instance('action-otp.view.link', $view);
    }

    /**
     * View for the otp.confirm page. Data: submitUrl, resendUrl.
     */
    public static function confirmView(string|Closure $view): void
    {
        app()->instance('action-otp.view.confirm', $view);
    }

    /**
     * Picks the identifier for otp.confirm from the authenticated user.
     * Default: getEmailForVerification(), then getAuthIdentifier().
     *
     * @param  Closure(object): (string|int)  $resolver
     */
    public static function confirmUsing(Closure $resolver): void
    {
        app()->instance('action-otp.confirm.identifier', $resolver);
    }

    /**
     * Swaps the manager for a fake that records sends instead of delivering
     * them. Pass a code to make every send use it.
     */
    public static function fake(?string $code = null): ActionOtpFake
    {
        $app = static::$app ?? app();

        $fake = new ActionOtpFake(
            $app->make(StoresCodes::class),
            $code === null ? $app->make(GeneratesCodes::class) : new FixedCodeGenerator($code),
            $app->make(Throttle::class),
            $app->make(SessionChallenges::class),
        );

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'action-otp';
    }
}
