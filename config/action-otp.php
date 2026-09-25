<?php

use Jtargosz\ActionOtp\Mail\CodeMail;

return [
    'code_format' => env('ACTION_OTP_FORMAT', 'numeric'),

    'code_length' => (int) env('ACTION_OTP_LENGTH', 6),

    'ttl_minutes' => (int) env('ACTION_OTP_TTL', 15),

    'max_attempts' => (int) env('ACTION_OTP_ATTEMPTS', 5),

    'throttle_seconds' => (int) env('ACTION_OTP_THROTTLE', 60),

    'send_cooldown' => (int) env('ACTION_OTP_COOLDOWN', 30),

    'store_prefix' => env('ACTION_OTP_PREFIX', 'action-otp:'),

    'notification' => CodeMail::class,

    'channels' => ['mail'],
];
