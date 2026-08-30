<?php

namespace App\Services;

use App\Models\EoiTechnicalProposalDocument;
use App\Support\EndowmentFundTechnicalProposalDocumentManifest;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Restores only the four checksum-pinned historical proposal scans shipped by
 * the dedicated Endowment Fund scenario. It deliberately cannot rehydrate an
 * arbitrary technical-proposal document.
 */
final class EndowmentFundTechnicalProposalDocumentRehydrator
{
    public const METADATA_VALID = 'valid';

    public const METADATA_INVALID = 'invalid';

    public const SOURCE_HEALTHY = 'healthy';

    public const SOURCE_MISSING = 'missing';

    public const SOURCE_INVALID = 'invalid';

    public const SOURCE_NOT_CHECKED = 'not_checked';

    public const STORAGE_HEALTHY = 'healthy';

    public const STORAGE_MISSING = 'missing';

    public const STORAGE_INVALID = 'invalid';

    public const STORAGE_NOT_CHECKED = 'not_checked';

    public const REPAIR_NOT_REQUESTED = 'not_requested';

    public const REPAIR_NOT_NEEDED = 'not_needed';

    public const REPAIR_RESTORED = 'restored';

    public const REPAIR_FAILED = 'failed';

    /**
     * @return array{
     *     document_id: string,
     *     applicant_name: string,
     *     filename: string,
     *     path: string,
     *     recognized: bool,
     *     metadata_status: string,
     *     source_status: string,
     *     storage_status: string,
     *     repair_status: string,
     *     message: string,
     *     ok: bool
     * }
     */
    public function inspect(EoiTechnicalProposalDocument $document): array
    {
        [$manifestDocument, $applicantName, $recognitionIssue] = $this->recognize($document);
        $result = $this->result($document, $applicantName);

        if (! $manifestDocument) {
            return [
                ...$result,
                'message' => $recognitionIssue,
            ];
        }

        [$sourceStatus, $sourceMessage] = $this->inspectSource($manifestDocument);
        [$storageStatus, $storageMessage] = $this->inspectStoredPath(
            Storage::disk('local'),
            (string) $document->file_path,
            $manifestDocument
        );
        $ok = $sourceStatus === self::SOURCE_HEALTHY
            && $storageStatus === self::STORAGE_HEALTHY;

        return [
            ...$result,
            'recognized' => true,
            'metadata_status' => self::METADATA_VALID,
            'source_status' => $sourceStatus,
            'storage_status' => $storageStatus,
            'message' => $ok ? 'Bundled source and private stored copy are healthy.' : $sourceMessage.' '.$storageMessage,
            'ok' => $ok,
        ];
    }

    /**
     * Restore a recognized missing or damaged private copy from its immutable
     * bundled source. Database records are never created or modified.
     *
     * @return array{
     *     document_id: string,
     *     applicant_name: string,
     *     filename: string,
     *     path: string,
     *     recognized: bool,
     *     metadata_status: string,
     *     source_status: string,
     *     storage_status: string,
     *     repair_status: string,
     *     message: string,
     *     ok: bool
     * }
     */
    public function repair(EoiTechnicalProposalDocument $document): array
    {
        $inspection = $this->inspect($document);

        if (! $inspection['recognized'] || $inspection['source_status'] !== self::SOURCE_HEALTHY) {
            return [
                ...$inspection,
                'repair_status' => self::REPAIR_FAILED,
                'message' => $inspection['message'].' No file was changed.',
            ];
        }

        if ($inspection['storage_status'] === self::STORAGE_HEALTHY) {
            return [
                ...$inspection,
                'repair_status' => self::REPAIR_NOT_NEEDED,
            ];
        }

        [$manifestDocument] = $this->recognize($document);

        if (! $manifestDocument) {
            return [
                ...$inspection,
                'repair_status' => self::REPAIR_FAILED,
                'message' => 'The document no longer matches the immutable manifest. No file was changed.',
            ];
        }

        $disk = Storage::disk('local');
        $destination = (string) $document->file_path;
        $temporary = $destination.'.rehydrating-'.Str::uuid().'.tmp';
        $backup = null;
        $source = $this->sourcePath($manifestDocument);
        $stream = null;

        try {
            $stream = fopen($source, 'rb');

            if ($stream === false || ! $disk->put($temporary, $stream)) {
                return $this->repairFailure($inspection, 'The verified bundled source could not be staged on private storage.');
            }

            [$temporaryStatus] = $this->inspectStoredPath($disk, $temporary, $manifestDocument);

            if ($temporaryStatus !== self::STORAGE_HEALTHY) {
                return $this->repairFailure($inspection, 'The staged private copy failed its size or checksum verification.');
            }

            // Another request may have completed recovery while this source was
            // being staged. Never replace a now-healthy copy.
            [$currentStatus] = $this->inspectStoredPath($disk, $destination, $manifestDocument);

            if ($currentStatus === self::STORAGE_HEALTHY) {
                $disk->delete($temporary);

                return [
                    ...$this->inspect($document),
                    'repair_status' => self::REPAIR_NOT_NEEDED,
                ];
            }

            if ($disk->exists($destination)) {
                $backup = $destination.'.invalid-'.Str::uuid().'.bak';

                if (! $disk->move($destination, $backup)) {
                    return $this->repairFailure($inspection, 'The invalid private copy could not be moved aside safely.');
                }
            }

            if (! $disk->move($temporary, $destination)) {
                if ($backup && $disk->exists($backup)) {
                    $disk->move($backup, $destination);
                }

                return $this->repairFailure($inspection, 'The verified staged copy could not be promoted to its expected path.');
            }

            if ($backup) {
                $disk->delete($backup);
            }

            $repaired = $this->inspect($document);

            if (! $repaired['ok']) {
                return $this->repairFailure($repaired, 'The promoted private copy did not pass final verification.');
            }

            return [
                ...$repaired,
                'repair_status' => self::REPAIR_RESTORED,
                'message' => 'The private stored copy was restored from the checksum-verified bundled source.',
            ];
        } catch (Throwable $exception) {
            // A filesystem adapter may throw after the existing invalid copy
            // has been moved aside. Restore that copy when promotion did not
            // produce a destination so an explicit repair can never leave the
            // document in a worse state than it found it.
            if ($backup) {
                try {
                    if ($disk->exists($backup) && ! $disk->exists($destination)) {
                        $disk->move($backup, $destination);
                    }
                } catch (Throwable) {
                    // Preserve the primary recovery error for the audit output.
                }
            }

            return $this->repairFailure(
                $inspection,
                'Private storage rejected the recovery operation: '.$exception->getMessage()
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            try {
                $disk->delete($temporary);
            } catch (Throwable) {
                // A failed cleanup must not hide the primary audit result.
            }
        }
    }

    /**
     * Best-effort recovery used only after the authorized download action has
     * established that its expected private path is unavailable.
     */
    public function recoverMissing(EoiTechnicalProposalDocument $document): bool
    {
        try {
            $inspection = $this->inspect($document);

            if (! $inspection['recognized']) {
                return false;
            }

            if ($inspection['storage_status'] === self::STORAGE_HEALTHY) {
                return true;
            }

            // A GET may recreate an absent copy, but it must not replace bytes
            // that are present and fail integrity checks. Explicit --repair is
            // required for that operation.
            if ($inspection['storage_status'] !== self::STORAGE_MISSING) {
                return false;
            }

            $result = $this->repair($document);

            if ($result['ok']) {
                Log::notice('Recovered a missing seeded technical-proposal document.', [
                    'document_id' => $document->getKey(),
                    'file_path' => $document->file_path,
                    'repair_status' => $result['repair_status'],
                ]);

                return true;
            }

            Log::warning('Unable to recover a missing seeded technical-proposal document.', [
                'document_id' => $document->getKey(),
                'file_path' => $document->file_path,
                'source_status' => $result['source_status'],
                'storage_status' => $result['storage_status'],
                'repair_status' => $result['repair_status'],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Seeded technical-proposal document recovery failed unexpectedly.', [
                'document_id' => $document->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * @return array{0: null|array<string, mixed>, 1: string, 2: string}
     */
    private function recognize(EoiTechnicalProposalDocument $document): array
    {
        try {
            $document->loadMissing([
                'submission.candidate.round.procurement',
                'submission.candidate.applicant.values',
            ]);
        } catch (Throwable) {
            return [null, '', 'The document workflow context could not be loaded.'];
        }

        $submission = $document->submission;
        $candidate = $submission?->candidate;
        $round = $candidate?->round;
        $procurement = $round?->procurement;
        $applicant = $candidate?->applicant;
        $applicantName = trim((string) ($applicant?->display_name ?? ''));

        if (! $submission || ! $candidate || ! $round || ! $procurement || ! $applicant) {
            return [null, $applicantName, 'The document does not have a complete technical-proposal workflow context.'];
        }

        if ((string) $document->proposal_submission_id !== (string) $submission->getKey()
            || (string) $submission->candidate_id !== (string) $candidate->getKey()
            || (string) $candidate->round_id !== (string) $round->getKey()
            || (string) $candidate->form_submission_id !== (string) $applicant->getKey()
            || (string) $round->procurement_id !== (string) $procurement->getKey()
            || $procurement->reference_no !== EndowmentFundTechnicalProposalDocumentManifest::PROCUREMENT_REFERENCE
            || $round->title !== EndowmentFundTechnicalProposalDocumentManifest::ROUND_TITLE) {
            return [null, $applicantName, 'The document does not belong to the immutable Endowment Fund scenario.'];
        }

        $manifestDocument = EndowmentFundTechnicalProposalDocumentManifest::find(
            $applicantName,
            (string) $document->original_filename
        );

        if (! $manifestDocument) {
            return [null, $applicantName, 'The document is not one of the checksum-pinned bundled proposal scans.'];
        }

        $expectedPath = 'eoi-technical-proposals/'.$round->getKey()
            .'/candidates/'.$candidate->getKey()
            .'/revisions/'.$submission->revision_number
            .'/'.Str::slug(pathinfo($manifestDocument['filename'], PATHINFO_FILENAME)).'.pdf';

        if ((string) $document->file_path !== $expectedPath
            || strtolower((string) $document->sha256) !== $manifestDocument['sha256']
            || (int) $document->file_size !== $manifestDocument['file_size']
            || strtolower((string) $document->extension) !== 'pdf'
            || strtolower((string) $document->mime_type) !== 'application/pdf') {
            return [null, $applicantName, 'The document metadata or storage path differs from the approved immutable manifest.'];
        }

        return [$manifestDocument, $applicantName, ''];
    }

    /** @param array<string, mixed> $manifestDocument */
    private function inspectSource(array $manifestDocument): array
    {
        $source = $this->sourcePath($manifestDocument);

        if (! is_file($source) || ! is_readable($source)) {
            return [self::SOURCE_MISSING, 'The bundled Git LFS source is missing or unreadable.'];
        }

        $handle = fopen($source, 'rb');

        if ($handle === false) {
            return [self::SOURCE_MISSING, 'The bundled Git LFS source could not be opened.'];
        }

        try {
            $header = fread($handle, 5);
        } finally {
            fclose($handle);
        }

        $size = filesize($source);
        $sha256 = hash_file('sha256', $source);

        if ($header !== '%PDF-'
            || $size !== $manifestDocument['file_size']
            || ! is_string($sha256)
            || ! hash_equals($manifestDocument['sha256'], strtolower($sha256))) {
            return [self::SOURCE_INVALID, 'The bundled source is an LFS pointer or failed size/checksum verification.'];
        }

        return [self::SOURCE_HEALTHY, 'The bundled source is healthy.'];
    }

    /** @param array<string, mixed> $manifestDocument */
    private function inspectStoredPath(
        FilesystemAdapter $disk,
        string $path,
        array $manifestDocument
    ): array {
        try {
            if (! $disk->exists($path)) {
                return [self::STORAGE_MISSING, 'The private stored copy is missing or unreadable.'];
            }

            if ((int) $disk->size($path) !== $manifestDocument['file_size']) {
                return [self::STORAGE_INVALID, 'The private stored copy has an unexpected size.'];
            }

            $stream = $disk->readStream($path);

            if ($stream === false) {
                return [self::STORAGE_INVALID, 'The private stored copy could not be read.'];
            }

            try {
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);
                $sha256 = hash_final($hash);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! hash_equals($manifestDocument['sha256'], strtolower($sha256))) {
                return [self::STORAGE_INVALID, 'The private stored copy failed checksum verification.'];
            }

            return [self::STORAGE_HEALTHY, 'The private stored copy is healthy.'];
        } catch (Throwable) {
            return [self::STORAGE_MISSING, 'The private stored copy is missing or unreadable.'];
        }
    }

    /** @param array<string, mixed> $manifestDocument */
    private function sourcePath(array $manifestDocument): string
    {
        return database_path(
            EndowmentFundTechnicalProposalDocumentManifest::BUNDLED_ASSET_DIRECTORY
                .DIRECTORY_SEPARATOR.$manifestDocument['filename']
        );
    }

    /** @return array<string, mixed> */
    private function result(EoiTechnicalProposalDocument $document, string $applicantName): array
    {
        return [
            'document_id' => (string) $document->getKey(),
            'applicant_name' => $applicantName,
            'filename' => (string) $document->original_filename,
            'path' => (string) $document->file_path,
            'recognized' => false,
            'metadata_status' => self::METADATA_INVALID,
            'source_status' => self::SOURCE_NOT_CHECKED,
            'storage_status' => self::STORAGE_NOT_CHECKED,
            'repair_status' => self::REPAIR_NOT_REQUESTED,
            'message' => '',
            'ok' => false,
        ];
    }

    /** @param array<string, mixed> $inspection */
    private function repairFailure(array $inspection, string $message): array
    {
        return [
            ...$inspection,
            'repair_status' => self::REPAIR_FAILED,
            'message' => $message.' No database record was changed.',
            'ok' => false,
        ];
    }
}
