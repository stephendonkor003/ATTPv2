@extends('layouts.vendor')

@section('title', 'Request Clarification')

@section('content')
    @php
        use Illuminate\Support\Str;
    @endphp

    <div class="mb-4">
        <h3 class="mb-1">Request Clarification</h3>
        <p class="text-muted mb-0">Send questions or request additional information from the procurement team.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>There were errors with your request.</strong>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <h5 class="mb-3">Send a Message</h5>
                    <form method="POST" action="{{ route('vendor.messages.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Procurement (optional)</label>
                            <select name="procurement_id" class="form-select">
                                <option value="">General</option>
                                @foreach ($vendorProcurements as $procurement)
                                    <option value="{{ $procurement->id }}">
                                        {{ $procurement->reference_no ?? 'N/A' }} - {{ $procurement->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Subject</label>
                            <input type="text" name="subject" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Message</label>
                            <textarea name="message" rows="5" class="form-control" required></textarea>
                        </div>
                        <button class="btn btn-vendor" type="submit">Send Message</button>
                    </form>

                    <hr class="my-4">

                    <h6 class="mb-3">Recent Messages</h6>
                    @forelse ($messages as $message)
                        <div class="mb-3">
                            <div class="fw-semibold">{{ $message->subject }}</div>
                            <div class="text-muted small">
                                {{ $message->procurement?->reference_no ?? 'General' }} ·
                                {{ $message->created_at?->format('M d, Y') }}
                            </div>
                            <div class="text-muted small">{{ Str::limit($message->message, 140) }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No messages sent yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <h5 class="mb-3">Request Information</h5>
                    <form method="POST" action="{{ route('vendor.information-requests.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Procurement (optional)</label>
                            <select name="procurement_id" class="form-select">
                                <option value="">General</option>
                                @foreach ($vendorProcurements as $procurement)
                                    <option value="{{ $procurement->id }}">
                                        {{ $procurement->reference_no ?? 'N/A' }} - {{ $procurement->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Request Topic</label>
                            <input type="text" name="request_topic" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Details</label>
                            <textarea name="details" rows="5" class="form-control" required></textarea>
                        </div>
                        <button class="btn btn-vendor" type="submit">Send Request</button>
                    </form>

                    <hr class="my-4">

                    <h6 class="mb-3">Recent Requests</h6>
                    @forelse ($informationRequests as $request)
                        <div class="mb-3">
                            <div class="fw-semibold">{{ $request->request_topic }}</div>
                            <div class="text-muted small">
                                {{ $request->procurement?->reference_no ?? 'General' }} ·
                                {{ $request->created_at?->format('M d, Y') }}
                            </div>
                            <div class="text-muted small">{{ Str::limit($request->details, 140) }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No information requests sent yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
