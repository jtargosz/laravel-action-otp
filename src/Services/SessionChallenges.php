<?php

namespace Jtargosz\ActionOtp\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * Remembers which challenge belongs to the current browser session. The
 * request is looked up on every call, so the singleton stays Octane safe.
 */
class SessionChallenges
{
    public function get(string $identifierHash, string $purposeHash): ?string
    {
        $value = $this->session()?->get($this->key($identifierHash, $purposeHash));

        return is_string($value) ? $value : null;
    }

    public function put(string $identifierHash, string $purposeHash, string $challengeHash): bool
    {
        $session = $this->session();

        if ($session === null) {
            return false;
        }

        $session->put($this->key($identifierHash, $purposeHash), $challengeHash);

        return true;
    }

    public function forget(string $identifierHash, string $purposeHash): void
    {
        $this->session()?->forget($this->key($identifierHash, $purposeHash));
    }

    public function available(): bool
    {
        return $this->session() !== null;
    }

    protected function session(): ?Session
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request && $request->hasSession() ? $request->session() : null;
    }

    protected function key(string $identifierHash, string $purposeHash): string
    {
        return 'action-otp.challenges.'.$identifierHash.'.'.$purposeHash;
    }
}
