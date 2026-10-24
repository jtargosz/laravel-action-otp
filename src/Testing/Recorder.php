<?php

namespace Jtargosz\ActionOtp\Testing;

use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use Jtargosz\ActionOtp\Support\OtpMessage;

/**
 * Shared by every clone of one fake, so to() and for() calls record into the
 * same place.
 *
 * @phpstan-type SentRecord array{identifier: string, purpose: string, action: VerifiableAction, message: OtpMessage, challenge: string|null}
 * @phpstan-type VerifiedRecord array{identifier: string, purpose: string, action: VerifiableAction, payload: mixed}
 *
 * @internal
 */
final class Recorder
{
    /**
     * @var list<SentRecord>
     */
    public array $sent = [];

    /**
     * @var list<VerifiedRecord>
     */
    public array $verified = [];
}
