<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Mail\EvaluationAssigned;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalRound;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\Sector;
use App\Services\EoiQualificationService;
use App\Services\EvaluationAssignmentTargetResolver;
use App\Support\ProcurementReviewAssignees;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EvaluationAssignmentController extends Controller
{
    use ScopesAssignedPortfolios;

    public function hub()
    {
        $procurementQuery = Procurement::with([
            'evaluationAssignments.evaluator',
            'evaluationAssignments.evaluation.portfolio:id,name',
            'evaluationAssignments.submission',
            'evaluationAssignments.technicalProposalRound:id,procurement_id,round_number,title,status',
            'submissions.submitter:id,name,email',
            'submissions.values',
            'technicalProposalRounds.candidates:id,round_id,status',
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

        $targetResolver = app(EvaluationAssignmentTargetResolver::class);
        $assignmentContexts = $procurements->mapWithKeys(function (Procurement $procurement) use ($targetResolver): array {
            $proposalContext = $targetResolver->technicalProposalContext($procurement);
            $round = $proposalContext['round'];
            $technicalTargets = collect($proposalContext['targets'] ?? [])->map(function ($target) use ($round): ?array {
                if (! $target instanceof FormSubmission || ! $round) {
                    return null;
                }

                $candidate = $target->technicalProposalCandidates->first();

                return $candidate ? [
                    'applicant' => $target,
                    'candidate' => $candidate,
                    'latest_submission' => $candidate->latestSubmission,
                ] : null;
            })->filter()->values();

            return [(string) $procurement->getKey() => [
                'application_submission_ids' => $procurement->submissions
                    ->filter(fn (FormSubmission $submission): bool => $submission->isAvailableForEvaluation())
                    ->pluck('id')
                    ->map(fn ($id): string => (string) $id)
                    ->all(),
                'technical_round' => $round,
                'technical_candidates' => $technicalTargets,
                'technical_submission_ids' => $technicalTargets
                    ->pluck('applicant.id')
                    ->map(fn ($id): string => (string) $id)
                    ->all(),
                'eligible_count' => $technicalTargets->count(),
                'status_counts' => $round
                    ? $round->candidates->countBy('status')->all()
                    : [],
            ]];
        })->all();

        return view('evaluations.assign-hub', compact(
            'procurements',
            'evaluations',
            'evaluationsByPortfolioId',
            'procurementPortfolioIds',
            'evaluators',
            'assignmentContexts'
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
            'assignment_type' => [
                'required',
                Rule::in([
                    'procurement',
                    'submission',
                    'technical_proposal_procurement',
                    'technical_proposal_submission',
                ]),
            ],
            'submission_id' => [
                Rule::requiredIf(fn (): bool => in_array($request->input('assignment_type'), [
                    'submission',
                    'technical_proposal_submission',
                ], true)),
                'nullable',
                'exists:form_submissions,id',
            ],
            'technical_proposal_round_id' => [
                Rule::requiredIf(fn (): bool => str_starts_with(
                    (string) $request->input('assignment_type'),
                    'technical_proposal_'
                )),
                'nullable',
                'uuid',
                Rule::exists('eoi_technical_proposal_rounds', 'id'),
            ],
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

        $workflowStage = str_starts_with($validated['assignment_type'], 'technical_proposal_')
            ? EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL
            : EvaluationAssignment::STAGE_APPLICATION;
        $isSubmissionAssignment = in_array($validated['assignment_type'], [
            'submission',
            'technical_proposal_submission',
        ], true);
        $targetResolver = app(EvaluationAssignmentTargetResolver::class);
        $technicalProposalRound = $workflowStage === EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL
            ? EoiTechnicalProposalRound::query()
                ->whereKey($validated['technical_proposal_round_id'] ?? null)
                ->where('procurement_id', $procurement->getKey())
                ->whereIn('status', [
                    EoiTechnicalProposalRound::STATUS_PUBLISHED,
                    EoiTechnicalProposalRound::STATUS_CLOSED,
                ])
                ->first()
            : null;

        if ($workflowStage === EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL && ! $technicalProposalRound) {
            return back()
                ->withInput()
                ->with('error', 'No published technical-proposal round is ready for evaluator assignment.');
        }

        if ($evaluation->status === 'close') {
            return back()
                ->withInput()
                ->with('error', 'Cannot assign evaluators to a closed evaluation.');
        }

        $submission = null;
        if ($isSubmissionAssignment) {
            $submission = FormSubmission::where('id', $validated['submission_id'])
                ->where('procurement_id', $validated['procurement_id'])
                ->first();

            if (! $submission) {
                return back()
                    ->withInput()
                    ->with('error', 'Selected submission does not belong to this procurement.');
            }

            if (! $targetResolver->isEligible(
                $procurement,
                $evaluation,
                $submission,
                $workflowStage,
                $technicalProposalRound
            )) {
                return back()
                    ->withInput()
                    ->with('error', $workflowStage === EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL
                        ? 'Only applicants qualified in the selected technical-proposal round can be assigned.'
                        : 'The selected application is not eligible for this evaluation assignment.');
            }
        }

        if ($workflowStage === EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL
            && ! $isSubmissionAssignment
            && $targetResolver->eligibleTargets(
                $procurement,
                $evaluation,
                $workflowStage,
                $technicalProposalRound
            )->isEmpty()) {
            return back()
                ->withInput()
                ->with('error', 'Complete proposal compliance review first. No second-round qualified applicants are available.');
        }

        $assignment = DB::transaction(function () use (
            $procurement,
            $evaluation,
            $validated,
            $workflowStage,
            $isSubmissionAssignment,
            $technicalProposalRound,
            $targetResolver,
            $submission
        ): ?EvaluationAssignment {
            // Lock the shared procurement row so concurrent assignment requests
            // cannot both pass the overlap check before either insert is visible.
            Procurement::query()
                ->whereKey($procurement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($technicalProposalRound) {
                $lockedRound = EoiTechnicalProposalRound::query()
                    ->whereKey($technicalProposalRound->getKey())
                    ->where('procurement_id', $procurement->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($lockedRound->status, [
                    EoiTechnicalProposalRound::STATUS_PUBLISHED,
                    EoiTechnicalProposalRound::STATUS_CLOSED,
                ], true)) {
                    return null;
                }

                if ($submission) {
                    $candidate = EoiTechnicalProposalCandidate::query()
                        ->where('round_id', $lockedRound->getKey())
                        ->where('form_submission_id', $submission->getKey())
                        ->with(['applicant', 'latestSubmission.documents'])
                        ->lockForUpdate()
                        ->first();

                    if (! $candidate || ! $targetResolver->technicalCandidateIsEligible(
                        $candidate,
                        $lockedRound,
                        $procurement
                    )) {
                        throw ValidationException::withMessages([
                            'submission_id' => 'Only an applicant in the qualified technical-proposal shortlist can be assigned.',
                        ]);
                    }

                    $targetResolver->assertEligible(
                        $procurement,
                        $evaluation,
                        $submission,
                        $workflowStage,
                        $lockedRound
                    );
                }
            }

            $existingCoverage = EvaluationAssignment::query()
                ->where('evaluation_id', $validated['evaluation_id'])
                ->where('procurement_id', $validated['procurement_id'])
                ->where('user_id', $validated['user_id'])
                ->where('workflow_stage', $workflowStage)
                ->when(
                    $technicalProposalRound,
                    fn ($query) => $query->where('technical_proposal_round_id', $technicalProposalRound->getKey()),
                    fn ($query) => $query->whereNull('technical_proposal_round_id')
                );

            if ($isSubmissionAssignment) {
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
                'form_submission_id' => $isSubmissionAssignment
                    ? $validated['submission_id']
                    : null,
                'workflow_stage' => $workflowStage,
                'technical_proposal_round_id' => $technicalProposalRound?->getKey(),
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

        if ($evaluation->isEoi() && $workflowStage === EvaluationAssignment::STAGE_APPLICATION) {
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
        $isEoi = ($assignment->evaluation?->isEoi() ?? false)
            && ! $assignment->isTechnicalProposal();
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
                ->where('workflow_stage', $lockedAssignment->workflow_stage ?: EvaluationAssignment::STAGE_APPLICATION)
                ->when(
                    $lockedAssignment->technical_proposal_round_id,
                    fn ($query) => $query->where('technical_proposal_round_id', $lockedAssignment->technical_proposal_round_id),
                    fn ($query) => $query->whereNull('technical_proposal_round_id')
                )
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
            ->where(function (Builder $query) use ($assignment): void {
                $query->where('evaluation_assignment_id', $assignment->getKey());

                if (! $assignment->isTechnicalProposal()) {
                    $query->orWhere(function (Builder $legacy) use ($assignment): void {
                        $legacy->whereNull('evaluation_assignment_id')
                            ->where('evaluation_id', $assignment->evaluation_id)
                            ->where('procurement_id', $assignment->procurement_id)
                            ->where('evaluator_id', $assignment->user_id)
                            ->when(
                                filled($assignment->form_submission_id),
                                fn ($scope) => $scope->where('form_submission_id', $assignment->form_submission_id)
                            );
                    });
                }
            });
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
