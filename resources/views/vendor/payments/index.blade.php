@extends('layouts.vendor')

@section('title', 'Payment Records')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">Payment Records</h3>
        <p class="text-muted mb-0">Track disbursements paid against your purchase orders.</p>
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            @if ($disbursements->isEmpty())
                <p class="text-muted mb-0">No payment records yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Receipt Ref</th>
                                <th>Purchase Order</th>
                                <th>Procurement</th>
                                <th>Amount</th>
                                <th>Paid At</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($disbursements as $disbursement)
                                <tr>
                                    <td>
                                        <span class="badge-soft">{{ $disbursement->reference_no ?? 'N/A' }}</span>
                                    </td>
                                    <td>{{ $disbursement->purchaseOrder?->reference_no ?? 'N/A' }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $disbursement->procurement?->title ?? 'N/A' }}</div>
                                        <small class="text-muted">{{ $disbursement->procurement?->reference_no ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        {{ $disbursement->amount ? number_format($disbursement->amount, 2) : 'N/A' }}
                                        {{ $disbursement->currency ?? '' }}
                                    </td>
                                    <td>{{ $disbursement->paid_at?->format('M d, Y') ?? 'N/A' }}</td>
                                    <td>
                                        <span class="status-pill text-capitalize">{{ $disbursement->status ?? 'completed' }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ route('vendor.payments.show', $disbursement) }}"
                                                class="btn btn-vendor-outline btn-sm">View</a>
                                            <a href="{{ route('vendor.payments.pdf', $disbursement) }}"
                                                class="btn btn-light btn-sm">PDF</a>
                                            <a href="{{ route('vendor.payments.download', $disbursement) }}"
                                                class="btn btn-vendor btn-sm">Download</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
