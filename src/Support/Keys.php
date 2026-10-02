<?php

namespace Jtargosz\ActionOtp\Support;

/**
 * Cache key layout. Identifiers, purposes and challenges only ever appear as
 * SHA-256 hashes, never in plain text.
 *
 * @internal
 */
final class Keys
{
    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    public static function isHash(string $value): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    public static function prefix(): string
    {
        return (string) config('action-otp.store_prefix', 'action-otp:');
    }

    public static function record(string $identifierHash, string $challengeHash): string
    {
        return self::prefix().$identifierHash.':c:'.$challengeHash;
    }

    public static function pointer(string $identifierHash, string $purposeHash): string
    {
        return self::prefix().$identifierHash.':i:'.$purposeHash;
    }

    public static function identifier(string $identifierHash, string $suffix): string
    {
        return self::prefix().$identifierHash.$suffix;
    }
}
