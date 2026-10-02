<?php

namespace Jtargosz\ActionOtp\Http\Controllers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Jtargosz\ActionOtp\Contracts\ManagesCodes;
use Jtargosz\ActionOtp\Http\Responses\DefaultVerifyResponse;
use Jtargosz\ActionOtp\Support\OtpResult;
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
     * Response for send and resend results.
     */
    protected function sendResponse(Request $request, OtpResult $result): Response
    {
        if ($request->expectsJson() || ! $request->hasSession()) {
            return DefaultVerifyResponse::json($result);
        }

        return $result->status->value === 'sent'
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
