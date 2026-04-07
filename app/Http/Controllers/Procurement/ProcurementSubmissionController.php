<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Services\ProcurementSubmissionScreeningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProcurementSubmissionController extends Controller
{
    use GovernanceScope;

    /**
     * List all procurement submissions
     */
    public function index(ProcurementSubmissionScreeningService $screeningService)
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to submissions.');
        }

        $submissions = $this->submissionsQuery($scopedNodeIds)
            ->latest()
            ->paginate(20);

        return view('procurement.procuresubmissions.index', [
            'submissions' => $submissions,
            'screeningConfigured' => $screeningService->isConfigured(),
        ]);
    }

    /**
     * View a single procurement submission
     */
    public function show(FormSubmission $submission, ProcurementSubmissionScreeningService $screeningService)
    {
        $this->assertSubmissionInScope($submission);
        $submission->load([
            'procurement',
            'form.fields',
            'values',
            'screening',
        ]);

        return view('procurement.procuresubmissions.show', [
            'submission' => $submission,
            'screeningConfigured' => $screeningService->isConfigured(),
        ]);
    }

    public function screen(Request $request, FormSubmission $submission, ProcurementSubmissionScreeningService $screeningService)
    {
        $this->assertSubmissionInScope($submission);

        if (! $screeningService->isConfigured()) {
            return back()->with('error', 'International screening is not configured.');
        }

        $screening = $screeningService->screenSubmission(
            $submission->loadMissing(['values', 'submitter']),
            $request->user(),
            'manual'
        );

        if ($screening->request_status === 'error') {
            return back()->with('error', $screening->error_message ?: 'International screening failed.');
        }

        return back()->with(
            'success',
            sprintf(
                'International screening completed for %s. Risk level: %s.',
                $screening->entity_name ?: $submission->procurement_submission_code,
                strtoupper((string) $screening->risk_level)
            )
        );
    }

    public function screenAll(Request $request, ProcurementSubmissionScreeningService $screeningService)
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to submissions.');
        }

        if (! $screeningService->isConfigured()) {
            return back()->with('error', 'International screening is not configured.');
        }

        $submissions = FormSubmission::with(['values', 'submitter'])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereHas('procurement', function ($proc) use ($scopedNodeIds) {
                    $proc->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                });
            })
            ->latest()
            ->get();

        if ($submissions->isEmpty()) {
            return back()->with('error', 'No submissions were available for international screening.');
        }

        $summary = $screeningService->screenSubmissions($submissions, $request->user(), 'bulk');

        return back()->with(
            'success',
            sprintf(
                'International screening finished. %d checked, %d failed, %d skipped.',
                $summary['checked'],
                $summary['failed'],
                $summary['skipped']
            )
        );
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

    private function submissionsQuery(?array $scopedNodeIds)
    {
        return FormSubmission::with([
            'procurement',
            'form',
            'screening',
            'values' => function ($query) {
                $query->whereIn('field_key', ['official_name', 'official_email']);
            },
        ])->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
            $query->whereHas('procurement', function ($proc) use ($scopedNodeIds) {
                $proc->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            });
        });
    }
}
