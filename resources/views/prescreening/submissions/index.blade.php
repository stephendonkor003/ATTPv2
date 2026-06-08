@extends('layouts.app')

@section('title', 'Prescreening Evaluations')

@section('content')
    @php
        $statusColors = [
            'submitted' => 'secondary',
            'prescreen_passed' => 'success',
            'prescreen_failed' => 'danger',
            'under_review' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
        ];
        $totalCount = $submissions->count();
        $passedCount = $submissions->where('status', 'prescreen_passed')->count();
        $failedCount = $submissions->where('status', 'prescreen_failed')->count();
        $pendingCount = $submissions->whereNotIn('status', ['prescreen_passed', 'prescreen_failed'])->count();
        $lockedCount = $submissions->filter(fn ($submission) => $submission->prescreeningResult?->is_locked)->count();
        $procurementOptions = $submissions->pluck('procurement')->filter()->unique('id')->sortBy('title')->values();
        $evaluatorOptions = $submissions
            ->map(fn ($submission) => $submission->prescreeningResult?->evaluator)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    @endphp

    <main class="nxl-container prescreening-submissions-page">
        <div class="page-header">
            <div class="page-header-left">
                <h4 class="fw-bold mb-1">
                    <i class="feather-file-text me-2"></i>
                    Prescreening Evaluations
                </h4>
                <p class="mb-0">Submission review, filters, and report downloads.</p>
            </div>
            <div class="page-actions">
                <button type="button" class="btn btn-light btn-sm" id="exportCsvBtn">
                    <i class="feather-download me-1"></i> CSV
                </button>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card summary-card--total">
                <span>Total</span>
                <strong>{{ number_format($totalCount) }}</strong>
            </div>
            <div class="summary-card summary-card--passed">
                <span>Passed</span>
                <strong>{{ number_format($passedCount) }}</strong>
            </div>
            <div class="summary-card summary-card--failed">
                <span>Failed</span>
                <strong>{{ number_format($failedCount) }}</strong>
            </div>
            <div class="summary-card summary-card--pending">
                <span>Pending</span>
                <strong>{{ number_format($pendingCount) }}</strong>
            </div>
            <div class="summary-card summary-card--locked">
                <span>Locked</span>
                <strong>{{ number_format($lockedCount) }}</strong>
            </div>
        </div>

        <section class="filter-panel">
            <div class="filter-field filter-field--wide">
                <label for="prescreeningSearch">Search</label>
                <div class="filter-input">
                    <i class="feather-search"></i>
                    <input id="prescreeningSearch" type="search" placeholder="Name, email, code, procurement, evaluator">
                </div>
            </div>

            <div class="filter-field">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="">All</option>
                    @foreach ($submissions->pluck('status')->filter()->unique()->sort()->values() as $status)
                        <option value="{{ $status }}">{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="procurementFilter">Procurement</label>
                <select id="procurementFilter">
                    <option value="">All</option>
                    @foreach ($procurementOptions as $procurement)
                        <option value="{{ $procurement->id }}">{{ $procurement->title }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="evaluatorFilter">Evaluator</label>
                <select id="evaluatorFilter">
                    <option value="">All</option>
                    <option value="unassigned">Unassigned</option>
                    @foreach ($evaluatorOptions as $evaluator)
                        <option value="{{ $evaluator->id }}">{{ $evaluator->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="lockedFilter">Lock</label>
                <select id="lockedFilter">
                    <option value="">All</option>
                    <option value="yes">Locked</option>
                    <option value="no">Open</option>
                </select>
            </div>

            <div class="filter-field">
                <label for="dateFromFilter">From</label>
                <input id="dateFromFilter" type="date">
            </div>

            <div class="filter-field">
                <label for="dateToFilter">To</label>
                <input id="dateToFilter" type="date">
            </div>

            <button type="button" class="btn btn-light filter-reset" id="resetFiltersBtn">
                <i class="feather-rotate-ccw me-1"></i> Reset
            </button>
        </section>

        <div class="table-toolbar">
            <div>
                <strong id="visibleCount">{{ $totalCount }}</strong>
                <span>visible submissions</span>
            </div>
        </div>

        <section class="submissions-table-card">
            <div class="table-responsive">
                <table class="table align-middle submissions-table" id="prescreeningSubmissionsTable">
                    <thead>
                        <tr>
                            <th>Submission</th>
                            <th>Applicant</th>
                            <th>Procurement</th>
                            <th>Status</th>
                            <th>Evaluator</th>
                            <th>Result</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($submissions as $submission)
                            @php
                                $status = $submission->status ?? 'submitted';
                                $badge = $statusColors[$status] ?? 'info';
                                $displayName = $submission->display_name;
                                $officialEmail = $submission->values->firstWhere('field_key', 'official_email')?->value;
                                $displayEmail = $officialEmail ?: $submission->submitter?->email;
                                $evaluator = $submission->prescreeningResult?->evaluator;
                                $isLocked = (bool) $submission->prescreeningResult?->is_locked;
                                $result = $submission->prescreeningResult;
                                $dateValue = optional($submission->submitted_at ?? $submission->created_at)->format('Y-m-d');
                                $searchText = \Illuminate\Support\Str::lower(implode(' ', array_filter([
                                    $submission->procurement_submission_code,
                                    $displayName,
                                    $displayEmail,
                                    $submission->procurement?->title,
                                    $submission->procurement?->reference_no,
                                    $status,
                                    $evaluator?->name,
                                ])));
                            @endphp

                            <tr
                                class="submission-row"
                                data-search="{{ e($searchText) }}"
                                data-status="{{ $status }}"
                                data-procurement="{{ $submission->procurement?->id }}"
                                data-evaluator="{{ $evaluator?->id ?? 'unassigned' }}"
                                data-locked="{{ $isLocked ? 'yes' : 'no' }}"
                                data-date="{{ $dateValue }}"
                                data-code="{{ e($submission->procurement_submission_code) }}"
                                data-name="{{ e($displayName) }}"
                                data-email="{{ e($displayEmail ?: '-') }}"
                                data-procurement-title="{{ e($submission->procurement?->title ?? '-') }}"
                                data-status-label="{{ e(\Illuminate\Support\Str::headline($status)) }}"
                                data-evaluator-name="{{ e($evaluator?->name ?? '-') }}"
                                data-result="{{ e(($result?->passed_criteria ?? '-') . '/' . ($result?->total_criteria ?? '-')) }}"
                            >
                                <td>
                                    <div class="submission-code">{{ $submission->procurement_submission_code }}</div>
                                    <small>{{ optional($submission->submitted_at ?? $submission->created_at)->format('d M Y') ?? '-' }}</small>
                                </td>
                                <td>
                                    <div class="applicant-name">{{ $displayName ?: '-' }}</div>
                                    @if ($displayEmail)
                                        <a href="mailto:{{ $displayEmail }}" class="applicant-email">{{ $displayEmail }}</a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="procurement-title">{{ $submission->procurement?->title ?? '-' }}</div>
                                    <small>{{ $submission->procurement?->reference_no ?? '-' }}</small>
                                </td>
                                <td>
                                    <span class="status-pill status-pill--{{ $status }} bg-{{ $badge }}">
                                        {{ \Illuminate\Support\Str::headline($status) }}
                                    </span>
                                    @if ($isLocked)
                                        <small class="lock-label">
                                            <i class="feather-lock"></i> Locked
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    {{ $evaluator?->name ?? '-' }}
                                </td>
                                <td>
                                    @if ($result)
                                        <div class="result-score">
                                            <strong>{{ $result->passed_criteria }}</strong>
                                            <span>/ {{ $result->total_criteria }}</span>
                                        </div>
                                        <small>{{ $result->failed_criteria }} failed</small>
                                    @else
                                        <span class="text-muted">Pending</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="row-actions">
                                        <a href="{{ route('prescreening.submissions.show', $submission) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="feather-eye me-1"></i> View
                                        </a>
                                        <a href="{{ route('prescreening.submissions.pdf', $submission) }}" class="btn btn-sm btn-success">
                                            <i class="feather-download me-1"></i> PDF
                                        </a>
                                        <a href="{{ route('prescreening.submissions.anonymised-pdf', $submission) }}" class="btn btn-sm btn-dark">
                                            <i class="feather-shield me-1"></i> Anonymised
                                        </a>

                                        @can('prescreening.request_rework')
                                            @if ($result && $isLocked)
                                                <form method="POST" action="{{ route('prescreening.submissions.rework', $submission) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Request rework for this evaluation?')">
                                                        Rework
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="empty-row">No prescreening submissions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="empty-filter-state d-none" id="emptyFilterState">
            <strong>No submissions match the selected filters.</strong>
        </div>
    </main>
@endsection

@push('styles')
    <style>
        .prescreening-submissions-page {
            --ink: #172033;
            --muted: #667085;
            --line: #d9e2ec;
            --soft: #f6f8fb;
            --green: #0f766e;
            --blue: #2563eb;
            --orange: #b45309;
            --red: #b42318;
        }

        .page-actions,
        .row-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .summary-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
            margin-bottom: 14px;
        }

        .summary-card {
            background: #fff;
            border: 1px solid var(--line);
            border-left: 4px solid var(--ink);
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .04);
            padding: 14px 15px;
        }

        .summary-card span,
        .filter-field label {
            color: var(--muted);
            display: block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .summary-card strong {
            color: var(--ink);
            display: block;
            font-size: 24px;
            line-height: 1.1;
            margin-top: 8px;
        }

        .summary-card--total { border-left-color: var(--blue); }
        .summary-card--passed { border-left-color: var(--green); }
        .summary-card--failed { border-left-color: var(--red); }
        .summary-card--pending { border-left-color: var(--orange); }
        .summary-card--locked { border-left-color: #475467; }

        .filter-panel {
            align-items: end;
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 8px;
            display: grid;
            gap: 12px;
            grid-template-columns: minmax(220px, 1.6fr) repeat(6, minmax(120px, 1fr)) auto;
            margin-bottom: 10px;
            padding: 14px;
        }

        .filter-field input,
        .filter-field select {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: var(--ink);
            height: 38px;
            padding: 0 10px;
            width: 100%;
        }

        .filter-input {
            position: relative;
        }

        .filter-input i {
            color: var(--muted);
            left: 11px;
            position: absolute;
            top: 11px;
        }

        .filter-input input {
            padding-left: 34px;
        }

        .filter-reset {
            height: 38px;
            white-space: nowrap;
        }

        .table-toolbar {
            color: var(--muted);
            font-size: 13px;
            margin: 10px 0 12px;
        }

        .table-toolbar strong {
            color: var(--ink);
        }

        .submissions-table-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, .05);
            overflow: hidden;
        }

        .submissions-table {
            margin-bottom: 0;
        }

        .submissions-table thead th {
            background: #f8fafc;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            padding: 12px 14px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .submissions-table td {
            color: #344054;
            padding: 14px;
            vertical-align: top;
        }

        .submission-code,
        .applicant-name,
        .procurement-title {
            color: var(--ink);
            font-weight: 800;
            line-height: 1.35;
        }

        .applicant-email,
        .submissions-table small {
            color: var(--muted);
            display: inline-block;
            font-size: 12px;
            margin-top: 3px;
        }

        .status-pill {
            border-radius: 999px;
            color: #fff;
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            padding: 5px 8px;
            text-transform: uppercase;
        }

        .lock-label {
            display: block;
        }

        .result-score strong {
            color: var(--ink);
            font-size: 18px;
        }

        .result-score span {
            color: var(--muted);
        }

        .row-actions {
            justify-content: flex-end;
        }

        .empty-row,
        .empty-filter-state {
            color: var(--muted);
            padding: 24px;
            text-align: center;
        }

        .empty-filter-state {
            background: #fff;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            margin-top: 14px;
        }

        @media (max-width: 1320px) {
            .filter-panel {
                grid-template-columns: repeat(4, minmax(150px, 1fr));
            }

            .filter-field--wide {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 720px) {
            .filter-panel,
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .page-actions,
            .row-actions {
                width: 100%;
            }

            .row-actions .btn,
            .row-actions form,
            .page-actions .btn {
                flex: 1 1 100%;
            }

            .row-actions form .btn {
                width: 100%;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const controls = {
                search: document.getElementById('prescreeningSearch'),
                status: document.getElementById('statusFilter'),
                procurement: document.getElementById('procurementFilter'),
                evaluator: document.getElementById('evaluatorFilter'),
                locked: document.getElementById('lockedFilter'),
                from: document.getElementById('dateFromFilter'),
                to: document.getElementById('dateToFilter'),
                reset: document.getElementById('resetFiltersBtn'),
                exportCsv: document.getElementById('exportCsvBtn'),
                count: document.getElementById('visibleCount'),
                empty: document.getElementById('emptyFilterState'),
            };
            const rows = Array.from(document.querySelectorAll('.submission-row'));

            const visibleRows = () => rows.filter((row) => !row.classList.contains('d-none'));

            const applyFilters = () => {
                const term = controls.search.value.trim().toLowerCase();
                const status = controls.status.value;
                const procurement = controls.procurement.value;
                const evaluator = controls.evaluator.value;
                const locked = controls.locked.value;
                const from = controls.from.value;
                const to = controls.to.value;
                let visible = 0;

                rows.forEach((row) => {
                    const date = row.dataset.date || '';
                    const matches = (!term || row.dataset.search.includes(term))
                        && (!status || row.dataset.status === status)
                        && (!procurement || row.dataset.procurement === procurement)
                        && (!evaluator || row.dataset.evaluator === evaluator)
                        && (!locked || row.dataset.locked === locked)
                        && (!from || date >= from)
                        && (!to || date <= to);

                    row.classList.toggle('d-none', !matches);
                    if (matches) visible += 1;
                });

                controls.count.textContent = visible;
                controls.empty.classList.toggle('d-none', visible !== 0);
            };

            const csvValue = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;

            const exportCsv = () => {
                const headers = ['Submission Code', 'Applicant', 'Email', 'Procurement', 'Status', 'Evaluator', 'Result'];
                const lines = [
                    headers.map(csvValue).join(','),
                    ...visibleRows().map((row) => [
                        row.dataset.code,
                        row.dataset.name,
                        row.dataset.email,
                        row.dataset.procurementTitle,
                        row.dataset.statusLabel,
                        row.dataset.evaluatorName,
                        row.dataset.result,
                    ].map(csvValue).join(',')),
                ];

                const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'prescreening-submissions.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            };

            ['search', 'status', 'procurement', 'evaluator', 'locked', 'from', 'to'].forEach((key) => {
                controls[key].addEventListener('input', applyFilters);
                controls[key].addEventListener('change', applyFilters);
            });

            controls.reset.addEventListener('click', () => {
                controls.search.value = '';
                controls.status.value = '';
                controls.procurement.value = '';
                controls.evaluator.value = '';
                controls.locked.value = '';
                controls.from.value = '';
                controls.to.value = '';
                applyFilters();
            });

            controls.exportCsv.addEventListener('click', exportCsv);
        });
    </script>
@endpush
