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

        $this->assertDatabaseCompatibility($procurement, $form, $plans, $manifest['manifest_hash']);

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
        ];

        if ($dryRun) {
            return $baseSummary;
        }

        $lock = Cache::lock("external-procurement-submissions:{$procurement->id}", 600);
        if (! $lock->get()) {
            throw new RuntimeException('Another external submission import is already running for this procurement.');
        }

        $createdPaths = [];

        try {
            foreach ($plans as &$plan) {
                $plan['package_sha256'] = $this->prepareAttachment($plan, $createdPaths);
            }
            unset($plan);

            $counts = $this->persist(
                $procurement,
                $form,
                $plans,
                $manifest['manifest_hash']
            );

            return [...$baseSummary, ...$counts];
        } catch (Throwable $exception) {
            $disk = Storage::disk('local');
            foreach (array_reverse($createdPaths) as $createdPath) {
                $disk->delete($createdPath);
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
            $submissionCode = 'PROC-EXT-'.strtoupper(substr(
                hash('sha256', $procurement->id.'|'.$applicant['email']),
                0,
                12
            ));
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
                'submission_code' => $submissionCode,
                'package_sha256' => $packageSha256,
            ];
        }, $applicants);
    }

    private function assertDatabaseCompatibility(
        Procurement $procurement,
        DynamicForm $form,
        array $plans,
        string $batchManifestHash
    ): void {
        $emails = collect($plans)->pluck('email')->map(fn ($email) => Str::lower($email))->all();
        $userGroups = User::query()
            ->whereIn(DB::raw('LOWER(TRIM(email))'), $emails)
            ->get()
            ->groupBy(fn (User $user): string => Str::lower(trim((string) $user->email)));

        foreach ($userGroups as $email => $matchingUsers) {
            if ($matchingUsers->count() > 1) {
                throw new RuntimeException("Multiple system users share the placeholder email: {$email}");
            }
        }

        $targetSubmissions = FormSubmission::query()
            ->where('procurement_id', $procurement->id)
            ->get();
        $targetImportAudits = ProcurementAuditLog::query()
            ->where('procurement_id', $procurement->id)
            ->where('action', self::AUDIT_ACTION)
            ->get();
        if ($targetSubmissions->isNotEmpty() && $targetSubmissions->count() !== count($plans)) {
            throw new RuntimeException('The target procurement contains a partial or unrelated submission set.');
        }

        $verifiedSubmissionIds = collect();
        $verifiedAuditIds = collect();

        foreach ($plans as $plan) {
            if (DiscussionAccountEmailPolicy::participantEmailExists($plan['email'])) {
                throw new RuntimeException("Placeholder email is already used by a discussion participant: {$plan['email']}");
            }

            /** @var User|null $user */
            $user = $userGroups->get(Str::lower($plan['email']))?->first();
            if (! $user) {
                if (FormSubmission::query()->where('procurement_submission_code', $plan['submission_code'])->exists()) {
                    throw new RuntimeException("Submission code already exists without its imported user: {$plan['submission_code']}");
                }

                $this->assertStoredPackage($plan, false);

                continue;
            }

            if ($user->user_type !== 'vendor' || trim((string) $user->name) !== $plan['name']) {
                throw new RuntimeException("Existing account conflicts with imported applicant: {$plan['email']}");
            }

            $submissions = FormSubmission::query()
                ->where('procurement_id', $procurement->id)
                ->where('submitted_by', $user->id)
                ->get();
            if ($submissions->count() !== 1) {
                throw new RuntimeException(
                    "Existing placeholder account is not an exact prior import: {$plan['email']}"
                );
            }

            /** @var FormSubmission $submission */
            $submission = $submissions->first();
            if (
                $submission->form_id !== $form->id
                || $submission->procurement_submission_code !== $plan['submission_code']
            ) {
                throw new RuntimeException("Existing submission conflicts with {$plan['email']}.");
            }

            $codeMatches = FormSubmission::query()
                ->where('procurement_submission_code', $plan['submission_code'])
                ->get();
            if ($codeMatches->count() !== 1 || $codeMatches->first()->id !== $submission->id) {
                throw new RuntimeException("Submission code is not unique: {$plan['submission_code']}");
            }

            $values = FormSubmissionValue::query()
                ->where('submission_id', $submission->id)
                ->get()
                ->groupBy('field_key');
            if ($values->count() !== $form->fields->count()) {
                throw new RuntimeException("Existing form values are incomplete or contain extra keys for {$plan['email']}.");
            }

            foreach ($form->fields as $field) {
                $matches = $values->get($field->field_key, collect());
                if ($matches->count() !== 1) {
                    throw new RuntimeException("Existing form value is missing or duplicated for {$plan['email']}: {$field->field_key}");
                }

                $expectedValue = $this->expectedFieldValue($field->field_key, $plan);
                $actualValue = $matches->first()->value;
                $matchesExpected = $expectedValue === null
                    ? $actualValue === null
                    : (string) $actualValue === (string) $expectedValue;
                if (! $matchesExpected) {
                    throw new RuntimeException("Existing form value conflicts with {$plan['email']}: {$field->field_key}");
                }
            }

            $audits = ProcurementAuditLog::query()
                ->where('procurement_id', $procurement->id)
                ->where('form_id', $form->id)
                ->where('submission_id', $submission->id)
                ->where('action', self::AUDIT_ACTION)
                ->get();

            if ($audits->count() !== 1) {
                throw new RuntimeException("Existing import audit is missing or duplicated for {$plan['email']}.");
            }

            /** @var ProcurementAuditLog $audit */
            $audit = $audits->first();
            if ($audit->user_id !== null) {
                throw new RuntimeException("Existing import audit has an unexpected user for {$plan['email']}.");
            }

            $expectedMetadata = $this->auditMetadata(
                $procurement,
                $plan,
                $batchManifestHash
            );
            if (! $this->metadataMatchesExactly($expectedMetadata, (array) $audit->metadata)) {
                throw new RuntimeException("Existing import audit metadata conflicts with {$plan['email']}.");
            }

            $this->assertStoredPackage($plan, true);
            $verifiedSubmissionIds->push((string) $submission->id);
            $verifiedAuditIds->push((string) $audit->id);
        }

        if (
            $targetSubmissions->isNotEmpty()
            && $targetSubmissions->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all()
                !== $verifiedSubmissionIds->sort()->values()->all()
        ) {
            throw new RuntimeException('The target procurement contains a submission not owned by this import.');
        }

        if (
            $targetImportAudits->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all()
                !== $verifiedAuditIds->sort()->values()->all()
        ) {
            throw new RuntimeException('The target procurement contains an external-import audit not owned by this import.');
        }
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
            $storedHash = hash_file('sha256', $disk->path($storagePath));
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
                $storedHash = hash_file('sha256', $targetPath);
                if (hash_equals($expectedHash, (string) $storedHash)) {
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

        $storedHash = hash_file('sha256', $disk->path($plan['storage_path']));
        if (! hash_equals($plan['package_sha256'], (string) $storedHash)) {
            throw new RuntimeException("Stored attachment conflicts with source: {$plan['storage_path']}");
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
            'import_version' => 1,
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
        string $batchManifestHash
    ): array {
        $counts = [
            'created_users' => 0,
            'reused_users' => 0,
            'created_submissions' => 0,
            'reused_submissions' => 0,
            'created_values' => 0,
            'created_audit_logs' => 0,
        ];

        DB::transaction(function () use (
            $procurement,
            $form,
            $plans,
            $batchManifestHash,
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

            $this->assertDatabaseCompatibility(
                $lockedProcurement,
                $lockedForm,
                $plans,
                $batchManifestHash
            );

            $fields = $lockedForm->fields->sortBy('sort_order')->values();
            $submittedAt = now();

            foreach ($plans as $plan) {
                $user = User::query()
                    ->whereRaw('LOWER(TRIM(email)) = ?', [Str::lower($plan['email'])])
                    ->lockForUpdate()
                    ->first();

                if (! $user) {
                    $user = User::create([
                        'name' => $plan['name'],
                        'email' => $plan['email'],
                        'password' => Hash::make(Str::password(48)),
                        'user_type' => 'vendor',
                        'must_change_password' => true,
                        'is_disabled' => true,
                        'disabled_at' => $submittedAt,
                        'disabled_reason' => 'Historical external procurement submission imported with a placeholder email.',
                        'is_blacklisted' => false,
                    ]);
                    $counts['created_users']++;
                } else {
                    if ($user->user_type !== 'vendor' || trim((string) $user->name) !== $plan['name']) {
                        throw new RuntimeException("Existing account changed during import: {$plan['email']}");
                    }

                    $priorSubmission = FormSubmission::query()
                        ->where('procurement_id', $procurement->id)
                        ->where('submitted_by', $user->id)
                        ->where('form_id', $form->id)
                        ->where('procurement_submission_code', $plan['submission_code'])
                        ->first();
                    $hasPriorAudit = $priorSubmission && ProcurementAuditLog::query()
                        ->where('procurement_id', $procurement->id)
                        ->where('form_id', $form->id)
                        ->where('submission_id', $priorSubmission->id)
                        ->where('action', self::AUDIT_ACTION)
                        ->exists();
                    if (! $hasPriorAudit) {
                        throw new RuntimeException("Existing account is not owned by this import: {$plan['email']}");
                    }
                    $counts['reused_users']++;
                }

                $submissions = FormSubmission::query()
                    ->where('procurement_id', $procurement->id)
                    ->where('submitted_by', $user->id)
                    ->lockForUpdate()
                    ->get();

                if ($submissions->count() > 1) {
                    throw new RuntimeException("Multiple target submissions exist for {$plan['email']}.");
                }

                /** @var FormSubmission|null $submission */
                $submission = $submissions->first();
                if (! $submission) {
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
                    if (
                        $submission->form_id !== $form->id
                        || $submission->procurement_submission_code !== $plan['submission_code']
                    ) {
                        throw new RuntimeException("Existing submission conflicts with {$plan['email']}.");
                    }
                    $counts['reused_submissions']++;
                }

                foreach ($fields as $field) {
                    $expectedValue = $this->expectedFieldValue($field->field_key, $plan);

                    $values = FormSubmissionValue::query()
                        ->where('submission_id', $submission->id)
                        ->where('field_key', $field->field_key)
                        ->lockForUpdate()
                        ->get();

                    if ($values->count() > 1) {
                        throw new RuntimeException("Duplicate form value exists for {$plan['email']}: {$field->field_key}");
                    }

                    $value = $values->first();
                    if (! $value) {
                        FormSubmissionValue::create([
                            'submission_id' => $submission->id,
                            'field_key' => $field->field_key,
                            'value' => $expectedValue,
                        ]);
                        $counts['created_values']++;
                    } elseif (
                        ($expectedValue === null && $value->value !== null)
                        || ($expectedValue !== null && (string) $value->value !== (string) $expectedValue)
                    ) {
                        throw new RuntimeException("Existing form value conflicts with {$plan['email']}: {$field->field_key}");
                    }
                }

                $audit = ProcurementAuditLog::query()
                    ->where('procurement_id', $procurement->id)
                    ->where('form_id', $form->id)
                    ->where('submission_id', $submission->id)
                    ->where('action', self::AUDIT_ACTION)
                    ->lockForUpdate()
                    ->first();

                if (! $audit) {
                    ProcurementAuditLog::create([
                        'user_id' => null,
                        'action' => self::AUDIT_ACTION,
                        'procurement_id' => $procurement->id,
                        'form_id' => $form->id,
                        'submission_id' => $submission->id,
                        'metadata' => $this->auditMetadata($procurement, $plan, $batchManifestHash),
                        'created_at' => $submittedAt,
                    ]);
                    $counts['created_audit_logs']++;
                } else {
                    $expectedMetadata = $this->auditMetadata($procurement, $plan, $batchManifestHash);
                    if (
                        $audit->user_id !== null
                        || ! $this->metadataMatchesExactly($expectedMetadata, (array) $audit->metadata)
                    ) {
                        throw new RuntimeException("Existing import audit conflicts with {$plan['email']}.");
                    }
                }
            }
        }, 3);

        return $counts;
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
