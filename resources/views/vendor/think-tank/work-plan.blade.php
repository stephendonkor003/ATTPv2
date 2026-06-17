@extends('layouts.vendor')

@section('title', 'Work Plan')

@section('content')
    @php
        use Illuminate\Support\Str;
        $currency = $member->consortium?->currency ?? 'USD';
    @endphp

    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">Think Tank Module</div>
            <h3 class="vendor-page-title">Work Plan</h3>
            <p class="text-muted mb-0">
                {{ $member->name }}{{ $member->consortium ? ' - ' . $member->consortium->name : '' }}
            </p>
        </div>
        <a href="{{ route('vendor.research-report.index') }}" class="btn btn-vendor-outline">
            <i class="feather-book-open me-1"></i> Research Report
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card vendor-card h-100">
                <div class="card-body vendor-metric">
                    <div>
                        <div class="vendor-metric-label">Work Plans</div>
                        <div class="vendor-metric-value">{{ number_format($stats['workplans']) }}</div>
                        <div class="text-muted small">{{ number_format($stats['approved']) }} approved</div>
                    </div>
                    <span class="vendor-metric-icon"><i class="feather-calendar"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vendor-card h-100">
                <div class="card-body vendor-metric">
                    <div>
                        <div class="vendor-metric-label">Planned Budget</div>
                        <div class="vendor-metric-value fs-5">{{ $currency }} {{ number_format($stats['planned_budget'], 2) }}</div>
                        <div class="text-muted small">Across visible work plans</div>
                    </div>
                    <span class="vendor-metric-icon"><i class="feather-pie-chart"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vendor-card h-100">
                <div class="card-body vendor-metric">
                    <div>
                        <div class="vendor-metric-label">Reports</div>
                        <div class="vendor-metric-value">{{ number_format($stats['reports']) }}</div>
                        <div class="text-muted small">{{ number_format($stats['average_progress'], 1) }}% average progress</div>
                    </div>
                    <span class="vendor-metric-icon"><i class="feather-clipboard"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vendor-card h-100">
                <div class="card-body vendor-metric">
                    <div>
                        <div class="vendor-metric-label">Funds Spent</div>
                        <div class="vendor-metric-value fs-5">{{ $currency }} {{ number_format($stats['funds_spent'], 2) }}</div>
                        <div class="text-muted small">Reported by this think tank</div>
                    </div>
                    <span class="vendor-metric-icon"><i class="feather-dollar-sign"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <div class="vendor-eyebrow">Consortium Workplans</div>
                            <h5 class="mb-0">Assigned Work Plan Records</h5>
                        </div>
                        <span class="badge-soft">{{ number_format($workplans->count()) }} record(s)</span>
                    </div>

                    @if ($workplans->isEmpty())
                        <div class="vendor-empty">
                            <span class="vendor-empty-icon mb-3"><i class="feather-calendar"></i></span>
                            <h5>No work plan found</h5>
                            <p class="text-muted mb-0">Once ATTP links a work plan to your consortium, it will appear here.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Work Plan</th>
                                        <th>Period</th>
                                        <th class="text-end">Budget</th>
                                        <th>Status</th>
                                        <th class="text-center">Reports</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($workplans as $workplan)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $workplan->title }}</div>
                                                <div class="text-muted small text-truncate" style="max-width: 360px;">
                                                    {{ Str::limit($workplan->objectives ?: 'No objectives recorded.', 120) }}
                                                </div>
                                            </td>
                                            <td>
                                                {{ $workplan->period_label ?: (($workplan->starts_on?->format('M d, Y') ?? 'N/A') . ' - ' . ($workplan->ends_on?->format('M d, Y') ?? 'N/A')) }}
                                            </td>
                                            <td class="text-end">{{ $currency }} {{ number_format((float) $workplan->planned_budget, 2) }}</td>
                                            <td><span class="status-pill">{{ Str::headline($workplan->status ?? 'draft') }}</span></td>
                                            <td class="text-center">{{ number_format($workplan->reports->count()) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="vendor-eyebrow">Recent Reporting</div>
                    <h5 class="mb-3">Work Plan Updates</h5>

                    @forelse ($reports->take(8) as $report)
                        <div class="vendor-file-row mb-2">
                            <span class="vendor-metric-icon"><i class="feather-clipboard"></i></span>
                            <span class="min-w-0">
                                <strong class="text-truncate d-block">{{ $report->title }}</strong>
                                <small>
                                    {{ $report->workplan?->title ?? 'No work plan selected' }} |
                                    {{ Str::headline($report->status ?? 'submitted') }} |
                                    {{ number_format((float) $report->progress_percent, 1) }}%
                                </small>
                            </span>
                        </div>
                    @empty
                        <div class="vendor-empty">
                            <span class="vendor-empty-icon mb-3"><i class="feather-clipboard"></i></span>
                            <p class="text-muted mb-0">No work plan reports submitted yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
