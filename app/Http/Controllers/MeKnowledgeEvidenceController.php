<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Indicator;
use App\Models\MeIndicatorAchievement;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\MePerformanceReport;
use App\Models\MeRepositoryFolder;
use App\Models\MeRepositoryDocumentVersion;
use App\Models\Sector;
use App\Services\MeRepositoryFolderService;
use App\Services\MeReportingNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);
        $this->middleware('permission:me.configuration.view|me.configuration.manage|me.performance_reports.view|me.performance_reports.review|me.data_entry.view|me.data_entry.manage|think_tank.me.reports.view|think_tank.me.reports.manage')
            ->only(['download', 'downloadVersion']);
        $this->middleware('permission:me.configuration.manage')->only([
            'store',
            'storeFolder',
            'updateFolder',
            'destroyFolder',
            'update',
            'replaceFile',
            'destroy',
        ]);
        $this->middleware('permission:me.performance_reports.review|me.configuration.manage')
            ->only('validateEvidence');
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $selectedPortfolioId = trim((string) $request->query('portfolio_id', ''));
        $selectedFolderId = trim((string) $request->query('folder_id', ''));

        $itemsQuery = MeKnowledgeEvidenceItem::query()
            ->with(['portfolio:id,name', 'folder:id,portfolio_id,name', 'creator:id,name', 'versions'])
            ->withCount(['indicators', 'links', 'reportDocuments', 'matrixVersions']);
        $this->scopeRepositoryQuery($itemsQuery);

        $items = $itemsQuery
            ->when($selectedPortfolioId !== '', fn ($query) => $query->where('portfolio_id', $selectedPortfolioId))
            ->when($selectedFolderId !== '', fn ($query) => $query->where('folder_id', $selectedFolderId))
            ->when($search !== '', function ($query) use ($search) {
                $escaped = addcslashes($search, '%_\\');
                $term = '%'.$escaped.'%';

                $query->where(function ($searchQuery) use ($term) {
                    $searchQuery->whereLike('title', $term)
                        ->orWhereLike('description', $term)
                        ->orWhereLike('original_filename', $term)
                        ->orWhereLike('external_url', $term)
                        ->orWhereHas('folder', fn ($folderQuery) => $folderQuery
                            ->whereLike('name', $term));
                });
            })
            ->orderBy('folder_id')
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $folderQuery = MeRepositoryFolder::query()
            ->with(['portfolio:id,name', 'indicators:id,indicator_code,name'])
            ->withCount('documents')
            ->orderBy('name');
        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($folderQuery);
        }
        $folders = $folderQuery
            ->when($selectedPortfolioId !== '', fn ($query) => $query->where('portfolio_id', $selectedPortfolioId))
            ->get();

        $indicatorQuery = Indicator::query()
            ->with('projectComponent.program:id,sector_id')
            ->orderBy('indicator_code');
        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToIndicators($indicatorQuery);
        }
        $indicators = $indicatorQuery->get(['id', 'indicator_code', 'name', 'project_component_id']);

        $portfolioQuery = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToSectors($portfolioQuery);
        }
        $portfolios = $portfolioQuery->get(['id', 'name']);

        return view('me.knowledge-evidence.index', [
            'items' => $items,
            'portfolios' => $portfolios,
            'documentTypes' => MeKnowledgeEvidenceItem::DOCUMENT_TYPES,
            'folders' => $folders,
            'indicators' => $indicators,
            'search' => $search,
            'selectedPortfolioId' => $selectedPortfolioId,
            'selectedFolderId' => $selectedFolderId,
        ]);
    }

    public function store(Request $request)
    {
        $folders = app(MeRepositoryFolderService::class);

        $validated = $request->validate([
            'folder_id' => 'nullable|uuid|exists:me_repository_folders,id',
            'portfolio_id' => 'nullable|required_without:folder_id|uuid|exists:myb_sectors,id',
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

        $folder = filled($validated['folder_id'] ?? null)
            ? MeRepositoryFolder::query()->findOrFail($validated['folder_id'])
            : $folders->general((string) $validated['portfolio_id'], (string) $request->user()->id);
        $this->assertFolderInCurrentScope($folder);
        $portfolioId = (string) $folder->portfolio_id;
        $this->assertPortfolioInCurrentScope($portfolioId);

        $file = $request->file('evidence_file');
        $checksum = $file ? hash_file('sha256', $file->getRealPath()) : null;
        if ($checksum && MeKnowledgeEvidenceItem::query()
            ->where('folder_id', $folder->id)
            ->where('checksum_sha256', $checksum)
            ->whereNull('retired_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'evidence_file' => 'This exact file is already in the selected repository folder. Link the existing document instead of uploading a duplicate.',
            ]);
        }
        $storedPath = $file?->store('me/knowledge-evidence', 'local');

        try {
            $evidence = MeKnowledgeEvidenceItem::create([
                'portfolio_id' => $portfolioId,
                'folder_id' => $folder->id,
                'title' => trim((string) $validated['title']),
                'document_type' => $validated['document_type'],
                'repository_category' => $this->repositoryCategory($validated['document_type']),
                'description' => $validated['description'] ?? null,
                'file_path' => $storedPath,
                'original_filename' => $file?->getClientOriginalName(),
                'mime_type' => $file?->getMimeType(),
                'file_size' => $file?->getSize(),
                'checksum_sha256' => $checksum,
                'version_number' => 1,
                'external_url' => $validated['external_url'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            if ($storedPath) {
                $this->recordVersion($evidence, $storedPath, $file, 1, 'Initial repository upload', $request);
            }
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

    public function storeFolder(Request $request)
    {
        $validated = $request->validate([
            'portfolio_id' => ['required', 'uuid', Rule::exists('myb_sectors', 'id')],
            'name' => [
                'required', 'string', 'max:180',
                Rule::unique('me_repository_folders', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $request->input('portfolio_id'))),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'indicator_ids' => ['required', 'array', 'min:1'],
            'indicator_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('myb_indicators', 'id')],
        ]);
        $this->assertPortfolioInCurrentScope((string) $validated['portfolio_id']);
        $this->assertIndicatorsBelongToPortfolio($validated['indicator_ids'], (string) $validated['portfolio_id']);

        DB::transaction(function () use ($validated, $request): void {
            $folder = MeRepositoryFolder::query()->create([
                'portfolio_id' => $validated['portfolio_id'],
                'name' => trim((string) $validated['name']),
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $folder->indicators()->sync(collect($validated['indicator_ids'])->mapWithKeys(
                fn (string $id): array => [$id => ['linked_by' => $request->user()->id]]
            )->all());
        });

        return back()->with('success', 'Repository folder created and linked to the selected indicator(s).');
    }

    public function updateFolder(Request $request, MeRepositoryFolder $folder)
    {
        $this->assertFolderInCurrentScope($folder);
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:180',
                Rule::unique('me_repository_folders', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $folder->portfolio_id))
                    ->ignore($folder->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'indicator_ids' => ['required', 'array', 'min:1'],
            'indicator_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('myb_indicators', 'id')],
        ]);
        $this->assertIndicatorsBelongToPortfolio($validated['indicator_ids'], (string) $folder->portfolio_id);

        DB::transaction(function () use ($validated, $request, $folder): void {
            $folder->update([
                'name' => trim((string) $validated['name']),
                'description' => $validated['description'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
            $folder->indicators()->sync(collect($validated['indicator_ids'])->mapWithKeys(
                fn (string $id): array => [$id => ['linked_by' => $request->user()->id]]
            )->all());
        });

        return back()->with('success', 'Folder name, description and indicator links updated.');
    }

    public function destroyFolder(MeRepositoryFolder $folder)
    {
        $this->assertFolderInCurrentScope($folder);
        if ($folder->documents()->exists() || $folder->indicators()->exists()) {
            throw ValidationException::withMessages([
                'folder' => 'Move or delete the documents and indicator links before deleting this folder.',
            ]);
        }
        $folder->delete();

        return back()->with('success', 'Empty repository folder deleted.');
    }

    public function update(Request $request, MeKnowledgeEvidenceItem $evidence)
    {
        $this->assertRepositoryItemInCurrentScope($evidence);
        $validated = $request->validate([
            'folder_id' => ['required', 'uuid', Rule::exists('me_repository_folders', 'id')],
            'title' => 'required|string|max:255',
            'document_type' => ['required', Rule::in(array_keys(MeKnowledgeEvidenceItem::DOCUMENT_TYPES))],
            'description' => 'nullable|string|max:5000',
            'external_url' => 'nullable|url|max:2000',
        ]);

        $folder = MeRepositoryFolder::query()->findOrFail($validated['folder_id']);
        $this->assertFolderInCurrentScope($folder);
        if ((string) $folder->portfolio_id !== (string) $evidence->portfolio_id) {
            throw ValidationException::withMessages([
                'folder_id' => 'A document can only be moved to a folder in the same portfolio.',
            ]);
        }

        $evidence->update([
            ...$validated,
            'repository_category' => $this->repositoryCategory($validated['document_type']),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Repository document details updated.');
    }

    public function replaceFile(Request $request, MeKnowledgeEvidenceItem $evidence)
    {
        $this->assertRepositoryItemInCurrentScope($evidence);
        $validated = $request->validate([
            'replacement_file' => [
                'required', 'file', 'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,zip',
            ],
            'change_notes' => 'required|string|max:5000',
        ]);

        $file = $request->file('replacement_file');
        $checksum = hash_file('sha256', $file->getRealPath());
        if (hash_equals((string) $evidence->checksum_sha256, $checksum)) {
            throw ValidationException::withMessages([
                'replacement_file' => 'The replacement is identical to the current file; no new version was created.',
            ]);
        }

        $path = $file->store('me/knowledge-evidence/'.$evidence->id, 'local');
        $nextVersion = ((int) $evidence->version_number) + 1;
        try {
            $this->recordVersion(
                $evidence,
                $path,
                $file,
                $nextVersion,
                $validated['change_notes'],
                $request
            );
            $evidence->update([
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'checksum_sha256' => $checksum,
                'version_number' => $nextVersion,
                'validation_status' => 'pending',
                'validated_by' => null,
                'validated_at' => null,
                'validation_notes' => null,
                'updated_by' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'A new repository document version was uploaded. Earlier versions were retained for audit history.');
    }

    public function download(Request $request, MeKnowledgeEvidenceItem $evidence)
    {
        $this->assertRepositoryItemInCurrentScope($evidence);
        $this->assertThinkTankMayDownload($request, $evidence);
        abort_unless($evidence->file_path && Storage::disk('local')->exists($evidence->file_path), 404);

        return Storage::disk('local')->download(
            $evidence->file_path,
            $evidence->original_filename ?: basename($evidence->file_path)
        );
    }

    public function downloadVersion(
        Request $request,
        MeKnowledgeEvidenceItem $evidence,
        MeRepositoryDocumentVersion $version
    ) {
        $this->assertRepositoryItemInCurrentScope($evidence);
        $this->assertThinkTankMayDownload($request, $evidence);
        abort_unless((string) $version->repository_item_id === (string) $evidence->id, 404);
        abort_unless($version->file_path && Storage::disk('local')->exists($version->file_path), 404);

        return Storage::disk('local')->download(
            $version->file_path,
            $version->original_filename ?: basename($version->file_path)
        );
    }

    public function destroy(MeKnowledgeEvidenceItem $evidence)
    {
        $this->assertRepositoryItemInCurrentScope($evidence);

        if ($evidence->indicators()->exists()
            || $evidence->links()->exists()
            || $evidence->reportDocuments()->exists()
            || $evidence->matrixVersions()->exists()) {
            throw ValidationException::withMessages([
                'evidence' => 'This document is linked to an indicator, report, achievement, or M&E Matrix and cannot be deleted. Remove its links or retain it as audit evidence.',
            ]);
        }

        $filePaths = $evidence->versions()->pluck('file_path')
            ->push($evidence->file_path)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $evidence->delete();

        if ($filePaths !== []) {
            Storage::disk('local')->delete($filePaths);
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
            'validation_notes' => ['required', 'string', 'max:5000'],
        ]);
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

    protected function assertFolderInCurrentScope(MeRepositoryFolder $folder): void
    {
        if ($this->userHasAssignedPortfolioScope()
            && ! $this->portfolioOwnedRecordIsInAssignedPortfolio($folder)) {
            abort(403, 'You do not have access to this repository folder.');
        }
    }

    /** @param array<int, string> $indicatorIds */
    private function assertIndicatorsBelongToPortfolio(array $indicatorIds, string $portfolioId): void
    {
        $validCount = Indicator::query()
            ->whereIn('id', $indicatorIds)
            ->whereHas('projectComponent.program', fn ($query) => $query->where('sector_id', $portfolioId))
            ->count();
        if ($validCount !== count(array_unique($indicatorIds))) {
            throw ValidationException::withMessages([
                'indicator_ids' => 'Every linked indicator must belong to the selected folder portfolio.',
            ]);
        }
    }

    private function assertThinkTankMayDownload(Request $request, MeKnowledgeEvidenceItem $evidence): void
    {
        $user = $request->user();
        if (! $user?->isThinkTankUser()) {
            return;
        }

        $memberId = $user->resolvedThinkTankMembership()?->id;
        abort_unless($memberId, 403, 'This account is not linked to a reporting organization.');

        $reportIds = MePerformanceReport::query()
            ->select('id')
            ->where('think_tank_member_id', $memberId);
        $achievementIds = MeIndicatorAchievement::query()
            ->select('id')
            ->whereIn('report_id', clone $reportIds);

        $ownedReportDocument = $evidence->reportDocuments()
            ->whereIn('report_id', clone $reportIds)
            ->exists();
        $ownedRepositoryLink = $evidence->links()
            ->where(function ($query) use ($reportIds, $achievementIds): void {
                $query->where(function ($reportQuery) use ($reportIds): void {
                    $reportQuery
                        ->where('linkable_type', MePerformanceReport::class)
                        ->whereIn('linkable_id', clone $reportIds);
                })->orWhere(function ($achievementQuery) use ($achievementIds): void {
                    $achievementQuery
                        ->where('linkable_type', MeIndicatorAchievement::class)
                        ->whereIn('linkable_id', clone $achievementIds);
                });
            })
            ->exists();

        abort_unless(
            $ownedReportDocument || $ownedRepositoryLink,
            403,
            'Think-tank users may download only evidence linked to their organization reports.'
        );
    }

    protected function assertPortfolioInCurrentScope(string $portfolioId): void
    {
        if ($this->userHasAssignedPortfolioScope()
            && ! in_array($portfolioId, $this->assignedPortfolioIds(), true)) {
            abort(403, 'You do not have access to the selected portfolio.');
        }
    }

    private function repositoryCategory(string $documentType): string
    {
        return match ($documentType) {
            'supporting_evidence', 'means_of_verification' => 'evidence',
            'me_matrix' => 'matrix',
            default => 'knowledge',
        };
    }

    private function recordVersion(
        MeKnowledgeEvidenceItem $evidence,
        string $path,
        $file,
        int $version,
        string $notes,
        Request $request
    ): MeRepositoryDocumentVersion {
        return $evidence->versions()->create([
            'version_number' => $version,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
            'change_notes' => $notes,
            'uploaded_by' => $request->user()->id,
        ]);
    }
}
