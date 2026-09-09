<?php

return [
    'authentication_method' => env('AUTHENTICATION_METHOD', 'OTP'), // OTP | LINK
    'otp_or_link_expiry_time' => (int) env('OTP_OR_LINK_EXPIRY_TIME', 30), // minutes
    // Include OTP in API responses only for non-production environments (local/testing).
    'expose_otp_in_response' => filter_var(
        env(
            'EXPOSE_OTP_IN_RESPONSE',
            in_array(env('APP_ENV', 'production'), ['local', 'testing'], true) ? 'true' : 'false'
        ),
        FILTER_VALIDATE_BOOLEAN
    ),
    'frontend_host' => env('FRONTEND_HOST', 'http://localhost:3000'),
    'backend_host' => env('BACKEND_HOST', 'http://localhost:8000'),
    'storage_path' => env('STORAGE_PATH', 'uploads'),
    // Reject write payloads carrying keys the endpoint does not recognise, so a
    // renamed or mistyped field fails loudly instead of being silently dropped.
    // Set REJECT_UNKNOWN_WRITE_KEYS=false to disable without a deploy if it ever
    // blocks a legitimate client.
    'reject_unknown_write_keys' => filter_var(
        env('REJECT_UNKNOWN_WRITE_KEYS', 'true'),
        FILTER_VALIDATE_BOOLEAN
    ),
    'token_expiry_days' => [
        'default' => 1,
        'remember' => 30,
        'social' => 30,
    ],
];
