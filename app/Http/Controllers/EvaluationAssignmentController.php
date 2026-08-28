<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Mail\EvaluationAssigned;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\Sector;
use App\Services\EoiQualificationService;
use App\Support\ProcurementReviewAssignees;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EvaluationAssignmentController extends Controller
{
    use ScopesAssignedPortfolios;

    public function hub()
    {
        $procurementQuery = Procurement::with([
            'evaluationAssignments.evaluator',
            'evaluationAssignments.evaluation.portfolio:id,name',
            'evaluationAssignments.submission',
            'submissions',
        ])
            ->orderBy('created_at', 'desc');

        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToProcurements($procurementQuery);
        }

        $procurements = $procurementQuery->get();

        $evaluationQuery = Evaluation::query()
            ->with('portfolio:id,name')
            ->where('status', 'active')
            ->whereIn('type', Evaluation::MANAGED_TYPES)
            ->whereNotNull('portfolio_id')
            ->where('is_portfolio_custom', true)
            ->orderBy('name');

        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToEvaluations($evaluationQuery);
        }

        $evaluations = $evaluationQuery->get();
        $evaluationsByPortfolioId = $evaluations->groupBy(fn (Evaluation $evaluation) => (string) $evaluation->portfolio_id);
        $procurementPortfolioIds = $this->portfolioIdsByProcurement($procurements);

        $evaluators = ProcurementReviewAssignees::query()
            ->orderBy('name')
            ->get();

        return view('evaluations.assign-hub', compact(
            'procurements',
            'evaluations',
            'evaluationsByPortfolioId',
            'procurementPortfolioIds',
            'evaluators'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'evaluation_id' => 'required|exists:evaluations,id',
            'procurement_id' => [
                'required',
                Rule::exists('procurements', 'id')->whereNull('deleted_at'),
            ],
            'user_id' => [
                'required',
                'uuid',
                ProcurementReviewAssignees::existsRule(),
            ],
            'assignment_type' => 'required|in:procurement,submission',
            'submission_id' => 'required_if:assignment_type,submission|nullable|exists:form_submissions,id',
        ], [
            'user_id.exists' => ProcurementReviewAssignees::INELIGIBLE_MESSAGE,
        ]);

        $evaluator = ProcurementReviewAssignees::query()
            ->findOrFail($validated['user_id']);
        $procurement = Procurement::findOrFail($validated['procurement_id']);
        $this->assertProcurementManageable($procurement);

        $evaluation = Evaluation::query()
            ->whereKey($validated['evaluation_id'])
            ->where('status', 'active')
            ->whereIn('type', Evaluation::MANAGED_TYPES)
            ->whereNotNull('portfolio_id')
            ->where('is_portfolio_custom', true)
            ->firstOrFail();
        $this->assertEvaluationSelectableForProcurement($evaluation, $procurement);

        if ($evaluation->status === 'close') {
            return back()
                ->withInput()
                ->with('error', 'Cannot assign evaluators to a closed evaluation.');
        }

        $submission = null;
        if ($validated['assignment_type'] === 'submission') {
            $submission = FormSubmission::where('id', $validated['submission_id'])
                ->where('procurement_id', $validated['procurement_id'])
                ->first();

            if (! $submission) {
                return back()
                    ->withInput()
                    ->with('error', 'Selected submission does not belong to this procurement.');
            }

            if (! $submission->isAvailableForEvaluation()) {
                return back()
                    ->withInput()
                    ->with('error', 'The selected application is not eligible for further evaluation.');
            }

            if (! $evaluation->isEoi()
                && $submission->status === FormSubmission::STATUS_EOI_EVALUATION) {
                return back()
                    ->withInput()
                    ->with('error', 'Complete the applicant\'s EOI panel before assigning Technical Evaluation.');
            }
        }

        $assignment = DB::transaction(function () use ($procurement, $validated): ?EvaluationAssignment {
            // Lock the shared procurement row so concurrent assignment requests
            // cannot both pass the overlap check before either insert is visible.
            Procurement::query()
                ->whereKey($procurement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existingCoverage = EvaluationAssignment::query()
                ->where('evaluation_id', $validated['evaluation_id'])
                ->where('procurement_id', $validated['procurement_id'])
                ->where('user_id', $validated['user_id']);

            if ($validated['assignment_type'] === 'submission') {
                $existingCoverage->where(function ($query) use ($validated): void {
                    $query->whereNull('form_submission_id')
                        ->orWhere('form_submission_id', $validated['submission_id']);
                });
            }

            if ($existingCoverage->exists()) {
                return null;
            }

            return EvaluationAssignment::create([
                'evaluation_id' => $validated['evaluation_id'],
                'procurement_id' => $validated['procurement_id'],
                'form_submission_id' => $validated['assignment_type'] === 'submission'
                    ? $validated['submission_id']
                    : null,
                'user_id' => $validated['user_id'],
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
                'status' => 'assigned',
            ]);
        });

        if (! $assignment) {
            return back()
                ->withInput()
                ->with('error', 'This user is already assigned as an evaluator for the selected procurement or applicant.');
        }

        if ($evaluation->isEoi()) {
            $qualificationService = app(EoiQualificationService::class);

            if ($submission) {
                $qualificationService->synchronizeApplicantStage($submission);
            } else {
                $qualificationService->synchronizeProcurementStages($procurement);
            }
        }

        if ($evaluator?->email) {
            Mail::to($evaluator->email)->send(
                new EvaluationAssigned($evaluator, $evaluation, $procurement, $submission)
            );
        }

        return back()->with([
            'success' => 'Evaluator assigned successfully.',
            'open_procurement_id' => $procurement->id,
        ]);
    }

    public function destroy(EvaluationAssignment $assignment)
    {
        $this->assertAssignmentManageable($assignment);

        $assignment->loadMissing(['evaluation', 'procurement', 'submission']);

        $procurementId = $assignment->procurement_id;
        $isEoi = $assignment->evaluation?->isEoi() ?? false;
        $procurement = $assignment->procurement;
        $submission = $assignment->submission;

        $removal = DB::transaction(function () use ($assignment): array {
            $lockedAssignment = EvaluationAssignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $remainingCoverage = EvaluationAssignment::query()
                ->where('evaluation_id', $lockedAssignment->evaluation_id)
                ->where('procurement_id', $lockedAssignment->procurement_id)
                ->where('user_id', $lockedAssignment->user_id)
                ->where('id', '<>', $lockedAssignment->getKey())
                ->lockForUpdate()
                ->get(['id', 'form_submission_id']);

            $uncoveredSubmissions = $this->evaluationSubmissionsForAssignment($lockedAssignment)
                ->lockForUpdate()
                ->get()
                ->reject(function (EvaluationSubmission $record) use ($remainingCoverage): bool {
                    return $remainingCoverage->contains(
                        fn (EvaluationAssignment $remaining): bool => blank($remaining->form_submission_id)
                            || (string) $remaining->form_submission_id === (string) $record->form_submission_id
                    );
                })
                ->values();

            if ($uncoveredSubmissions->contains(
                fn (EvaluationSubmission $record): bool => filled($record->submitted_at)
            )) {
                return [
                    'blocked' => true,
                    'drafts_removed' => 0,
                    'draft_paths' => [],
                ];
            }

            $draftPaths = [];

            foreach ($uncoveredSubmissions as $draft) {
                if (filled($draft->video_path)) {
                    $draftPaths[] = (string) $draft->video_path;
                }

                $draft->criteriaScores()->delete();
                $draft->sectionScores()->delete();
                $draft->delete();
            }

            $lockedAssignment->delete();

            return [
                'blocked' => false,
                'drafts_removed' => $uncoveredSubmissions->count(),
                'draft_paths' => $draftPaths,
            ];
        });

        if ($removal['blocked']) {
            return back()->with([
                'error' => 'Cannot remove this evaluator because a submitted evaluation would be left without an active assignment.',
                'open_procurement_id' => $procurementId,
            ]);
        }

        foreach ($removal['draft_paths'] as $draftPath) {
            Storage::disk('local')->delete($draftPath);
            Storage::disk('public')->delete($draftPath);
        }

        if ($isEoi && $procurement) {
            $qualificationService = app(EoiQualificationService::class);

            if ($submission) {
                $qualificationService->synchronizeApplicantStage($submission);
            } else {
                $qualificationService->synchronizeProcurementStages($procurement);
            }
        }

        $success = 'Evaluator removed successfully.';
        if ($removal['drafts_removed'] > 0) {
            $success .= ' '.number_format($removal['drafts_removed']).' abandoned draft evaluation(s) were also removed.';
        }

        return back()->with([
            'success' => $success,
            'open_procurement_id' => $procurementId,
        ]);
    }

    private function evaluationSubmissionsForAssignment(EvaluationAssignment $assignment): Builder
    {
        return EvaluationSubmission::query()
            ->where('evaluation_id', $assignment->evaluation_id)
            ->where('procurement_id', $assignment->procurement_id)
            ->where('evaluator_id', $assignment->user_id)
            ->when(
                filled($assignment->form_submission_id),
                fn ($query) => $query->where('form_submission_id', $assignment->form_submission_id)
            );
    }

    private function assertProcurementManageable(Procurement $procurement): void
    {
        if (! $this->userHasAssignedPortfolioScope()) {
            return;
        }

        abort_unless(
            $this->procurementIsInAssignedPortfolio($procurement),
            403,
            'This procurement is not assigned to your portfolio.'
        );
    }

    private function assertAssignmentManageable(EvaluationAssignment $assignment): void
    {
        if (! $this->userHasAssignedPortfolioScope()) {
            return;
        }

        abort_unless(
            $this->evaluationAssignmentIsInAssignedPortfolio($assignment),
            403,
            'This evaluation assignment is not assigned to your portfolio.'
        );
    }

    private function assertEvaluationSelectableForProcurement(Evaluation $evaluation, Procurement $procurement): void
    {
        if ($this->userHasAssignedPortfolioScope()) {
            abort_unless(
                $this->evaluationIsInAssignedPortfolio($evaluation),
                403,
                'This evaluation template is not assigned to your portfolio.'
            );
        }

        $procurementPortfolioId = $this->portfolioIdForProcurement($procurement);
        if (! $procurementPortfolioId) {
            return;
        }

        abort_unless(
            (string) $evaluation->portfolio_id === (string) $procurementPortfolioId,
            422,
            'Select an evaluation template from the same portfolio as this procurement.'
        );
    }

    private function portfolioIdsByProcurement($procurements): array
    {
        $nodeIds = $procurements
            ->pluck('governance_node_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($nodeIds->isEmpty()) {
            return [];
        }

        $portfolioByNodeId = Sector::query()
            ->whereIn('governance_node_id', $nodeIds->all())
            ->pluck('id', 'governance_node_id')
            ->mapWithKeys(fn ($id, $nodeId) => [(string) $nodeId => (string) $id])
            ->all();

        return $procurements
            ->mapWithKeys(function (Procurement $procurement) use ($portfolioByNodeId) {
                return [
                    (string) $procurement->id => $procurement->governance_node_id
                        ? ($portfolioByNodeId[(string) $procurement->governance_node_id] ?? null)
                        : null,
                ];
            })
            ->all();
    }

    private function portfolioIdForProcurement(Procurement $procurement): ?string
    {
        if (! $procurement->governance_node_id) {
            return null;
        }

        $portfolioId = Sector::query()
            ->where('governance_node_id', $procurement->governance_node_id)
            ->value('id');

        return $portfolioId ? (string) $portfolioId : null;
    }
}
