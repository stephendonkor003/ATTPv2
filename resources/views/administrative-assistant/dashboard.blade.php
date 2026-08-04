@extends('layouts.administrative-assistant')

@section('title', 'Upload Centre')

@push('styles')
<style>
    .aa-stat { height: 100%; padding: 18px; }
    .aa-stat-icon { width: 42px; height: 42px; display: grid; place-items: center; flex: 0 0 42px; border-radius: 12px; background: var(--aa-mint); color: var(--aa-teal); font-size: 1.1rem; }
    .aa-stat-value { color: var(--aa-navy); font-size: 1.65rem; line-height: 1; font-weight: 850; }
    .folder-card { position: relative; display: block; min-height: 248px; padding: 28px 24px 22px; overflow: hidden; color: inherit; text-decoration: none; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .folder-card::before { content: ''; position: absolute; top: 0; left: 23px; width: 92px; height: 10px; border-radius: 0 0 8px 8px; background: #e7b83f; }
    .folder-card:hover { color: inherit; transform: translateY(-4px); border-color: #e5bd51; box-shadow: 0 22px 48px rgba(16,35,63,.14); }
    .folder-icon { width: 63px; height: 53px; display: grid; place-items: center; border-radius: 9px 13px 13px 13px; background: linear-gradient(145deg,#f4c958,#e5aa29); color: #6d4b04; font-size: 1.65rem; box-shadow: inset 0 1px rgba(255,255,255,.5), 0 10px 22px rgba(191,137,18,.2); }
    .folder-year { color: var(--aa-navy); font-size: 2rem; font-weight: 900; letter-spacing: -.04em; }
    .folder-metrics { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 8px; }
    .folder-metric { padding: 9px; border-radius: 11px; background: #f7f9fc; text-align: center; }
    .folder-metric strong { display: block; color: var(--aa-navy); font-size: 1.05rem; }
    .folder-metric span { color: #748196; font-size: .68rem; }
    .progress-thin { height: 7px; border-radius: 99px; background: #edf1f5; overflow: hidden; }
    .progress-thin > span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg,var(--aa-teal),#29b89f); }
    .month-card { position: relative; display: block; min-height: 220px; padding: 20px; color: inherit; text-decoration: none; overflow: hidden; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .month-card:hover { color: inherit; transform: translateY(-3px); border-color: #86cfc2; box-shadow: 0 20px 42px rgba(16,35,63,.12); }
    .month-number { width: 58px; height: 58px; display: grid; place-items: center; border-radius: 16px; color: #fff; background: linear-gradient(145deg,var(--aa-navy),var(--aa-teal)); font-size: 1.35rem; font-weight: 900; box-shadow: 0 10px 24px rgba(8,127,115,.18); }
    .month-name { color: var(--aa-navy); font-size: 1.25rem; font-weight: 850; }
    .vendor-stack { display: flex; align-items: center; min-height: 31px; }
    .vendor-dot { width: 31px; height: 31px; display: grid; place-items: center; margin-left: -7px; border: 2px solid #fff; border-radius: 50%; background: #dff5ef; color: #087368; font-size: .65rem; font-weight: 850; }
    .vendor-dot:first-child { margin-left: 0; }
    .aa-breadcrumbs { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; margin-bottom: 17px; font-size: .82rem; }
    .aa-breadcrumbs a { color: #537083; text-decoration: none; font-weight: 700; }
    .aa-breadcrumbs .current { color: var(--aa-navy); font-weight: 800; }
    .aa-filter { padding: 18px; }
    .aa-tabs { display: flex; gap: 7px; overflow-x: auto; padding-bottom: 3px; }
    .aa-tab { white-space: nowrap; padding: 8px 13px; border: 1px solid var(--aa-border); border-radius: 999px; background: #fff; color: #56657a; text-decoration: none; font-size: .82rem; font-weight: 750; }
    .aa-tab.active { background: var(--aa-navy); border-color: var(--aa-navy); color: #fff; }
    .vendor-card { overflow: hidden; }
    .vendor-card-header { padding: 20px 22px; background: linear-gradient(115deg,#f7fbfa,#f7f9fc); border-bottom: 1px solid var(--aa-border); }
    .vendor-avatar { width: 52px; height: 52px; display: grid; place-items: center; flex: 0 0 52px; border-radius: 15px; background: linear-gradient(145deg,var(--aa-navy),var(--aa-teal)); color: #fff; font-size: 1rem; font-weight: 900; }
    .vendor-name { color: var(--aa-navy); font-size: 1.15rem; font-weight: 850; }
    .vendor-summary { display: flex; flex-wrap: wrap; gap: 7px; }
    .summary-pill { padding: 6px 10px; border-radius: 999px; background: #fff; border: 1px solid var(--aa-border); color: #657387; font-size: .72rem; font-weight: 750; }
    .deliverable-list { padding: 8px 20px 18px; }
    .deliverable-row { display: grid; grid-template-columns: 70px minmax(0,1fr) 145px 190px; align-items: center; gap: 14px; padding: 16px 0; border-bottom: 1px solid #e8edf3; }
    .deliverable-row:last-child { border-bottom: 0; }
    .due-tile { padding: 9px 5px; text-align: center; border-radius: 12px; background: #f3f6f9; }
    .due-tile strong { display: block; color: var(--aa-navy); font-size: 1.15rem; line-height: 1; }
    .due-tile span { color: #748196; font-size: .66rem; text-transform: uppercase; font-weight: 800; }
    .deliverable-title { color: var(--aa-navy); font-weight: 800; }
    .deliverable-ref { color: #7a8797; font-size: .72rem; }
    .deliverable-data { font-size: .75rem; }
    .deliverable-data small { display: block; color: #7a8797; }
    .badge-overdue { background: #feeceb; color: #ae2f2a; }
    .badge-due_soon { background: #fff5dc; color: #8a6110; }
    .badge-upcoming { background: #eaf3ff; color: #2762a8; }
    .badge-uploaded { background: #e5f8ef; color: #08734e; }
    @media (max-width: 991.98px) {
        .deliverable-row { grid-template-columns: 62px minmax(0,1fr) 150px; }
        .deliverable-data { display: none; }
    }
    @media (max-width: 575.98px) {
        .folder-card { min-height: 225px; }
        .deliverable-row { grid-template-columns: 54px minmax(0,1fr); }
        .deliverable-action { grid-column: 1 / -1; }
        .deliverable-action .btn { width: 100%; }
        .vendor-card-header { padding: 17px; }
        .deliverable-list { padding: 6px 16px 16px; }
    }
</style>
@endpush

@section('content')
    @php
        $monthLabel = $selectedMonth ? \Carbon\Carbon::create(null, $selectedMonth, 1)->format('F') : null;
        $contextRows = $selectedMonth ? $monthRows : ($selectedYear ? $allRows->filter(fn ($row) => (int) ($row->due_date?->year ?: $row->purchase_request->start_year) === $selectedYear) : $allRows);
    @endphp

    @if ($selectedYear)
        <nav class="aa-breadcrumbs" aria-label="Folder path">
            <a href="{{ route('administrative-assistant.dashboard') }}"><i class="feather-folder me-1"></i>All years</a>
            <i class="feather-chevron-right text-muted"></i>
            @if ($selectedMonth)
                <a href="{{ route('administrative-assistant.dashboard', ['year' => $selectedYear]) }}">{{ $selectedYear }}</a>
                <i class="feather-chevron-right text-muted"></i>
                <span class="current">{{ $monthLabel }}</span>
            @else
                <span class="current">{{ $selectedYear }}</span>
            @endif
        </nav>
    @endif

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
        <div>
            <div class="aa-topbar-kicker mb-2">
                @if (! $selectedYear) Step 1 of 3 · Choose a year
                @elseif (! $selectedMonth) Step 2 of 3 · Choose a month
                @else Step 3 of 3 · Choose a vendor deliverable
                @endif
            </div>
            <h1 class="aa-page-title mb-2">
                @if (! $selectedYear) Upload centre
                @elseif (! $selectedMonth) {{ $selectedYear }} monthly folders
                @else {{ $monthLabel }} {{ $selectedYear }} vendors
                @endif
            </h1>
            <p class="text-muted mb-0">
                @if (! $selectedYear) Open a year folder to find its monthly invoices and evidence.
                @elseif (! $selectedMonth) Open a month to see its vendors and their documents.
                @else Each vendor has one clear card containing all deliverables due this month.
                @endif
            </p>
        </div>
        @if ($selectedYear)
            <a href="{{ $selectedMonth ? route('administrative-assistant.dashboard', ['year' => $selectedYear]) : route('administrative-assistant.dashboard') }}" class="btn btn-light border">
                <i class="feather-arrow-left me-1"></i> {{ $selectedMonth ? 'Back to months' : 'Back to years' }}
            </a>
        @endif
    </div>

    @if (! $selectedYear)
        <div class="row g-3 mb-4">
            @foreach ([
                ['label' => 'Waiting for upload', 'value' => $stats['outstanding'], 'icon' => 'upload-cloud'],
                ['label' => 'Overdue', 'value' => $stats['overdue'], 'icon' => 'alert-circle'],
                ['label' => 'Due this month', 'value' => $stats['due_this_month'], 'icon' => 'calendar'],
                ['label' => 'Uploaded', 'value' => $stats['uploaded'], 'icon' => 'check-circle'],
            ] as $stat)
                <div class="col-6 col-xl-3">
                    <div class="aa-card aa-stat d-flex align-items-center gap-3">
                        <span class="aa-stat-icon"><i class="feather-{{ $stat['icon'] }}"></i></span>
                        <div>
                            <div class="aa-stat-value">{{ number_format($stat['value']) }}</div>
                            <div class="small text-muted mt-1">{{ $stat['label'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($years->isEmpty())
            <div class="aa-card text-center py-5 px-3">
                <span class="aa-stat-icon mx-auto mb-3"><i class="feather-folder"></i></span>
                <h4 class="fw-bold">No year folders yet</h4>
                <p class="text-muted mb-0">Folders appear automatically when a vendor purchase order has monthly deliverables.</p>
            </div>
        @else
            <div class="row g-4">
                @foreach ($years as $year)
                    <div class="col-md-6 col-xl-4">
                        <a href="{{ route('administrative-assistant.dashboard', ['year' => $year->year]) }}" class="aa-card folder-card">
                            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                <span class="folder-icon"><i class="feather-folder"></i></span>
                                @if ($year->outstanding_count)
                                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill">{{ $year->outstanding_count }} waiting</span>
                                @else
                                    <span class="badge bg-success-subtle text-success rounded-pill">Complete</span>
                                @endif
                            </div>
                            <div class="folder-year">{{ $year->year }}</div>
                            <div class="small text-muted mb-3">Invoices and deliverable evidence</div>
                            <div class="folder-metrics mb-3">
                                <div class="folder-metric"><strong>{{ $year->month_count }}</strong><span>Months</span></div>
                                <div class="folder-metric"><strong>{{ $year->vendor_count }}</strong><span>Vendors</span></div>
                                <div class="folder-metric"><strong>{{ $year->task_count }}</strong><span>Items</span></div>
                            </div>
                            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Uploaded</span><strong>{{ $year->progress }}%</strong></div>
                            <div class="progress-thin"><span style="width: {{ $year->progress }}%"></span></div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    @elseif (! $selectedMonth)
        <div class="row g-3 mb-4">
            @foreach ([
                ['label' => 'Months with work', 'value' => $months->count(), 'icon' => 'calendar'],
                ['label' => 'Vendors', 'value' => $contextRows->pluck('purchase_order.vendor_id')->filter()->unique()->count(), 'icon' => 'briefcase'],
                ['label' => 'Waiting for upload', 'value' => $contextRows->where('has_documents', false)->count(), 'icon' => 'upload-cloud'],
                ['label' => 'Uploaded', 'value' => $contextRows->where('has_documents', true)->count(), 'icon' => 'check-circle'],
            ] as $stat)
                <div class="col-6 col-xl-3">
                    <div class="aa-card aa-stat d-flex align-items-center gap-3">
                        <span class="aa-stat-icon"><i class="feather-{{ $stat['icon'] }}"></i></span>
                        <div><div class="aa-stat-value">{{ number_format($stat['value']) }}</div><div class="small text-muted mt-1">{{ $stat['label'] }}</div></div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($months->isEmpty())
            <div class="aa-card text-center py-5 px-3"><h4 class="fw-bold">No monthly folders in {{ $selectedYear }}</h4></div>
        @else
            <div class="row g-4">
                @foreach ($months as $month)
                    <div class="col-sm-6 col-xl-4">
                        <a href="{{ route('administrative-assistant.dashboard', ['year' => $selectedYear, 'month' => $month->month]) }}" class="aa-card month-card">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <span class="month-number">{{ str_pad((string) $month->month, 2, '0', STR_PAD_LEFT) }}</span>
                                @if ($month->outstanding_count)
                                    <span class="badge {{ $month->overdue_count ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning-emphasis' }} rounded-pill">
                                        {{ $month->outstanding_count }} waiting
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success rounded-pill">All uploaded</span>
                                @endif
                            </div>
                            <div class="month-name">{{ $month->name }}</div>
                            <div class="text-muted small mb-3">{{ $month->task_count }} deliverable item(s)</div>
                            <div class="d-flex justify-content-between align-items-center gap-3 mt-auto">
                                <div class="vendor-stack" title="{{ $month->vendor_names->join(', ') }}">
                                    @foreach ($month->vendor_names->take(4) as $vendorName)
                                        <span class="vendor-dot">{{ \Illuminate\Support\Str::of($vendorName)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('') }}</span>
                                    @endforeach
                                    @if ($month->vendor_count > 4)<span class="vendor-dot">+{{ $month->vendor_count - 4 }}</span>@endif
                                </div>
                                <div class="text-end"><strong>{{ $month->vendor_count }}</strong><div class="small text-muted">vendor(s)</div></div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        <div class="row g-3 mb-4">
            @foreach ([
                ['label' => 'Vendors', 'value' => $monthRows->pluck('purchase_order.vendor_id')->filter()->unique()->count(), 'icon' => 'briefcase'],
                ['label' => 'Deliverables', 'value' => $monthRows->count(), 'icon' => 'file-text'],
                ['label' => 'Waiting for upload', 'value' => $monthRows->where('has_documents', false)->count(), 'icon' => 'upload-cloud'],
                ['label' => 'Uploaded', 'value' => $monthRows->where('has_documents', true)->count(), 'icon' => 'check-circle'],
            ] as $stat)
                <div class="col-6 col-xl-3">
                    <div class="aa-card aa-stat d-flex align-items-center gap-3">
                        <span class="aa-stat-icon"><i class="feather-{{ $stat['icon'] }}"></i></span>
                        <div><div class="aa-stat-value">{{ number_format($stat['value']) }}</div><div class="small text-muted mt-1">{{ $stat['label'] }}</div></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="aa-card aa-filter mb-4">
            <form method="GET" action="{{ route('administrative-assistant.dashboard') }}" class="row g-3 align-items-end">
                <input type="hidden" name="year" value="{{ $selectedYear }}">
                <input type="hidden" name="month" value="{{ $selectedMonth }}">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="col-lg-6">
                    <label class="form-label fw-bold small">Find a vendor or deliverable</label>
                    <div class="input-group"><span class="input-group-text bg-white"><i class="feather-search"></i></span><input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Vendor, PR, PO, invoice or deliverable"></div>
                </div>
                <div class="col-lg-4">
                    <label class="form-label fw-bold small">Vendor</label>
                    <select name="vendor" class="form-select">
                        <option value="">All vendors</option>
                        @foreach ($vendors as $vendor)<option value="{{ $vendor['id'] }}" @selected($vendorId === (string) $vendor['id'])>{{ $vendor['name'] }}</option>@endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-grid"><button class="btn btn-aa"><i class="feather-filter me-1"></i> Show</button></div>
            </form>
            <div class="aa-tabs mt-3">
                @foreach (['all' => 'All', 'outstanding' => 'To upload', 'overdue' => 'Overdue', 'due_soon' => 'Due soon', 'uploaded' => 'Uploaded'] as $key => $label)
                    <a class="aa-tab {{ $status === $key ? 'active' : '' }}" href="{{ route('administrative-assistant.dashboard', array_filter(['year' => $selectedYear, 'month' => $selectedMonth, 'status' => $key, 'q' => $search, 'vendor' => $vendorId])) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @if ($vendorCards->isEmpty())
            <div class="aa-card text-center py-5 px-3">
                <span class="aa-stat-icon mx-auto mb-3"><i class="feather-search"></i></span>
                <h4 class="fw-bold">No vendor cards match</h4>
                <p class="text-muted mb-3">Clear the filters to see every vendor for {{ $monthLabel }}.</p>
                <a href="{{ route('administrative-assistant.dashboard', ['year' => $selectedYear, 'month' => $selectedMonth]) }}" class="btn btn-aa-soft">Clear filters</a>
            </div>
        @else
            <div class="d-grid gap-4">
                @foreach ($vendorCards as $vendorCard)
                    @php
                        $initials = \Illuminate\Support\Str::of($vendorCard->vendor_name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
                    @endphp
                    <article class="aa-card vendor-card">
                        <div class="vendor-card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <span class="vendor-avatar">{{ $initials ?: 'V' }}</span>
                                <div class="min-w-0"><div class="vendor-name text-truncate">{{ $vendorCard->vendor_name }}</div><div class="small text-muted text-truncate">{{ $vendorCard->vendor?->email ?? 'Vendor account' }}</div></div>
                            </div>
                            <div class="vendor-summary">
                                <span class="summary-pill"><strong>{{ $vendorCard->task_count }}</strong> item(s)</span>
                                <span class="summary-pill text-success"><strong>{{ $vendorCard->uploaded_count }}</strong> uploaded</span>
                                @if ($vendorCard->outstanding_count)<span class="summary-pill text-warning"><strong>{{ $vendorCard->outstanding_count }}</strong> waiting</span>@endif
                                @if ($vendorCard->overdue_count)<span class="summary-pill text-danger"><strong>{{ $vendorCard->overdue_count }}</strong> overdue</span>@endif
                            </div>
                        </div>

                        <div class="deliverable-list">
                            @foreach ($vendorCard->rows as $row)
                                <div class="deliverable-row">
                                    <div class="due-tile">
                                        <strong>{{ $row->due_date?->format('d') ?? '--' }}</strong>
                                        <span>{{ $row->due_date?->format('M') ?? 'No date' }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1"><span class="badge badge-{{ $row->status }} rounded-pill">{{ match($row->status) { 'due_soon' => 'Due soon', 'uploaded' => 'Uploaded', 'overdue' => 'Overdue', default => 'Upcoming' } }}</span>@if($row->document_count)<span class="small text-muted"><i class="feather-paperclip me-1"></i>{{ $row->document_count }} file(s)</span>@endif</div>
                                        <div class="deliverable-title text-truncate">{{ $row->title }}</div>
                                        <div class="deliverable-ref">PR {{ $row->purchase_request->reference_no ?? 'N/A' }} · PO {{ $row->purchase_order->reference_no ?? 'N/A' }}</div>
                                    </div>
                                    <div class="deliverable-data">
                                        <small>Amount</small><strong>{{ $row->purchase_order->resolved_currency }} {{ number_format((float) $row->item->amount, 2) }}</strong>
                                        <small class="mt-1">Invoice</small><strong>{{ $row->invoice?->reference_no ?? 'Not uploaded' }}</strong>
                                    </div>
                                    <div class="deliverable-action">
                                        <a href="{{ route('administrative-assistant.evidence.show', [$row->purchase_order, $row->item, 'year' => $selectedYear, 'month' => $selectedMonth]) }}" class="btn {{ $row->has_documents ? 'btn-aa-soft' : 'btn-aa' }} w-100">
                                            <i class="feather-{{ $row->has_documents ? 'eye' : 'upload-cloud' }} me-1"></i>{{ $row->has_documents ? 'View / add files' : 'Upload documents' }}
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endif
@endsection
