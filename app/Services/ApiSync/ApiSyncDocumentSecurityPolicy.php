<?php

namespace App\Services\ApiSync;

use App\Exceptions\ApiSyncDocumentHeldException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ApiSyncDocumentSecurityPolicy
{
    public function destinationPath(string $snapshotId, string $documentId): string
    {
        if (! Str::isUuid($snapshotId) || ! Str::isUuid($documentId)) {
            throw new RuntimeException('The immutable document snapshot identifiers are invalid.');
        }

        return $this->stagingPrefix().'/'.strtolower($snapshotId).'/'.strtolower($documentId).'.bin';
    }

    /**
     * Copy one approved source file to an opaque private snapshot location.
     * The caller supplies a final approval callback so a lifecycle or source
     * metadata change during the copy cannot cross the snapshot boundary.
     *
     * @param  (callable(): bool)|null  $sourceStillApproved
     * @return array{storage_disk:string,storage_path:string,display_filename:string,detected_mime:string,byte_size:int,sha256:string}
     */
    public function stage(
        string $sourcePath,
        string $destinationPath,
        string $displayFilename,
        ?int $expectedSize = null,
        ?string $expectedChecksum = null,
        ?callable $sourceStillApproved = null,
    ): array {
        $diskName = (string) config('api_sync_documents.disk', 'api_sync_documents');
        $disk = Storage::disk($diskName);
        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('The API synchronization document disk is unavailable.');
        }

        $sourcePath = $this->safeRelativePath($sourcePath, 'invalid_source_path');
        $destinationPath = $this->safeRelativePath($destinationPath, 'invalid_staging_path');
        $displayFilename = $this->sanitizeFilename($displayFilename);
        $extension = strtolower((string) pathinfo($displayFilename, PATHINFO_EXTENSION));
        if (! in_array($extension, (array) config('api_sync_documents.allowed_extensions', []), true)) {
            throw $this->held('file_type_not_allowed', 'The approved file type is not eligible for AU-PReMIS transfer.');
        }

        $root = $this->canonicalRoot($disk);
        $sourceAbsolute = $disk->path($sourcePath);
        if (is_link($sourceAbsolute)) {
            throw $this->held('symbolic_link_rejected', 'The approved file is a symbolic link and cannot be transferred safely.');
        }
        $sourceReal = realpath($sourceAbsolute);
        if ($sourceReal === false || ! is_file($sourceReal) || ! is_readable($sourceReal)) {
            throw $this->held('source_missing', 'The approved file is missing or cannot be read from private storage.');
        }
        $this->assertInsideRoot($root, $sourceReal, 'invalid_source_path');

        $before = @stat($sourceReal);
        if (! is_array($before)) {
            throw $this->held('source_unreadable', 'The approved file metadata could not be read safely.');
        }
        $maximum = min(20 * 1_024 * 1_024, max(1_024, (int) config('api_sync_documents.maximum_file_bytes', 20 * 1_024 * 1_024)));
        $beforeSize = (int) ($before['size'] ?? -1);
        if ($beforeSize === 0) {
            throw $this->held('empty_file_not_allowed', 'Empty approved files are not eligible for AU-PReMIS transfer.');
        }
        if ($beforeSize < 0 || $beforeSize > $maximum) {
            throw $this->held('file_size_not_allowed', 'The approved file exceeds the 20 MiB transfer limit.');
        }
        if ($expectedSize !== null && $expectedSize >= 0 && $expectedSize !== $beforeSize) {
            throw $this->held('source_metadata_changed', 'The approved file size no longer matches its validated record.');
        }

        $destinationDirectory = dirname($destinationPath);
        if (! $disk->makeDirectory($destinationDirectory)) {
            throw new RuntimeException('The private API synchronization staging directory could not be created.');
        }
        $destinationAbsolute = $disk->path($destinationPath);
        $destinationParentReal = realpath(dirname($destinationAbsolute));
        if ($destinationParentReal === false) {
            throw new RuntimeException('The private API synchronization staging directory is unavailable.');
        }
        $this->assertInsideRoot($root, $destinationParentReal, 'invalid_staging_path');
        if (file_exists($destinationAbsolute) || is_link($destinationAbsolute)) {
            throw new RuntimeException('The immutable document destination already exists.');
        }

        $temporaryAbsolute = $destinationAbsolute.'.part.'.bin2hex(random_bytes(16));
        $source = @fopen($sourceReal, 'rb');
        $temporary = @fopen($temporaryAbsolute, 'x+b');
        if (! is_resource($source) || ! is_resource($temporary)) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($temporary)) {
                fclose($temporary);
            }
            @unlink($temporaryAbsolute);
            throw new RuntimeException('The approved file could not be copied into private snapshot storage.');
        }

        try {
            $openedBefore = fstat($source);
            if (! is_array($openedBefore) || ! $this->sameFileStat($before, $openedBefore)) {
                throw $this->held('source_changed_during_snapshot', 'The approved file changed while its immutable snapshot was starting.');
            }

            $hash = hash_init('sha256');
            $bytes = 0;
            while (! feof($source)) {
                $buffer = fread($source, 1_048_576);
                if ($buffer === false) {
                    throw $this->held('source_read_failed', 'The approved file could not be read completely.');
                }
                if ($buffer === '') {
                    continue;
                }
                $bytes += strlen($buffer);
                if ($bytes > $maximum) {
                    throw $this->held('file_size_not_allowed', 'The approved file exceeds the 20 MiB transfer limit.');
                }
                hash_update($hash, $buffer);
                $this->writeAll($temporary, $buffer);
            }

            if (! fflush($temporary)) {
                throw new RuntimeException('The immutable document snapshot could not be flushed to private storage.');
            }
            if (function_exists('fsync') && ! fsync($temporary)) {
                throw new RuntimeException('The immutable document snapshot could not be synchronized to private storage.');
            }

            $openedAfter = fstat($source);
            $pathAfter = @stat($sourceReal);
            if (! is_array($openedAfter)
                || ! is_array($pathAfter)
                || ! $this->sameFileStat($openedBefore, $openedAfter)
                || ! $this->sameFileStat($openedAfter, $pathAfter)
                || $bytes !== $beforeSize) {
                throw $this->held('source_changed_during_snapshot', 'The approved file changed while its immutable snapshot was being prepared.');
            }

            $checksum = hash_final($hash);
            if ($expectedChecksum !== null
                && (! preg_match('/^[a-f0-9]{64}$/D', strtolower($expectedChecksum))
                    || ! hash_equals(strtolower($expectedChecksum), $checksum))) {
                throw $this->held('source_checksum_mismatch', 'The approved file no longer matches its validated checksum.');
            }

            $detectedMime = $this->inspectContent($temporaryAbsolute, $extension);
            if ($sourceStillApproved !== null && ! $sourceStillApproved()) {
                throw $this->held('source_changed_during_snapshot', 'The document approval or source record changed while its immutable snapshot was being prepared.');
            }
        } catch (\Throwable $exception) {
            fclose($source);
            fclose($temporary);
            @unlink($temporaryAbsolute);
            throw $exception;
        }

        fclose($source);
        fclose($temporary);
        @chmod($temporaryAbsolute, 0600);
        if (! @rename($temporaryAbsolute, $destinationAbsolute)) {
            @unlink($temporaryAbsolute);
            throw new RuntimeException('The immutable document snapshot could not be committed atomically.');
        }
        @chmod($destinationAbsolute, 0400);

        return [
            'storage_disk' => $diskName,
            'storage_path' => $destinationPath,
            'display_filename' => $displayFilename,
            'detected_mime' => $detectedMime,
            'byte_size' => $bytes,
            'sha256' => $checksum,
        ];
    }

    public function removeStaged(string $relativePath): void
    {
        $relativePath = $this->safeRelativePath($relativePath, 'invalid_staging_path');
        $disk = Storage::disk((string) config('api_sync_documents.disk', 'api_sync_documents'));
        $root = $this->canonicalRoot($disk);
        $absolute = $disk->path($relativePath);
        $parent = realpath(dirname($absolute));
        if ($parent === false) {
            return;
        }
        $this->assertInsideRoot($root, $parent, 'invalid_staging_path');
        if (is_file($absolute) && ! is_link($absolute)) {
            @chmod($absolute, 0600);
            if (! @unlink($absolute)) {
                throw new RuntimeException('The private immutable document could not be removed.');
            }
        }
    }

    public function clearPreparingDestination(string $destinationPath): void
    {
        $destinationPath = $this->safeRelativePath($destinationPath, 'invalid_staging_path');
        $this->removeStaged($destinationPath);
        $disk = Storage::disk((string) config('api_sync_documents.disk', 'api_sync_documents'));
        $root = $this->canonicalRoot($disk);
        $absolute = $disk->path($destinationPath);
        $parent = realpath(dirname($absolute));
        if ($parent === false) {
            return;
        }
        $this->assertInsideRoot($root, $parent, 'invalid_staging_path');
        foreach (glob($absolute.'.part.*', GLOB_NOSORT) ?: [] as $partial) {
            $this->assertInsideRoot($root, $partial, 'invalid_staging_path');
            if (is_file($partial) && ! is_link($partial)) {
                @chmod($partial, 0600);
                if (! @unlink($partial)) {
                    throw new RuntimeException('An incomplete private document checkpoint could not be removed.');
                }
            }
        }
    }

    public function readStaged(string $relativePath, int $offset, int $length, int $expectedSize): string
    {
        if ($offset < 0 || $length < 1 || $expectedSize < 0 || $offset + $length > $expectedSize) {
            throw new RuntimeException('The immutable document byte range is invalid.');
        }
        $relativePath = $this->safeRelativePath($relativePath, 'invalid_staging_path');
        $disk = Storage::disk((string) config('api_sync_documents.disk', 'api_sync_documents'));
        $root = $this->canonicalRoot($disk);
        $absolute = $disk->path($relativePath);
        if (is_link($absolute)) {
            throw new RuntimeException('The immutable document staging object is invalid.');
        }
        $real = realpath($absolute);
        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            throw new RuntimeException('The immutable document staging object is unavailable.');
        }
        $this->assertInsideRoot($root, $real, 'invalid_staging_path');
        if (filesize($real) !== $expectedSize) {
            throw new RuntimeException('The immutable document staging object failed its size check.');
        }

        $handle = @fopen($real, 'rb');
        if (! is_resource($handle) || fseek($handle, $offset) !== 0) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('The immutable document byte range could not be opened.');
        }
        $bytes = '';
        while (strlen($bytes) < $length && ! feof($handle)) {
            $chunk = fread($handle, min(1_048_576, $length - strlen($bytes)));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $bytes .= $chunk;
        }
        fclose($handle);
        if (strlen($bytes) !== $length) {
            throw new RuntimeException('The immutable document byte range could not be read completely.');
        }

        return $bytes;
    }

    public function purgeSnapshot(string $snapshotId): void
    {
        if (! Str::isUuid($snapshotId)) {
            throw new RuntimeException('The immutable document snapshot identifier is invalid.');
        }
        $disk = Storage::disk((string) config('api_sync_documents.disk', 'api_sync_documents'));
        $root = $this->canonicalRoot($disk);
        $relative = $this->stagingPrefix().'/'.strtolower($snapshotId);
        $absolute = $disk->path($relative);
        $parent = realpath(dirname($absolute));
        if ($parent !== false) {
            $this->assertInsideRoot($root, $parent, 'invalid_staging_path');
        }
        if ($disk->directoryExists($relative) && ! $disk->deleteDirectory($relative)) {
            throw new RuntimeException('The immutable document snapshot directory could not be removed.');
        }
    }

    public function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', trim($filename)));
        $filename = preg_replace('/[\x00-\x1F\x7F]+/u', '', $filename) ?? '';
        $filename = trim($filename, " .\t\n\r\0\x0B");
        if ($filename === '') {
            $filename = 'approved-document';
        }

        return mb_substr($filename, 0, 240);
    }

    private function inspectContent(string $path, string $extension): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = strtolower((string) $finfo->file($path));

        return match ($extension) {
            'pdf' => $this->inspectPdf($path, $detected),
            'png' => $this->requireMime($detected, ['image/png'], 'image/png'),
            'jpg', 'jpeg' => $this->requireMime($detected, ['image/jpeg'], 'image/jpeg'),
            'txt' => $this->inspectText($path, $detected, 'text/plain'),
            'csv' => $this->inspectText($path, $detected, 'text/csv'),
            'docx', 'xlsx', 'pptx' => $this->inspectOfficeOpenXml($path, $extension, $detected),
            default => throw $this->held('file_type_not_allowed', 'The approved file type is not eligible for AU-PReMIS transfer.'),
        };
    }

    private function inspectPdf(string $path, string $detected): string
    {
        $this->requireMime($detected, ['application/pdf'], 'application/pdf');
        $handle = @fopen($path, 'rb');
        if (! is_resource($handle) || fread($handle, 5) !== '%PDF-') {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw $this->held('content_type_mismatch', 'The approved PDF did not pass content inspection.');
        }
        rewind($handle);
        $tail = '';
        while (! feof($handle)) {
            $chunk = fread($handle, 1_048_576);
            if ($chunk === false) {
                fclose($handle);
                throw $this->held('content_inspection_failed', 'The approved PDF could not be inspected safely.');
            }
            $window = $tail.$chunk;
            if (preg_match('/\/(?:Encrypt|JavaScript|JS\b|Launch\b|OpenAction\b|AA\b|AcroForm\b|XFA\b|SubmitForm\b|ImportData\b|EmbeddedFile\b|RichMedia\b)/', $window)) {
                fclose($handle);
                throw $this->held('active_or_encrypted_content_rejected', 'Encrypted, embedded, or active PDF content is not eligible for transfer.');
            }
            $tail = substr($window, -128);
        }
        fclose($handle);

        return 'application/pdf';
    }

    private function inspectText(string $path, string $detected, string $normalized): string
    {
        $this->requireMime($detected, ['text/plain', 'text/csv', 'application/csv'], $normalized);
        $handle = @fopen($path, 'rb');
        if (! is_resource($handle)) {
            throw $this->held('content_inspection_failed', 'The approved text document could not be inspected safely.');
        }
        while (! feof($handle)) {
            $chunk = fread($handle, 1_048_576);
            if ($chunk === false || str_contains($chunk, "\0")) {
                fclose($handle);
                throw $this->held('content_type_mismatch', 'The approved text document did not pass content inspection.');
            }
        }
        fclose($handle);

        return $normalized;
    }

    private function inspectOfficeOpenXml(string $path, string $extension, string $detected): string
    {
        $allowedMimes = [
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        $this->requireMime($detected, $allowedMimes, 'application/zip');
        if (! class_exists(ZipArchive::class)) {
            throw $this->held('content_inspection_unavailable', 'Office document inspection is unavailable on this deployment.');
        }

        $archive = new ZipArchive;
        if ($archive->open($path, ZipArchive::RDONLY) !== true || $archive->numFiles < 1 || $archive->numFiles > 5_000) {
            throw $this->held('office_package_rejected', 'The Office document package could not be inspected safely.');
        }

        try {
            $requiredPart = match ($extension) {
                'docx' => 'word/document.xml',
                'xlsx' => 'xl/workbook.xml',
                'pptx' => 'ppt/presentation.xml',
            };
            if ($archive->locateName('[Content_Types].xml', ZipArchive::FL_NOCASE) === false
                || $archive->locateName($requiredPart, ZipArchive::FL_NOCASE) === false) {
                throw $this->held('office_package_rejected', 'The Office document package is incomplete or does not match its filename.');
            }

            $inflated = 0;
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index, ZipArchive::FL_UNCHANGED);
                if (! is_array($stat) || ! is_string($stat['name'] ?? null)) {
                    throw $this->held('office_package_rejected', 'The Office document package contains an unreadable entry.');
                }
                $name = str_replace('\\', '/', $stat['name']);
                $lower = strtolower($name);
                if ($name === ''
                    || str_contains($name, "\0")
                    || str_starts_with($name, '/')
                    || preg_match('/^[a-z]:\//i', $name)
                    || in_array('..', explode('/', $name), true)
                    || preg_match('~(^|/)(?:activex|embeddings|oleobjects?|macrosheets?)(/|$)~i', $lower)
                    || preg_match('~(?:vbaproject\.bin|\.(?:exe|dll|com|scr|msi|js|vbs|cmd|bat|ps1|jar|zip|rar|7z|tar|gz))$~i', $lower)) {
                    throw $this->held('active_or_embedded_content_rejected', 'Macros, embedded objects, executables, and nested archives are not eligible for transfer.');
                }

                $size = (int) ($stat['size'] ?? 0);
                $compressed = (int) ($stat['comp_size'] ?? 0);
                $inflated += $size;
                if ($size < 0
                    || $inflated > 100 * 1_024 * 1_024
                    || ($compressed > 0 && $size > 1_048_576 && $size / $compressed > 100)) {
                    throw $this->held('office_package_expansion_rejected', 'The Office document package exceeds safe expansion limits.');
                }
            }

            $contentTypes = $archive->getFromName('[Content_Types].xml', 2_097_152);
            if (! is_string($contentTypes)
                || preg_match('/(?:macroEnabled|vba|activeX|oleObject)/i', $contentTypes)) {
                throw $this->held('active_or_embedded_content_rejected', 'Macro-enabled or active Office content is not eligible for transfer.');
            }

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = (string) $archive->getNameIndex($index);
                if (! str_ends_with(strtolower($name), '.rels')) {
                    continue;
                }
                $relationships = $archive->getFromIndex($index, 2_097_152);
                if (! is_string($relationships)
                    || preg_match('/TargetMode\s*=\s*["\']External["\']/i', $relationships)) {
                    throw $this->held('external_office_relationship_rejected', 'Office documents with external content relationships are not eligible for transfer.');
                }
            }
        } finally {
            $archive->close();
        }

        return match ($extension) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        };
    }

    /** @param list<string> $allowed */
    private function requireMime(string $detected, array $allowed, string $normalized): string
    {
        if (! in_array($detected, $allowed, true)) {
            throw $this->held('content_type_mismatch', 'The approved file content does not match its permitted filename type.');
        }

        return $normalized;
    }

    private function safeRelativePath(string $path, string $code): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === ''
            || str_contains($path, "\0")
            || str_starts_with($path, '/')
            || str_contains($path, '://')
            || preg_match('/^[a-z]:\//i', $path)) {
            throw $this->held($code, 'A private storage path failed the synchronization safety policy.');
        }
        $segments = explode('/', $path);
        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            throw $this->held($code, 'A private storage path failed the synchronization safety policy.');
        }

        return implode('/', array_values(array_filter($segments, static fn (string $segment): bool => $segment !== '')));
    }

    private function canonicalRoot(FilesystemAdapter $disk): string
    {
        $root = realpath($disk->path(''));
        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('The private API synchronization document disk is unavailable.');
        }

        return $root;
    }

    private function stagingPrefix(): string
    {
        $prefix = trim(str_replace('\\', '/', (string) config('api_sync_documents.staging_prefix', 'api-sync/v2-document-snapshots')), '/');
        if (! str_starts_with($prefix, 'api-sync/')
            || str_contains($prefix, '://')
            || in_array('..', explode('/', $prefix), true)) {
            throw new RuntimeException('The immutable API synchronization staging prefix is unsafe.');
        }

        return $prefix;
    }

    private function assertInsideRoot(string $root, string $candidate, string $code): void
    {
        $root = rtrim(str_replace('\\', '/', $root), '/').'/';
        $candidate = rtrim(str_replace('\\', '/', $candidate), '/');
        if (PHP_OS_FAMILY === 'Windows') {
            $root = strtolower($root);
            $candidate = strtolower($candidate);
        }
        if (! str_starts_with($candidate.'/', $root)) {
            throw $this->held($code, 'A private storage path escaped its approved synchronization boundary.');
        }
    }

    /** @param resource $stream */
    private function writeAll($stream, string $buffer): void
    {
        $offset = 0;
        $length = strlen($buffer);
        while ($offset < $length) {
            $written = fwrite($stream, substr($buffer, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('The immutable document snapshot could not be written completely.');
            }
            $offset += $written;
        }
    }

    /** @param array<string|int, mixed> $left @param array<string|int, mixed> $right */
    private function sameFileStat(array $left, array $right): bool
    {
        foreach (['dev', 'ino', 'size', 'mtime'] as $key) {
            if (isset($left[$key], $right[$key]) && (string) $left[$key] !== (string) $right[$key]) {
                return false;
            }
        }

        return true;
    }

    private function held(string $code, string $message): ApiSyncDocumentHeldException
    {
        return new ApiSyncDocumentHeldException($code, $message);
    }
}
