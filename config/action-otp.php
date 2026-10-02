<?php

use Jtargosz\ActionOtp\Mail\CodeMail;

return [
    // Defaults for every action. Override per action with the #[Ttl],
    // #[CodeFormat], #[CodeLength] and #[SendWith] attributes.
    'code_format' => env('ACTION_OTP_FORMAT', 'numeric'),

    'code_length' => (int) env('ACTION_OTP_LENGTH', 6),

    'ttl_minutes' => (int) env('ACTION_OTP_TTL', 15),

    'expired_grace_minutes' => (int) env('ACTION_OTP_GRACE', 5),

    'max_attempts' => (int) env('ACTION_OTP_ATTEMPTS', 5),

    'throttle_seconds' => (int) env('ACTION_OTP_THROTTLE', 60),

    'send_cooldown' => (int) env('ACTION_OTP_COOLDOWN', 30),

    'store_prefix' => env('ACTION_OTP_PREFIX', 'action-otp:'),

    'notification' => CodeMail::class,

    'channels' => ['mail'],

    // Host for the SMS autofill line "@host #code". Falls back to app.url.
    'sms_host' => env('ACTION_OTP_SMS_HOST'),

    // Magic link in the default mail. Needs ActionOtp::routes() and ActionOtp::linkView().
    'link' => [
        'enabled' => (bool) env('ACTION_OTP_LINK', false),
    ],

    // Requests per minute per IP for the package routes.
    'rate_limit' => (int) env('ACTION_OTP_RATE_LIMIT', 10),

    // Seconds an otp.confirm confirmation stays valid.
    'confirm_timeout' => (int) env('ACTION_OTP_CONFIRM_TIMEOUT', 900),

    // Where the package routes redirect after a successful verification (web, not JSON).
    'redirects' => [
        'verified' => '/',
    ],
];
