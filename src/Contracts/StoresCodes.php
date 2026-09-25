<?php

namespace Jtargosz\ActionOtp\Contracts;

interface StoresCodes
{
    public function scope(string $identifier): static;

    public function put(array $record): void;

    public function get(): ?array;

    public function flush(): void;
}
