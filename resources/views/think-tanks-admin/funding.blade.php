@extends('layouts.app')

@section('title', 'Funding to Think Tanks')

@push('styles')
    <style>
        .tt-funding-source {
            border: 0;
            border-radius: 10px;
            background: #0f172a;
            color: #f8fafc;
        }

        .tt-funding-source .source-kicker {
            color: #bae6fd;
            font-weight: 700;
            letter-spacing: 0.06em;
        }

        .tt-funding-source .source-path {
            color: #f1f5f9;
            line-height: 1.65;
            font-size: 0.94rem;
        }

        .tt-funding-source .source-path strong {
            color: #ffffff;
            font-weight: 800;
        }

        .tt-funding-source .source-title {
            color: #ffffff;
            font-weight: 800;
        }

        .tt-metric-card {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }

        .tt-metric-card .label {
            color: #64748b;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .tt-metric-card .value {
            color: #0f172a;
            font-size: 1.35rem;
            font-weight: 700;
        }

        .tt-progress {
            height: 8px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .tt-progress > span {
            display: block;
            height: 100%;
            background: #0ea5e9;
        }

        .tt-transfer-modal .modal-dialog {
            max-width: min(1120px, calc(100vw - 2rem));
        }

        .tt-transfer-modal .modal-content {
            border: 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
        }

        .tt-transfer-modal .modal-header {
            background: #0f172a;
            color: #ffffff;
            border: 0;
        }

        .tt-transfer-modal .modal-title {
            color: #ffffff;
            font-weight: 900;
        }

        .tt-transfer-modal .modal-kicker,
        .tt-transfer-modal .modal-subtitle {
            color: #facc15;
            font-weight: 800;
        }

        .tt-transfer-modal .btn-close {
            filter: invert(1);
        }

        .tt-modal-stat {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.9rem;
            background: #f8fafc;
        }

        .tt-modal-stat .label {
            color: #64748b;
            font-size: 0.76rem;
            text-transform: uppercase;
        }

        .tt-modal-stat .value {
            color: #0f172a;
            font-size: 1.1rem;
            font-weight: 800;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="feather-send text-primary me-2"></i>Funding to Think Tanks</h4>
                <p class="text-muted mb-0">Sub-activity budget, transfer coverage, and think tank funding status.</p>
            </div>
            <div class="d-flex gap-2">
                @can('think_tanks.funding.history.view')
                    <a href="{{ route('think-tanks-admin.funding.history') }}" class="btn btn-light btn-sm border">
                        <i class="feather-clock me-1"></i> Transfer History
                    </a>
                @endcan
                @can('think_tanks.funding.transfer.create')
                    <a href="{{ route('think-tanks-admin.funding.create') }}" class="btn btn-primary btn-sm">
                        <i class="feather-plus me-1"></i> Record Transfer
                    </a>
                @endcan
            </div>
        </div>

        @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

        <div class="card tt-funding-source mb-4">
            <div class="card-body">
                <div class="small text-uppercase mb-2 source-kicker">Budget Source</div>
                <h5 class="source-title mb-2">{{ $source['subActivity']?->name ?? 'Funding to Think Tanks' }}</h5>
                <div class="source-path">
                    {{ $source['subActivity']?->activity?->project?->project_id ?? 'PROG00001-02' }}
                    - {{ $source['subActivity']?->activity?->project?->name ?? 'COMPONENT 2: Strengthen the quality, relevance, and uptake of policy research on priority issues' }}
                    / {{ $source['subActivity']?->activity?->name ?? 'Sub-Component: Finance high-quality research on priority issues and support think tanks capacity building' }}
                </div>
            </div>
        </div>

        @php
            $currency = 'USD';
        @endphp

        <div class="row g-3 mb-4">
            @foreach ([
                ['label' => 'Sub-Activity Budget', 'value' => $summary['budget'], 'percent' => 100, 'bar' => '#0ea5e9'],
                ['label' => 'Transferred', 'value' => $summary['transferred'], 'percent' => $summary['transfer_rate'], 'bar' => '#16a34a'],
                ['label' => 'Remaining', 'value' => $summary['remaining'], 'percent' => $summary['remaining_rate'], 'bar' => '#f59e0b'],
                ['label' => 'Confirmed Received', 'value' => $summary['confirmed'], 'percent' => $summary['confirmation_rate'], 'bar' => '#6366f1'],
            ] as $card)
                <div class="col-md-6 col-xl-3">
                    <div class="card tt-metric-card h-100">
                        <div class="card-body">
                            <div class="label">{{ $card['label'] }}</div>
                            <div class="value">{{ $currency }} {{ number_format((float) $card['value'], 2) }}</div>
                            <div class="d-flex justify-content-between small text-muted mt-3 mb-1">
                                <span>{{ number_format((float) $card['percent'], 1) }}%</span>
                                <span>{{ $card['label'] === 'Sub-Activity Budget' ? 'base' : 'of budget' }}</span>
                            </div>
                            <div class="tt-progress"><span style="width: {{ min(100, (float) $card['percent']) }}%; background: {{ $card['bar'] }}"></span></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Think Tanks and Consortia</h5>
                <span class="text-muted small">{{ number_format($thinkTanks->count()) }} think tanks</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="thinkTanksFundingTable" class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Think Tank</th>
                                <th>Consortium</th>
                                <th>Country</th>
                                <th>Allocated</th>
                                <th>Current Disbursement</th>
                                <th>Transferred</th>
                                <th>Transfers</th>
                                <th>Receipt</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($thinkTanks as $thinkTank)
                                @php
                                    $allocated = (float) $thinkTank->budget_allocated + (float) $thinkTank->fund_allocations_sum_amount_allocated;
                                    $transferred = (float) $thinkTank->transfer_disbursements_sum_amount;
                                    $transfers = $thinkTank->transferDisbursements;
                                    $currentDisbursement = (float) ($transfers->first()?->amount ?? 0);
                                    $confirmedAmount = (float) $transfers->where('recipient_confirmation_status', 'confirmed')->sum('amount');
                                    $pendingAmount = max($transferred - $confirmedAmount, 0);
                                    $modalId = 'thinkTankTransfers' . str_replace('-', '', $thinkTank->id);
                                @endphp
                                <tr>
                                    <td><a href="{{ route('think-tanks-admin.show', $thinkTank) }}" class="fw-semibold">{{ $thinkTank->name }}</a><br><span class="text-muted small">{{ $thinkTank->email ?: 'No email' }}</span></td>
                                    <td>{{ $thinkTank->consortium?->name ?? '-' }}</td>
                                    <td>{{ $thinkTank->country ?: '-' }}</td>
                                    <td>{{ $currency }} {{ number_format($allocated, 2) }}</td>
                                    <td>{{ $currency }} {{ number_format($currentDisbursement, 2) }}</td>
                                    <td>{{ $currency }} {{ number_format($transferred, 2) }}</td>
                                    <td>{{ number_format($thinkTank->transfer_disbursements_count) }}</td>
                                    <td>{{ number_format($thinkTank->confirmed_transfers_count) }} confirmed</td>
                                    <td><span class="badge bg-light text-dark">{{ ucfirst($thinkTank->status) }}</span></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                            View Transfers
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @foreach ($thinkTanks as $thinkTank)
        @php
            $transfers = $thinkTank->transferDisbursements;
            $transferred = (float) $transfers->sum('amount');
            $currentDisbursement = (float) ($transfers->first()?->amount ?? 0);
            $confirmedAmount = (float) $transfers->where('recipient_confirmation_status', 'confirmed')->sum('amount');
            $pendingAmount = max($transferred - $confirmedAmount, 0);
            $modalId = 'thinkTankTransfers' . str_replace('-', '', $thinkTank->id);
        @endphp
        <div class="modal fade tt-transfer-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <div class="small modal-kicker">Transfer history</div>
                            <h5 class="modal-title fw-bold" id="{{ $modalId }}Label">{{ $thinkTank->name }}</h5>
                            <div class="small modal-subtitle">{{ $thinkTank->consortium?->name ?? 'No consortium linked' }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-3"><div class="tt-modal-stat"><div class="label">Number of Transfers</div><div class="value">{{ number_format($transfers->count()) }}</div></div></div>
                            <div class="col-md-3"><div class="tt-modal-stat"><div class="label">Current Disbursement</div><div class="value">USD {{ number_format($currentDisbursement, 2) }}</div></div></div>
                            <div class="col-md-3"><div class="tt-modal-stat"><div class="label">Total Transferred</div><div class="value">USD {{ number_format($transferred, 2) }}</div></div></div>
                            <div class="col-md-3"><div class="tt-modal-stat"><div class="label">Pending Receipt</div><div class="value">USD {{ number_format($pendingAmount, 2) }}</div></div></div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Paid At</th>
                                        <th>Receipt</th>
                                        <th>Notes</th>
                                        @can('think_tanks.funding.transfer.edit')
                                            <th>Action</th>
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($transfers as $transfer)
                                        @php $editRowId = 'editTransferRow' . str_replace('-', '', $transfer->id); @endphp
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td><strong>{{ $transfer->transfer_reference ?: $transfer->reference_no }}</strong><br><span class="text-muted small">{{ $transfer->purchaseOrder?->reference_no }}</span></td>
                                            <td>USD {{ number_format($transfer->amount, 2) }}</td>
                                            <td>{{ $transfer->payment_method ?: 'Bank transfer' }}</td>
                                            <td>{{ $transfer->paid_at?->format('M d, Y H:i') ?? '-' }}</td>
                                            <td>
                                                <span class="badge {{ $transfer->recipient_confirmation_status === 'confirmed' ? 'bg-success' : 'bg-warning text-dark' }}">
                                                    {{ str_replace('_', ' ', ucfirst($transfer->recipient_confirmation_status ?? 'pending')) }}
                                                </span>
                                                @if ($transfer->recipient_confirmed_at)
                                                    <div class="text-muted small">{{ $transfer->recipient_confirmed_at->format('M d, Y H:i') }}</div>
                                                @endif
                                            </td>
                                            <td>{{ \Illuminate\Support\Str::limit($transfer->notes, 100) }}</td>
                                            @can('think_tanks.funding.transfer.edit')
                                                <td>
                                                    <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $editRowId }}" aria-expanded="false" aria-controls="{{ $editRowId }}">
                                                        Edit
                                                    </button>
                                                </td>
                                            @endcan
                                        </tr>
                                        @can('think_tanks.funding.transfer.edit')
                                            <tr class="collapse" id="{{ $editRowId }}">
                                                <td colspan="8" class="bg-light">
                                                    <form method="POST" action="{{ route('think-tanks-admin.funding.transfers.update', $transfer) }}" class="row g-3 align-items-end">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="col-md-2">
                                                            <label class="form-label">Amount</label>
                                                            <input class="form-control form-control-sm" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $transfer->amount) }}" required>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label">Paid At</label>
                                                            <input class="form-control form-control-sm" type="datetime-local" name="paid_at" value="{{ old('paid_at', $transfer->paid_at?->format('Y-m-d\TH:i')) }}" required>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label">Method</label>
                                                            <input class="form-control form-control-sm" name="payment_method" value="{{ old('payment_method', $transfer->payment_method ?: 'Bank transfer') }}" required>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Transfer Reference</label>
                                                            <input class="form-control form-control-sm" name="transfer_reference" value="{{ old('transfer_reference', $transfer->transfer_reference) }}">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Notes</label>
                                                            <input class="form-control form-control-sm" name="notes" value="{{ old('notes', $transfer->notes) }}">
                                                        </div>
                                                        <div class="col-12 d-flex justify-content-end">
                                                            <button class="btn btn-sm btn-primary" type="submit">Update Transfer</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endcan
                                    @empty
                                        <tr><td colspan="{{ auth()->user()?->can('think_tanks.funding.transfer.edit') ? 8 : 7 }}" class="text-center text-muted py-4">No periodic disbursement has been recorded for this think tank yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        @can('think_tanks.funding.transfer.create')
                            <a href="{{ route('think-tanks-admin.funding.create') }}" class="btn btn-primary">
                                <i class="feather-plus me-1"></i> Record New Transfer
                            </a>
                        @endcan
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && $.fn.DataTable && !$.fn.DataTable.isDataTable('#thinkTanksFundingTable')) {
                $('#thinkTanksFundingTable').DataTable(window.dataTableConfig || {});
            }
        });
    </script>
@endpush
