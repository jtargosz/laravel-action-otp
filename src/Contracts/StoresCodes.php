<?php

namespace Jtargosz\ActionOtp\Contracts;

interface StoresCodes
{
    public function scope(string $identifier): static;

    /**
     * @param  array{action: mixed, notifiable: mixed, code: string, expires_at: \DateTimeInterface}  $record
     */
    public function put(array $record): void;

    /**
     * @return array<string, mixed>|null
     */
    public function get(): ?array;

    public function flush(): void;
}
