<?php

namespace Jtargosz\ActionOtp\Attributes;

use Attribute;

/**
 * Code lifetime in minutes for this action, overrides `ttl_minutes`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Ttl
{
    public function __construct(public readonly int $minutes) {}
}
