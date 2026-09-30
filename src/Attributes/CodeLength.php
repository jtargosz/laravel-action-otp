<?php

namespace Jtargosz\ActionOtp\Attributes;

use Attribute;

/**
 * Code length for this action (1-64), overrides `code_length`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class CodeLength
{
    public function __construct(public readonly int $length) {}
}
