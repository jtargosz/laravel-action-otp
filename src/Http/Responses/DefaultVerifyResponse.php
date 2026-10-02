<?php

namespace Jtargosz\ActionOtp\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Jtargosz\ActionOtp\Contracts\VerifyResponse;
use Jtargosz\ActionOtp\Support\OtpResult;
use Jtargosz\ActionOtp\Support\OtpStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * JSON: {status, message} with 200, 409 (device_mismatch), 422 or 429. The
 * payload is never serialized. Web: redirect to the intended URL on success,
 * back with a `code` error otherwise. A Responsable or Response returned by
 * handle() is sent as is.
 */
class DefaultVerifyResponse implements VerifyResponse
{
    public function toResponse(Request $request, OtpResult $result): Response
    {
        if ($result->ok() && ($result->payload instanceof Responsable || $result->payload instanceof Response)) {
            return Router::toResponse($request, $result->payload);
        }

        if ($request->expectsJson() || ! $request->hasSession()) {
            return self::json($result);
        }

        if ($result->ok()) {
            return redirect()
                ->intended((string) config('action-otp.redirects.verified', '/'))
                ->with('status', $result->message);
        }

        return back()
            ->withErrors(['code' => $result->message])
            ->withInput($request->except('code', 'challenge'));
    }

    public static function json(OtpResult $result): JsonResponse
    {
        return new JsonResponse(
            ['status' => $result->status->value, 'message' => $result->message],
            self::httpStatus($result->status)
        );
    }

    public static function httpStatus(OtpStatus $status): int
    {
        return match ($status) {
            OtpStatus::Sent, OtpStatus::Matched, OtpStatus::Verified => 200,
            OtpStatus::DeviceMismatch => 409,
            OtpStatus::Throttled => 429,
            default => 422,
        };
    }
}
