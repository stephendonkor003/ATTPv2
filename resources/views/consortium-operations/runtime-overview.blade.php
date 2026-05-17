@extends('layouts.app')

@section('title', 'Runtime Partner Overview')

@section('content')
    <div class="nxl-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="feather-activity text-primary me-2"></i>
                Runtime Partner Overview
            </h4>
            <p class="text-muted mb-0">{{ $funder?->name ?? 'Funding Partner' }} live visibility into consortium implementation, reporting, funds, and risks.</p>
        </div>
        @can('consortiums.view')
            <a href="{{ route('consortium-operations.index') }}" class="btn btn-primary btn-sm">Think Tank Management</a>
        @else
            <span class="btn btn-primary btn-sm disabled">Think Tank Management</span>
        @endcan
    </div>

    <ul class="nav nav-tabs attp-management-tabs mb-4">
        <li class="nav-item"><span class="nav-link active" aria-current="page">Partner Runtime Overview</span></li>
        @can('consortiums.view')
            <li class="nav-item"><a class="nav-link" href="{{ route('consortium-operations.index') }}">Consortium Visibility</a></li>
        @else
            <li class="nav-item"><span class="nav-link disabled">Consortium Visibility</span></li>
        @endcan
        <li class="nav-item"><a class="nav-link" href="#funds-risks">Funds & Risks</a></li>
    </ul>

    <div class="row g-3 mb-4" id="funds-risks">
        @foreach ([
            'Consortia' => $summary['consortia'],
            'Reports Submitted' => $summary['submitted_reports'],
            'Funds Disbursed' => number_format($summary['funds_disbursed'], 2),
            'Open Risks' => $summary['open_risks'],
            'Research Outputs' => $summary['research_outputs'],
            'Procurement Plans' => $summary['procurement_plans'],
            'Opportunities' => $summary['procurement_opportunities'],
            'Applications' => $summary['procurement_applications'],
        ] as $label => $value)
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $label }}</div>
                        <h3 class="mb-0">{{ is_numeric($value) ? number_format($value) : $value }}</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead style="background:#e2e8f0;color:#0f172a;">
                        <tr><th>Consortium</th><th>Think Tanks</th><th>Total Distributed</th><th>Spent</th><th>Reports</th><th>Research</th><th>Procurement</th><th>Risks</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($consortia as $consortium)
                        <tr>
                            <td><strong>{{ $consortium->name }}</strong><br><span class="text-muted small">{{ $consortium->country }}</span></td>
                            <td>{{ $consortium->members->count() }}</td>
                            <td>USD {{ number_format($consortium->fundAllocations->sum('amount_disbursed'), 2) }}</td>
                            <td>USD {{ number_format($consortium->fundAllocations->sum('amount_spent'), 2) }}</td>
                            <td>{{ $consortium->activityReports->where('status', 'submitted')->count() }}</td>
                            <td>{{ $consortium->researchOutputs->count() }}</td>
                            <td>{{ $consortium->procurements->count() }} / {{ $consortium->procurements->sum(fn($procurement) => $procurement->submissions->count()) }} applications</td>
                            <td><span class="badge {{ $consortium->riskFlags->count() ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">{{ $consortium->riskFlags->count() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No consortium records available.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>
@endsection
