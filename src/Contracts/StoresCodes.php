<?php

namespace Jtargosz\ActionOtp\Contracts;

use DateTimeInterface;

/**
 * Stores pending records, one per challenge. Implementations must only touch
 * the record itself: attempt counters, lockout and cooldown belong to the
 * manager and must survive forget().
 *
 * @phpstan-type OtpRecord array{
 *     identifier: string,
 *     purpose: string,
 *     action: VerifiableAction,
 *     notifiable: object,
 *     code: string,
 *     format: string,
 *     link: string|null,
 *     expires_at: DateTimeInterface,
 *     any_device: bool
 * }
 */
interface StoresCodes
{
    /**
     * @param  OtpRecord  $record
     */
    public function put(string $identifierHash, string $challengeHash, array $record): void;

    /**
     * Returns whatever is stored. The manager validates the shape, so a broken
     * or foreign value fails closed.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $identifierHash, string $challengeHash): ?array;

    public function forget(string $identifierHash, string $challengeHash): void;
}
