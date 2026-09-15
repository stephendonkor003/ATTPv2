<?php

return [
    // All application email uses the single Laravel default transport.
    'mailer' => null,
    'coordinator_email' => env('ASSISTANT_COORDINATOR_EMAIL', 'chirwat@africanunion.org'),
];
