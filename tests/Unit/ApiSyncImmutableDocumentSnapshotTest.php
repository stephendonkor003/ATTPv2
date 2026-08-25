<?php

use App\Exceptions\ApiSyncDocumentHeldException;
use App\Jobs\BuildApiSyncDocumentSnapshot;
use App\Services\ApiSync\ApiSyncDocumentSecurityPolicy;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

function bootApiSyncDocumentTestApplication(): bool
{
    if (! Container::getInstance()->bound(Kernel::class)) {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $application->make(Kernel::class)->bootstrap();

        return true;
    }

    return false;
}

it('freezes a safe non-empty private file and rejects empty or escaping sources', function () {
    $booted = bootApiSyncDocumentTestApplication();
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'attp-sync-doc-'.bin2hex(random_bytes(8));
    mkdir($root, 0700, true);
    config([
        'filesystems.disks.api_sync_document_test' => [
            'driver' => 'local',
            'root' => $root,
            'serve' => false,
            'throw' => false,
        ],
        'api_sync_documents.disk' => 'api_sync_document_test',
        'api_sync_documents.staging_prefix' => 'api-sync/v2-document-snapshots',
        'api_sync_documents.maximum_file_bytes' => 20 * 1_024 * 1_024,
        'api_sync_documents.allowed_extensions' => ['pdf', 'csv'],
    ]);
    Storage::forgetDisk('api_sync_document_test');

    try {
        mkdir($root.DIRECTORY_SEPARATOR.'approved', 0700, true);
        $csv = "indicator,value\nA,1\n";
        file_put_contents($root.DIRECTORY_SEPARATOR.'approved'.DIRECTORY_SEPARATOR.'results.csv', $csv);
        file_put_contents($root.DIRECTORY_SEPARATOR.'approved'.DIRECTORY_SEPARATOR.'empty.pdf', '');
        $activePdf = "%PDF-1.4\n1 0 obj\n<< /OpenAction 2 0 R >>\nendobj\n%%EOF";
        file_put_contents($root.DIRECTORY_SEPARATOR.'approved'.DIRECTORY_SEPARATOR.'active.pdf', $activePdf);
        $policy = new ApiSyncDocumentSecurityPolicy;
        $snapshot = '014f5f7f-2f59-4c03-9212-5d8dbbcb15f5';
        $transfer = 'f35c0866-ddcd-4edb-b7fd-9e5d771eab84';
        $destination = $policy->destinationPath($snapshot, $transfer);
        $result = $policy->stage(
            'approved/results.csv',
            $destination,
            'results.csv',
            strlen($csv),
            hash('sha256', $csv),
            fn (): bool => true,
        );

        expect($result['byte_size'])->toBe(strlen($csv))
            ->and($result['sha256'])->toBe(hash('sha256', $csv))
            ->and($result['detected_mime'])->toBe('text/csv')
            ->and(Storage::disk('api_sync_document_test')->get($destination))->toBe($csv);

        $emptyHold = null;
        try {
            $policy->stage(
                'approved/empty.pdf',
                $policy->destinationPath($snapshot, 'c5ccca99-e9b0-4b79-a587-c7be6495d481'),
                'empty.pdf',
                0,
            );
        } catch (ApiSyncDocumentHeldException $exception) {
            $emptyHold = $exception->holdCode;
        }
        expect($emptyHold)->toBe('empty_file_not_allowed');

        $activeHold = null;
        try {
            $policy->stage(
                'approved/active.pdf',
                $policy->destinationPath($snapshot, '21fcb86f-f521-4d43-b82a-19436506200e'),
                'active.pdf',
                strlen($activePdf),
            );
        } catch (ApiSyncDocumentHeldException $exception) {
            $activeHold = $exception->holdCode;
        }
        expect($activeHold)->toBe('active_or_encrypted_content_rejected');

        $pathHold = null;
        try {
            $policy->stage('../outside.csv', $policy->destinationPath($snapshot, '7d683330-79ad-4784-badd-29beb73943f3'), 'outside.csv');
        } catch (ApiSyncDocumentHeldException $exception) {
            $pathHold = $exception->holdCode;
        }
        expect($pathHold)->toBe('invalid_source_path');
    } finally {
        (new Filesystem)->deleteDirectory($root);
        Storage::forgetDisk('api_sync_document_test');
        if ($booted) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('uses only the approved project-document allowlist and freezes capture relationships', function () {
    $root = dirname(__DIR__, 2);
    $source = file_get_contents($root.'/app/Services/ApiSync/ApiSyncDocumentSource.php');

    expect($source)
        ->toContain("DB::table('me_performance_report_documents as document')")
        ->toContain("whereIn('report.status', ['approved', 'archived'])")
        ->toContain("where('document.validation_status', 'validated')")
        ->toContain("DB::table('me_mission_report_documents as document')")
        ->toContain("whereIn('report.status', ['reviewed', 'archived'])")
        ->toContain("DB::table('me_repository_document_versions as version')")
        ->toContain("where('item.validation_status', 'validated')")
        ->toContain("whereNull('item.retired_at')")
        ->toContain("where('newer_version.updated_at', '<=', \$capturedAt)")
        ->toContain("where('newer_version.created_at', '<=', \$capturedAt)")
        ->toContain("where('report.updated_at', '<=', \$capturedAt)")
        ->toContain("where('report.created_at', '<=', \$capturedAt)")
        ->toContain("where('item.updated_at', '<=', \$capturedAt)")
        ->toContain("where('item.created_at', '<=', \$capturedAt)")
        ->toContain("where('link.updated_at', '<=', \$capturedAt)")
        ->toContain("where('link.created_at', '<=', \$capturedAt)")
        ->toContain("whereColumn('approved_link.repository_item_id', 'item.id')")
        ->toContain("whereColumn('approved_document.repository_item_id', 'item.id')")
        ->toContain("whereNotNull('approved_report.project_component_id')")
        ->toContain("whereIn('approved_report.status', ['approved', 'archived'])")
        ->toContain("whereColumn('approved_report.portfolio_id', 'item.portfolio_id')")
        ->toContain("whereColumn('report.portfolio_id', 'approved_item.portfolio_id')")
        ->toContain('withoutRepositoryDuplicates')
        ->toContain("whereColumn('repository_item.id', 'document.repository_item_id')")
        ->toContain('stillApproved')
        ->not->toContain('myb_program_funding_documents')
        ->not->toContain('myb_purchase_request_attachments')
        ->not->toContain('me_submission_evidence')
        ->not->toContain('procurement_documents')
        ->not->toContain('external_url');
});

it('persists a durable private manifest with database-enforced immutability', function () {
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents($root.'/database/migrations/2026_08_25_000006_create_api_sync_document_snapshots.php');
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncDocumentSnapshotService.php');
    preg_match('/foreach \(\$pending as \$document\).*?\n        \$remaining =/s', $service, $buildLoop);

    expect($migration)
        ->toContain("Schema::create('api_sync_snapshot_documents'")
        ->toContain("char('source_key', 64)")
        ->toContain("char('source_revision', 64)")
        ->toContain("char('sha256', 64)")
        ->toContain('byte_size > 0 AND byte_size <= 20971520')
        ->toContain("classification = 'restricted'")
        ->toContain('display_filename IS NOT NULL AND detected_mime IS NOT NULL')
        ->toContain("storage_path LIKE 'api-sync/v2-document-snapshots/%'")
        ->toContain('api_sync_document_issues_immutable_trigger')
        ->toContain('api_sync_snapshot_documents_transition_guard_trigger')
        ->toContain("state IN ('preparing','ready','held','purged')");
    expect($service)
        ->toContain("'source_revision' => \$candidate['revision']")
        ->toContain('array_chunk($rows, 200)')
        ->toContain("where('state', ApiSyncSnapshotDocument::STATE_PREPARING)")
        ->toContain('documents_per_job')
        ->toContain('maximum_job_seconds')
        ->toContain('sync_document_snapshot_checkpointed')
        ->toContain('hasContinuation')
        ->toContain("->orWhere('document_snapshot_status', self::STATUS_FAILED)")
        ->toContain("Cache::lock('api-sync-document-snapshot-build:'")
        ->toContain('document_snapshot_status !== self::STATUS_PURGE_PENDING')
        ->toContain("->whereIn('state', [")
        ->toContain('ApiSyncSnapshotDocument::STATE_PREPARING')
        ->toContain('ApiSyncSnapshotDocument::STATE_READY')
        ->toContain('lockForUpdate()');
    expect($buildLoop[0] ?? '')
        ->not->toBe('')
        ->not->toContain('catch (RuntimeException)');
});

it('revalidates document authority under locks in the same transaction that publishes ready', function () {
    $root = dirname(__DIR__, 2);
    $source = file_get_contents($root.'/app/Services/ApiSync/ApiSyncDocumentSource.php');
    $snapshot = file_get_contents($root.'/app/Services/ApiSync/ApiSyncDocumentSnapshotService.php');
    $commit = str($snapshot)->after('private function commitStagedDocument(')->before('/** @param array<string,mixed>|null $candidate')->toString();

    expect($source)
        ->toContain('public function stillApprovedForUpdate(array $candidate): bool')
        ->toContain('currentPerformance((string) $candidate[\'source_document_id\'], true)')
        ->toContain('currentMission((string) $candidate[\'source_document_id\'], true)')
        ->toContain('$this->approvedKnowledgeProjects([$itemId], now()->addSecond(), $forUpdate)')
        ->toContain('if ($forUpdate) {')
        ->toContain('$query->lockForUpdate();')
        ->toContain('$linkedQuery->lockForUpdate();')
        ->toContain('$attachedQuery->lockForUpdate();');
    expect($commit)
        ->toContain('return DB::transaction(function () use (')
        ->toContain('ApiSyncInvitation::query()->lockForUpdate()')
        ->toContain('ApiSyncPairing::query()->lockForUpdate()')
        ->toContain('ApiSyncSnapshotDocument::query()->lockForUpdate()')
        ->toContain('$this->source->stillApprovedForUpdate($candidate)')
        ->toContain("'state' => ApiSyncSnapshotDocument::STATE_READY")
        ->toContain('}, 3);');
    expect(strpos($commit, 'ApiSyncInvitation::query()->lockForUpdate()'))
        ->toBeLessThan(strpos($commit, 'ApiSyncPairing::query()->lockForUpdate()'))
        ->and(strpos($commit, 'ApiSyncPairing::query()->lockForUpdate()'))
        ->toBeLessThan(strpos($commit, 'ApiSyncSnapshotDocument::query()->lockForUpdate()'))
        ->and(strpos($commit, '$this->source->stillApprovedForUpdate($candidate)'))
        ->toBeLessThan(strpos($commit, "'state' => ApiSyncSnapshotDocument::STATE_READY"));
});

it('runs encrypted bounded continuations and exposes only authenticated ranged content', function () {
    $interfaces = class_implements(BuildApiSyncDocumentSnapshot::class);
    $root = dirname(__DIR__, 2);
    $job = file_get_contents($root.'/app/Jobs/BuildApiSyncDocumentSnapshot.php');
    $routes = file_get_contents($root.'/routes/api.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/Api/ApiSyncDocumentController.php');
    $filesystems = file_get_contents($root.'/config/filesystems.php');
    $invitations = file_get_contents($root.'/app/Services/ApiSync/ApiSyncInvitationService.php');

    expect($interfaces)->toContain(ShouldQueue::class)
        ->toContain(ShouldBeEncrypted::class)
        ->toContain(ShouldBeUniqueUntilProcessing::class);
    expect($job)
        ->toContain('hasContinuation')
        ->toContain('delay(now()->addSeconds(5))');
    expect($routes)
        ->toContain("'/documents/inventory'")
        ->toContain("'/documents/{transferId}/content'")
        ->toContain("'api.sync.v2'");
    expect($controller)
        ->toContain("header('X-AUPReMIS-Invitation-Id')")
        ->toContain("header('Range')")
        ->toContain("header('If-Match')")
        ->toContain("'Content-Range'")
        ->toContain("'X-Content-SHA256'")
        ->toContain("'X-Chunk-SHA256'")
        ->toContain("'X-Document-Transfer-Id'")
        ->not->toContain('storage_path');
    expect($filesystems)
        ->toContain("'api_sync_documents' => [")
        ->toContain("'serve' => false");
    expect($invitations)
        ->toContain('invalid_document_scope_pair')
        ->toContain('if ($metadata !== $content)');
});
