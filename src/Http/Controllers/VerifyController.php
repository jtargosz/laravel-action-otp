<?php

namespace Jtargosz\ActionOtp\Http\Controllers;

use Illuminate\Http\Request;
use Jtargosz\ActionOtp\Contracts\VerifyResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class VerifyController extends Controller
{
    public function __invoke(Request $request, VerifyResponse $response): Response
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64'],
            'purpose' => ['nullable', 'string', 'max:100'],
            'challenge' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $this->guarded(fn () => $this->manager()
            ->to($data['identifier'])
            ->for($data['purpose'] ?? 'default')
            ->withChallenge($data['challenge'] ?? null)
            ->verify($data['code']));

        return $response->toResponse($request, $result);
    }
}
