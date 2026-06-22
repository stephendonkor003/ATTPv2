@extends('layouts.partner')

@section('content')
<div class="nxl-container">
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div>
                <h4 class="fw-bold mb-1">Partner Reports</h4>
                <p class="text-muted mb-0">Funding balances and think tank performance for {{ $funder->name }}.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('partner.reports.financial-position') }}" class="btn btn-primary btn-sm">
                    <i class="feather-dollar-sign me-1"></i> Financial Position
                </a>
                <a href="{{ route('partner.dashboard') }}" class="btn btn-light btn-sm">
                    <i class="feather-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-3">
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('partner.dashboard') }}" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                <div class="card-body">
                    <div class="fs-3 text-primary mb-2"><i class="feather-home"></i></div>
                    <h6 class="fw-bold mb-1">Dashboard Report</h6>
                    <p class="small text-muted mb-0">Executive view of funded programs, projects, funding and delivery.</p>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('partner.reports.financial-position') }}" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                <div class="card-body">
                    <div class="fs-3 text-success mb-2"><i class="feather-dollar-sign"></i></div>
                    <h6 class="fw-bold mb-1">Financial Position</h6>
                    <p class="small text-muted mb-0">Budget, commitments, paid amounts and remaining balances.</p>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('partner.insights') }}" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                <div class="card-body">
                    <div class="fs-3 text-warning mb-2"><i class="feather-bar-chart-2"></i></div>
                    <h6 class="fw-bold mb-1">Portfolio Insights</h6>
                    <p class="small text-muted mb-0">Drill into funded programs, projects, activities and sectors.</p>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('partner.workplan.index') }}" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                <div class="card-body">
                    <div class="fs-3 text-info mb-2"><i class="feather-check-square"></i></div>
                    <h6 class="fw-bold mb-1">Work Plan Report</h6>
                    <p class="small text-muted mb-0">Read-only work-plan and no-objection status report.</p>
                </div>
            </a>
        </div>
    </div>

    @if($fundings->isNotEmpty())
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-light">
                <h5 class="fw-bold mb-1">Program Reports</h5>
                <div class="small text-muted">Admin-style program reports scoped to {{ $funder->name }} funding records.</div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Period</th>
                                <th class="text-end">Approved Amount</th>
                                <th class="text-center">Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fundings as $funding)
                                <tr>
                                    <td class="fw-semibold">{{ $funding->program_name ?? ($funding->program?->name ?? 'Unnamed program') }}</td>
                                    <td>{{ $funding->start_year ?? 'N/A' }} - {{ $funding->end_year ?? 'N/A' }}</td>
                                    <td class="text-end">
                                        {{ $funding->currency ?? $funder->currency }} {{ number_format((float) $funding->approved_amount, 2) }}
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('partner.programs.report', $funding) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="feather-file-text me-1"></i> View Report
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="mt-3">
        @include('partner.partials.funding-report', ['reportingOverview' => $reportingOverview, 'funder' => $funder])
    </div>
</div>
@endsection
