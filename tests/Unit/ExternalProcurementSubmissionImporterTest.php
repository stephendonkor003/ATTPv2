<?php

use App\Models\Procurement;
use App\Services\ExternalProcurementSubmissionImporter;

function externalProcurementImporterFixtureDirectory(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'attp-external-procurement-'.bin2hex(random_bytes(12));

    if (! mkdir($path, 0700, true) && ! is_dir($path)) {
        throw new RuntimeException("Unable to create test fixture directory [{$path}].");
    }

    return $path;
}

function externalProcurementImporterRemoveFixtureDirectory(string $path): void
{
    $temporaryRoot = realpath(sys_get_temp_dir());
    $fixtureRoot = realpath($path);

    if ($fixtureRoot === false) {
        return;
    }

    if ($temporaryRoot === false) {
        throw new LogicException('Unable to resolve the system temporary directory.');
    }

    $expectedPrefix = $temporaryRoot.DIRECTORY_SEPARATOR.'attp-external-procurement-';

    if (! str_starts_with($fixtureRoot, $expectedPrefix)) {
        throw new LogicException("Refusing to remove unexpected fixture directory [{$path}].");
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fixtureRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($fixtureRoot);
}

function externalProcurementImporterWritePdf(string $path): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
        throw new RuntimeException("Unable to create test applicant directory [{$directory}].");
    }

    $pdf = <<<'PDF'
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R >>
endobj
4 0 obj
<< /Length 44 >>
stream
BT /F1 12 Tf 20 100 Td (Test submission) Tj ET
endstream
endobj
xref
0 5
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000202 00000 n 
trailer
<< /Root 1 0 R /Size 5 >>
startxref
296
%%EOF
PDF;

    if (file_put_contents($path, $pdf) === false) {
        throw new RuntimeException("Unable to write test PDF [{$path}].");
    }
}

function externalProcurementImporterEntryBySlug(array $manifest, string $slug): array
{
    foreach ($manifest['applicants'] ?? [] as $applicant) {
        if (($applicant['slug'] ?? null) === $slug) {
            return $applicant;
        }
    }

    throw new RuntimeException("Applicant [{$slug}] was not present in the inspected manifest.");
}

function externalProcurementImporterCaptureFailure(callable $callback): ?Throwable
{
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception;
    }

    return null;
}

it('inspects one applicant folder into a deterministic PDF manifest', function () {
    $source = externalProcurementImporterFixtureDirectory();
    $relativePath = 'Expression of Interest.pdf';
    $absolutePath = $source.DIRECTORY_SEPARATOR.'Acacia Policy Institute'.DIRECTORY_SEPARATOR.$relativePath;

    try {
        externalProcurementImporterWritePdf($absolutePath);

        $manifest = (new ExternalProcurementSubmissionImporter)->inspectSource($source);
        $applicant = externalProcurementImporterEntryBySlug($manifest, 'acacia-policy-institute');
        $file = $applicant['files'][0] ?? [];

        expect($manifest)
            ->toMatchArray([
                'source_path' => realpath($source),
                'applicant_count' => 1,
                'file_count' => 1,
            ])
            ->and($applicant)->toMatchArray([
                'name' => 'Acacia Policy Institute',
                'slug' => 'acacia-policy-institute',
                'email' => 'acacia-policy-institute@africathinktank.africa',
                'is_archive' => false,
            ])
            ->and($applicant['manifest_hash'])->toMatch('/\A[a-f0-9]{64}\z/')
            ->and(str_replace('\\', '/', $file['relative_path'] ?? ''))->toBe($relativePath)
            ->and(realpath($file['absolute_path'] ?? ''))->toBe(realpath($absolutePath))
            ->and($file['size'] ?? null)->toBe(filesize($absolutePath))
            ->and($file['sha256'] ?? null)->toBe(hash_file('sha256', $absolutePath));
    } finally {
        externalProcurementImporterRemoveFixtureDirectory($source);
    }
});

it('keeps multiple applicants and PDFs distinct and stable across inspections', function () {
    $source = externalProcurementImporterFixtureDirectory();

    try {
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Beacon Analytics'.DIRECTORY_SEPARATOR.'Technical Proposal.pdf'
        );
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Beacon Analytics'.DIRECTORY_SEPARATOR.'Financial Proposal.pdf'
        );
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Civic Futures'.DIRECTORY_SEPARATOR.'Expression of Interest.pdf'
        );

        $importer = new ExternalProcurementSubmissionImporter;
        $firstManifest = $importer->inspectSource($source);
        $secondManifest = $importer->inspectSource($source);
        $beacon = externalProcurementImporterEntryBySlug($firstManifest, 'beacon-analytics');
        $civic = externalProcurementImporterEntryBySlug($firstManifest, 'civic-futures');
        $emails = array_column($firstManifest['applicants'], 'email');

        expect($firstManifest)
            ->toMatchArray([
                'applicant_count' => 2,
                'file_count' => 3,
            ])
            ->and($secondManifest)->toBe($firstManifest)
            ->and($beacon['email'])->toBe('beacon-analytics@africathinktank.africa')
            ->and($beacon['files'])->toHaveCount(2)
            ->and($civic['email'])->toBe('civic-futures@africathinktank.africa')
            ->and($civic['files'])->toHaveCount(1)
            ->and(array_unique($emails))->toHaveCount(2);
    } finally {
        externalProcurementImporterRemoveFixtureDirectory($source);
    }
});

it('uses the deterministic complete ZIP hash as a multi-file package identity', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('PHP ZIP support is required to verify package identity.');
    }

    $source = externalProcurementImporterFixtureDirectory();

    try {
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Archive Applicant'.DIRECTORY_SEPARATOR.'Technical Proposal.pdf'
        );
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Archive Applicant'.DIRECTORY_SEPARATOR.'Financial Proposal.pdf'
        );

        $importer = new ExternalProcurementSubmissionImporter;
        $manifest = $importer->inspectSource($source);
        $applicant = externalProcurementImporterEntryBySlug($manifest, 'archive-applicant');
        $procurement = new Procurement;
        $procurement->id = 'procurement-fixture';
        $attachmentPlans = new ReflectionMethod($importer, 'attachmentPlans');
        $firstPlan = $attachmentPlans->invoke($importer, [$applicant], $procurement)[0];
        $secondPlan = $attachmentPlans->invoke($importer, [$applicant], $procurement)[0];
        $sourceHashes = array_column($applicant['files'], 'sha256');

        expect($firstPlan['package_sha256'])
            ->toMatch('/\A[a-f0-9]{64}\z/')
            ->not->toBe($applicant['manifest_hash'])
            ->not->toBeIn($sourceHashes)
            ->and($firstPlan['storage_path'])->toBe(
                'procurement_submissions/external-imports/procurement-fixture/'
                .$firstPlan['package_sha256'].'.zip'
            )
            ->and($secondPlan)->toBe($firstPlan);
    } finally {
        externalProcurementImporterRemoveFixtureDirectory($source);
    }
});

it('revalidates every multi-PDF source checksum before packaging', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('PHP ZIP support is required to verify package revalidation.');
    }

    $source = externalProcurementImporterFixtureDirectory();

    try {
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Mutable Applicant'.DIRECTORY_SEPARATOR.'Technical Proposal.pdf'
        );
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Mutable Applicant'.DIRECTORY_SEPARATOR.'Financial Proposal.pdf'
        );

        $importer = new ExternalProcurementSubmissionImporter;
        $manifest = $importer->inspectSource($source);
        $applicant = externalProcurementImporterEntryBySlug($manifest, 'mutable-applicant');
        $mutatedPath = $applicant['files'][0]['absolute_path'];
        $originalContents = file_get_contents($mutatedPath);
        file_put_contents($mutatedPath, str_replace('Test submission', 'Best submission', $originalContents));

        $procurement = new Procurement;
        $procurement->id = 'procurement-fixture';
        $attachmentPlans = new ReflectionMethod($importer, 'attachmentPlans');
        $exception = externalProcurementImporterCaptureFailure(
            fn () => $attachmentPlans->invoke($importer, [$applicant], $procurement)
        );

        expect(strtolower($exception?->getMessage() ?? ''))
            ->toContain('checksum changed before packaging')
            ->and($exception?->getMessage() ?? '')->toContain($applicant['files'][0]['relative_path']);
    } finally {
        externalProcurementImporterRemoveFixtureDirectory($source);
    }
});

it('matches prior audit metadata exactly while ignoring associative key order', function () {
    $importer = new ExternalProcurementSubmissionImporter;
    $matchesExactly = new ReflectionMethod($importer, 'metadataMatchesExactly');
    $expected = [
        'import_version' => 1,
        'source' => [
            'folder' => 'Applicant',
            'files' => [
                ['path' => 'First.pdf', 'sha256' => 'first'],
                ['path' => 'Second.pdf', 'sha256' => 'second'],
            ],
        ],
    ];
    $sameWithReorderedKeys = [
        'source' => [
            'files' => [
                ['sha256' => 'first', 'path' => 'First.pdf'],
                ['sha256' => 'second', 'path' => 'Second.pdf'],
            ],
            'folder' => 'Applicant',
        ],
        'import_version' => 1,
    ];
    $withUnexpectedKey = [...$sameWithReorderedKeys, 'unexpected' => true];
    $withReorderedFileList = $sameWithReorderedKeys;
    $withReorderedFileList['source']['files'] = array_reverse(
        $withReorderedFileList['source']['files']
    );

    expect($matchesExactly->invoke($importer, $expected, $sameWithReorderedKeys))->toBeTrue()
        ->and($matchesExactly->invoke($importer, $expected, $withUnexpectedKey))->toBeFalse()
        ->and($matchesExactly->invoke($importer, $expected, $withReorderedFileList))->toBeFalse();
});

it('rejects a one-byte file posing as a PDF with a useful diagnostic', function () {
    $source = externalProcurementImporterFixtureDirectory();
    $invalidPath = $source.DIRECTORY_SEPARATOR.'Broken Applicant'.DIRECTORY_SEPARATOR.'Broken.pdf';

    try {
        mkdir(dirname($invalidPath), 0700, true);
        file_put_contents($invalidPath, 'x');

        $exception = externalProcurementImporterCaptureFailure(
            fn () => (new ExternalProcurementSubmissionImporter)->inspectSource($source)
        );
        $message = strtolower($exception?->getMessage() ?? '');

        expect($exception)->toBeInstanceOf(Throwable::class)
            ->and($message)->toContain('broken.pdf')
            ->and($message)->toContain('pdf');
    } finally {
        externalProcurementImporterRemoveFixtureDirectory($source);
    }
});

it('requires the PDF signature at the start and rejects nested source directories', function () {
    $prefixedSource = externalProcurementImporterFixtureDirectory();
    $nestedSource = externalProcurementImporterFixtureDirectory();

    try {
        $prefixedPath = $prefixedSource.DIRECTORY_SEPARATOR.'Prefixed Applicant'.DIRECTORY_SEPARATOR.'Prefixed.pdf';
        externalProcurementImporterWritePdf($prefixedPath);
        file_put_contents($prefixedPath, "junk\n".file_get_contents($prefixedPath));

        $prefixedFailure = externalProcurementImporterCaptureFailure(
            fn () => (new ExternalProcurementSubmissionImporter)->inspectSource($prefixedSource)
        );

        externalProcurementImporterWritePdf(
            $nestedSource.DIRECTORY_SEPARATOR.'Nested Applicant'.DIRECTORY_SEPARATOR.'supporting'.DIRECTORY_SEPARATOR.'EOI.pdf'
        );
        $nestedFailure = externalProcurementImporterCaptureFailure(
            fn () => (new ExternalProcurementSubmissionImporter)->inspectSource($nestedSource)
        );

        expect(strtolower($prefixedFailure?->getMessage() ?? ''))
            ->toContain('not a valid pdf')
            ->and(strtolower($nestedFailure?->getMessage() ?? ''))
            ->toContain('nested directories');
    } finally {
        externalProcurementImporterRemoveFixtureDirectory($prefixedSource);
        externalProcurementImporterRemoveFixtureDirectory($nestedSource);
    }
});

it('rejects applicant folders that derive the same email slug', function () {
    $source = externalProcurementImporterFixtureDirectory();

    try {
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Alpha Group'.DIRECTORY_SEPARATOR.'EOI.pdf'
        );
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Alpha-Group'.DIRECTORY_SEPARATOR.'Proposal.pdf'
        );

        $exception = externalProcurementImporterCaptureFailure(
            fn () => (new ExternalProcurementSubmissionImporter)->inspectSource($source)
        );
        $message = strtolower($exception?->getMessage() ?? '');

        expect($exception)->toBeInstanceOf(Throwable::class)
            ->and($message)->toContain('alpha-group')
            ->and($message)->toMatch('/same|collision|conflict|duplicate|unique/');
    } finally {
        externalProcurementImporterRemoveFixtureDirectory($source);
    }
});

it('keeps the external import explicit, atomic, idempotent, archived, and private', function () {
    $root = dirname(__DIR__, 2);
    $service = file_get_contents($root.'/app/Services/ExternalProcurementSubmissionImporter.php');
    $seeder = file_get_contents($root.'/database/seeders/ExternalProcurementSubmissionsSeeder.php');
    $databaseSeeder = file_get_contents($root.'/database/seeders/DatabaseSeeder.php');
    $inspectSource = new ReflectionMethod(ExternalProcurementSubmissionImporter::class, 'inspectSource');
    $import = new ReflectionMethod(ExternalProcurementSubmissionImporter::class, 'import');

    expect(ExternalProcurementSubmissionImporter::DEFAULT_EMAIL_DOMAIN)
        ->toBe('africathinktank.africa')
        ->and($inspectSource->getNumberOfParameters())->toBe(1)
        ->and($import->getNumberOfParameters())->toBe(3)
        ->and($import->getParameters()[2]->getName())->toBe('dryRun')
        ->and($service)
        ->toMatch('/function\s+import\s*\([^)]*\$dryRun/s')
        ->toContain('$emailDomain = self::DEFAULT_EMAIL_DOMAIN;')
        ->toContain('if (is_link($sourceDirectory))')
        ->toContain('DB::transaction')
        ->toContain('lockForUpdate')
        ->toContain("'reused_submissions'")
        ->toContain("'batch_manifest_sha256'")
        ->toContain('sha256')
        ->toContain('ZipArchive')
        ->toContain("str_starts_with(\$header, '%PDF-')")
        ->toContain('$packageSha256 = $this->expectedPackageHash($applicant)')
        ->toContain("hash_file('sha256', \$temporaryPath)")
        ->toContain('$filename = $packageSha256.')
        ->toContain("fopen(\$targetPath, 'xb')")
        ->toContain('assertStoredPackage($plan, true)')
        ->toContain('if ($audit->user_id !== null)')
        ->toContain('metadataMatchesExactly($expectedMetadata, (array) $audit->metadata)')
        ->toContain('$targetImportAudits = ProcurementAuditLog::query()')
        ->toContain('$verifiedAuditIds->sort()->values()->all()')
        ->toMatch('/\$targetImportAudits\s*=\s*ProcurementAuditLog::query\(\)\s*->where\(\'procurement_id\', \$procurement->id\)\s*->where\(\'action\', self::AUDIT_ACTION\)\s*->get\(\);/s')
        ->toContain('hash_equals($expectedHash, (string) $storedHash)')
        ->toMatch('/Storage::disk\((?:[\'\"]local[\'\"]|\$this->[^)]+)\)/')
        ->and(substr_count($service, '$this->assertSourceFilesUnchanged($plan);'))->toBe(2)
        ->and(substr_count($service, '$audit->user_id !== null'))->toBe(2)
        ->and($seeder)
        ->toContain('ExternalProcurementSubmissionImporter')
        ->toContain('EXTERNAL_PROCUREMENT_IMPORT_DRY_RUN')
        ->toContain("\$dryRunSetting === false || trim(\$dryRunSetting) === ''")
        ->toContain('$dryRun = true;')
        ->toContain('FILTER_NULL_ON_FAILURE')
        ->toContain('$dryRun')
        ->toContain('->unique(fn (string $candidate): string => realpath($candidate) ?: $candidate)')
        ->not->toContain('->map(fn (string $candidate): string => realpath(')
        ->not->toContain('DEFAULT_EMAIL_DOMAIN')
        ->not->toContain('EXTERNAL_PROCUREMENT_APPLICANT_DOMAIN')
        ->and($databaseSeeder)
        ->not->toContain('ExternalProcurementSubmissionsSeeder::class');
});
