@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Purchase Request: {{ $purchaseRequest->reference_no }}</h4>
                <p class="text-muted mb-0">
                    Generated from a budget commitment (multi-year supported)
                </p>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('finance.purchase-requests.index') }}" class="btn btn-outline-secondary">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
                <a href="{{ route('finance.purchase-requests.pdf', $purchaseRequest) }}" class="btn btn-outline-primary" target="_blank">
                    <i class="feather-file-text me-1"></i> View PDF
                </a>
                <a href="{{ route('finance.purchase-requests.download', $purchaseRequest) }}" class="btn btn-primary">
                    <i class="feather-download me-1"></i> Download PDF
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="row g-4 mt-1">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Summary</h6>

                        <table class="table table-sm mb-0">
                            <tr>
                                <th style="width: 200px;">Program</th>
                                <td>{{ $purchaseRequest->programFunding?->program?->name ?? $purchaseRequest->programFunding?->program_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Governance Node</th>
                                <td>{{ $purchaseRequest->governanceNode?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Sub-Activity</th>
                                <td>{{ $purchaseRequest->subActivity?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Start Year</th>
                                <td>{{ $purchaseRequest->start_year }}</td>
                            </tr>
                            <tr>
                                <th>Commitment Date</th>
                                <td>{{ $purchaseRequest->commitment_date?->format('F j, Y') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Delivery Date</th>
                                <td>{{ $purchaseRequest->delivery_date?->format('F j, Y') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Total Amount</th>
                                <td class="fw-bold">
                                    {{ $purchaseRequest->currency ?? $purchaseRequest->programFunding?->program?->currency ?? '' }}
                                    {{ number_format((float) $purchaseRequest->total_amount, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="badge {{ $purchaseRequest->status === 'approved' ? 'bg-success' : ($purchaseRequest->status === 'submitted' ? 'bg-warning text-dark' : ($purchaseRequest->status === 'cancelled' ? 'bg-danger' : 'bg-secondary')) }}">
                                        {{ ucfirst($purchaseRequest->status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Created</th>
                                <td>{{ $purchaseRequest->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Created By</th>
                                <td>{{ $purchaseRequest->creator?->name ?? '—' }}</td>
                            </tr>
                        </table>

                        @if (!empty($purchaseRequest->description))
                            <div class="mt-3">
                                <div class="fw-semibold mb-1">Description</div>
                                <div class="text-muted">{{ $purchaseRequest->description }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Requested Items</h6>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Category</th>
                                        <th>Resource Item</th>
                                        <th>Milestone / Description</th>
                                        <th>Milestone Date</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchaseRequest->items as $item)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $item->resourceCategory?->name ?? '—' }}</td>
                                            <td>{{ $item->resource?->name ?? '—' }}</td>
                                            <td>{{ $item->milestone ?? '—' }}</td>
                                            <td>{{ $item->milestone_date?->format('Y-m-d') ?? '—' }}</td>
                                            <td class="text-end fw-semibold">
                                                {{ $purchaseRequest->currency ?? $purchaseRequest->programFunding?->program?->currency ?? '' }}
                                                {{ number_format((float) $item->amount, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="5" class="text-end">Total</th>
                                        <th class="text-end">
                                            {{ $purchaseRequest->currency ?? $purchaseRequest->programFunding?->program?->currency ?? '' }}
                                            {{ number_format((float) $purchaseRequest->total_amount, 2) }}
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Year Contributions</h6>

                        @if ($yearSplits->isEmpty())
                            <div class="text-muted">No year split data found.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Year</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($yearSplits as $year => $amount)
                                            <tr>
                                                <td>{{ $year }}</td>
                                                <td class="text-end fw-semibold">
                                                    {{ $purchaseRequest->currency ?? $purchaseRequest->programFunding?->program?->currency ?? '' }}
                                                    {{ number_format((float) $amount, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                @can('finance.purchase_requests.send')
                    <div class="card shadow-sm mt-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Send Purchase Request</h6>

                            <form method="POST" action="{{ route('finance.purchase-requests.send', $purchaseRequest) }}">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Recipient Name</label>
                                    <input type="text"
                                        name="recipient_name"
                                        value="{{ old('recipient_name') }}"
                                        class="form-control @error('recipient_name') is-invalid @enderror"
                                        required>
                                    @error('recipient_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Recipient Email</label>
                                    <input type="email"
                                        name="recipient_email"
                                        value="{{ old('recipient_email') }}"
                                        class="form-control @error('recipient_email') is-invalid @enderror"
                                        required>
                                    @error('recipient_email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                @error('email')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="feather-send me-1"></i> Send Email with PDF
                                </button>
                            </form>
                        </div>
                    </div>
                @endcan
            </div>
        </div>

    </div>
@endsection
