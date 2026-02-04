@extends('layouts.app')

@section('title', 'Vendor Information Requests')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-inbox text-primary me-2"></i>
                    Vendor Information Requests
                </h4>
                <p class="text-muted mb-0">Review and respond to vendor information requests.</p>
            </div>
            <a href="{{ route('vendors.requests.messages.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-message-square me-1"></i> Clarification Messages
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <x-data-table id="vendorInfoRequestsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Vendor</th>
                            <th>Procurement</th>
                            <th>Topic</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $requestRecord)
                            <tr>
                                <td class="ps-4">{{ $requestRecord->user->name ?? 'Vendor' }}</td>
                                <td>{{ $requestRecord->procurement->title ?? '—' }}</td>
                                <td>{{ $requestRecord->request_topic ?? '—' }}</td>
                                <td>{{ ucfirst($requestRecord->status ?? 'open') }}</td>
                                <td>{{ $requestRecord->created_at?->format('d M Y') ?? '—' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('vendors.requests.information.show', $requestRecord) }}"
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
