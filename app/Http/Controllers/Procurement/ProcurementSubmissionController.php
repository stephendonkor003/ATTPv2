<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProcurementSubmissionController extends Controller
{
    use GovernanceScope;

    /**
     * List all procurement submissions
     */
    public function index()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to submissions.');
        }

        $submissions = FormSubmission::with([
                'procurement',
                'form',
                'values'
            ])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereHas('procurement', function ($proc) use ($scopedNodeIds) {
                    $proc->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                });
            })
            ->latest()
            ->paginate(20);

        return view('procurement.procuresubmissions.index', compact('submissions'));
    }

    /**
     * View a single procurement submission
     */
    public function show(FormSubmission $submission)
    {
        $this->assertSubmissionInScope($submission);
        $submission->load([
            'procurement',
            'form',
            'values'
        ]);

        return view('procurement.procuresubmissions.show', compact('submission'));
    }

    /**
     * Download/stream a file value from a procurement submission (private storage).
     */
    public function downloadValue(Request $request, FormSubmission $submission, FormSubmissionValue $value)
    {
        $this->assertSubmissionInScope($submission);

        abort_unless($value->submission_id === $submission->id, 404);

        $path = (string) ($value->value ?? '');
        abort_if($path === '', 404, 'File not found.');

        // File fields store a path string. Ignore non-file JSON payloads.
        if (str_starts_with($path, '[') || str_starts_with($path, '{')) {
            abort(404, 'Not a file value.');
        }

        $privateDisk = Storage::disk('local');

        if (! $privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            // Best-effort migration from public -> private.
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'File missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('download')) {
            return $privateDisk->download($path, basename($path), $headers);
        }

        return $privateDisk->response($path, null, $headers);
    }
}
