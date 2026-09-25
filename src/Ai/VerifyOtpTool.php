<?php

namespace Jtargosz\ActionOtp\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class VerifyOtpTool implements Tool
{
    public function description(): string|Stringable
    {
        return 'Check a one time verification code without running the action.';
    }

    public function handle(Request $request): string|Stringable
    {
        $result = ActionOtp::to($request['identifier'])->peek($request['code']);

        return $result->status->value.': '.$result->message;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()->required(),
            'code' => $schema->string()->required(),
        ];
    }
}
