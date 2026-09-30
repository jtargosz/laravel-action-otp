<?php

namespace Jtargosz\ActionOtp\Tests\Fixtures;

use Jtargosz\ActionOtp\Attributes\AnyDevice;
use Jtargosz\ActionOtp\Attributes\CodeFormat;
use Jtargosz\ActionOtp\Attributes\CodeLength;
use Jtargosz\ActionOtp\Attributes\SendWith;
use Jtargosz\ActionOtp\Attributes\Ttl;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use RuntimeException;

class ConfirmLoginAction implements VerifiableAction
{
    public function __construct(public string $result = 'logged-in') {}

    public function handle(): mixed
    {
        return $this->result;
    }
}

class SecretHoldingAction implements VerifiableAction
{
    public function __construct(public string $secret = 'top-secret-value') {}

    public function handle(): mixed
    {
        return $this->secret;
    }
}

class ThrowingAction implements VerifiableAction
{
    public function handle(): mixed
    {
        throw new RuntimeException('handle failed');
    }
}

#[CodeFormat('alphanumeric')]
class AlphanumericAction implements VerifiableAction
{
    public function handle(): mixed
    {
        return 'alnum';
    }
}

#[CodeFormat('alpha')]
#[CodeLength(8)]
class AlphaEightAction implements VerifiableAction
{
    public function handle(): mixed
    {
        return 'alpha';
    }
}

class InheritsAlphaEightAction extends AlphaEightAction {}

#[Ttl(1)]
class ShortLivedAction implements VerifiableAction
{
    public function handle(): mixed
    {
        return 'short';
    }
}

#[AnyDevice]
class AnyDeviceAction implements VerifiableAction
{
    public function handle(): mixed
    {
        return 'any-device';
    }
}

#[SendWith(FixtureSms::class)]
class SmsAction implements VerifiableAction
{
    public function handle(): mixed
    {
        return 'sms';
    }
}

#[SendWith(\stdClass::class)]
class BrokenSendWithAction implements VerifiableAction
{
    public function handle(): mixed
    {
        return null;
    }
}
