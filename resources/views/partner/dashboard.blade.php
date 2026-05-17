@extends('layouts.partner')

@section('content')
<div class="nxl-container">
    <!-- Welcome Header -->
    <div class="page-header">
        <div class="d-flex align-items-center">
            <div class="flex-grow-1">
                <h4 class="fw-bold mb-1">{{ __('partner.welcome') }}, {{ $funder->name }}!</h4>
                <p class="text-muted mb-0">{{ __('partner.dashboard_description') }}</p>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mt-3">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Statistics Cards -->
    <div class="row g-3 mt-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="fw-bold mb-1">{{ $stats['total_programs'] }}</h3>
                            <p class="text-muted mb-0 small">{{ __('partner.total_programs') }}</p>
                        </div>
                        <div class="fs-1 text-primary">
                            <i class="feather-folder"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="fw-bold mb-1">{{ $funder->currency }} {{ number_format($stats['total_funding'], 2) }}</h3>
                            <p class="text-muted mb-0 small">{{ __('partner.total_funding') }}</p>
                        </div>
                        <div class="fs-1 text-success">
                            <i class="feather-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="fw-bold mb-1">{{ $stats['active_programs'] }}</h3>
                            <p class="text-muted mb-0 small">{{ __('partner.active_programs') }}</p>
                        </div>
                        <div class="fs-1 text-warning">
                            <i class="feather-activity"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="fw-bold mb-1">{{ $stats['think_tanks'] }}</h3>
                            <p class="text-muted mb-0 small">Think Tanks</p>
                        </div>
                        <div class="fs-1 text-info">
                            <i class="feather-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="fw-bold mb-0">Funding & Think Tank Report</h5>
            <a href="{{ route('partner.reports.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="feather-pie-chart me-1"></i> Full Report
            </a>
        </div>
        @include('partner.partials.funding-report', ['reportingOverview' => $reportingOverview, 'funder' => $funder])
    </div>

    <!-- Recent Programs -->
    <div class="card shadow-sm mt-4">
        <div class="card-header d-flex justify-content-between align-items-center bg-light">
            <h5 class="mb-0 fw-bold">{{ __('partner.recent_programs') }}</h5>
            @if($fundings->count() > 5)
                <a href="{{ route('partner.programs.index') }}" class="btn btn-sm btn-primary">
                    {{ __('partner.view_all') }}
                </a>
            @endif
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('partner.program_name') }}</th>
                            <th>{{ __('partner.governance_node') }}</th>
                            <th class="text-end">{{ __('partner.approved_amount') }}</th>
                            <th>{{ __('partner.period') }}</th>
                            <th class="text-center">{{ __('partner.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fundings->take(5) as $funding)
                        <tr>
                            <td><strong>{{ $funding->program_name ?? ($funding->program?->name ?? '—') }}</strong></td>
                            <td>
                                <div>{{ $funding->governanceNode->name ?? '-' }}</div>
                                @if($funding->governanceNode)
                                    <small class="text-muted">{{ $funding->governanceNode->level->name ?? '' }}</small>
                                @endif
                            </td>
                            <td class="text-end">
                                <strong>{{ $funding->currency ?? $funder->currency }} {{ number_format($funding->approved_amount, 2) }}</strong>
                            </td>
                            <td>{{ $funding->start_year }} - {{ $funding->end_year }}</td>
                            <td class="text-center">
                                <a href="{{ route('partner.programs.show', $funding->id) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="feather-eye"></i> {{ __('partner.view') }}
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                {{ __('partner.no_programs') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
