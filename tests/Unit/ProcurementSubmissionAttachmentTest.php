<?php

function procurementSubmissionAttachmentSources(): array
{
    $root = dirname(__DIR__, 2);

    return [
        'controller' => file_get_contents(
            $root.'/app/Http/Controllers/Procurement/ProcurementSubmissionController.php'
        ),
        'view' => file_get_contents(
            $root.'/resources/views/procurement/procuresubmissions/show.blade.php'
        ),
    ];
}

it('logs missing and unreadable private attachments before returning not found', function () {
    $controller = procurementSubmissionAttachmentSources()['controller'];

    $missingLog = strpos(
        $controller,
        "Log::warning('Procurement submission attachment is unavailable on the private disk.'"
    );
    $missingAbort = strpos($controller, "abort(404, 'File missing on disk.');");
    $unreadableGuard = strpos(
        $controller,
        'if (is_link($absolutePath) || ! is_file($absolutePath) || ! is_readable($absolutePath))'
    );
    $unreadableLog = strpos(
        $controller,
        "Log::warning('Procurement submission attachment exists but is not a readable regular file.'"
    );
    $unreadableAbort = strpos($controller, "abort(404, 'File is unavailable.');");

    expect($controller)
        ->toContain('use Illuminate\\Support\\Facades\\Log;')
        ->toContain("'submission_id' => \$submission->id")
        ->toContain("'value_id' => \$value->id")
        ->toContain("'storage_path' => \$path")
        ->toContain("'absolute_path' => \$privateDisk->path(\$path)")
        ->toContain("'disk_root' => config('filesystems.disks.local.root')")
        ->and($missingLog)->not->toBeFalse()
        ->and($missingAbort)->not->toBeFalse()
        ->and($unreadableGuard)->not->toBeFalse()
        ->and($unreadableLog)->not->toBeFalse()
        ->and($unreadableAbort)->not->toBeFalse();

    expect($missingLog)->toBeLessThan($missingAbort)
        ->and($unreadableGuard)->toBeLessThan($unreadableLog)
        ->and($unreadableLog)->toBeLessThan($unreadableAbort);
});

it('downloads ZIP packages with a friendly name while allowing inline PDF viewing', function () {
    $controller = procurementSubmissionAttachmentSources()['controller'];

    expect($controller)
        ->toContain("'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0'")
        ->toContain("'X-Content-Type-Options' => 'nosniff'")
        ->toContain('$extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));')
        ->toContain("Str::slug(\$submission->procurement_submission_code ?: 'procurement-submission')")
        ->toContain(".(\$extension !== '' ? '.'.\$extension : '')")
        ->toContain("if (\$request->boolean('download') || \$extension === 'zip')")
        ->toContain('return $privateDisk->download($path, $downloadName, $headers);')
        ->toContain('return $privateDisk->response($path, $downloadName, $headers);');
});

it('renders relative format-aware document links for PDFs and ZIP packages', function () {
    $view = procurementSubmissionAttachmentSources()['view'];

    expect($view)
        ->toContain("Str::lower(pathinfo((string) \$value, PATHINFO_EXTENSION)) === 'zip'")
        ->toContain("...(\$isDocumentPackage ? ['download' => 1] : [])")
        ->toContain('], false);')
        ->toContain('@unless ($isDocumentPackage) target="_blank" rel="noopener" @endunless')
        ->toContain("{{ \$isDocumentPackage ? 'Download document package' : 'View document' }}")
        ->toContain("{{ \$isDocumentPackage ? 'feather-download' : 'feather-external-link' }}")
        ->toContain('ZIP package containing all documents uploaded for this submission.');
});
