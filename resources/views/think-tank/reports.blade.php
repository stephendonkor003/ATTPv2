@php
    $currency = 'USD';
    $portalRouteParams = (auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
        ? ['think_tank_member_id' => $member->id]
        : [];
    $reportAction = route('think-tank.reports.store', $portalRouteParams);
@endphp

@push('styles')
    <style>
        .tt-report-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(280px, .6fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        .tt-report-banner,
        .tt-report-deadline,
        .tt-report-card,
        .tt-report-stat {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
        }

        .tt-report-banner {
            padding: 26px;
            color: #fff;
            min-height: 210px;
            background:
                linear-gradient(120deg, rgba(15, 23, 42, .96), rgba(29, 78, 216, .84)),
                url("{{ asset('admin/assets/images/gallery/2.png') }}");
            background-size: cover;
            background-position: center;
        }

        .tt-report-banner h1 {
            margin: 10px 0;
            max-width: 780px;
            color: #fff;
            font-size: 29px;
            line-height: 1.18;
            letter-spacing: 0;
        }

        .tt-report-banner p {
            max-width: 820px;
            margin: 0;
            color: rgba(255, 255, 255, .86);
        }

        .tt-report-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, .28);
            background: rgba(255, 255, 255, .13);
            border-radius: 999px;
            padding: 7px 11px;
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
        }

        .tt-report-deadline {
            padding: 22px;
            display: grid;
            align-content: center;
            gap: 12px;
            background: linear-gradient(180deg, #f8fafc, #ffffff);
        }

        .tt-report-deadline strong {
            display: block;
            font-size: 46px;
            line-height: 1;
            color: #0f172a;
            letter-spacing: 0;
        }

        .tt-report-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .tt-report-stat {
            padding: 18px;
            min-height: 108px;
        }

        .tt-report-stat .label {
            color: #64748b;
            font-size: 13px;
            font-weight: 800;
            margin: 0;
        }

        .tt-report-stat .value {
            margin-top: 8px;
            font-size: 23px;
            font-weight: 900;
            color: #0f172a;
        }

        .tt-report-tabs {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
            overflow: hidden;
        }

        .tt-report-tabs .nav {
            gap: 8px;
            padding: 14px 16px 0;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .tt-report-tabs .nav-link {
            border: 1px solid transparent;
            border-radius: 8px 8px 0 0;
            color: #475569;
            font-weight: 850;
            padding: 10px 14px;
        }

        .tt-report-tabs .nav-link.active {
            color: #0f172a;
            background: #fff;
            border-color: #e2e8f0 #e2e8f0 #fff;
            box-shadow: 0 -4px 10px rgba(15, 23, 42, .04);
        }

        .tt-report-tab-body {
            padding: 20px;
        }

        .tt-form-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, .55fr);
            gap: 20px;
            align-items: start;
        }

        .tt-form-section {
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            padding: 18px;
            background: #fff;
            margin-bottom: 16px;
        }

        .tt-form-section h2,
        .tt-report-card h2 {
            font-size: 17px;
            font-weight: 900;
            margin: 0 0 4px;
            color: #0f172a;
        }

        .tt-form-section p,
        .tt-report-card .hint {
            color: #64748b;
            font-size: 13px;
            margin: 0 0 16px;
        }

        .tt-field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .tt-field {
            display: grid;
            gap: 7px;
        }

        .tt-field.full {
            grid-column: 1 / -1;
        }

        .tt-field label {
            color: #334155;
            font-size: 13px;
            font-weight: 850;
        }

        .tt-field small {
            color: #64748b;
        }

        .tt-field input,
        .tt-field select,
        .tt-field textarea {
            border: 1px solid #d8dee8;
            border-radius: 7px;
            padding: 11px 12px;
            background: #fff;
            width: 100%;
            color: #0f172a;
        }

        .tt-field textarea {
            min-height: 112px;
            resize: vertical;
        }

        .tt-side-note {
            border: 1px solid #dbeafe;
            border-radius: 9px;
            padding: 16px;
            background: #eff6ff;
            color: #1e3a8a;
        }

        .tt-side-note h3 {
            font-size: 15px;
            font-weight: 900;
            margin: 0 0 10px;
            color: #172554;
        }

        .tt-check-list {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .tt-check-list li {
            display: flex;
            gap: 9px;
            line-height: 1.45;
        }

        .tt-check-list i {
            margin-top: 3px;
            color: #2563eb;
        }

        .tt-report-table-wrap {
            overflow-x: auto;
        }

        .tt-report-table th {
            background: #f8fafc;
            color: #475569;
        }

        .tt-report-name {
            font-weight: 850;
            color: #0f172a;
        }

        .tt-muted {
            color: #64748b;
            font-size: 13px;
        }

        .tt-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 12px;
            font-weight: 850;
            background: #e0f2fe;
            color: #075985;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .tt-status.approved {
            background: #dcfce7;
            color: #166534;
        }

        .tt-status.revisions_requested,
        .tt-status.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .tt-progress-track {
            min-width: 130px;
        }

        .tt-progress-bar {
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            background: #e2e8f0;
            margin-top: 7px;
        }

        .tt-progress-bar span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #0ea5e9, #22c55e);
        }

        .tt-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 9px;
            padding: 26px;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
        }

        .tt-guidance-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .tt-report-card {
            padding: 18px;
        }

        @media (max-width: 1100px) {
            .tt-report-hero,
            .tt-form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .tt-report-stats,
            .tt-field-grid,
            .tt-guidance-grid {
                grid-template-columns: 1fr;
            }

            .tt-report-banner h1 {
                font-size: 23px;
            }
        }
    </style>
@endpush

<x-think-tank.partials.shell :member="$member" title="Activity Reports">
    <section class="tt-report-hero">
        <div class="tt-report-banner">
            <span class="tt-report-kicker"><i class="feather-file-text"></i> Secretariat Reporting</span>
            <h1>Submit clear activity reports with evidence and fund utilisation.</h1>
            <p>
                Use this workspace to report monthly implementation progress, achievements, challenges,
                next steps, and proof of work to the ATTP Secretariat.
            </p>
        </div>
        <aside class="tt-report-deadline">
            <div class="tt-muted fw-bold">Next monthly report deadline</div>
            <strong>{{ $monthlyReportDaysLeft >= 0 ? $monthlyReportDaysLeft : abs($monthlyReportDaysLeft) }}</strong>
            <div>
                @if($monthlyReportDaysLeft >= 0)
                    days left, due {{ $monthlyReportDue->format('M d, Y') }}.
                @else
                    days overdue since {{ $monthlyReportDue->format('M d, Y') }}.
                @endif
            </div>
            <a class="btn btn-primary" href="#submit-report">
                <i class="feather-send me-1"></i> Prepare report
            </a>
        </aside>
    </section>

    <section class="tt-report-stats">
        <div class="tt-report-stat">
            <p class="label">Total reports</p>
            <div class="value">{{ number_format($reportStats['total']) }}</div>
        </div>
        <div class="tt-report-stat">
            <p class="label">Awaiting review</p>
            <div class="value">{{ number_format($reportStats['submitted']) }}</div>
        </div>
        <div class="tt-report-stat">
            <p class="label">Average progress</p>
            <div class="value">{{ number_format($reportStats['average_progress'], 1) }}%</div>
        </div>
        <div class="tt-report-stat">
            <p class="label">Funds reported spent</p>
            <div class="value">{{ $currency }} {{ number_format($reportStats['funds_spent'], 2) }}</div>
        </div>
    </section>

    <section class="tt-report-tabs" id="submit-report">
        <ul class="nav nav-tabs" id="ttReportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="submit-tab" data-bs-toggle="tab" data-bs-target="#submit-pane" type="button" role="tab" aria-controls="submit-pane" aria-selected="true">
                    <i class="feather-edit-3 me-1"></i> Submit Report
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab" aria-controls="history-pane" aria-selected="false">
                    <i class="feather-list me-1"></i> Submitted Reports
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="guidance-tab" data-bs-toggle="tab" data-bs-target="#guidance-pane" type="button" role="tab" aria-controls="guidance-pane" aria-selected="false">
                    <i class="feather-help-circle me-1"></i> Reporting Guide
                </button>
            </li>
        </ul>

        <div class="tab-content tt-report-tab-body">
            <div class="tab-pane fade show active" id="submit-pane" role="tabpanel" aria-labelledby="submit-tab" tabindex="0">
                <form method="POST" action="{{ $reportAction }}" enctype="multipart/form-data">
                    @csrf
                    <div class="tt-form-grid">
                        <div>
                            <div class="tt-form-section">
                                <h2>Report details</h2>
                                <p>Identify the reporting period and workplan this update belongs to.</p>
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
                                                <option value="{{ $workplan->id }}" @selected(old('workplan_id') === $workplan->id)>
                                                    {{ $workplan->title }}
                                                </option>
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
                                        <small>All think tank reporting funds must be entered in USD.</small>
                                    </div>
                                    <div class="tt-field">
                                        <label for="evidence_files">Evidence files</label>
                                        <input id="evidence_files" type="file" name="evidence_files[]" multiple>
                                        <small>You can attach multiple PDF, Word, Excel, image, or ZIP evidence files.</small>
                                    </div>
                                    <div class="tt-field full">
                                        <label for="evidence_title">Evidence group title</label>
                                        <input id="evidence_title" name="evidence_title" value="{{ old('evidence_title') }}" placeholder="Attendance list, field photo pack, invoices, report annexes">
                                    </div>
                                </div>
                            </div>

                            <div class="tt-form-section">
                                <h2>Narrative update</h2>
                                <p>Give the Secretariat enough context to understand what was done, what changed, and what support is needed.</p>
                                <div class="tt-field-grid">
                                    <div class="tt-field full">
                                        <label for="summary">Summary</label>
                                        <textarea id="summary" name="summary" placeholder="Briefly summarize the work completed in this reporting period.">{{ old('summary') }}</textarea>
                                    </div>
                                    <div class="tt-field">
                                        <label for="achievements">Achievements</label>
                                        <textarea id="achievements" name="achievements" placeholder="Outputs delivered, milestones reached, stakeholders engaged.">{{ old('achievements') }}</textarea>
                                    </div>
                                    <div class="tt-field">
                                        <label for="challenges">Challenges</label>
                                        <textarea id="challenges" name="challenges" placeholder="Delays, risks, blockers, procurement issues, data gaps.">{{ old('challenges') }}</textarea>
                                    </div>
                                    <div class="tt-field full">
                                        <label for="next_steps">Next steps</label>
                                        <textarea id="next_steps" name="next_steps" placeholder="Planned work before the next reporting deadline.">{{ old('next_steps') }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <button type="reset" class="btn btn-light border">Clear form</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather-send me-1"></i> Submit to Secretariat
                                </button>
                            </div>
                        </div>

                        <aside class="tt-side-note">
                            <h3>Before submitting</h3>
                            <ul class="tt-check-list">
                                <li><i class="feather-check-circle"></i><span>Use the correct reporting period for this month.</span></li>
                                <li><i class="feather-check-circle"></i><span>Record the amount spent against ATTP-funded activities.</span></li>
                                <li><i class="feather-check-circle"></i><span>Attach evidence where available, especially for events, procurements, and publications.</span></li>
                                <li><i class="feather-check-circle"></i><span>State challenges clearly so the Secretariat can follow up quickly.</span></li>
                            </ul>
                        </aside>
                    </div>
                </form>
            </div>

            <div class="tab-pane fade" id="history-pane" role="tabpanel" aria-labelledby="history-tab" tabindex="0">
                <div class="tt-report-table-wrap">
                    <table class="tt-report-table">
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
                                    <div class="tt-report-name">{{ $report->title }}</div>
                                    <div class="tt-muted">{{ $report->workplan?->title ?? 'No workplan selected' }}</div>
                                </td>
                                <td>
                                    {{ $report->reporting_period_start?->format('M d') ?? 'N/A' }}
                                    -
                                    {{ $report->reporting_period_end?->format('M d, Y') ?? 'N/A' }}
                                </td>
                                <td>
                                    <div class="tt-progress-track">
                                        <strong>{{ number_format($progress, 1) }}%</strong>
                                        <div class="tt-progress-bar"><span style="width: {{ $progress }}%"></span></div>
                                    </div>
                                </td>
                                <td>{{ $currency }} {{ number_format((float) $report->funds_spent, 2) }}</td>
                                <td>{{ number_format($report->evidence->count()) }}</td>
                                <td><span class="tt-status {{ $report->status }}">{{ str_replace('_', ' ', $report->status) }}</span></td>
                                <td>{{ $report->submitted_at?->format('d M Y') ?? $report->created_at?->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="tt-empty">
                                        No reports have been submitted yet. Use the Submit Report tab to send the first update.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $reports->links() }}
                </div>
            </div>

            <div class="tab-pane fade" id="guidance-pane" role="tabpanel" aria-labelledby="guidance-tab" tabindex="0">
                <div class="tt-guidance-grid">
                    <div class="tt-report-card">
                        <h2>What to report</h2>
                        <p class="hint">Cover activities, milestones, stakeholder engagements, procurement updates, and research delivery.</p>
                    </div>
                    <div class="tt-report-card">
                        <h2>Evidence to attach</h2>
                        <p class="hint">Upload attendance sheets, signed minutes, concept notes, invoices, photos, publications, or implementation annexes.</p>
                    </div>
                    <div class="tt-report-card">
                        <h2>How review works</h2>
                        <p class="hint">Submitted reports are visible to the ATTP Secretariat for approval, revision requests, and fund oversight.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-think-tank.partials.shell>
