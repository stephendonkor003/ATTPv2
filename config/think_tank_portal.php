<?php

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env(
        'THINK_TANK_PORTAL_ALLOWED_ORIGINS',
        'http://localhost:3000,http://localhost:3100,http://127.0.0.1:3000,http://127.0.0.1:3100'
    ))
)));

$trustedProxies = array_values(array_filter(array_map(
    static fn (string $proxy): string => trim($proxy),
    explode(',', (string) env('THINK_TANK_TRUSTED_PROXIES', ''))
)));

return [
    'frontend_url' => rtrim((string) env('THINK_TANK_PORTAL_URL', 'http://localhost:3000'), '/'),
    'allowed_origins' => $origins,
    // Exact Next/reverse-proxy addresses or CIDRs only. Never use *.
    'trusted_proxies' => $trustedProxies,
    'password_reset_path' => '/reset-password',
    'password_min_length' => 12,
    'password_max_bytes' => 72,
    // A valid hash for a value no account can use. Unknown and duplicate
    // emails perform the same single password check as known accounts.
    'dummy_password_hash' => env(
        'THINK_TANK_PORTAL_DUMMY_PASSWORD_HASH',
        '$2y$12$IsdzQWyVSSHhdASAL3eTxOB.hnP7iF0GTKlF0SQpRImnWCEaUE8QO'
    ),
    'require_mfa' => env('THINK_TANK_PORTAL_REQUIRE_MFA', true),
    'mfa_verification_hours' => 24,
    'mfa_resend_seconds' => 60,
    'mfa_issue_max_per_hour' => 5,
    'mfa_verify_max_attempts' => 5,
    'mfa_verify_account_max_attempts' => 10,
    'mfa_verify_decay_seconds' => 600,
    'login_email_max_attempts' => 20,
    'login_email_decay_seconds' => 900,
    // This store must be shared by every application node in production.
    // Database and Redis stores provide the required cross-process lock.
    'email_lock_store' => env('THINK_TANK_PORTAL_LOCK_STORE', env('CACHE_STORE', 'database')),
    'email_lock_seconds' => 120,
    'email_lock_wait_seconds' => 5,
];
