<?php

namespace Jtargosz\ActionOtp\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Resends the pending code of one identifier. The identifier comes from your
 * code (for example the authenticated user), never from the model.
 */
class ResendOtpTool implements Tool
{
    public function __construct(
        protected string|int $identifier,
        protected string $purpose = 'default',
        protected ?string $challenge = null,
    ) {}

    public function description(): string|Stringable
    {
        return 'Resend the pending one time verification code to the current user.';
    }

    public function handle(Request $request): string|Stringable
    {
        $result = ActionOtp::to($this->identifier)
            ->for($this->purpose)
            ->withChallenge($this->challenge)
            ->resend();

        return $result->status->value.': '.$result->message;
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
