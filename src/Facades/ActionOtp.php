<?php

namespace Jtargosz\ActionOtp\Facades;

use Illuminate\Support\Facades\Facade;
use Jtargosz\ActionOtp\Contracts\GeneratesCodes;
use Jtargosz\ActionOtp\Contracts\ManagesCodes;
use Jtargosz\ActionOtp\Contracts\StoresCodes;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
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
