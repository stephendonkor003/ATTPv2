@extends('layouts.app')

@section('title', 'Clarification Details')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Clarification Message</h4>
                <p class="text-muted mb-0">Review the request and send a response.</p>
            </div>
            <a href="{{ route('vendors.requests.messages.index') }}" class="btn btn-outline-secondary btn-sm">
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
                        <div class="fw-semibold">{{ $message->user->name ?? 'Vendor' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Email</div>
                        <div class="fw-semibold">{{ $message->user->email ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Procurement</div>
                        <div class="fw-semibold">{{ $message->procurement->title ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Status</div>
                        <div class="fw-semibold">{{ ucfirst($message->status ?? 'open') }}</div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="text-muted small">Subject</div>
                    <div class="fw-semibold">{{ $message->subject ?? '—' }}</div>
                </div>

                <div class="mt-3">
                    <div class="text-muted small">Message</div>
                    <div class="border rounded p-3 bg-light">{{ $message->message }}</div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0 fw-semibold">Respond to Vendor</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('vendors.requests.messages.respond', $message) }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-control" required>
                                <option value="open" {{ $message->status === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ $message->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="closed" {{ $message->status === 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Response</label>
                            <textarea name="response" rows="5" class="form-control" required>{{ old('response', $message->response) }}</textarea>
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
