<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Mail\EvaluationAssigned;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

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
            ->whereIn('type', ['services', 'goods'])
            ->whereNotNull('portfolio_id')
            ->where('is_portfolio_custom', true)
            ->orderBy('name');

        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToEvaluations($evaluationQuery);
        }

        $evaluations = $evaluationQuery->get();
        $evaluationsByPortfolioId = $evaluations->groupBy(fn (Evaluation $evaluation) => (string) $evaluation->portfolio_id);
        $procurementPortfolioIds = $this->portfolioIdsByProcurement($procurements);

        $evaluators = User::orderBy('name')->get();

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
        $request->validate([
            'evaluation_id' => 'required|exists:evaluations,id',
            'procurement_id' => 'required|exists:procurements,id',
            'user_id' => 'required|exists:users,id',
            'assignment_type' => 'required|in:procurement,submission',
            'submission_id' => 'required_if:assignment_type,submission|nullable|exists:form_submissions,id',
        ]);

        $procurement = Procurement::findOrFail($request->procurement_id);
        $this->assertProcurementManageable($procurement);

        $evaluation = Evaluation::query()
            ->whereKey($request->evaluation_id)
            ->where('status', 'active')
            ->whereIn('type', ['services', 'goods'])
            ->whereNotNull('portfolio_id')
            ->where('is_portfolio_custom', true)
            ->firstOrFail();
        $this->assertEvaluationSelectableForProcurement($evaluation, $procurement);

        if ($evaluation->status === 'close') {
            return back()->with('error', 'Cannot assign evaluators to a closed evaluation.');
        }

        $exists = EvaluationAssignment::where([
            'evaluation_id' => $request->evaluation_id,
            'procurement_id' => $request->procurement_id,
            'user_id' => $request->user_id,
            'form_submission_id' => $request->assignment_type === 'submission'
                ? $request->submission_id
                : null,
        ])->exists();

        if ($exists) {
            return back()->with('error', 'This user is already assigned as an evaluator.');
        }

        $submission = null;
        if ($request->assignment_type === 'submission') {
            $submission = FormSubmission::where('id', $request->submission_id)
                ->where('procurement_id', $request->procurement_id)
                ->first();

            if (! $submission) {
                return back()->with('error', 'Selected submission does not belong to this procurement.');
            }
        }

        EvaluationAssignment::create([
            'evaluation_id' => $request->evaluation_id,
            'procurement_id' => $request->procurement_id,
            'form_submission_id' => $request->assignment_type === 'submission'
                ? $request->submission_id
                : null,
            'user_id' => $request->user_id,
            'assigned_by' => Auth::id(),
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        $evaluator = User::find($request->user_id);
        if ($evaluator?->email) {
            Mail::to($evaluator->email)->send(
                new EvaluationAssigned($evaluator, $evaluation, $procurement, $submission)
            );
        }

        return back()->with('success', 'Evaluator assigned successfully.');
    }

    public function destroy(EvaluationAssignment $assignment)
    {
        $this->assertAssignmentManageable($assignment);

        if ($assignment->status === 'submitted') {
            return back()->with('error', 'Cannot remove evaluator after submission.');
        }

        $assignment->delete();

        return back()->with('success', 'Evaluator removed successfully.');
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
