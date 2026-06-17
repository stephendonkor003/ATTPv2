@extends('layouts.vendor')

@section('title', $purchaseRequest->reference_no)

@section('content')
    @php
        $indexRoute = route('vendor.purchase-requests.index');
        $documentRoute = 'vendor.purchase-requests.documents.download';
    @endphp

    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">Purchase Request</div>
            <h3 class="mb-1">{{ $purchaseRequest->title }}</h3>
            <p class="text-muted mb-0">{{ $purchaseRequest->reference_no }}</p>
        </div>
        <a href="{{ $indexRoute }}" class="btn btn-vendor-outline">
            <i class="feather-arrow-left me-1"></i> Back
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card vendor-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <span class="status-pill">{{ ucwords(str_replace('_', ' ', $purchaseRequest->status)) }}</span>
                            <span class="badge-soft ms-2">{{ ucfirst($purchaseRequest->priority) }}</span>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small">Requested Amount</div>
                            <div class="fs-4 fw-bold">
                                {{ $purchaseRequest->currency }} {{ number_format((float) $purchaseRequest->requested_amount, 2) }}
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Related Procurement</div>
                            <div class="fw-semibold">{{ $purchaseRequest->procurement?->title ?? 'Not linked' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Needed By</div>
                            <div class="fw-semibold">{{ $purchaseRequest->needed_by?->format('M d, Y') ?? 'Not set' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Submitted</div>
                            <div class="fw-semibold">{{ $purchaseRequest->created_at?->format('M d, Y H:i') }}</div>
                        </div>
                    </div>

                    @if ($purchaseRequest->description)
                        <div class="mt-4">
                            <div class="text-muted small">Description</div>
                            <div class="vendor-readable">{{ $purchaseRequest->description }}</div>
                        </div>
                    @endif

                    <div class="mt-4">
                        <div class="text-muted small">Business Justification</div>
                        <div class="vendor-readable">{{ $purchaseRequest->business_justification }}</div>
                    </div>
                </div>
            </div>

            <div class="card vendor-card">
                <div class="card-body">
                    <h5 class="mb-3">Line Items</h5>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Unit</th>
                                    <th class="text-end">Amount</th>
                                    <th>Delivery</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($purchaseRequest->items as $item)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $item->item_name }}</div>
                                            <small class="text-muted">{{ $item->description }}</small>
                                        </td>
                                        <td class="text-end">{{ number_format((float) $item->quantity, 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $item->amount, 2) }}</td>
                                        <td>{{ $item->delivery_date?->format('M d, Y') ?? 'Not set' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card vendor-card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Admin Feedback</h5>
                    @if ($purchaseRequest->admin_response)
                        <div class="vendor-readable">{{ $purchaseRequest->admin_response }}</div>
                        <div class="text-muted small mt-2">
                            Updated {{ $purchaseRequest->reviewed_at?->format('M d, Y H:i') ?? 'recently' }}
                        </div>
                    @else
                        <p class="text-muted mb-0">No admin feedback yet. The request is still available in the admin queue.</p>
                    @endif
                </div>
            </div>

            <div class="card vendor-card">
                <div class="card-body">
                    <h5 class="mb-3">Documents</h5>
                    @forelse ($purchaseRequest->documents as $document)
                        <a href="{{ route($documentRoute, [$purchaseRequest, $document]) }}" class="vendor-file-row">
                            <span><i class="feather-paperclip"></i></span>
                            <span>
                                <strong>{{ $document->title }}</strong>
                                <small>{{ $document->file_name }}</small>
                            </span>
                        </a>
                    @empty
                        <p class="text-muted mb-0">No documents uploaded.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
