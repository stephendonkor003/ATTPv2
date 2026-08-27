<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local Login OTP
    |--------------------------------------------------------------------------
    |
    | Keep this false while working locally. Set REQUIRE_LOGIN_OTP_LOCALLY=true
    | when you want to test the live-style OTP flow on your machine.
    |
    */
    'require_login_otp_locally' => env('REQUIRE_LOGIN_OTP_LOCALLY', false),

    // Bound the duration of an administrator acting as another user. Active
    // sessions are returned to the administrator when this limit is reached.
    'impersonation_ttl_minutes' => (int) env('IMPERSONATION_TTL_MINUTES', 240),
];
