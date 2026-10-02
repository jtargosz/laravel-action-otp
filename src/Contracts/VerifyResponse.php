<?php

namespace Jtargosz\ActionOtp\Contracts;

use Illuminate\Http\Request;
use Jtargosz\ActionOtp\Support\OtpResult;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a verify result of the package routes (verify, link, confirm) into a
 * response. Bind your own implementation in the container to change it.
 */
interface VerifyResponse
{
    public function toResponse(Request $request, OtpResult $result): Response;
}
