@extends('layouts.app')

@section('title', 'Vendor Purchase Request Details')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Vendor Purchase Request</h4>
                <p class="text-muted mb-0">{{ $purchaseRequest->reference_no }} submitted by {{ $purchaseRequest->user->name ?? 'Vendor' }}.</p>
            </div>
            <a href="{{ route('vendors.requests.purchase-requests.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please check the highlighted fields and try again.</strong>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                            <div>
                                <div class="text-muted small">Request</div>
                                <h5 class="fw-bold mb-1">{{ $purchaseRequest->title }}</h5>
                                <div class="text-muted">{{ \Illuminate\Support\Str::headline($purchaseRequest->request_type) }}</div>
                            </div>
                            <span class="badge bg-primary-subtle text-primary px-3 py-2">
                                {{ \Illuminate\Support\Str::headline($purchaseRequest->status ?? 'submitted') }}
                            </span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">Vendor</div>
                                <div class="fw-semibold">{{ $purchaseRequest->user->name ?? 'Vendor' }}</div>
                                <div class="text-muted small">{{ $purchaseRequest->user->email ?? 'N/A' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Requested Amount</div>
                                <div class="fw-semibold">
                                    {{ $purchaseRequest->currency }}
                                    {{ number_format((float) $purchaseRequest->requested_amount, 2) }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Funding Source</div>
                                <div class="fw-semibold">
                                    {{ $purchaseRequest->subActivity?->name ?? $purchaseRequest->procurement?->title ?? 'N/A' }}
                                </div>
                                @if ($purchaseRequest->subActivity?->activity?->project)
                                    <div class="text-muted small">
                                        {{ $purchaseRequest->subActivity->activity->project->name }}
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Purchase Order</div>
                                <div class="fw-semibold">{{ $purchaseRequest->purchaseOrder->reference_no ?? 'N/A' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Needed By</div>
                                <div class="fw-semibold">{{ $purchaseRequest->needed_by?->format('d M Y') ?? 'N/A' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Priority</div>
                                <div class="fw-semibold">{{ ucfirst($purchaseRequest->priority ?? 'normal') }}</div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="text-muted small">Description</div>
                            <div class="border rounded p-3 bg-light">{{ $purchaseRequest->description ?: 'No description supplied.' }}</div>
                        </div>

                        @if ($purchaseRequest->business_justification)
                            <div class="mt-3">
                                <div class="text-muted small">Business Justification</div>
                                <div class="border rounded p-3 bg-light">{{ $purchaseRequest->business_justification }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-semibold">Line Items</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Amount</th>
                                        <th>Delivery</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchaseRequest->items as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $item->item_name }}</div>
                                                <div class="text-muted small">{{ $item->description ?: 'N/A' }}</div>
                                            </td>
                                            <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                            <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                                            <td>{{ number_format((float) $item->amount, 2) }}</td>
                                            <td>{{ $item->delivery_date?->format('d M Y') ?? 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-semibold">Admin Review</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info small">
                            Select <strong>Return to Vendor for Correction</strong> and add a response note to let the vendor edit and resubmit this request. The vendor will receive an email automatically.
                        </div>
                        <form action="{{ route('vendors.requests.purchase-requests.respond', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-control" required>
                                    @foreach ([
                                        'submitted' => 'Submitted',
                                        'in_review' => 'In Review',
                                        'revision_requested' => 'Return to Vendor for Correction',
                                        'approved' => 'Approved',
                                        'rejected' => 'Rejected',
                                        'converted' => 'Converted',
                                        'completed' => 'Completed',
                                    ] as $status => $statusLabel)
                                        <option value="{{ $status }}" {{ $purchaseRequest->status === $status ? 'selected' : '' }}>
                                            {{ $statusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Response</label>
                                <textarea name="admin_response" rows="6" class="form-control"
                                    placeholder="Required when returning the request to the vendor.">{{ old('admin_response', $purchaseRequest->admin_response) }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="feather-send me-1"></i> Save Review
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-semibold">Attached Documents</h6>
                    </div>
                    <div class="card-body">
                        @forelse ($purchaseRequest->documents as $document)
                            <div class="border rounded p-3 mb-2">
                                <div class="fw-semibold">{{ $document->title }}</div>
                                <div class="text-muted small">{{ $document->file_name }}</div>
                                <a href="{{ route('vendors.requests.documents.download', $document) }}"
                                    class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="feather-download me-1"></i> Download
                                </a>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No documents attached.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
