@extends('layouts.app')

@section('content')
    @php
        $statusClasses = [
            'approved' => 'bg-success',
            'completed' => 'bg-success',
            'converted' => 'bg-info text-dark',
            'in_review' => 'bg-primary',
            'revision_requested' => 'bg-warning text-dark',
            'submitted' => 'bg-warning text-dark',
            'draft' => 'bg-secondary',
            'rejected' => 'bg-danger',
            'cancelled' => 'bg-danger',
            'canceled' => 'bg-danger',
        ];
        $statusIcons = [
            'submitted' => 'feather-inbox',
            'in_review' => 'feather-search',
            'revision_requested' => 'feather-rotate-ccw',
            'approved' => 'feather-check-circle',
            'rejected' => 'feather-x-circle',
            'converted' => 'feather-repeat',
            'completed' => 'feather-flag',
        ];
        $decidableStatuses = ['draft', 'submitted'];
    @endphp

    <div class="nxl-container">
        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Purchase Requests</h4>
                <p class="text-muted mb-0">
                    Finance purchase requests and vendor-submitted purchase requests grouped by workflow status.
                </p>
            </div>
            @can('finance.commitments.create')
                <a href="{{ route('finance.purchase-requests.create') }}" class="btn btn-primary">
                    <i class="feather-plus me-1"></i> Create Purchase Request
                </a>
            @endcan
        </div>

        @if (session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <ul class="nav nav-pills gap-2 flex-nowrap overflow-auto pb-2" id="purchaseRequestStatusTabs" role="tablist">
                    @foreach ($statusTabs as $status => $label)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center gap-2 {{ $loop->first ? 'active' : '' }}"
                                id="purchase-request-tab-{{ $status }}"
                                data-bs-toggle="tab"
                                data-bs-target="#purchase-request-pane-{{ $status }}"
                                type="button"
                                role="tab"
                                aria-controls="purchase-request-pane-{{ $status }}"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                <i class="{{ $statusIcons[$status] ?? 'feather-circle' }}"></i>
                                <span>{{ $label }}</span>
                                <span class="badge bg-light text-dark">{{ number_format($purchaseRequestStatusCounts[$status] ?? 0) }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content pt-3" id="purchaseRequestStatusTabsContent">
                    @foreach ($statusTabs as $status => $label)
                        @php
                            $rows = $purchaseRequestRowsByStatus[$status] ?? collect();
                        @endphp
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
                            id="purchase-request-pane-{{ $status }}"
                            role="tabpanel"
                            aria-labelledby="purchase-request-tab-{{ $status }}"
                            tabindex="0">
                            @if ($rows->isEmpty())
                                <div class="border rounded bg-light p-4 text-center text-muted">
                                    <i class="{{ $statusIcons[$status] ?? 'feather-inbox' }} d-block fs-4 mb-2"></i>
                                    No purchase requests in {{ strtolower($label) }} status.
                                </div>
                            @else
                                <x-data-table id="purchaseRequestsTable_{{ $status }}">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Source</th>
                                            <th>Reference</th>
                                            <th>Requestor</th>
                                            @if ($canViewAll)
                                                <th>Governance Node</th>
                                            @endif
                                            <th>Program</th>
                                            <th>Procurement / Sub-Activity</th>
                                            <th>Start Year</th>
                                            <th>Commitment Date</th>
                                            <th>Delivery Date</th>
                                            <th class="text-end">Total</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($rows as $row)
                                            @php
                                                $pr = $row->record;
                                                $commitmentStatuses = $row->commitment_statuses;
                                                $isFinanceRow = $row->source === 'finance';
                                                $canEditLockedPurchaseRequest = auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin();
                                                $canEditThisPurchaseRequest = $isFinanceRow
                                                    && $canEditPurchaseRequests
                                                    && (
                                                        $canEditLockedPurchaseRequest
                                                        || (
                                                            ($pr->status ?? 'draft') === 'draft'
                                                            && $commitmentStatuses->isNotEmpty()
                                                            && $commitmentStatuses->every(fn ($status) => $status === 'draft')
                                                        )
                                                    );
                                                $canDeleteThisPurchaseRequest = $isFinanceRow
                                                    && $canDeletePurchaseRequests
                                                    && ($pr->status ?? 'draft') !== 'approved'
                                                    && ! $commitmentStatuses->contains('approved');
                                            @endphp
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>
                                                    <span class="badge {{ $isFinanceRow ? 'bg-primary' : 'bg-info text-dark' }}">
                                                        {{ $row->source_label }}
                                                    </span>
                                                </td>
                                                <td class="fw-semibold">{{ $row->reference_no }}</td>
                                                <td>
                                                    <div class="fw-semibold">{{ $row->requestor_name }}</div>
                                                    @if ($row->requestor_email)
                                                        <div class="text-muted small">{{ $row->requestor_email }}</div>
                                                    @endif
                                                    @if ($row->priority)
                                                        <span class="badge bg-light text-dark mt-1">{{ ucfirst($row->priority) }} priority</span>
                                                    @endif
                                                </td>
                                                @if ($canViewAll)
                                                    <td>{{ $row->governance_node ?: 'N/A' }}</td>
                                                @endif
                                                <td>{{ $row->program }}</td>
                                                <td>
                                                    <div class="fw-semibold">{{ $row->sub_activity }}</div>
                                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($row->title, 70) }}</div>
                                                </td>
                                                <td><span class="badge bg-light text-dark">{{ $row->start_year ?: 'N/A' }}</span></td>
                                                <td>{{ $row->commitment_date?->format('F j, Y') ?? 'N/A' }}</td>
                                                <td>{{ $row->delivery_date?->format('F j, Y') ?? 'N/A' }}</td>
                                                <td class="text-end fw-bold">
                                                    <span class="text-muted me-1">{{ $row->currency }}</span>
                                                    {{ number_format((float) $row->total_amount, 2) }}
                                                </td>
                                                <td>
                                                    <span class="badge {{ $statusClasses[$row->status] ?? 'bg-secondary' }}">
                                                        {{ \Illuminate\Support\Str::headline($row->status ?? 'submitted') }}
                                                    </span>
                                                </td>
                                                <td>{{ $row->created_at?->format('Y-m-d') ?? 'N/A' }}</td>
                                                <td class="text-end">
                                                    @if ($isFinanceRow)
                                                        <a href="{{ route('finance.purchase-requests.show', $pr) }}"
                                                            class="btn btn-sm btn-outline-primary"
                                                            title="View Purchase Request">
                                                            <i class="feather-eye"></i>
                                                        </a>
                                                        <a href="{{ route('finance.purchase-requests.download', $pr) }}"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            title="Download PDF">
                                                            <i class="feather-download"></i>
                                                        </a>

                                                        @if ($canEditThisPurchaseRequest)
                                                            <a href="{{ route('finance.purchase-requests.edit', $pr) }}"
                                                                class="btn btn-sm btn-outline-warning"
                                                                title="Edit Purchase Request">
                                                                <i class="feather-edit-2"></i>
                                                            </a>
                                                        @endif

                                                        @if ($canDeleteThisPurchaseRequest)
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-danger js-delete-pr"
                                                                title="Delete Purchase Request"
                                                                data-info-url="{{ route('finance.purchase-requests.destroy-info', $pr) }}"
                                                                data-delete-url="{{ route('finance.purchase-requests.destroy', $pr) }}"
                                                                data-force-delete-url="{{ route('finance.purchase-requests.force-destroy', $pr) }}">
                                                                <i class="feather-trash-2"></i>
                                                            </button>
                                                        @endif

                                                        @if ($canApprovePurchaseRequests && in_array($pr->status, $decidableStatuses, true))
                                                            <form method="POST" action="{{ route('finance.purchase-requests.approve', $pr) }}" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Approve Purchase Request">
                                                                    <i class="feather-check"></i>
                                                                </button>
                                                            </form>

                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-danger"
                                                                title="Reject Purchase Request"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#rejectPurchaseRequestModal{{ $row->key }}">
                                                                <i class="feather-x"></i>
                                                            </button>

                                                            <div class="modal fade" id="rejectPurchaseRequestModal{{ $row->key }}" tabindex="-1" aria-hidden="true">
                                                                <div class="modal-dialog modal-dialog-centered">
                                                                    <div class="modal-content text-start">
                                                                        <form method="POST" action="{{ route('finance.purchase-requests.reject', $pr) }}">
                                                                            @csrf
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title">Reject Purchase Request</h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <div class="mb-0">
                                                                                    <label class="form-label">Reason for rejection</label>
                                                                                    <textarea name="rejection_reason"
                                                                                        class="form-control"
                                                                                        rows="4"
                                                                                        minlength="5"
                                                                                        maxlength="1000"
                                                                                        required>{{ old('rejection_reason') }}</textarea>
                                                                                </div>
                                                                            </div>
                                                                            <div class="modal-footer">
                                                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                                <button type="submit" class="btn btn-danger">
                                                                                    <i class="feather-x me-1"></i> Reject
                                                                                </button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @else
                                                        @if ($canManageVendorRequests)
                                                            <a href="{{ route('vendors.requests.purchase-requests.show', $pr) }}"
                                                                class="btn btn-sm btn-outline-primary"
                                                                title="Review Vendor Purchase Request">
                                                                <i class="feather-eye me-1"></i> Review
                                                            </a>
                                                        @else
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                                Review
                                                            </button>
                                                        @endif
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </x-data-table>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

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
                        <div class="small text-muted mt-2">Loading impact details...</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0" id="deletePrModalFooter">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <form id="deletePrForm" method="POST" style="display:none">
        @csrf
        @method('DELETE')
    </form>

    <form id="forceDeletePrForm" method="POST" style="display:none">
        @csrf
        @method('DELETE')
    </form>

@push('scripts')
<script>
(function () {
    const STATUS_COLORS = {
        draft: 'secondary',
        submitted: 'warning',
        approved: 'success',
        rejected: 'danger',
        cancelled: 'danger',
        completed: 'success',
    };

    function statusBadge(status) {
        const col = STATUS_COLORS[status] || 'secondary';
        const textClass = col === 'warning' ? 'dark' : 'white';
        return `<span class="badge bg-${col} text-${textClass}">${status}</span>`;
    }

    function buildPrModalBody(data) {
        const s = data.summary;
        let html = `<p class="mb-3 text-muted" style="font-size:.9rem">
            <strong>${s.reference_no}</strong> - ${s.currency} ${s.total_amount} - ${statusBadge(s.status)}
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
                    <div class="text-muted small mt-1">${item.currency} ${item.total_amount} - ${item.commitment_count} commitment${item.commitment_count !== 1 ? 's' : ''}, ${item.item_count} item${item.item_count !== 1 ? 's' : ''}</div>
                </div>`;
                return;
            }

            if (item.type === 'purchase_order') {
                const extras = [];
                if (item.disbursement_count > 0) {
                    extras.push(`${item.disbursement_count} disbursement${item.disbursement_count !== 1 ? 's' : ''}`);
                }
                if (item.has_invoice) {
                    extras.push('invoice');
                }
                if (item.has_negotiation) {
                    extras.push('negotiation');
                }

                html += `<div class="list-group-item px-0 py-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="badge bg-warning text-dark me-1" style="font-size:.68rem">Purchase Order</span><strong>${item.reference_no}</strong></div>
                        ${statusBadge(item.status)}
                    </div>
                    <div class="text-muted small mt-1">${item.currency} ${item.amount} - ${item.vendor}${extras.length ? ' - includes: ' + extras.join(', ') : ''}</div>
                </div>`;
            }
        });

        html += `</div><div class="alert alert-danger mb-0" style="font-size:.85rem">
            <i class="feather-alert-triangle me-1"></i><strong>This action cannot be undone.</strong>
        </div>`;

        return html;
    }

    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach((button) => {
        button.addEventListener('shown.bs.tab', function () {
            if (window.jQuery && $.fn.DataTable) {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            }
        });
    });

    document.addEventListener('click', function (event) {
        const btn = event.target.closest('.js-delete-pr');
        if (!btn) {
            return;
        }

        const infoUrl = btn.dataset.infoUrl;
        const deleteUrl = btn.dataset.deleteUrl;
        const forceDeleteUrl = btn.dataset.forceDeleteUrl;
        const body = document.getElementById('deletePrModalBody');
        const footer = document.getElementById('deletePrModalFooter');
        const form = document.getElementById('deletePrForm');
        const forceForm = document.getElementById('forceDeletePrForm');

        body.innerHTML = `<div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
            <div class="small text-muted mt-2">Loading impact details...</div>
        </div>`;
        footer.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>`;

        bootstrap.Modal.getOrCreateInstance(document.getElementById('deletePrModal')).show();

        fetch(infoUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(data => {
                body.innerHTML = buildPrModalBody(data);

                if (data.can_delete) {
                    form.action = deleteUrl;
                    footer.innerHTML = `
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmPrDeleteBtn">
                            <i class="feather-trash-2 me-1"></i> Delete All
                        </button>`;
                    document.getElementById('confirmPrDeleteBtn').addEventListener('click', function () {
                        this.disabled = true;
                        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';
                        form.submit();
                    });
                    return;
                }

                if (data.is_admin && forceDeleteUrl) {
                    forceForm.action = forceDeleteUrl;
                    footer.innerHTML = `
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="forcePrDeleteBtn">
                            <i class="feather-zap me-1"></i> Force Delete (Admin Only)
                        </button>`;
                    document.getElementById('forcePrDeleteBtn').addEventListener('click', function () {
                        this.disabled = true;
                        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Force Deleting...';
                        forceForm.submit();
                    });
                    return;
                }

                footer.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>`;
            })
            .catch(() => {
                body.innerHTML = `<div class="alert alert-danger">Failed to load details. Please try again.</div>`;
            });
    });
})();
</script>
@endpush
@endsection
