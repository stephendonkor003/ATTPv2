<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\MeMatrixVersion;
use App\Models\Sector;
use App\Services\MeRepositoryFolderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MeMatrixController extends Controller
{
    use ScopesAssignedPortfolios;

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.configuration.view|me.configuration.manage')->only(['index', 'download']);
        $this->middleware('permission:me.configuration.manage')->except(['index', 'download']);
    }

    public function index(Request $request)
    {
        $query = MeMatrixVersion::query()
            ->with(['portfolio:id,name', 'repositoryItem', 'createdBy:id,name'])
            ->latest('created_at');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request->user());
        }

        $portfolios = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($portfolios, $request->user());
        }

        return view('me.matrices.index', [
            'matrices' => $query->paginate(15),
            'portfolios' => $portfolios->get(['id', 'name']),
            'statuses' => MeMatrixVersion::STATUSES,
        ]);
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
        $version = (int) ($validated['version_number'] ?? 0);
        if ($version === 0) {
            $version = ((int) MeMatrixVersion::query()->where('matrix_code', $code)->max('version_number')) + 1;
        }
        if (MeMatrixVersion::query()->where('matrix_code', $code)->where('version_number', $version)->exists()) {
            throw ValidationException::withMessages([
                'version_number' => "Version {$version} already exists for matrix {$code}.",
            ]);
        }

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
            $folder = app(MeRepositoryFolderService::class)->resolve(
                (string) $validated['portfolio_id'],
                'M&E Matrices',
                (string) $request->user()->id,
                [],
                'Uploaded and controlled M&E Matrix versions.'
            );
            DB::transaction(function () use ($validated, $request, $file, $path, $checksum, $summary, $code, $version, $folder): void {
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
                MeMatrixVersion::query()->create([
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

        return back()->with('success', "M&E Matrix {$code} version {$version} uploaded as a draft and synchronized with the Knowledge Repository.");
    }

    public function activate(Request $request, MeMatrixVersion $matrix)
    {
        $this->assertMatrixInScope($request, $matrix);
        abort_if($matrix->status === 'retired', 422, 'A retired matrix cannot be activated. Upload a new version.');

        DB::transaction(function () use ($matrix, $request): void {
            MeMatrixVersion::query()
                ->where('matrix_code', $matrix->matrix_code)
                ->where('id', '!=', $matrix->id)
                ->where('status', 'active')
                ->update(['status' => 'retired']);
            $matrix->update([
                'status' => 'active',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $matrix->repositoryItem?->update([
                'validation_status' => 'validated',
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', "M&E Matrix {$matrix->matrix_code} version {$matrix->version_number} is now the active version.");
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

        return back()->with('success', 'The matrix version was retired and remains available for audit history.');
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
            if (($lastRow * $columns) <= 20000) {
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
