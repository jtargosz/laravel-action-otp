<?php

namespace Jtargosz\ActionOtp\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Checks a code with peek(), without running the action. The model supplies
 * only the code; the identifier is fixed by your code.
 */
class CheckOtpTool implements Tool
{
    public function __construct(
        protected string|int $identifier,
        protected string $purpose = 'default',
        protected ?string $challenge = null,
    ) {}

    public function description(): string|Stringable
    {
        return 'Check the one time verification code the current user typed, without running the action.';
    }

    public function handle(Request $request): string|Stringable
    {
        $code = $request['code'] ?? null;

        if (! is_string($code)) {
            return 'mismatch: '.__('action-otp::action-otp.mismatch');
        }

        $result = ActionOtp::to($this->identifier)
            ->for($this->purpose)
            ->withChallenge($this->challenge)
            ->peek($code);

        return $result->status->value.': '.$result->message;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->required(),
        ];
    }
}
