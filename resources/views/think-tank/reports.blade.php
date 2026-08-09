@php
    $currency = 'USD';
    $isAdminView = auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin();
    $reportAction = route('think-tank.reports.store', $portalRouteParams);
    $resetParams = $isAdminView ? ['think_tank_member_id' => $member->id] : [];
@endphp


<x-think-tank.partials.shell :member="$member" title="Activity Reports">
    <div class="tt-reports-shell">
        <section class="tt-report-search">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h2 class="tt-search-title">Activity Report Search</h2>
                    <p class="tt-search-subtitle">Select a think tank and run the search to generate its full reporting profile.</p>
                </div>
                <a class="btn btn-dark fw-bold" href="{{ route('think-tank.reports.download', $reportsQueryParams) }}">
                    <i class="feather-download me-1"></i> Download Report
                </a>
            </div>

            <form method="GET" action="{{ route('think-tank.reports') }}">
                <div class="tt-search-grid">
                    <div class="tt-field">
                        <label for="think_tank_member_id">Think tank</label>
                        @if($isAdminView)
                            <select id="think_tank_member_id" name="think_tank_member_id" required>
                                @foreach($membersForSearch as $searchMember)
                                    <option value="{{ $searchMember->id }}" @selected((string) $member->id === (string) $searchMember->id)>
                                        {{ $searchMember->name }}{{ $searchMember->consortium ? ' - ' . $searchMember->consortium->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input value="{{ $member->name }}" readonly>
                        @endif
                    </div>
                    <div class="tt-field">
                        <label for="filter_month">Month</label>
                        <input id="filter_month" type="month" name="filter_month" value="{{ $dashboardFilter['month'] }}">
                    </div>
                    <div class="tt-field">
                        <label for="filter_year">Year</label>
                        <select id="filter_year" name="filter_year">
                            <option value="">All years</option>
                            @foreach($dashboardFilter['year_options'] as $year)
                                <option value="{{ $year }}" @selected((string) $dashboardFilter['year'] === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="tt-field">
                        <label for="date_from">From</label>
                        <input id="date_from" type="date" name="date_from" value="{{ $dashboardFilter['date_from'] }}">
                    </div>
                    <div class="tt-field">
                        <label for="date_to">To</label>
                        <input id="date_to" type="date" name="date_to" value="{{ $dashboardFilter['date_to'] }}">
                    </div>
                    <div class="tt-field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All statuses</option>
                            @foreach(['submitted', 'approved', 'revisions_requested', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected($statusFilter === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="tt-search-actions">
                        <button class="btn btn-primary fw-bold" type="submit">
                            <i class="feather-search me-1"></i> Run Search
                        </button>
                        <a class="btn btn-light border fw-bold" href="{{ route('think-tank.reports', $resetParams) }}">Reset</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="tt-report-hero">
            <div class="tt-hero-grid">
                <div>
                    <span class="tt-kicker"><i class="feather-file-text"></i> {{ $dashboardFilter['label'] }} reporting profile</span>
                    <h1>{{ $member->name }}</h1>
                    <p class="mb-2">{{ $member->consortium?->name ?? 'Consortium not linked' }}{{ $member->country ? ' / ' . $member->country : '' }}</p>
                    <div class="tt-hero-meta">Reports, progress, spending, evidence, and review outcomes generated from the selected think tank records.</div>
                </div>
                <aside class="tt-deadline-box">
                    <div class="tt-deadline-label">Next monthly report deadline</div>
                    <div class="tt-deadline-number">{{ $monthlyReportDaysLeft >= 0 ? $monthlyReportDaysLeft : abs($monthlyReportDaysLeft) }}</div>
                    <div>
                        @if($monthlyReportDaysLeft >= 0)
                            days left, due {{ $monthlyReportDue->format('M d, Y') }}.
                        @else
                            days overdue since {{ $monthlyReportDue->format('M d, Y') }}.
                        @endif
                    </div>
                </aside>
            </div>
        </section>

        <section class="tt-kpi-grid">
            <article class="tt-report-kpi">
                <span class="tt-kpi-icon"><i class="feather-file-text"></i></span>
                <div class="tt-kpi-value">{{ number_format($reportStats['total']) }}</div>
                <div class="tt-kpi-label">Reports in selected view</div>
            </article>
            <article class="tt-report-kpi green">
                <span class="tt-kpi-icon"><i class="feather-check-circle"></i></span>
                <div class="tt-kpi-value">{{ number_format($reportStats['approved']) }}</div>
                <div class="tt-kpi-label">Approved reports</div>
            </article>
            <article class="tt-report-kpi amber">
                <span class="tt-kpi-icon"><i class="feather-trending-up"></i></span>
                <div class="tt-kpi-value">{{ number_format($reportStats['average_progress'], 1) }}%</div>
                <div class="tt-kpi-label">Average reported progress</div>
            </article>
            <article class="tt-report-kpi teal">
                <span class="tt-kpi-icon"><i class="feather-dollar-sign"></i></span>
                <div class="tt-kpi-value">{{ $currency }} {{ number_format($reportStats['funds_spent'], 2) }}</div>
                <div class="tt-kpi-label">Funds reported spent</div>
            </article>
        </section>

        <section class="tt-main-grid">
            <div>
                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Graphs and Report Analysis</h2>
                            <p>Status, monthly submissions, progress, spending, and evidence coverage.</p>
                        </div>
                    </div>
                    <div class="tt-chart-grid">
                        <div class="tt-chart-box">
                            <h3>Report Status</h3>
                            <div id="ttReportStatusChart"></div>
                        </div>
                        <div class="tt-chart-box">
                            <h3>Submissions and Progress</h3>
                            <div id="ttReportTimelineChart"></div>
                        </div>
                        <div class="tt-chart-box">
                            <h3>Funds Spent by Month</h3>
                            <div id="ttReportFundsChart"></div>
                        </div>
                        <div class="tt-chart-box">
                            <h3>Evidence Coverage</h3>
                            <div id="ttReportEvidenceChart"></div>
                        </div>
                    </div>
                </div>

                <section class="tt-report-tabs" id="report-workspace">
                    <ul class="nav nav-tabs" id="ttReportTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab" aria-controls="history-pane" aria-selected="true">
                                <i class="feather-list me-1"></i> Report Register
                            </button>
                        </li>
                        @can('think_tank.reports.submit')
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="submit-tab" data-bs-toggle="tab" data-bs-target="#submit-pane" type="button" role="tab" aria-controls="submit-pane" aria-selected="false">
                                    <i class="feather-edit-3 me-1"></i> Submit Report
                                </button>
                            </li>
                        @endcan
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="guide-tab" data-bs-toggle="tab" data-bs-target="#guide-pane" type="button" role="tab" aria-controls="guide-pane" aria-selected="false">
                                <i class="feather-help-circle me-1"></i> Reporting Guide
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content tt-tab-body">
                        <div class="tab-pane fade show active" id="history-pane" role="tabpanel" aria-labelledby="history-tab" tabindex="0">
                            <div class="tt-table-wrap">
                                <table class="tt-table">
                                    <thead>
                                    <tr>
                                        <th>Report</th>
                                        <th>Period</th>
                                        <th>Progress</th>
                                        <th>Funds spent</th>
                                        <th>Evidence</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($reports as $report)
                                        @php
                                            $progress = min(100, max(0, (float) $report->progress_percent));
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $report->title }}</strong>
                                                <div class="text-muted small">{{ $report->workplan?->title ?? 'No workplan selected' }}</div>
                                            </td>
                                            <td>{{ $report->reporting_period_start?->format('M d') ?? 'N/A' }} - {{ $report->reporting_period_end?->format('M d, Y') ?? 'N/A' }}</td>
                                            <td>
                                                <strong>{{ number_format($progress, 1) }}%</strong>
                                                <div class="tt-progress"><span style="width: {{ $progress }}%"></span></div>
                                            </td>
                                            <td>{{ $currency }} {{ number_format((float) $report->funds_spent, 2) }}</td>
                                            <td>{{ number_format($report->evidence->count()) }}</td>
                                            <td><span class="tt-status {{ $report->status }}">{{ str_replace('_', ' ', $report->status) }}</span></td>
                                            <td>{{ $report->submitted_at?->format('d M Y') ?? $report->created_at?->format('d M Y') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7"><div class="tt-empty">No reports match the selected search.</div></td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">{{ $reports->links() }}</div>
                        </div>

                        @can('think_tank.reports.submit')
                            <div class="tab-pane fade" id="submit-pane" role="tabpanel" aria-labelledby="submit-tab" tabindex="0">
                                <form method="POST" action="{{ $reportAction }}" enctype="multipart/form-data">
                                    @csrf
                                    <div class="tt-form-grid">
                                        <div>
                                            <div class="tt-form-card">
                                                <h3>Report details</h3>
                                                <p>Identify the reporting period, workplan, progress, funds spent, and supporting evidence.</p>
                                                <div class="tt-field-grid">
                                                    <div class="tt-field full">
                                                        <label for="title">Report title</label>
                                                        <input id="title" name="title" value="{{ old('title') }}" placeholder="May 2026 implementation progress report" required>
                                                    </div>
                                                    <div class="tt-field">
                                                        <label for="workplan_id">Workplan</label>
                                                        <select id="workplan_id" name="workplan_id">
                                                            <option value="">Select workplan</option>
                                                            @foreach($workplans as $workplan)
                                                                <option value="{{ $workplan->id }}" @selected((string) old('workplan_id') === (string) $workplan->id)>{{ $workplan->title }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="tt-field">
                                                        <label for="progress_percent">Progress percentage</label>
                                                        <input id="progress_percent" type="number" min="0" max="100" step="0.01" name="progress_percent" value="{{ old('progress_percent') }}" placeholder="75">
                                                    </div>
                                                    <div class="tt-field">
                                                        <label for="reporting_period_start">Reporting period start</label>
                                                        <input id="reporting_period_start" type="date" name="reporting_period_start" value="{{ old('reporting_period_start', now()->startOfMonth()->toDateString()) }}">
                                                    </div>
                                                    <div class="tt-field">
                                                        <label for="reporting_period_end">Reporting period end</label>
                                                        <input id="reporting_period_end" type="date" name="reporting_period_end" value="{{ old('reporting_period_end', now()->endOfMonth()->toDateString()) }}">
                                                    </div>
                                                    <div class="tt-field">
                                                        <label for="funds_spent">Funds spent this period (USD)</label>
                                                        <input id="funds_spent" type="number" min="0" step="0.01" name="funds_spent" value="{{ old('funds_spent') }}" placeholder="0.00">
                                                    </div>
                                                    <div class="tt-field">
                                                        <label for="evidence_files">Evidence files</label>
                                                        <input id="evidence_files" type="file" name="evidence_files[]" multiple>
                                                    </div>
                                                    <div class="tt-field full">
                                                        <label for="evidence_title">Evidence group title</label>
                                                        <input id="evidence_title" name="evidence_title" value="{{ old('evidence_title') }}" placeholder="Attendance list, field photo pack, invoices, annexes">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="tt-form-card">
                                                <h3>Narrative update</h3>
                                                <p>Summarise implementation progress, achievements, challenges, and next steps.</p>
                                                <div class="tt-field-grid">
                                                    <div class="tt-field full">
                                                        <label for="summary">Summary</label>
                                                        <textarea id="summary" name="summary">{{ old('summary') }}</textarea>
                                                    </div>
                                                    <div class="tt-field">
                                                        <label for="achievements">Achievements</label>
                                                        <textarea id="achievements" name="achievements">{{ old('achievements') }}</textarea>
                                                    </div>
                                                    <div class="tt-field">
                                                        <label for="challenges">Challenges</label>
                                                        <textarea id="challenges" name="challenges">{{ old('challenges') }}</textarea>
                                                    </div>
                                                    <div class="tt-field full">
                                                        <label for="next_steps">Next steps</label>
                                                        <textarea id="next_steps" name="next_steps">{{ old('next_steps') }}</textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                <button type="reset" class="btn btn-light border">Clear form</button>
                                                <button type="submit" class="btn btn-primary"><i class="feather-send me-1"></i> Submit to Secretariat</button>
                                            </div>
                                        </div>

                                        <aside class="tt-side-note">
                                            <h3>Before submitting</h3>
                                            <p>Make sure the report can stand as an implementation record.</p>
                                            <ul class="tt-check-list">
                                                <li><i class="feather-check-circle me-1"></i> Use the correct reporting period.</li>
                                                <li><i class="feather-check-circle me-1"></i> Enter all funds in USD.</li>
                                                <li><i class="feather-check-circle me-1"></i> Attach evidence where available.</li>
                                                <li><i class="feather-check-circle me-1"></i> Explain challenges clearly for Secretariat follow-up.</li>
                                            </ul>
                                        </aside>
                                    </div>
                                </form>
                            </div>
                        @endcan

                        <div class="tab-pane fade" id="guide-pane" role="tabpanel" aria-labelledby="guide-tab" tabindex="0">
                            <div class="tt-chart-grid">
                                <div class="tt-form-card">
                                    <h3>What to report</h3>
                                    <p>Activities, milestones, stakeholder engagements, procurement updates, and research delivery.</p>
                                </div>
                                <div class="tt-form-card">
                                    <h3>Evidence to attach</h3>
                                    <p>Attendance sheets, signed minutes, invoices, photos, publications, or implementation annexes.</p>
                                </div>
                                <div class="tt-form-card">
                                    <h3>How review works</h3>
                                    <p>Submitted reports are visible to the ATTP Secretariat for approval, revision requests, and oversight.</p>
                                </div>
                                <div class="tt-form-card">
                                    <h3>Financial reporting</h3>
                                    <p>Funds spent should match the work completed in the reporting period and remain in USD.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside>
                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Review Status</h2>
                            <p>Current status split for the selected search.</p>
                        </div>
                    </div>
                    <div class="tt-status-list">
                        @forelse($statusCounts as $status => $count)
                            <div class="tt-status-row">
                                <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                        @empty
                            <div class="tt-empty">No status data yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Evidence Summary</h2>
                            <p>Supporting files attached to reports.</p>
                        </div>
                    </div>
                    <div class="tt-status-list">
                        <div class="tt-status-row"><span>Total evidence files</span><strong>{{ number_format($reportStats['evidence_count']) }}</strong></div>
                        <div class="tt-status-row"><span>Reports with evidence</span><strong>{{ number_format($reportStats['with_evidence']) }}</strong></div>
                        <div class="tt-status-row"><span>Reports without evidence</span><strong>{{ number_format($reportStats['without_evidence']) }}</strong></div>
                    </div>
                </div>

                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Quick Links</h2>
                            <p>Move between the reporting and dashboard views.</p>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <a class="btn btn-primary" href="#report-workspace"><i class="feather-edit-3 me-1"></i> Open report workspace</a>
                        <a class="btn btn-light border" href="{{ route('think-tank.dashboard', $portalRouteParams) }}"><i class="feather-activity me-1"></i> Dashboard overview</a>
                        <a class="btn btn-dark" href="{{ route('think-tank.reports.download', $reportsQueryParams) }}"><i class="feather-download me-1"></i> Download reporting PDF</a>
                    </div>
                </div>
            </aside>
        </section>
    </div>
</x-think-tank.partials.shell>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const chartData = @json($chartData);
            const money = (value) => 'USD ' + Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            const baseOptions = {
                chart: { toolbar: { show: false }, fontFamily: 'Inter, Arial, sans-serif' },
                dataLabels: { enabled: false },
                colors: ['#2563eb', '#22c55e', '#f59e0b', '#ef4444'],
                grid: { borderColor: '#e2e8f0' },
                legend: { position: 'bottom' }
            };

            new ApexCharts(document.querySelector('#ttReportStatusChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'donut', height: 250 },
                series: chartData.status.values,
                labels: chartData.status.labels
            }).render();

            new ApexCharts(document.querySelector('#ttReportTimelineChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'line', height: 250 },
                stroke: { curve: 'smooth', width: 3 },
                series: [
                    { name: 'Reports submitted', data: chartData.timeline.counts },
                    { name: 'Average progress %', data: chartData.timeline.progress }
                ],
                xaxis: { categories: chartData.timeline.labels }
            }).render();

            new ApexCharts(document.querySelector('#ttReportFundsChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'bar', height: 250 },
                series: [{ name: 'Funds spent', data: chartData.funds.values }],
                xaxis: { categories: chartData.funds.labels },
                yaxis: { labels: { formatter: (value) => Number(value).toLocaleString() } },
                tooltip: { y: { formatter: money } },
                plotOptions: { bar: { borderRadius: 5, columnWidth: '48%' } }
            }).render();

            new ApexCharts(document.querySelector('#ttReportEvidenceChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'bar', height: 250 },
                colors: ['#0f766e', '#f59e0b'],
                series: [{ name: 'Reports', data: chartData.evidence.values }],
                xaxis: { categories: chartData.evidence.labels },
                plotOptions: { bar: { horizontal: true, borderRadius: 5 } }
            }).render();
        });
    </script>
@endpush
