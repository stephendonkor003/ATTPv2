<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\Sector;
use App\Services\MeReportingNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeKnowledgeEvidenceController extends Controller
{
    use ScopesAssignedPortfolios;

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.configuration.view|me.configuration.manage')->only([
            'index',
            'download',
        ]);
        $this->middleware('permission:me.configuration.manage')->only([
            'store',
            'destroy',
        ]);
        $this->middleware('permission:me.performance_reports.review|me.configuration.manage')
            ->only('validateEvidence');
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $selectedPortfolioId = trim((string) $request->query('portfolio_id', ''));

        $itemsQuery = MeKnowledgeEvidenceItem::query()
            ->with(['portfolio:id,name', 'creator:id,name'])
            ->withCount('indicators');
        $this->scopeRepositoryQuery($itemsQuery);

        $items = $itemsQuery
            ->when($selectedPortfolioId !== '', fn ($query) => $query->where('portfolio_id', $selectedPortfolioId))
            ->when($search !== '', function ($query) use ($search) {
                $escaped = addcslashes($search, '%_\\');
                $term = '%'.$escaped.'%';

                $query->where(function ($searchQuery) use ($term) {
                    $searchQuery->whereLike('title', $term)
                        ->orWhereLike('description', $term)
                        ->orWhereLike('original_filename', $term)
                        ->orWhereLike('external_url', $term);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $portfolioQuery = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToSectors($portfolioQuery);
        }
        $portfolios = $portfolioQuery->get(['id', 'name']);

        return view('me.knowledge-evidence.index', [
            'items' => $items,
            'portfolios' => $portfolios,
            'documentTypes' => MeKnowledgeEvidenceItem::DOCUMENT_TYPES,
            'search' => $search,
            'selectedPortfolioId' => $selectedPortfolioId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'portfolio_id' => 'required|uuid|exists:myb_sectors,id',
            'title' => 'required|string|max:255',
            'document_type' => [
                'required',
                Rule::in(array_keys(MeKnowledgeEvidenceItem::DOCUMENT_TYPES)),
            ],
            'description' => 'nullable|string|max:5000',
            'evidence_file' => [
                'nullable',
                'required_without:external_url',
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png',
            ],
            'external_url' => 'nullable|required_without:evidence_file|url|max:2000',
        ]);

        $this->assertPortfolioInCurrentScope((string) $validated['portfolio_id']);

        $file = $request->file('evidence_file');
        $storedPath = $file?->store('me/knowledge-evidence', 'local');

        try {
            $evidence = MeKnowledgeEvidenceItem::create([
                'portfolio_id' => $validated['portfolio_id'],
                'title' => trim((string) $validated['title']),
                'document_type' => $validated['document_type'],
                'description' => $validated['description'] ?? null,
                'file_path' => $storedPath,
                'original_filename' => $file?->getClientOriginalName(),
                'mime_type' => $file?->getMimeType(),
                'file_size' => $file?->getSize(),
                'external_url' => $validated['external_url'] ?? null,
                'created_by' => auth()->id(),
            ]);
            if ($evidence->document_type === 'means_of_verification') {
                $notifications = app(MeReportingNotificationService::class);
                $notifications->reminder($evidence, 'repository_mov_validation_required', [
                    'title' => 'Repository MOV requires validation',
                    'message' => $evidence->title.' was added and is awaiting validation.',
                    'severity' => 'info',
                    'url' => route('budget.me.rebuild.knowledge-repository', ['q' => $evidence->title]),
                    'category' => 'mov_validation',
                ], $notifications->reviewers('me.performance_reports.review'));
            }
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        return redirect()
            ->route('budget.me.rebuild.knowledge-repository')
            ->with('success', 'Evidence item added to the repository.');
    }

    public function download(MeKnowledgeEvidenceItem $evidence)
    {
        $this->assertRepositoryItemInCurrentScope($evidence);
        abort_unless($evidence->file_path && Storage::disk('local')->exists($evidence->file_path), 404);

        return Storage::disk('local')->download(
            $evidence->file_path,
            $evidence->original_filename ?: basename($evidence->file_path)
        );
    }

    public function destroy(MeKnowledgeEvidenceItem $evidence)
    {
        $this->assertRepositoryItemInCurrentScope($evidence);

        if ($evidence->indicators()->exists()) {
            throw ValidationException::withMessages([
                'evidence' => 'This evidence item is used as a Means of Verification and cannot be deleted.',
            ]);
        }

        $filePath = $evidence->file_path;
        $evidence->delete();

        if ($filePath) {
            Storage::disk('local')->delete($filePath);
        }

        return redirect()
            ->route('budget.me.rebuild.knowledge-repository')
            ->with('success', 'Evidence item removed from the repository.');
    }

    public function validateEvidence(Request $request, MeKnowledgeEvidenceItem $evidence)
    {
        $this->assertRepositoryItemInCurrentScope($evidence);
        $validated = $request->validate([
            'validation_status' => ['required', Rule::in(['validated', 'rejected'])],
            'validation_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($validated['validation_status'] === 'rejected' && blank($validated['validation_notes'] ?? null)) {
            throw ValidationException::withMessages([
                'validation_notes' => 'Explain why this Means of Verification was rejected.',
            ]);
        }
        $evidence->update([
            'validation_status' => $validated['validation_status'],
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
            'validation_notes' => $validated['validation_notes'] ?? null,
        ]);

        return back()->with('success', 'Means of Verification validation recorded.');
    }

    protected function scopeRepositoryQuery($query): void
    {
        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query);
        }
    }

    protected function assertRepositoryItemInCurrentScope(MeKnowledgeEvidenceItem $evidence): void
    {
        if ($this->userHasAssignedPortfolioScope()
            && ! $this->portfolioOwnedRecordIsInAssignedPortfolio($evidence)) {
            abort(403, 'You do not have access to this repository item.');
        }
    }

    protected function assertPortfolioInCurrentScope(string $portfolioId): void
    {
        if ($this->userHasAssignedPortfolioScope()
            && ! in_array($portfolioId, $this->assignedPortfolioIds(), true)) {
            abort(403, 'You do not have access to the selected portfolio.');
        }
    }
}
