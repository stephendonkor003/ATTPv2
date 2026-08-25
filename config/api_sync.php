<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ATTP -> AU-PReMIS controlled data synchronization
    |--------------------------------------------------------------------------
    |
    | Pairing codes are deliberately short lived and single use. The issued
    | bearer credential is stored only as a SHA-256 digest and cannot be
    | recovered from the ATTP database.
    |
    */
    'enabled' => (bool) env('ATTP_API_SYNC_ENABLED', false),
    // Migration compatibility only. New connections use signed v2 invitations.
    'legacy_v1_enabled' => (bool) env('ATTP_API_SYNC_V1_ENABLED', false),
    'require_https' => (bool) env('ATTP_API_SYNC_REQUIRE_HTTPS', true),

    'provider' => [
        'name' => env('ATTP_API_SYNC_PROVIDER_NAME', env('APP_NAME', 'ATTP')),
        'code' => env('ATTP_API_SYNC_PROVIDER_CODE', 'ATTP'),
        'instance_id' => env('ATTP_API_SYNC_INSTANCE_ID'),
    ],

    'pairing_ttl_minutes' => max(5, (int) env('ATTP_API_SYNC_PAIRING_TTL_MINUTES', 10)),
    'session_ttl_minutes' => min(1_440, max(30, (int) env('ATTP_API_SYNC_SESSION_TTL_MINUTES', 360))),

    'pagination' => [
        'default_limit' => max(1, (int) env('ATTP_API_SYNC_DEFAULT_PAGE_SIZE', 100)),
        'maximum_limit' => max(1, (int) env('ATTP_API_SYNC_MAX_PAGE_SIZE', 250)),
    ],

    'snapshot' => [
        // Snapshot builds have a deliberately longer retry window than normal
        // web jobs. Keep them on this dedicated durable queue connection so a
        // worker cannot reserve the same long-running job twice.
        'connection' => env('ATTP_API_SYNC_SNAPSHOT_CONNECTION', 'api_sync_database'),
        'queue' => env('ATTP_API_SYNC_SNAPSHOT_QUEUE', 'api-sync'),
        'insert_chunk' => min(1_000, max(50, (int) env('ATTP_API_SYNC_SNAPSHOT_INSERT_CHUNK', 500))),
        'maximum_records' => min(1_000_000, max(1_000, (int) env('ATTP_API_SYNC_SNAPSHOT_MAX_RECORDS', 250_000))),
        'maximum_bytes' => min(2_147_483_648, max(10_485_760, (int) env('ATTP_API_SYNC_SNAPSHOT_MAX_BYTES', 536_870_912))),
        'maximum_record_bytes' => min(1_048_576, max(65_536, (int) env('ATTP_API_SYNC_SNAPSHOT_MAX_RECORD_BYTES', 262_144))),
        'maximum_build_seconds' => min(1_800, max(60, (int) env('ATTP_API_SYNC_SNAPSHOT_MAX_BUILD_SECONDS', 900))),
        'maximum_active_sessions' => min(10, max(1, (int) env('ATTP_API_SYNC_SNAPSHOT_MAX_ACTIVE_SESSIONS', 2))),
    ],

    // Set a dedicated production value. APP_KEY is a secure fallback so a
    // copied database alone is insufficient to brute-force seven-digit codes.
    'pairing_pepper' => env('ATTP_API_SYNC_PAIRING_PEPPER', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | AU-PReMIS initiated synchronization protocol (v2)
    |--------------------------------------------------------------------------
    |
    | Every deployed ATTP instance trusts exactly one central AU-PReMIS origin
    | and RSA signing key. Keep the public-key fingerprint pinned even though
    | the PEM is already configured; this prevents an unnoticed key-file swap.
    |
    */
    'v2' => [
        'enabled' => (bool) env('ATTP_API_SYNC_V2_ENABLED', false),
        'public_origin' => env('ATTP_API_SYNC_V2_PUBLIC_ORIGIN', env('APP_URL')),
        'maximum_clock_skew_seconds' => min(900, max(30, (int) env('ATTP_API_SYNC_V2_CLOCK_SKEW_SECONDS', 300))),
        'maximum_invitation_ttl_minutes' => min(60, max(5, (int) env('ATTP_API_SYNC_V2_INVITATION_TTL_MINUTES', 15))),
        'maximum_approval_attempts' => min(10, max(3, (int) env('ATTP_API_SYNC_V2_MAX_APPROVAL_ATTEMPTS', 5))),
        'confirmation_timeout_seconds' => min(60, max(5, (int) env('ATTP_API_SYNC_V2_CONFIRMATION_TIMEOUT_SECONDS', 25))),
        'central' => [
            'instance_id' => env('ATTP_API_SYNC_V2_AUP_INSTANCE_ID'),
            'origin' => env('ATTP_API_SYNC_V2_AUP_ORIGIN'),
            'key_id' => env('ATTP_API_SYNC_V2_AUP_KEY_ID'),
            'public_key_path' => env('ATTP_API_SYNC_V2_AUP_PUBLIC_KEY_PATH'),
            'public_key_pem' => env('ATTP_API_SYNC_V2_AUP_PUBLIC_KEY_PEM'),
            'public_key_sha256' => env('ATTP_API_SYNC_V2_AUP_PUBLIC_KEY_SHA256'),
            'allow_private_networks' => (bool) env('ATTP_API_SYNC_V2_ALLOW_PRIVATE_NETWORKS', false),
            'allowed_ips' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('ATTP_API_SYNC_V2_ALLOWED_IPS', '')),
            ))),
        ],
        'allowed_datasets' => [
            'portfolios',
            'programmes',
            'projects',
            'activities',
            'sub_activities',
            'fiscal_years',
            'budget_allocations',
            'commitments',
            'executions',
        ],
        'allowed_scopes' => [
            'records.read',
            'documents.metadata.read',
            'documents.content.read',
        ],
        // Both scopes must be explicitly requested and locally approved before
        // private immutable document bytes are staged or served. The provider
        // never serves live source paths or documents outside the fixed M&E
        // allowlist.
        'documents' => [
            'enabled' => (bool) env('ATTP_API_SYNC_V2_DOCUMENTS_ENABLED', false),
            'metadata_scope' => 'documents.metadata.read',
            'content_scope' => 'documents.content.read',
        ],
    ],
];
