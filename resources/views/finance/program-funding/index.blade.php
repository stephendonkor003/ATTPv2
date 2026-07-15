@extends('layouts.app')

@section('title', 'Program Funding')

@push('styles')
    <style>
        .funding-workspace {
            color: #0f172a;
        }

        .funding-hero {
            border-radius: 8px;
            padding: 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 58%, #522b39 100%);
            box-shadow: 0 16px 32px rgba(6, 63, 54, 0.14);
        }

        .funding-hero h4,
        .funding-hero p {
            color: #ffffff;
        }

        .funding-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .funding-hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            color: #effff9;
            background: rgba(255, 255, 255, 0.1);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .funding-stat-grid,
        .funding-insight-grid {
            display: grid;
            gap: 12px;
        }

        .funding-stat-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .funding-insight-grid {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr);
        }

        .funding-stat-card,
        .funding-panel,
        .funding-table-card {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .funding-stat-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            min-height: 82px;
        }

        .funding-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            color: #065f46;
            background: #d1fae5;
        }

        .funding-stat-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .funding-stat-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .funding-stat-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .funding-stat-label {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .funding-stat-value {
            color: #0f172a;
            font-size: 1.18rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .funding-panel {
            padding: 16px;
        }

        .funding-status-list {
            display: grid;
            gap: 9px;
        }

        .funding-status-row {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: #f8fafc;
        }

        .funding-status-row span {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .funding-status-row strong {
            color: #0f172a;
        }

        .funding-progress {
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            background: #e2e8f0;
        }

        .funding-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0f766e 0%, #d97706 100%);
        }

        .funding-type-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .funding-type-cloud span {
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            color: #075985;
            background: #e0f2fe;
            font-size: 0.82rem;
            font-weight: 800;
        }

        .funding-table-card .table td {
            vertical-align: middle;
        }

        .funding-program-cell {
            min-width: 260px;
        }

        .funding-muted {
            color: #64748b;
            font-size: 0.82rem;
        }

        .funding-status-badge {
            border-radius: 999px;
            padding: 0.28rem 0.62rem;
            display: inline-flex;
            align-items: center;
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .funding-status-badge.draft {
            color: #92400e;
            background: #fef3c7;
        }

        .funding-status-badge.submitted {
            color: #075985;
            background: #e0f2fe;
        }

        .funding-status-badge.approved {
            color: #065f46;
            background: #d1fae5;
        }

        .funding-status-badge.rejected {
            color: #991b1b;
            background: #fee2e2;
        }

        .funding-status-badge.unknown {
            color: #475569;
            background: #f1f5f9;
        }

        .funding-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            white-space: nowrap;
        }

        .funding-icon-action {
            width: 30px;
            height: 30px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #1d4ed8;
            background: #eff6ff;
            font-size: 0.9rem;
            line-height: 1;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .funding-icon-action:hover {
            border-color: #1d4ed8;
            color: #ffffff;
            background: #1d4ed8;
        }

        .funding-icon-action.warning {
            border-color: #fde68a;
            color: #92400e;
            background: #fffbeb;
        }

        .funding-icon-action.warning:hover {
            border-color: #d97706;
            color: #ffffff;
            background: #d97706;
        }

        .funding-icon-action.danger {
            border-color: #fecaca;
            color: #b91c1c;
            background: #fef2f2;
        }

        .funding-icon-action.danger:hover {
            border-color: #b91c1c;
            color: #ffffff;
            background: #b91c1c;
        }

        .funding-empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            color: #64748b;
            background: #f8fafc;
        }

        @media (max-width: 1199.98px) {
            .funding-stat-grid,
            .funding-insight-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .funding-stat-grid,
            .funding-insight-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $statusClass = fn ($status) => in_array($status, ['draft', 'submitted', 'approved', 'rejected'], true) ? $status : 'unknown';
        $approvedCoverage = $fundingStats['total_amount'] > 0
            ? min(100, round(($fundingStats['approved_amount'] / $fundingStats['total_amount']) * 100))
            : 0;
        $maxFunderAmount = max(1, (float) $topFunders->max(fn ($funder) => (float) $funder->amount));
    @endphp

    <div class="nxl-container funding-workspace">
        <div class="funding-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="funding-kicker mb-2">Finance Funding Control</div>
                    <h4 class="fw-bold mb-2">Program Funding Workspace</h4>
                    <p class="mb-0">
                        Track funding sources, governance ownership, approval status, documents, and partner commitments before budget execution.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="funding-hero-chip"><i class="feather-file-text"></i> {{ number_format($fundingStats['total']) }} records</span>
                        <span class="funding-hero-chip"><i class="feather-users"></i> {{ number_format($fundingStats['funders']) }} funders</span>
                        <span class="funding-hero-chip"><i class="feather-shield"></i> {{ number_format($fundingStats['governance_nodes']) }} governance nodes</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    @can('finance.program_funding.create')
                        <a href="{{ route('finance.program-funding.create') }}" class="btn btn-success">
                            <i class="feather-plus-circle me-1"></i> New Funding
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mt-3">
                <i class="feather-check-circle me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mt-3">
                <i class="feather-alert-triangle me-2"></i> {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="funding-stat-grid mt-3">
            <div class="funding-stat-card">
                <span class="funding-stat-icon"><i class="feather-file-text"></i></span>
                <div>
                    <div class="funding-stat-label">Funding Records</div>
                    <div class="funding-stat-value">{{ number_format($fundingStats['total']) }}</div>
                    <small class="text-muted">{{ number_format($fundingStats['documents']) }} documents attached</small>
                </div>
            </div>
            <div class="funding-stat-card">
                <span class="funding-stat-icon blue"><i class="feather-dollar-sign"></i></span>
                <div>
                    <div class="funding-stat-label">Portfolio Amount</div>
                    <div class="funding-stat-value">{{ number_format((float) $fundingStats['total_amount'], 2) }}</div>
                    <small class="text-muted">Across visible records</small>
                </div>
            </div>
            <div class="funding-stat-card">
                <span class="funding-stat-icon amber"><i class="feather-clock"></i></span>
                <div>
                    <div class="funding-stat-label">Awaiting Approval</div>
                    <div class="funding-stat-value">{{ number_format($fundingStats['submitted']) }}</div>
                    <small class="text-muted">{{ number_format($fundingStats['draft']) }} drafts</small>
                </div>
            </div>
            <div class="funding-stat-card">
                <span class="funding-stat-icon wine"><i class="feather-check-circle"></i></span>
                <div>
                    <div class="funding-stat-label">Approved Funding</div>
                    <div class="funding-stat-value">{{ number_format((float) $fundingStats['approved_amount'], 2) }}</div>
                    <small class="text-muted">{{ $approvedCoverage }}% of visible amount</small>
                </div>
            </div>
        </div>

        <div class="funding-insight-grid mt-3">
            <div class="funding-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Approval Pipeline</h6>
                        <p class="text-muted small mb-0">Funding records by lifecycle status.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">{{ number_format($fundingStats['approved']) }} approved</span>
                </div>

                <div class="funding-status-list">
                    @forelse ($statusBreakdown as $status => $rollup)
                        <div class="funding-status-row">
                            <div>
                                <span>{{ Str::headline($status) }}</span>
                                <div class="small text-muted">{{ number_format((float) $rollup['amount'], 2) }}</div>
                            </div>
                            <strong>{{ number_format($rollup['count']) }}</strong>
                        </div>
                    @empty
                        <div class="funding-empty-state">No funding statuses have been recorded.</div>
                    @endforelse
                </div>
            </div>

            <div class="funding-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Funding Type Mix</h6>
                        <p class="text-muted small mb-0">Grant, allocation, and capital distribution.</p>
                    </div>
                    <i class="feather-pie-chart text-success"></i>
                </div>

                <div class="funding-type-cloud mb-3">
                    @forelse ($typeBreakdown as $type => $rollup)
                        <span>{{ Str::headline($type) }}: {{ number_format($rollup['count']) }}</span>
                    @empty
                        <span>No types</span>
                    @endforelse
                </div>

                <div class="funding-status-list">
                    @forelse ($typeBreakdown->take(3) as $type => $rollup)
                        <div class="funding-status-row">
                            <div>
                                <span>{{ Str::headline($type) }}</span>
                                <div class="small text-muted">{{ number_format($rollup['count']) }} records</div>
                            </div>
                            <strong>{{ number_format((float) $rollup['amount'], 2) }}</strong>
                        </div>
                    @empty
                        <div class="funding-empty-state">No funding type data available.</div>
                    @endforelse
                </div>
            </div>

            <div class="funding-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Top Funders</h6>
                        <p class="text-muted small mb-0">Largest funders by visible approved amount field.</p>
                    </div>
                    <span class="badge bg-light text-dark border">Top {{ number_format($topFunders->count()) }}</span>
                </div>

                <div class="d-grid gap-3">
                    @forelse ($topFunders as $funder)
                        @php
                            $width = max(3, min(100, ((float) $funder->amount / $maxFunderAmount) * 100));
                        @endphp
                        <div>
                            <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
                                <div class="fw-semibold text-dark text-truncate">{{ $funder->name }}</div>
                                <div class="small fw-bold text-nowrap">{{ number_format((float) $funder->amount, 2) }}</div>
                            </div>
                            <div class="funding-progress">
                                <span style="width: {{ $width }}%;"></span>
                            </div>
                            <small class="text-muted">{{ number_format($funder->count) }} records</small>
                        </div>
                    @empty
                        <div class="funding-empty-state">No funder funding records have been added yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3 border-0 funding-table-card">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Funding Records</h6>
                        <p class="text-muted small mb-0">Review funding source, governance owner, amount, period, status, and supporting documents.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">{{ number_format($fundings->count()) }} records</span>
                </div>

                <x-data-table
                    id="fundingTable"
                    :config="[
                        'pageLength' => 25,
                        'order' => [[6, 'desc']],
                        'columnDefs' => [
                            ['targets' => [0, 8], 'orderable' => false, 'searchable' => false],
                        ],
                    ]"
                >
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Program</th>
                            <th>Funder</th>
                            <th>Governance</th>
                            <th>Period</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th>Documents</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fundings as $funding)
                            @php
                                $status = $funding->status ?: 'unknown';
                                $programName = $funding->program_name ?: ($funding->program?->name ?? 'Unassigned program');
                                $currency = $funding->resolved_currency;
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="funding-program-cell">
                                        <div class="fw-semibold text-dark">{{ $programName }}</div>
                                        <div class="funding-muted">{{ Str::headline($funding->funding_type ?: 'unspecified') }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $funding->funder->name ?? 'Unassigned' }}</div>
                                    <div class="funding-muted">{{ $funding->is_continental_initiative ? 'Continental initiative' : 'Targeted funding' }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $funding->governanceNode->name ?? '-' }}</div>
                                    <div class="funding-muted">{{ $funding->governanceNode->level->name ?? 'No level' }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $funding->start_year ?? 'N/A' }} - {{ $funding->end_year ?? 'N/A' }}</div>
                                    <div class="funding-muted">
                                        {{ $funding->created_at ? $funding->created_at->format('d M Y') : 'Not logged' }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="fw-bold text-dark">{{ $currency }} {{ number_format((float) ($funding->approved_amount ?? 0), 2) }}</div>
                                </td>
                                <td>
                                    <span class="funding-status-badge {{ $statusClass($status) }}">
                                        {{ Str::headline($status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ number_format($funding->documents->count()) }}</div>
                                    <div class="funding-muted">attached</div>
                                </td>
                                <td class="text-end">
                                    <div class="funding-actions">
                                        @can('finance.program_funding.view')
                                            <a href="{{ route('finance.program-funding.show', $funding->id) }}"
                                                class="funding-icon-action" title="View Funding">
                                                <i class="feather-eye"></i>
                                            </a>
                                        @endcan
                                        @can('finance.program_funding.edit')
                                            <a href="{{ route('finance.program-funding.edit', $funding->id) }}"
                                                class="funding-icon-action warning" title="Edit Funding">
                                                <i class="feather-edit"></i>
                                            </a>
                                        @endcan
                                        @can('finance.program_funding.delete')
                                            <form method="POST" action="{{ route('finance.program-funding.destroy', $funding) }}"
                                                onsubmit="return confirm('Delete this program funding record? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="funding-icon-action danger" title="Delete Funding">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>

                @if ($fundings->isEmpty())
                    <div class="funding-empty-state text-center mt-3">
                        No program funding records found.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
