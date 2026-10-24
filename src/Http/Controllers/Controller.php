<?php

namespace Jtargosz\ActionOtp\Http\Controllers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Jtargosz\ActionOtp\Actions\ConfirmSession;
use Jtargosz\ActionOtp\Contracts\ManagesCodes;
use Jtargosz\ActionOtp\Http\Responses\DefaultVerifyResponse;
use Jtargosz\ActionOtp\Support\OtpResult;
use Jtargosz\ActionOtp\Support\OtpStatus;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
abstract class Controller
{
    protected function manager(): ManagesCodes
    {
        return app('action-otp');
    }

    /**
     * Blank identifiers or purposes become validation errors, not 500s.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function guarded(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['identifier' => $e->getMessage()]);
        }
    }

    /**
     * The request carries the identifier only without ActionOtp::identifyUsing().
     *
     * @return array<string, array<int, string>>
     */
    protected function identifierRules(): array
    {
        return app()->bound('action-otp.identifier') ? [] : ['identifier' => ['required', 'string', 'max:255']];
    }

    /**
     * Identifier for the verify and resend routes: from the resolver, otherwise
     * from the validated input. Null when the resolver has none.
     *
     * @param  array<string, mixed>  $data
     */
    protected function identifier(Request $request, array $data): ?string
    {
        if (! app()->bound('action-otp.identifier')) {
            return (string) $data['identifier'];
        }

        $identifier = app('action-otp.identifier')($request);

        if (! is_string($identifier) && ! is_int($identifier)) {
            return null;
        }

        $identifier = trim((string) $identifier);

        return $identifier === '' ? null : $identifier;
    }

    /**
     * Marks the session for otp.confirm. Done here on the injected request,
     * not in ConfirmSession::handle(), so it never touches another request's
     * session (Octane, queues).
     */
    protected function rememberConfirmation(Request $request, string $purpose, OtpResult $result): void
    {
        if ($purpose === ConfirmSession::PURPOSE && $result->status === OtpStatus::Verified && $request->hasSession()) {
            $request->session()->put(ConfirmSession::SESSION_KEY, Carbon::now()->unix());
        }
    }

    protected function sendResponse(Request $request, OtpResult $result): Response
    {
        if ($request->expectsJson() || ! $request->hasSession()) {
            return DefaultVerifyResponse::json($result);
        }

        return $result->status === OtpStatus::Sent
            ? back()->with('status', $result->message)
            : back()->withErrors(['code' => $result->message]);
    }

    /**
     * Renders a view registered with ActionOtp::linkView() or confirmView().
     *
     * @param  array<string, mixed>  $data
     */
    protected function view(string $name, Request $request, array $data): Response
    {
        $key = 'action-otp.view.'.$name;

        if (! app()->bound($key)) {
            throw new LogicException("No {$name} view registered. Call ActionOtp::{$name}View() in a service provider.");
        }

        $view = app($key);

        $rendered = is_string($view) ? view($view, $data) : $view($request, $data);

        return Router::toResponse($request, $rendered);
    }
}
