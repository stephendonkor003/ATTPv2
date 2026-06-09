<?php

namespace App\Http\Controllers;

use App\Models\DataWarehouseCategory;
use App\Models\DataWarehouseFile;
use App\Models\DataWarehouseRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DataWarehouseController extends Controller
{
    public function index()
    {
        $records = DataWarehouseRecord::with(['category', 'files', 'creator'])
            ->latest()
            ->paginate(15);

        $stats = [
            'records' => DataWarehouseRecord::count(),
            'files' => DataWarehouseFile::count(),
            'categories' => DataWarehouseCategory::count(),
            'size' => DataWarehouseFile::sum('size'),
        ];

        return view('data-warehouse.index', compact('records', 'stats'));
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
            'files.required' => 'Upload at least one historical data file.',
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
