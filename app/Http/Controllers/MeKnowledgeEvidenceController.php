<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Indicator;
use App\Models\MeIndicatorAchievement;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\MePerformanceReport;
use App\Models\MeRepositoryDocumentVersion;
use App\Models\MeRepositoryFolder;
use App\Models\Sector;
use App\Services\MeReportingNotificationService;
use App\Services\MeRepositoryFolderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeKnowledgeEvidenceController extends Controller
{
    use ScopesAssignedPortfolios;

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.configuration.view|me.configuration.manage|world.indicators.manage|me.performance_reports.view|me.performance_reports.review|me.data_entry.view|me.data_entry.manage')->only([
            'index',
        ]);
        $this->middleware('permission:me.configuration.view|me.configuration.manage|world.indicators.manage|me.performance_reports.view|me.performance_reports.review|me.data_entry.view|me.data_entry.manage|think_tank.me.reports.view|think_tank.me.reports.manage')
            ->only(['download', 'preview', 'downloadVersion']);
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
        $filters = $this->filters($request);

        $portfolioQuery = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($portfolioQuery, $request->user());
        }
        $portfolios = $portfolioQuery->get(['id', 'name']);
        $this->assertAuthorizedSelection($filters['portfolio_id'], $portfolios, 'portfolio');

        $folderQuery = MeRepositoryFolder::query()
            ->with(['portfolio:id,name', 'indicators:id,indicator_code,name'])
            ->withCount([
                'documents as documents_count' => fn (Builder $query): Builder => $query->whereNull('retired_at'),
                'documents as pending_documents_count' => fn (Builder $query): Builder => $query
                    ->whereNull('retired_at')->where('validation_status', 'pending'),
                'documents as validated_documents_count' => fn (Builder $query): Builder => $query
                    ->whereNull('retired_at')->where('validation_status', 'validated'),
            ])
            ->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($folderQuery, $request->user());
        }
        $folders = $folderQuery->get();
        $this->assertAuthorizedSelection($filters['folder_id'], $folders, 'repository folder');
        if ($filters['folder_id'] && $filters['portfolio_id']) {
            $selectedFolder = $folders->firstWhere('id', $filters['folder_id']);
            abort_if(
                (string) $selectedFolder?->portfolio_id !== $filters['portfolio_id'],
                422,
                'The selected folder does not belong to the selected portfolio.'
            );
        }

        $baseItemsQuery = MeKnowledgeEvidenceItem::query()->whereNull('retired_at');
        $this->scopeRepositoryQuery($baseItemsQuery, $request);
        $filteredItemsQuery = $this->applyFilters(clone $baseItemsQuery, $filters);
        $filteredVersionsQuery = MeRepositoryDocumentVersion::query()
            ->whereHas('repositoryItem', function (Builder $query) use ($filters, $request): void {
                $query->whereNull('retired_at');
                $this->scopeRepositoryQuery($query, $request);
                $this->applyFilters($query, $filters);
            });
        $metrics = [
            'documents' => (clone $filteredItemsQuery)->count(),
            'pending' => (clone $filteredItemsQuery)->where('validation_status', 'pending')->count(),
            'validated' => (clone $filteredItemsQuery)->where('validation_status', 'validated')->count(),
            'rejected' => (clone $filteredItemsQuery)->where('validation_status', 'rejected')->count(),
            'file_documents' => (clone $filteredItemsQuery)->whereNotNull('file_path')->count(),
            'external_documents' => (clone $filteredItemsQuery)->whereNotNull('external_url')->count(),
            'versions' => (clone $filteredVersionsQuery)->count(),
            'storage_bytes' => (int) (clone $filteredVersionsQuery)->sum('file_size'),
        ];
        $metrics['validation_rate'] = $metrics['documents'] > 0
            ? round(($metrics['validated'] / $metrics['documents']) * 100, 1)
            : 0.0;

        $items = (clone $filteredItemsQuery)
            ->with([
                'portfolio:id,name',
                'folder:id,portfolio_id,name',
                'folder.indicators:id,indicator_code,name',
                'creator:id,name',
                'validatedBy:id,name',
            ])
            ->withCount(['indicators', 'links', 'reportDocuments', 'matrixVersions', 'versions'])
            ->tap(fn (Builder $query): Builder => $this->applySort($query, $filters['sort']))
            ->paginate($filters['per_page'])
            ->withQueryString();
        $items->getCollection()->each(fn (MeKnowledgeEvidenceItem $item) => $item->setAttribute(
            'repository_file_available',
            $item->file_path && Storage::disk('local')->exists($item->file_path)
        ));

        $selectedItem = null;
        if ($filters['document_id']) {
            $selectedItem = (clone $filteredItemsQuery)->whereKey($filters['document_id'])->first();
            abort_unless($selectedItem, 404, 'The selected repository document is outside the active scope.');
        } elseif ($items->isNotEmpty()) {
            $selectedItem = $items->first();
        }
        if ($selectedItem) {
            $selectedItem->load([
                'portfolio:id,name',
                'folder:id,portfolio_id,name,description',
                'folder.indicators:id,indicator_code,name',
                'creator:id,name',
                'validatedBy:id,name',
                'versions.uploadedBy:id,name',
            ])->loadCount(['indicators', 'links', 'reportDocuments', 'matrixVersions', 'versions']);
            $selectedItem->setAttribute(
                'repository_file_available',
                $selectedItem->file_path && Storage::disk('local')->exists($selectedItem->file_path)
            );
        }

        $indicatorQuery = Indicator::query()
            ->with('projectComponent.program:id,sector_id')
            ->orderBy('indicator_code');
        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToIndicators($indicatorQuery);
        }
        $indicators = $indicatorQuery->get(['id', 'indicator_code', 'name', 'project_component_id']);

        return view('me.knowledge-evidence.index', [
            'items' => $items,
            'portfolios' => $portfolios,
            'documentTypes' => MeKnowledgeEvidenceItem::DOCUMENT_TYPES,
            'folders' => $folders,
            'indicators' => $indicators,
            'filters' => $filters,
            'metrics' => $metrics,
            'selectedItem' => $selectedItem,
            'generatedAt' => now(),
            'canManage' => $request->user()->hasPermission('me.configuration.manage'),
            'canValidate' => $request->user()->hasPermission('me.performance_reports.review')
                || $request->user()->hasPermission('me.configuration.manage'),
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
            'external_url' => 'nullable|required_without:evidence_file|url:http,https|max:2000',
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
            $evidence = DB::transaction(function () use ($portfolioId, $folder, $validated, $storedPath, $file, $checksum, $request): MeKnowledgeEvidenceItem {
                $evidence = MeKnowledgeEvidenceItem::query()->create([
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
                    'validation_status' => 'pending',
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
                if ($storedPath) {
                    $this->recordVersion($evidence, $storedPath, $file, 1, 'Initial repository upload', $request);
                }

                return $evidence;
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        if ($evidence->document_type === 'means_of_verification') {
            try {
                $notifications = app(MeReportingNotificationService::class);
                $notifications->reminder($evidence, 'repository_mov_validation_required', [
                    'title' => 'Repository MOV requires validation',
                    'message' => $evidence->title.' was added and is awaiting validation.',
                    'severity' => 'info',
                    'url' => route('budget.me.rebuild.knowledge-repository', ['document_id' => $evidence->id]),
                    'category' => 'mov_validation',
                ], $notifications->reviewers('me.performance_reports.review'));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect()
            ->route('budget.me.rebuild.knowledge-repository', [
                'portfolio_id' => $portfolioId,
                'folder_id' => $folder->id,
                'document_id' => $evidence->id,
            ])
            ->with('success', 'Evidence item added to the repository.');
    }

    public function storeFolder(Request $request)
    {
        $inlineIndicatorCreation = ($request->expectsJson() || $request->ajax())
            && $request->boolean('indicator_creation');
        $validated = $request->validate([
            'indicator_creation' => ['nullable', 'boolean'],
            'portfolio_id' => ['required', 'uuid', Rule::exists('myb_sectors', 'id')],
            'name' => [
                'required', 'string', 'max:180',
                Rule::unique('me_repository_folders', 'name')
                    ->where(fn ($query) => $query->where('portfolio_id', $request->input('portfolio_id'))),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'indicator_ids' => [$inlineIndicatorCreation ? 'nullable' : 'required', 'array', 'min:1'],
            'indicator_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('myb_indicators', 'id')],
        ]);
        $this->assertPortfolioInCurrentScope((string) $validated['portfolio_id']);
        $indicatorIds = $validated['indicator_ids'] ?? [];
        if ($indicatorIds !== []) {
            $this->assertIndicatorsBelongToPortfolio($indicatorIds, (string) $validated['portfolio_id']);
        }

        $folder = DB::transaction(function () use ($validated, $indicatorIds, $request): MeRepositoryFolder {
            $folder = MeRepositoryFolder::query()->create([
                'portfolio_id' => $validated['portfolio_id'],
                'name' => trim((string) $validated['name']),
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            if ($indicatorIds !== []) {
                $folder->indicators()->sync(collect($indicatorIds)->mapWithKeys(
                    fn (string $id): array => [$id => ['linked_by' => $request->user()->id]]
                )->all());
            }

            return $folder;
        });

        if ($request->expectsJson() || $request->ajax()) {
            $folder->load('portfolio:id,name');

            return response()->json([
                'message' => 'Repository folder created and selected.',
                'data' => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'label' => $folder->name.' (0 documents)',
                    'portfolio_id' => $folder->portfolio_id,
                    'portfolio_name' => $folder->portfolio?->name,
                    'documents_count' => 0,
                ],
            ], 201);
        }

        return redirect()->route('budget.me.rebuild.knowledge-repository', [
            'portfolio_id' => $folder->portfolio_id,
            'folder_id' => $folder->id,
        ])->with('success', 'Repository folder created and linked to the selected indicator(s).');
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

        return redirect()->route('budget.me.rebuild.knowledge-repository', [
            'portfolio_id' => $folder->portfolio_id,
            'folder_id' => $folder->id,
        ])->with('success', 'Folder name, description and indicator links updated.');
    }

    public function destroyFolder(MeRepositoryFolder $folder)
    {
        $this->assertFolderInCurrentScope($folder);
        if ($folder->documents()->exists()) {
            throw ValidationException::withMessages([
                'folder' => 'Move or delete the documents before deleting this folder.',
            ]);
        }
        DB::transaction(function () use ($folder): void {
            $folder->indicators()->detach();
            $folder->delete();
        });

        return redirect()->route('budget.me.rebuild.knowledge-repository', [
            'portfolio_id' => $folder->portfolio_id,
        ])->with('success', 'Empty repository folder and its indicator shortcuts were deleted.');
    }

    public function update(Request $request, MeKnowledgeEvidenceItem $evidence)
    {
        $this->assertRepositoryItemInCurrentScope($evidence);
        $validated = $request->validate([
            'folder_id' => ['required', 'uuid', Rule::exists('me_repository_folders', 'id')],
            'title' => 'required|string|max:255',
            'document_type' => ['required', Rule::in(array_keys(MeKnowledgeEvidenceItem::DOCUMENT_TYPES))],
            'description' => 'nullable|string|max:5000',
            'external_url' => 'nullable|url:http,https|max:2000',
        ]);

        if (! $evidence->file_path && blank($validated['external_url'] ?? null)) {
            throw ValidationException::withMessages([
                'external_url' => 'This document has no stored file, so an HTTP or HTTPS source URL is required.',
            ]);
        }

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

        return redirect()->route('budget.me.rebuild.knowledge-repository', [
            'portfolio_id' => $evidence->portfolio_id,
            'folder_id' => $evidence->folder_id,
            'document_id' => $evidence->id,
        ])->with('success', 'Repository document details updated.');
    }

    public function replaceFile(Request $request, MeKnowledgeEvidenceItem $evidence)
    {
        $this->assertRepositoryItemInCurrentScope($evidence);
        $validated = $request->validate([
            'replacement_file' => [
                'required', 'file', 'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png',
            ],
            'change_notes' => 'required|string|max:5000',
        ]);

        $file = $request->file('replacement_file');
        $checksum = hash_file('sha256', $file->getRealPath());
        if ($evidence->checksum_sha256 && hash_equals((string) $evidence->checksum_sha256, $checksum)) {
            throw ValidationException::withMessages([
                'replacement_file' => 'The replacement is identical to the current file; no new version was created.',
            ]);
        }
        if (MeKnowledgeEvidenceItem::query()
            ->where('folder_id', $evidence->folder_id)
            ->where('checksum_sha256', $checksum)
            ->where('id', '!=', $evidence->id)
            ->whereNull('retired_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'replacement_file' => 'This exact file already exists as another document in the selected folder.',
            ]);
        }

        $path = $file->store('me/knowledge-evidence/'.$evidence->id, 'local');
        try {
            $evidence = DB::transaction(function () use ($evidence, $path, $file, $checksum, $validated, $request): MeKnowledgeEvidenceItem {
                $locked = MeKnowledgeEvidenceItem::query()->lockForUpdate()->findOrFail($evidence->id);
                $nextVersion = max(
                    (int) $locked->version_number,
                    (int) $locked->versions()->max('version_number')
                ) + 1;
                $this->recordVersion(
                    $locked,
                    $path,
                    $file,
                    $nextVersion,
                    $validated['change_notes'],
                    $request
                );
                $locked->update([
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

                return $locked;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route('budget.me.rebuild.knowledge-repository', [
            'portfolio_id' => $evidence->portfolio_id,
            'folder_id' => $evidence->folder_id,
            'document_id' => $evidence->id,
        ])->with('success', 'A new repository document version was uploaded. Earlier versions were retained for audit history.');
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

    public function preview(Request $request, MeKnowledgeEvidenceItem $evidence)
    {
        $this->assertRepositoryItemInCurrentScope($evidence);
        $this->assertThinkTankMayDownload($request, $evidence);
        abort_unless($evidence->file_path && Storage::disk('local')->exists($evidence->file_path), 404);
        abort_unless($evidence->isPreviewable(), 415, 'This file type must be downloaded to view it.');

        return Storage::disk('local')->response(
            $evidence->file_path,
            $evidence->original_filename ?: basename($evidence->file_path),
            [
                'Content-Type' => $evidence->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $evidence->original_filename ?: 'document').'"',
                'X-Content-Type-Options' => 'nosniff',
            ]
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

        return redirect()->route('budget.me.rebuild.knowledge-repository', [
            'portfolio_id' => $evidence->portfolio_id,
            'folder_id' => $evidence->folder_id,
            'document_id' => $evidence->id,
        ])->with('success', 'Evidence validation decision recorded.');
    }

    private function filters(Request $request): array
    {
        $documentType = trim((string) $request->query('document_type'));
        $validationStatus = trim((string) $request->query('validation_status'));
        $source = trim((string) $request->query('source'));
        $sort = trim((string) $request->query('sort', 'newest'));
        $perPage = (int) $request->query('per_page', 25);

        return [
            'q' => Str::limit(trim((string) $request->query('q')), 120, ''),
            'portfolio_id' => $this->uuidOrNull($request->query('portfolio_id')),
            'folder_id' => $this->uuidOrNull($request->query('folder_id')),
            'document_id' => $this->uuidOrNull($request->query('document_id')),
            'document_type' => array_key_exists($documentType, MeKnowledgeEvidenceItem::DOCUMENT_TYPES)
                ? $documentType
                : null,
            'validation_status' => in_array($validationStatus, ['pending', 'validated', 'rejected'], true)
                ? $validationStatus
                : null,
            'source' => in_array($source, ['file', 'external'], true) ? $source : null,
            'sort' => in_array($sort, ['newest', 'oldest', 'title', 'version', 'validation'], true)
                ? $sort
                : 'newest',
            'per_page' => in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 25,
        ];
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['portfolio_id'], fn (Builder $builder): Builder => $builder
                ->where('portfolio_id', $filters['portfolio_id']))
            ->when($filters['folder_id'], fn (Builder $builder): Builder => $builder
                ->where('folder_id', $filters['folder_id']))
            ->when($filters['document_type'], fn (Builder $builder): Builder => $builder
                ->where('document_type', $filters['document_type']))
            ->when($filters['validation_status'], fn (Builder $builder): Builder => $builder
                ->where('validation_status', $filters['validation_status']))
            ->when($filters['source'] === 'file', fn (Builder $builder): Builder => $builder
                ->whereNotNull('file_path'))
            ->when($filters['source'] === 'external', fn (Builder $builder): Builder => $builder
                ->whereNotNull('external_url'))
            ->when($filters['q'] !== '', function (Builder $builder) use ($filters): Builder {
                $like = '%'.addcslashes(mb_strtolower($filters['q']), '%_\\').'%';

                return $builder->where(function (Builder $search) use ($like): void {
                    $search->whereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(original_filename, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(external_url, '')) LIKE ?", [$like])
                        ->orWhereHas('folder', fn (Builder $folder): Builder => $folder
                            ->whereRaw('LOWER(name) LIKE ?', [$like]))
                        ->orWhereHas('portfolio', fn (Builder $portfolio): Builder => $portfolio
                            ->whereRaw('LOWER(name) LIKE ?', [$like]));
                });
            });
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest('created_at')->orderBy('title'),
            'title' => $query->orderBy('title')->latest('updated_at'),
            'version' => $query->orderByDesc('version_number')->latest('updated_at'),
            'validation' => $query
                ->orderByRaw("CASE validation_status WHEN 'pending' THEN 1 WHEN 'rejected' THEN 2 WHEN 'validated' THEN 3 ELSE 4 END")
                ->latest('updated_at'),
            default => $query->latest('updated_at')->latest('created_at'),
        };
    }

    private function assertAuthorizedSelection(?string $id, Collection $options, string $label): void
    {
        if ($id && ! $options->contains(fn ($option): bool => (string) $option->id === $id)) {
            abort(403, 'You do not have access to the selected '.$label.'.');
        }
    }

    private function uuidOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return Str::isUuid($value) ? $value : null;
    }

    protected function scopeRepositoryQuery($query, ?Request $request = null): void
    {
        if ($this->userHasAssignedPortfolioScope($request?->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request?->user());
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
