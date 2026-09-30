<?php

namespace Jtargosz\ActionOtp\Attributes;

use Attribute;

/**
 * Lets the code (and link) for this action be used from any device, without
 * the browser session or challenge token that started the flow.
 *
 * Use only for low risk actions: whoever triggers a send for an identifier
 * replaces its pending AnyDevice action, so the owner could confirm an action
 * someone else started.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AnyDevice {}
