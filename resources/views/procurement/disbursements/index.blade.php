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
                            <th>Vendor</th>
                            <th class="text-center">Amount</th>
                            <th class="text-center">Paid At</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" width="120">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($disbursements as $disbursement)
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $disbursement->reference_no ?? 'N/A' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $disbursement->purchaseOrder?->reference_no ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ $disbursement->procurement?->title ?? 'N/A' }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $disbursement->vendor?->name ?? 'Vendor' }}</div>
                                    <small class="text-muted">{{ $disbursement->vendor?->email ?? 'N/A' }}</small>
                                </td>
                                <td class="text-center">
                                    {{ $disbursement->amount ? number_format($disbursement->amount, 2) : 'N/A' }}
                                    {{ $disbursement->currency ?? '' }}
                                </td>
                                <td class="text-center">
                                    {{ $disbursement->paid_at?->format('d M Y') ?? 'N/A' }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary text-capitalize">
                                        {{ $disbursement->status ?? 'completed' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('procurement.disbursements.show', $disbursement) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
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
