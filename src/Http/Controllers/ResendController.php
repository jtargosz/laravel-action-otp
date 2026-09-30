<?php

namespace Jtargosz\ActionOtp\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ResendController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:100'],
            'challenge' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $this->guarded(fn () => $this->manager()
            ->to($data['identifier'])
            ->for($data['purpose'] ?? 'default')
            ->withChallenge($data['challenge'] ?? null)
            ->resend());

        return $this->sendResponse($request, $result);
    }
}
