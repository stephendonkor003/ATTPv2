@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Purchase Requests</h4>
                <p class="text-muted mb-0">
                    Auto-generated from budget commitments (scoped by governance node)
                </p>
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

        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <x-data-table id="purchaseRequestsTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Reference</th>
                            @if ($canViewAll)
                                <th>Governance Node</th>
                            @endif
                            <th>Program</th>
                            <th>Sub-Activity</th>
                            <th>Start Year</th>
                            <th>Commitment Date</th>
                            <th>Delivery Date</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($purchaseRequests as $pr)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $pr->reference_no }}</td>
                                @if ($canViewAll)
                                    <td>{{ $pr->governanceNode?->name ?? '—' }}</td>
                                @endif
                                <td>{{ $pr->programFunding?->program?->name ?? $pr->programFunding?->program_name ?? '—' }}</td>
                                <td>{{ $pr->subActivity?->name ?? '—' }}</td>
                                <td><span class="badge bg-light text-dark">{{ $pr->start_year }}</span></td>
                                <td>{{ $pr->commitment_date?->format('F j, Y') ?? '—' }}</td>
                                <td>{{ $pr->delivery_date?->format('F j, Y') ?? '—' }}</td>
                                <td class="text-end fw-bold">
                                    <span class="text-muted me-1">
                                        {{ $pr->currency ?? $pr->programFunding?->program?->currency ?? '' }}
                                    </span>
                                    {{ number_format((float) $pr->total_amount, 2) }}
                                </td>
                                <td>
                                    <span class="badge {{ $pr->status === 'approved' ? 'bg-success' : ($pr->status === 'submitted' ? 'bg-warning text-dark' : ($pr->status === 'cancelled' ? 'bg-danger' : 'bg-secondary')) }}">
                                        {{ ucfirst($pr->status) }}
                                    </span>
                                </td>
                                <td>{{ $pr->created_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('finance.purchase-requests.show', $pr) }}"
                                        class="btn btn-sm btn-outline-primary"
                                        title="View Purchase Request">
                                        <i class="feather-eye"></i>
                                    </a>
                                    <a href="{{ route('finance.purchase-requests.download', $pr) }}"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Download PDF">
                                        <i class="feather-download"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>

    </div>
@endsection
