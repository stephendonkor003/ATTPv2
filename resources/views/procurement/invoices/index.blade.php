@extends('layouts.app')

@push('styles')
    <style>
        .invoice-page .hero-card {
            background: linear-gradient(120deg, #0f172a 0%, #1e293b 45%, #10b981 100%);
            color: #fff;
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }

        .invoice-page .hero-card h4 {
            color: #fff;
        }

        .invoice-page .hero-card p {
            color: rgba(255, 255, 255, 0.75);
        }

        .invoice-page .table-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 16px 28px rgba(15, 23, 42, 0.08);
        }

        .invoice-page .table thead th {
            background: #f8fafc;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container invoice-page">
        <div class="card hero-card mb-4">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start">
                <div>
                    <h4 class="fw-bold mb-1">Vendor Invoices</h4>
                    <p class="mb-0">Review monthly invoices and generate purchase orders.</p>
                </div>
                <div class="mt-3 mt-lg-0">
                    <span class="badge bg-light text-dark px-3 py-2">
                        Budget Execution
                    </span>
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
                <x-data-table id="procurementInvoicesTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Invoice Ref</th>
                            <th>Procurement</th>
                            <th>Vendor</th>
                            <th class="text-center">Month</th>
                            <th class="text-center">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" width="120">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            @php
                                $linkedPurchaseOrder = $invoice->purchaseOrder ?: $invoice->evidence?->purchaseOrder;
                                $isThinkTankTransfer = $linkedPurchaseOrder?->po_type === 'think_tank_transfer';
                                $procurementTitle = $isThinkTankTransfer
                                    ? 'Funding to Think Tanks'
                                    : ($invoice->procurement?->title ?? $linkedPurchaseOrder?->po_title ?? 'Purchase order invoice');
                                $procurementReference = $isThinkTankTransfer
                                    ? ($linkedPurchaseOrder?->reference_no ?? 'Think tank transfer')
                                    : ($invoice->procurement?->reference_no ?? $linkedPurchaseOrder?->reference_no ?? 'N/A');
                                $vendorName = $isThinkTankTransfer
                                    ? ($linkedPurchaseOrder?->thinkTankMember?->name ?? $invoice->vendor?->name ?? 'Think Tank')
                                    : ($invoice->vendor?->name ?? 'Vendor');
                                $vendorEmail = $isThinkTankTransfer
                                    ? ($linkedPurchaseOrder?->thinkTankMember?->email ?? $invoice->vendor?->email ?? 'N/A')
                                    : ($invoice->vendor?->email ?? 'N/A');
                                $badgeClass = match ($invoice->status) {
                                    'paid' => 'bg-success',
                                    'approved' => 'bg-primary',
                                    'rejected' => 'bg-danger',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <tr>
                                <td class="ps-4 fw-semibold">
                                    {{ $invoice->reference_no ?? 'N/A' }}
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $procurementTitle }}</div>
                                    <small class="text-muted">{{ $procurementReference }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $vendorName }}</div>
                                    <small class="text-muted">{{ $vendorEmail }}</small>
                                </td>
                                <td class="text-center">
                                    {{ $invoice->invoice_month?->format('M Y') ?? 'N/A' }}
                                </td>
                                <td class="text-center">
                                    {{ $invoice->amount ? number_format($invoice->amount, 2) : 'N/A' }}
                                    {{ $invoice->currency ?? '' }}
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $badgeClass }} text-capitalize">
                                        {{ str_replace('_', ' ', $invoice->status ?? 'submitted') }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('procurement.invoices.show', $invoice) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>

                <div class="mt-3">
                    {{ $invoices->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
