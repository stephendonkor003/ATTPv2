<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\PrescreeningEvaluation;
use App\Models\PrescreeningResult;
use App\Models\PrescreeningTemplate;
use App\Models\Procurement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;

class PrescreeningReportController extends Controller
{
    use GovernanceScope;

    public function index()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to prescreening reports.');
        }

        $procurements = Procurement::orderBy('title')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->get();
        $submissions = FormSubmission::with('procurement')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereHas('procurement', function ($proc) use ($scopedNodeIds) {
                    $proc->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                });
            })
            ->orderByDesc('created_at')
            ->get();

        return view('reports.prescreening.index', compact('procurements', 'submissions'));
    }

    public function submission(FormSubmission $submission)
    {
        $this->assertSubmissionInScope($submission);
        $submission->load(['procurement', 'submitter', 'values', 'prescreeningResult.evaluator']);

        $template = $this->resolveTemplate($submission);
        $sections = $template ? $template->sections : collect();

        $evaluations = PrescreeningEvaluation::with('criterion')
            ->where('submission_id', $submission->id)
            ->get()
            ->keyBy('criterion_id');

        return view('reports.prescreening.submission', compact(
            'submission',
            'template',
            'sections',
            'evaluations'
        ));
    }

    public function submissionPdf(FormSubmission $submission)
    {
        $this->assertSubmissionInScope($submission);
        $submission->load(['procurement', 'submitter', 'values', 'prescreeningResult.evaluator']);

        $template = $this->resolveTemplate($submission);
        $sections = $template ? $template->sections : collect();
        $evaluations = PrescreeningEvaluation::with('criterion')
            ->where('submission_id', $submission->id)
            ->get()
            ->keyBy('criterion_id');

        $pdf = Pdf::loadView('reports.prescreening.pdf.submission', compact(
            'submission',
            'template',
            'sections',
            'evaluations'
        ));

        return $pdf->download('prescreening-submission-' . $submission->id . '.pdf');
    }

    public function procurement(Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);
        $procurement->load(['prescreeningTemplate', 'prescreeningTemplate.sections.criteria']);

        $submissions = FormSubmission::with(['submitter', 'prescreeningResult.evaluator'])
            ->where('procurement_id', $procurement->id)
            ->orderByDesc('created_at')
            ->get();

        $summary = $this->buildSummary($submissions);
        $criteriaRates = $this->buildCriteriaRates($procurement, $submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);

        return view('reports.prescreening.procurement', compact(
            'procurement',
            'submissions',
            'summary',
            'criteriaRates',
            'evaluatorBreakdown'
        ));
    }

    public function procurementPdf(Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);
        $procurement->load(['prescreeningTemplate', 'prescreeningTemplate.sections.criteria']);

        $submissions = FormSubmission::with(['submitter', 'prescreeningResult.evaluator'])
            ->where('procurement_id', $procurement->id)
            ->orderByDesc('created_at')
            ->get();

        $summary = $this->buildSummary($submissions);
        $criteriaRates = $this->buildCriteriaRates($procurement, $submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);

        $pdf = Pdf::loadView('reports.prescreening.pdf.procurement', compact(
            'procurement',
            'submissions',
            'summary',
            'criteriaRates',
            'evaluatorBreakdown'
        ));

        return $pdf->download('prescreening-procurement-' . $procurement->id . '.pdf');
    }

    public function consolidated()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to prescreening reports.');
        }

        $procurements = Procurement::with('prescreeningTemplate')
            ->orderBy('title')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->get();

        $submissions = FormSubmission::with(['procurement', 'prescreeningResult.evaluator'])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereHas('procurement', function ($proc) use ($scopedNodeIds) {
                    $proc->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                });
            })
            ->orderByDesc('created_at')
            ->get();

        $summary = $this->buildSummary($submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);

        return view('reports.prescreening.consolidated', compact(
            'procurements',
            'submissions',
            'summary',
            'evaluatorBreakdown'
        ));
    }

    public function consolidatedPdf()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to prescreening reports.');
        }

        $procurements = Procurement::with('prescreeningTemplate')
            ->orderBy('title')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->get();

        $submissions = FormSubmission::with(['procurement', 'prescreeningResult.evaluator'])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereHas('procurement', function ($proc) use ($scopedNodeIds) {
                    $proc->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                });
            })
            ->orderByDesc('created_at')
            ->get();

        $summary = $this->buildSummary($submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);

        $pdf = Pdf::loadView('reports.prescreening.pdf.consolidated', compact(
            'procurements',
            'submissions',
            'summary',
            'evaluatorBreakdown'
        ));

        return $pdf->download('prescreening-consolidated.pdf');
    }

    private function resolveTemplate(FormSubmission $submission): ?PrescreeningTemplate
    {
        if ($submission->prescreeningResult?->prescreening_template_id) {
            return PrescreeningTemplate::with('sections.criteria')
                ->find($submission->prescreeningResult->prescreening_template_id);
        }

        return $submission->procurement?->prescreeningTemplate?->load('sections.criteria');
    }

    private function buildSummary($submissions): array
    {
        $total = $submissions->count();
        $passed = $submissions->where('status', 'prescreen_passed')->count();
        $failed = $submissions->where('status', 'prescreen_failed')->count();
        $pending = $submissions->whereNotIn('status', ['prescreen_passed', 'prescreen_failed'])->count();

        return compact('total', 'passed', 'failed', 'pending');
    }

    private function buildEvaluatorBreakdown($submissions)
    {
        return $submissions
            ->groupBy(fn ($s) => $s->prescreeningResult?->evaluator?->name ?? 'Unassigned')
            ->map(function ($group) {
                return [
                    'total' => $group->count(),
                    'passed' => $group->where('status', 'prescreen_passed')->count(),
                    'failed' => $group->where('status', 'prescreen_failed')->count(),
                ];
            });
    }

    private function buildCriteriaRates(Procurement $procurement, $submissions)
    {
        $template = $procurement->prescreeningTemplate;
        if (!$template) {
            return collect();
        }

        $criteria = $template->criteria()
            ->with('section')
            ->orderBy('sort_order')
            ->get();

        return $criteria->map(function ($criterion) use ($submissions) {
            $evaluations = PrescreeningEvaluation::where('criterion_id', $criterion->id)
                ->whereIn('submission_id', $submissions->pluck('id'))
                ->get();

            $total = $evaluations->count();
            $passed = $evaluations->where('is_passed', true)->count();
            $failed = $total - $passed;

            $rate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

            return [
                'section' => $criterion->section?->name ?? 'General Requirements',
                'name' => $criterion->name,
                'total' => $total,
                'passed' => $passed,
                'failed' => $failed,
                'rate' => $rate,
            ];
        });
    }
}
