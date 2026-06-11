@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ===================== PAGE HEADER ===================== --}}
        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Planned Commitments</h4>
                <p class="text-muted mb-0">
                    Planned committed resources across projects, activities, and sub-activities
                </p>
            </div>

            @can('finance.commitments.create')
                <a href="{{ route('finance.commitments.create') }}" class="btn btn-primary">
                    <i class="feather-plus-circle me-1"></i>
                    New Planned Commitment
                </a>
            @endcan
        </div>

        {{-- ===================== FLASH MESSAGES ===================== --}}
        @if (session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- ===================== COMMITMENTS TABLE ===================== --}}
        <div class="card shadow-sm mt-4">
            <div class="card-body">

                <x-data-table
                    id="commitmentsTable"
                >
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Program</th>
                            <th>Allocation</th>
                            <th>Resource</th>
                            <th>Milestone Date</th>
                            <th class="text-end">Amount</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($commitments as $c)
                            @php
                                $label = null;
                                if ($c->allocation_level === 'project') {
                                    $label = \App\Models\Project::find($c->allocation_id)?->name;
                                }
                                if ($c->allocation_level === 'activity') {
                                    $label = \App\Models\Activity::find($c->allocation_id)?->name;
                                }
                                if ($c->allocation_level === 'sub_activity') {
                                    $label = \App\Models\SubActivity::find($c->allocation_id)?->name;
                                }
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>

                                {{-- Program --}}
                                <td>
                                    {{ $c->programFunding->program->name ?? $c->programFunding->program_name ?? '—' }}
                                </td>

                                {{-- Allocation --}}
                                <td>
                                    <span class="badge mb-1 {{ $c->allocation_level === 'project' ? 'bg-primary' : ($c->allocation_level === 'activity' ? 'bg-warning text-dark' : 'bg-success') }}">
                                        {{ ucfirst(str_replace('_', ' ', $c->allocation_level)) }}
                                    </span>
                                    <div class="small text-muted mt-1">
                                        {{ $label ?? 'Allocation not found' }}
                                    </div>
                                </td>

                                {{-- Resource --}}
                                <td>
                                    @if ($c->purchaseRequest)
                                        <div class="fw-semibold">
                                            @can('finance.purchase_requests.view')
                                                <a href="{{ route('finance.purchase-requests.show', $c->purchaseRequest) }}">
                                                    {{ $c->purchaseRequest->reference_no }}
                                                </a>
                                            @else
                                                {{ $c->purchaseRequest->reference_no }}
                                            @endcan
                                        </div>
                                        <small class="text-muted">Purchase Request</small>
                                    @else
                                        <div class="fw-semibold">{{ $c->resource->name ?? '—' }}</div>
                                        <small class="text-muted">
                                            <span class="badge bg-info-subtle text-info">
                                                {{ $c->resourceCategory->name ?? '—' }}
                                            </span>
                                        </small>
                                    @endif
                                </td>

                                {{-- Milestone Date (earliest) --}}
                                @php
                                    $milestoneDate = $c->purchaseRequest?->items
                                        ? $c->purchaseRequest->items
                                            ->filter(fn($i) => !empty($i->milestone_date))
                                            ->sortBy('milestone_date')
                                            ->first()?->milestone_date
                                        : null;
                                @endphp
                                <td>
                                    {{ $milestoneDate?->format('Y-m-d') ?? '—' }}
                                </td>

                                {{-- Amount --}}
                                <td class="text-end fw-bold">
                                    <span class="text-muted me-1">
                                        {{ $c->programFunding->program->currency ?? '' }}
                                    </span>
                                    {{ number_format($c->commitment_amount, 2) }}
                                </td>


                                {{-- Year --}}
                                <td>
                                    <span class="badge bg-light text-dark">
                                        {{ $c->commitment_year }}
                                    </span>
                                </td>

                                {{-- Status --}}
                                <td>
                                    <span class="badge {{ $c->status === 'approved' ? 'bg-success' : ($c->status === 'submitted' ? 'bg-warning text-dark' : ($c->status === 'cancelled' ? 'bg-danger' : 'bg-secondary')) }}">
                                        {{ ucfirst($c->status) }}
                                    </span>
                                </td>

                                {{-- Action --}}
                                <td class="text-end">
                                    <a href="{{ route('finance.commitments.show', $c->id) }}"
                                        class="btn btn-sm btn-outline-primary"
                                        title="View Commitment">
                                        <i class="feather-eye"></i>
                                    </a>
                                    @can('finance.commitments.edit')
                                        @if ($c->status === 'draft')
                                            <a href="{{ route('finance.commitments.edit', $c->id) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Edit Commitment">
                                                <i class="feather-edit-2"></i>
                                            </a>
                                        @endif
                                    @endcan
                                    @can('finance.commitments.delete')
                                        <button type="button"
                                            class="btn btn-sm btn-outline-danger js-delete-commitment"
                                            title="Delete Commitment"
                                            data-info-url="{{ route('finance.commitments.destroy-info', $c->id) }}"
                                            data-delete-url="{{ route('finance.commitments.destroy', $c->id) }}"
                                            data-force-delete-url="{{ route('finance.commitments.force-destroy', $c->id) }}">
                                            <i class="feather-trash-2"></i>
                                        </button>
                                    @endcan
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>

            </div>
        </div>

    </div>

    {{-- ===== Cascade-Delete Confirmation Modal ===== --}}
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

    {{-- Hidden form that gets its action set dynamically --}}
    <form id="deleteCommitmentForm" method="POST" style="display:none">
        @csrf
        @method('DELETE')
    </form>

    <form id="forceDeleteCommitmentForm" method="POST" style="display:none">
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
            html += `<div class="alert alert-danger mb-0">
                <i class="feather-x-circle me-2"></i><strong>Cannot delete:</strong> ${data.block_reason}
            </div>`;
            return html;
        }

        if (data.chain.length === 0) {
            html += `<div class="alert alert-warning mb-3">
                This commitment will be permanently deleted. This action cannot be undone.
            </div>`;
            return html;
        }

        html += `<p class="fw-semibold mb-2" style="font-size:.85rem">The following records will also be permanently deleted:</p>
                 <div class="list-group list-group-flush mb-3">`;

        data.chain.forEach(item => {
            if (item.type === 'purchase_request') {
                html += `<div class="list-group-item px-0 py-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="badge bg-primary me-1" style="font-size:.68rem">Purchase Request</span>
                            <strong>${item.reference_no}</strong>
                        </div>
                        ${statusBadge(item.status)}
                    </div>
                    <div class="text-muted small mt-1">
                        ${item.currency} ${item.total_amount} &mdash; ${item.item_count} item${item.item_count !== 1 ? 's' : ''}
                    </div>
                </div>`;
            } else if (item.type === 'purchase_order') {
                const extras = [];
                if (item.disbursement_count > 0) extras.push(`${item.disbursement_count} disbursement${item.disbursement_count !== 1 ? 's' : ''}`);
                if (item.has_invoice) extras.push('invoice');
                if (item.has_negotiation) extras.push('negotiation');
                html += `<div class="list-group-item px-0 py-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="badge bg-warning text-dark me-1" style="font-size:.68rem">Purchase Order</span>
                            <strong>${item.reference_no}</strong>
                        </div>
                        ${statusBadge(item.status)}
                    </div>
                    <div class="text-muted small mt-1">
                        ${item.currency} ${item.amount} &mdash; ${item.vendor}
                        ${extras.length ? ' &mdash; includes: ' + extras.join(', ') : ''}
                    </div>
                </div>`;
            }
        });

        html += `</div>
        <div class="alert alert-danger mb-0" style="font-size:.85rem">
            <i class="feather-alert-triangle me-1"></i>
            <strong>This action cannot be undone.</strong> All listed records will be permanently deleted.
        </div>`;
        return html;
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-delete-commitment');
        if (!btn) return;

        const infoUrl        = btn.dataset.infoUrl;
        const deleteUrl      = btn.dataset.deleteUrl;
        const forceDeleteUrl = btn.dataset.forceDeleteUrl;

        const modal           = document.getElementById('deleteCommitmentModal');
        const body            = document.getElementById('deleteCommitmentModalBody');
        const footer          = document.getElementById('deleteCommitmentModalFooter');
        const deleteForm      = document.getElementById('deleteCommitmentForm');
        const forceDeleteForm = document.getElementById('forceDeleteCommitmentForm');

        // Reset to loading state
        body.innerHTML = `<div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
            <div class="small text-muted mt-2">Loading impact details…</div>
        </div>`;
        footer.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>`;

        const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
        bsModal.show();

        fetch(infoUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                body.innerHTML = buildModalBody(data);

                if (data.can_delete) {
                    deleteForm.action = deleteUrl;
                    footer.innerHTML = `
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                            <i class="feather-trash-2 me-1"></i> Delete All
                        </button>`;
                    document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
                        this.disabled = true;
                        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting…';
                        deleteForm.submit();
                    });
                } else if (data.is_admin && forceDeleteUrl) {
                    forceDeleteForm.action = forceDeleteUrl;
                    footer.innerHTML = `
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="forceDeleteBtn">
                            <i class="feather-zap me-1"></i> Force Delete (Admin Only)
                        </button>`;
                    document.getElementById('forceDeleteBtn').addEventListener('click', function () {
                        this.disabled = true;
                        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Force Deleting…';
                        forceDeleteForm.submit();
                    });
                } else {
                    footer.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>`;
                }
            })
            .catch(() => {
                body.innerHTML = `<div class="alert alert-danger">Failed to load deletion details. Please try again.</div>`;
            });
    });
})();
</script>
@endpush
@endsection
