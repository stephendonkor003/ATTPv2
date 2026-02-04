@extends('layouts.app')

@section('title', 'Information Request Details')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Information Request</h4>
                <p class="text-muted mb-0">Review the request and send a response.</p>
            </div>
            <a href="{{ route('vendors.requests.information.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Request Details</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Vendor</div>
                        <div class="fw-semibold">{{ $requestRecord->user->name ?? 'Vendor' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Email</div>
                        <div class="fw-semibold">{{ $requestRecord->user->email ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Procurement</div>
                        <div class="fw-semibold">{{ $requestRecord->procurement->title ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Status</div>
                        <div class="fw-semibold">{{ ucfirst($requestRecord->status ?? 'open') }}</div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="text-muted small">Topic</div>
                    <div class="fw-semibold">{{ $requestRecord->request_topic ?? '—' }}</div>
                </div>

                <div class="mt-3">
                    <div class="text-muted small">Details</div>
                    <div class="border rounded p-3 bg-light">{{ $requestRecord->details }}</div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0 fw-semibold">Respond to Vendor</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('vendors.requests.information.respond', $requestRecord) }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-control" required>
                                <option value="open" {{ $requestRecord->status === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ $requestRecord->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="closed" {{ $requestRecord->status === 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Response</label>
                            <textarea name="response" rows="5" class="form-control" required>{{ old('response', $requestRecord->response) }}</textarea>
                        </div>
                    </div>
                    <div class="mt-3 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-send me-1"></i> Send Response
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
