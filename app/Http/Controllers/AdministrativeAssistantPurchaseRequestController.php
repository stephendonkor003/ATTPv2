<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequestIntake;
use App\Models\PurchaseRequestIntakeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdministrativeAssistantPurchaseRequestController extends Controller
{
    private const MAX_DOCUMENTS = 10;

    private const MAX_DOCUMENT_SIZE_KB = 20480;

    private const MAX_COMBINED_DOCUMENT_SIZE_BYTES = 60 * 1024 * 1024;

    public function create(Request $request)
    {
        $recentSubmissions = PurchaseRequestIntake::query()
            ->where('created_by', $request->user()->id)
            ->withCount(['items', 'documents'])
            ->latest()
            ->limit(10)
            ->get();

        return view('administrative-assistant.purchase-requests.create', compact('recentSubmissions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'priority' => ['required', Rule::in(PurchaseRequestIntake::PRIORITIES)],
            'estimated_amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'items' => ['required', 'array', 'min:1', 'max:25'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
            'documents' => ['nullable', 'array', 'max:'.self::MAX_DOCUMENTS],
            'documents.*' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,zip',
                'max:'.self::MAX_DOCUMENT_SIZE_KB,
            ],
        ], [
            'description.required' => 'Please explain what is needed and why.',
            'items.required' => 'Add at least one requested item.',
            'items.min' => 'Add at least one requested item.',
            'items.*.name.required' => 'Each requested item needs a name.',
            'documents.max' => 'Choose no more than 10 supporting documents.',
            'documents.*.mimes' => 'Supporting documents must be PDFs, Office files, images, text files, CSV files, or ZIP files.',
            'documents.*.max' => 'Each supporting document must not be larger than 20MB.',
        ]);

        $documents = collect($request->file('documents', []))
            ->filter(fn ($file): bool => $file instanceof UploadedFile && $file->isValid())
            ->values();

        if ($documents->count() > self::MAX_DOCUMENTS) {
            throw ValidationException::withMessages([
                'documents' => 'Choose no more than 10 supporting documents.',
            ]);
        }

        if ($documents->sum(fn (UploadedFile $file): int => (int) $file->getSize()) > self::MAX_COMBINED_DOCUMENT_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'documents' => 'The combined size of all supporting documents must not be larger than 60MB.',
            ]);
        }

        $user = $request->user();
        $storedPaths = [];

        try {
            $intake = DB::transaction(function () use ($data, $documents, $user, &$storedPaths): PurchaseRequestIntake {
                $intake = PurchaseRequestIntake::create([
                    'reference_no' => PurchaseRequestIntake::generateReference(),
                    'created_by' => $user->id,
                    'governance_node_id' => $user->governance_node_id ?: null,
                    'title' => trim($data['title']),
                    'description' => trim($data['description']),
                    'needed_by' => $data['needed_by'] ?? null,
                    'priority' => $data['priority'],
                    'estimated_amount' => $data['estimated_amount'] ?? null,
                    'currency' => Str::upper($data['currency']),
                    'status' => PurchaseRequestIntake::STATUS_SUBMITTED,
                ]);

                $intake->items()->createMany(
                    collect($data['items'])
                        ->map(fn (array $item): array => [
                            'name' => trim($item['name']),
                            'notes' => filled($item['notes'] ?? null) ? trim($item['notes']) : null,
                            'quantity' => $item['quantity'],
                        ])
                        ->all()
                );

                foreach ($documents as $document) {
                    $metadata = $this->storeDocument($document, $intake, $user->id);
                    $storedPaths[] = $metadata['file_path'];
                    $intake->documents()->create($metadata);
                }

                return $intake;
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }

        return redirect()
            ->route('administrative-assistant.purchase-requests.show', $intake)
            ->with('success', "Purchase request {$intake->reference_no} was submitted to the back office.");
    }

    public function show(Request $request, PurchaseRequestIntake $intake)
    {
        $this->assertOwnedByCurrentUser($request, $intake);

        $intake->load([
            'items' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'documents' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'governanceNode',
            'convertedPurchaseRequest',
        ]);

        return view('administrative-assistant.purchase-requests.show', compact('intake'));
    }

    public function download(
        Request $request,
        PurchaseRequestIntake $intake,
        PurchaseRequestIntakeDocument $document
    ) {
        $this->assertOwnedByCurrentUser($request, $intake);
        abort_unless((string) $document->intake_id === (string) $intake->id, 404);

        $expectedDirectory = "administrative-assistant/purchase-request-intakes/{$intake->id}/";
        abort_unless(
            Str::startsWith(str_replace('\\', '/', $document->file_path), $expectedDirectory),
            404
        );

        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404, 'Document file is missing.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];
        $name = $this->safeDownloadName($document->file_name);

        return $request->boolean('download')
            ? $disk->download($document->file_path, $name, $headers)
            : $disk->response($document->file_path, $name, $headers);
    }

    private function assertOwnedByCurrentUser(Request $request, PurchaseRequestIntake $intake): void
    {
        abort_unless(
            (string) $intake->created_by === (string) $request->user()->id,
            404
        );
    }

    private function storeDocument(
        UploadedFile $document,
        PurchaseRequestIntake $intake,
        string $userId
    ): array {
        $extension = Str::lower((string) $document->getClientOriginalExtension());
        $filename = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
        $directory = "administrative-assistant/purchase-request-intakes/{$intake->id}";
        $path = $document->storeAs($directory, $filename, 'local');

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'documents' => 'One or more supporting documents could not be stored. Please try again.',
            ]);
        }

        return [
            'uploaded_by' => $userId,
            'file_path' => $path,
            'file_name' => $this->safeDownloadName($document->getClientOriginalName()),
            'mime_type' => (string) ($document->getMimeType() ?: 'application/octet-stream'),
            'file_size_bytes' => (int) $document->getSize(),
        ];
    }

    private function safeDownloadName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));

        return Str::limit($name !== '' ? $name : 'document', 180, '');
    }
}
