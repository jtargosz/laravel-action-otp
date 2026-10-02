<?php

namespace Jtargosz\ActionOtp\Attributes;

use Attribute;

/**
 * Notification class for this action, overrides `notification`. The class
 * must extend Illuminate\Notifications\Notification and accept an OtpMessage.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class SendWith
{
    /**
     * @param  class-string  $notification
     */
    public function __construct(public readonly string $notification) {}
}
