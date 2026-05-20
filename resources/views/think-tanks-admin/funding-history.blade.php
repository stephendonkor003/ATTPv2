@extends('layouts.app')

@section('title', 'Transfer History')

@push('styles')
    <style>
        .tt-history-modal .modal-dialog {
            max-width: min(1120px, calc(100vw - 2rem));
        }

        .tt-history-modal .modal-content {
            border: 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
        }

        .tt-history-modal .modal-header {
            background: #0f172a;
            color: #ffffff;
            border: 0;
        }

        .tt-history-modal .modal-title {
            color: #ffffff;
            font-weight: 900;
        }

        .tt-history-modal .modal-kicker,
        .tt-history-modal .modal-subtitle {
            color: #facc15;
            font-weight: 800;
        }

        .tt-history-modal .btn-close {
            filter: invert(1);
        }

        .tt-detail-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            padding: 0.95rem;
            height: 100%;
        }

        .tt-detail-card .label {
            color: #64748b;
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .tt-detail-card .value {
            color: #0f172a;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .tt-detail-section-title {
            color: #0f172a;
            font-weight: 800;
            margin-bottom: 0.8rem;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="feather-clock text-primary me-2"></i>Transfer History</h4>
                <p class="text-muted mb-0">All Funding to Think Tanks transfer records and receipt confirmations.</p>
            </div>
            <div class="d-flex gap-2">
                @can('think_tanks.funding.transfer.create')
                    <a href="{{ route('think-tanks-admin.funding.create') }}" class="btn btn-primary btn-sm">
                        <i class="feather-plus me-1"></i> Record Transfer
                    </a>
                @endcan
                <a href="{{ route('think-tanks-admin.funding') }}" class="btn btn-light btn-sm border">Funding Dashboard</a>
            </div>
        </div>

        @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

        @php
            $currency = 'USD';
        @endphp

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Sub-Activity Budget</div><h5 class="mb-0">{{ $currency }} {{ number_format($summary['budget'], 2) }}</h5></div></div></div>
            <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Transferred</div><h5 class="mb-0">{{ $currency }} {{ number_format($summary['transferred'], 2) }}</h5></div></div></div>
            <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Confirmed</div><h5 class="mb-0">{{ $currency }} {{ number_format($summary['confirmed'], 2) }}</h5></div></div></div>
            <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Remaining</div><h5 class="mb-0">{{ $currency }} {{ number_format($summary['remaining'], 2) }}</h5></div></div></div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0"><h5 class="mb-0">Transfers</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="transferHistoryTable" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Think Tank</th>
                                <th>Amount</th>
                                <th>Transfer</th>
                                <th>Paid At</th>
                                <th>Receipt Status</th>
                                <th>Notes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transfers as $transfer)
                                @php $modalId = 'transferDetails' . str_replace('-', '', $transfer->id); @endphp
                                <tr>
                                    <td>
                                        @if ($transfer->thinkTankMember)
                                            <a href="{{ route('think-tanks-admin.show', $transfer->thinkTankMember) }}" class="fw-semibold">{{ $transfer->thinkTankMember->name }}</a>
                                        @else
                                            <strong>-</strong>
                                        @endif
                                        <br><span class="text-muted small">{{ $transfer->thinkTankMember?->consortium?->name }}</span>
                                    </td>
                                    <td>USD {{ number_format($transfer->amount, 2) }}</td>
                                    <td>{{ $transfer->payment_method }}<br><span class="text-muted small">{{ $transfer->transfer_reference ?: $transfer->reference_no }}</span></td>
                                    <td>{{ $transfer->paid_at?->format('M d, Y H:i') ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $transfer->recipient_confirmation_status === 'confirmed' ? 'bg-success' : 'bg-warning text-dark' }}">
                                            {{ str_replace('_', ' ', ucfirst($transfer->recipient_confirmation_status)) }}
                                        </span>
                                        @if ($transfer->recipient_confirmed_at)
                                            <div class="text-muted small">by {{ $transfer->recipientConfirmer?->name ?? 'portal user' }} on {{ $transfer->recipient_confirmed_at->format('M d, Y H:i') }}</div>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($transfer->notes, 90) }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                            Show Details
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No transfers recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">{{ $transfers->links() }}</div>
        </div>
    </div>

    @foreach ($transfers as $transfer)
        @php
            $modalId = 'transferDetails' . str_replace('-', '', $transfer->id);
            $purchaseOrder = $transfer->purchaseOrder;
            $commitment = $purchaseOrder?->budgetCommitment;
            $purchaseRequest = $commitment?->purchaseRequest;
            $member = $transfer->thinkTankMember;
        @endphp
        <div class="modal fade tt-history-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <div class="small modal-kicker">Transaction information</div>
                            <h5 class="modal-title" id="{{ $modalId }}Label">{{ $transfer->transfer_reference ?: $transfer->reference_no }}</h5>
                            <div class="small modal-subtitle">{{ $member?->name ?? 'Think tank transfer' }} | USD {{ number_format($transfer->amount, 2) }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <h6 class="tt-detail-section-title">Transfer Summary</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3"><div class="tt-detail-card"><div class="label">Amount</div><div class="value">USD {{ number_format($transfer->amount, 2) }}</div></div></div>
                            <div class="col-md-3"><div class="tt-detail-card"><div class="label">Payment Method</div><div class="value">{{ $transfer->payment_method ?: 'Bank transfer' }}</div></div></div>
                            <div class="col-md-3"><div class="tt-detail-card"><div class="label">Transfer Reference</div><div class="value">{{ $transfer->transfer_reference ?: '-' }}</div></div></div>
                            <div class="col-md-3"><div class="tt-detail-card"><div class="label">Paid At</div><div class="value">{{ $transfer->paid_at?->format('M d, Y H:i') ?? '-' }}</div></div></div>
                        </div>

                        <h6 class="tt-detail-section-title">Think Tank and Consortium</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4"><div class="tt-detail-card"><div class="label">Think Tank</div><div class="value">{{ $member?->name ?? '-' }}</div></div></div>
                            <div class="col-md-4"><div class="tt-detail-card"><div class="label">Consortium</div><div class="value">{{ $member?->consortium?->name ?? '-' }}</div></div></div>
                            <div class="col-md-2"><div class="tt-detail-card"><div class="label">Country</div><div class="value">{{ $member?->country ?: '-' }}</div></div></div>
                            <div class="col-md-2"><div class="tt-detail-card"><div class="label">Portal User</div><div class="value">{{ $member?->portalUser?->email ?? $member?->email ?? '-' }}</div></div></div>
                        </div>

                        <h6 class="tt-detail-section-title">Budget and System Trail</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3"><div class="tt-detail-card"><div class="label">Disbursement Ref</div><div class="value">{{ $transfer->reference_no }}</div></div></div>
                            <div class="col-md-3"><div class="tt-detail-card"><div class="label">Purchase Order</div><div class="value">{{ $purchaseOrder?->reference_no ?? '-' }}</div></div></div>
                            <div class="col-md-3"><div class="tt-detail-card"><div class="label">Purchase Request</div><div class="value">{{ $purchaseRequest?->reference_no ?? '-' }}</div></div></div>
                            <div class="col-md-3"><div class="tt-detail-card"><div class="label">Commitment Year</div><div class="value">{{ $commitment?->commitment_year ?? '-' }}</div></div></div>
                            <div class="col-md-4"><div class="tt-detail-card"><div class="label">Commitment Status</div><div class="value">{{ $commitment ? ucfirst($commitment->status) : '-' }}</div></div></div>
                            <div class="col-md-4"><div class="tt-detail-card"><div class="label">Created By</div><div class="value">{{ $commitment?->creator?->name ?? '-' }}</div></div></div>
                            <div class="col-md-4"><div class="tt-detail-card"><div class="label">Approved By</div><div class="value">{{ $commitment?->approver?->name ?? '-' }}</div></div></div>
                        </div>

                        <h6 class="tt-detail-section-title">Receipt Confirmation</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="tt-detail-card">
                                    <div class="label">Receipt Status</div>
                                    <div class="value">
                                        <span class="badge {{ $transfer->recipient_confirmation_status === 'confirmed' ? 'bg-success' : 'bg-warning text-dark' }}">
                                            {{ str_replace('_', ' ', ucfirst($transfer->recipient_confirmation_status ?? 'pending')) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3"><div class="tt-detail-card"><div class="label">Confirmed By</div><div class="value">{{ $transfer->recipientConfirmer?->name ?? '-' }}</div></div></div>
                            <div class="col-md-3"><div class="tt-detail-card"><div class="label">Confirmed At</div><div class="value">{{ $transfer->recipient_confirmed_at?->format('M d, Y H:i') ?? '-' }}</div></div></div>
                            <div class="col-md-3"><div class="tt-detail-card"><div class="label">Recipient Notes</div><div class="value">{{ $transfer->recipient_confirmation_notes ?: '-' }}</div></div></div>
                        </div>

                        <h6 class="tt-detail-section-title">Administrative Notes</h6>
                        <div class="tt-detail-card">
                            <div class="value">{{ $transfer->notes ?: 'No administrative notes recorded for this transfer.' }}</div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        @if ($member)
                            <a href="{{ route('think-tanks-admin.show', $member) }}" class="btn btn-primary">
                                <i class="feather-user me-1"></i> Open Think Tank Profile
                            </a>
                        @endif
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
