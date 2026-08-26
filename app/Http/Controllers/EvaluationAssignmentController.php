<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Mail\EvaluationAssigned;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\Sector;
use App\Support\ProcurementReviewAssignees;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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

        $exists = EvaluationAssignment::where([
            'evaluation_id' => $validated['evaluation_id'],
            'procurement_id' => $validated['procurement_id'],
            'user_id' => $validated['user_id'],
            'form_submission_id' => $validated['assignment_type'] === 'submission'
                ? $validated['submission_id']
                : null,
        ])->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'This user is already assigned as an evaluator.');
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
        }

        EvaluationAssignment::create([
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

        if ($assignment->status === 'submitted') {
            return back()->with([
                'error' => 'Cannot remove evaluator after submission.',
                'open_procurement_id' => $assignment->procurement_id,
            ]);
        }

        $procurementId = $assignment->procurement_id;
        $assignment->delete();

        return back()->with([
            'success' => 'Evaluator removed successfully.',
            'open_procurement_id' => $procurementId,
        ]);
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
