<?php

namespace Jtargosz\ActionOtp\Testing;

use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use Jtargosz\ActionOtp\Support\OtpMessage;

/**
 * Shared by every clone of one fake, so to() and for() calls record into the
 * same place.
 *
 * @internal
 */
final class Recorder
{
    /**
     * @var list<array{identifier: string, purpose: string, action: VerifiableAction, message: OtpMessage, challenge: string|null}>
     */
    public array $sent = [];

    /**
     * @var list<array{identifier: string, purpose: string, action: VerifiableAction, payload: mixed}>
     */
    public array $verified = [];
}
