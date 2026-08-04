<?php

namespace App\Http\Controllers;

use App\Models\ProcurementAuditLog;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use App\Models\PurchaseRequestItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdministrativeAssistantEvidenceController extends Controller
{
    public function index(Request $request)
    {
        $allRows = $this->evidenceRows();

        $rowYear = fn ($row): int => (int) (
            $row->due_date?->year
            ?: $row->purchase_request->start_year
            ?: $row->purchase_request->created_at?->year
            ?: now()->year
        );
        $rowMonth = fn ($row): int => (int) ($row->due_date?->month ?: 0);

        $stats = [
            'outstanding' => $allRows->where('has_documents', false)->count(),
            'overdue' => $allRows->where('status', 'overdue')->count(),
            'due_this_month' => $allRows
                ->where('has_documents', false)
                ->filter(fn ($row) => $row->due_date?->isSameMonth(now()))
                ->count(),
            'uploaded' => $allRows->where('has_documents', true)->count(),
        ];

        $years = $allRows
            ->groupBy($rowYear)
            ->map(function (Collection $yearRows, int $year) use ($rowMonth) {
                $scheduledMonths = $yearRows
                    ->map($rowMonth)
                    ->filter(fn (int $month) => $month >= 1 && $month <= 12)
                    ->unique();

                return (object) [
                    'year' => $year,
                    'task_count' => $yearRows->count(),
                    'vendor_count' => $yearRows
                        ->pluck('purchase_order.vendor_id')
                        ->filter()
                        ->unique()
                        ->count(),
                    'month_count' => $scheduledMonths->count(),
                    'outstanding_count' => $yearRows->where('has_documents', false)->count(),
                    'uploaded_count' => $yearRows->where('has_documents', true)->count(),
                    'overdue_count' => $yearRows->where('status', 'overdue')->count(),
                    'progress' => $yearRows->isEmpty()
                        ? 0
                        : (int) round(($yearRows->where('has_documents', true)->count() / $yearRows->count()) * 100),
                ];
            })
            ->sortKeysDesc()
            ->values();

        $requestedYear = (int) $request->query('year');
        $selectedYear = $years->contains(fn ($year) => $year->year === $requestedYear)
            ? $requestedYear
            : null;
        $requestedMonth = (int) $request->query('month');
        $selectedMonth = $selectedYear && $requestedMonth >= 1 && $requestedMonth <= 12
            ? $requestedMonth
            : null;

        $selectedYearRows = $selectedYear
            ? $allRows->filter(fn ($row) => $rowYear($row) === $selectedYear)->values()
            : collect();

        $months = $selectedYearRows
            ->groupBy($rowMonth)
            ->filter(fn (Collection $monthRows, int $month) => $month >= 1 && $month <= 12)
            ->map(function (Collection $monthRows, int $month) {
                return (object) [
                    'month' => $month,
                    'name' => Carbon::create(null, $month, 1)->format('F'),
                    'short_name' => Carbon::create(null, $month, 1)->format('M'),
                    'task_count' => $monthRows->count(),
                    'vendor_count' => $monthRows
                        ->pluck('purchase_order.vendor_id')
                        ->filter()
                        ->unique()
                        ->count(),
                    'outstanding_count' => $monthRows->where('has_documents', false)->count(),
                    'uploaded_count' => $monthRows->where('has_documents', true)->count(),
                    'overdue_count' => $monthRows->where('status', 'overdue')->count(),
                    'vendor_names' => $monthRows->pluck('vendor_name')->filter()->unique()->sort()->values(),
                ];
            })
            ->sortKeys()
            ->values();

        $monthRows = $selectedMonth
            ? $selectedYearRows->filter(fn ($row) => $rowMonth($row) === $selectedMonth)->values()
            : collect();

        $vendors = $monthRows
            ->map(fn ($row) => ['id' => $row->purchase_order->vendor_id, 'name' => $row->vendor_name])
            ->filter(fn ($vendor) => filled($vendor['id']))
            ->unique('id')
            ->sortBy('name')
            ->values();

        $filteredRows = $monthRows;
        $status = trim((string) $request->query('status', 'all'));
        if ($status === 'outstanding') {
            $filteredRows = $filteredRows->where('has_documents', false);
        } elseif (in_array($status, ['overdue', 'due_soon', 'upcoming', 'uploaded'], true)) {
            $filteredRows = $filteredRows->where('status', $status);
        } else {
            $status = 'all';
        }

        $vendorId = trim((string) $request->query('vendor'));
        if ($vendorId !== '') {
            $filteredRows = $filteredRows->filter(
                fn ($row) => (string) $row->purchase_order->vendor_id === $vendorId
            );
        }

        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $filteredRows = $filteredRows->filter(function ($row) use ($needle): bool {
                return str_contains(mb_strtolower(implode(' ', [
                    $row->vendor_name,
                    $row->purchase_order->reference_no,
                    $row->purchase_request->reference_no,
                    $row->purchase_request->description,
                    $row->title,
                    $row->invoice?->reference_no,
                ])), $needle);
            });
        }

        $filteredRows = $filteredRows->values();
        $vendorCards = $filteredRows
            ->groupBy(fn ($row) => (string) ($row->purchase_order->vendor_id ?: 'unassigned-'.$row->purchase_order->id))
            ->map(function (Collection $vendorRows) {
                $first = $vendorRows->first();

                return (object) [
                    'vendor' => $first->purchase_order->vendor,
                    'vendor_name' => $first->vendor_name,
                    'rows' => $vendorRows->sortBy(fn ($row) => $row->due_date?->timestamp ?? PHP_INT_MAX)->values(),
                    'task_count' => $vendorRows->count(),
                    'outstanding_count' => $vendorRows->where('has_documents', false)->count(),
                    'uploaded_count' => $vendorRows->where('has_documents', true)->count(),
                    'overdue_count' => $vendorRows->where('status', 'overdue')->count(),
                ];
            })
            ->sortBy('vendor_name')
            ->values();

        return view('administrative-assistant.dashboard', compact(
            'allRows',
            'stats',
            'years',
            'months',
            'selectedYear',
            'selectedMonth',
            'monthRows',
            'filteredRows',
            'vendorCards',
            'vendors',
            'status',
            'vendorId',
            'search'
        ));
    }

    public function show(ProcurementPurchaseOrder $purchaseOrder, PurchaseRequestItem $item)
    {
        [$purchaseRequest, $evidence] = $this->resolveEvidenceContext($purchaseOrder, $item);

        $purchaseOrder->loadMissing('vendor', 'invoice');
        $evidence?->loadMissing('invoice');

        return view('administrative-assistant.evidence', [
            'purchaseOrder' => $purchaseOrder,
            'purchaseRequest' => $purchaseRequest,
            'item' => $item,
            'evidence' => $evidence,
            'invoice' => $evidence?->invoice,
            'suggestedDate' => $evidence?->deliverable_date
                ?: $item->milestone_date
                ?: $purchaseOrder->expected_delivery_date
                ?: $purchaseRequest->delivery_date,
        ]);
    }

    public function store(
        Request $request,
        ProcurementPurchaseOrder $purchaseOrder,
        PurchaseRequestItem $item
    ) {
        [$purchaseRequest, $existingEvidence] = $this->resolveEvidenceContext($purchaseOrder, $item);

        abort_if(
            in_array(strtolower((string) $purchaseOrder->status), ['cancelled', 'canceled', 'void'], true),
            403,
            'This purchase order is no longer open for uploads.'
        );

        $data = $request->validate([
            'deliverable_date' => ['required', 'date'],
            'invoice_reference' => ['nullable', 'string', 'max:100'],
            'invoice_amount' => ['nullable', 'numeric', 'min:0.01'],
            'return_year' => ['nullable', 'integer', 'min:2000', 'max:2200'],
            'return_month' => ['nullable', 'integer', 'between:1,12'],
            'invoice_document' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png',
                'max:20480',
            ],
            'supporting_documents' => ['nullable', 'array', 'max:20'],
            'supporting_documents.*' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,zip',
                'max:20480',
            ],
            'notes' => ['nullable', 'string', 'max:3000'],
        ], [
            'deliverable_date.required' => 'Please confirm the deliverable date.',
            'invoice_document.mimes' => 'The invoice must be a PDF, Office file, or image.',
            'invoice_document.max' => 'The invoice must not be larger than 20MB.',
            'supporting_documents.*.mimes' => 'Supporting documents must be PDFs, Office files, images, text files, or ZIP files.',
            'supporting_documents.*.max' => 'Each supporting document must not be larger than 20MB.',
        ]);

        $invoiceFile = $request->file('invoice_document');
        $supportingFiles = collect($request->file('supporting_documents', []))
            ->filter(fn ($file) => $file?->isValid())
            ->values();

        if (! $invoiceFile && $supportingFiles->isEmpty()) {
            throw ValidationException::withMessages([
                'documents' => 'Choose an invoice or at least one supporting document to upload.',
            ]);
        }

        $deliverableDate = Carbon::parse($data['deliverable_date'])->startOfDay();
        $storedDocuments = [];
        $storedPaths = [];
        $directory = "procurement_purchase_orders/{$purchaseOrder->id}/line-item-evidence/{$item->id}";

        try {
            if ($invoiceFile?->isValid()) {
                $storedDocuments[] = $this->storeDocument(
                    $invoiceFile,
                    $directory,
                    'invoice',
                    $deliverableDate
                );
                $storedPaths[] = $storedDocuments[array_key_last($storedDocuments)]['path'];
            }

            foreach ($supportingFiles as $file) {
                $storedDocuments[] = $this->storeDocument(
                    $file,
                    $directory,
                    'supporting',
                    $deliverableDate
                );
                $storedPaths[] = $storedDocuments[array_key_last($storedDocuments)]['path'];
            }

            $evidence = DB::transaction(function () use (
                $data,
                $deliverableDate,
                $existingEvidence,
                $invoiceFile,
                $item,
                $purchaseOrder,
                $purchaseRequest,
                $storedDocuments
            ) {
                $evidence = $existingEvidence ?: new ProcurementPurchaseOrderItemEvidence([
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_request_item_id' => $item->id,
                    'created_by' => auth()->id(),
                ]);

                $invoice = $evidence->invoice;
                if ($invoiceFile) {
                    $invoice = $this->resolveOrCreateInvoice(
                        $evidence,
                        $purchaseOrder,
                        $purchaseRequest,
                        $item,
                        $deliverableDate,
                        $data
                    );
                }

                $documents = collect($evidence->documents ?? [])
                    ->filter(fn ($document) => is_array($document))
                    ->values();

                $newDocuments = collect($storedDocuments)->map(function (array $document) use ($invoice): array {
                    if ($document['document_type'] === 'invoice' && $invoice) {
                        $document['invoice_id'] = $invoice->id;
                        $document['invoice_reference'] = $invoice->reference_no;
                    }

                    return $document;
                });

                $notes = trim((string) ($data['notes'] ?? ''));
                if ($notes !== '') {
                    $entry = sprintf(
                        '[%s by %s] %s',
                        now()->format('Y-m-d H:i'),
                        auth()->user()?->name ?: 'Administrative Assistant',
                        $notes
                    );
                    $notes = trim(implode("\n\n", array_filter([$evidence->notes, $entry])));
                } else {
                    $notes = $evidence->notes;
                }

                $evidence->fill([
                    'deliverable_id' => $item->deliverable_id,
                    'invoice_id' => $invoice?->id ?: $evidence->invoice_id,
                    'is_met' => (bool) ($evidence->is_met ?? false),
                    'deliverable_date' => $deliverableDate->toDateString(),
                    'notes' => $notes,
                    'documents' => $documents->concat($newDocuments)->values()->all(),
                    'vendor_submission_status' => ProcurementPurchaseOrderItemEvidence::VENDOR_STATUS_SUBMITTED,
                    'vendor_submitted_at' => now(),
                    'vendor_resubmission_requested_at' => null,
                    'vendor_resubmission_requested_by' => null,
                    'vendor_resubmission_note' => null,
                    'created_by' => $evidence->created_by ?: auth()->id(),
                ]);
                $evidence->save();

                ProcurementAuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'Administrative Assistant uploaded deliverable evidence',
                    'procurement_id' => $purchaseOrder->procurement_id,
                    'metadata' => [
                        'purchase_order_id' => $purchaseOrder->id,
                        'purchase_request_id' => $purchaseRequest->id,
                        'purchase_request_item_id' => $item->id,
                        'evidence_id' => $evidence->id,
                        'invoice_id' => $invoice?->id,
                        'documents_added' => count($storedDocuments),
                        'deliverable_date' => $deliverableDate->toDateString(),
                    ],
                    'created_at' => now(),
                ]);

                return $evidence;
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $message = $invoiceFile
            ? 'Invoice and evidence documents uploaded. The vendor account and invoice register have been updated automatically.'
            : 'Evidence documents uploaded and added to the vendor account.';

        return redirect()
            ->route('administrative-assistant.evidence.show', array_filter([
                $purchaseOrder,
                $item,
                'year' => $data['return_year'] ?? null,
                'month' => $data['return_month'] ?? null,
            ]))
            ->with('success', $message);
    }

    public function download(
        Request $request,
        ProcurementPurchaseOrder $purchaseOrder,
        PurchaseRequestItem $item,
        ProcurementPurchaseOrderItemEvidence $evidence,
        int $document
    ) {
        $this->resolveEvidenceContext($purchaseOrder, $item);

        abort_unless(
            (string) $evidence->purchase_order_id === (string) $purchaseOrder->id
                && (string) $evidence->purchase_request_item_id === (string) $item->id,
            404
        );

        $file = ($evidence->documents ?? [])[$document] ?? null;
        abort_unless(is_array($file) && filled($file['path'] ?? null), 404, 'Document not found.');

        $disk = Storage::disk('local');
        abort_unless($disk->exists($file['path']), 404, 'Document file is missing.');

        $name = ($file['display_name'] ?? null) ?: ($file['name'] ?? basename($file['path']));
        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $request->boolean('download')
            ? $disk->download($file['path'], $name, $headers)
            : $disk->response($file['path'], $name, $headers);
    }

    private function evidenceRows(): Collection
    {
        $purchaseOrders = ProcurementPurchaseOrder::query()
            ->with([
                'vendor',
                'invoice',
                'lineItemEvidence.invoice',
                'purchaseRequest.items.resourceCategory',
                'purchaseRequest.items.resource',
                'purchaseRequest.items.deliverable',
                'budgetCommitment.purchaseRequest.items.resourceCategory',
                'budgetCommitment.purchaseRequest.items.resource',
                'budgetCommitment.purchaseRequest.items.deliverable',
            ])
            ->whereNotNull('vendor_id')
            ->whereNotIn('status', ['cancelled', 'canceled', 'void'])
            ->where(function ($query): void {
                $query->whereNotNull('purchase_request_id')
                    ->orWhereHas('budgetCommitment', fn ($commitment) => $commitment->whereNotNull('purchase_request_id'));
            })
            ->orderByDesc('issued_at')
            ->get();

        $rows = $purchaseOrders
            ->flatMap(function (ProcurementPurchaseOrder $purchaseOrder): Collection {
                $purchaseRequest = $purchaseOrder->sourcePurchaseRequest();
                if (! $purchaseRequest) {
                    return collect();
                }

                $evidenceByItem = $purchaseOrder->lineItemEvidence
                    ->keyBy(fn ($evidence) => (string) $evidence->purchase_request_item_id);

                return $purchaseRequest->items->map(function ($item) use (
                    $evidenceByItem,
                    $purchaseOrder,
                    $purchaseRequest
                ) {
                    $evidence = $evidenceByItem->get((string) $item->id);
                    $documents = collect($evidence?->documents ?? [])->filter(fn ($document) => is_array($document));
                    $dueDate = $evidence?->deliverable_date
                        ?: $item->milestone_date
                        ?: $purchaseOrder->expected_delivery_date
                        ?: $purchaseRequest->delivery_date;
                    $dueDate = $dueDate ? Carbon::parse($dueDate)->startOfDay() : null;
                    $hasDocuments = $documents->isNotEmpty();

                    $status = 'upcoming';
                    if ($hasDocuments) {
                        $status = 'uploaded';
                    } elseif ($dueDate?->lt(today())) {
                        $status = 'overdue';
                    } elseif ($dueDate?->lte(today()->addDays(30))) {
                        $status = 'due_soon';
                    }

                    return (object) [
                        'purchase_order' => $purchaseOrder,
                        'purchase_request' => $purchaseRequest,
                        'item' => $item,
                        'evidence' => $evidence,
                        'invoice' => $evidence?->invoice,
                        'title' => $item->milestone
                            ?: $item->deliverable?->title
                            ?: $item->resource?->name
                            ?: $item->resourceCategory?->name
                            ?: 'Deliverable evidence',
                        'vendor_name' => $purchaseOrder->vendor?->name ?: 'Vendor not assigned',
                        'due_date' => $dueDate,
                        'document_count' => $documents->count(),
                        'has_documents' => $hasDocuments,
                        'status' => $status,
                    ];
                });
            });

        // A purchase request can have more than one purchase order (for example,
        // one per budget year). Keep one clear task per monthly line item while
        // preferring the PO that already owns uploaded evidence.
        return $rows
            ->groupBy(fn ($row) => (string) $row->item->id)
            ->map(function (Collection $candidates) {
                return $candidates
                    ->sort(function ($left, $right): int {
                        if ($left->has_documents !== $right->has_documents) {
                            return $left->has_documents ? -1 : 1;
                        }

                        return ($right->purchase_order->created_at?->timestamp ?? 0)
                            <=> ($left->purchase_order->created_at?->timestamp ?? 0);
                    })
                    ->first();
            })
            ->sortBy(function ($row): string {
                $rank = match ($row->status) {
                    'overdue' => 0,
                    'due_soon' => 1,
                    'upcoming' => 2,
                    default => 3,
                };

                return sprintf('%d-%s', $rank, $row->due_date?->format('Ymd') ?? '99999999');
            })
            ->values();
    }

    private function resolveEvidenceContext(
        ProcurementPurchaseOrder $purchaseOrder,
        PurchaseRequestItem $item
    ): array {
        $purchaseOrder->loadMissing([
            'purchaseRequest.items',
            'budgetCommitment.purchaseRequest.items',
            'lineItemEvidence.invoice',
        ]);

        $purchaseRequest = $purchaseOrder->sourcePurchaseRequest();
        abort_unless($purchaseRequest, 404, 'The purchase request linked to this order was not found.');
        abort_unless(
            (string) $item->purchase_request_id === (string) $purchaseRequest->id,
            404,
            'This deliverable does not belong to the selected purchase order.'
        );

        $item->loadMissing('resourceCategory', 'resource', 'deliverable');
        $evidence = $purchaseOrder->lineItemEvidence
            ->first(fn ($record) => (string) $record->purchase_request_item_id === (string) $item->id);

        return [$purchaseRequest, $evidence];
    }

    private function storeDocument($file, string $directory, string $type, Carbon $date): array
    {
        $path = $file->store($directory, 'local');

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'display_name' => null,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'document_type' => $type,
            'deliverable_date' => $date->toDateString(),
            'source' => 'administrative_assistant',
            'source_label' => 'Administrative Assistant',
            'counts_as_vendor_submission' => true,
            'uploaded_by' => auth()->id(),
            'uploaded_by_name' => auth()->user()?->name,
            'uploaded_at' => now()->toIso8601String(),
        ];
    }

    private function resolveOrCreateInvoice(
        ProcurementPurchaseOrderItemEvidence $evidence,
        ProcurementPurchaseOrder $purchaseOrder,
        $purchaseRequest,
        PurchaseRequestItem $item,
        Carbon $deliverableDate,
        array $data
    ): ProcurementInvoice {
        $reference = trim((string) ($data['invoice_reference'] ?? ''));
        $invoice = $evidence->invoice;

        if (! $invoice && $reference !== '') {
            $invoice = ProcurementInvoice::query()
                ->where('reference_no', $reference)
                ->where('vendor_id', $purchaseOrder->vendor_id)
                ->first();

            $referenceBelongsElsewhere = ProcurementInvoice::query()
                ->where('reference_no', $reference)
                ->when($invoice, fn ($query) => $query->whereKeyNot($invoice->id))
                ->exists();

            if ($referenceBelongsElsewhere) {
                throw ValidationException::withMessages([
                    'invoice_reference' => 'That invoice number is already used for another vendor. Check the number and try again.',
                ]);
            }
        }

        $amount = round((float) ($data['invoice_amount'] ?? $item->amount ?? 0), 2);
        if ($amount <= 0) {
            $amount = round((float) ($purchaseOrder->amount ?? $purchaseRequest->total_amount ?? 0), 2);
        }

        if (! $invoice) {
            $invoice = ProcurementInvoice::create([
                'procurement_id' => $purchaseOrder->procurement_id,
                'vendor_id' => $purchaseOrder->vendor_id,
                'sub_activity_id' => $purchaseOrder->sub_activity_id ?: $purchaseRequest->allocation_id,
                'governance_node_id' => $purchaseOrder->governance_node_id ?: $purchaseRequest->governance_node_id,
                'invoice_month' => $deliverableDate->copy()->startOfMonth()->toDateString(),
                'reference_no' => $reference !== '' ? $reference : ProcurementInvoice::generateReference(),
                'amount' => $amount,
                'currency' => $purchaseOrder->resolved_currency,
                'status' => 'submitted',
                'created_by' => auth()->id(),
                'notes' => 'Created automatically from evidence for '
                    . ($purchaseRequest->reference_no ?: 'purchase request')
                    . ' / '
                    . ($purchaseOrder->reference_no ?: 'purchase order')
                    . '.',
            ]);
        } elseif ($invoice->status === 'submitted') {
            $invoice->update([
                'invoice_month' => $deliverableDate->copy()->startOfMonth()->toDateString(),
                'amount' => $amount,
                'reference_no' => $reference !== '' ? $reference : $invoice->reference_no,
            ]);
        }

        if ($item->deliverable_id) {
            $invoice->deliverables()->syncWithoutDetaching([$item->deliverable_id]);
        }

        return $invoice;
    }
}
