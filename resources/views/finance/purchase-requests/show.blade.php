@extends('layouts.app')

@push('styles')
    <style>
        .pr-show-page {
            padding-bottom: 2rem;
        }

        .pr-page-header {
            background: linear-gradient(135deg, #123047 0%, #0f766e 100%);
            border-radius: 12px;
            box-shadow: 0 16px 36px rgba(15, 23, 42, .14);
            color: #fff;
            padding: 20px;
        }

        .pr-page-header .text-muted {
            color: rgba(255, 255, 255, .74) !important;
        }

        .pr-page-header .btn-outline-secondary,
        .pr-page-header .btn-outline-warning,
        .pr-page-header .btn-outline-danger,
        .pr-page-header .btn-outline-primary {
            background: rgba(255, 255, 255, .12);
            border-color: rgba(255, 255, 255, .38);
            color: #fff;
        }

        .pr-page-header .btn-outline-secondary:hover,
        .pr-page-header .btn-outline-warning:hover,
        .pr-page-header .btn-outline-danger:hover,
        .pr-page-header .btn-outline-primary:hover {
            background: rgba(255, 255, 255, .22);
            border-color: rgba(255, 255, 255, .62);
            color: #fff;
        }

        .pr-page-header .btn-primary {
            background: #fff;
            border-color: #fff;
            color: #0f766e;
            font-weight: 700;
        }

        .pr-reference-pill {
            align-items: center;
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 999px;
            display: inline-flex;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: 0;
            margin-bottom: 8px;
            padding: 4px 10px;
            text-transform: uppercase;
        }

        .pr-header-meta {
            color: rgba(255, 255, 255, .76);
            font-size: .88rem;
        }

        .pr-card-hover {
            border: 1px solid #e4ebf5;
            transition: box-shadow .18s ease, transform .18s ease, border-color .18s ease;
        }

        .pr-card-hover:hover {
            border-color: #c8d7e8;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .11) !important;
            transform: translateY(-1px);
        }

        .pr-overview-card .card-body,
        .pr-line-items-card .card-body,
        .pr-side-card .card-body {
            padding: 20px;
        }

        .pr-kpi-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .pr-kpi-item {
            background: #f8fafc;
            border: 1px solid #e3eaf4;
            border-radius: 10px;
            min-height: 92px;
            padding: 12px;
        }

        .pr-kpi-label {
            color: #64748b;
            display: block;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: 0;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .pr-kpi-value {
            color: #0f172a;
            display: block;
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .pr-kpi-note {
            color: #64748b;
            display: block;
            font-size: .78rem;
            margin-top: 4px;
        }

        .pr-summary-table {
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .pr-summary-table th,
        .pr-summary-table td {
            background: #fff;
            border: 1px solid #e8eef6;
            padding: 10px 12px;
            vertical-align: middle;
        }

        .pr-summary-table th {
            border-right: 0;
            border-radius: 10px 0 0 10px;
            color: #64748b;
            font-size: .78rem;
            text-transform: uppercase;
            width: 190px;
        }

        .pr-summary-table td {
            border-left: 0;
            border-radius: 0 10px 10px 0;
            color: #1f2937;
        }

        .pr-side-stack {
            display: grid;
            gap: 18px;
        }

        .pr-side-stack .card {
            margin-top: 0 !important;
        }

        .pr-side-card .table {
            margin-bottom: 0;
        }

        .pr-section-kicker {
            color: #64748b;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .pr-line-evidence-status {
            min-width: 104px;
            border-radius: 999px;
        }

        .pr-line-items-card .table {
            min-width: 900px;
        }

        .pr-line-items-card th {
            color: #475569;
            font-size: .74rem;
            letter-spacing: 0;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .pr-line-items-card td {
            vertical-align: middle;
        }

        .pr-line-items-card .deliverable-cell {
            max-width: 340px;
            white-space: normal;
        }

        .pr-line-evidence-modal.show {
            background: rgba(16, 24, 40, .42);
            display: block;
        }

        .pr-line-evidence-modal .modal-dialog {
            max-width: min(860px, calc(100vw - 28px));
        }

        .pr-line-evidence-modal .modal-content {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
            overflow: hidden;
        }

        .pr-line-evidence-summary {
            background: #f8fafc;
            border: 1px solid #e3eaf4;
            border-left: 3px solid #0f766e;
            border-radius: 10px;
            padding: 12px 14px;
        }

        .pr-evidence-document-row {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) minmax(220px, 1.25fr) auto;
            gap: 8px;
            align-items: end;
            background: #fff;
            border: 1px solid #e3eaf4;
            border-radius: 10px;
            padding: 10px;
        }

        .pr-evidence-document-row + .pr-evidence-document-row {
            margin-top: 8px;
        }

        .pr-attachment-upload-row {
            border: 1px solid #e3eaf4;
            border-radius: 10px;
            background: #f8fafc;
            padding: 10px;
        }

        .pr-attachment-upload-row + .pr-attachment-upload-row {
            margin-top: 8px;
        }

        .pr-evidence-document-label {
            color: #667085;
            display: block;
            font-size: .72rem;
            font-weight: 700;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        body.pr-line-evidence-modal-open {
            overflow: hidden;
        }

        @media (max-width: 575.98px) {
            .pr-evidence-document-row {
                grid-template-columns: 1fr;
            }

            .pr-page-header {
                border-radius: 10px;
                padding: 16px;
            }
        }

        @media (max-width: 1199.98px) {
            .pr-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .pr-kpi-grid {
                grid-template-columns: 1fr;
            }

            .pr-summary-table th,
            .pr-summary-table td {
                display: block;
                width: 100%;
            }

            .pr-summary-table th {
                border-radius: 10px 10px 0 0;
                border-right: 1px solid #e8eef6;
                padding-bottom: 4px;
            }

            .pr-summary-table td {
                border-left: 1px solid #e8eef6;
                border-radius: 0 0 10px 10px;
                border-top: 0;
                padding-top: 4px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $statusClasses = [
            'approved' => 'bg-success',
            'submitted' => 'bg-warning text-dark',
            'draft' => 'bg-secondary',
            'rejected' => 'bg-danger',
            'cancelled' => 'bg-danger',
        ];
        $decidableStatuses = ['draft', 'submitted'];
        $purchaseRequestStatus = $purchaseRequest->status ?? 'draft';
        $canDecidePurchaseRequest = $canApprovePurchaseRequests && in_array($purchaseRequestStatus, $decidableStatuses, true);
        $commitmentStatuses = $purchaseRequest->commitments->pluck('status');
        $canEditLockedPurchaseRequest = auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin();
        $canEditThisPurchaseRequest = $canEditPurchaseRequests
            && (
                $canEditLockedPurchaseRequest
                || (
                    $purchaseRequestStatus === 'draft'
                    && $commitmentStatuses->isNotEmpty()
                    && $commitmentStatuses->every(fn ($status) => $status === 'draft')
                )
            );
        $canDeleteThisPurchaseRequest = $canDeletePurchaseRequests
            && $purchaseRequestStatus !== 'approved'
            && ! $commitmentStatuses->contains('approved');
        $lineItemEvidencePayload = $lineItemEvidenceByItem
            ->mapWithKeys(fn ($evidence) => [
                (string) $evidence->purchase_request_item_id => [
                    'is_met' => (bool) $evidence->is_met,
                    'deliverable_date' => $evidence->deliverable_date?->format('Y-m-d'),
                    'notes' => $evidence->notes,
                    'documents' => collect($evidence->documents ?? [])
                        ->map(fn ($document, $index) => [
                            'index' => $index,
                            'name' => $document['name'] ?? 'Document',
                            'display_name' => $document['display_name'] ?? null,
                            'url' => route('procurement.purchase-orders.line-item-evidence.document', [$evidencePurchaseOrder, $evidence, $index]) . '?download=1',
                        ])
                        ->values()
                        ->all(),
                ],
            ])
            ->all();
        $lineItemModalPayload = $purchaseRequest->items
            ->mapWithKeys(fn ($item) => [
                (string) $item->id => [
                    'title' => $item->resource?->name ?? $item->resourceCategory?->name ?? 'Line item',
                    'category' => $item->resourceCategory?->name ?? 'N/A',
                    'deliverable' => $item->milestone ?: ($item->deliverable?->title ?? 'No deliverable linked'),
                    'amount' => number_format((float) $item->amount, 2),
                    'currency' => $purchaseRequest->resolved_currency,
                ],
            ])
            ->all();
        $formatBytes = function ($bytes) {
            $bytes = (float) $bytes;
            if ($bytes <= 0) {
                return '0 B';
            }

            $units = ['B', 'KB', 'MB', 'GB'];
            $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

            return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1) . ' ' . $units[$power];
        };
        $purchaseRequestCurrency = $purchaseRequest->resolved_currency;
        $programName = $purchaseRequest->programFunding?->program?->name ?? $purchaseRequest->programFunding?->program_name ?? 'N/A';
        $governanceNodeName = $purchaseRequest->governanceNode?->name ?? 'N/A';
        $subActivityName = $purchaseRequest->subActivity?->name ?? 'N/A';
        $lineItemsCount = $purchaseRequest->items->count();
        $confirmedLineItemsCount = $lineItemEvidenceByItem->filter(fn ($evidence) => (bool) $evidence->is_met)->count();
        $pendingLineItemsCount = max($lineItemsCount - $confirmedLineItemsCount, 0);
        $attachmentCount = $purchaseRequest->attachments->count();
    @endphp

    <div class="nxl-container pr-show-page">

        <div class="page-header pr-page-header d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div class="min-w-0">
                <span class="pr-reference-pill">Purchase Request</span>
                <h4 class="fw-bold mb-1">{{ $purchaseRequest->reference_no }}</h4>
                <div class="pr-header-meta d-flex flex-wrap align-items-center gap-2">
                    <span>{{ $programName }}</span>
                    <span class="text-muted">Generated from a budget commitment</span>
                    <span class="badge {{ $statusClasses[$purchaseRequestStatus] ?? 'bg-secondary' }}">
                        {{ ucfirst(str_replace('_', ' ', $purchaseRequestStatus)) }}
                    </span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('finance.purchase-requests.index') }}" class="btn btn-outline-secondary">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
                @if ($canEditThisPurchaseRequest)
                    <a href="{{ route('finance.purchase-requests.edit', $purchaseRequest) }}" class="btn btn-outline-warning">
                        <i class="feather-edit-2 me-1"></i> Edit
                    </a>
                @endif
                @if ($canDeletePurchaseRequests)
                    <button type="button"
                        class="btn btn-outline-danger js-delete-pr"
                        data-info-url="{{ route('finance.purchase-requests.destroy-info', $purchaseRequest) }}"
                        data-delete-url="{{ route('finance.purchase-requests.destroy', $purchaseRequest) }}"
                        data-force-delete-url="{{ route('finance.purchase-requests.force-destroy', $purchaseRequest) }}">
                        <i class="feather-trash-2 me-1"></i> Delete
                    </button>
                @endif
                <a href="{{ route('finance.purchase-requests.pdf', $purchaseRequest) }}" class="btn btn-outline-primary" target="_blank">
                    <i class="feather-file-text me-1"></i> View PDF
                </a>
                <a href="{{ route('finance.purchase-requests.download', $purchaseRequest) }}" class="btn btn-primary">
                    <i class="feather-download me-1"></i> Download PDF
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="row g-4 mt-1 align-items-start">
            <div class="col-xl-8 order-1">
                <div class="card shadow-sm pr-overview-card pr-card-hover">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-2 mb-3">
                            <div>
                                <div class="pr-section-kicker mb-1">Overview</div>
                                <h5 class="fw-bold mb-1">Purchase Request Details</h5>
                                <div class="text-muted small">Budget commitment, delivery timing, and approval status in one place.</div>
                            </div>
                            <span class="badge {{ $statusClasses[$purchaseRequestStatus] ?? 'bg-secondary' }} align-self-start">
                                {{ ucfirst(str_replace('_', ' ', $purchaseRequestStatus)) }}
                            </span>
                        </div>

                        <div class="pr-kpi-grid mb-3">
                            <div class="pr-kpi-item">
                                <span class="pr-kpi-label">Total Amount</span>
                                <span class="pr-kpi-value">
                                    {{ $purchaseRequestCurrency }} {{ number_format((float) $purchaseRequest->total_amount, 2) }}
                                </span>
                                <span class="pr-kpi-note">Approved request value</span>
                            </div>
                            <div class="pr-kpi-item">
                                <span class="pr-kpi-label">Line Items</span>
                                <span class="pr-kpi-value">{{ $lineItemsCount }}</span>
                                <span class="pr-kpi-note">{{ $confirmedLineItemsCount }} confirmed, {{ $pendingLineItemsCount }} pending</span>
                            </div>
                            <div class="pr-kpi-item">
                                <span class="pr-kpi-label">Delivery Date</span>
                                <span class="pr-kpi-value">{{ $purchaseRequest->delivery_date?->format('M j, Y') ?? 'N/A' }}</span>
                                <span class="pr-kpi-note">Target completion</span>
                            </div>
                            <div class="pr-kpi-item">
                                <span class="pr-kpi-label">Attachments</span>
                                <span class="pr-kpi-value">{{ $attachmentCount }}</span>
                                <span class="pr-kpi-note">Supporting documents</span>
                            </div>
                        </div>

                        <table class="table table-sm mb-0 pr-summary-table">
                            <tr>
                                <th style="width: 200px;">Program</th>
                                <td>{{ $purchaseRequest->programFunding?->program?->name ?? $purchaseRequest->programFunding?->program_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Governance Node</th>
                                <td>{{ $purchaseRequest->governanceNode?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Sub-Activity</th>
                                <td>{{ $purchaseRequest->subActivity?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Start Year</th>
                                <td>{{ $purchaseRequest->start_year }}</td>
                            </tr>
                            <tr>
                                <th>Commitment Date</th>
                                <td>{{ $purchaseRequest->commitment_date?->format('F j, Y') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Delivery Date</th>
                                <td>{{ $purchaseRequest->delivery_date?->format('F j, Y') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Total Amount</th>
                                <td class="fw-bold">
                                    {{ $purchaseRequestCurrency }}
                                    {{ number_format((float) $purchaseRequest->total_amount, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="badge {{ $statusClasses[$purchaseRequestStatus] ?? 'bg-secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $purchaseRequestStatus)) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Created</th>
                                <td>{{ $purchaseRequest->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Created By</th>
                                <td>{{ $purchaseRequest->creator?->name ?? '—' }}</td>
                            </tr>
                        </table>

                        @if (!empty($purchaseRequest->description))
                            <div class="mt-3">
                                <div class="fw-semibold mb-1">Description</div>
                                <div class="text-muted">{{ $purchaseRequest->description }}</div>
                            </div>
                        @endif

                        @if ($purchaseRequestStatus === 'rejected' && !empty($purchaseRequest->rejection_reason))
                            <div class="alert alert-danger mt-3 mb-0">
                                <div class="fw-semibold mb-1">Rejection reason</div>
                                <div>{{ $purchaseRequest->rejection_reason }}</div>
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            <div class="col-12 order-3">
                <div class="card shadow-sm pr-line-items-card pr-card-hover">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                            <div>
                                <h6 class="fw-bold mb-1">Line Items from Purchase Request</h6>
                                @if ($evidencePurchaseOrder)
                                    <div class="small text-muted">
                                        Evidence is linked to purchase order {{ $evidencePurchaseOrder->reference_no ?? 'N/A' }}.
                                    </div>
                                @else
                                    <div class="small text-muted">
                                        Create a purchase order for this request before adding deliverable evidence.
                                    </div>
                                @endif
                            </div>
                            <span class="badge bg-light text-dark border align-self-start align-self-md-center">
                                {{ $purchaseRequest->items->count() }} line {{ $purchaseRequest->items->count() === 1 ? 'item' : 'items' }}
                            </span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 52px;">#</th>
                                        <th class="text-center" style="width: 145px;">Deliverable Check</th>
                                        <th>Category</th>
                                        <th>Resource Item</th>
                                        <th>Deliverable</th>
                                        <th style="width: 180px;">Evidence</th>
                                        <th style="width: 150px;">Deliverable Date</th>
                                        <th class="text-end" style="width: 160px;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchaseRequest->items as $item)
                                        @php
                                            $itemEvidence = $lineItemEvidenceByItem->get($item->id);
                                            $itemDocuments = collect($itemEvidence?->documents ?? []);
                                            $isConfirmed = (bool) $itemEvidence?->is_met;
                                        @endphp
                                        <tr>
                                            <td class="text-muted">{{ $loop->iteration }}</td>
                                            <td class="text-center">
                                                @if ($evidencePurchaseOrder && $canManageLineItemEvidence)
                                                    <input type="checkbox"
                                                        class="form-check-input pr-evidence-open"
                                                        data-item-id="{{ $item->id }}"
                                                        @checked($isConfirmed)
                                                        aria-label="Open deliverable evidence for {{ $item->resource?->name ?? $item->resourceCategory?->name ?? 'line item' }}">
                                                @else
                                                    <input type="checkbox" class="form-check-input" @checked($isConfirmed) disabled>
                                                @endif
                                                <div class="mt-1">
                                                    @if ($itemEvidence)
                                                        <span class="badge {{ $isConfirmed ? 'bg-success-subtle text-success' : 'bg-info-subtle text-info' }} pr-line-evidence-status">
                                                            {{ $isConfirmed ? 'Confirmed' : 'Recorded' }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-light text-muted border pr-line-evidence-status">Pending</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $item->resourceCategory?->name ?? '—' }}</td>
                                            <td>{{ $item->resource?->name ?? '—' }}</td>
                                            <td class="deliverable-cell">
                                                <div class="fw-semibold">{{ $item->milestone ?: ($item->deliverable?->title ?? '—') }}</div>
                                                @if ($item->deliverable?->procurement)
                                                    <div class="small text-muted">{{ $item->deliverable->procurement->reference_no ?? $item->deliverable->procurement->title }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($evidencePurchaseOrder && $canManageLineItemEvidence)
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-primary pr-evidence-open"
                                                        data-item-id="{{ $item->id }}">
                                                        <i class="feather-upload-cloud me-1"></i> Evidence
                                                    </button>
                                                @elseif (! $evidencePurchaseOrder)
                                                    <span class="badge bg-light text-muted border">PO required</span>
                                                @endif

                                                @if ($itemDocuments->isNotEmpty())
                                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                                        @foreach ($itemDocuments as $documentIndex => $document)
                                                            <a href="{{ route('procurement.purchase-orders.line-item-evidence.document', [$evidencePurchaseOrder, $itemEvidence, $documentIndex]) }}?download=1"
                                                                class="badge bg-light text-dark border"
                                                                title="{{ $document['name'] ?? 'Document' }}">
                                                                {{ $document['display_name'] ?? $document['name'] ?? 'Document' }}
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td>{{ $item->milestone_date?->format('Y-m-d') ?? '—' }}</td>
                                            <td class="text-end fw-semibold">
                                                {{ $purchaseRequestCurrency }}
                                                {{ number_format((float) $item->amount, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="7" class="text-end">Total</th>
                                        <th class="text-end">
                                            {{ $purchaseRequestCurrency }}
                                            {{ number_format((float) $purchaseRequest->total_amount, 2) }}
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 order-2">
                <div class="pr-side-stack">
                <div class="card shadow-sm pr-side-card pr-card-hover">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Year Contributions</h6>

                        @if ($yearSplits->isEmpty())
                            <div class="text-muted">No year split data found.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Year</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($yearSplits as $year => $amount)
                                            <tr>
                                                <td>{{ $year }}</td>
                                                <td class="text-end fw-semibold">
                                                    {{ $purchaseRequestCurrency }}
                                                    {{ number_format((float) $amount, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm pr-side-card pr-card-hover mt-4">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                            <div>
                                <h6 class="fw-bold mb-1">Attachments</h6>
                                <div class="small text-muted">Supporting documents linked to this purchase request.</div>
                            </div>
                            @if ($canEditPurchaseRequests)
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#purchaseRequestAttachmentUploadModal">
                                    <i class="feather-paperclip me-1"></i> Add
                                </button>
                            @endif
                        </div>

                        @if ($purchaseRequest->attachments->isEmpty())
                            <div class="text-muted">No attachments have been uploaded.</div>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach ($purchaseRequest->attachments as $attachment)
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div class="min-w-0">
                                                <a href="{{ route('finance.purchase-requests.attachments.download', [$purchaseRequest, $attachment]) }}?download=1" class="fw-semibold">
                                                    <i class="feather-download me-1"></i>{{ $attachment->title ?: $attachment->file_name }}
                                                </a>
                                                <div class="small text-muted">
                                                    {{ $attachment->file_name }} | {{ $formatBytes($attachment->file_size_bytes) }}
                                                </div>
                                                <div class="small text-muted">
                                                    Uploaded by {{ $attachment->uploader?->name ?? 'System' }}
                                                    @if ($attachment->created_at)
                                                        on {{ $attachment->created_at->format('Y-m-d H:i') }}
                                                    @endif
                                                </div>
                                            </div>
                                            @if ($canEditPurchaseRequests)
                                                <form method="POST" action="{{ route('finance.purchase-requests.attachments.destroy', [$purchaseRequest, $attachment]) }}"
                                                    onsubmit="return confirm('Delete this attachment?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete attachment">
                                                        <i class="feather-trash-2"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                @if ($canApprovePurchaseRequests || in_array($purchaseRequestStatus, ['approved', 'rejected'], true))
                    <div class="card shadow-sm pr-side-card pr-card-hover mt-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Approval Decision</h6>

                            @if ($purchaseRequestStatus === 'approved')
                                <div class="alert alert-success mb-0">
                                    Approved by {{ $purchaseRequest->approver?->name ?? 'System' }}
                                    @if ($purchaseRequest->approved_at)
                                        on {{ $purchaseRequest->approved_at->format('Y-m-d H:i') }}
                                    @endif
                                </div>
                            @elseif ($purchaseRequestStatus === 'rejected')
                                <div class="alert alert-danger mb-0">
                                    <div class="fw-semibold">Rejected by {{ $purchaseRequest->rejector?->name ?? 'System' }}</div>
                                    @if ($purchaseRequest->rejected_at)
                                        <div class="small mb-2">{{ $purchaseRequest->rejected_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                    <div>{{ $purchaseRequest->rejection_reason ?? 'No reason recorded.' }}</div>
                                </div>
                            @elseif ($canDecidePurchaseRequest)
                                <form method="POST" action="{{ route('finance.purchase-requests.approve', $purchaseRequest) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="feather-check me-1"></i> Approve Purchase Request
                                    </button>
                                </form>

                                <button type="button"
                                    class="btn btn-outline-danger w-100 mt-2"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#rejectPurchaseRequestForm"
                                    aria-expanded="false"
                                    aria-controls="rejectPurchaseRequestForm">
                                    <i class="feather-x me-1"></i> Reject Purchase Request
                                </button>

                                <div class="collapse mt-3" id="rejectPurchaseRequestForm">
                                    <form method="POST" action="{{ route('finance.purchase-requests.reject', $purchaseRequest) }}">
                                        @csrf
                                        <div class="mb-3">
                                            <label class="form-label">Reason for rejection</label>
                                            <textarea name="rejection_reason"
                                                class="form-control @error('rejection_reason') is-invalid @enderror"
                                                rows="4"
                                                minlength="5"
                                                maxlength="1000"
                                                required>{{ old('rejection_reason') }}</textarea>
                                            @error('rejection_reason')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <button type="submit" class="btn btn-danger w-100">
                                            <i class="feather-x me-1"></i> Confirm Rejection
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="text-muted">No approval action is available for the current status.</div>
                            @endif
                        </div>
                    </div>
                @endif

                @can('finance.purchase_requests.send')
                    <div class="card shadow-sm pr-side-card pr-card-hover mt-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Send Purchase Request</h6>

                            <form method="POST" action="{{ route('finance.purchase-requests.send', $purchaseRequest) }}">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Recipient Name</label>
                                    <input type="text"
                                        name="recipient_name"
                                        value="{{ old('recipient_name') }}"
                                        class="form-control @error('recipient_name') is-invalid @enderror"
                                        required>
                                    @error('recipient_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Recipient Email</label>
                                    <input type="email"
                                        name="recipient_email"
                                        value="{{ old('recipient_email') }}"
                                        class="form-control @error('recipient_email') is-invalid @enderror"
                                        required>
                                    @error('recipient_email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                @error('email')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="feather-send me-1"></i> Send Email with PDF
                                </button>
                            </form>
                        </div>
                    </div>
                @endcan
                </div>
            </div>
        </div>

    </div>

@if ($canEditPurchaseRequests)
    <div class="modal fade" id="purchaseRequestAttachmentUploadModal" tabindex="-1" aria-labelledby="purchaseRequestAttachmentUploadTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('finance.purchase-requests.attachments.store', $purchaseRequest) }}" enctype="multipart/form-data" id="purchaseRequestAttachmentUploadForm">
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="purchaseRequestAttachmentUploadTitle">Upload Attachments</h5>
                        <div class="small text-muted">Add one or more supporting documents to this purchase request.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-semibold mb-0">Documents</label>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="prAttachmentUploadAdd">
                            <i class="feather-plus me-1"></i> Add Document
                        </button>
                    </div>
                    <div id="prAttachmentUploadList"></div>
                    <div class="form-text">PDF, Office, CSV, TXT, image, or ZIP files up to 20 MB each.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-upload-cloud me-1"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($evidencePurchaseOrder && $canManageLineItemEvidence)
    <div class="modal fade pr-line-evidence-modal" id="prLineItemEvidenceModal" tabindex="-1" aria-labelledby="prLineItemEvidenceTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('finance.purchase-requests.line-item-evidence.store', $purchaseRequest) }}" enctype="multipart/form-data" id="prLineItemEvidenceForm">
                @csrf
                <input type="hidden" name="purchase_request_item_id" id="prEvidenceItemId">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="prLineItemEvidenceTitle">Deliverable Evidence</h5>
                        <div class="small text-muted" id="prLineItemEvidenceSubtitle"></div>
                    </div>
                    <button type="button" class="btn-close pr-line-evidence-close" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="pr-line-evidence-summary mb-3">
                        <div class="small text-muted">Linked Deliverable</div>
                        <div class="fw-semibold" id="prLineItemEvidenceDeliverable">N/A</div>
                        <div class="small text-muted mt-1">Purchase Order: {{ $evidencePurchaseOrder->reference_no ?? 'N/A' }}</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input type="checkbox" class="form-check-input" name="is_met" id="prEvidenceMet" value="1">
                                <label class="form-check-label fw-semibold" for="prEvidenceMet">
                                    Confirm this deliverable for the line item
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="prEvidenceDate">Date</label>
                            <input type="date" name="deliverable_date" id="prEvidenceDate" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="prEvidenceNotes">Notes</label>
                            <textarea name="notes" id="prEvidenceNotes" class="form-control" rows="4" maxlength="3000"
                                placeholder="Add acceptance notes, delivery comments, or review observations."></textarea>
                        </div>
                    </div>

                    <div class="mt-3 d-none" id="prExistingDocumentsWrap">
                        <label class="form-label fw-semibold">Existing Documents</label>
                        <div class="d-flex flex-wrap gap-1" id="prExistingDocuments"></div>
                    </div>

                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-semibold mb-0">Relevant Documents</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="prEvidenceAddDocument">
                                <i class="feather-plus me-1"></i> Add Document
                            </button>
                        </div>
                        <div id="prEvidenceDocumentList"></div>
                        <div class="form-text">PDF, Office, image, or ZIP files up to 20 MB each.</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light pr-line-evidence-close">Close</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-1"></i> Save Evidence
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($canEditPurchaseRequests)
    @push('scripts')
        <script>
            (function () {
                const list = document.getElementById('prAttachmentUploadList');
                const addButton = document.getElementById('prAttachmentUploadAdd');

                function escapeHtml(value) {
                    return String(value || '').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;',
                    }[char]));
                }

                function addRow(title = '') {
                    if (!list) return;

                    const row = document.createElement('div');
                    row.className = 'pr-attachment-upload-row';
                    row.innerHTML = `
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold mb-1">Document Title</label>
                                <input type="text" name="pr_attachment_titles[]" class="form-control"
                                    maxlength="255" placeholder="Approval memo, TOR, quotation"
                                    value="${escapeHtml(title)}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">File</label>
                                <input type="file" name="pr_attachments[]" class="form-control" required
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.zip">
                            </div>
                            <div class="col-md-1 text-md-end">
                                <button type="button" class="btn btn-outline-danger w-100 pr-attachment-upload-remove" title="Remove document row">
                                    <i class="feather-trash-2"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    row.querySelector('.pr-attachment-upload-remove')?.addEventListener('click', () => {
                        if (list.querySelectorAll('.pr-attachment-upload-row').length <= 1) {
                            row.querySelector('input[type="text"]').value = '';
                            row.querySelector('input[type="file"]').value = '';
                            return;
                        }
                        row.remove();
                    });
                    list.appendChild(row);
                }

                addButton?.addEventListener('click', () => addRow());
                addRow();
            })();
        </script>
    @endpush
@endif

@if ($evidencePurchaseOrder && $canManageLineItemEvidence)
    @push('scripts')
        <script>
            (function () {
                const itemData = @json($lineItemModalPayload);
                const evidenceData = @json($lineItemEvidencePayload);

                const modal = document.getElementById('prLineItemEvidenceModal');
                const form = document.getElementById('prLineItemEvidenceForm');
                const itemIdInput = document.getElementById('prEvidenceItemId');
                const title = document.getElementById('prLineItemEvidenceTitle');
                const subtitle = document.getElementById('prLineItemEvidenceSubtitle');
                const deliverable = document.getElementById('prLineItemEvidenceDeliverable');
                const metInput = document.getElementById('prEvidenceMet');
                const dateInput = document.getElementById('prEvidenceDate');
                const notesInput = document.getElementById('prEvidenceNotes');
                const documentList = document.getElementById('prEvidenceDocumentList');
                const existingWrap = document.getElementById('prExistingDocumentsWrap');
                const existingDocuments = document.getElementById('prExistingDocuments');
                const addDocumentBtn = document.getElementById('prEvidenceAddDocument');

                function escapeHtml(value) {
                    return String(value || '').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;',
                    }[char]));
                }

                function addDocumentRow(documentName = '') {
                    if (!documentList) return;

                    const row = document.createElement('div');
                    row.className = 'pr-evidence-document-row';
                    row.innerHTML = `
                        <div>
                            <label class="pr-evidence-document-label">Document Name</label>
                            <input type="text" name="document_names[]" class="form-control" maxlength="255"
                                placeholder="Signed contract, delivery note, acceptance memo"
                                value="${escapeHtml(documentName)}">
                        </div>
                        <div>
                            <label class="pr-evidence-document-label">Upload File</label>
                            <input type="file" name="documents[]" class="form-control"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
                        </div>
                        <button type="button" class="btn btn-outline-danger pr-evidence-document-remove" aria-label="Remove document row">
                            <i class="feather-trash-2"></i>
                        </button>
                    `;
                    row.querySelector('.pr-evidence-document-remove')?.addEventListener('click', () => row.remove());
                    documentList.appendChild(row);
                }

                function showModal() {
                    if (!modal) return;

                    document.body.classList.add('pr-line-evidence-modal-open');
                    modal.classList.add('show');
                    modal.style.display = 'block';
                    modal.removeAttribute('aria-hidden');
                    modal.setAttribute('aria-modal', 'true');
                    modal.setAttribute('role', 'dialog');

                    setTimeout(() => metInput?.focus(), 0);
                }

                function closeModal() {
                    if (!modal) return;

                    modal.classList.remove('show');
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    modal.removeAttribute('aria-modal');
                    modal.removeAttribute('role');
                    document.body.classList.remove('pr-line-evidence-modal-open');
                }

                function openForItem(itemId) {
                    const item = itemData[itemId] || {};
                    const evidence = evidenceData[itemId] || {};

                    if (form) {
                        form.reset();
                    }
                    if (documentList) {
                        documentList.innerHTML = '';
                    }
                    if (existingDocuments) {
                        existingDocuments.innerHTML = '';
                    }

                    if (itemIdInput) itemIdInput.value = itemId;
                    if (title) title.textContent = item.title || 'Line item';
                    if (subtitle) subtitle.textContent = `${item.category || 'N/A'} | ${item.currency || ''} ${item.amount || '0.00'}`;
                    if (deliverable) deliverable.textContent = item.deliverable || 'No deliverable linked';
                    if (metInput) metInput.checked = Boolean(evidence.is_met);
                    if (dateInput) dateInput.value = evidence.deliverable_date || '';
                    if (notesInput) notesInput.value = evidence.notes || '';

                    const documents = Array.isArray(evidence.documents) ? evidence.documents : [];
                    if (existingWrap) {
                        existingWrap.classList.toggle('d-none', documents.length === 0);
                    }
                    documents.forEach((fileDocument) => {
                        const link = document.createElement('a');
                        link.href = fileDocument.url || '#';
                        link.className = 'badge bg-light text-dark border';
                        link.textContent = fileDocument.display_name || fileDocument.name || 'Document';
                        link.title = fileDocument.name || 'Document';
                        existingDocuments?.appendChild(link);
                    });

                    addDocumentRow();
                    showModal();
                }

                document.querySelectorAll('.pr-evidence-open').forEach((trigger) => {
                    trigger.addEventListener('click', (event) => {
                        if (trigger.matches('input[type="checkbox"]')) {
                            event.preventDefault();
                        }
                        openForItem(trigger.dataset.itemId);
                    });
                });

                addDocumentBtn?.addEventListener('click', () => addDocumentRow());
                document.querySelectorAll('.pr-line-evidence-close').forEach((button) => {
                    button.addEventListener('click', closeModal);
                });
                modal?.addEventListener('mousedown', (event) => {
                    if (event.target === modal) {
                        closeModal();
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal?.classList.contains('show')) {
                        closeModal();
                    }
                });
            })();
        </script>
    @endpush
@endif

@if ($canDeletePurchaseRequests)
    {{-- Cascade-Delete PR Modal --}}
    <div class="modal fade" id="deletePrModal" tabindex="-1" aria-labelledby="deletePrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="deletePrModalLabel">
                        <i class="feather-alert-triangle text-danger me-2"></i>Delete Purchase Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="deletePrModalBody">
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                        <div class="small text-muted mt-2">Loading impact details…</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0" id="deletePrModalFooter">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <form id="deletePrForm" method="POST"
          action="{{ route('finance.purchase-requests.destroy', $purchaseRequest) }}"
          style="display:none">
        @csrf
        @method('DELETE')
    </form>

    <form id="forceDeletePrForm" method="POST"
          action="{{ route('finance.purchase-requests.force-destroy', $purchaseRequest) }}"
          style="display:none">
        @csrf
        @method('DELETE')
    </form>

    @push('scripts')
    <script>
    (function () {
        const STATUS_COLORS = {
            draft: 'secondary', submitted: 'warning', approved: 'success',
            cancelled: 'danger', completed: 'success',
        };
        function statusBadge(status) {
            const col = STATUS_COLORS[status] || 'secondary';
            return `<span class="badge bg-${col} text-${col === 'warning' ? 'dark' : 'white'}">${status}</span>`;
        }
        function buildBody(data) {
            const s = data.summary;
            let html = `<p class="mb-3 text-muted" style="font-size:.9rem">
                <strong>${s.reference_no}</strong> &mdash; ${s.currency} ${s.total_amount} &mdash; ${statusBadge(s.status)}
            </p>`;
            if (!data.can_delete) {
                return html + `<div class="alert alert-danger mb-0">
                    <i class="feather-x-circle me-2"></i><strong>Cannot delete:</strong> ${data.block_reason}
                </div>`;
            }
            if (data.chain.length === 0) {
                return html + `<div class="alert alert-warning mb-0">This purchase request will be permanently deleted.</div>`;
            }
            html += `<p class="fw-semibold mb-2" style="font-size:.85rem">The following records will also be permanently deleted:</p>
                     <div class="list-group list-group-flush mb-3">`;
            data.chain.forEach(item => {
                if (item.type === 'purchase_request') {
                    html += `<div class="list-group-item px-0 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="badge bg-primary me-1" style="font-size:.68rem">Purchase Request</span><strong>${item.reference_no}</strong></div>
                            ${statusBadge(item.status)}
                        </div>
                        <div class="text-muted small mt-1">${item.currency} ${item.total_amount} &mdash; ${item.commitment_count} commitment${item.commitment_count !== 1 ? 's' : ''}, ${item.item_count} item${item.item_count !== 1 ? 's' : ''}</div>
                    </div>`;
                } else if (item.type === 'purchase_order') {
                    const extras = [];
                    if (item.disbursement_count > 0) extras.push(`${item.disbursement_count} disbursement${item.disbursement_count !== 1 ? 's' : ''}`);
                    if (item.has_invoice) extras.push('invoice');
                    if (item.has_negotiation) extras.push('negotiation');
                    html += `<div class="list-group-item px-0 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="badge bg-warning text-dark me-1" style="font-size:.68rem">Purchase Order</span><strong>${item.reference_no}</strong></div>
                            ${statusBadge(item.status)}
                        </div>
                        <div class="text-muted small mt-1">${item.currency} ${item.amount} &mdash; ${item.vendor}${extras.length ? ' &mdash; includes: ' + extras.join(', ') : ''}</div>
                    </div>`;
                }
            });
            html += `</div><div class="alert alert-danger mb-0" style="font-size:.85rem">
                <i class="feather-alert-triangle me-1"></i><strong>This action cannot be undone.</strong>
            </div>`;
            return html;
        }

        document.querySelector('.js-delete-pr')?.addEventListener('click', function () {
            const infoUrl        = this.dataset.infoUrl;
            const forceDeleteUrl = this.dataset.forceDeleteUrl;
            const body           = document.getElementById('deletePrModalBody');
            const footer         = document.getElementById('deletePrModalFooter');
            body.innerHTML = `<div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                <div class="small text-muted mt-2">Loading impact details…</div>
            </div>`;
            footer.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deletePrModal')).show();

            fetch(infoUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    body.innerHTML = buildBody(data);
                    if (data.can_delete) {
                        footer.innerHTML = `
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmPrDeleteBtn">
                                <i class="feather-trash-2 me-1"></i> Delete All
                            </button>`;
                        document.getElementById('confirmPrDeleteBtn').addEventListener('click', function () {
                            this.disabled = true;
                            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting…';
                            document.getElementById('deletePrForm').submit();
                        });
                    } else if (data.is_admin && forceDeleteUrl) {
                        document.getElementById('forceDeletePrForm').action = forceDeleteUrl;
                        footer.innerHTML = `
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="forcePrDeleteBtn">
                                <i class="feather-zap me-1"></i> Force Delete (Admin Only)
                            </button>`;
                        document.getElementById('forcePrDeleteBtn').addEventListener('click', function () {
                            this.disabled = true;
                            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Force Deleting…';
                            document.getElementById('forceDeletePrForm').submit();
                        });
                    } else {
                        footer.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>`;
                    }
                })
                .catch(() => {
                    body.innerHTML = `<div class="alert alert-danger">Failed to load details. Please try again.</div>`;
                });
        });
    })();
    </script>
    @endpush
@endif
@endsection
