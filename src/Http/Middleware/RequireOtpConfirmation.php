<?php

namespace Jtargosz\ActionOtp\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Jtargosz\ActionOtp\Actions\ConfirmSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Like password.confirm, but with a one-time code. Alias: otp.confirm.
 */
class RequireOtpConfirmation
{
    /**
     * Middleware string with a custom confirm route and/or timeout in seconds.
     */
    public static function using(?string $redirectToRoute = null, ?int $seconds = null): string
    {
        return static::class.':'.($redirectToRoute ?? '').','.($seconds ?? '');
    }

    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null, string|int|null $seconds = null): Response
    {
        $timeout = $seconds !== null && $seconds !== ''
            ? (int) $seconds
            : (int) config('action-otp.confirm_timeout', 900);

        $confirmedAt = (int) $request->session()->get(ConfirmSession::SESSION_KEY, 0);

        if (Date::now()->unix() - $confirmedAt > $timeout) {
            if ($request->expectsJson()) {
                return new JsonResponse(['message' => (string) __('action-otp::action-otp.confirm_required')], 423);
            }

            return redirect()->guest(route($redirectToRoute ?: 'action-otp.confirm'));
        }

        return $next($request);
    }
}
