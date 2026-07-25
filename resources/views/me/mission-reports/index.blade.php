@extends('layouts.app')

@section('title', 'Mission Reports')
@section('lean_admin_scripts', '1')

@section('content')
<div class="container-fluid py-4">
    <div class="p-4 rounded-4 text-white mb-4" style="background:linear-gradient(120deg,#073f30,#0b6d50)">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="small text-uppercase fw-bold opacity-75">Monitoring &amp; Evaluation</div>
                <h2 class="text-white fw-bold mb-1">Mission Reports</h2>
                <p class="mb-0 opacity-75">Standardized monitoring, technical-supervision and results-verification mission records.</p>
            </div>
            @can('me.mission_reports.manage')
                <a href="{{ route('budget.me.mission-reports.create') }}" class="btn btn-light fw-bold">
                    <i class="feather-plus me-1"></i>New Mission Report
                </a>
            @endcan
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row g-3 mb-4">
        @foreach(['draft'=>'Draft / Returned','submitted'=>'Submitted','reviewed'=>'Approved','archived'=>'Archived'] as $key=>$label)
            <div class="col-6 col-xl-3">
                <a href="{{ route('budget.me.mission-reports.index',['status'=>$key]) }}" class="card h-100 text-decoration-none border-0 shadow-sm">
                    <div class="card-body"><div class="text-muted small text-uppercase fw-bold">{{ $label }}</div><div class="display-6 fw-bold text-dark">{{ (int)($counts[$key] ?? 0) }}</div></div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Mission register</h5>
            @if($status)<a href="{{ route('budget.me.mission-reports.index') }}" class="btn btn-sm btn-outline-secondary">Clear status filter</a>@endif
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Report</th><th>Template</th><th>Portfolio / Component</th><th>Mission dates</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td><strong>{{ $report->title }}</strong><div class="small text-muted">{{ $report->report_number }}</div></td>
                        <td>{{ $report->template?->name }}</td>
                        <td>{{ $report->portfolio?->name }}<div class="small text-muted">{{ $report->projectComponent?->name }}</div></td>
                        <td>{{ $report->mission_start_date?->format('d M Y') }} – {{ $report->mission_end_date?->format('d M Y') }}</td>
                        <td><span class="badge {{ $report->isReviewed() ? 'bg-success' : ($report->isSubmitted() ? 'bg-primary' : ($report->isArchived() ? 'bg-dark' : 'bg-warning text-dark')) }}">{{ $report->statusLabel() }}</span></td>
                        <td class="text-end"><a href="{{ route('budget.me.mission-reports.edit',$report) }}" class="btn btn-sm btn-outline-success">{{ $report->isEditable() ? 'Open / Edit' : 'View' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No mission reports match this view.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())<div class="card-footer bg-white">{{ $reports->links() }}</div>@endif
    </div>
</div>
@endsection
