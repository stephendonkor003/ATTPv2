<?php

use App\Support\MePerformanceReportDocumentSafety;

it('accepts only normalized relative M&E document paths', function () {
    expect(MePerformanceReportDocumentSafety::normalizeRelativePath(
        'me\\performance-reports\\report-id\\evidence.pdf'
    ))->toBe('me/performance-reports/report-id/evidence.pdf')
        ->and(MePerformanceReportDocumentSafety::normalizeRelativePath(
            'me/performance-reports//report-id/evidence.pdf'
        ))->toBe('me/performance-reports/report-id/evidence.pdf');

    foreach ([
        null,
        '',
        '../evidence.pdf',
        'me/performance-reports/report-id/../evidence.pdf',
        'me/performance-reports/report-id/evidence..pdf',
        "me/performance-reports/report-id/evidence.pdf\0ignored",
        '/me/performance-reports/report-id/evidence.pdf',
        'C:/storage/evidence.pdf',
        'file://storage/evidence.pdf',
    ] as $unsafePath) {
        expect(MePerformanceReportDocumentSafety::normalizeRelativePath($unsafePath))->toBeNull();
    }
});

it('requires the exact report or repository directory boundary', function () {
    $reportPath = 'me/performance-reports/report-id/evidence.pdf';

    expect(MePerformanceReportDocumentSafety::hasExpectedPrefix(
        $reportPath,
        ['me/performance-reports/report-id']
    ))->toBeTrue()
        ->and(MePerformanceReportDocumentSafety::hasExpectedPrefix(
            $reportPath,
            ['me/performance-reports/report']
        ))->toBeFalse()
        ->and(MePerformanceReportDocumentSafety::hasExpectedPrefix(
            'me/knowledge-evidence/achievement/evidence.pdf',
            ['me/knowledge-evidence', 'me/performance-reports']
        ))->toBeTrue();
});

it('keeps resolved files inside the configured local disk root', function () {
    expect(MePerformanceReportDocumentSafety::isContainedPath(
        '/srv/application/storage/private',
        '/srv/application/storage/private/me/performance-reports/report-id/evidence.pdf'
    ))->toBeTrue()
        ->and(MePerformanceReportDocumentSafety::isContainedPath(
            '/srv/application/storage/private',
            '/srv/application/storage/private-copy/evidence.pdf'
        ))->toBeFalse()
        ->and(MePerformanceReportDocumentSafety::isContainedPath(
            '/srv/application/storage/private',
            '/srv/application/storage/evidence.pdf'
        ))->toBeFalse();
});

it('reduces download names to a bounded safe basename', function () {
    expect(MePerformanceReportDocumentSafety::safeDownloadName(
        '../../Quarterly Report.pdf',
        'me/performance-reports/report-id/stored.pdf'
    ))->toBe('Quarterly Report.pdf')
        ->and(MePerformanceReportDocumentSafety::safeDownloadName(
            "folder\\unsafe\r\nname.pdf",
            'me/performance-reports/report-id/stored.pdf'
        ))->toBe('unsafename.pdf')
        ->and(MePerformanceReportDocumentSafety::safeDownloadName(
            '..',
            'me/performance-reports/report-id/stored.pdf'
        ))->toBe('stored.pdf')
        ->and(mb_strlen(MePerformanceReportDocumentSafety::safeDownloadName(
            str_repeat('a', 220).'.pdf',
            'me/performance-reports/report-id/stored.pdf'
        )))->toBe(180);
});

it('retains shared and repository-managed report files when an owning row is removed', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/MePerformanceReportController.php');
    $apiController = file_get_contents(
        $root.'/app/Http/Controllers/Api/V1/ThinkTank/MonitoringReportsController.php'
    );

    expect($controller)
        ->toContain('safeReportDocumentPath($report, $document)')
        ->toContain('MePerformanceReportDocumentSafety::safeDownloadName')
        ->toContain('realpath($disk->path(')
        ->toContain('if (! $repositoryItemId && $path !== null)')
        ->toContain('DB::afterCommit(fn () => $this->deleteUnreferencedReportFile($report, $path))')
        ->toContain('MePerformanceReportDocument::query()->where(\'file_path\', $path)->exists()')
        ->toContain('MeKnowledgeEvidenceItem::query()->where(\'file_path\', $path)->exists()')
        ->toContain('MeRepositoryDocumentVersion::query()->where(\'file_path\', $path)->exists()')
        ->toContain('MeRepositoryDocumentLink::query()')
        ->toContain("->where('purpose', 'report_attachment')")
        ->toContain('deleteUnreferencedReportFile($report, $storedPath)');

    expect($apiController)
        ->toContain("->where('think_tank_member_id', \$member->id)")
        ->toContain("->where('report_id', \$locked->id)")
        ->toContain('parent::destroyDocument($request, $locked, $ownedDocument)');
});
