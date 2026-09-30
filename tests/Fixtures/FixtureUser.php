<?php

namespace Jtargosz\ActionOtp\Tests\Fixtures;

use Illuminate\Auth\GenericUser;
use Illuminate\Notifications\Notifiable;

class FixtureUser extends GenericUser
{
    use Notifiable;

    public function getEmailForVerification(): string
    {
        return (string) $this->attributes['email'];
    }

    public function getKey(): mixed
    {
        return $this->attributes['id'];
    }
}
