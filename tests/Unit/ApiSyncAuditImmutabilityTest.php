<?php

it('enforces API synchronization audit history as append only in PostgreSQL', function () {
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents($root.'/database/migrations/2026_08_25_000008_enforce_api_sync_audit_append_only.php');
    $eventModel = file_get_contents($root.'/app/Models/ApiSyncEvent.php');
    $invitationEventModel = file_get_contents($root.'/app/Models/ApiSyncInvitationEvent.php');

    expect($migration)
        ->toContain("'api_sync_events'")
        ->toContain("'api_sync_invitation_events'")
        ->toContain('BEFORE UPDATE OR DELETE')
        ->toContain('BEFORE TRUNCATE')
        ->toContain('attp_api_sync_audit_append_only_guard');

    expect($eventModel)
        ->toContain('API sync events are append-only.');
    expect($invitationEventModel)
        ->toContain('API synchronization invitation events are append-only.');
});
