<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\FormSubmissionValue;
use App\Models\User;
use App\Models\VendorDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorKnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->vendor($request);
        $type = $request->string('type')->toString();
        $search = trim((string) $request->input('q'));

        $documents = VendorDocument::query()
            ->where('user_id', $user->id)
            ->when($type !== '', fn ($query) => $query->where('document_type', $type))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('file_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get()
            ->map(fn (VendorDocument $document) => [
                'kind' => 'vendor_document',
                'id' => $document->id,
                'title' => $document->title,
                'file_name' => $document->file_name,
                'document_type' => $document->document_type ?: Str::headline($document->source_type),
                'source' => Str::headline(str_replace('_', ' ', $document->source_type)),
                'uploaded_at' => $document->created_at,
                'size' => $document->file_size_bytes,
                'download_url' => route('vendor.knowledge.download', $document),
            ]);

        $submissionFiles = $this->submissionFiles($user, $search, $type);

        $library = $documents
            ->merge($submissionFiles)
            ->sortByDesc(fn ($item) => $item['uploaded_at']?->timestamp ?? 0)
            ->values();

        $types = $library->pluck('document_type')->filter()->unique()->sort()->values();

        return view('vendor.knowledge.index', compact('library', 'types', 'type', 'search'));
    }

    public function store(Request $request)
    {
        $user = $this->vendor($request);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'document_type' => 'required|string|max:80',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|string|max:500',
            'document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip|max:20480',
        ]);

        $file = $request->file('document');
        $path = $file->store("vendor-documents/{$user->id}/knowledge", 'local');

        VendorDocument::create([
            'user_id' => $user->id,
            'uploaded_by' => $user->id,
            'source_type' => 'manual_upload',
            'title' => $data['title'],
            'document_type' => $data['document_type'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
            'tags' => collect(explode(',', (string) ($data['tags'] ?? '')))
                ->map(fn ($tag) => trim($tag))
                ->filter()
                ->values()
                ->all(),
        ]);

        return back()->with('success', 'Document added to your knowledge library.');
    }

    public function download(Request $request, VendorDocument $document)
    {
        $user = $this->vendor($request);
        abort_unless((string) $document->user_id === (string) $user->id, 403);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function downloadSubmissionFile(Request $request, FormSubmissionValue $value)
    {
        $user = $this->vendor($request);
        $value->load('submission');

        abort_unless($value->submission && (string) $value->submission->submitted_by === (string) $user->id, 403);

        $path = trim((string) $value->value);
        abort_unless(Str::startsWith($path, ['procurement_submissions/', 'public/procurement_submissions/']), 404);

        $location = $this->resolveStoredLocation($path);
        abort_unless($location, 404);

        [$disk, $storedPath] = $location;

        return Storage::disk($disk)->download($storedPath, basename($storedPath));
    }

    private function submissionFiles(User $user, string $search, string $type)
    {
        return FormSubmissionValue::with('submission.procurement')
            ->whereHas('submission', fn ($query) => $query->where('submitted_by', $user->id))
            ->whereNotNull('value')
            ->latest()
            ->get()
            ->filter(fn (FormSubmissionValue $value) => Str::startsWith(trim((string) $value->value), ['procurement_submissions/', 'public/procurement_submissions/']))
            ->map(function (FormSubmissionValue $value) {
                $path = trim((string) $value->value);
                $location = $this->resolveStoredLocation($path);
                $storedPath = $location[1] ?? $path;

                return [
                    'kind' => 'form_submission',
                    'id' => $value->id,
                    'title' => Str::headline(str_replace('_', ' ', (string) $value->field_key)),
                    'file_name' => basename($storedPath),
                    'document_type' => 'Procurement Submission',
                    'source' => $value->submission?->procurement?->title ?: $value->submission?->procurement_submission_code ?: 'Submitted Form',
                    'uploaded_at' => $value->created_at,
                    'size' => $location ? Storage::disk($location[0])->size($storedPath) : null,
                    'download_url' => route('vendor.knowledge.submission-file', $value),
                ];
            })
            ->filter(function ($item) use ($search, $type) {
                if ($type !== '' && $item['document_type'] !== $type) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                return Str::contains(Str::lower($item['title'] . ' ' . $item['file_name'] . ' ' . $item['source']), Str::lower($search));
            })
            ->values();
    }

    private function vendor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->user_type === 'vendor', 403);
        abort_if($user->is_disabled || $user->is_blacklisted, 403);

        return $user;
    }

    private function resolveStoredLocation(string $path): ?array
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');

        foreach (['local', 'public'] as $disk) {
            $candidatePath = $path;

            if ($disk === 'public' && Str::startsWith($candidatePath, 'public/')) {
                $candidatePath = Str::after($candidatePath, 'public/');
            }

            if (Storage::disk($disk)->exists($candidatePath)) {
                return [$disk, $candidatePath];
            }
        }

        if (Str::startsWith($path, 'storage/')) {
            $publicPath = Str::after($path, 'storage/');

            if (Storage::disk('public')->exists($publicPath)) {
                return ['public', $publicPath];
            }
        }

        return null;
    }
}
