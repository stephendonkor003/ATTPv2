<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Evaluation;
use App\Models\Procurement;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcurementEvaluationController extends Controller
{
    use ScopesAssignedPortfolios;

    public function create(Procurement $procurement)
    {
        $this->assertProcurementInEvaluationScope($procurement);

        $evaluationsQuery = Evaluation::query()
            ->where('status', 'active')
            ->whereIn('type', ['services', 'goods'])
            ->whereNotNull('portfolio_id')
            ->where('is_portfolio_custom', true)
            ->orderBy('name');

        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToEvaluations($evaluationsQuery);
        }

        $procurementPortfolioId = $this->portfolioIdForProcurement($procurement);
        if ($procurementPortfolioId) {
            $evaluationsQuery->where('portfolio_id', $procurementPortfolioId);
        }

        $evaluations = $evaluationsQuery->get();
        $users = User::orderBy('name')->get();
        $assignments = $procurement->evaluationAssignments()
            ->with(['evaluator', 'evaluation.portfolio:id,name'])
            ->latest()
            ->get();

        return view('evaluations.assign', compact(
            'procurement',
            'evaluations',
            'users',
            'assignments'
        ));
    }

    public function store(Request $request, Procurement $procurement)
    {
        $this->assertProcurementInEvaluationScope($procurement);

        $request->validate([
            'evaluation_id' => 'required|exists:evaluations,id',
        ]);

        $evaluation = Evaluation::query()
            ->whereKey($request->evaluation_id)
            ->where('status', 'active')
            ->whereIn('type', ['services', 'goods'])
            ->whereNotNull('portfolio_id')
            ->where('is_portfolio_custom', true)
            ->firstOrFail();

        $procurementPortfolioId = $this->portfolioIdForProcurement($procurement);
        if ($procurementPortfolioId) {
            abort_unless(
                (string) $evaluation->portfolio_id === (string) $procurementPortfolioId,
                422,
                'Select an evaluation template from the same portfolio as this procurement.'
            );
        }

        if ($this->userHasAssignedPortfolioScope()) {
            abort_unless(
                $this->evaluationIsInAssignedPortfolio($evaluation),
                403,
                'This evaluation template is not assigned to your portfolio.'
            );
        }

        $existingLink = DB::table('procurement_evaluations')
            ->where('procurement_id', $procurement->id)
            ->first();

        if ($existingLink) {
            DB::table('procurement_evaluations')
                ->where('id', $existingLink->id)
                ->update([
                    'evaluation_id' => $request->evaluation_id,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('procurement_evaluations')->insert([
                'id' => (string) Str::uuid(),
                'procurement_id' => $procurement->id,
                'evaluation_id' => $request->evaluation_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()
            ->route('procurements.show', $procurement)
            ->with('success', 'Evaluation assigned to procurement.');
    }

    private function assertProcurementInEvaluationScope(Procurement $procurement): void
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
