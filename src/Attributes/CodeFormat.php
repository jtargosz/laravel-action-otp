<?php

namespace Jtargosz\ActionOtp\Attributes;

use Attribute;

/**
 * Code format for this action (`numeric`, `alpha`, `alphanumeric`), overrides `code_format`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class CodeFormat
{
    public function __construct(public readonly string $format) {}
}
