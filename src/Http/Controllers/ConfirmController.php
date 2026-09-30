<?php

namespace Jtargosz\ActionOtp\Http\Controllers;

use Illuminate\Http\Request;
use Jtargosz\ActionOtp\Actions\ConfirmSession;
use Jtargosz\ActionOtp\Contracts\ManagesCodes;
use Jtargosz\ActionOtp\Contracts\VerifyResponse;
use Jtargosz\ActionOtp\Services\SessionChallenges;
use Jtargosz\ActionOtp\Support\Keys;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Code based confirmation for the otp.confirm middleware. The identifier
 * always comes from the authenticated user, never from the request.
 *
 * @internal
 */
class ConfirmController extends Controller
{
    public function show(Request $request, SessionChallenges $sessions): Response
    {
        [$identifier, $user] = $this->identify($request);

        // Send once per session. Refreshing the page must not spam codes;
        // the user asks for a new one with the resend route.
        if ($sessions->get(Keys::hash($identifier), Keys::hash(ConfirmSession::PURPOSE)) === null) {
            $this->otp($identifier)->send(new ConfirmSession, $user);
        }

        return $this->view('confirm', $request, [
            'submitUrl' => route('action-otp.confirm.store'),
            'resendUrl' => route('action-otp.confirm.resend'),
        ]);
    }

    public function store(Request $request, VerifyResponse $response): Response
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        [$identifier] = $this->identify($request);

        return $response->toResponse($request, $this->otp($identifier)->verify($data['code']));
    }

    public function resend(Request $request): Response
    {
        [$identifier] = $this->identify($request);

        return $this->sendResponse($request, $this->otp($identifier)->resend());
    }

    protected function otp(string $identifier): ManagesCodes
    {
        return $this->manager()->to($identifier)->for(ConfirmSession::PURPOSE);
    }

    /**
     * @return array{0: string, 1: object}
     */
    protected function identify(Request $request): array
    {
        $user = $request->user();

        if (! is_object($user)) {
            abort(401);
        }

        if (app()->bound('action-otp.confirm.identifier')) {
            $identifier = app('action-otp.confirm.identifier')($user);
        } elseif (method_exists($user, 'getEmailForVerification')) {
            $identifier = $user->getEmailForVerification();
        } elseif (method_exists($user, 'getAuthIdentifier')) {
            $identifier = $user->getAuthIdentifier();
        } else {
            $identifier = null;
        }

        if (! is_string($identifier) && ! is_int($identifier)) {
            throw new LogicException('Could not resolve an OTP identifier for the user. Call ActionOtp::confirmUsing().');
        }

        return [(string) $identifier, $user];
    }
}
