<?php

namespace Jtargosz\ActionOtp\Http\Controllers;

use Illuminate\Http\Request;
use Jtargosz\ActionOtp\Contracts\VerifyResponse;
use Jtargosz\ActionOtp\Support\OtpResult;
use Jtargosz\ActionOtp\Support\OtpStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class VerifyController extends Controller
{
    public function __invoke(Request $request, VerifyResponse $response): Response
    {
        $data = $request->validate([
            ...$this->identifierRules(),
            'code' => ['required', 'string', 'max:64'],
            'purpose' => ['nullable', 'string', 'max:100'],
            'challenge' => ['nullable', 'string', 'max:100'],
        ]);

        $identifier = $this->identifier($request, $data);

        if ($identifier === null) {
            return $response->toResponse($request, OtpResult::of(OtpStatus::Empty));
        }

        $purpose = $data['purpose'] ?? 'default';

        $result = $this->guarded(fn () => $this->manager()
            ->to($identifier)
            ->for($purpose)
            ->withChallenge($data['challenge'] ?? null)
            ->verify($data['code']));

        $this->rememberConfirmation($request, trim($purpose), $result);

        return $response->toResponse($request, $result);
    }
}
