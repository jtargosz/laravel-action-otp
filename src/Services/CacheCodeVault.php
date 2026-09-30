<?php

namespace Jtargosz\ActionOtp\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Jtargosz\ActionOtp\Contracts\StoresCodes;
use Jtargosz\ActionOtp\Support\Keys;

/**
 * @phpstan-import-type OtpRecord from StoresCodes
 */
class CacheCodeVault implements StoresCodes
{
    /**
     * @param  OtpRecord  $record
     */
    public function put(string $identifierHash, string $challengeHash, array $record): void
    {
        Cache::put(Keys::record($identifierHash, $challengeHash), $record, self::ttl($record['expires_at']));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $identifierHash, string $challengeHash): ?array
    {
        $record = Cache::get(Keys::record($identifierHash, $challengeHash));

        return is_array($record) ? $record : null;
    }

    public function forget(string $identifierHash, string $challengeHash): void
    {
        Cache::forget(Keys::record($identifierHash, $challengeHash));
    }

    /**
     * Seconds to keep a record: its lifetime plus the grace period in which an
     * expired code still reads as "expired" instead of "empty".
     */
    public static function ttl(\DateTimeInterface $expiresAt): int
    {
        $grace = max(0, (int) config('action-otp.expired_grace_minutes', 5));

        $ttl = $expiresAt->getTimestamp() - Carbon::now()->getTimestamp() + $grace * 60;

        return max(60, $ttl);
    }
}
