<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Evaluation;
use App\Models\Sector;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvaluationController extends Controller
{
    use ScopesAssignedPortfolios;

    public function index()
    {
        $evaluationsQuery = $this->evaluationTemplateQuery()
            ->with('portfolio:id,name')
            ->withCount('sections')
            ->orderByDesc('created_at');
        $this->scopeEvaluationTemplateQuery($evaluationsQuery);

        $evaluations = $evaluationsQuery->paginate(15);

        return view('evaluations.index', compact('evaluations'));
    }

    public function create()
    {
        return view('evaluations.create', $this->evaluationPortfolioFormData());
    }

    public function store(Request $request)
    {
        $portfolioId = $this->resolveEvaluationPortfolioId($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:services,goods',
            'portfolio_id' => 'required|exists:myb_sectors,id',
        ]);

        Evaluation::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'portfolio_id' => $portfolioId,
            'is_portfolio_custom' => true,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('evals.cfg.index')
            ->with('success', 'Evaluation created successfully.');
    }

    public function show(Evaluation $evaluation)
    {
        $this->assertEvaluationTemplateManageable($evaluation);

        $evaluation->load('sections.criteria');

        return view('evaluations.show', compact('evaluation'));
    }

    public function preview(Request $request, Evaluation $evaluation)
    {
        $this->assertEvaluationTemplateManageable($evaluation);

        $evaluation->load([
            'sections' => fn ($query) => $query->orderBy('created_at'),
            'sections.criteria' => fn ($query) => $query->orderBy('created_at'),
        ]);

        $html = view('evaluations.partials.template-preview', compact('evaluation'))->render();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'title' => $evaluation->name,
                'html' => $html,
                'download_url' => route('evals.cfg.template.pdf', $evaluation),
            ]);
        }

        return view('evaluations.preview', compact('evaluation'));
    }

    public function templatePdf(Evaluation $evaluation)
    {
        $this->assertEvaluationTemplateManageable($evaluation);

        $evaluation->load([
            'sections' => fn ($query) => $query->orderBy('created_at'),
            'sections.criteria' => fn ($query) => $query->orderBy('created_at'),
        ]);

        $sectionTotals = $evaluation->sections->mapWithKeys(function ($section) {
            return [$section->id => (float) $section->criteria->sum('max_score')];
        });

        $overallTotal = (float) $sectionTotals->sum();

        $pdf = Pdf::loadView('evaluations.pdf.template', compact(
            'evaluation',
            'sectionTotals',
            'overallTotal'
        ))->setPaper('a4', 'portrait');

        $safeName = Str::slug($evaluation->name ?: 'evaluation-template');

        return $pdf->download("{$safeName}-template.pdf");
    }

    public function edit(Evaluation $evaluation)
    {
        $this->assertEvaluationTemplateManageable($evaluation);

        return view('evaluations.edit', array_merge(
            compact('evaluation'),
            $this->evaluationPortfolioFormData($evaluation)
        ));
    }

    public function update(Request $request, Evaluation $evaluation)
    {
        $this->assertEvaluationTemplateManageable($evaluation);

        if ($request->has('status')) {
            $allowedStatuses = ['draft', 'active', 'close'];

            if (! in_array($request->status, $allowedStatuses, true)) {
                return back()->with('error', 'Invalid evaluation status.');
            }

            if ($evaluation->status === 'close') {
                return back()->with('error', 'Closed evaluations cannot be modified.');
            }

            $evaluation->update([
                'status' => $request->status,
            ]);

            return back()->with('success', 'Evaluation status updated successfully.');
        }

        $portfolioId = $this->resolveEvaluationPortfolioId($request, $evaluation);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'portfolio_id' => 'required|exists:myb_sectors,id',
        ]);

        if ($evaluation->status !== 'draft') {
            return back()->with('error', 'Only draft evaluations can be edited.');
        }

        $evaluation->update([
            'name' => $request->name,
            'description' => $request->description,
            'portfolio_id' => $portfolioId,
        ]);

        return redirect()
            ->route('evals.cfg.index')
            ->with('success', 'Evaluation updated successfully.');
    }

    public function destroy(Evaluation $evaluation)
    {
        $this->assertEvaluationTemplateManageable($evaluation);

        if ($evaluation->status !== 'draft') {
            return back()->with('error', 'Only draft evaluations can be deleted.');
        }

        $evaluation->delete();

        return redirect()
            ->route('evals.cfg.index')
            ->with('success', 'Evaluation deleted successfully.');
    }

    private function evaluationTemplateQuery()
    {
        return Evaluation::query()
            ->whereIn('type', ['services', 'goods'])
            ->whereIn('status', ['draft', 'active', 'close'])
            ->whereNotNull('portfolio_id')
            ->where('is_portfolio_custom', true);
    }

    private function scopeEvaluationTemplateQuery($query): void
    {
        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToEvaluations($query);
        }
    }

    private function assertEvaluationTemplateManageable(Evaluation $evaluation): void
    {
        abort_unless(
            in_array($evaluation->type, ['services', 'goods'], true)
            && in_array($evaluation->status, ['draft', 'active', 'close'], true)
            && filled($evaluation->portfolio_id),
            404
        );

        abort_unless((bool) $evaluation->is_portfolio_custom, 404);

        if (! $this->userHasAssignedPortfolioScope()) {
            return;
        }

        abort_unless(
            $this->evaluationIsInAssignedPortfolio($evaluation),
            403,
            'This evaluation configuration is not assigned to your portfolio.'
        );
    }

    private function resolveEvaluationPortfolioId(Request $request, ?Evaluation $evaluation = null): string
    {
        if (! $this->userHasAssignedPortfolioScope()) {
            $portfolioId = $request->input('portfolio_id') ?: ($evaluation?->portfolio_id ?? null);

            if (! $portfolioId || ! Sector::query()->whereKey($portfolioId)->exists()) {
                throw ValidationException::withMessages([
                    'portfolio_id' => 'Select the portfolio this evaluation belongs to.',
                ]);
            }

            return (string) $portfolioId;
        }

        $portfolioIds = $this->assignedPortfolioIds();
        $portfolioId = $request->input('portfolio_id') ?: ($evaluation?->portfolio_id ?: ($portfolioIds[0] ?? null));

        if (! $portfolioId || ! in_array((string) $portfolioId, $portfolioIds, true)) {
            throw ValidationException::withMessages([
                'portfolio_id' => 'Select a portfolio assigned to your account.',
            ]);
        }

        return (string) $portfolioId;
    }

    private function evaluationPortfolioFormData(?Evaluation $evaluation = null): array
    {
        $portfolioQuery = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToSectors($portfolioQuery);
        }

        $portfolioOptions = $portfolioQuery->get(['id', 'name']);
        $selectedPortfolioId = old(
            'portfolio_id',
            $evaluation?->portfolio_id ?: ($this->userHasAssignedPortfolioScope() ? ($portfolioOptions->first()?->id) : null)
        );

        return [
            'portfolioOptions' => $portfolioOptions,
            'selectedPortfolioId' => $selectedPortfolioId,
            'portfolioFieldLocked' => $this->userHasAssignedPortfolioScope() || $portfolioOptions->count() === 1,
        ];
    }
}
