<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\MeMatrixVersion;
use App\Models\Sector;
use App\Services\MeRepositoryFolderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MeMatrixController extends Controller
{
    use ScopesAssignedPortfolios;

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.configuration.view|me.configuration.manage|world.indicators.manage')->only(['index', 'download', 'pdf']);
        $this->middleware('permission:me.configuration.manage')->except(['index', 'download', 'pdf']);
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $portfolioQuery = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($portfolioQuery, $request->user());
        }
        $portfolios = $portfolioQuery->get(['id', 'name']);
        $this->assertAuthorizedPortfolio($filters['portfolio_id'], $portfolios);

        $filteredQuery = $this->applyFilters($this->matrixQuery($request), $filters);
        $analysisMatrices = (clone $filteredQuery)
            ->with(['portfolio:id,name', 'repositoryItem:id,file_size,mime_type,original_filename,file_path'])
            ->get();
        $metrics = $this->metrics($analysisMatrices);

        $matrices = (clone $filteredQuery)
            ->with([
                'portfolio:id,name',
                'repositoryItem:id,portfolio_id,title,file_path,original_filename,mime_type,file_size,validation_status,retired_at',
                'createdBy:id,name',
                'approvedBy:id,name',
            ])
            ->tap(fn (Builder $query): Builder => $this->applySort($query, $filters['sort']))
            ->paginate($filters['per_page'])
            ->withQueryString();
        $matrices->getCollection()->each(function (MeMatrixVersion $matrix): void {
            $item = $matrix->repositoryItem;
            $matrix->setAttribute(
                'matrix_file_available',
                (bool) ($item?->file_path && Storage::disk('local')->exists($item->file_path))
            );
        });

        $selectedMatrix = null;
        if ($filters['matrix_id']) {
            $selectedMatrix = (clone $filteredQuery)->whereKey($filters['matrix_id'])->first();
            abort_unless($selectedMatrix, 404, 'The selected M&E Matrix is outside the active scope.');
        } elseif ($matrices->isNotEmpty()) {
            $selectedMatrix = $matrices->first();
        }
        $selectedMatrix?->load([
            'portfolio:id,name',
            'repositoryItem:id,portfolio_id,title,file_path,original_filename,mime_type,file_size,checksum_sha256,validation_status,retired_at',
            'createdBy:id,name',
            'approvedBy:id,name',
        ]);
        if ($selectedMatrix) {
            $selectedMatrix->setAttribute(
                'matrix_file_available',
                (bool) ($selectedMatrix->repositoryItem?->file_path
                    && Storage::disk('local')->exists($selectedMatrix->repositoryItem->file_path))
            );
        }

        return view('me.matrices.index', [
            'matrices' => $matrices,
            'portfolios' => $portfolios,
            'statuses' => MeMatrixVersion::STATUSES,
            'filters' => $filters,
            'metrics' => $metrics,
            'charts' => $this->charts($analysisMatrices),
            'selectedMatrix' => $selectedMatrix,
            'generatedAt' => now(),
            'canManage' => $request->user()->hasPermission('me.configuration.manage'),
            'exportQuery' => collect($filters)
                ->except(['matrix_id', 'per_page'])
                ->reject(fn ($value): bool => $value === null || $value === '' || $value === 'newest')
                ->all(),
        ]);
    }

    public function pdf(Request $request)
    {
        $filters = $this->filters($request);
        $portfolioQuery = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($portfolioQuery, $request->user());
        }
        $portfolios = $portfolioQuery->get(['id', 'name']);
        $this->assertAuthorizedPortfolio($filters['portfolio_id'], $portfolios);

        $query = $this->applyFilters($this->matrixQuery($request), $filters);
        if ($filters['matrix_id']) {
            $query->whereKey($filters['matrix_id']);
        }
        $matrices = $query
            ->with([
                'portfolio:id,name',
                'repositoryItem:id,portfolio_id,title,original_filename,mime_type,file_size,validation_status,retired_at',
                'createdBy:id,name',
                'approvedBy:id,name',
            ])
            ->tap(fn (Builder $builder): Builder => $this->applySort($builder, $filters['sort']))
            ->get();
        abort_if($filters['matrix_id'] && $matrices->isEmpty(), 404, 'The selected M&E Matrix is outside the active scope.');

        $filename = $filters['matrix_id'] && $matrices->first()
            ? Str::slug($matrices->first()->matrix_code).'-v'.$matrices->first()->version_number.'-control-sheet.pdf'
            : 'attp-me-matrix-control-register-'.now()->format('Ymd-His').'.pdf';

        return Pdf::loadView('me.matrices.report-pdf', [
            'matrices' => $matrices,
            'metrics' => $this->metrics($matrices),
            'filters' => $filters,
            'generatedBy' => $request->user(),
            'generatedAt' => now(),
            'scopeLabel' => $this->scopeLabel($filters, $portfolios, $matrices),
            'isIndividual' => filled($filters['matrix_id']),
            'statuses' => MeMatrixVersion::STATUSES,
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'portfolio_id' => 'required|uuid|exists:myb_sectors,id',
            'title' => 'required|string|max:255',
            'matrix_code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'version_number' => 'nullable|integer|min:1|max:9999',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'change_summary' => 'required|string|max:5000',
            'matrix_file' => 'required|file|max:30720|mimes:xlsx,xls,csv,pdf',
        ]);
        $this->assertPortfolioInScope($request, $validated['portfolio_id']);

        $code = Str::upper(trim($validated['matrix_code']));
        $file = $request->file('matrix_file');
        $checksum = hash_file('sha256', $file->getRealPath());
        if (MeKnowledgeEvidenceItem::query()->where('portfolio_id', $validated['portfolio_id'])
            ->where('checksum_sha256', $checksum)->whereNull('retired_at')->exists()) {
            throw ValidationException::withMessages([
                'matrix_file' => 'This exact matrix file is already in the portfolio repository.',
            ]);
        }

        $path = $file->store('me/matrices/'.$code, 'local');
        try {
            $summary = $this->inspectMatrix($path, $file->getClientOriginalExtension());
            $matrix = DB::transaction(function () use ($validated, $request, $file, $path, $checksum, $summary, $code): MeMatrixVersion {
                $existingVersions = MeMatrixVersion::query()
                    ->where('matrix_code', $code)
                    ->lockForUpdate()
                    ->pluck('version_number');
                $version = (int) ($validated['version_number'] ?? 0);
                if ($version === 0) {
                    $version = ((int) $existingVersions->max()) + 1;
                }
                if ($existingVersions->contains($version)) {
                    throw ValidationException::withMessages([
                        'version_number' => "Version {$version} already exists for matrix {$code}.",
                    ]);
                }
                $folder = app(MeRepositoryFolderService::class)->resolve(
                    (string) $validated['portfolio_id'],
                    'M&E Matrices',
                    (string) $request->user()->id,
                    [],
                    'Uploaded and controlled M&E Matrix versions.'
                );
                $repository = MeKnowledgeEvidenceItem::query()->create([
                    'portfolio_id' => $validated['portfolio_id'],
                    'folder_id' => $folder->id,
                    'title' => trim($validated['title']),
                    'document_type' => 'me_matrix',
                    'repository_category' => 'matrix',
                    'description' => $validated['change_summary'],
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'checksum_sha256' => $checksum,
                    'version_number' => $version,
                    'validation_status' => 'pending',
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
                $repository->versions()->create([
                    'version_number' => $version,
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'checksum_sha256' => $checksum,
                    'change_notes' => $validated['change_summary'],
                    'uploaded_by' => $request->user()->id,
                ]);

                return MeMatrixVersion::query()->create([
                    'portfolio_id' => $validated['portfolio_id'],
                    'repository_item_id' => $repository->id,
                    'title' => trim($validated['title']),
                    'matrix_code' => $code,
                    'version_number' => $version,
                    'effective_from' => $validated['effective_from'] ?? null,
                    'effective_to' => $validated['effective_to'] ?? null,
                    'status' => 'draft',
                    'change_summary' => $validated['change_summary'],
                    'import_summary' => $summary,
                    'created_by' => $request->user()->id,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route('budget.me.matrices.index', ['matrix_id' => $matrix->id])
            ->with('success', "M&E Matrix {$code} version {$matrix->version_number} uploaded as a draft and synchronized with the Knowledge Repository.");
    }

    public function activate(Request $request, MeMatrixVersion $matrix)
    {
        $this->assertMatrixInScope($request, $matrix);
        abort_if($matrix->status === 'retired', 422, 'A retired matrix cannot be activated. Upload a new version.');

        DB::transaction(function () use ($matrix, $request): void {
            $previousActiveVersions = MeMatrixVersion::query()
                ->with('repositoryItem')
                ->where('matrix_code', $matrix->matrix_code)
                ->where('id', '!=', $matrix->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();
            foreach ($previousActiveVersions as $previous) {
                $previous->update(['status' => 'retired']);
                $previous->repositoryItem?->update([
                    'retired_at' => now(),
                    'retired_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
            }
            $matrix->update([
                'status' => 'active',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $matrix->repositoryItem?->update([
                'validation_status' => 'validated',
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
                'validation_notes' => 'Validated through activation of the controlled M&E Matrix version.',
                'retired_at' => null,
                'retired_by' => null,
                'updated_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('budget.me.matrices.index', ['matrix_id' => $matrix->id])
            ->with('success', "M&E Matrix {$matrix->matrix_code} version {$matrix->version_number} is now the active version.");
    }

    public function retire(Request $request, MeMatrixVersion $matrix)
    {
        $this->assertMatrixInScope($request, $matrix);
        $matrix->update(['status' => 'retired']);
        $matrix->repositoryItem?->update([
            'retired_at' => now(),
            'retired_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('budget.me.matrices.index', ['matrix_id' => $matrix->id])
            ->with('success', 'The matrix version was retired and remains available for audit history.');
    }

    public function download(Request $request, MeMatrixVersion $matrix)
    {
        $this->assertMatrixInScope($request, $matrix);
        $item = $matrix->repositoryItem;
        abort_unless($item?->file_path && Storage::disk('local')->exists($item->file_path), 404);

        return Storage::disk('local')->download($item->file_path, $item->original_filename);
    }

    public function destroy(Request $request, MeMatrixVersion $matrix)
    {
        $this->assertMatrixInScope($request, $matrix);
        if ($matrix->status !== 'draft') {
            throw ValidationException::withMessages(['matrix' => 'Only a draft matrix can be deleted. Retire active versions to preserve the audit trail.']);
        }

        $repository = $matrix->repositoryItem;
        $paths = $repository?->versions()->pluck('file_path')->push($repository->file_path)->filter()->unique()->all() ?? [];
        DB::transaction(function () use ($matrix, $repository): void {
            $matrix->delete();
            $repository?->delete();
        });
        Storage::disk('local')->delete($paths);

        return back()->with('success', 'Draft M&E Matrix version deleted.');
    }

    private function filters(Request $request): array
    {
        $status = trim((string) $request->query('status'));
        $format = Str::upper(trim((string) $request->query('format')));
        $effectiveState = trim((string) $request->query('effective_state'));
        $sort = trim((string) $request->query('sort', 'newest'));
        $perPage = (int) $request->query('per_page', 25);

        return [
            'q' => Str::limit(trim((string) $request->query('q')), 120, ''),
            'portfolio_id' => $this->uuidOrNull($request->query('portfolio_id')),
            'matrix_id' => $this->uuidOrNull($request->query('matrix_id')),
            'status' => array_key_exists($status, MeMatrixVersion::STATUSES) ? $status : null,
            'format' => in_array($format, ['XLSX', 'XLS', 'CSV', 'PDF'], true) ? $format : null,
            'effective_state' => in_array($effectiveState, ['current', 'upcoming', 'expired', 'undated'], true)
                ? $effectiveState
                : null,
            'sort' => in_array($sort, ['newest', 'oldest', 'title', 'code', 'version', 'status'], true)
                ? $sort
                : 'newest',
            'per_page' => in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 25,
        ];
    }

    private function matrixQuery(Request $request): Builder
    {
        $query = MeMatrixVersion::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request->user());
        }

        return $query;
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['portfolio_id'], fn (Builder $builder): Builder => $builder
                ->where('portfolio_id', $filters['portfolio_id']))
            ->when($filters['status'], fn (Builder $builder): Builder => $builder
                ->where('status', $filters['status']))
            ->when($filters['format'], fn (Builder $builder): Builder => $builder
                ->where('import_summary->format', $filters['format']))
            ->when($filters['effective_state'] === 'current', fn (Builder $builder): Builder => $builder
                ->where(fn (Builder $dates): Builder => $dates
                    ->whereNull('effective_from')->orWhereDate('effective_from', '<=', today()))
                ->where(fn (Builder $dates): Builder => $dates
                    ->whereNull('effective_to')->orWhereDate('effective_to', '>=', today())))
            ->when($filters['effective_state'] === 'upcoming', fn (Builder $builder): Builder => $builder
                ->whereDate('effective_from', '>', today()))
            ->when($filters['effective_state'] === 'expired', fn (Builder $builder): Builder => $builder
                ->whereDate('effective_to', '<', today()))
            ->when($filters['effective_state'] === 'undated', fn (Builder $builder): Builder => $builder
                ->whereNull('effective_from')->whereNull('effective_to'))
            ->when($filters['q'] !== '', function (Builder $builder) use ($filters): Builder {
                $like = '%'.addcslashes(mb_strtolower($filters['q']), '%_\\').'%';

                return $builder->where(function (Builder $search) use ($like): void {
                    $search->whereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(matrix_code) LIKE ?', [$like])
                        ->orWhereRaw("LOWER(COALESCE(change_summary, '')) LIKE ?", [$like])
                        ->orWhereHas('portfolio', fn (Builder $portfolio): Builder => $portfolio
                            ->whereRaw('LOWER(name) LIKE ?', [$like]))
                        ->orWhereHas('repositoryItem', fn (Builder $repository): Builder => $repository
                            ->whereRaw("LOWER(COALESCE(original_filename, '')) LIKE ?", [$like]));
                });
            });
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest('created_at')->orderBy('matrix_code')->orderBy('version_number'),
            'title' => $query->orderBy('title')->orderByDesc('version_number'),
            'code' => $query->orderBy('matrix_code')->orderByDesc('version_number'),
            'version' => $query->orderByDesc('version_number')->latest('created_at'),
            'status' => $query
                ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'draft' THEN 2 WHEN 'retired' THEN 3 ELSE 4 END")
                ->orderBy('matrix_code')->orderByDesc('version_number'),
            default => $query->latest('created_at')->orderBy('matrix_code'),
        };
    }

    private function metrics(Collection $matrices): array
    {
        $inspection = $matrices->map(fn (MeMatrixVersion $matrix): array => $matrix->inspectionTotals());
        $codes = $matrices->pluck('matrix_code')->filter()->unique();
        $activeCodes = $matrices->where('status', 'active')->pluck('matrix_code')->filter()->unique();

        return [
            'total' => $matrices->count(),
            'active' => $matrices->where('status', 'active')->count(),
            'draft' => $matrices->where('status', 'draft')->count(),
            'retired' => $matrices->where('status', 'retired')->count(),
            'codes' => $codes->count(),
            'portfolios' => $matrices->pluck('portfolio_id')->filter()->unique()->count(),
            'active_coverage' => $codes->isNotEmpty()
                ? round(($activeCodes->count() / $codes->count()) * 100, 1)
                : 0.0,
            'storage_bytes' => (int) $matrices->sum(fn (MeMatrixVersion $matrix): int => (int) $matrix->repositoryItem?->file_size),
            'sheets' => (int) $inspection->sum('sheet_count'),
            'rows' => (int) $inspection->sum('data_rows'),
            'formulas' => (int) $inspection->sum('formula_cells'),
            'validations' => (int) $inspection->sum('validated_cells'),
        ];
    }

    private function charts(Collection $matrices): array
    {
        $statusColors = ['active' => '#187459', 'draft' => '#b8791f', 'retired' => '#73838a'];
        $status = collect(MeMatrixVersion::STATUSES)->map(function (string $label, string $key) use ($matrices, $statusColors): array {
            return [
                'key' => $key,
                'label' => $label,
                'count' => $matrices->where('status', $key)->count(),
                'color' => $statusColors[$key],
            ];
        })->values();

        $formatColors = ['XLSX' => '#075c7a', 'XLS' => '#438ca2', 'CSV' => '#6b63a8', 'PDF' => '#ae4d49'];
        $formats = collect($formatColors)->map(function (string $color, string $format) use ($matrices): array {
            return [
                'key' => $format,
                'label' => $format,
                'count' => $matrices->filter(fn (MeMatrixVersion $matrix): bool => $matrix->formatLabel() === $format)->count(),
                'color' => $color,
            ];
        })->values();

        $portfolios = $matrices->groupBy(fn (MeMatrixVersion $matrix): string => (string) ($matrix->portfolio_id ?: 'unassigned'))
            ->map(function (Collection $versions): array {
                $matrix = $versions->first();

                return [
                    'key' => (string) ($matrix->portfolio_id ?: 'unassigned'),
                    'label' => $matrix->portfolio?->name ?: 'Portfolio unavailable',
                    'count' => $versions->count(),
                ];
            })->sortByDesc('count')->take(8)->values();

        $activity = collect();
        $start = now()->startOfMonth()->subMonths(11);
        for ($offset = 0; $offset < 12; $offset++) {
            $month = $start->copy()->addMonths($offset);
            $activity->push([
                'key' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'count' => $matrices->filter(fn (MeMatrixVersion $matrix): bool => $matrix->created_at?->format('Y-m') === $month->format('Y-m'))->count(),
            ]);
        }

        return compact('status', 'formats', 'portfolios', 'activity');
    }

    private function scopeLabel(array $filters, Collection $portfolios, Collection $matrices): string
    {
        if ($filters['matrix_id'] && $matrices->first()) {
            $matrix = $matrices->first();

            return $matrix->matrix_code.' · Version '.$matrix->version_number;
        }

        $parts = [];
        if ($filters['portfolio_id']) {
            $parts[] = $portfolios->firstWhere('id', $filters['portfolio_id'])?->name ?: 'Selected portfolio';
        }
        if ($filters['status']) {
            $parts[] = MeMatrixVersion::STATUSES[$filters['status']];
        }
        if ($filters['format']) {
            $parts[] = $filters['format'].' format';
        }
        if ($filters['effective_state']) {
            $parts[] = Str::headline($filters['effective_state']).' dates';
        }
        if ($filters['q'] !== '') {
            $parts[] = 'Search: '.$filters['q'];
        }

        return $parts === [] ? 'All authorized matrix versions' : implode(' · ', $parts);
    }

    private function assertAuthorizedPortfolio(?string $portfolioId, Collection $portfolios): void
    {
        if ($portfolioId && ! $portfolios->contains(fn ($portfolio): bool => (string) $portfolio->id === $portfolioId)) {
            abort(403, 'You do not have access to the selected portfolio.');
        }
    }

    private function uuidOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return Str::isUuid($value) ? $value : null;
    }

    private function inspectMatrix(string $path, string $extension): array
    {
        if (strtolower($extension) === 'pdf') {
            return ['format' => 'PDF', 'message' => 'PDF retained for viewing; spreadsheet structure inspection is not applicable.'];
        }

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));
        $sheets = [];
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $lastColumn = $worksheet->getHighestDataColumn();
            $lastRow = $worksheet->getHighestDataRow();
            $columns = Coordinate::columnIndexFromString($lastColumn);
            $formulas = 0;
            $validations = 0;
            $inspectionLimited = ($lastRow * $columns) > 20000;
            if (! $inspectionLimited) {
                foreach ($worksheet->getCellCollection()->getCoordinates() as $coordinate) {
                    $cell = $worksheet->getCell($coordinate);
                    if ($cell->isFormula()) {
                        $formulas++;
                    }
                    if ($cell->getDataValidation()->getType() !== \PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_NONE) {
                        $validations++;
                    }
                }
            }
            $sheets[] = [
                'name' => $worksheet->getTitle(),
                'data_rows' => $lastRow,
                'data_columns' => $columns,
                'formula_cells' => $formulas,
                'validated_cells' => $validations,
                'inspection_limited' => $inspectionLimited,
            ];
        }
        $spreadsheet->disconnectWorksheets();

        return ['format' => Str::upper($extension), 'sheet_count' => count($sheets), 'sheets' => $sheets];
    }

    private function assertMatrixInScope(Request $request, MeMatrixVersion $matrix): void
    {
        if ($this->userHasAssignedPortfolioScope($request->user())
            && ! $this->portfolioOwnedRecordIsInAssignedPortfolio($matrix, $request->user())) {
            abort(403, 'You do not have access to this M&E Matrix.');
        }
    }

    private function assertPortfolioInScope(Request $request, string $portfolioId): void
    {
        if ($this->userHasAssignedPortfolioScope($request->user())
            && ! in_array($portfolioId, $this->assignedPortfolioIds($request->user()), true)) {
            abort(403, 'You do not have access to the selected portfolio.');
        }
    }
}
