<?php

return [
    'authentication_method' => env('AUTHENTICATION_METHOD', 'OTP'), // OTP | LINK
    'otp_or_link_expiry_time' => (int) env('OTP_OR_LINK_EXPIRY_TIME', 30), // minutes
    // Include OTP in API responses only for non-production environments.
    //
    // Production short-circuits to false before the env var is even consulted,
    // so an EXPOSE_OTP_IN_RESPONSE=true line copied into a production .env
    // cannot turn OTP leakage back on.
    'expose_otp_in_response' => env('APP_ENV', 'production') === 'production'
        ? false
        : filter_var(
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
    // BE-61 Part C: the old organizer-scans-volunteer flow (scan/,
    // scan-permissions/*) is being retired in favor of the volunteer's own
    // self-scan. The frontend is dropping four screens for it and asked that
    // both sides go in the same release, so this stays "on" until that
    // release date is set — flip it to false then, rather than deleting the
    // code before the frontend is ready. See FURSA_BACKEND_ISSUES Part C.
    'organizer_scan_flow_enabled' => filter_var(
        env('ORGANIZER_SCAN_FLOW_ENABLED', 'true'),
        FILTER_VALIDATE_BOOLEAN
    ),
];
