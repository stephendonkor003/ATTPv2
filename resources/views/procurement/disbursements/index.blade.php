@extends('layouts.app')

@push('styles')
    <style>
        .disb-page .hero-card {
            background: linear-gradient(120deg, #0f172a 0%, #1e293b 45%, #14b8a6 100%);
            color: #fff;
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }

        .disb-page .hero-card h1,
        .disb-page .hero-card h2,
        .disb-page .hero-card h3,
        .disb-page .hero-card h4,
        .disb-page .hero-card h5,
        .disb-page .hero-card h6,
        .disb-page .hero-card p,
        .disb-page .hero-card .text-muted {
            color: #fff !important;
        }

        .disb-page .stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        @media (hover: hover) and (pointer: fine) {
            .disb-page .stat-card:hover {
                border-color: #14b8a6;
                box-shadow: 0 18px 34px rgba(15, 23, 42, 0.14);
                transform: translateY(-3px);
            }

            .disb-page .stat-card:hover .stat-value {
                color: #0f766e;
            }
        }

        .disb-page .stat-title {
            color: #64748b;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .disb-page .stat-value {
            color: #0f172a;
            font-size: 1.4rem;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .disb-page .table-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 16px 28px rgba(15, 23, 42, 0.08);
        }

        .disb-page .table thead th {
            background: #f8fafc;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container disb-page">
        <div class="card hero-card mb-4">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start">
                <div>
                    <h4 class="fw-bold mb-1">Planned Disbursements</h4>
                    <p class="mb-0">Track planned payments against purchase orders.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
                    <span class="badge bg-light text-dark px-3 py-2">
                        Budget Execution
                    </span>
                    <a href="{{ route('procurement.disbursements.create') }}" class="btn btn-light btn-sm">
                        <i class="feather-plus-circle me-1"></i> New Planned Disbursement
                    </a>
                </div>
            </div>
        </div>

        @php
            $summaryCurrency = $disbursementSummary['currency'] ?? 'USD';
            $cardMoney = fn ($value) => trim($summaryCurrency . ' ' . number_format((float) $value, 2));
            $currencyNote = $summaryCurrency === 'Mixed' ? 'Multiple program currencies' : 'Program currency';
            $unsupportedPaidReceipts = (int) ($disbursementSummary['unsupported_paid_receipts'] ?? 0);
            $unsupportedPaidAmount = (float) ($disbursementSummary['unsupported_paid_amount'] ?? 0);
        @endphp

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">Total Receipts</div>
                    <div class="stat-value">{{ number_format((int) ($disbursementSummary['total_receipts'] ?? 0)) }}</div>
                    <div class="text-muted small">Across all pages</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">Actual Paid Amount</div>
                    <div class="stat-value">{{ $cardMoney($disbursementSummary['total_paid_amount'] ?? 0) }}</div>
                    <div class="text-muted small">Completed/paid receipts with a live source | {{ $currencyNote }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">This Month Paid</div>
                    <div class="stat-value">{{ $cardMoney($disbursementSummary['this_month_paid_amount'] ?? 0) }}</div>
                    <div class="text-muted small">{{ now()->format('F Y') }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">Pending / Other Amount</div>
                    <div class="stat-value">{{ $cardMoney($disbursementSummary['pending_amount'] ?? 0) }}</div>
                    <div class="text-muted small">
                        Not counted as actual paid
                        @if ($unsupportedPaidReceipts > 0)
                            | Includes {{ number_format($unsupportedPaidReceipts) }} unsupported historical
                            {{ \Illuminate\Support\Str::plural('receipt', $unsupportedPaidReceipts) }}
                            ({{ $cardMoney($unsupportedPaidAmount) }})
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">Paid PO Lines</div>
                    <div class="stat-value">{{ number_format((int) ($disbursementSummary['paid_line_items'] ?? 0)) }}</div>
                    <div class="text-muted small">
                        {{ number_format((int) ($disbursementSummary['paid_purchase_orders'] ?? 0)) }} purchase orders covered
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">Latest Receipt</div>
                    <div class="stat-value">{{ $latestDisbursement?->reference_no ?? 'N/A' }}</div>
                    <div class="text-muted small">{{ $latestDisbursement?->paid_at?->format('d M Y') ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card table-card">
            <div class="card-body">
                <x-data-table id="disbursementsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Receipt Ref</th>
                            <th>Purchase Order</th>
                            <th>Paid Line</th>
                            <th>Vendor</th>
                            <th class="text-center">Amount</th>
                            <th class="text-center">Paid At</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Procurement</th>
                            <th class="text-center" width="230">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($disbursements as $disbursement)
                            @php
                                $procurementComplete = $disbursement->isProcurementProcessingComplete();
                                $recordedAsPaid = $disbursement->paid_at
                                    && in_array(strtolower((string) $disbursement->status), \App\Models\ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES, true);
                                $hasLiveFinancialSource = $disbursement->purchaseOrder
                                    || $disbursement->procurement
                                    || $disbursement->fundAllocation
                                    || $disbursement->consortiumDisbursementRequest;
                                $isUnsupportedHistoricalReceipt = $recordedAsPaid && ! $hasLiveFinancialSource;
                            @endphp
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $disbursement->reference_no ?? 'N/A' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $disbursement->purchaseOrder?->reference_no ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ $disbursement->procurement?->title ?? 'N/A' }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $disbursement->purchaseRequestItem?->resource?->name
                                            ?? $disbursement->purchaseRequestItem?->resourceCategory?->name
                                            ?? 'N/A' }}
                                    </div>
                                    <small class="text-muted">
                                        {{ $disbursement->purchaseRequestItem?->milestone
                                            ?: ($disbursement->deliverable?->title ?? $disbursement->deliverable?->status ?? '') }}
                                    </small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $disbursement->vendor?->name ?? 'Vendor' }}</div>
                                    <small class="text-muted">{{ $disbursement->vendor?->email ?? 'N/A' }}</small>
                                </td>
                                <td class="text-center">
                                    {{ $disbursement->amount ? $disbursement->resolved_currency . ' ' . number_format($disbursement->amount, 2) : 'N/A' }}
                                </td>
                                <td class="text-center">
                                    {{ $disbursement->paid_at?->format('d M Y') ?? 'N/A' }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary text-capitalize">
                                        {{ $disbursement->status ?? 'completed' }}
                                    </span>
                                    @if ($isUnsupportedHistoricalReceipt)
                                        <div class="mt-1">
                                            <span class="badge bg-danger" title="Excluded from Actual Paid because its source record no longer exists.">
                                                Unsupported historical receipt
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $procurementComplete ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ $disbursement->procurement_processing_status_label }}
                                    </span>
                                    @if ($disbursement->goods_receipt_reference)
                                        <div class="small text-muted mt-1">{{ $disbursement->goods_receipt_reference }}</div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-wrap justify-content-center gap-1">
                                        <a href="{{ route('procurement.disbursements.show', $disbursement) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                        @if ($canHandleProcurementProcessing && $disbursement->isAwaitingProcurementProcessing())
                                            <a href="{{ route('procurement.disbursements.show', $disbursement) }}#procurementProcessingPanel"
                                                class="btn btn-sm btn-outline-success">
                                                Input SAP 52
                                            </a>
                                        @endif
                                        @if ($canEditDisbursements)
                                            <a href="{{ route('procurement.disbursements.edit', $disbursement) }}"
                                                class="btn btn-sm btn-outline-warning">
                                                Edit
                                            </a>
                                            <form method="POST"
                                                action="{{ route('procurement.disbursements.destroy', $disbursement) }}"
                                                onsubmit="return confirm('Revert this payment? The receipt will stay on record, but the linked purchase order payment totals will be recalculated.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Revert Payment
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>

                <div class="mt-3">
                    {{ $disbursements->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
