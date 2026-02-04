@extends('layouts.app')

@section('title', 'Vendor Clarification Messages')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-message-square text-primary me-2"></i>
                    Vendor Clarifications
                </h4>
                <p class="text-muted mb-0">Review and respond to clarification messages from vendors.</p>
            </div>
            <a href="{{ route('vendors.requests.information.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-inbox me-1"></i> Information Requests
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <x-data-table id="vendorMessagesTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Vendor</th>
                            <th>Procurement</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($messages as $message)
                            <tr>
                                <td class="ps-4">{{ $message->user->name ?? 'Vendor' }}</td>
                                <td>{{ $message->procurement->title ?? '—' }}</td>
                                <td>{{ $message->subject ?? '—' }}</td>
                                <td>{{ ucfirst($message->status ?? 'open') }}</td>
                                <td>{{ $message->created_at?->format('d M Y') ?? '—' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('vendors.requests.messages.show', $message) }}"
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
