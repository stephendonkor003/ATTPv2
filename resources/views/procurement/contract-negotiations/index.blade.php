@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">Contract Negotiations</h4>
                <p class="text-muted mb-0">Manage vendor negotiations before award and PO creation.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <x-data-table id="contractNegotiationsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Procurement</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Submissions</th>
                            <th class="text-center">Negotiations</th>
                            <th class="text-center">Agreed</th>
                            <th class="text-center" width="120">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($procurements as $procurement)
                            <tr>
                                <td class="ps-4 fw-semibold">
                                    {{ $procurement->reference_no ?? 'N/A' }}
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $procurement->title }}</div>
                                    <small class="text-muted">{{ $procurement->resource->name ?? 'N/A' }}</small>
                                </td>
                                <td class="text-center">
                                    @php
                                        $statusColors = [
                                            'draft' => 'secondary',
                                            'submitted' => 'warning',
                                            'rejected' => 'danger',
                                            'approved' => 'success',
                                            'published' => 'primary',
                                            'closed' => 'dark',
                                            'awarded' => 'success',
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $statusColors[$procurement->status] ?? 'secondary' }}">
                                        {{ ucfirst($procurement->status ?? 'draft') }}
                                    </span>
                                </td>
                                <td class="text-center">{{ $procurement->submissions_count }}</td>
                                <td class="text-center">{{ $procurement->contract_negotiations_count }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ ($procurement->agreed_negotiations_count ?? 0) > 0 ? 'success' : 'secondary' }}">
                                        {{ $procurement->agreed_negotiations_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('procurement.contract-negotiations.show', $procurement) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        Manage
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>

                <div class="mt-3">
                    {{ $procurements->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
