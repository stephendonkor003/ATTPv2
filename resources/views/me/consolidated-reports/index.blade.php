@extends('layouts.app')

@section('title', 'ATTP Consolidated M&E Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 mb-4">
        <div><div class="text-muted small fw-semibold text-uppercase">Monitoring &amp; Evaluation</div><h3 class="mb-1">Think Tank Submissions &amp; Consolidated Report</h3><p class="text-muted mb-0">Review each organization separately and consolidate only finally approved data using each indicator’s authorized roll-up method.</p></div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-success" href="{{ route('budget.me.consolidated-reports.excel', request()->query()) }}"><i class="feather-file-text me-1"></i> Excel</a>
            <a class="btn btn-outline-danger" href="{{ route('budget.me.consolidated-reports.pdf', request()->query()) }}"><i class="feather-download me-1"></i> PDF</a>
        </div>
    </div>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card border-0 shadow-sm mb-4"><div class="card-body">
        <form method="GET" class="row g-3 align-items-end" id="consolidated-filter">
            <div class="col-md-2"><label class="form-label">Year</label><input type="number" name="reporting_year" value="{{ $filters['year'] }}" min="2000" max="2100" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Frequency</label><select name="reporting_period_type" id="consolidated-period-type" class="form-select">@foreach($periodTypes as $value=>$label)<option value="{{ $value }}" @selected($filters['period_type']===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Period</label><select name="reporting_period_label" id="consolidated-period-label" class="form-select"></select></div>
            <div class="col-md-3"><label class="form-label">Portfolio</label><select name="portfolio_id" class="form-select"><option value="">All portfolios</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected($filters['portfolio_id']===$portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Apply</button></div>
        </form>
    </div></div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Active reporting organizations</div><div class="fs-3 fw-bold">{{ number_format($thinkTanks->count()) }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Submissions in selected period</div><div class="fs-3 fw-bold">{{ number_format($reports->count()) }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Approved / archived inputs</div><div class="fs-3 fw-bold text-success">{{ number_format($approvedReports->count()) }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Consolidated indicators</div><div class="fs-3 fw-bold text-primary">{{ number_format($consolidated->count()) }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3"><h5 class="mb-0">Organization submission register</h5><div class="text-muted small">Every active think tank is listed, including organizations with no submission.</div></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Think tank / partner</th><th>Country</th><th>Submission(s)</th><th>Current stage</th><th class="text-end">Action</th></tr></thead><tbody>
        @foreach($thinkTanks as $thinkTank)
            @php($organizationReports = $reports->where('think_tank_member_id', $thinkTank->id))
            <tr><td><div class="fw-semibold">{{ $thinkTank->name }}</div><div class="text-muted small">{{ \Illuminate\Support\Str::headline($thinkTank->role ?: 'think tank') }}</div></td><td>{{ $thinkTank->country ?: 'Not recorded' }}</td><td>{{ $organizationReports->count() }} report(s)@foreach($organizationReports as $organizationReport)<div class="small text-muted">{{ $organizationReport->form?->code }} &middot; {{ $organizationReport->indicatorResults->count() }} indicators</div>@endforeach</td><td>@forelse($organizationReports as $organizationReport)<span class="badge me-1 {{ in_array($organizationReport->status,['approved','archived']) ? 'bg-success' : ($organizationReport->status==='verified' ? 'bg-info text-dark' : ($organizationReport->status==='submitted' ? 'bg-primary' : 'bg-warning text-dark')) }}">{{ $organizationReport->lifecycleLabel() }}</span>@empty<span class="badge bg-light text-danger border">No submission</span>@endforelse</td><td class="text-end">@foreach($organizationReports as $organizationReport)<a href="{{ route('budget.me.performance-reports.edit',$organizationReport) }}" class="btn btn-sm btn-light border mb-1">View {{ $organizationReport->form?->code }}</a>@endforeach</td></tr>
        @endforeach
        </tbody></table></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><h5 class="mb-0">Approved consolidated indicator performance</h5><div class="text-muted small">Draft, submitted and merely verified reports are deliberately excluded.</div></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Indicator</th><th>Roll-up control</th><th class="text-end">Consolidated result</th><th class="text-end">Organizations</th><th class="text-end">Achievements</th><th class="text-end">Beneficiaries</th><th>Disaggregation snapshot</th></tr></thead><tbody>
        @forelse($consolidated as $row)
            <tr><td><div class="text-primary small fw-bold">{{ $row['indicator']?->indicator_code }}</div><div class="fw-semibold">{{ $row['indicator']?->name }}</div></td><td>{{ $row['rollup_label'] }}@if($row['duplicate_result_count'] > 0)<div class="small text-warning fw-semibold"><i class="feather-alert-triangle me-1"></i>{{ $row['duplicate_result_count'] }} overlapping approved result(s) suppressed</div>@endif</td><td class="text-end fw-bold">{{ $row['value'] !== null ? number_format($row['value'],2) : 'Not numerically additive' }} @if($row['indicator']?->unit)<span class="text-muted small">{{ $row['indicator']->unit->symbol ?: $row['indicator']->unit->name }}</span>@endif</td><td class="text-end">{{ number_format($row['organization_count']) }}</td><td class="text-end">{{ number_format($row['achievement_count']) }}</td><td class="text-end">{{ number_format($row['beneficiary_count']) }}</td><td><div class="small"><strong>Gender:</strong> F {{ number_format($row['gender']->get('female',0)) }}, M {{ number_format($row['gender']->get('male',0)) }}</div><div class="small"><strong>Age:</strong> youth {{ number_format($row['age_groups']->get('youth_below_35',0)) }}, adult {{ number_format($row['age_groups']->get('adult_35_plus',0)) }}</div><div class="small"><strong>Stakeholders:</strong> {{ $row['stakeholders']->keys()->take(3)->map(fn($value) => \Illuminate\Support\Str::headline($value))->join(', ') ?: 'Not recorded' }}</div><div class="small"><strong>Themes:</strong> {{ $row['themes']->keys()->take(3)->map(fn($value) => \Illuminate\Support\Str::headline($value))->join(', ') ?: 'Not recorded' }}</div><div class="small text-muted">{{ $row['countries']->keys()->take(4)->join(', ') ?: 'No country recorded' }}@if($row['recs']->isNotEmpty()) &middot; {{ $row['recs']->keys()->map(fn($value) => strtoupper($value))->join(', ') }}@endif</div></td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-5">No finally approved think-tank data is available for this period.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const type=document.getElementById('consolidated-period-type'),label=document.getElementById('consolidated-period-label'),labels=@json($periodLabels),current=@json($filters['period_label']);const refresh=()=>{const selected=label.value||current;label.innerHTML='';Object.entries(labels[type.value]||{}).forEach(([v,t])=>label.add(new Option(t,v,false,v===selected)));};type.addEventListener('change',refresh);refresh();});
</script>
@endsection
