<?php

namespace Jtargosz\ActionOtp\Contracts;

use Jtargosz\ActionOtp\Support\OtpResult;

interface ManagesCodes
{
    /**
     * Scope to one identifier (email, phone, user id). Compared exactly, so
     * normalize it first.
     */
    public function to(string|int $identifier): static;

    /**
     * Scope to a purpose, so one identifier can have several pending actions.
     * Defaults to "default".
     */
    public function for(string $purpose): static;

    /**
     * Use the challenge token returned by send() instead of the browser
     * session. Meant for API clients.
     */
    public function withChallenge(?string $challenge): static;

    /**
     * Scope by identifier hash. Used by the signed magic link, which never
     * carries the plain identifier.
     *
     * @internal
     */
    public function forIdentifierHash(string $identifierHash): static;

    public function send(VerifiableAction $action, object $notifiable): OtpResult;

    public function verify(string $code): OtpResult;

    public function peek(string $code): OtpResult;

    /**
     * @internal Called by the magic link route.
     */
    public function verifyLink(string $token): OtpResult;

    public function resend(): OtpResult;

    public function clear(): void;
}
