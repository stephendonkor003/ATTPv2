<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Evaluation;
use App\Models\EvaluationSubmission;
use App\Models\ReworkRequest;
use App\Models\Sector;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            'description' => 'nullable|string|max:1000',
            'type' => ['required', Rule::in(Evaluation::MANAGED_TYPES)],
            'portfolio_id' => 'required|exists:myb_sectors,id',
        ]);

        $evaluation = Evaluation::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'portfolio_id' => $portfolioId,
            'is_portfolio_custom' => true,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('evals.cfg.show', $evaluation)
            ->with('success', 'Evaluation created. Add sections and criteria to complete the template.');
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
            'portfolio:id,name',
            'creator:id,name,email',
            'sections' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->orderBy('id'),
            'sections.criteria' => fn ($query) => $query
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        $sectionTotals = $evaluation->sections->mapWithKeys(function ($section) {
            return [$section->id => (float) $section->criteria->sum('max_score')];
        });

        $overallTotal = (float) $sectionTotals->sum();
        $reportGeneratedAt = now(config('app.timezone', 'UTC'));
        $generatedBy = auth()->user();
        $documentReference = 'ATTP-EVAL-'
            .Str::upper(Str::substr((string) $evaluation->getKey(), 0, 8))
            .'-'.$reportGeneratedAt->format('Ymd');

        $pdf = Pdf::loadView('evaluations.pdf.template', compact(
            'evaluation',
            'sectionTotals',
            'overallTotal',
            'reportGeneratedAt',
            'generatedBy',
            'documentReference'
        ))->setPaper('a4', 'portrait');

        $safeName = Str::slug($evaluation->name ?: 'evaluation-template');

        return $pdf->download("{$safeName}-template.pdf");
    }

    public function edit(Evaluation $evaluation)
    {
        $this->assertEvaluationTemplateManageable($evaluation);
        $evaluation->load('sections.criteria');

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
            'description' => 'nullable|string|max:1000',
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
            ->route('evals.cfg.show', $evaluation)
            ->with('success', 'Evaluation details updated successfully.');
    }

    public function destroy(Evaluation $evaluation)
    {
        $this->assertEvaluationTemplateManageable($evaluation);

        if ($evaluation->status !== 'draft') {
            return back()->with('error', 'Only draft evaluations can be deleted.');
        }

        if ($this->evaluationHasRecordedUse($evaluation)) {
            return back()->with('error', 'This evaluation form is already linked to evaluation records and cannot be deleted.');
        }

        DB::transaction(function () use ($evaluation): void {
            // Delete models one at a time so EvaluationSection's recursive
            // cleanup removes nested sections, criteria, and score rows.
            $evaluation->rootSections()->get()->each(
                fn ($section) => $section->delete()
            );

            $evaluation->delete();
        });

        return redirect()
            ->route('evals.cfg.index')
            ->with('success', 'Evaluation deleted successfully.');
    }

    private function evaluationHasRecordedUse(Evaluation $evaluation): bool
    {
        return filled($evaluation->procurement_id)
            || $evaluation->assignments()->exists()
            || $evaluation->procurements()->exists()
            || EvaluationSubmission::query()
                ->where('evaluation_id', $evaluation->getKey())
                ->exists()
            || ReworkRequest::query()
                ->where('evaluation_id', $evaluation->getKey())
                ->exists();
    }

    private function evaluationTemplateQuery()
    {
        return Evaluation::query()
            ->whereIn('type', Evaluation::MANAGED_TYPES)
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
            in_array($evaluation->type, Evaluation::MANAGED_TYPES, true)
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
