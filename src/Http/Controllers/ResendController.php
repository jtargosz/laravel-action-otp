<?php

namespace Jtargosz\ActionOtp\Http\Controllers;

use Illuminate\Http\Request;
use Jtargosz\ActionOtp\Support\OtpResult;
use Jtargosz\ActionOtp\Support\OtpStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ResendController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            ...$this->identifierRules(),
            'purpose' => ['nullable', 'string', 'max:100'],
            'challenge' => ['nullable', 'string', 'max:100'],
        ]);

        $identifier = $this->identifier($request, $data);

        if ($identifier === null) {
            return $this->sendResponse($request, OtpResult::of(OtpStatus::Empty));
        }

        $result = $this->guarded(fn () => $this->manager()
            ->to($identifier)
            ->for($data['purpose'] ?? 'default')
            ->withChallenge($data['challenge'] ?? null)
            ->resend());

        return $this->sendResponse($request, $result);
    }
}
