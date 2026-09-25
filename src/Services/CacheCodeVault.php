<?php

namespace Jtargosz\ActionOtp\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Jtargosz\ActionOtp\Contracts\StoresCodes;
use Jtargosz\ActionOtp\Exceptions\MissingIdentifier;

class CacheCodeVault implements StoresCodes
{
    protected string $hash = '';

    public function scope(string $identifier): static
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new MissingIdentifier('Identifier is empty.');
        }

        $clone = clone $this;
        $clone->hash = hash('sha256', $identifier);

        return $clone;
    }

    /**
     * @param  array{action: mixed, notifiable: mixed, code: string, expires_at: \DateTimeInterface}  $record
     */
    public function put(array $record): void
    {
        $grace = max(0, (int) config('action-otp.expired_grace_minutes', 5));

        $ttl = $record['expires_at']->getTimestamp() - Carbon::now()->getTimestamp() + $grace * 60;

        Cache::put($this->key(), $record, max(60, $ttl));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        $record = Cache::get($this->key());

        return is_array($record) ? $record : null;
    }

    public function flush(): void
    {
        Cache::forget($this->key());
        Cache::forget($this->key().':tries');
        Cache::forget($this->key().':locked');
        Cache::forget($this->key().':sent_at');
    }

    protected function key(): string
    {
        if ($this->hash === '') {
            throw new MissingIdentifier('No identifier set.');
        }

        return (string) config('action-otp.store_prefix', 'action-otp:').$this->hash;
    }
}
