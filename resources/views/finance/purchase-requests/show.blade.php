@extends('layouts.app')

@section('content')
    @php
        $statusClasses = [
            'approved' => 'bg-success',
            'submitted' => 'bg-warning text-dark',
            'draft' => 'bg-secondary',
            'rejected' => 'bg-danger',
            'cancelled' => 'bg-danger',
        ];
        $decidableStatuses = ['draft', 'submitted'];
        $purchaseRequestStatus = $purchaseRequest->status ?? 'draft';
        $canDecidePurchaseRequest = $canApprovePurchaseRequests && in_array($purchaseRequestStatus, $decidableStatuses, true);
        $commitmentStatuses = $purchaseRequest->commitments->pluck('status');
        $canEditThisPurchaseRequest = $canEditPurchaseRequests
            && $purchaseRequestStatus === 'draft'
            && $commitmentStatuses->isNotEmpty()
            && $commitmentStatuses->every(fn ($status) => $status === 'draft');
        $canDeleteThisPurchaseRequest = $canDeletePurchaseRequests
            && $purchaseRequestStatus !== 'approved'
            && ! $commitmentStatuses->contains('approved');
    @endphp

    <div class="nxl-container">

        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Purchase Request: {{ $purchaseRequest->reference_no }}</h4>
                <p class="text-muted mb-0">
                    Generated from a budget commitment (multi-year supported)
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('finance.purchase-requests.index') }}" class="btn btn-outline-secondary">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
                @if ($canEditThisPurchaseRequest)
                    <a href="{{ route('finance.purchase-requests.edit', $purchaseRequest) }}" class="btn btn-outline-warning">
                        <i class="feather-edit-2 me-1"></i> Edit Draft
                    </a>
                @endif
                @if ($canDeletePurchaseRequests)
                    <button type="button"
                        class="btn btn-outline-danger js-delete-pr"
                        data-info-url="{{ route('finance.purchase-requests.destroy-info', $purchaseRequest) }}"
                        data-delete-url="{{ route('finance.purchase-requests.destroy', $purchaseRequest) }}">
                        <i class="feather-trash-2 me-1"></i> Delete
                    </button>
                @endif
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
                                    <span class="badge {{ $statusClasses[$purchaseRequestStatus] ?? 'bg-secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $purchaseRequestStatus)) }}
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

                        @if ($purchaseRequestStatus === 'rejected' && !empty($purchaseRequest->rejection_reason))
                            <div class="alert alert-danger mt-3 mb-0">
                                <div class="fw-semibold mb-1">Rejection reason</div>
                                <div>{{ $purchaseRequest->rejection_reason }}</div>
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

                @if ($canApprovePurchaseRequests || in_array($purchaseRequestStatus, ['approved', 'rejected'], true))
                    <div class="card shadow-sm mt-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Approval Decision</h6>

                            @if ($purchaseRequestStatus === 'approved')
                                <div class="alert alert-success mb-0">
                                    Approved by {{ $purchaseRequest->approver?->name ?? 'System' }}
                                    @if ($purchaseRequest->approved_at)
                                        on {{ $purchaseRequest->approved_at->format('Y-m-d H:i') }}
                                    @endif
                                </div>
                            @elseif ($purchaseRequestStatus === 'rejected')
                                <div class="alert alert-danger mb-0">
                                    <div class="fw-semibold">Rejected by {{ $purchaseRequest->rejector?->name ?? 'System' }}</div>
                                    @if ($purchaseRequest->rejected_at)
                                        <div class="small mb-2">{{ $purchaseRequest->rejected_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                    <div>{{ $purchaseRequest->rejection_reason ?? 'No reason recorded.' }}</div>
                                </div>
                            @elseif ($canDecidePurchaseRequest)
                                <form method="POST" action="{{ route('finance.purchase-requests.approve', $purchaseRequest) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="feather-check me-1"></i> Approve Purchase Request
                                    </button>
                                </form>

                                <button type="button"
                                    class="btn btn-outline-danger w-100 mt-2"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#rejectPurchaseRequestForm"
                                    aria-expanded="false"
                                    aria-controls="rejectPurchaseRequestForm">
                                    <i class="feather-x me-1"></i> Reject Purchase Request
                                </button>

                                <div class="collapse mt-3" id="rejectPurchaseRequestForm">
                                    <form method="POST" action="{{ route('finance.purchase-requests.reject', $purchaseRequest) }}">
                                        @csrf
                                        <div class="mb-3">
                                            <label class="form-label">Reason for rejection</label>
                                            <textarea name="rejection_reason"
                                                class="form-control @error('rejection_reason') is-invalid @enderror"
                                                rows="4"
                                                minlength="5"
                                                maxlength="1000"
                                                required>{{ old('rejection_reason') }}</textarea>
                                            @error('rejection_reason')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <button type="submit" class="btn btn-danger w-100">
                                            <i class="feather-x me-1"></i> Confirm Rejection
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="text-muted">No approval action is available for the current status.</div>
                            @endif
                        </div>
                    </div>
                @endif

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

@if ($canDeletePurchaseRequests)
    {{-- Cascade-Delete PR Modal --}}
    <div class="modal fade" id="deletePrModal" tabindex="-1" aria-labelledby="deletePrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="deletePrModalLabel">
                        <i class="feather-alert-triangle text-danger me-2"></i>Delete Purchase Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="deletePrModalBody">
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                        <div class="small text-muted mt-2">Loading impact details…</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0" id="deletePrModalFooter">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <form id="deletePrForm" method="POST"
          action="{{ route('finance.purchase-requests.destroy', $purchaseRequest) }}"
          style="display:none">
        @csrf
        @method('DELETE')
    </form>

    @push('scripts')
    <script>
    (function () {
        const STATUS_COLORS = {
            draft: 'secondary', submitted: 'warning', approved: 'success',
            cancelled: 'danger', completed: 'success',
        };
        function statusBadge(status) {
            const col = STATUS_COLORS[status] || 'secondary';
            return `<span class="badge bg-${col} text-${col === 'warning' ? 'dark' : 'white'}">${status}</span>`;
        }
        function buildBody(data) {
            const s = data.summary;
            let html = `<p class="mb-3 text-muted" style="font-size:.9rem">
                <strong>${s.reference_no}</strong> &mdash; ${s.currency} ${s.total_amount} &mdash; ${statusBadge(s.status)}
            </p>`;
            if (!data.can_delete) {
                return html + `<div class="alert alert-danger mb-0">
                    <i class="feather-x-circle me-2"></i><strong>Cannot delete:</strong> ${data.block_reason}
                </div>`;
            }
            if (data.chain.length === 0) {
                return html + `<div class="alert alert-warning mb-0">This purchase request will be permanently deleted.</div>`;
            }
            html += `<p class="fw-semibold mb-2" style="font-size:.85rem">The following records will also be permanently deleted:</p>
                     <div class="list-group list-group-flush mb-3">`;
            data.chain.forEach(item => {
                if (item.type === 'purchase_request') {
                    html += `<div class="list-group-item px-0 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="badge bg-primary me-1" style="font-size:.68rem">Purchase Request</span><strong>${item.reference_no}</strong></div>
                            ${statusBadge(item.status)}
                        </div>
                        <div class="text-muted small mt-1">${item.currency} ${item.total_amount} &mdash; ${item.commitment_count} commitment${item.commitment_count !== 1 ? 's' : ''}, ${item.item_count} item${item.item_count !== 1 ? 's' : ''}</div>
                    </div>`;
                } else if (item.type === 'purchase_order') {
                    const extras = [];
                    if (item.disbursement_count > 0) extras.push(`${item.disbursement_count} disbursement${item.disbursement_count !== 1 ? 's' : ''}`);
                    if (item.has_invoice) extras.push('invoice');
                    if (item.has_negotiation) extras.push('negotiation');
                    html += `<div class="list-group-item px-0 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="badge bg-warning text-dark me-1" style="font-size:.68rem">Purchase Order</span><strong>${item.reference_no}</strong></div>
                            ${statusBadge(item.status)}
                        </div>
                        <div class="text-muted small mt-1">${item.currency} ${item.amount} &mdash; ${item.vendor}${extras.length ? ' &mdash; includes: ' + extras.join(', ') : ''}</div>
                    </div>`;
                }
            });
            html += `</div><div class="alert alert-danger mb-0" style="font-size:.85rem">
                <i class="feather-alert-triangle me-1"></i><strong>This action cannot be undone.</strong>
            </div>`;
            return html;
        }

        document.querySelector('.js-delete-pr')?.addEventListener('click', function () {
            const infoUrl = this.dataset.infoUrl;
            const body    = document.getElementById('deletePrModalBody');
            const footer  = document.getElementById('deletePrModalFooter');
            body.innerHTML = `<div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                <div class="small text-muted mt-2">Loading impact details…</div>
            </div>`;
            footer.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deletePrModal')).show();

            fetch(infoUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    body.innerHTML = buildBody(data);
                    if (data.can_delete) {
                        footer.innerHTML = `
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmPrDeleteBtn">
                                <i class="feather-trash-2 me-1"></i> Delete All
                            </button>`;
                        document.getElementById('confirmPrDeleteBtn').addEventListener('click', function () {
                            this.disabled = true;
                            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting…';
                            document.getElementById('deletePrForm').submit();
                        });
                    } else {
                        footer.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>`;
                    }
                })
                .catch(() => {
                    body.innerHTML = `<div class="alert alert-danger">Failed to load details. Please try again.</div>`;
                });
        });
    })();
    </script>
    @endpush
@endif
@endsection
