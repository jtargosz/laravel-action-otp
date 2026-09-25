<?php

namespace Jtargosz\ActionOtp\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SendOtpTool implements Tool
{
    public function description(): string|Stringable
    {
        return 'Resend the pending one time verification code to the given identifier.';
    }

    public function handle(Request $request): string|Stringable
    {
        $result = ActionOtp::to($request['identifier'])->resend();

        return $result->status->value.': '.$result->message;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()->required(),
        ];
    }
}
