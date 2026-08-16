<?php

namespace App\Services;

use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\Procurement;
use App\Models\ProcurementAuditLog;
use App\Models\User;
use App\Support\DiscussionAccountEmailPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ZipArchive;

final class ExternalProcurementSubmissionImporter
{
    public const DEFAULT_EMAIL_DOMAIN = 'africathinktank.africa';

    public const AUDIT_ACTION = 'external_procurement_submission_imported';

    public const OWNERSHIP_MARKER = 'attp.external-procurement-submission-importer';

    private const IMPORT_VERSION = 2;

    private const LEGACY_IMPORT_VERSION = 1;

    private const DISABLED_REASON = 'Historical external procurement submission imported with a placeholder email.';

    private const MAX_APPLICANTS = 500;

    private const MAX_FILES = 5000;

    private const MAX_FILE_BYTES = 20 * 1024 * 1024;

    private const MAX_APPLICANT_BYTES = 200 * 1024 * 1024;

    private const MAX_BATCH_BYTES = 2 * 1024 * 1024 * 1024;

    /**
     * Inspect and validate the complete source tree without writing anything.
     *
     * @return array{
     *   source_path:string,
     *   email_domain:string,
     *   applicant_count:int,
     *   file_count:int,
     *   total_bytes:int,
     *   manifest_hash:string,
     *   applicants:array<int,array<string,mixed>>
     * }
     */
    public function inspectSource(string $sourceDirectory): array
    {
        if (is_link($sourceDirectory)) {
            throw new RuntimeException("Submission source directory cannot be a symbolic link: {$sourceDirectory}");
        }

        $sourcePath = realpath($sourceDirectory);
        if ($sourcePath === false || ! is_dir($sourcePath)) {
            throw new RuntimeException("Submission source directory was not found: {$sourceDirectory}");
        }

        $emailDomain = self::DEFAULT_EMAIL_DOMAIN;

        $entries = iterator_to_array(new \FilesystemIterator(
            $sourcePath,
            \FilesystemIterator::SKIP_DOTS
        ));

        foreach ($entries as $entry) {
            if ($entry->isLink()) {
                throw new RuntimeException("Symbolic links are not allowed in the submission source: {$entry->getFilename()}");
            }

            if (! $entry->isDir()) {
                throw new RuntimeException("Only applicant folders are allowed at the source root: {$entry->getFilename()}");
            }
        }

        usort(
            $entries,
            fn (SplFileInfo $left, SplFileInfo $right): int => strnatcasecmp(
                $left->getFilename(),
                $right->getFilename()
            )
        );

        if ($entries === []) {
            throw new RuntimeException('No applicant folders were found in the submission source.');
        }

        if (count($entries) > self::MAX_APPLICANTS) {
            throw new RuntimeException('The submission source exceeds the maximum applicant-folder limit.');
        }

        $applicants = [];
        $emails = [];
        $fileCount = 0;
        $totalBytes = 0;

        foreach ($entries as $directory) {
            $applicant = $this->inspectApplicantDirectory(
                $directory->getPathname(),
                $directory->getFilename(),
                $emailDomain
            );

            $emailKey = Str::lower($applicant['email']);
            if (isset($emails[$emailKey])) {
                throw new RuntimeException(sprintf(
                    'Applicant folders "%s" and "%s" resolve to the same placeholder email (%s).',
                    $emails[$emailKey],
                    $applicant['name'],
                    $applicant['email']
                ));
            }

            $emails[$emailKey] = $applicant['name'];
            $fileCount += count($applicant['files']);
            $totalBytes += $applicant['total_bytes'];
            $applicants[] = $applicant;
        }

        if ($fileCount > self::MAX_FILES) {
            throw new RuntimeException('The submission source exceeds the maximum file-count limit.');
        }

        if ($totalBytes > self::MAX_BATCH_BYTES) {
            throw new RuntimeException('The submission source exceeds the maximum total-size limit.');
        }

        $batchManifest = collect($applicants)
            ->map(fn (array $applicant): string => implode('|', [
                $applicant['name'],
                $applicant['email'],
                $applicant['manifest_hash'],
            ]))
            ->implode("\n");

        return [
            'source_path' => $sourcePath,
            'email_domain' => $emailDomain,
            'applicant_count' => count($applicants),
            'file_count' => $fileCount,
            'total_bytes' => $totalBytes,
            'manifest_hash' => hash('sha256', $batchManifest),
            'applicants' => $applicants,
        ];
    }

    /**
     * Import a validated source tree into the exact procurement and its active form.
     *
     * @return array<string,mixed>
     */
    public function import(
        string $procurementTitle,
        string $sourceDirectory,
        bool $dryRun = false
    ): array {
        $manifest = $this->inspectSource($sourceDirectory);
        [$procurement, $form] = $this->resolveTarget($procurementTitle);
        $plans = $this->attachmentPlans($manifest['applicants'], $procurement);

        $preflight = $this->preflightDatabaseState(
            $procurement,
            $form,
            $plans,
            $manifest['manifest_hash']
        );

        $baseSummary = [
            'status' => $dryRun ? 'dry-run' : 'completed',
            'procurement_id' => (string) $procurement->id,
            'procurement_reference' => (string) $procurement->reference_no,
            'procurement_title' => (string) $procurement->title,
            'form_id' => (string) $form->id,
            'source_path' => $manifest['source_path'],
            'manifest_hash' => $manifest['manifest_hash'],
            'applicant_count' => $manifest['applicant_count'],
            'source_file_count' => $manifest['file_count'],
            'source_bytes' => $manifest['total_bytes'],
            'single_file_packages' => collect($plans)->where('is_archive', false)->count(),
            'archive_packages' => collect($plans)->where('is_archive', true)->count(),
            'planned_new_applicants' => $preflight['new_count'],
            'planned_replacements' => $preflight['changed_count'],
            'planned_unchanged' => $preflight['unchanged_count'],
            'planned_removals' => $preflight['stale_count'],
            'preserved_unrelated_submissions' => $preflight['unrelated_count'],
        ];

        if ($dryRun) {
            return $baseSummary;
        }

        $lock = Cache::lock("external-procurement-submissions:{$procurement->id}", 600);
        if (! $lock->get()) {
            throw new RuntimeException('Another external submission import is already running for this procurement.');
        }

        $createdPaths = [];
        $databaseCommitted = false;

        try {
            $preflight = $this->preflightDatabaseState(
                $procurement,
                $form,
                $plans,
                $manifest['manifest_hash']
            );

            foreach ($plans as &$plan) {
                $plan['package_sha256'] = $this->prepareAttachment($plan, $createdPaths);
            }
            unset($plan);

            $committedObsoletePaths = [];
            $counts = $this->persist(
                $procurement,
                $form,
                $plans,
                $manifest['manifest_hash'],
                $committedObsoletePaths
            );
            $databaseCommitted = true;

            $cleanup = $this->deleteObsoletePackages(
                $procurement,
                $committedObsoletePaths,
                collect($plans)->pluck('storage_path')->all()
            );

            return [...$baseSummary, ...$counts, ...$cleanup];
        } catch (Throwable $exception) {
            if (! $databaseCommitted) {
                $disk = Storage::disk('local');
                foreach (array_reverse($createdPaths) as $createdPath) {
                    $disk->delete($createdPath);
                }
            }

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /** @return array<string,mixed> */
    private function inspectApplicantDirectory(
        string $directory,
        string $folderName,
        string $emailDomain
    ): array {
        $name = trim($folderName);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new RuntimeException("Invalid applicant folder name: {$folderName}");
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            throw new RuntimeException("Applicant folder name cannot form an email address: {$folderName}");
        }

        if (strlen($slug) > 63) {
            throw new RuntimeException("Applicant folder name is too long for a placeholder email: {$folderName}");
        }

        $email = Str::lower("{$slug}@{$emailDomain}");
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new RuntimeException("Applicant folder name creates an invalid placeholder email: {$folderName}");
        }

        $root = realpath($directory);
        if ($root === false) {
            throw new RuntimeException("Applicant folder cannot be read: {$folderName}");
        }

        foreach (new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS) as $child) {
            if ($child->isLink()) {
                throw new RuntimeException("Symbolic links are not allowed in applicant folder: {$folderName}");
            }

            if ($child->isDir()) {
                throw new RuntimeException("Nested directories are not allowed in applicant folder: {$folderName}");
            }
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $root,
            RecursiveDirectoryIterator::SKIP_DOTS
        ));

        $files = [];
        $relativePaths = [];
        $totalBytes = 0;

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException("Symbolic links are not allowed in applicant folder: {$folderName}");
            }

            if (! $file->isFile()) {
                continue;
            }

            $absolutePath = $file->getRealPath();
            if ($absolutePath === false || ! is_readable($absolutePath)) {
                throw new RuntimeException("Unreadable source file in applicant folder: {$folderName}");
            }

            $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            if (! str_starts_with($absolutePath, $rootPrefix)) {
                throw new RuntimeException("Source file escapes its applicant folder: {$file->getFilename()}");
            }

            $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($rootPrefix)));
            $relativeKey = Str::lower($relativePath);
            if (isset($relativePaths[$relativeKey])) {
                throw new RuntimeException("Duplicate case-insensitive source path in {$folderName}: {$relativePath}");
            }
            $relativePaths[$relativeKey] = true;

            if (Str::lower($file->getExtension()) !== 'pdf') {
                throw new RuntimeException("Only PDF source documents are accepted: {$folderName}/{$relativePath}");
            }

            $size = $file->getSize();
            if ($size <= 0) {
                throw new RuntimeException("Source PDF is empty: {$folderName}/{$relativePath}");
            }
            if ($size > self::MAX_FILE_BYTES) {
                throw new RuntimeException("Source PDF exceeds 20 MB: {$folderName}/{$relativePath}");
            }

            $handle = fopen($absolutePath, 'rb');
            $header = $handle !== false ? fread($handle, 1024) : false;
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (! is_string($header) || ! str_starts_with($header, '%PDF-')) {
                throw new RuntimeException("Source file is not a valid PDF: {$folderName}/{$relativePath}");
            }

            $sha256 = hash_file('sha256', $absolutePath);
            if ($sha256 === false) {
                throw new RuntimeException("Source PDF could not be checksummed: {$folderName}/{$relativePath}");
            }

            $totalBytes += $size;
            $files[] = [
                'relative_path' => $relativePath,
                'absolute_path' => $absolutePath,
                'original_name' => $file->getFilename(),
                'size' => $size,
                'sha256' => $sha256,
            ];
        }

        if ($files === []) {
            throw new RuntimeException("Applicant folder contains no PDF documents: {$folderName}");
        }

        if ($totalBytes > self::MAX_APPLICANT_BYTES) {
            throw new RuntimeException("Applicant folder exceeds 200 MB: {$folderName}");
        }

        usort(
            $files,
            fn (array $left, array $right): int => strnatcasecmp(
                $left['relative_path'],
                $right['relative_path']
            )
        );

        $fileManifest = collect($files)
            ->map(fn (array $file): string => implode('|', [
                $file['relative_path'],
                $file['size'],
                $file['sha256'],
            ]))
            ->implode("\n");

        return [
            'name' => $name,
            'email' => $email,
            'slug' => $slug,
            'files' => $files,
            'file_count' => count($files),
            'total_bytes' => $totalBytes,
            'manifest_hash' => hash('sha256', $fileManifest),
            'is_archive' => count($files) > 1,
        ];
    }

    /** @return array{0:Procurement,1:DynamicForm} */
    private function resolveTarget(string $procurementTitle): array
    {
        $procurements = Procurement::query()
            ->where('title', $procurementTitle)
            ->get();

        if ($procurements->count() !== 1) {
            throw new RuntimeException(sprintf(
                'Expected exactly one procurement with the configured title; found %d.',
                $procurements->count()
            ));
        }

        $procurement = $procurements->first();
        $forms = DynamicForm::query()
            ->where('procurement_id', $procurement->id)
            ->where('status', 'approved')
            ->where('is_active', true)
            ->where('applies_to', 'submission')
            ->with('fields')
            ->get();

        if ($forms->count() !== 1) {
            throw new RuntimeException(sprintf(
                'Expected exactly one active approved submission form for procurement %s; found %d.',
                $procurement->id,
                $forms->count()
            ));
        }

        /** @var DynamicForm $form */
        $form = $forms->first();
        $this->assertFormDefinition($form);

        return [$procurement, $form];
    }

    private function assertFormDefinition(DynamicForm $form): void
    {
        $fieldKeyGroups = $form->fields->groupBy(
            fn ($field): string => Str::lower(trim((string) $field->field_key))
        );
        if ($fieldKeyGroups->contains(fn ($fields): bool => $fields->count() > 1)) {
            throw new RuntimeException('The target application form contains duplicate field keys.');
        }

        $fieldTypes = $form->fields->pluck('field_type', 'field_key');

        foreach ([
            'official_name' => 'text',
            'official_email' => 'email',
            'submit_eoi' => 'file',
        ] as $key => $expectedType) {
            if ($fieldTypes->get($key) !== $expectedType) {
                throw new RuntimeException("Application form field {$key} must exist with type {$expectedType}.");
            }
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function attachmentPlans(array $applicants, Procurement $procurement): array
    {
        return array_map(function (array $applicant) use ($procurement): array {
            $packageSha256 = $this->expectedPackageHash($applicant);
            $directory = implode('/', [
                'procurement_submissions',
                'external-imports',
                (string) $procurement->id,
            ]);

            $filename = $packageSha256.($applicant['is_archive'] ? '.zip' : '.pdf');

            return [
                ...$applicant,
                'storage_path' => "{$directory}/{$filename}",
                'submission_code' => $this->submissionCode($procurement, $applicant['email']),
                'package_sha256' => $packageSha256,
            ];
        }, $applicants);
    }

    private function submissionCode(Procurement $procurement, string $email): string
    {
        return 'PROC-EXT-'.strtoupper(substr(
            hash('sha256', $procurement->id.'|'.Str::lower(trim($email))),
            0,
            12
        ));
    }

    /** @return array<string,mixed> */
    private function preflightDatabaseState(
        Procurement $procurement,
        DynamicForm $form,
        array $plans,
        string $batchManifestHash,
        bool $lockForUpdate = false
    ): array {
        $plannedBatchHash = hash('sha256', collect($plans)
            ->map(fn (array $plan): string => implode('|', [
                $plan['name'],
                $plan['email'],
                $plan['manifest_hash'],
            ]))
            ->implode("\n"));
        if (! hash_equals($plannedBatchHash, $batchManifestHash)) {
            throw new RuntimeException('Validated source plans no longer match the batch manifest.');
        }

        $submissionQuery = FormSubmission::query()
            ->where('procurement_id', $procurement->id);
        if ($lockForUpdate) {
            $submissionQuery->lockForUpdate();
        }
        $targetSubmissions = $submissionQuery->get();
        $targetSubmissionIds = $targetSubmissions->pluck('id')->map(fn ($id): string => (string) $id)->all();

        $auditQuery = ProcurementAuditLog::query()
            ->where('action', self::AUDIT_ACTION)
            ->where(function ($query) use ($procurement, $targetSubmissionIds): void {
                $query->where('procurement_id', $procurement->id);
                if ($targetSubmissionIds !== []) {
                    $query->orWhereIn('submission_id', $targetSubmissionIds);
                }
            });
        if ($lockForUpdate) {
            $auditQuery->lockForUpdate();
        }
        $importAudits = $auditQuery->get();
        $auditsBySubmission = $importAudits->groupBy(fn ($audit): string => (string) $audit->submission_id);

        foreach ($auditsBySubmission as $submissionId => $audits) {
            if ($submissionId === '' || $audits->count() !== 1) {
                throw new RuntimeException('External-import audit provenance is orphaned or duplicated.');
            }
        }

        $targetById = $targetSubmissions->keyBy(fn (FormSubmission $submission): string => (string) $submission->id);
        $owned = [];
        foreach ($importAudits as $audit) {
            /** @var FormSubmission|null $submission */
            $submission = $targetById->get((string) $audit->submission_id);
            if (! $submission) {
                throw new RuntimeException('External-import audit points outside its recorded procurement.');
            }

            $managed = $this->validateManagedImport(
                $procurement,
                $form,
                $submission,
                $audit,
                $lockForUpdate
            );
            $email = $managed['email'];
            if (isset($owned[$email])) {
                throw new RuntimeException("Multiple imported submissions claim placeholder email: {$email}");
            }
            $owned[$email] = $managed;
        }

        if ($owned !== []) {
            $batchRows = array_values(array_map(
                fn (array $managed): array => [
                    'name' => $managed['metadata']['source_folder'],
                    'email' => $managed['email'],
                    'manifest_hash' => $managed['metadata']['applicant_manifest_sha256'],
                ],
                $owned
            ));
            usort($batchRows, fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));
            $recordedBatchHash = hash('sha256', collect($batchRows)
                ->map(fn (array $row): string => implode('|', $row))
                ->implode("\n"));

            foreach ($owned as $managed) {
                if (! hash_equals($recordedBatchHash, $managed['metadata']['batch_manifest_sha256'])) {
                    throw new RuntimeException('Existing import audits do not describe one complete authoritative batch.');
                }
            }
        }

        $planEmails = collect($plans)->pluck('email')->map(
            fn ($email): string => Str::lower(trim((string) $email))
        )->all();
        $userGroups = User::query()
            ->whereIn(DB::raw('LOWER(TRIM(email))'), $planEmails)
            ->get()
            ->groupBy(fn (User $user): string => Str::lower(trim((string) $user->email)));
        foreach ($userGroups as $email => $users) {
            if ($users->count() !== 1) {
                throw new RuntimeException("Multiple system users share the placeholder email: {$email}");
            }
        }

        $codeGroups = FormSubmission::query()
            ->whereIn('procurement_submission_code', collect($plans)->pluck('submission_code')->all())
            ->get()
            ->groupBy('procurement_submission_code');
        $seenPlanEmails = [];
        $newCount = 0;
        $changedCount = 0;
        $unchangedCount = 0;
        $obsoletePaths = [];

        foreach ($plans as $plan) {
            $email = Str::lower(trim($plan['email']));
            $seenPlanEmails[$email] = true;
            $managed = $owned[$email] ?? null;
            /** @var User|null $existingUser */
            $existingUser = $userGroups->get($email)?->first();

            if (DiscussionAccountEmailPolicy::participantEmailExists($plan['email'])) {
                throw new RuntimeException("Placeholder email is already used by a discussion participant: {$plan['email']}");
            }
            if ($existingUser && (! $managed || (string) $existingUser->id !== (string) $managed['user']->id)) {
                throw new RuntimeException("Existing account is not owned by this import: {$plan['email']}");
            }
            if ($managed && ! $existingUser) {
                throw new RuntimeException("Imported placeholder account disappeared during preflight: {$plan['email']}");
            }
            if ($managed && trim((string) $managed['user']->name) !== $plan['name']) {
                throw new RuntimeException("Imported applicant name changed for existing placeholder email: {$plan['email']}");
            }

            $codeMatches = $codeGroups->get($plan['submission_code'], collect());
            if ($codeMatches->count() > 1 || (
                $codeMatches->count() === 1
                && (! $managed || (string) $codeMatches->first()->id !== (string) $managed['submission']->id)
            )) {
                throw new RuntimeException("Submission code conflicts with a non-imported row: {$plan['submission_code']}");
            }

            $this->assertStoredPackage($plan, false);

            if (! $managed) {
                $newCount++;

                continue;
            }

            $unchanged = hash_equals(
                $managed['metadata']['applicant_manifest_sha256'],
                $plan['manifest_hash']
            ) && hash_equals($managed['metadata']['package_sha256'], $plan['package_sha256'])
                && $managed['metadata']['storage_path'] === $plan['storage_path']
                && $managed['values_match'];
            if ($unchanged) {
                $this->assertStoredPackage($plan, true);
                $unchangedCount++;
            } else {
                $this->assertManagedSubmissionReplaceable($procurement, $managed, $lockForUpdate);
                $changedCount++;
                if ($managed['metadata']['storage_path'] !== $plan['storage_path']) {
                    $obsoletePaths[] = $managed['metadata']['storage_path'];
                }
            }
        }

        $stale = array_filter(
            $owned,
            fn (string $email): bool => ! isset($seenPlanEmails[$email]),
            ARRAY_FILTER_USE_KEY
        );
        foreach ($stale as $managed) {
            $this->assertManagedSubmissionReplaceable($procurement, $managed, $lockForUpdate);
            $obsoletePaths[] = $managed['metadata']['storage_path'];
        }

        return [
            'owned' => $owned,
            'stale' => $stale,
            'new_count' => $newCount,
            'changed_count' => $changedCount,
            'unchanged_count' => $unchangedCount,
            'stale_count' => count($stale),
            'unrelated_count' => $targetSubmissions->count() - count($owned),
            'obsolete_paths' => array_values(array_unique($obsoletePaths)),
            'batch_manifest_hash' => $batchManifestHash,
        ];
    }

    /** @return array<string,mixed> */
    private function validateManagedImport(
        Procurement $procurement,
        DynamicForm $form,
        FormSubmission $submission,
        ProcurementAuditLog $audit,
        bool $lockForUpdate
    ): array {
        if (
            (string) $audit->procurement_id !== (string) $procurement->id
            || (string) $audit->form_id !== (string) $form->id
            || (string) $audit->submission_id !== (string) $submission->id
        ) {
            throw new RuntimeException('External-import audit ownership fields are invalid.');
        }
        if ($audit->user_id !== null) {
            throw new RuntimeException('External-import audit has an unexpected actor.');
        }

        $metadata = is_array($audit->metadata) ? $audit->metadata : [];
        $version = $metadata['import_version'] ?? null;
        $legacyKeys = [
            'import_version', 'batch_manifest_sha256', 'applicant_manifest_sha256',
            'source_folder', 'placeholder_email', 'target_reference_no', 'storage_path',
            'package_sha256', 'packaged_as_zip', 'source_files',
        ];
        $versionTwoKeys = [...$legacyKeys, 'managed_by'];
        $expectedKeys = $version === self::LEGACY_IMPORT_VERSION ? $legacyKeys : $versionTwoKeys;
        $actualKeys = array_keys($metadata);
        sort($expectedKeys);
        sort($actualKeys);
        if (
            ! in_array($version, [self::LEGACY_IMPORT_VERSION, self::IMPORT_VERSION], true)
            || $actualKeys !== $expectedKeys
            || ($version === self::IMPORT_VERSION && ($metadata['managed_by'] ?? null) !== self::OWNERSHIP_MARKER)
        ) {
            throw new RuntimeException('External-import audit has invalid ownership metadata.');
        }

        foreach (['batch_manifest_sha256', 'applicant_manifest_sha256', 'package_sha256'] as $hashKey) {
            if (! is_string($metadata[$hashKey]) || preg_match('/\A[a-f0-9]{64}\z/', $metadata[$hashKey]) !== 1) {
                throw new RuntimeException("External-import audit has an invalid {$hashKey}.");
            }
        }
        if (
            ! is_string($metadata['source_folder'])
            || trim($metadata['source_folder']) !== $metadata['source_folder']
            || $metadata['source_folder'] === ''
            || mb_strlen($metadata['source_folder']) > 255
            || preg_match('~[\\\\/]~', $metadata['source_folder']) === 1
            || ! is_string($metadata['placeholder_email'])
            || ! is_string($metadata['target_reference_no'])
            || $metadata['target_reference_no'] !== (string) $procurement->reference_no
            || ! is_string($metadata['storage_path'])
            || ! is_bool($metadata['packaged_as_zip'])
            || ! is_array($metadata['source_files'])
            || ! array_is_list($metadata['source_files'])
            || $metadata['source_files'] === []
            || count($metadata['source_files']) > self::MAX_FILES
        ) {
            throw new RuntimeException('External-import audit metadata is malformed.');
        }

        $derivedEmail = Str::lower(Str::slug($metadata['source_folder']).'@'.self::DEFAULT_EMAIL_DOMAIN);
        if ($metadata['placeholder_email'] !== $derivedEmail || ! filter_var($derivedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('External-import audit placeholder email is not derived from its source folder.');
        }

        $sourcePaths = [];
        $totalBytes = 0;
        foreach ($metadata['source_files'] as $sourceFile) {
            $sourceKeys = is_array($sourceFile) ? array_keys($sourceFile) : [];
            sort($sourceKeys);
            if (
                $sourceKeys !== ['bytes', 'path', 'sha256']
                || ! is_string($sourceFile['path'])
                || $sourceFile['path'] === ''
                || basename($sourceFile['path']) !== $sourceFile['path']
                || Str::lower(pathinfo($sourceFile['path'], PATHINFO_EXTENSION)) !== 'pdf'
                || ! is_int($sourceFile['bytes'])
                || $sourceFile['bytes'] < 5
                || $sourceFile['bytes'] > self::MAX_FILE_BYTES
                || ! is_string($sourceFile['sha256'])
                || preg_match('/\A[a-f0-9]{64}\z/', $sourceFile['sha256']) !== 1
            ) {
                throw new RuntimeException('External-import audit contains an invalid source-file record.');
            }
            $pathKey = Str::lower($sourceFile['path']);
            if (isset($sourcePaths[$pathKey])) {
                throw new RuntimeException('External-import audit contains duplicate source-file paths.');
            }
            $sourcePaths[$pathKey] = true;
            $totalBytes += $sourceFile['bytes'];
        }
        if ($totalBytes > self::MAX_APPLICANT_BYTES) {
            throw new RuntimeException('External-import audit exceeds the applicant size limit.');
        }

        $sortedSourceFiles = $metadata['source_files'];
        usort($sortedSourceFiles, fn (array $left, array $right): int => strnatcasecmp($left['path'], $right['path']));
        if ($sortedSourceFiles !== $metadata['source_files']) {
            throw new RuntimeException('External-import audit source files are not canonically ordered.');
        }
        $applicantManifestHash = hash('sha256', collect($metadata['source_files'])
            ->map(fn (array $file): string => implode('|', [$file['path'], $file['bytes'], $file['sha256']]))
            ->implode("\n"));
        if (! hash_equals($applicantManifestHash, $metadata['applicant_manifest_sha256'])) {
            throw new RuntimeException('External-import audit applicant manifest checksum is invalid.');
        }

        $isArchive = count($metadata['source_files']) > 1;
        $expectedStoragePath = implode('/', [
            'procurement_submissions',
            'external-imports',
            (string) $procurement->id,
            $metadata['package_sha256'].($isArchive ? '.zip' : '.pdf'),
        ]);
        if (
            $metadata['packaged_as_zip'] !== $isArchive
            || $metadata['storage_path'] !== $expectedStoragePath
            || (! $isArchive && ! hash_equals($metadata['source_files'][0]['sha256'], $metadata['package_sha256']))
        ) {
            throw new RuntimeException('External-import audit package provenance is invalid.');
        }

        if (
            (string) $submission->procurement_id !== (string) $procurement->id
            || (string) $submission->form_id !== (string) $form->id
            || $submission->procurement_submission_code !== $this->submissionCode($procurement, $derivedEmail)
            || $submission->submitted_at === null
        ) {
            throw new RuntimeException("Imported submission ownership fields are invalid: {$derivedEmail}");
        }

        $userQuery = User::query()->whereKey($submission->submitted_by);
        if ($lockForUpdate) {
            $userQuery->lockForUpdate();
        }
        /** @var User|null $user */
        $user = $userQuery->first();
        if (
            ! $user
            || Str::lower(trim((string) $user->email)) !== $derivedEmail
            || trim((string) $user->name) !== $metadata['source_folder']
            || $user->user_type !== 'vendor'
        ) {
            throw new RuntimeException("Imported placeholder account provenance changed: {$derivedEmail}");
        }

        if (User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$derivedEmail])->count() !== 1) {
            throw new RuntimeException("Imported placeholder email is not globally unique: {$derivedEmail}");
        }
        if (FormSubmission::query()
            ->where('procurement_id', $procurement->id)
            ->where('submitted_by', $user->id)
            ->count() !== 1) {
            throw new RuntimeException("Imported account has multiple target submissions: {$derivedEmail}");
        }

        $valueQuery = FormSubmissionValue::query()->where('submission_id', $submission->id);
        if ($lockForUpdate) {
            $valueQuery->lockForUpdate();
        }
        $valueRows = $valueQuery->get();
        $values = $valueRows->groupBy('field_key');
        $valuesMatch = $valueRows->count() === $form->fields->count()
            && $values->count() === $form->fields->count();
        $metadataPlan = [
            'name' => $metadata['source_folder'],
            'email' => $metadata['placeholder_email'],
            'storage_path' => $metadata['storage_path'],
        ];
        foreach ($form->fields as $field) {
            $matches = $values->get($field->field_key, collect());
            $expectedValue = $this->expectedFieldValue($field->field_key, $metadataPlan);
            if ($matches->count() !== 1 || ! $this->fieldValueMatches($matches->first()->value, $expectedValue)) {
                $valuesMatch = false;
            }
        }

        $this->assertStoredManagedPackage($metadata);

        return [
            'email' => $derivedEmail,
            'submission' => $submission,
            'audit' => $audit,
            'user' => $user,
            'metadata' => $metadata,
            'values_match' => $valuesMatch,
        ];
    }

    private function assertManagedSubmissionReplaceable(
        Procurement $procurement,
        array $managed,
        bool $lockForUpdate
    ): void {
        /** @var FormSubmission $submission */
        $submission = $managed['submission'];
        /** @var ProcurementAuditLog $audit */
        $audit = $managed['audit'];
        /** @var User $user */
        $user = $managed['user'];
        $email = $managed['email'];

        if (
            $submission->status !== FormSubmission::STATUS_SUBMITTED
            || $user->is_disabled !== true
            || $user->disabled_at === null
            || (string) $user->disabled_reason !== self::DISABLED_REASON
            || $user->must_change_password !== true
            || $user->is_blacklisted !== false
        ) {
            throw new RuntimeException("Imported submission or account state is no longer replaceable: {$email}");
        }
        foreach (['assigned_prescreener_id', 'vendor_response', 'resubmitted_at', 'withdrawn_at', 'withdrawal_reason'] as $column) {
            if (Schema::hasColumn('form_submissions', $column) && $submission->getAttribute($column) !== null) {
                throw new RuntimeException("Imported submission has workflow state and cannot be replaced: {$email}");
            }
        }

        $otherAudits = ProcurementAuditLog::query()
            ->where('submission_id', $submission->id)
            ->where('id', '!=', $audit->id);
        if ($lockForUpdate) {
            $otherAudits->lockForUpdate();
        }
        if ($otherAudits->exists()) {
            throw new RuntimeException("Imported submission has additional audit activity: {$email}");
        }

        $references = [
            ['evaluation_assignments', 'form_submission_id'],
            ['evaluation_submissions', 'form_submission_id'],
            ['evaluation_results', 'submission_id'],
            ['evaluation_criteria_scores', 'submission_id'],
            ['evaluation_section_scores', 'submission_id'],
            ['site_visits', 'form_submission_id'],
            ['attp_think_tank_procurement_reviews', 'form_submission_id'],
            ['procurement_submission_screenings', 'submission_id'],
            ['prescreening_evaluations', 'submission_id'],
            ['prescreening_results', 'submission_id'],
            ['procurement_contract_negotiations', 'submission_id'],
        ];
        foreach ($references as [$table, $column]) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, $column)
                && DB::table($table)->where($column, $submission->id)->exists()
            ) {
                throw new RuntimeException("Imported submission has downstream workflow data in {$table}: {$email}");
            }
        }

        if (
            Schema::hasColumn('procurements', 'awarded_submission_id')
            && (string) $procurement->awarded_submission_id === (string) $submission->id
        ) {
            throw new RuntimeException("Imported submission has been awarded and cannot be replaced: {$email}");
        }
    }

    private function fieldValueMatches(mixed $actual, ?string $expected): bool
    {
        return $expected === null ? $actual === null : (string) $actual === $expected;
    }

    private function expectedPackageHash(array $applicant): string
    {
        if (! $applicant['is_archive']) {
            return $applicant['files'][0]['sha256'];
        }

        $temporaryPath = $this->buildArchive($applicant);
        try {
            $hash = hash_file('sha256', $temporaryPath);
            if ($hash === false) {
                throw new RuntimeException("Could not checksum submission package for {$applicant['name']}.");
            }

            return $hash;
        } finally {
            if (file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function prepareAttachment(array $plan, array &$createdPaths): string
    {
        if (! $plan['is_archive']) {
            return $this->copyFileExclusively(
                $plan['files'][0]['absolute_path'],
                $plan['storage_path'],
                $plan['package_sha256'],
                $createdPaths
            );
        }

        $temporaryPath = $this->buildArchive($plan);
        try {
            return $this->copyFileExclusively(
                $temporaryPath,
                $plan['storage_path'],
                $plan['package_sha256'],
                $createdPaths
            );
        } finally {
            if (file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function buildArchive(array $plan): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP support is required for applicants with multiple source files.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'attp-procurement-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Could not create a temporary submission package.');
        }

        @unlink($temporaryPath);
        $zip = new ZipArchive;
        $zipIsOpen = false;

        try {
            $this->assertSourceFilesUnchanged($plan);

            if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Could not create submission package for {$plan['name']}.");
            }
            $zipIsOpen = true;

            foreach ($plan['files'] as $file) {
                if (! $zip->addFile($file['absolute_path'], $file['relative_path'])) {
                    throw new RuntimeException("Could not package {$file['relative_path']} for {$plan['name']}.");
                }
                if (method_exists($zip, 'setMtimeName')) {
                    $zip->setMtimeName($file['relative_path'], 315532800);
                }
            }

            $packageManifest = json_encode([
                'applicant' => $plan['name'],
                'email' => $plan['email'],
                'manifest_sha256' => $plan['manifest_hash'],
                'files' => collect($plan['files'])->map(fn (array $file): array => [
                    'path' => $file['relative_path'],
                    'bytes' => $file['size'],
                    'sha256' => $file['sha256'],
                ])->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $manifestName = '_external-import-manifest.json';
            $zip->addFromString($manifestName, $packageManifest);
            if (method_exists($zip, 'setMtimeName')) {
                $zip->setMtimeName($manifestName, 315532800);
            }

            if (! $zip->close()) {
                throw new RuntimeException("Could not finalize submission package for {$plan['name']}.");
            }
            $zipIsOpen = false;

            $this->assertSourceFilesUnchanged($plan);

            return $temporaryPath;
        } catch (Throwable $exception) {
            if ($zipIsOpen) {
                $zip->close();
            }
            if (file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw $exception;
        }
    }

    private function copyFileExclusively(
        string $sourcePath,
        string $storagePath,
        string $expectedHash,
        array &$createdPaths
    ): string {
        $disk = Storage::disk('local');

        if ($disk->exists($storagePath)) {
            $targetPath = $disk->path($storagePath);
            if (is_link($targetPath) || ! is_file($targetPath) || ! is_readable($targetPath)) {
                throw new RuntimeException("Stored attachment is not a regular readable file: {$storagePath}");
            }
            $storedHash = hash_file('sha256', $targetPath);
            if (! hash_equals($expectedHash, (string) $storedHash)) {
                throw new RuntimeException("Stored attachment conflicts with its source package: {$storagePath}");
            }

            return $expectedHash;
        }

        if (! $disk->makeDirectory(dirname($storagePath))) {
            throw new RuntimeException("Could not create private import directory for {$storagePath}.");
        }

        $source = fopen($sourcePath, 'rb');
        if ($source === false) {
            throw new RuntimeException("Could not read prepared source file for {$storagePath}.");
        }

        $targetPath = $disk->path($storagePath);
        $target = @fopen($targetPath, 'xb');
        if ($target === false) {
            fclose($source);

            if ($disk->exists($storagePath)) {
                $storedHash = is_link($targetPath) || ! is_file($targetPath) || ! is_readable($targetPath)
                    ? false
                    : hash_file('sha256', $targetPath);
                if (is_string($storedHash) && hash_equals($expectedHash, $storedHash)) {
                    return $expectedHash;
                }
            }

            throw new RuntimeException("Could not exclusively create private attachment: {$storagePath}");
        }

        $createdPaths[] = $storagePath;
        try {
            $copiedBytes = stream_copy_to_stream($source, $target);
            if ($copiedBytes === false || $copiedBytes !== filesize($sourcePath)) {
                throw new RuntimeException("Private attachment copy was incomplete: {$storagePath}");
            }
            fflush($target);
        } finally {
            fclose($source);
            fclose($target);
        }

        $storedHash = hash_file('sha256', $disk->path($storagePath));
        if (! hash_equals($expectedHash, (string) $storedHash)) {
            throw new RuntimeException("Stored attachment checksum failed: {$storagePath}");
        }

        return $expectedHash;
    }

    private function assertStoredPackage(array $plan, bool $mustExist): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($plan['storage_path'])) {
            if ($mustExist) {
                throw new RuntimeException("Imported attachment is missing: {$plan['storage_path']}");
            }

            return;
        }

        $absolutePath = $disk->path($plan['storage_path']);
        if (is_link($absolutePath) || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException("Stored attachment is not a regular readable file: {$plan['storage_path']}");
        }
        $storedHash = hash_file('sha256', $absolutePath);
        if (! hash_equals($plan['package_sha256'], (string) $storedHash)) {
            throw new RuntimeException("Stored attachment conflicts with source: {$plan['storage_path']}");
        }
    }

    private function assertStoredManagedPackage(array $metadata): void
    {
        $disk = Storage::disk('local');
        $storagePath = $metadata['storage_path'];
        if (! $disk->exists($storagePath)) {
            throw new RuntimeException("Imported attachment is missing: {$storagePath}");
        }

        $absolutePath = $disk->path($storagePath);
        if (is_link($absolutePath) || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException("Imported attachment is not a regular readable file: {$storagePath}");
        }

        $storedHash = hash_file('sha256', $absolutePath);
        if (! is_string($storedHash) || ! hash_equals($metadata['package_sha256'], $storedHash)) {
            throw new RuntimeException("Imported attachment checksum no longer matches its audit: {$storagePath}");
        }

        if (! $metadata['packaged_as_zip']) {
            clearstatcache(true, $absolutePath);
            $header = file_get_contents($absolutePath, false, null, 0, 5);
            if (
                $header !== '%PDF-'
                || filesize($absolutePath) !== $metadata['source_files'][0]['bytes']
            ) {
                throw new RuntimeException("Imported PDF package no longer matches its audit: {$storagePath}");
            }

            return;
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP support is required to validate an existing imported package.');
        }

        $zip = new ZipArchive;
        if ($zip->open($absolutePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("Imported ZIP package cannot be opened: {$storagePath}");
        }

        try {
            $expectedFiles = collect($metadata['source_files'])->keyBy(
                fn (array $file): string => Str::lower($file['path'])
            );
            $seen = [];
            $manifestName = '_external-import-manifest.json';
            $manifestContents = null;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $entryName = is_array($stat) ? ($stat['name'] ?? null) : null;
                if (! is_string($entryName) || $entryName === '' || basename($entryName) !== $entryName) {
                    throw new RuntimeException("Imported ZIP contains an invalid entry: {$storagePath}");
                }
                $entryKey = Str::lower($entryName);
                if (isset($seen[$entryKey])) {
                    throw new RuntimeException("Imported ZIP contains a duplicate entry: {$storagePath}");
                }
                $seen[$entryKey] = true;

                if ($entryName === $manifestName) {
                    $manifestContents = $zip->getFromIndex($index);
                    if (! is_string($manifestContents)) {
                        throw new RuntimeException("Imported ZIP manifest cannot be read: {$storagePath}");
                    }

                    continue;
                }

                $expected = $expectedFiles->get($entryKey);
                if (! $expected || $entryName !== $expected['path'] || (int) ($stat['size'] ?? -1) !== $expected['bytes']) {
                    throw new RuntimeException("Imported ZIP entry conflicts with its audit: {$storagePath}");
                }

                $stream = $zip->getStream($entryName);
                if ($stream === false) {
                    throw new RuntimeException("Imported ZIP entry cannot be read: {$storagePath}");
                }
                $hash = hash_init('sha256');
                $header = fread($stream, 5);
                if (! is_string($header) || $header !== '%PDF-') {
                    fclose($stream);
                    throw new RuntimeException("Imported ZIP entry is no longer a PDF: {$storagePath}");
                }
                hash_update($hash, $header);
                $remainingBytes = hash_update_stream($hash, $stream, $expected['bytes'] - 4);
                fclose($stream);
                if (
                    $remainingBytes !== $expected['bytes'] - 5
                    || ! hash_equals($expected['sha256'], hash_final($hash))
                ) {
                    throw new RuntimeException("Imported ZIP entry checksum conflicts with its audit: {$storagePath}");
                }
            }

            $expectedManifest = json_encode([
                'applicant' => $metadata['source_folder'],
                'email' => $metadata['placeholder_email'],
                'manifest_sha256' => $metadata['applicant_manifest_sha256'],
                'files' => $metadata['source_files'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (
                count($seen) !== count($metadata['source_files']) + 1
                || $manifestContents !== $expectedManifest
                || ! isset($seen[Str::lower($manifestName)])
            ) {
                throw new RuntimeException("Imported ZIP contents conflict with its audit: {$storagePath}");
            }
        } finally {
            $zip->close();
        }
    }

    private function expectedFieldValue(string $fieldKey, array $plan): ?string
    {
        return match ($fieldKey) {
            'official_name' => $plan['name'],
            'official_email' => $plan['email'],
            'submit_eoi' => $plan['storage_path'],
            default => null,
        };
    }

    private function assertSourceFilesUnchanged(array $plan): void
    {
        foreach ($plan['files'] as $file) {
            $path = $file['absolute_path'];
            $displayPath = $plan['name'].'/'.$file['relative_path'];

            if (is_link($path) || ! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException("Validated source PDF changed before packaging: {$displayPath}");
            }

            clearstatcache(true, $path);
            $size = filesize($path);
            if ($size === false || $size !== $file['size']) {
                throw new RuntimeException("Validated source PDF size changed before packaging: {$displayPath}");
            }

            $handle = fopen($path, 'rb');
            $header = $handle !== false ? fread($handle, 5) : false;
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($header !== '%PDF-') {
                throw new RuntimeException("Validated source file is no longer a PDF: {$displayPath}");
            }

            $sha256 = hash_file('sha256', $path);
            if (! is_string($sha256) || ! hash_equals($file['sha256'], $sha256)) {
                throw new RuntimeException("Validated source PDF checksum changed before packaging: {$displayPath}");
            }
        }
    }

    /** @return array<string,mixed> */
    private function auditMetadata(
        Procurement $procurement,
        array $plan,
        string $batchManifestHash
    ): array {
        return [
            'import_version' => self::IMPORT_VERSION,
            'managed_by' => self::OWNERSHIP_MARKER,
            'batch_manifest_sha256' => $batchManifestHash,
            'applicant_manifest_sha256' => $plan['manifest_hash'],
            'source_folder' => $plan['name'],
            'placeholder_email' => $plan['email'],
            'target_reference_no' => $procurement->reference_no,
            'storage_path' => $plan['storage_path'],
            'package_sha256' => $plan['package_sha256'],
            'packaged_as_zip' => $plan['is_archive'],
            'source_files' => collect($plan['files'])->map(fn (array $file): array => [
                'path' => $file['relative_path'],
                'bytes' => $file['size'],
                'sha256' => $file['sha256'],
            ])->all(),
        ];
    }

    /** @return array<string,int> */
    private function persist(
        Procurement $procurement,
        DynamicForm $form,
        array $plans,
        string $batchManifestHash,
        array &$committedObsoletePaths
    ): array {
        $counts = [
            'created_users' => 0,
            'reused_users' => 0,
            'created_submissions' => 0,
            'reused_submissions' => 0,
            'replaced_submissions' => 0,
            'removed_submissions' => 0,
            'created_values' => 0,
            'removed_values' => 0,
            'created_audit_logs' => 0,
            'updated_audit_logs' => 0,
            'removed_audit_logs' => 0,
        ];

        DB::transaction(function () use (
            $procurement,
            $form,
            $plans,
            $batchManifestHash,
            &$committedObsoletePaths,
            &$counts
        ): void {
            $lockedProcurement = Procurement::query()->lockForUpdate()->find($procurement->id);
            if (! $lockedProcurement || $lockedProcurement->title !== $procurement->title) {
                throw new RuntimeException('The target procurement changed during import.');
            }

            $lockedForm = DynamicForm::query()->lockForUpdate()->find($form->id);
            if (
                ! $lockedForm
                || $lockedForm->procurement_id !== $procurement->id
                || $lockedForm->status !== 'approved'
                || ! $lockedForm->is_active
                || $lockedForm->applies_to !== 'submission'
            ) {
                throw new RuntimeException('The target application form changed during import.');
            }

            $lockedForm->load('fields');
            $this->assertFormDefinition($lockedForm);

            $preflight = $this->preflightDatabaseState(
                $lockedProcurement,
                $lockedForm,
                $plans,
                $batchManifestHash,
                true
            );
            $committedObsoletePaths = $preflight['obsolete_paths'];

            $fields = $lockedForm->fields->sortBy('sort_order')->values();
            $submittedAt = now();

            foreach ($plans as $plan) {
                $email = Str::lower(trim($plan['email']));
                $managed = $preflight['owned'][$email] ?? null;

                if (! $managed) {
                    $existingUsers = User::query()
                        ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                        ->lockForUpdate()
                        ->get();
                    if ($existingUsers->isNotEmpty()) {
                        throw new RuntimeException("Placeholder account appeared during import: {$plan['email']}");
                    }
                    if (DiscussionAccountEmailPolicy::participantEmailExists($plan['email'])) {
                        throw new RuntimeException("Discussion participant email appeared during import: {$plan['email']}");
                    }

                    $user = User::create([
                        'name' => $plan['name'],
                        'email' => $plan['email'],
                        'password' => Hash::make(Str::password(48)),
                        'user_type' => 'vendor',
                        'must_change_password' => true,
                        'is_disabled' => true,
                        'disabled_at' => $submittedAt,
                        'disabled_reason' => self::DISABLED_REASON,
                        'is_blacklisted' => false,
                    ]);
                    $counts['created_users']++;
                } else {
                    /** @var User $user */
                    $user = $managed['user'];
                    $counts['reused_users']++;
                }

                if (! $managed) {
                    $submission = FormSubmission::create([
                        'procurement_id' => $procurement->id,
                        'procurement_submission_code' => $plan['submission_code'],
                        'form_id' => $form->id,
                        'submitted_by' => $user->id,
                        'status' => FormSubmission::STATUS_SUBMITTED,
                        'submitted_at' => $submittedAt,
                        'publication_version' => max(1, (int) $procurement->publication_version),
                    ]);
                    $counts['created_submissions']++;
                } else {
                    /** @var FormSubmission $submission */
                    $submission = $managed['submission'];
                    $isChanged = ! hash_equals(
                        $managed['metadata']['applicant_manifest_sha256'],
                        $plan['manifest_hash']
                    ) || ! hash_equals($managed['metadata']['package_sha256'], $plan['package_sha256'])
                        || $managed['metadata']['storage_path'] !== $plan['storage_path']
                        || ! $managed['values_match'];
                    if ($isChanged) {
                        $counts['replaced_submissions']++;
                    } else {
                        $counts['reused_submissions']++;
                    }
                }

                if (! $managed || $isChanged) {
                    if ($managed) {
                        $counts['removed_values'] += FormSubmissionValue::query()
                            ->where('submission_id', $submission->id)
                            ->delete();
                    }
                    foreach ($fields as $field) {
                        FormSubmissionValue::create([
                            'submission_id' => $submission->id,
                            'field_key' => $field->field_key,
                            'value' => $this->expectedFieldValue($field->field_key, $plan),
                        ]);
                        $counts['created_values']++;
                    }
                }

                $expectedMetadata = $this->auditMetadata($procurement, $plan, $batchManifestHash);
                if (! $managed) {
                    ProcurementAuditLog::create([
                        'user_id' => null,
                        'action' => self::AUDIT_ACTION,
                        'procurement_id' => $procurement->id,
                        'form_id' => $form->id,
                        'submission_id' => $submission->id,
                        'metadata' => $expectedMetadata,
                        'created_at' => $submittedAt,
                    ]);
                    $counts['created_audit_logs']++;
                } elseif (! $this->metadataMatchesExactly($expectedMetadata, (array) $managed['audit']->metadata)) {
                    /** @var ProcurementAuditLog $audit */
                    $audit = $managed['audit'];
                    $audit->metadata = $expectedMetadata;
                    if (! $audit->save()) {
                        throw new RuntimeException("Could not update import audit for {$plan['email']}.");
                    }
                    $counts['updated_audit_logs']++;
                }
            }

            foreach ($preflight['stale'] as $managed) {
                /** @var FormSubmission $submission */
                $submission = $managed['submission'];
                /** @var ProcurementAuditLog $audit */
                $audit = $managed['audit'];
                $counts['removed_values'] += FormSubmissionValue::query()
                    ->where('submission_id', $submission->id)
                    ->delete();
                if (! $audit->delete()) {
                    throw new RuntimeException("Could not remove stale import audit for {$managed['email']}.");
                }
                $counts['removed_audit_logs']++;
                if (! $submission->delete()) {
                    throw new RuntimeException("Could not remove stale imported submission for {$managed['email']}.");
                }
                $counts['removed_submissions']++;
            }
        }, 3);

        return $counts;
    }

    /** @return array<string,int> */
    private function deleteObsoletePackages(
        Procurement $procurement,
        array $obsoletePaths,
        array $activePaths
    ): array {
        $counts = [
            'deleted_obsolete_files' => 0,
            'retained_referenced_files' => 0,
            'failed_file_deletions' => 0,
        ];
        $active = array_fill_keys($activePaths, true);
        $disk = Storage::disk('local');
        $pathPattern = '~\Aprocurement_submissions/external-imports/'
            .preg_quote((string) $procurement->id, '~')
            .'/([a-f0-9]{64})\.(pdf|zip)\z~';

        foreach (array_values(array_unique($obsoletePaths)) as $path) {
            try {
                if (! is_string($path) || preg_match($pathPattern, $path, $matches) !== 1) {
                    $counts['failed_file_deletions']++;

                    continue;
                }
                if (isset($active[$path]) || $this->storedPathIsReferenced($path)) {
                    $counts['retained_referenced_files']++;

                    continue;
                }
                if (! $disk->exists($path)) {
                    continue;
                }

                $absolutePath = $disk->path($path);
                $hash = is_link($absolutePath) || ! is_file($absolutePath)
                    ? false
                    : hash_file('sha256', $absolutePath);
                if (! is_string($hash) || ! hash_equals($matches[1], $hash) || ! $disk->delete($path)) {
                    $counts['failed_file_deletions']++;

                    continue;
                }
                $counts['deleted_obsolete_files']++;
            } catch (Throwable) {
                $counts['failed_file_deletions']++;
            }
        }

        return $counts;
    }

    private function storedPathIsReferenced(string $path): bool
    {
        if (FormSubmissionValue::query()->where('value', $path)->exists()) {
            return true;
        }

        return ProcurementAuditLog::query()
            ->get(['metadata'])
            ->contains(fn (ProcurementAuditLog $audit): bool => $this->metadataContainsValue(
                (array) $audit->metadata,
                $path
            ));
    }

    private function metadataContainsValue(array $metadata, string $path): bool
    {
        foreach ($metadata as $value) {
            if ($value === $path || (is_array($value) && $this->metadataContainsValue($value, $path))) {
                return true;
            }
        }

        return false;
    }

    private function metadataMatchesExactly(array $expected, array $actual): bool
    {
        return $this->canonicalizeMetadata($expected) === $this->canonicalizeMetadata($actual);
    }

    private function canonicalizeMetadata(array $metadata): array
    {
        if (! array_is_list($metadata)) {
            ksort($metadata);
        }

        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                $metadata[$key] = $this->canonicalizeMetadata($value);
            }
        }

        return $metadata;
    }
}
