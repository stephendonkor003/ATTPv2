<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\Procurement;
use App\Models\ProcurementSubmissionScreening;
use App\Services\ProcurementSubmissionScreeningAutomation;
use App\Services\ProcurementSubmissionScreeningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcurementSubmissionController extends Controller
{
    use GovernanceScope;

    /**
     * List all procurement submissions
     */
    public function index(Request $request, ProcurementSubmissionScreeningService $screeningService)
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to submissions.');
        }

        $procurementGroups = $this->procurementGroupsQuery($scopedNodeIds)->get();
        $overview = [
            'procurements' => $procurementGroups->count(),
            'submissions' => (int) $procurementGroups->sum('submissions_count'),
            'screened' => (int) $procurementGroups->sum('screening_success_count'),
            'needs_attention' => (int) $procurementGroups->sum(
                fn (Procurement $procurement): int => max(
                    0,
                    (int) $procurement->submissions_count - (int) $procurement->screening_success_count,
                )
            ),
        ];

        $selectedProcurement = null;
        $submissions = collect();
        $statusDistribution = collect();

        if ($request->filled('procurement_id')) {
            $selectedProcurement = $this->resolveScopedProcurement(
                (string) $request->query('procurement_id'),
                $scopedNodeIds
            );

            $submissions = $this->submissionsQuery($scopedNodeIds)
                ->where('procurement_id', $selectedProcurement->id)
                ->orderByDesc('submitted_at')
                ->orderByDesc('created_at')
                ->get();

            $statusDistribution = $submissions
                ->countBy(fn (FormSubmission $submission): string => $submission->status ?: 'unknown')
                ->sortDesc();
        }

        return view('procurement.procuresubmissions.index', [
            'procurementGroups' => $procurementGroups,
            'selectedProcurement' => $selectedProcurement,
            'submissions' => $submissions,
            'overview' => $overview,
            'statusDistribution' => $statusDistribution,
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
            'submitter',
            'values',
            'screening.checker',
            'screening.reviewer',
        ]);

        return view('procurement.procuresubmissions.show', [
            'submission' => $submission,
            'screeningConfigured' => $screeningService->isConfigured(),
        ]);
    }

    public function screeningReport(
        Request $request,
        FormSubmission $submission,
        ProcurementSubmissionScreeningService $screeningService,
    ) {
        $this->authorizeScreeningOperation($request);
        $this->assertSubmissionInScope($submission);

        $submission->load([
            'procurement',
            'form',
            'submitter',
            'values',
            'screening.checker',
            'screening.reviewer',
        ]);

        return view('procurement.procuresubmissions.screening-report', [
            'submission' => $submission,
            'screeningConfigured' => $screeningService->isConfigured(),
        ]);
    }

    public function screen(
        Request $request,
        FormSubmission $submission,
        ProcurementSubmissionScreeningAutomation $screeningAutomation,
    ) {
        $this->authorizeScreeningOperation($request);
        $this->assertSubmissionInScope($submission);

        $result = $screeningAutomation->queueSubmission(
            $submission,
            $request->user()?->id,
            'manual',
            true,
        );

        [$key, $message] = match ($result) {
            ProcurementSubmissionScreeningAutomation::QUEUED => [
                'success',
                '3PAP screening was queued and will continue in the background.',
            ],
            ProcurementSubmissionScreeningAutomation::ALREADY_ACTIVE => [
                'success',
                '3PAP screening is already in progress for this applicant.',
            ],
            ProcurementSubmissionScreeningAutomation::NOT_CONFIGURED => [
                'error',
                'International screening is not configured.',
            ],
            default => [
                'error',
                'This submission is not currently eligible for international screening.',
            ],
        };

        return $this->redirectWithMessage(
            $request,
            $submission,
            $key,
            $message,
        );
    }

    public function saveScreeningDecision(Request $request, FormSubmission $submission)
    {
        $this->authorizeScreeningOperation($request);
        $this->assertSubmissionInScope($submission);

        $validated = $request->validate([
            'review_decision' => ['required', 'in:fit,not_fit'],
            'review_notes' => ['nullable', 'string', 'max:5000'],
            'screening_run_token' => ['required', 'uuid'],
        ]);

        $screening = $submission->screening()->first();
        if (! $screening || $screening->request_status !== ProcurementSubmissionScreening::STATUS_SUCCESS) {
            return redirect()
                ->route('procurement.submissions.screening.report', $submission)
                ->with('error', 'Wait for a successful international screening before recording a fit decision.');
        }

        if (! is_string($screening->run_token)
            || ! hash_equals($screening->run_token, $validated['screening_run_token'])) {
            return redirect()
                ->route('procurement.submissions.screening.report', $submission)
                ->with('error', 'The screening result changed while this report was open. Review the latest result before deciding.');
        }

        $updated = $submission->screening()
            ->whereKey($screening->getKey())
            ->where('request_status', ProcurementSubmissionScreening::STATUS_SUCCESS)
            ->where('run_token', $validated['screening_run_token'])
            ->update([
                'review_decision' => $validated['review_decision'],
                'review_notes' => $validated['review_notes'] ?: null,
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
            ]);

        if ($updated !== 1) {
            return redirect()
                ->route('procurement.submissions.screening.report', $submission)
                ->with('error', 'The screening result changed while this decision was being saved. Please review it again.');
        }

        return redirect()
            ->route('procurement.submissions.screening.report', $submission)
            ->with(
                'success',
                $validated['review_decision'] === 'fit'
                    ? 'Applicant marked as fit.'
                    : 'Applicant marked as not fit.'
            );
    }

    public function screenAll(
        Request $request,
        ProcurementSubmissionScreeningService $screeningService,
        ProcurementSubmissionScreeningAutomation $screeningAutomation,
    ) {
        $this->authorizeScreeningOperation($request);

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to submissions.');
        }

        if (! $screeningService->isConfigured()) {
            return back()->with('error', 'International screening is not configured.');
        }

        $selectedProcurement = $request->filled('procurement_id')
            ? $this->resolveScopedProcurement((string) $request->input('procurement_id'), $scopedNodeIds)
            : null;

        $submissions = $this->applySubmissionScope(
            FormSubmission::with(['values', 'submitter']),
            $scopedNodeIds
        )
            ->whereNotNull('procurement_id')
            ->whereNotNull('submitted_at')
            ->when(
                $selectedProcurement,
                fn ($query) => $query->where('procurement_id', $selectedProcurement->id)
            )
            ->latest()
            ->get();

        if ($submissions->isEmpty()) {
            return back()->with(
                'error',
                $selectedProcurement
                    ? 'No submissions were available for this procurement.'
                    : 'No submissions were available for international screening.'
            );
        }

        $summary = $screeningAutomation->queueMany(
            $submissions,
            $request->user()?->id,
            'bulk',
        );

        return back()->with(
            'success',
            sprintf(
                'International screening%s queued. %d newly queued, %d already in progress, %d already current, %d skipped.',
                $selectedProcurement ? ' for '.$selectedProcurement->title : '',
                $summary['queued'],
                $summary['active'],
                $summary['current'],
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

        if (! $privateDisk->exists($path)) {
            Log::warning('Procurement submission attachment is unavailable on the private disk.', [
                'submission_id' => $submission->id,
                'value_id' => $value->id,
                'storage_path' => $path,
                'absolute_path' => $privateDisk->path($path),
                'disk_root' => config('filesystems.disks.local.root'),
            ]);

            abort(404, 'File missing on disk.');
        }

        $absolutePath = $privateDisk->path($path);
        if (is_link($absolutePath) || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            Log::warning('Procurement submission attachment exists but is not a readable regular file.', [
                'submission_id' => $submission->id,
                'value_id' => $value->id,
                'storage_path' => $path,
                'absolute_path' => $absolutePath,
            ]);

            abort(404, 'File is unavailable.');
        }

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
        $downloadName = Str::slug($submission->procurement_submission_code ?: 'procurement-submission')
            .($extension !== '' ? '.'.$extension : '');

        // Browsers cannot display ZIP packages reliably. Always return those as downloads.
        if ($request->boolean('download') || $extension === 'zip') {
            return $privateDisk->download($path, $downloadName, $headers);
        }

        return $privateDisk->response($path, $downloadName, $headers);
    }

    private function submissionsQuery(?array $scopedNodeIds)
    {
        return $this->applySubmissionScope(FormSubmission::with([
            'procurement',
            'form',
            'submitter',
            'screening',
            'values' => function ($query) {
                $query->whereIn('field_key', ['official_name', 'official_email']);
            },
        ]), $scopedNodeIds);
    }

    private function authorizeScreeningOperation(Request $request): void
    {
        abort_unless(
            $request->user()?->can('forms.manage') === true,
            403,
            'You do not have permission to access 3PAP screening.',
        );
    }

    private function procurementGroupsQuery(?array $scopedNodeIds)
    {
        return $this->applyProcurementNodeScope(Procurement::query(), $scopedNodeIds)
            ->whereHas('submissions')
            ->withCount([
                'submissions',
                'submissions as screening_records_count' => fn ($query) => $query->whereHas('screening'),
                'submissions as screening_success_count' => fn ($query) => $query->whereHas(
                    'screening',
                    fn ($screening) => $screening->where('request_status', 'success')
                ),
                'submissions as screening_failed_count' => fn ($query) => $query->whereHas(
                    'screening',
                    fn ($screening) => $screening->where('request_status', 'error')
                ),
                'submissions as screening_active_count' => fn ($query) => $query->whereHas(
                    'screening',
                    fn ($screening) => $screening->whereIn(
                        'request_status',
                        ProcurementSubmissionScreening::ACTIVE_STATUSES,
                    )
                ),
                'submissions as screening_waiting_count' => fn ($query) => $query->whereHas(
                    'screening',
                    fn ($screening) => $screening->where(
                        'request_status',
                        ProcurementSubmissionScreening::STATUS_WAITING,
                    )
                ),
                'submissions as fit_count' => fn ($query) => $query->whereHas(
                    'screening',
                    fn ($screening) => $screening->where('review_decision', 'fit')
                ),
                'submissions as not_fit_count' => fn ($query) => $query->whereHas(
                    'screening',
                    fn ($screening) => $screening->where('review_decision', 'not_fit')
                ),
            ])
            ->withMax('submissions as latest_submission_at', 'submitted_at')
            ->orderByDesc('latest_submission_at')
            ->orderBy('title');
    }

    private function resolveScopedProcurement(string $procurementId, ?array $scopedNodeIds): Procurement
    {
        abort_unless(Str::isUuid($procurementId), 404);

        return $this->procurementGroupsQuery($scopedNodeIds)
            ->whereKey($procurementId)
            ->firstOrFail();
    }

    private function applySubmissionScope($query, ?array $scopedNodeIds)
    {
        return $query->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
            $query->whereHas('procurement', function ($proc) use ($scopedNodeIds) {
                $proc->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            });
        });
    }

    private function applyProcurementNodeScope($query, ?array $scopedNodeIds)
    {
        return $query->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
            $query->whereIn('governance_node_id', $scopedNodeIds)
                ->whereNotNull('governance_node_id');
        });
    }

    private function redirectWithMessage(Request $request, FormSubmission $submission, string $key, string $message)
    {
        if ($request->filled('redirect_to')) {
            return redirect()->to((string) $request->input('redirect_to'))->with($key, $message);
        }

        if ($request->boolean('to_report')) {
            return redirect()
                ->route('procurement.submissions.screening.report', $submission)
                ->with($key, $message);
        }

        return back()->with($key, $message);
    }
}
