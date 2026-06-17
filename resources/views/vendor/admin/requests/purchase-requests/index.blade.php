@extends('layouts.app')

@section('title', 'Vendor Purchase Requests')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-shopping-bag text-primary me-2"></i>
                    Vendor Purchase Requests
                </h4>
                <p class="text-muted mb-0">Review vendor-submitted purchase requests.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('vendors.requests.reports.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="feather-clipboard me-1"></i> Reports
                </a>
                <a href="{{ route('vendors.requests.messages.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="feather-message-square me-1"></i> Clarifications
                </a>
                <a href="{{ route('vendors.requests.information.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="feather-inbox me-1"></i> Information
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <x-data-table id="vendorPurchaseRequestsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Vendor</th>
                            <th>Title</th>
                            <th>Amount</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $requestRecord)
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $requestRecord->reference_no }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $requestRecord->user->name ?? 'Vendor' }}</div>
                                    <div class="text-muted small">{{ $requestRecord->user->email ?? 'N/A' }}</div>
                                </td>
                                <td>{{ $requestRecord->title }}</td>
                                <td>
                                    {{ $requestRecord->currency }}
                                    {{ number_format((float) $requestRecord->requested_amount, 2) }}
                                </td>
                                <td>{{ ucfirst($requestRecord->priority ?? 'normal') }}</td>
                                <td>{{ \Illuminate\Support\Str::headline($requestRecord->status ?? 'submitted') }}</td>
                                <td>{{ $requestRecord->created_at?->format('d M Y') ?? 'N/A' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('vendors.requests.purchase-requests.show', $requestRecord) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="feather-eye me-1"></i> View
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
