@extends('layouts.vendor')

@section('title', 'Purchase Order')

@section('content')
    @php
        use Illuminate\Support\Str;

        $formatMoney = fn ($amount, $currency = 'USD') => trim(($currency ?: 'USD') . ' ' . number_format((float) $amount, 2));
        $lineItems = $sourcePurchaseRequest?->items ?? collect();
        $evidenceByItem = $purchaseOrder->lineItemEvidence->keyBy('purchase_request_item_id');
        $currency = $purchaseOrder->resolved_currency;
        $isCancelled = strtolower((string) $purchaseOrder->status) === 'cancelled';
        $signedPaymentDocuments = $purchaseOrder->disbursements
            ->flatMap(function ($disbursement) {
                return collect($disbursement->signed_documents ?? [])
                    ->filter(fn ($document) => is_array($document) && ! empty($document['path']))
                    ->values()
                    ->map(fn ($document, $documentIndex) => [
                        'disbursement' => $disbursement,
                        'document' => $document,
                        'documentIndex' => $documentIndex,
                    ]);
            })
            ->values();
    @endphp

    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">Purchase Order</div>
            <h3 class="mb-1">{{ $purchaseOrder->po_title ?: 'Purchase Order' }}</h3>
            <p class="text-muted mb-0">{{ $purchaseOrder->reference_no ?? 'N/A' }} | {{ Str::headline($purchaseOrder->status ?? 'draft') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('vendor.purchase-orders.pdf', $purchaseOrder) }}" class="btn btn-vendor-outline">
                <i class="feather-eye me-1"></i> PDF
            </a>
            <a href="{{ route('vendor.purchase-orders.download', $purchaseOrder) }}" class="btn btn-vendor">
                <i class="feather-download me-1"></i> Download
            </a>
            <a href="{{ route('vendor.purchase-orders.index') }}" class="btn btn-vendor-outline">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Review the highlighted upload fields and try again.</strong>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="vendor-eyebrow">Order Value</div>
                    <div class="vendor-metric-value fs-4">{{ $formatMoney($purchaseOrder->amount, $currency) }}</div>
                    <div class="text-muted small mt-1">Issued {{ $purchaseOrder->issued_at?->format('M d, Y') ?? 'N/A' }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="vendor-eyebrow">Delivery</div>
                    <div class="fw-bold">{{ $purchaseOrder->expected_delivery_date?->format('M d, Y') ?? 'Not set' }}</div>
                    <div class="text-muted small mt-1">{{ $purchaseOrder->delivery_location ?: 'Delivery location not set' }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="vendor-eyebrow">Evidence</div>
                    <div class="fw-bold">{{ number_format($purchaseOrder->lineItemEvidence->count()) }} deliverable record(s)</div>
                    <div class="text-muted small mt-1">{{ number_format($lineItems->count()) }} deliverable(s) on this PO</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card vendor-card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="vendor-eyebrow">Buyer Contact</div>
                    <div class="fw-semibold">{{ $purchaseOrder->buyer_contact_name ?: 'ATTP' }}</div>
                    <div class="text-muted small">{{ $purchaseOrder->buyer_contact_email ?: 'N/A' }}</div>
                    <div class="text-muted small">{{ $purchaseOrder->buyer_contact_phone ?: 'N/A' }}</div>
                </div>
                <div class="col-lg-6">
                    <div class="vendor-eyebrow">Terms</div>
                    <div class="text-muted small"><strong class="text-dark">Payment:</strong> {{ $purchaseOrder->payment_terms ?: 'N/A' }}</div>
                    <div class="text-muted small"><strong class="text-dark">Delivery:</strong> {{ $purchaseOrder->delivery_terms ?: 'N/A' }}</div>
                    @if ($purchaseOrder->valid_until)
                        <div class="text-muted small"><strong class="text-dark">Valid Until:</strong> {{ $purchaseOrder->valid_until->format('M d, Y') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card vendor-card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <div class="vendor-eyebrow">Signed Payment Documents</div>
                    <h5 class="mb-0">{{ number_format($signedPaymentDocuments->count()) }} signed {{ $signedPaymentDocuments->count() === 1 ? 'file' : 'files' }}</h5>
                </div>
                <span class="badge-soft">Available after ATTP records payment</span>
            </div>

            @if ($signedPaymentDocuments->isEmpty())
                <div class="vendor-empty py-3">
                    <div class="vendor-empty-icon"><i class="feather-file-text"></i></div>
                    <h5>No signed payment documents yet</h5>
                    <p class="text-muted mb-0">Signed disbursement documents will appear here once ATTP completes the payment record.</p>
                </div>
            @else
                <div class="vstack gap-2">
                    @foreach ($signedPaymentDocuments as $signedPaymentDocument)
                        @php
                            $receipt = $signedPaymentDocument['disbursement'];
                            $document = $signedPaymentDocument['document'];
                            $documentIndex = $signedPaymentDocument['documentIndex'];
                        @endphp
                        <div class="border rounded-3 p-3">
                            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                                <div>
                                    <div class="fw-bold">{{ $document['display_name'] ?? $document['name'] ?? 'Signed document' }}</div>
                                    <div class="text-muted small">
                                        Receipt {{ $receipt->reference_no ?? 'N/A' }}
                                        @if (! empty($document['signed_at']))
                                            | Signed {{ \Illuminate\Support\Carbon::parse($document['signed_at'])->format('M d, Y H:i') }}
                                        @endif
                                    </div>
                                    @if (! empty($document['source_document_name']))
                                        <div class="text-muted small">Source: {{ $document['source_document_name'] }}</div>
                                    @endif
                                    @if (! empty($document['digital_signature_code']))
                                        <div class="text-muted small">Digital Code: <span class="fw-semibold">{{ $document['digital_signature_code'] }}</span></div>
                                    @endif
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('vendor.purchase-orders.disbursements.signed-documents.download', [$purchaseOrder, $receipt, $documentIndex]) }}"
                                        class="btn btn-vendor-outline btn-sm">
                                        <i class="feather-eye me-1"></i> View
                                    </a>
                                    <a href="{{ route('vendor.purchase-orders.disbursements.signed-documents.pdf', [$purchaseOrder, $receipt, $documentIndex]) }}?download=1"
                                        class="btn btn-vendor btn-sm">
                                        <i class="feather-file-text me-1"></i> Download PDF
                                    </a>
                                    <a href="{{ route('vendor.purchase-orders.disbursements.signed-documents.download', [$purchaseOrder, $receipt, $documentIndex]) }}?download=1"
                                        class="btn btn-vendor-outline btn-sm">
                                        <i class="feather-download me-1"></i> Original
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <div class="vendor-eyebrow">Deliverables</div>
                    <h5 class="mb-0">Evidence Documents</h5>
                </div>
                @if ($isCancelled)
                    <span class="badge bg-danger">Uploads disabled</span>
                @else
                    <span class="badge-soft">Upload documents against the matching deliverable</span>
                @endif
            </div>

            @if ($lineItems->isEmpty())
                <div class="vendor-empty">
                    <div class="vendor-empty-icon"><i class="feather-check-square"></i></div>
                    <h5>No deliverables on this purchase order</h5>
                    <p class="text-muted mb-0">ATTP has not attached deliverable line items to this PO yet.</p>
                </div>
            @else
                <div class="vstack gap-3">
                    @foreach ($lineItems as $item)
                        @php
                            $evidence = $evidenceByItem->get($item->id);
                            $documents = collect($evidence?->documents ?? [])->filter(fn ($document) => is_array($document))->values();
                            $deliverableTitle = $item->milestone ?: ($item->deliverable?->title ?? $item->resource?->name ?? 'Deliverable');
                            $lineAmount = $purchaseOrder->lineItemPayableAmount($item);
                            $hasVendorDocuments = $evidence?->hasVendorDocuments() ?? false;
                            $isResubmissionRequested = $evidence?->isOpenForVendorResubmission() ?? false;
                            $isLockedSubmission = $evidence?->isLockedForVendorUpload() ?? false;
                            $isVerified = (bool) ($evidence?->is_met ?? false);
                            $canUploadEvidence = ! $isCancelled && ! $isVerified && (! $hasVendorDocuments || $isResubmissionRequested);
                        @endphp

                        <div class="border rounded-3 p-3 {{ $isLockedSubmission ? 'bg-light' : '' }}">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                <div class="min-w-0">
                                    <div class="fw-bold">{{ $deliverableTitle }}</div>
                                    <div class="text-muted small">
                                        {{ $item->resourceCategory?->name ?? 'Service' }} | {{ $formatMoney($lineAmount, $currency) }}
                                    </div>
                                    @if ($item->milestone_date)
                                        <div class="text-muted small">Due {{ $item->milestone_date->format('M d, Y') }}</div>
                                    @endif
                                </div>
                                <span class="status-pill">
                                    @if ($isVerified)
                                        Verified
                                    @elseif ($isResubmissionRequested)
                                        Resubmission Requested
                                    @elseif ($isLockedSubmission)
                                        Submitted
                                    @elseif ($documents->isNotEmpty())
                                        Evidence Uploaded
                                    @else
                                        Awaiting Evidence
                                    @endif
                                </span>
                            </div>

                            @if ($documents->isNotEmpty())
                                <div class="mb-3">
                                    <div class="small fw-semibold mb-2">Uploaded Evidence</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($documents as $documentIndex => $document)
                                            <a href="{{ route('vendor.purchase-orders.evidence.documents.download', [$purchaseOrder, $evidence, $documentIndex]) }}?download=1"
                                                class="badge bg-light text-dark border text-decoration-none"
                                                title="{{ $document['name'] ?? 'Document' }}">
                                                <i class="feather-paperclip me-1"></i>
                                                {{ $document['display_name'] ?? $document['name'] ?? 'Document' }}
                                                @if (($document['source'] ?? null) === 'vendor')
                                                    <span class="text-muted">Vendor</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($evidence?->notes)
                                <div class="alert alert-light border small mb-3">
                                    {!! nl2br(e($evidence->notes)) !!}
                                </div>
                            @endif

                            @if ($isVerified)
                                <div class="alert alert-success border d-flex align-items-start gap-2 mb-0">
                                    <i class="feather-check-circle mt-1"></i>
                                    <div>
                                        <div class="fw-bold">Internal verification completed</div>
                                        <div class="small">This evidence has been verified by ATTP and is closed for vendor edits.</div>
                                    </div>
                                </div>
                            @elseif ($isResubmissionRequested)
                                <div class="alert alert-warning border d-flex align-items-start gap-2 mb-3">
                                    <i class="feather-rotate-ccw mt-1"></i>
                                    <div>
                                        <div class="fw-bold">Resubmission requested</div>
                                        <div class="small">ATTP has reopened this deliverable for corrected evidence.</div>
                                        @if ($evidence?->vendor_resubmission_note)
                                            <div class="small mt-2"><strong>Admin note:</strong> {{ $evidence->vendor_resubmission_note }}</div>
                                        @endif
                                    </div>
                                </div>
                            @elseif ($isLockedSubmission)
                                <div class="alert alert-info border d-flex align-items-start gap-2 mb-0">
                                    <i class="feather-clock mt-1"></i>
                                    <div>
                                        <div class="fw-bold">Submitted, awaiting internal process verification</div>
                                        <div class="small">This section is locked. It will reopen only if ATTP requests a resubmission.</div>
                                        @if ($evidence?->vendor_submitted_at)
                                            <div class="small mt-1">Submitted on {{ $evidence->vendor_submitted_at->format('M d, Y H:i') }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if ($canUploadEvidence)
                                <form method="POST"
                                    action="{{ route('vendor.purchase-orders.evidence.store', [$purchaseOrder, $item]) }}"
                                    enctype="multipart/form-data"
                                    class="vendor-evidence-form"
                                    data-evidence-uploader>
                                    @csrf

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold">Delivered Date</label>
                                            <input type="date" name="deliverable_date" class="form-control"
                                                value="{{ old('deliverable_date', $evidence?->deliverable_date?->toDateString()) }}">
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label small fw-semibold">Vendor Note</label>
                                            <input type="text" name="notes" class="form-control"
                                                value="{{ old('notes') }}"
                                                placeholder="Optional note for this evidence upload">
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                            <label class="form-label small fw-semibold mb-0">Documents</label>
                                            <button type="button" class="btn btn-vendor-outline btn-sm" data-add-document>
                                                <i class="feather-plus me-1"></i> Add
                                            </button>
                                        </div>
                                        <div class="vstack gap-2" data-document-list>
                                            <div class="row g-2 align-items-center" data-document-row>
                                                <div class="col-md-5">
                                                    <input type="text" name="document_names[]" class="form-control"
                                                        placeholder="Document label">
                                                </div>
                                                <div class="col-md-6">
                                                    <input type="file" name="documents[]" class="form-control"
                                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip" required>
                                                </div>
                                                <div class="col-md-1 text-end">
                                                    <button type="button" class="btn btn-light btn-sm" data-remove-document title="Remove">
                                                        <i class="feather-x"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-muted small mt-2">PDF, Office files, images, or ZIP. Maximum 20MB per file.</div>
                                    </div>

                                    <div class="text-end mt-3">
                                        <button type="submit" class="btn btn-vendor">
                                            <i class="feather-upload-cloud me-1"></i> Upload Evidence
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('click', function (event) {
            const addButton = event.target.closest('[data-add-document]');
            if (addButton) {
                const form = addButton.closest('[data-evidence-uploader]');
                const list = form?.querySelector('[data-document-list]');
                const template = list?.querySelector('[data-document-row]');

                if (!list || !template) {
                    return;
                }

                const row = template.cloneNode(true);
                row.querySelectorAll('input').forEach((input) => {
                    input.value = '';
                });
                list.appendChild(row);
                return;
            }

            const removeButton = event.target.closest('[data-remove-document]');
            if (!removeButton) {
                return;
            }

            const list = removeButton.closest('[data-document-list]');
            const rows = list ? list.querySelectorAll('[data-document-row]') : [];

            if (rows.length <= 1) {
                rows[0]?.querySelectorAll('input').forEach((input) => {
                    input.value = '';
                });
                return;
            }

            removeButton.closest('[data-document-row]')?.remove();
        });
    </script>
@endpush
