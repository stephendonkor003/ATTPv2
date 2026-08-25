<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Immutable AU-PReMIS document snapshots
    |--------------------------------------------------------------------------
    |
    | These limits are deliberately hard-capped in code as well as here. Source
    | paths are never exported: approved bytes are copied into an opaque private
    | snapshot before the central system is allowed to read them.
    |
    */
    'enabled' => (bool) env('ATTP_API_SYNC_V2_DOCUMENTS_ENABLED', false),
    'metadata_scope' => 'documents.metadata.read',
    'content_scope' => 'documents.content.read',
    'disk' => 'api_sync_documents',
    'staging_prefix' => 'api-sync/v2-document-snapshots',

    'maximum_documents' => min(1_000, max(1, (int) env('ATTP_API_SYNC_V2_DOCUMENT_MAX_COUNT', 1_000))),
    'maximum_file_bytes' => min(20 * 1_024 * 1_024, max(1_024, (int) env('ATTP_API_SYNC_V2_DOCUMENT_MAX_FILE_BYTES', 20 * 1_024 * 1_024))),
    'maximum_snapshot_bytes' => min(2 * 1_024 * 1_024 * 1_024, max(1_024, (int) env('ATTP_API_SYNC_V2_DOCUMENT_MAX_SNAPSHOT_BYTES', 2 * 1_024 * 1_024 * 1_024))),
    'maximum_chunk_bytes' => min(4 * 1_024 * 1_024, max(64 * 1_024, (int) env('ATTP_API_SYNC_V2_DOCUMENT_MAX_CHUNK_BYTES', 4 * 1_024 * 1_024))),
    'page_size' => min(250, max(1, (int) env('ATTP_API_SYNC_V2_DOCUMENT_PAGE_SIZE', 100))),
    'documents_per_job' => min(100, max(1, (int) env('ATTP_API_SYNC_V2_DOCUMENTS_PER_JOB', 25))),
    'maximum_job_seconds' => min(300, max(30, (int) env('ATTP_API_SYNC_V2_DOCUMENT_JOB_SECONDS', 240))),

    'queue' => [
        'connection' => env('ATTP_API_SYNC_SNAPSHOT_CONNECTION', 'api_sync_database'),
        'name' => env('ATTP_API_SYNC_SNAPSHOT_QUEUE', 'api-sync'),
    ],

    // Office Open XML is inspected as a package. Legacy Office, macros,
    // executables, generic archives, embedded objects and active content are
    // never admitted to the immutable snapshot.
    'allowed_extensions' => [
        'pdf', 'png', 'jpg', 'jpeg', 'txt', 'csv', 'docx', 'xlsx', 'pptx',
    ],
];
