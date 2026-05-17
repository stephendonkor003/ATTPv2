@extends('layouts.app')

@section('title', 'Consortium Operations')

@push('styles')
    <style>
        .think-guide-hero {
            background: linear-gradient(130deg, #0f172a 0%, #1d4ed8 55%, #38bdf8 100%);
            border-radius: 16px;
            padding: 1.4rem;
            color: #f8fafc;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.2);
        }

        .think-guide-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            font-size: 0.75rem;
            margin: 0.2rem 0.4rem 0 0;
        }

        .think-stat-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }

        .think-stat-card .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #dbeafe;
            color: #1d4ed8;
        }

        .think-table thead th {
            background: #e2e8f0;
            color: #0f172a;
            border-bottom: 2px solid #94a3b8;
            white-space: nowrap;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .think-table td {
            vertical-align: middle;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container">
        @php
            $firstConsortium = $consortia->first();
        @endphp

        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-users text-primary me-2"></i>
                    Think Tank Management
                </h4>
                <p class="text-muted mb-0">Consortium supervision, funding oversight, research, reporting, and procurement workspace.</p>
            </div>
            @can('consortiums.manage')
                <a href="#create-consortium" class="btn btn-primary btn-sm">
                    <i class="feather-plus me-1"></i> New Consortium
                </a>
            @endcan
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <ul class="nav nav-tabs attp-management-tabs mb-4">
            <li class="nav-item"><a class="nav-link active" aria-current="page" href="{{ route('consortium-operations.index') }}">Consortium Operations</a></li>
            @if (Route::has('partner.runtime-overview'))
                @can('partner.runtime_overview.view')
                    <li class="nav-item"><a class="nav-link" href="{{ route('partner.runtime-overview') }}">Partner Runtime Overview</a></li>
                @else
                    <li class="nav-item"><span class="nav-link disabled">Partner Runtime Overview</span></li>
                @endcan
            @endif
            @can('consortiums.view')
                @if ($firstConsortium)
                    <li class="nav-item"><a class="nav-link" href="{{ route('consortium-operations.show', $firstConsortium) }}">Think Tank Portal Oversight</a></li>
                @elseif ($firstPortalMember && Route::has('think-tank.dashboard'))
                    <li class="nav-item"><a class="nav-link" href="{{ route('think-tank.dashboard', ['think_tank_member_id' => $firstPortalMember->id]) }}">Think Tank Portal Oversight</a></li>
                @else
                    <li class="nav-item"><span class="nav-link disabled">Think Tank Portal Oversight</span></li>
                @endif
            @else
                <li class="nav-item"><span class="nav-link disabled">Think Tank Portal Oversight</span></li>
            @endcan
        </ul>

        <div class="card shadow-sm border-0 overflow-hidden mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="think-guide-hero">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-8">
                            <span class="badge bg-light text-primary fw-semibold mb-2">Think Tank Operations</span>
                            <h4 class="fw-bold mb-2 text-white">Secretariat 360 Oversight Workspace</h4>
                            <p class="mb-0">
                                Track each consortium, its think tank members, distributed funds, submitted reports,
                                published research, procurement plans, and implementation risks in one place.
                            </p>
                            <div class="mt-3">
                                <span class="think-guide-chip"><i class="feather-credit-card"></i> Funds</span>
                                <span class="think-guide-chip"><i class="feather-file-text"></i> Reports</span>
                                <span class="think-guide-chip"><i class="feather-book-open"></i> Research</span>
                                <span class="think-guide-chip"><i class="feather-briefcase"></i> Procurement</span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="p-3 rounded-3" style="background: rgba(15, 23, 42, 0.18); border: 1px solid rgba(255,255,255,.3);">
                                <p class="fw-semibold mb-1">Recommended Start</p>
                                <p class="mb-0 small">Create a consortium, add its think tanks, assign allocations, then monitor reports and procurement from the detail page.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="card think-stat-card h-100">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div><div class="text-muted small">Consortia</div><h3 class="mb-0">{{ number_format($summary['consortia']) }}</h3></div>
                        <span class="stat-icon"><i class="feather-grid"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card think-stat-card h-100">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div><div class="text-muted small">Think Tanks</div><h3 class="mb-0">{{ number_format($summary['think_tanks']) }}</h3></div>
                        <span class="stat-icon"><i class="feather-users"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card think-stat-card h-100">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div><div class="text-muted small">Total Distributed</div><h3 class="mb-0">USD {{ number_format($summary['funds_disbursed'], 2) }}</h3></div>
                        <span class="stat-icon"><i class="feather-dollar-sign"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card think-stat-card h-100">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div><div class="text-muted small">Open Risks</div><h3 class="mb-0">{{ number_format($summary['open_risks']) }}</h3></div>
                        <span class="stat-icon"><i class="feather-alert-triangle"></i></span>
                    </div>
                </div>
            </div>
        </div>

        @can('consortiums.manage')
        <div class="card border-0 shadow-sm mb-4" id="create-consortium">
            <div class="card-header bg-white border-0"><h5 class="mb-0">Create Consortium</h5></div>
            <div class="card-body">
                <form class="row g-3" method="POST" action="{{ route('consortium-operations.store') }}">
                    @csrf
                    <div class="col-md-4"><input class="form-control" name="name" placeholder="Consortium name" required></div>
                    <div class="col-md-4"><input class="form-control" name="country" placeholder="Country"></div>
                    <div class="col-md-4"><input class="form-control" name="region" placeholder="Region"></div>
                    <div class="col-md-4"><input class="form-control" name="currency" value="USD" placeholder="Currency" readonly></div>
                    <div class="col-md-2"><input class="form-control" name="start_date" type="date"></div>
                    <div class="col-md-2"><input class="form-control" name="end_date" type="date"></div>
                    <div class="col-md-10"><textarea class="form-control" name="mandate" placeholder="Mandate" rows="1"></textarea></div>
                    <div class="col-md-2 d-grid"><button class="btn btn-primary" type="submit">Create</button></div>
                </form>
            </div>
        </div>
        @endcan

        <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
            <form class="row g-2 align-items-center" method="GET">
                <div class="col-md-7"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search consortia"></div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">All statuses</option>
                        @foreach (['active', 'paused', 'closed'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit">Filter</button></div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle think-table">
                    <thead>
                        <tr><th>Consortium</th><th>Partner</th><th>Members</th><th>Reports</th><th>Risks</th><th>Total Distributed</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse ($consortia as $consortium)
                        <tr>
                            <td><strong>{{ $consortium->name }}</strong><br><span class="text-muted small">{{ $consortium->code }}</span></td>
                            <td>{{ $consortium->funder?->name ?? 'Not linked' }}</td>
                            <td>{{ $consortium->members_count }}</td>
                            <td>{{ $consortium->activity_reports_count }}</td>
                            <td>{{ $consortium->risk_flags_count }}</td>
                            <td>USD {{ number_format((float) $consortium->fund_allocations_sum_amount_disbursed, 2) }}</td>
                            <td><a class="btn btn-sm btn-light border" href="{{ route('consortium-operations.show', $consortium) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No consortium workspaces yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $consortia->links() }}</div>
        </div>
    </div>
@endsection
