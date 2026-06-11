@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ===================== PAGE HEADER ===================== --}}
        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">
                    Budget Commitment Details
                </h4>
                <p class="text-muted mb-0">
                    Commitment ID: #{{ $commitment->id }}
                </p>
            </div>

            <a href="{{ route('finance.commitments.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> Back to Commitments
            </a>
        </div>

        {{-- ===================== MAIN CARD ===================== --}}
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body">

                {{-- ===================== STATUS & SUMMARY ===================== --}}
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-5 g-3 mb-4">

                    <div class="col">
                        <div class="p-3 bg-light border rounded h-100">
                            <div class="text-muted small text-uppercase">Status</div>
                            <span
                                class="badge mt-1
                            {{ $commitment->status === 'approved'
                                ? 'bg-success'
                                : ($commitment->status === 'submitted'
                                    ? 'bg-warning text-dark'
                                    : ($commitment->status === 'cancelled'
                                        ? 'bg-danger'
                                        : 'bg-secondary')) }}">
                                {{ ucfirst($commitment->status) }}
                            </span>
                            @if($commitment->status === 'cancelled' && $commitment->rejection_reason)
                                <div class="text-muted small mt-2">
                                    Reason: {{ $commitment->rejection_reason }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col">
                        <div class="p-3 bg-light border rounded h-100">
                            <div class="text-muted small text-uppercase">Commitment Year</div>
                            <span class="badge bg-light text-dark mt-1">
                                {{ $commitment->commitment_year }}
                            </span>
                        </div>
                    </div>

                    <div class="col">
                        <div class="p-3 bg-light border rounded h-100">
                            <div class="text-muted small text-uppercase">Amount</div>
                            <div class="fw-bold text-primary fs-6 mt-1">
                                {{ $commitment->programFunding->program->currency ?? $commitment->programFunding->program_name ?? '' }}
                                {{ number_format($commitment->commitment_amount, 2) }}
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="p-3 bg-light border rounded h-100">
                            <div class="text-muted small text-uppercase">Created At</div>
                            <div class="fw-semibold mt-1">
                                {{ optional($commitment->created_at)->format('Y-m-d H:i') ?? '—' }}
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="p-3 bg-light border rounded h-100">
                            <div class="text-muted small text-uppercase">Allocation Level</div>
                            <span
                                class="badge mt-1
                            {{ $commitment->allocation_level === 'project'
                                ? 'bg-primary'
                                : ($commitment->allocation_level === 'activity'
                                    ? 'bg-warning text-dark'
                                    : 'bg-success') }}">
                                {{ ucfirst(str_replace('_', ' ', $commitment->allocation_level)) }}
                            </span>
                        </div>
                    </div>

                </div>

                {{-- ===================== PROGRAM & FUNDING ===================== --}}
                <h6 class="fw-bold text-primary mb-3">Program & Funding Context</h6>

                @php
                    $programName = $commitment->programFunding?->program?->name
                        ?? $commitment->programFunding?->program_name
                        ?? '—';
                    $projectName = $commitment->allocation_level === 'project'
                        ? \App\Models\Project::find($commitment->allocation_id)?->name
                        : ($commitment->allocation_level === 'activity'
                            ? \App\Models\Activity::find($commitment->allocation_id)?->project?->name
                            : \App\Models\SubActivity::find($commitment->allocation_id)?->activity?->project?->name);
                    $activityName = $commitment->allocation_level === 'activity'
                        ? \App\Models\Activity::find($commitment->allocation_id)?->name
                        : ($commitment->allocation_level === 'sub_activity'
                            ? \App\Models\SubActivity::find($commitment->allocation_id)?->activity?->name
                            : null);
                    $subActivityName = $commitment->allocation_level === 'sub_activity'
                        ? \App\Models\SubActivity::find($commitment->allocation_id)?->name
                        : null;
                @endphp

                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="p-3 border rounded bg-light h-100">
                            <h6 class="fw-bold text-primary mb-3">Program & Funding</h6>
                            <div class="mb-2">
                                <div class="text-muted small text-uppercase">Program</div>
                                <span class="fw-semibold">{{ $programName }}</span>
                            </div>
                            <div class="mb-2">
                                <div class="text-muted small text-uppercase">Program Funding</div>
                                <span class="fw-semibold">{{ $commitment->programFunding?->program_name ?? $programName }}</span>
                            </div>
                            <div>
                                <div class="text-muted small text-uppercase">Project</div>
                                <span class="fw-semibold">{{ $projectName ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="p-3 border rounded bg-light h-100">
                            <h6 class="fw-bold text-warning mb-3">Allocation</h6>
                            <div class="mb-2">
                                <div class="text-muted small text-uppercase">Project</div>
                                <span class="fw-semibold">{{ $projectName ?? '—' }}</span>
                            </div>
                            <div class="mb-2">
                                <div class="text-muted small text-uppercase">Activity</div>
                                <span class="fw-semibold">{{ $activityName ?? '—' }}</span>
                            </div>
                            <div>
                                <div class="text-muted small text-uppercase">Sub-Activity</div>
                                <span class="fw-semibold">{{ $subActivityName ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===================== RESOURCE / PURCHASE REQUEST DETAILS ===================== --}}
                @if ($commitment->purchaseRequest)
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
                                <h6 class="fw-bold text-success mb-0">Purchase Request</h6>
                                <div class="text-muted small">
                                    Delivery: {{ $commitment->purchaseRequest->delivery_date?->format('Y-m-d') ?? '—' }}
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="p-3 border rounded bg-light h-100">
                                        <div class="text-muted small text-uppercase">Reference</div>
                                        @can('finance.purchase_requests.view')
                                            <a href="{{ route('finance.purchase-requests.show', $commitment->purchaseRequest) }}" class="fw-semibold">
                                                {{ $commitment->purchaseRequest->reference_no }}
                                            </a>
                                        @else
                                            <span class="fw-semibold">{{ $commitment->purchaseRequest->reference_no }}</span>
                                        @endcan
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 border rounded bg-light h-100">
                                        <div class="text-muted small text-uppercase">Start Year</div>
                                        <span class="fw-semibold">{{ $commitment->purchaseRequest->start_year }}</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 border rounded bg-light h-100">
                                        <div class="text-muted small text-uppercase">Total Amount</div>
                                        <span class="fw-bold text-primary">
                                            {{ $commitment->purchaseRequest->currency ?? $commitment->programFunding->program->currency ?? '' }}
                                            {{ number_format((float) $commitment->purchaseRequest->total_amount, 2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            @if (!empty($commitment->description))
                                <div class="alert alert-info mb-3">
                                    <strong>Description:</strong> {{ $commitment->description }}
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">#</th>
                                            <th>Category</th>
                                            <th>Resource Item</th>
                                            <th>Milestone / Description</th>
                                            <th>Milestone Date</th>
                                            <th class="text-end" style="width: 160px;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($commitment->purchaseRequest->items as $item)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $item->resourceCategory->name ?? '—' }}</td>
                                                <td>{{ $item->resource->name ?? '—' }}</td>
                                                <td>{{ $item->milestone ?? '—' }}</td>
                                                <td>{{ $item->milestone_date?->format('Y-m-d') ?? '—' }}</td>
                                                <td class="text-end fw-semibold">
                                                    {{ $commitment->purchaseRequest->currency ?? $commitment->programFunding->program->currency ?? '' }}
                                                    {{ number_format((float) $item->amount, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold text-success mb-3">Resource Commitment</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 border rounded bg-light h-100">
                                        <div class="text-muted small text-uppercase">Resource Category</div>
                                        <span class="fw-semibold">{{ $commitment->resourceCategory->name ?? '—' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded bg-light h-100">
                                        <div class="text-muted small text-uppercase">Resource Item</div>
                                        <span class="fw-semibold">{{ $commitment->resource->name ?? '—' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <hr>

                {{-- ===================== AUDIT INFORMATION ===================== --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold text-secondary mb-3">Audit Information</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="text-muted small text-uppercase">Created By</div>
                                <div class="fw-semibold">{{ $commitment->creator->name ?? '—' }}</div>
                                @if (!empty($commitment->creator->email))
                                    <div class="text-muted small">{{ $commitment->creator->email }}</div>
                                @endif
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small text-uppercase">Created At</div>
                                <div>{{ $commitment->created_at ?? '—' }}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small text-uppercase">Approved By</div>
                                <div class="fw-semibold">{{ $commitment->approver->name ?? '—' }}</div>
                                @if (!empty($commitment->approver?->email))
                                    <div class="text-muted small">{{ $commitment->approver->email }}</div>
                                @endif
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small text-uppercase">Approved At</div>
                                <div>{{ $commitment->approved_at ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===================== ACTIONS ===================== --}}
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 align-items-center">

                    @can('finance.commitments.edit')
                        @if ($commitment->status === 'draft')
                            <a href="{{ route('finance.commitments.edit', $commitment) }}"
                                class="btn btn-outline-secondary">
                                <i class="feather-edit-2 me-1"></i>
                                Edit Commitment
                            </a>
                        @endif
                    @endcan

                    @can('finance.commitments.delete')
                        @if ($commitment->status === 'draft')
                            <button type="button"
                                class="btn btn-outline-danger js-delete-commitment"
                                data-info-url="{{ route('finance.commitments.destroy-info', $commitment) }}"
                                data-delete-url="{{ route('finance.commitments.destroy', $commitment) }}">
                                <i class="feather-trash-2 me-1"></i>
                                Delete
                            </button>
                        @endif
                    @endcan

                    @if ($commitment->status === 'draft')
                        @canany(['finance.commitments.submit', 'finance.commitments.edit'])
                            <form method="POST" action="{{ route('finance.commitments.submit', $commitment) }}">
                                @csrf
                                <button class="btn btn-warning">
                                    <i class="feather-send me-1"></i>
                                    Submit for Approval
                                </button>
                            </form>
                        @endcanany
                    @endif

                    @canany(['finance.commitments.approve', 'finance.commitments.edit'])
                        @if (in_array($commitment->status, ['submitted', 'draft']))
                            <form method="POST" action="{{ route('finance.commitments.approve', $commitment) }}">
                                @csrf
                                <button class="btn btn-success">
                                    <i class="feather-check-circle me-1"></i>
                                    Approve Commitment
                                </button>
                            </form>
                        @endif
                    @endcanany

                    @canany(['finance.commitments.cancel', 'finance.commitments.edit'])
                        @if (in_array($commitment->status, ['draft', 'submitted']))
                            <form method="POST" action="{{ route('finance.commitments.cancel', $commitment) }}" class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                @csrf
                                <input type="text" name="reason" class="form-control form-control-sm" style="min-width: 220px;" placeholder="Reason for rejection/cancel" required>
                                <button class="btn btn-danger">
                                    <i class="feather-x-circle me-1"></i>
                                    Cancel / Reject
                                </button>
                            </form>
                        @endif
                    @endcanany
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

@can('finance.commitments.delete')
@if ($commitment->status === 'draft')
    {{-- Cascade-Delete Modal --}}
    <div class="modal fade" id="deleteCommitmentModal" tabindex="-1" aria-labelledby="deleteCommitmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="deleteCommitmentModalLabel">
                        <i class="feather-alert-triangle text-danger me-2"></i>Delete Budget Commitment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="deleteCommitmentModalBody">
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                        <div class="small text-muted mt-2">Loading impact details…</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0" id="deleteCommitmentModalFooter">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <form id="deleteCommitmentForm" method="POST"
          action="{{ route('finance.commitments.destroy', $commitment) }}"
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
        function buildModalBody(data) {
            const s = data.summary;
            let html = `<p class="mb-3 text-muted" style="font-size:.9rem">
                Commitment <strong>${s.currency} ${s.amount}</strong> &mdash; Year ${s.year} &mdash; ${statusBadge(s.status)}
            </p>`;
            if (!data.can_delete) {
                return html + `<div class="alert alert-danger mb-0">
                    <i class="feather-x-circle me-2"></i><strong>Cannot delete:</strong> ${data.block_reason}
                </div>`;
            }
            if (data.chain.length === 0) {
                return html + `<div class="alert alert-warning mb-3">
                    This commitment will be permanently deleted. This action cannot be undone.
                </div>`;
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
                        <div class="text-muted small mt-1">${item.currency} ${item.total_amount} &mdash; ${item.item_count} item${item.item_count !== 1 ? 's' : ''}</div>
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

        document.querySelector('.js-delete-commitment')?.addEventListener('click', function () {
            const infoUrl = this.dataset.infoUrl;
            const body    = document.getElementById('deleteCommitmentModalBody');
            const footer  = document.getElementById('deleteCommitmentModalFooter');
            body.innerHTML = `<div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                <div class="small text-muted mt-2">Loading impact details…</div>
            </div>`;
            footer.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteCommitmentModal')).show();

            fetch(infoUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    body.innerHTML = buildModalBody(data);
                    if (data.can_delete) {
                        footer.innerHTML = `
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                                <i class="feather-trash-2 me-1"></i> Delete All
                            </button>`;
                        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
                            this.disabled = true;
                            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting…';
                            document.getElementById('deleteCommitmentForm').submit();
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
@endcan
@endsection
