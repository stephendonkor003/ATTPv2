@extends('layouts.app')

@section('title', 'Vendor Reports')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-clipboard text-primary me-2"></i>
                    Vendor Reports
                </h4>
                <p class="text-muted mb-0">Review progress, financial, completion, and deliverable reports from vendors.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('vendors.requests.purchase-requests.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="feather-shopping-bag me-1"></i> Purchase Requests
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
                <x-data-table id="vendorReportsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Vendor</th>
                            <th>Report</th>
                            <th>Type</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reports as $report)
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $report->reference_no }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $report->user->name ?? 'Vendor' }}</div>
                                    <div class="text-muted small">{{ $report->user->email ?? 'N/A' }}</div>
                                </td>
                                <td>{{ $report->title }}</td>
                                <td>{{ \Illuminate\Support\Str::headline($report->report_type) }}</td>
                                <td>
                                    {{ $report->reporting_period_start?->format('d M Y') ?? 'N/A' }}
                                    -
                                    {{ $report->reporting_period_end?->format('d M Y') ?? 'N/A' }}
                                </td>
                                <td>{{ \Illuminate\Support\Str::headline($report->status ?? 'submitted') }}</td>
                                <td>{{ $report->created_at?->format('d M Y') ?? 'N/A' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('vendors.requests.reports.show', $report) }}"
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
