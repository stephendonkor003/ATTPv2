@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Commitment Report</h4>
                <p class="text-muted mb-0">Program-based commitments vs allocations with insights and charts</p>
            </div>

            @if ($program)
                @php $exportQuery = http_build_query($query); @endphp
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="exportPdfBtn">
                        <i class="feather-file-text me-1"></i> Export PDF
                    </button>
                    <a href="{{ route('budget.reports.commitments.export.excel') }}{{ $exportQuery ? '?' . $exportQuery : '' }}"
                        class="btn btn-outline-success">
                        <i class="feather-download me-1"></i> Export Excel
                    </a>
                    <button class="btn btn-outline-primary" type="button" onclick="window.print()">
                        <i class="feather-printer me-1"></i> Print
                    </button>
                </div>
            @endif
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <form method="GET" action="{{ route('budget.reports.commitments') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Program</label>
                        <select name="program_id" class="form-select" required>
                            <option value="">Select Program</option>
                            @foreach ($programs as $p)
                                <option value="{{ $p->id }}" {{ request('program_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Filter Type</label>
                        <select name="filter_mode" id="filter_mode" class="form-select">
                            <option value="multi_year" {{ $filters['mode'] === 'multi_year' ? 'selected' : '' }}>Multi Year</option>
                            <option value="yearly" {{ $filters['mode'] === 'yearly' ? 'selected' : '' }}>Yearly</option>
                            <option value="quarterly" {{ $filters['mode'] === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                            <option value="semiannual" {{ $filters['mode'] === 'semiannual' ? 'selected' : '' }}>6 Months</option>
                            <option value="range" {{ $filters['mode'] === 'range' ? 'selected' : '' }}>Date Range</option>
                        </select>
                    </div>

                    <div class="col-md-2 filter-field filter-multi-year">
                        <label class="form-label">Start Year</label>
                        <input type="number" name="start_year" class="form-control" value="{{ request('start_year', $filters['start_year']) }}">
                    </div>
                    <div class="col-md-2 filter-field filter-multi-year">
                        <label class="form-label">End Year</label>
                        <input type="number" name="end_year" class="form-control" value="{{ request('end_year', $filters['end_year']) }}">
                    </div>

                    <div class="col-md-2 filter-field filter-yearly d-none">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" value="{{ request('year', $filters['start_year']) }}">
                    </div>

                    <div class="col-md-2 filter-field filter-quarterly d-none">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" value="{{ request('year', $filters['start_year']) }}">
                    </div>
                    <div class="col-md-2 filter-field filter-quarterly d-none">
                        <label class="form-label">Quarter</label>
                        <select name="quarter" class="form-select">
                            @for ($q = 1; $q <= 4; $q++)
                                <option value="{{ $q }}" {{ (int) request('quarter', 1) === $q ? 'selected' : '' }}>Q{{ $q }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-md-2 filter-field filter-semiannual d-none">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" value="{{ request('year', $filters['start_year']) }}">
                    </div>
                    <div class="col-md-2 filter-field filter-semiannual d-none">
                        <label class="form-label">Half Year</label>
                        <select name="half" class="form-select">
                            <option value="1" {{ (int) request('half', 1) === 1 ? 'selected' : '' }}>H1 (Jan-Jun)</option>
                            <option value="2" {{ (int) request('half', 1) === 2 ? 'selected' : '' }}>H2 (Jul-Dec)</option>
                        </select>
                    </div>

                    <div class="col-md-3 filter-field filter-range d-none">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-3 filter-field filter-range d-none">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="feather-filter me-1"></i> Run Report
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if (!$program)
            <div class="alert alert-info mt-4">Select a program and filter range to generate the commitment report.</div>
        @else
            @php
                $currency = $program->currency
                    ?? $program->approvedFundings?->first()?->currency
                    ?? $program->fundings?->first()?->currency
                    ?? '';
            @endphp

            {{-- SECTION 1: BALANCE-SHEET STYLE TABLE --}}
            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <div class="section-title">Section 1: Commitment Balance Sheet</div>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">{{ $program->name }}</h5>
                            <div class="text-muted">Commitment Report — {{ $filters['label'] }}</div>
                            <div class="text-muted mt-1">
                                Funding Partners:
                                @if ($funders->isEmpty())
                                    <span class="text-muted">N/A</span>
                                @else
                                    {{ $funders->pluck('name')->implode(', ') }}
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold">Total Allocated: {{ $currency }} {{ number_format($totals['allocated'] ?? 0, 2) }}</div>
                            <div class="fw-semibold">Total Committed: {{ $currency }} {{ number_format($totals['committed'] ?? 0, 2) }}</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle balance-table">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2" style="min-width: 260px;">Project / Activity / Sub-Activity</th>
                                    <th rowspan="2">PR Reference No</th>
                                    <th rowspan="2" class="text-end">Allocated</th>
                                    <th rowspan="2" class="text-end">Planned Commitment</th>
                                    <th rowspan="2" class="text-end">Variance</th>
                                    <th rowspan="2" class="text-end">Utilization %</th>
                                    @foreach ($filters['year_range'] as $year)
                                        <th colspan="3" class="text-center">{{ $year }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach ($filters['year_range'] as $year)
                                        <th class="text-end">Allocated</th>
                                        <th class="text-end">Committed</th>
                                        <th class="text-end">Variance</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report as $projectRow)
                                    <tr class="project-row">
                                        <td colspan="2" class="project-label">{{ $projectRow['project']->name }}</td>
                                        <td class="text-end">{{ number_format($projectRow['allocated'], 2) }}</td>
                                        <td class="text-end">{{ number_format($projectRow['committed'], 2) }}</td>
                                        <td class="text-end {{ $projectRow['variance'] < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ number_format($projectRow['variance'], 2) }}
                                        </td>
                                        <td class="text-end">{{ number_format($projectRow['utilization'], 2) }}%</td>
                                        @foreach ($filters['year_range'] as $year)
                                            <td class="text-end">{{ number_format($projectRow['yearly']['allocated'][$year] ?? 0, 2) }}</td>
                                            <td class="text-end">{{ number_format($projectRow['yearly']['committed'][$year] ?? 0, 2) }}</td>
                                            <td class="text-end {{ ($projectRow['yearly']['variance'][$year] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format($projectRow['yearly']['variance'][$year] ?? 0, 2) }}
                                            </td>
                                        @endforeach
                                    </tr>

                                    @foreach ($projectRow['activities'] as $activityRow)
                                        <tr class="activity-row">
                                            <td colspan="2" class="activity-label">{{ $activityRow['activity']->name }}</td>
                                            <td class="text-end">{{ number_format($activityRow['allocated'], 2) }}</td>
                                            <td class="text-end">{{ number_format($activityRow['committed'], 2) }}</td>
                                            <td class="text-end {{ $activityRow['variance'] < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format($activityRow['variance'], 2) }}
                                            </td>
                                            <td class="text-end">{{ number_format($activityRow['utilization'], 2) }}%</td>
                                            @foreach ($filters['year_range'] as $year)
                                                <td class="text-end">{{ number_format($activityRow['yearly']['allocated'][$year] ?? 0, 2) }}</td>
                                                <td class="text-end">{{ number_format($activityRow['yearly']['committed'][$year] ?? 0, 2) }}</td>
                                                <td class="text-end {{ ($activityRow['yearly']['variance'][$year] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                                    {{ number_format($activityRow['yearly']['variance'][$year] ?? 0, 2) }}
                                                </td>
                                            @endforeach
                                        </tr>

                                        @foreach ($activityRow['subActivities'] as $subRow)
                                            <tr class="sub-row">
                                                <td>{{ $subRow['subActivity']->name }}</td>
                                                <td title="{{ $subRow['references_full'] ?? '' }}">{{ $subRow['references'] }}</td>
                                                <td class="text-end">{{ number_format($subRow['allocated'], 2) }}</td>
                                                <td class="text-end">{{ number_format($subRow['committed'], 2) }}</td>
                                                <td class="text-end {{ $subRow['variance'] < 0 ? 'text-danger' : 'text-success' }}">
                                                    {{ number_format($subRow['variance'], 2) }}
                                                </td>
                                                <td class="text-end">{{ number_format($subRow['utilization'], 2) }}%</td>
                                                @foreach ($filters['year_range'] as $year)
                                                    <td class="text-end">{{ number_format($subRow['yearly']['allocated'][$year] ?? 0, 2) }}</td>
                                                    <td class="text-end">{{ number_format($subRow['yearly']['committed'][$year] ?? 0, 2) }}</td>
                                                    <td class="text-end {{ ($subRow['yearly']['variance'][$year] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                                        {{ number_format($subRow['yearly']['variance'][$year] ?? 0, 2) }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- SECTION 2: CHARTS --}}
            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <div class="section-title">Section 2: Graphs & Trends</div>
                    <h5 class="fw-bold mb-3">Graphs & Insights</h5>
                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-semibold mb-2">Commitments Over Time (Line)</div>
                                <canvas id="commitmentLineChart" height="200"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-semibold mb-2">Allocations vs Commitments (Bar)</div>
                                <canvas id="commitmentBarChart" height="200"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-semibold mb-2">Sub-Activity Distribution (Bubble)</div>
                                <canvas id="commitmentBubbleChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION 3: AI SUMMARY --}}
            <div class="card shadow-sm mt-4">
                <div class="card-body summary-card">
                    <div class="section-title">Section 3: AI Summary</div>
                    <h5 class="fw-bold mb-3">AI Summary</h5>
                    <ul class="mb-0">
                        @foreach ($summary as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if ($program)
            <form method="POST" action="{{ route('budget.reports.commitments.export.pdf') }}" id="pdfExportForm" class="d-none">
                @csrf
                <input type="hidden" name="program_id" value="{{ request('program_id') }}">
                <input type="hidden" name="filter_mode" value="{{ request('filter_mode', $filters['mode']) }}">
                <input type="hidden" name="start_year" value="{{ request('start_year', $filters['start_year']) }}">
                <input type="hidden" name="end_year" value="{{ request('end_year', $filters['end_year']) }}">
                <input type="hidden" name="year" value="{{ request('year', $filters['start_year']) }}">
                <input type="hidden" name="quarter" value="{{ request('quarter', 1) }}">
                <input type="hidden" name="half" value="{{ request('half', 1) }}">
                <input type="hidden" name="start_date" value="{{ request('start_date') }}">
                <input type="hidden" name="end_date" value="{{ request('end_date') }}">
                <input type="hidden" name="chart_line" id="chart_line">
                <input type="hidden" name="chart_bar" id="chart_bar">
                <input type="hidden" name="chart_bubble" id="chart_bubble">
            </form>
        @endif
    </div>

    <style>
        .section-title {
            background: linear-gradient(90deg, #0f172a 0%, #1f3a8a 100%);
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px 8px 0 0;
            margin: -16px -16px 16px -16px;
        }
        .balance-table thead th {
            font-size: 12px;
            vertical-align: middle;
        }
        .balance-table .project-row {
            background: #f1f5f9;
            font-weight: 700;
        }
        .balance-table .activity-row {
            background: #f8fafc;
            font-weight: 600;
        }
        .balance-table .sub-row td:first-child {
            padding-left: 24px;
        }
        .balance-table .activity-row td:first-child {
            padding-left: 12px;
        }
        .project-label {
            font-size: 15px;
            font-weight: 700;
        }
        .activity-label {
            font-size: 13px;
            font-style: italic;
            font-weight: 600;
        }
        .summary-card {
            background: #f8fafc;
            border-left: 4px solid #1f3a8a;
        }
        @media print {
            .page-header, .card form, .btn, nav, footer { display: none !important; }
            .nxl-container { padding: 0 !important; }
        }
    </style>

    @if ($program && $chartData)
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const lineLabels = @json($chartData['line']['labels']);
            const lineData = @json($chartData['line']['data']);
            const barLabels = @json($chartData['bar']['labels']);
            const barAllocations = @json($chartData['bar']['allocations']);
            const barCommitments = @json($chartData['bar']['commitments']);
            const bubbleData = @json($chartData['bubble']);

            window.commitmentLineChart = new Chart(document.getElementById('commitmentLineChart'), {
                type: 'line',
                data: {
                    labels: lineLabels,
                    datasets: [{
                        label: 'Commitments',
                        data: lineData,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,0.15)',
                        tension: 0.3,
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } }
                }
            });

            window.commitmentBarChart = new Chart(document.getElementById('commitmentBarChart'), {
                type: 'bar',
                data: {
                    labels: barLabels,
                    datasets: [{
                        label: 'Allocated',
                        data: barAllocations,
                        backgroundColor: '#198754'
                    }, {
                        label: 'Committed',
                        data: barCommitments,
                        backgroundColor: '#0d6efd'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });

            window.commitmentBubbleChart = new Chart(document.getElementById('commitmentBubbleChart'), {
                type: 'bubble',
                data: {
                    datasets: [{
                        label: 'Sub-Activities',
                        data: bubbleData,
                        backgroundColor: 'rgba(13,110,253,0.4)',
                        borderColor: '#0d6efd'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: { title: { display: true, text: 'Allocated' } },
                        y: { title: { display: true, text: 'Committed' } }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const raw = context.raw || {};
                                    return `${raw.label ?? 'Sub-Activity'}: ${raw.x} allocated, ${raw.y} committed`;
                                }
                            }
                        }
                    }
                }
            });

            const filterMode = document.getElementById('filter_mode');
            const allFields = document.querySelectorAll('.filter-field');
            const updateFilterFields = () => {
                allFields.forEach(el => el.classList.add('d-none'));
                const mode = filterMode.value;
                document.querySelectorAll(`.filter-${mode}`).forEach(el => el.classList.remove('d-none'));
            };
            filterMode.addEventListener('change', updateFilterFields);
            updateFilterFields();

            const exportPdfBtn = document.getElementById('exportPdfBtn');
            if (exportPdfBtn) {
                exportPdfBtn.addEventListener('click', () => {
                    const form = document.getElementById('pdfExportForm');
                    if (!form) return;

                    const line = window.commitmentLineChart?.toBase64Image?.() || '';
                    const bar = window.commitmentBarChart?.toBase64Image?.() || '';
                    const bubble = window.commitmentBubbleChart?.toBase64Image?.() || '';

                    document.getElementById('chart_line').value = line;
                    document.getElementById('chart_bar').value = bar;
                    document.getElementById('chart_bubble').value = bubble;

                    form.submit();
                });
            }
        </script>
    @else
        <script>
            const filterMode = document.getElementById('filter_mode');
            const allFields = document.querySelectorAll('.filter-field');
            const updateFilterFields = () => {
                allFields.forEach(el => el.classList.add('d-none'));
                const mode = filterMode.value;
                document.querySelectorAll(`.filter-${mode}`).forEach(el => el.classList.remove('d-none'));
            };
            filterMode.addEventListener('change', updateFilterFields);
            updateFilterFields();
        </script>
    @endif
@endsection
