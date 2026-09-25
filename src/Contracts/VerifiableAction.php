<?php

namespace Jtargosz\ActionOtp\Contracts;

interface VerifiableAction
{
    public function handle(): mixed;
}
