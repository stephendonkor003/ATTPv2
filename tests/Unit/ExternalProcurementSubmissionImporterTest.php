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

function externalProcurementImporterRemoveTemporaryArchive(string $path): void
{
    $temporaryRoot = realpath(sys_get_temp_dir());
    $archivePath = realpath($path);

    if ($archivePath === false) {
        return;
    }

    $archiveName = basename($archivePath);
    $hasExpectedName = str_starts_with($archiveName, 'attp-procurement-')
        || (PHP_OS_FAMILY === 'Windows' && preg_match('/\Aatt[a-z0-9]+\.tmp\z/i', $archiveName) === 1);

    if (
        $temporaryRoot === false
        || dirname($archivePath) !== $temporaryRoot
        || ! $hasExpectedName
    ) {
        throw new LogicException("Refusing to remove unexpected temporary archive [{$path}].");
    }

    unlink($archivePath);
}

function externalProcurementImporterMethodSource(string $source, string $method): string
{
    $pattern = '/\b(?:public|protected|private) function\s+'.preg_quote($method, '/').'\s*\(/';
    if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        throw new RuntimeException("Importer method [{$method}] was not found.");
    }

    $start = $match[0][1];
    $nextPattern = '/\n    (?:public|protected|private) function\s+\w+\s*\(/';
    $next = preg_match($nextPattern, $source, $nextMatch, PREG_OFFSET_CAPTURE, $start + 1) === 1
        ? $nextMatch[0][1]
        : strlen($source);

    return substr($source, $start, $next - $start);
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

it('packages every source PDF once with an exact deterministic ZIP manifest', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('PHP ZIP support is required to inspect package contents.');
    }

    $source = externalProcurementImporterFixtureDirectory();
    $archivePath = null;
    $zip = new ZipArchive;
    $zipIsOpen = false;

    try {
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Complete Applicant'.DIRECTORY_SEPARATOR.'Technical Proposal.pdf'
        );
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Complete Applicant'.DIRECTORY_SEPARATOR.'Financial Proposal.pdf'
        );

        $importer = new ExternalProcurementSubmissionImporter;
        $manifest = $importer->inspectSource($source);
        $applicant = externalProcurementImporterEntryBySlug($manifest, 'complete-applicant');
        $buildArchive = new ReflectionMethod($importer, 'buildArchive');
        $archivePath = $buildArchive->invoke($importer, $applicant);
        $zipIsOpen = $zip->open($archivePath, ZipArchive::RDONLY) === true;

        expect($zipIsOpen)->toBeTrue();

        $entryNames = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryNames[] = $zip->getNameIndex($index);
        }

        $sourceFilesByPath = [];
        foreach ($applicant['files'] as $file) {
            $sourceFilesByPath[$file['relative_path']] = $file;
        }

        $packageManifest = json_decode(
            $zip->getFromName('_external-import-manifest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        expect($entryNames)->toBe([
            'Financial Proposal.pdf',
            'Technical Proposal.pdf',
            '_external-import-manifest.json',
        ])
            ->and($zip->numFiles)->toBe(count($applicant['files']) + 1)
            ->and(hash('sha256', $zip->getFromName('Financial Proposal.pdf')))
            ->toBe($sourceFilesByPath['Financial Proposal.pdf']['sha256'])
            ->and(hash('sha256', $zip->getFromName('Technical Proposal.pdf')))
            ->toBe($sourceFilesByPath['Technical Proposal.pdf']['sha256'])
            ->and($packageManifest)->toMatchArray([
                'applicant' => 'Complete Applicant',
                'email' => 'complete-applicant@africathinktank.africa',
                'manifest_sha256' => $applicant['manifest_hash'],
            ])
            ->and(array_column($packageManifest['files'], 'path'))->toBe([
                'Financial Proposal.pdf',
                'Technical Proposal.pdf',
            ]);
    } finally {
        if ($zipIsOpen) {
            $zip->close();
        }
        if (is_string($archivePath)) {
            externalProcurementImporterRemoveTemporaryArchive($archivePath);
        }
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

it('uses stable submission identity and explicit ownership metadata across reruns', function () {
    $source = externalProcurementImporterFixtureDirectory();

    try {
        externalProcurementImporterWritePdf(
            $source.DIRECTORY_SEPARATOR.'Stable Applicant'.DIRECTORY_SEPARATOR.'Expression of Interest.pdf'
        );

        $importer = new ExternalProcurementSubmissionImporter;
        $manifest = $importer->inspectSource($source);
        $applicant = externalProcurementImporterEntryBySlug($manifest, 'stable-applicant');
        $procurement = new Procurement;
        $procurement->id = 'procurement-fixture';
        $procurement->reference_no = 'REF-001';
        $attachmentPlans = new ReflectionMethod($importer, 'attachmentPlans');
        $plan = $attachmentPlans->invoke($importer, [$applicant], $procurement)[0];
        $submissionCode = new ReflectionMethod($importer, 'submissionCode');
        $auditMetadata = new ReflectionMethod($importer, 'auditMetadata');
        $metadata = $auditMetadata->invoke(
            $importer,
            $procurement,
            $plan,
            $manifest['manifest_hash']
        );
        $expectedCode = 'PROC-EXT-'.strtoupper(substr(hash(
            'sha256',
            'procurement-fixture|stable-applicant@africathinktank.africa'
        ), 0, 12));

        expect(ExternalProcurementSubmissionImporter::OWNERSHIP_MARKER)
            ->toBe('attp.external-procurement-submission-importer')
            ->and($plan['submission_code'])->toBe($expectedCode)
            ->and($submissionCode->invoke(
                $importer,
                $procurement,
                '  STABLE-APPLICANT@AFRICATHINKTANK.AFRICA  '
            ))->toBe($expectedCode)
            ->and($metadata)->toMatchArray([
                'import_version' => 2,
                'managed_by' => ExternalProcurementSubmissionImporter::OWNERSHIP_MARKER,
                'batch_manifest_sha256' => $manifest['manifest_hash'],
                'applicant_manifest_sha256' => $applicant['manifest_hash'],
                'placeholder_email' => 'stable-applicant@africathinktank.africa',
                'storage_path' => $plan['storage_path'],
                'package_sha256' => $plan['package_sha256'],
                'packaged_as_zip' => false,
            ])
            ->and($metadata['source_files'])->toHaveCount(1);
    } finally {
        externalProcurementImporterRemoveFixtureDirectory($source);
    }
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

it('reconciles only proven importer-owned records and keeps identical or unrelated rows intact', function () {
    $service = file_get_contents(
        dirname(__DIR__, 2).'/app/Services/ExternalProcurementSubmissionImporter.php'
    );
    $preflight = externalProcurementImporterMethodSource($service, 'preflightDatabaseState');
    $validateManaged = externalProcurementImporterMethodSource($service, 'validateManagedImport');
    $replaceable = externalProcurementImporterMethodSource($service, 'assertManagedSubmissionReplaceable');
    $persist = externalProcurementImporterMethodSource($service, 'persist');

    expect($service)
        ->toContain("public const OWNERSHIP_MARKER = 'attp.external-procurement-submission-importer';")
        ->toContain('private const IMPORT_VERSION = 2;')
        ->toContain('private const LEGACY_IMPORT_VERSION = 1;')
        ->toContain("'managed_by' => self::OWNERSHIP_MARKER")
        ->and($validateManaged)
        ->toContain("\$versionTwoKeys = [...\$legacyKeys, 'managed_by'];")
        ->toContain('! in_array($version, [self::LEGACY_IMPORT_VERSION, self::IMPORT_VERSION], true)')
        ->toContain("(\$metadata['managed_by'] ?? null) !== self::OWNERSHIP_MARKER")
        ->toContain("throw new RuntimeException('External-import audit has invalid ownership metadata.')")
        ->not->toContain('assertManagedSubmissionReplaceable(')
        ->and($preflight)
        ->toContain("->where('action', self::AUDIT_ACTION)")
        ->toContain('$managed = $this->validateManagedImport(')
        ->toContain('$stale = array_filter(')
        ->toContain('$owned,')
        ->toContain('fn (string $email): bool => ! isset($seenPlanEmails[$email])')
        ->toContain("'unrelated_count' => \$targetSubmissions->count() - count(\$owned)")
        ->toContain("throw new RuntimeException(\"Existing account is not owned by this import: {\$plan['email']}\")")
        ->toContain('Submission code conflicts with a non-imported row:')
        ->toContain('if ($unchanged)')
        ->toContain("&& \$managed['values_match']")
        ->toContain('$this->assertStoredPackage($plan, true);')
        ->toContain('$unchangedCount++')
        ->and(substr_count($preflight, 'assertManagedSubmissionReplaceable('))->toBe(2)
        ->and($replaceable)
        ->toContain('Imported submission has workflow state and cannot be replaced:')
        ->toContain('Imported submission has additional audit activity:')
        ->toContain('Imported submission has downstream workflow data in')
        ->toContain("['evaluation_submissions', 'form_submission_id']")
        ->toContain("['evaluation_criteria_scores', 'submission_id']")
        ->toContain("['evaluation_section_scores', 'submission_id']")
        ->toContain("['site_visits', 'form_submission_id']")
        ->toContain("['procurement_submission_screenings', 'submission_id']")
        ->toContain("'awarded_submission_id'")
        ->and($persist)
        ->toContain("\$managed = \$preflight['owned'][\$email] ?? null")
        ->toContain("\$submission = \$managed['submission'];")
        ->toContain("|| ! \$managed['values_match']")
        ->toContain("\$counts['reused_submissions']++")
        ->toContain('if (! $managed || $isChanged)')
        ->toContain("->where('submission_id', \$submission->id)")
        ->toContain('$audit->metadata = $expectedMetadata')
        ->toContain("foreach (\$preflight['stale'] as \$managed)")
        ->toContain('$audit->delete()')
        ->toContain('$submission->delete()')
        ->not->toContain('$user->delete()')
        ->and(substr_count($persist, '$submission->delete()'))->toBe(1);
});

it('preflights before writes and removes obsolete managed packages only after commit', function () {
    $service = file_get_contents(
        dirname(__DIR__, 2).'/app/Services/ExternalProcurementSubmissionImporter.php'
    );
    $import = externalProcurementImporterMethodSource($service, 'import');
    $persist = externalProcurementImporterMethodSource($service, 'persist');
    $cleanup = externalProcurementImporterMethodSource($service, 'deleteObsoletePackages');
    $firstPreflight = strpos($import, '$preflight = $this->preflightDatabaseState(');
    $dryRunReturn = strpos($import, 'if ($dryRun)');
    $lockedPreflight = strrpos($import, '$preflight = $this->preflightDatabaseState(');
    $preparePackages = strpos($import, '$plan[\'package_sha256\'] = $this->prepareAttachment(');
    $persistDatabase = strpos($import, '$counts = $this->persist(');
    $markCommitted = strpos($import, '$databaseCommitted = true;');
    $deleteObsolete = strpos($import, '$cleanup = $this->deleteObsoletePackages(');

    expect($import)
        ->toContain("'planned_replacements' => \$preflight['changed_count']")
        ->toContain("'planned_unchanged' => \$preflight['unchanged_count']")
        ->toContain("'planned_removals' => \$preflight['stale_count']")
        ->toContain("'preserved_unrelated_submissions' => \$preflight['unrelated_count']")
        ->toContain('$databaseCommitted = false;')
        ->toContain('if (! $databaseCommitted)')
        ->toContain('foreach (array_reverse($createdPaths) as $createdPath)')
        ->not->toContain("\$disk->delete(\$preflight['obsolete_paths'])")
        ->and(substr_count($import, '$this->preflightDatabaseState('))->toBe(2)
        ->and($firstPreflight)->toBeLessThan($dryRunReturn)
        ->and($dryRunReturn)->toBeLessThan($lockedPreflight)
        ->and($lockedPreflight)->toBeLessThan($preparePackages)
        ->and($preparePackages)->toBeLessThan($persistDatabase)
        ->and($persistDatabase)->toBeLessThan($markCommitted)
        ->and($markCommitted)->toBeLessThan($deleteObsolete)
        ->and($persist)
        ->toContain('DB::transaction(function () use (')
        ->toContain('$this->preflightDatabaseState(')
        ->toContain('$batchManifestHash,')
        ->toContain('true')
        ->and($cleanup)
        ->toContain('$active = array_fill_keys($activePaths, true)')
        ->toContain("preg_quote((string) \$procurement->id, '~')")
        ->toContain('isset($active[$path]) || $this->storedPathIsReferenced($path)')
        ->toContain('hash_equals($matches[1], $hash)')
        ->toContain('$disk->delete($path)');
});

it('keeps the external import explicit, atomic, idempotent, archived, and private', function () {
    $root = dirname(__DIR__, 2);
    $service = file_get_contents($root.'/app/Services/ExternalProcurementSubmissionImporter.php');
    $seeder = file_get_contents($root.'/database/seeders/ExternalProcurementSubmissionsSeeder.php');
    $databaseSeeder = file_get_contents($root.'/database/seeders/DatabaseSeeder.php');
    $gitignore = file_get_contents($root.'/.gitignore');
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
        ->toContain("metadataMatchesExactly(\$expectedMetadata, (array) \$managed['audit']->metadata)")
        ->toContain('assertStoredManagedPackage($metadata)')
        ->toContain('hash_equals($expectedHash, (string) $storedHash)')
        ->toMatch('/Storage::disk\((?:[\'\"]local[\'\"]|\$this->[^)]+)\)/')
        ->and(substr_count($service, '$this->assertSourceFilesUnchanged($plan);'))->toBe(2)
        ->and(substr_count($service, '$audit->user_id !== null'))->toBe(1)
        ->and(substr_count(
            $service,
            'if (is_link($absolutePath) || ! is_file($absolutePath) || ! is_readable($absolutePath))'
        ))->toBe(2)
        ->and($seeder)
        ->toContain('ExternalProcurementSubmissionImporter')
        ->toContain('EXTERNAL_PROCUREMENT_IMPORT_DRY_RUN')
        ->toContain("\$dryRunSetting === false || trim(\$dryRunSetting) === ''")
        ->toContain('$dryRun = true;')
        ->toContain('FILTER_NULL_ON_FAILURE')
        ->toContain('$dryRun')
        ->toContain("return resource_path('Submissions');")
        ->not->toContain('storage_path(')
        ->not->toContain('realpath(')
        ->not->toContain('DEFAULT_EMAIL_DOMAIN')
        ->not->toContain('EXTERNAL_PROCUREMENT_APPLICANT_DOMAIN')
        ->and($databaseSeeder)
        ->not->toContain('ExternalProcurementSubmissionsSeeder::class')
        ->and($gitignore)
        ->not->toContain('!/storage/app/private/Submissions');
});
