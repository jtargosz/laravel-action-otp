<?php

namespace Jtargosz\ActionOtp\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Jtargosz\ActionOtp\Support\Keys;

/**
 * Per identifier attempt counter, lockout, send cooldown and the slot lock
 * that serializes every operation on one identifier. Shared by all purposes
 * and challenges of that identifier.
 */
class Throttle
{
    public function lock(string $identifierHash): ?Lock
    {
        $lock = Cache::lock(Keys::identifier($identifierHash, ':verify'), 10);

        try {
            $lock->block(2);
        } catch (LockTimeoutException) {
            return null;
        }

        return $lock;
    }

    public function locked(string $identifierHash): bool
    {
        return Cache::has(Keys::identifier($identifierHash, ':locked'));
    }

    public function hit(string $identifierHash): void
    {
        $key = Keys::identifier($identifierHash, ':tries');
        $max = (int) config('action-otp.max_attempts', 5);

        // add() only creates the counter (with its window) when it is missing,
        // increment() then bumps it atomically on every cache driver.
        Cache::add($key, 0, 3600);
        $tries = (int) Cache::increment($key);

        $seconds = (int) config('action-otp.throttle_seconds', 60);

        if ($tries >= $max && $seconds > 0) {
            Cache::put(Keys::identifier($identifierHash, ':locked'), true, Carbon::now()->addSeconds($seconds));
        }
    }

    /**
     * Only a successful verification may call this.
     */
    public function reset(string $identifierHash): void
    {
        Cache::forget(Keys::identifier($identifierHash, ':tries'));
        Cache::forget(Keys::identifier($identifierHash, ':locked'));
        $this->forgetSent($identifierHash);
    }

    public function coolingDown(string $identifierHash): bool
    {
        return Cache::has(Keys::identifier($identifierHash, ':sent_at'));
    }

    public function markSent(string $identifierHash): void
    {
        $cooldown = (int) config('action-otp.send_cooldown', 30);

        if ($cooldown > 0) {
            Cache::put(Keys::identifier($identifierHash, ':sent_at'), true, Carbon::now()->addSeconds($cooldown));
        }
    }

    public function forgetSent(string $identifierHash): void
    {
        Cache::forget(Keys::identifier($identifierHash, ':sent_at'));
    }
}
