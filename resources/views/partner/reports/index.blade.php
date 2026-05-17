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

    <div class="mt-3">
        @include('partner.partials.funding-report', ['reportingOverview' => $reportingOverview, 'funder' => $funder])
    </div>
</div>
@endsection
