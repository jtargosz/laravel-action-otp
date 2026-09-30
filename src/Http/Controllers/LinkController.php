<?php

namespace Jtargosz\ActionOtp\Http\Controllers;

use Illuminate\Http\Request;
use Jtargosz\ActionOtp\Contracts\VerifyResponse;
use Jtargosz\ActionOtp\Support\Keys;
use Symfony\Component\HttpFoundation\Response;

/**
 * Magic link. GET only shows a confirmation page, so mail scanners that open
 * links consume nothing. The POST from that page verifies.
 *
 * @internal
 */
class LinkController extends Controller
{
    public function show(Request $request): Response
    {
        [, $purpose] = $this->params($request);

        return $this->view('link', $request, [
            // Exactly the signed URL, so the form POST passes the signature check.
            'url' => $request->getSchemeAndHttpHost().$request->getRequestUri(),
            'purpose' => $purpose,
        ]);
    }

    public function store(Request $request, VerifyResponse $response): Response
    {
        [$identifierHash, $purpose, $token] = $this->params($request);

        $result = $this->manager()
            ->forIdentifierHash($identifierHash)
            ->for($purpose)
            ->verifyLink($token);

        return $response->toResponse($request, $result);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function params(Request $request): array
    {
        $identifierHash = $request->query('i');
        $purpose = $request->query('p');
        $token = $request->query('t');

        if (! is_string($identifierHash) || ! Keys::isHash($identifierHash)
            || ! is_string($purpose) || trim($purpose) === ''
            || ! is_string($token) || $token === '') {
            abort(404);
        }

        return [$identifierHash, $purpose, $token];
    }
}
