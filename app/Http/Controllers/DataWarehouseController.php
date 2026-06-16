<?php

namespace App\Http\Controllers;

use App\Models\DataWarehouseCategory;
use App\Models\DataWarehouseFile;
use App\Models\DataWarehouseRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DataWarehouseController extends Controller
{
    private array $knowledgeUserCache = [];

    public function index(Request $request)
    {
        [, $modules, $stats] = $this->knowledgeLibraryPayload();

        return view('data-warehouse.index', compact('modules', 'stats'));
    }

    public function showModule(Request $request, string $module)
    {
        [$allFiles, $modules, $stats] = $this->knowledgeLibraryPayload();

        $selectedModule = $modules->firstWhere('slug', $module);
        abort_unless($selectedModule, 404, 'Knowledge folder not found.');

        $files = $allFiles
            ->where('module', $selectedModule['slug'])
            ->sortByDesc(fn (array $file) => $file['uploaded_at'] ?? '')
            ->values();

        return view('data-warehouse.module', compact('modules', 'selectedModule', 'files', 'stats'));
    }

    private function knowledgeLibraryPayload(): array
    {
        $allFiles = $this->knowledgeFiles();
        $definitions = collect($this->knowledgeModules());

        $modules = $definitions
            ->map(function (array $definition, string $slug) use ($allFiles) {
                $files = $allFiles->where('module', $slug);

                return array_merge($definition, [
                    'slug' => $slug,
                    'files_count' => $files->count(),
                    'stored_size' => (int) $files->sum('size'),
                    'latest_upload' => $files->max('uploaded_at'),
                ]);
            })
            ->values();

        $stats = [
            'modules' => $modules->count(),
            'files' => $allFiles->count(),
            'size' => (int) $allFiles->sum('size'),
            'latest_upload' => $allFiles->max('uploaded_at'),
            'records' => DataWarehouseRecord::count(),
        ];

        return [$allFiles, $modules, $stats];
    }

    public function create()
    {
        $categories = DataWarehouseCategory::where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('data-warehouse.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:data_warehouse_categories,id'],
            'new_category_name' => ['nullable', 'string', 'max:255'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'reference_period' => ['nullable', 'string', 'max:100'],
            'data_owner' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:draft,published,archived'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'max:102400'],
            'file_titles' => ['nullable', 'array'],
            'file_titles.*' => ['nullable', 'string', 'max:255'],
            'file_descriptions' => ['nullable', 'array'],
            'file_descriptions.*' => ['nullable', 'string', 'max:1000'],
        ], [
            'files.required' => 'Upload at least one knowledge file.',
            'files.*.max' => 'Each data file must be 100 MB or smaller.',
        ]);

        $category = $this->resolveCategory($validated);

        $record = DB::transaction(function () use ($request, $validated, $category) {
            $record = DataWarehouseRecord::create([
                'category_id' => $category?->id,
                'title' => $validated['title'],
                'source_name' => $validated['source_name'] ?? null,
                'reference_period' => $validated['reference_period'] ?? null,
                'data_owner' => $validated['data_owner'] ?? null,
                'tags' => $this->parseTags($validated['tags'] ?? null),
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'created_by' => $request->user()?->id,
            ]);

            foreach ($request->file('files', []) as $index => $uploadedFile) {
                $originalName = $uploadedFile->getClientOriginalName();
                $safeName = now()->format('YmdHis') . '-' . Str::random(8) . '-' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
                $extension = $uploadedFile->getClientOriginalExtension();
                $fileName = $extension ? $safeName . '.' . $extension : $safeName;
                $path = $uploadedFile->storeAs('data-warehouse/' . now()->format('Y/m'), $fileName, 'public');

                DataWarehouseFile::create([
                    'record_id' => $record->id,
                    'title' => $validated['file_titles'][$index] ?? pathinfo($originalName, PATHINFO_FILENAME),
                    'description' => $validated['file_descriptions'][$index] ?? null,
                    'original_name' => $originalName,
                    'path' => $path,
                    'disk' => 'public',
                    'mime_type' => $uploadedFile->getClientMimeType(),
                    'size' => $uploadedFile->getSize() ?: 0,
                    'uploaded_by' => $request->user()?->id,
                ]);
            }

            return $record;
        });

        return redirect()
            ->route('data-warehouse.show', $record)
            ->with('success', 'Historical data record uploaded successfully.');
    }

    public function show(DataWarehouseRecord $record)
    {
        $record->load(['category', 'files.uploader', 'creator']);

        return view('data-warehouse.show', compact('record'));
    }

    public function categories()
    {
        $categories = DataWarehouseCategory::withCount('records')
            ->orderBy('name')
            ->get();

        return view('data-warehouse.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:data_warehouse_categories,code'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        DataWarehouseCategory::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?: $this->uniqueCategoryCode($validated['name']),
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Data category created successfully.');
    }

    public function download(DataWarehouseFile $file)
    {
        if (! Storage::disk($file->disk)->exists($file->path)) {
            return back()->with('error', 'The stored file could not be found.');
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function libraryFile(Request $request, string $token)
    {
        $payload = $this->decodeFileToken($token);
        abort_unless($payload, 404, 'File reference could not be read.');

        $disk = $payload['disk'] ?? 'local';
        $path = $payload['path'] ?? null;
        $name = $payload['name'] ?? ($path ? basename($path) : 'document');

        abort_unless($path && Storage::disk($disk)->exists($path), 404, 'File not found.');

        if ($request->query('download') === '1') {
            return Storage::disk($disk)->download($path, $name);
        }

        $mimeType = $payload['mime_type'] ?: Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';

        return Storage::disk($disk)->response($path, $name, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
        ]);
    }

    private function knowledgeModules(): array
    {
        return [
            'think-tank-management' => [
                'label' => 'Think Tank Management',
                'description' => 'Research outputs, consortium evidence, reports, and think tank portal documents.',
                'accent' => '#0f766e',
            ],
            'governance-setup' => [
                'label' => 'Governance Setup',
                'description' => 'Governance, treaty, member-state communication, and setup documents.',
                'accent' => '#2563eb',
            ],
            'budget-structure' => [
                'label' => 'Budget Structure',
                'description' => 'Program funding, funder, allocation, and budget support documents.',
                'accent' => '#7c3aed',
            ],
            'execution-commitment' => [
                'label' => 'Execution & Commitment',
                'description' => 'Purchase request, commitment, and execution support attachments.',
                'accent' => '#0ea5e9',
            ],
            'work-plan-registry' => [
                'label' => 'Work Plan Registry',
                'description' => 'Approved work plan files, TORs, concept notes, and registry evidence.',
                'accent' => '#16a34a',
            ],
            'monitoring-evaluation' => [
                'label' => 'M&E',
                'description' => 'Monitoring, evaluation, data source, survey, and proof files.',
                'accent' => '#db2777',
            ],
            'procurement' => [
                'label' => 'Procurement',
                'description' => 'Procurement opportunities, contracts, POs, disbursement evidence, and submissions.',
                'accent' => '#ea580c',
            ],
            'vendor-management' => [
                'label' => 'Vendor Management',
                'description' => 'Vendor profile, invoice, message, category, and supplier documents.',
                'accent' => '#0891b2',
            ],
            'prescreening-evaluation' => [
                'label' => 'Prescreening & Evaluation',
                'description' => 'Prescreening submissions, evaluation records, proof videos, and supporting files.',
                'accent' => '#9333ea',
            ],
            'site-visit' => [
                'label' => 'Site Visit',
                'description' => 'Site visit media, observation documents, reports, and visit evidence.',
                'accent' => '#65a30d',
            ],
            'knowledge-archive' => [
                'label' => 'Knowledge Archive',
                'description' => 'Manually uploaded knowledge records and cross-module reference files.',
                'accent' => '#334155',
            ],
        ];
    }

    private function knowledgeFiles(): Collection
    {
        $knownFiles = collect($this->knownFileSources())
            ->flatMap(fn (array $source) => $this->filesFromTableSource($source))
            ->merge($this->filesFromJsonEvidence())
            ->merge($this->filesFromFormSubmissionValues())
            ->filter()
            ->unique(fn (array $file) => $file['disk'] . ':' . $file['path'])
            ->values();

        $knownKeys = $knownFiles
            ->mapWithKeys(fn (array $file) => [$file['disk'] . ':' . $file['path'] => true])
            ->all();

        return $knownFiles
            ->merge($this->filesFromStorageScan($knownKeys))
            ->unique(fn (array $file) => $file['disk'] . ':' . $file['path'])
            ->values();
    }

    private function knownFileSources(): array
    {
        return [
            [
                'table' => 'data_warehouse_files',
                'module' => 'knowledge-archive',
                'path_column' => 'path',
                'disk' => 'public',
                'name_column' => 'original_name',
                'title_column' => 'title',
                'description_column' => 'description',
                'mime_column' => 'mime_type',
                'size_column' => 'size',
                'uploaded_by_column' => 'uploaded_by',
                'date_column' => 'created_at',
                'source' => 'Knowledge record',
            ],
            [
                'table' => 'myb_purchase_request_attachments',
                'module' => 'execution-commitment',
                'path_column' => 'file_path',
                'disk' => 'local',
                'name_column' => 'file_name',
                'title_column' => 'title',
                'description_column' => 'document_type',
                'mime_column' => 'mime_type',
                'size_column' => 'file_size_bytes',
                'uploaded_by_column' => 'uploaded_by',
                'date_column' => 'created_at',
                'source' => 'Purchase request attachment',
            ],
            [
                'table' => 'myb_program_funding_documents',
                'module' => 'budget-structure',
                'path_column' => 'file_path',
                'disk' => 'local',
                'name_column' => 'file_name',
                'title_column' => 'document_type',
                'description_column' => 'description',
                'uploaded_by_column' => 'uploaded_by',
                'date_column' => 'created_at',
                'source' => 'Program funding document',
            ],
            [
                'table' => 'myb_treaty_supporting_documents',
                'module' => 'governance-setup',
                'path_column' => 'file_path',
                'disk' => 'local',
                'name_column' => 'file_name',
                'title_column' => 'title',
                'description_column' => 'document_type',
                'uploaded_by_column' => 'uploaded_by',
                'date_column' => 'created_at',
                'source' => 'Treaty supporting document',
            ],
            [
                'table' => 'myb_member_state_communication_attachments',
                'module' => 'governance-setup',
                'path_column' => 'file_path',
                'disk' => 'local',
                'name_column' => 'file_name',
                'mime_column' => 'mime_type',
                'size_column' => 'file_size_bytes',
                'uploaded_by_column' => 'uploaded_by',
                'date_column' => 'created_at',
                'source' => 'Member-state communication',
            ],
            [
                'table' => 'procurement_contract_documents',
                'module' => 'procurement',
                'path_column' => 'file_path',
                'disk' => 'local',
                'name_column' => 'file_name',
                'mime_column' => 'mime_type',
                'size_column' => 'file_size',
                'uploaded_by_column' => 'uploaded_by',
                'date_column' => 'created_at',
                'source' => 'Procurement contract document',
            ],
            [
                'table' => 'procurement_purchase_orders',
                'module' => 'procurement',
                'path_column' => 'supporting_document_path',
                'disk' => 'local',
                'name_column' => 'supporting_document_name',
                'title_column' => 'po_title',
                'mime_column' => 'supporting_document_mime_type',
                'size_column' => 'supporting_document_size',
                'uploaded_by_column' => 'created_by',
                'date_column' => 'created_at',
                'source' => 'Purchase order support',
            ],
            [
                'table' => 'site_visit_medias',
                'module' => 'site-visit',
                'path_column' => 'file_path',
                'disk' => 'local',
                'mime_column' => 'file_type',
                'uploaded_by_column' => 'uploaded_by',
                'date_column' => 'created_at',
                'source' => 'Site visit media',
            ],
            [
                'table' => 'attp_think_tank_research_outputs',
                'module' => 'think-tank-management',
                'path_column' => 'file_path',
                'disk' => 'local',
                'title_column' => 'title',
                'description_column' => 'abstract',
                'uploaded_by_column' => 'submitted_by',
                'date_column' => 'submitted_at',
                'source' => 'Think tank research output',
            ],
            [
                'table' => 'attp_report_evidence',
                'module' => 'think-tank-management',
                'path_column' => 'file_path',
                'disk' => 'local',
                'name_column' => 'file_name',
                'title_column' => 'title',
                'description_column' => 'evidence_type',
                'mime_column' => 'mime_type',
                'size_column' => 'file_size_bytes',
                'uploaded_by_column' => 'uploaded_by',
                'date_column' => 'created_at',
                'source' => 'Consortium report evidence',
            ],
            [
                'table' => 'attp_news_attachments',
                'module' => 'knowledge-archive',
                'path_column' => 'file_path',
                'disk' => 'local',
                'name_column' => 'file_name',
                'title_column' => 'title',
                'mime_column' => 'mime_type',
                'size_column' => 'file_size_bytes',
                'uploaded_by_column' => 'uploaded_by',
                'date_column' => 'created_at',
                'source' => 'Published communication attachment',
            ],
        ];
    }

    private function filesFromTableSource(array $source): Collection
    {
        if (! Schema::hasTable($source['table']) || ! Schema::hasColumn($source['table'], $source['path_column'])) {
            return collect();
        }

        $dateColumn = $source['date_column'] ?? 'created_at';
        $query = DB::table($source['table'])
            ->whereNotNull($source['path_column'])
            ->where($source['path_column'], '<>', '');

        if (Schema::hasColumn($source['table'], $dateColumn)) {
            $query->orderByDesc($dateColumn);
        }

        return $query->limit(2000)
            ->get()
            ->map(fn ($row) => $this->fileFromRow($row, $source))
            ->filter()
            ->values();
    }

    private function fileFromRow(object $row, array $source): ?array
    {
        $path = trim((string) ($row->{$source['path_column']} ?? ''));
        if ($path === '') {
            return null;
        }

        $location = $this->resolveStoredLocation($source['disk'] ?? 'local', $path);
        if (! $location) {
            return null;
        }

        [$disk, $storedPath] = $location;
        $name = $this->valueFromColumn($row, $source['name_column'] ?? null) ?: basename($storedPath);
        $title = $this->valueFromColumn($row, $source['title_column'] ?? null) ?: pathinfo($name, PATHINFO_FILENAME);
        $description = $this->valueFromColumn($row, $source['description_column'] ?? null);
        $mimeType = $this->valueFromColumn($row, $source['mime_column'] ?? null) ?: $this->safeMimeType($disk, $storedPath);
        $size = (int) ($this->valueFromColumn($row, $source['size_column'] ?? null) ?: $this->safeFileSize($disk, $storedPath));
        $uploadedBy = $this->userName($this->valueFromColumn($row, $source['uploaded_by_column'] ?? null));
        $uploadedAt = $this->valueFromColumn($row, $source['date_column'] ?? null) ?: ($this->safeLastModified($disk, $storedPath)?->toDateTimeString());

        return $this->knowledgeFile([
            'module' => $source['module'],
            'title' => $title,
            'description' => $description,
            'source' => $source['source'] ?? Str::headline(str_replace('_', ' ', $source['table'])),
            'original_name' => $name,
            'disk' => $disk,
            'path' => $storedPath,
            'mime_type' => $mimeType,
            'size' => $size,
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => $uploadedAt,
        ]);
    }

    private function filesFromJsonEvidence(): Collection
    {
        if (! Schema::hasTable('procurement_purchase_order_item_evidence') || ! Schema::hasColumn('procurement_purchase_order_item_evidence', 'documents')) {
            return collect();
        }

        return DB::table('procurement_purchase_order_item_evidence')
            ->whereNotNull('documents')
            ->limit(2000)
            ->get()
            ->flatMap(function ($row) {
                $documents = json_decode((string) $row->documents, true);
                if (! is_array($documents)) {
                    return [];
                }

                return collect($documents)
                    ->filter(fn ($document) => is_array($document) && ! empty($document['path']))
                    ->map(function (array $document) use ($row) {
                        $location = $this->resolveStoredLocation('local', (string) $document['path']);
                        if (! $location) {
                            return null;
                        }

                        [$disk, $path] = $location;
                        $name = (string) ($document['name'] ?? basename($path));
                        $title = (string) ($document['display_name'] ?? pathinfo($name, PATHINFO_FILENAME));

                        return $this->knowledgeFile([
                            'module' => 'procurement',
                            'title' => $title,
                            'description' => $row->notes ?? 'Line item evidence',
                            'source' => 'PO line item evidence',
                            'original_name' => $name,
                            'disk' => $disk,
                            'path' => $path,
                            'mime_type' => $document['mime_type'] ?? $this->safeMimeType($disk, $path),
                            'size' => (int) ($document['size'] ?? $this->safeFileSize($disk, $path)),
                            'uploaded_by' => $this->userName($document['uploaded_by'] ?? $row->created_by ?? null),
                            'uploaded_at' => $document['uploaded_at'] ?? $row->created_at ?? null,
                        ]);
                    })
                    ->filter()
                    ->all();
            })
            ->values();
    }

    private function filesFromFormSubmissionValues(): Collection
    {
        if (! Schema::hasTable('form_submission_values') || ! Schema::hasTable('form_submissions')) {
            return collect();
        }

        return DB::table('form_submission_values as values')
            ->join('form_submissions as submissions', 'submissions.id', '=', 'values.submission_id')
            ->select([
                'values.value',
                'values.field_key',
                'values.created_at',
                'submissions.submitted_by',
                'submissions.procurement_submission_code',
            ])
            ->whereNotNull('values.value')
            ->limit(2000)
            ->get()
            ->map(function ($row) {
                $path = trim((string) $row->value);
                if ($path === '' || ! Str::startsWith($path, ['procurement_submissions/', 'public/procurement_submissions/'])) {
                    return null;
                }

                $location = $this->resolveStoredLocation('local', $path);
                if (! $location) {
                    return null;
                }

                [$disk, $storedPath] = $location;
                $name = basename($storedPath);

                return $this->knowledgeFile([
                    'module' => 'prescreening-evaluation',
                    'title' => Str::headline(str_replace('_', ' ', (string) $row->field_key)),
                    'description' => $row->procurement_submission_code,
                    'source' => 'Procurement submission file',
                    'original_name' => $name,
                    'disk' => $disk,
                    'path' => $storedPath,
                    'mime_type' => $this->safeMimeType($disk, $storedPath),
                    'size' => $this->safeFileSize($disk, $storedPath),
                    'uploaded_by' => $this->userName($row->submitted_by ?? null),
                    'uploaded_at' => $row->created_at ?? null,
                ]);
            })
            ->filter()
            ->values();
    }

    private function filesFromStorageScan(array $knownKeys): Collection
    {
        $files = collect();

        foreach (['local', 'public'] as $disk) {
            foreach (Storage::disk($disk)->allFiles() as $path) {
                $path = str_replace('\\', '/', $path);
                if ($disk === 'local' && Str::startsWith($path, 'public/')) {
                    continue;
                }

                $module = $this->moduleForStoragePath($path);
                if (! $module) {
                    continue;
                }

                $key = $disk . ':' . $path;
                if (isset($knownKeys[$key])) {
                    continue;
                }

                $files->push($this->knowledgeFile([
                    'module' => $module,
                    'title' => pathinfo($path, PATHINFO_FILENAME),
                    'description' => Str::headline(str_replace(['-', '_', '/'], ' ', dirname($path))),
                    'source' => 'Stored upload folder',
                    'original_name' => basename($path),
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $this->safeMimeType($disk, $path),
                    'size' => $this->safeFileSize($disk, $path),
                    'uploaded_by' => null,
                    'uploaded_at' => $this->safeLastModified($disk, $path)?->toDateTimeString(),
                ]));
            }
        }

        return $files;
    }

    private function moduleForStoragePath(string $path): ?string
    {
        $path = Str::lower(str_replace('\\', '/', $path));

        return match (true) {
            Str::contains($path, ['think-tank-research', 'consortium-evidence', 'consortium-expenses', 'attp_report']) => 'think-tank-management',
            Str::contains($path, ['treaties/', 'member-state-communication', 'governance']) => 'governance-setup',
            Str::contains($path, ['program-funding-documents', 'funders/logos', 'budget']) => 'budget-structure',
            Str::contains($path, ['purchase-requests/', 'commitment']) => 'execution-commitment',
            Str::contains($path, ['approved-work-plan', 'work-plan', 'tor', 'concept-note']) => 'work-plan-registry',
            Str::contains($path, ['me/', 'm-e', 'monitoring', 'survey']) => 'monitoring-evaluation',
            Str::contains($path, ['procurement_purchase_orders', 'procurement_contract', 'procurement_submissions', 'procurement/']) => 'procurement',
            Str::contains($path, ['vendor', 'supplier']) => 'vendor-management',
            Str::contains($path, ['evaluation_proofs', 'evaluation', 'prescreening']) => 'prescreening-evaluation',
            Str::contains($path, ['site-visits', 'site_visit']) => 'site-visit',
            Str::contains($path, ['data-warehouse']) => 'knowledge-archive',
            default => null,
        };
    }

    private function knowledgeFile(array $file): array
    {
        $file['extension'] = Str::upper(pathinfo($file['original_name'] ?? $file['path'], PATHINFO_EXTENSION) ?: 'FILE');
        $file['token'] = $this->encodeFileToken([
            'disk' => $file['disk'],
            'path' => $file['path'],
            'name' => $file['original_name'] ?? basename($file['path']),
            'mime_type' => $file['mime_type'] ?? null,
        ]);
        $file['view_url'] = route('data-warehouse.library.file', $file['token']);
        $file['download_url'] = route('data-warehouse.library.file', ['token' => $file['token'], 'download' => 1]);

        return $file;
    }

    private function resolveStoredLocation(string $preferredDisk, string $path): ?array
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $candidates = array_values(array_unique([$preferredDisk, 'local', 'public']));

        foreach ($candidates as $disk) {
            $candidatePath = $path;
            if ($disk === 'public' && Str::startsWith($candidatePath, 'public/')) {
                $candidatePath = Str::after($candidatePath, 'public/');
            }

            if (Storage::disk($disk)->exists($candidatePath)) {
                return [$disk, $candidatePath];
            }
        }

        if (Str::startsWith($path, 'storage/') && Storage::disk('public')->exists(Str::after($path, 'storage/'))) {
            return ['public', Str::after($path, 'storage/')];
        }

        return null;
    }

    private function valueFromColumn(object $row, ?string $column): mixed
    {
        return $column && property_exists($row, $column) ? $row->{$column} : null;
    }

    private function safeMimeType(string $disk, string $path): ?string
    {
        try {
            return Storage::disk($disk)->mimeType($path) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeFileSize(string $disk, string $path): int
    {
        try {
            return (int) Storage::disk($disk)->size($path);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeLastModified(string $disk, string $path): ?\Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::createFromTimestamp(Storage::disk($disk)->lastModified($path));
        } catch (\Throwable) {
            return null;
        }
    }

    private function userName(mixed $userId): ?string
    {
        $userId = trim((string) $userId);
        if ($userId === '') {
            return null;
        }

        if (! array_key_exists($userId, $this->knowledgeUserCache)) {
            $this->knowledgeUserCache[$userId] = Schema::hasTable('users')
                ? DB::table('users')->where('id', $userId)->value('name')
                : null;
        }

        return $this->knowledgeUserCache[$userId];
    }

    private function encodeFileToken(array $payload): string
    {
        return rtrim(strtr(base64_encode(Crypt::encryptString(json_encode($payload))), '+/', '-_'), '=');
    }

    private function decodeFileToken(string $token): ?array
    {
        try {
            $encrypted = base64_decode(strtr($token, '-_', '+/'), true);
            if ($encrypted === false) {
                return null;
            }

            $payload = json_decode(Crypt::decryptString($encrypted), true);
            return is_array($payload) ? $payload : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveCategory(array $validated): ?DataWarehouseCategory
    {
        $newCategoryName = trim((string) ($validated['new_category_name'] ?? ''));
        if ($newCategoryName !== '') {
            return DataWarehouseCategory::firstOrCreate(
                ['name' => $newCategoryName],
                [
                    'code' => $this->uniqueCategoryCode($newCategoryName),
                    'status' => 'active',
                    'created_by' => auth()->id(),
                ]
            );
        }

        if (!empty($validated['category_id'])) {
            return DataWarehouseCategory::find($validated['category_id']);
        }

        return null;
    }

    private function parseTags(?string $tags): array
    {
        if (!$tags) {
            return [];
        }

        return collect(explode(',', $tags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function uniqueCategoryCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_')) ?: 'DATA';
        $code = $base;
        $counter = 1;

        while (DataWarehouseCategory::where('code', $code)->exists()) {
            $counter++;
            $code = $base . '_' . $counter;
        }

        return $code;
    }
}
