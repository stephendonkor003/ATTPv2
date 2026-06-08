@extends('layouts.app')
@section('title', 'Comprehensive Site Visit Report')

@section('content')
    @php
        $scoreFor = function ($visit) {
            foreach ($visit->approvals as $approval) {
                if (preg_match('/Total score:\s*([0-9.]+)/i', (string) $approval->remarks, $matches)) {
                    return (float) $matches[1];
                }
            }

            return null;
        };

        $scoreValues = $siteVisits->map($scoreFor)->filter(fn ($score) => $score !== null);
        $totalActionItems = $siteVisits->sum(fn ($visit) => $visit->observations->where('action_required', true)->count());
        $approvedCount = $siteVisits->where('status', 'approved')->count();
        $submittedCount = $siteVisits->where('status', 'submitted')->count();
        $draftCount = $siteVisits->where('status', 'draft')->count();
        $averageScore = $scoreValues->isNotEmpty() ? round($scoreValues->avg(), 1) : null;
    @endphp

    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h5 class="m-b-10">Site Visit Comprehensive Report</h5>
                    <p class="text-muted mb-0">
                        Procurement: <strong>{{ $procurement->title }}</strong>
                    </p>
                </div>
            </div>

            <div class="main-content site-report">
                <div class="report-summary">
                    <div class="report-stat">
                        <span>Total Visits</span>
                        <strong>{{ $siteVisits->count() }}</strong>
                    </div>
                    <div class="report-stat report-stat--approved">
                        <span>Approved</span>
                        <strong>{{ $approvedCount }}</strong>
                    </div>
                    <div class="report-stat report-stat--pending">
                        <span>Submitted</span>
                        <strong>{{ $submittedCount }}</strong>
                    </div>
                    <div class="report-stat report-stat--draft">
                        <span>Draft</span>
                        <strong>{{ $draftCount }}</strong>
                    </div>
                    <div class="report-stat report-stat--risk">
                        <span>Action Items</span>
                        <strong>{{ $totalActionItems }}</strong>
                    </div>
                    <div class="report-stat">
                        <span>Average Score</span>
                        <strong>{{ $averageScore ?? '-' }}</strong>
                    </div>
                </div>

                <div class="report-filter-bar">
                    <div class="filter-field filter-field--wide">
                        <label for="siteVisitSearch">Search</label>
                        <div class="filter-input">
                            <i class="feather-search"></i>
                            <input id="siteVisitSearch" type="search" placeholder="Applicant, team, reviewer, observation">
                        </div>
                    </div>

                    <div class="filter-field">
                        <label for="siteVisitStatus">Status</label>
                        <select id="siteVisitStatus">
                            <option value="">All</option>
                            @foreach ($siteVisits->pluck('status')->filter()->unique()->sort()->values() as $status)
                                <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="siteVisitAssignment">Assignment</label>
                        <select id="siteVisitAssignment">
                            <option value="">All</option>
                            @foreach ($siteVisits->pluck('assignment_type')->filter()->unique()->sort()->values() as $assignment)
                                <option value="{{ $assignment }}">{{ ucfirst($assignment) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="siteVisitAction">Action</label>
                        <select id="siteVisitAction">
                            <option value="">All</option>
                            <option value="yes">Required</option>
                            <option value="no">None</option>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="siteVisitDateFrom">From</label>
                        <input id="siteVisitDateFrom" type="date">
                    </div>

                    <div class="filter-field">
                        <label for="siteVisitDateTo">To</label>
                        <input id="siteVisitDateTo" type="date">
                    </div>

                    <button type="button" class="btn btn-light report-reset" id="siteVisitReset">
                        <i class="feather-rotate-ccw me-1"></i> Reset
                    </button>
                </div>

                <div class="report-count-row">
                    <span id="siteVisitVisibleCount">{{ $siteVisits->count() }}</span>
                    <span>visible</span>
                </div>

                <div class="visit-grid" id="siteVisitGrid">
                    @forelse($siteVisits as $visit)
                        @php
                            $score = $scoreFor($visit);
                            $actionCount = $visit->observations->where('action_required', true)->count();
                            $highCount = $visit->observations->where('severity', 'high')->count();
                            $leaderName = $visit->group?->leader?->name ?? $visit->assignment?->user?->name ?? '-';
                            $teamName = $visit->assignment_type === 'individual'
                                ? ($visit->assignment?->user?->name ?? '-')
                                : ($visit->group?->group_name ?? '-');
                            $searchText = implode(' ', array_filter([
                                $visit->submission?->display_name,
                                $visit->submission?->procurement_submission_code,
                                $teamName,
                                $leaderName,
                                $visit->status,
                                $visit->observations->pluck('category')->implode(' '),
                                \Illuminate\Support\Str::limit($visit->observations->pluck('description')->implode(' '), 1500, ''),
                            ]));
                        @endphp

                        <article
                            class="visit-card"
                            data-search="{{ e(\Illuminate\Support\Str::lower($searchText)) }}"
                            data-status="{{ $visit->status }}"
                            data-assignment="{{ $visit->assignment_type }}"
                            data-action="{{ $actionCount > 0 ? 'yes' : 'no' }}"
                            data-date="{{ optional($visit->visit_date)->format('Y-m-d') }}"
                        >
                            <div class="visit-card__head">
                                <div>
                                    <span class="visit-card__eyebrow">Applicant</span>
                                    <h6>{{ $visit->submission?->display_name ?? '-' }}</h6>
                                </div>
                                <span class="status-pill status-pill--{{ $visit->status }}">
                                    {{ ucfirst($visit->status ?? '-') }}
                                </span>
                            </div>

                            <div class="visit-card__metrics">
                                <div>
                                    <span>Visit Date</span>
                                    <strong>{{ optional($visit->visit_date)->format('d M Y') ?? '-' }}</strong>
                                </div>
                                <div>
                                    <span>Score</span>
                                    <strong>{{ $score !== null ? number_format($score, 1) : '-' }}</strong>
                                </div>
                                <div>
                                    <span>Observations</span>
                                    <strong>{{ $visit->observations->count() }}</strong>
                                </div>
                                <div>
                                    <span>Actions</span>
                                    <strong>{{ $actionCount }}</strong>
                                </div>
                            </div>

                            <div class="visit-card__assignment">
                                <div>
                                    <span>Team</span>
                                    <strong>{{ $teamName }}</strong>
                                </div>
                                <div>
                                    <span>Lead</span>
                                    <strong>{{ $leaderName }}</strong>
                                </div>
                            </div>

                            <div class="visit-card__badges">
                                <span>{{ ucfirst($visit->assignment_type ?? '-') }}</span>
                                @if ($highCount > 0)
                                    <span class="badge-risk">{{ $highCount }} high</span>
                                @endif
                                @if ($actionCount > 0)
                                    <span class="badge-action">{{ $actionCount }} action</span>
                                @endif
                            </div>

                            <div class="visit-card__observations">
                                @forelse($visit->observations->take(3) as $obs)
                                    <div class="observation-row">
                                        <span class="severity-dot severity-dot--{{ $obs->severity }}"></span>
                                        <div>
                                            <strong>{{ $obs->category }}</strong>
                                            <p>{{ \Illuminate\Support\Str::limit($obs->description, 180) }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No observations recorded.</p>
                                @endforelse

                                @if ($visit->observations->count() > 3)
                                    <small class="text-muted">
                                        +{{ $visit->observations->count() - 3 }} more observations
                                    </small>
                                @endif
                            </div>

                            <div class="visit-card__actions">
                                <a href="{{ route('site-visits.show', $visit) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="feather-eye me-1"></i> View
                                </a>
                                <a href="{{ route('site-visits.procurements.site-visit-report.pdf', [$procurement, $visit]) }}" class="btn btn-danger btn-sm">
                                    <i class="feather-download me-1"></i> PDF
                                </a>
                                <a href="{{ route('site-visits.procurements.site-visit-report.anonymised-pdf', [$procurement, $visit]) }}" class="btn btn-dark btn-sm">
                                    <i class="feather-shield me-1"></i> Anonymised PDF
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="empty-report">
                            <strong>No site visits recorded for this procurement.</strong>
                        </div>
                    @endforelse
                </div>

                <div class="empty-report d-none" id="siteVisitEmpty">
                    <strong>No reports match the selected filters.</strong>
                </div>
            </div>
        </div>
    </main>

    <style>
        .site-report {
            --ink: #172033;
            --muted: #667085;
            --line: #d9e2ec;
            --soft: #f5f7fa;
            --approved: #0f766e;
            --pending: #8a5a00;
            --draft: #475467;
            --risk: #b42318;
        }

        .report-summary {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            margin-bottom: 14px;
        }

        .report-stat {
            background: #fff;
            border: 1px solid var(--line);
            border-left: 4px solid #344054;
            border-radius: 8px;
            padding: 14px 16px;
        }

        .report-stat span,
        .visit-card__eyebrow,
        .visit-card__metrics span,
        .visit-card__assignment span,
        .filter-field label {
            color: var(--muted);
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .report-stat strong {
            color: var(--ink);
            display: block;
            font-size: 24px;
            line-height: 1.1;
            margin-top: 7px;
        }

        .report-stat--approved { border-left-color: var(--approved); }
        .report-stat--pending { border-left-color: var(--pending); }
        .report-stat--draft { border-left-color: var(--draft); }
        .report-stat--risk { border-left-color: var(--risk); }

        .report-filter-bar {
            align-items: end;
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 8px;
            display: grid;
            gap: 12px;
            grid-template-columns: minmax(220px, 2fr) repeat(5, minmax(120px, 1fr)) auto;
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

        .report-reset {
            height: 38px;
            white-space: nowrap;
        }

        .report-count-row {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            margin: 10px 0 14px;
        }

        .visit-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
        }

        .visit-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .05);
            display: flex;
            flex-direction: column;
            min-height: 100%;
            padding: 18px;
        }

        .visit-card__head {
            align-items: start;
            display: flex;
            gap: 14px;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .visit-card h6 {
            color: var(--ink);
            font-size: 16px;
            line-height: 1.35;
            margin: 5px 0 0;
        }

        .status-pill {
            border-radius: 999px;
            color: #fff;
            flex: 0 0 auto;
            font-size: 11px;
            font-weight: 800;
            padding: 5px 9px;
            text-transform: uppercase;
        }

        .status-pill--approved { background: var(--approved); }
        .status-pill--submitted { background: var(--pending); }
        .status-pill--draft { background: var(--draft); }
        .status-pill--rejected { background: var(--risk); }

        .visit-card__metrics {
            border-bottom: 1px solid var(--line);
            border-top: 1px solid var(--line);
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(4, 1fr);
            padding: 12px 0;
        }

        .visit-card__metrics strong,
        .visit-card__assignment strong {
            color: var(--ink);
            display: block;
            font-size: 13px;
            line-height: 1.35;
            margin-top: 4px;
        }

        .visit-card__assignment {
            display: grid;
            gap: 12px;
            grid-template-columns: 1.2fr .8fr;
            margin-top: 12px;
        }

        .visit-card__badges {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin: 14px 0;
        }

        .visit-card__badges span {
            background: #eef2f6;
            border-radius: 999px;
            color: #344054;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 9px;
        }

        .visit-card__badges .badge-risk {
            background: #fee4e2;
            color: var(--risk);
        }

        .visit-card__badges .badge-action {
            background: #fff7ed;
            color: #9a4d00;
        }

        .visit-card__observations {
            display: grid;
            gap: 10px;
            margin-bottom: 16px;
        }

        .observation-row {
            display: grid;
            gap: 9px;
            grid-template-columns: 10px 1fr;
        }

        .observation-row strong {
            color: var(--ink);
            display: block;
            font-size: 13px;
            margin-bottom: 2px;
        }

        .observation-row p {
            color: #475467;
            font-size: 13px;
            line-height: 1.45;
            margin: 0;
        }

        .severity-dot {
            border-radius: 50%;
            display: inline-block;
            height: 9px;
            margin-top: 6px;
            width: 9px;
        }

        .severity-dot--high { background: var(--risk); }
        .severity-dot--medium { background: #d97706; }
        .severity-dot--low { background: #2563eb; }

        .visit-card__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
            margin-top: auto;
        }

        .empty-report {
            background: #fff;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            color: var(--muted);
            padding: 24px;
            text-align: center;
        }

        @media (max-width: 1180px) {
            .report-filter-bar {
                grid-template-columns: repeat(3, minmax(150px, 1fr));
            }
            .filter-field--wide {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 720px) {
            .report-filter-bar,
            .visit-card__assignment,
            .visit-card__metrics {
                grid-template-columns: 1fr;
            }
            .visit-grid {
                grid-template-columns: 1fr;
            }
            .visit-card__head {
                display: block;
            }
            .status-pill {
                display: inline-block;
                margin-top: 10px;
            }
            .visit-card__actions {
                justify-content: stretch;
            }
            .visit-card__actions .btn {
                flex: 1;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const controls = {
                search: document.getElementById('siteVisitSearch'),
                status: document.getElementById('siteVisitStatus'),
                assignment: document.getElementById('siteVisitAssignment'),
                action: document.getElementById('siteVisitAction'),
                from: document.getElementById('siteVisitDateFrom'),
                to: document.getElementById('siteVisitDateTo'),
                reset: document.getElementById('siteVisitReset'),
                count: document.getElementById('siteVisitVisibleCount'),
                empty: document.getElementById('siteVisitEmpty'),
            };
            const cards = Array.from(document.querySelectorAll('.visit-card'));

            const applyFilters = () => {
                const term = controls.search.value.trim().toLowerCase();
                const status = controls.status.value;
                const assignment = controls.assignment.value;
                const action = controls.action.value;
                const from = controls.from.value;
                const to = controls.to.value;
                let visible = 0;

                cards.forEach((card) => {
                    const date = card.dataset.date || '';
                    const matches = (!term || card.dataset.search.includes(term))
                        && (!status || card.dataset.status === status)
                        && (!assignment || card.dataset.assignment === assignment)
                        && (!action || card.dataset.action === action)
                        && (!from || date >= from)
                        && (!to || date <= to);

                    card.classList.toggle('d-none', !matches);
                    if (matches) visible += 1;
                });

                controls.count.textContent = visible;
                controls.empty.classList.toggle('d-none', visible !== 0);
            };

            ['search', 'status', 'assignment', 'action', 'from', 'to'].forEach((key) => {
                controls[key].addEventListener('input', applyFilters);
                controls[key].addEventListener('change', applyFilters);
            });

            controls.reset.addEventListener('click', () => {
                controls.search.value = '';
                controls.status.value = '';
                controls.assignment.value = '';
                controls.action.value = '';
                controls.from.value = '';
                controls.to.value = '';
                applyFilters();
            });
        });
    </script>
@endsection
