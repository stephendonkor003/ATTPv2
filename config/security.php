<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local Login OTP
    |--------------------------------------------------------------------------
    |
    | Production always requires the session-bound email OTP challenge for
    | every account. Local and automated environments may opt in explicitly
    | so development never depends on an external mail delivery service.
    |
    */
    'require_login_otp_locally' => env('REQUIRE_LOGIN_OTP_LOCALLY', false),

    // Bound the duration of an administrator acting as another user. Active
    // sessions are returned to the administrator when this limit is reached.
    'impersonation_ttl_minutes' => (int) env('IMPERSONATION_TTL_MINUTES', 240),
];
