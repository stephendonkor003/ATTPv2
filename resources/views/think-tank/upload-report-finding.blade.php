@php
    $currency = 'USD';
    $isAdminView = auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin();
    $reportAction = route('think-tank.reports.store', $portalRouteParams);
@endphp

@push('styles')
    <style>
        .think-tank-workspace > .card.shadow-sm.border-0.overflow-hidden.mb-4 {
            display: none;
        }

        .tt-upload-shell {
            display: grid;
            gap: 18px;
        }

        .tt-upload-hero,
        .tt-upload-panel,
        .tt-upload-note,
        .tt-upload-recent {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }

        .tt-upload-hero {
            padding: 24px;
            color: #f8fafc;
            background:
                linear-gradient(120deg, rgba(15, 23, 42, .96), rgba(37, 99, 235, .9)),
                linear-gradient(45deg, rgba(245, 158, 11, .2), rgba(14, 165, 233, .12));
        }

        .tt-upload-hero h1 {
            color: #ffffff;
            font-size: 30px;
            font-weight: 900;
            line-height: 1.15;
            margin: 10px 0 8px;
        }

        .tt-upload-kicker {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid rgba(248, 250, 252, .32);
            border-radius: 999px;
            background: rgba(248, 250, 252, .12);
            color: #fde68a;
            font-size: 12px;
            font-weight: 900;
            padding: 7px 11px;
        }

        .tt-upload-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(300px, .38fr);
            gap: 18px;
            align-items: start;
        }

        .tt-upload-panel,
        .tt-upload-note,
        .tt-upload-recent {
            padding: 18px;
        }

        .tt-upload-panel h2,
        .tt-upload-note h2,
        .tt-upload-recent h2 {
            color: #0f172a;
            font-size: 18px;
            font-weight: 900;
            margin: 0 0 4px;
        }

        .tt-upload-panel p,
        .tt-upload-note p,
        .tt-upload-recent p {
            color: #64748b;
            font-size: 13px;
            margin: 0 0 14px;
        }

        .tt-field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .tt-field {
            display: grid;
            gap: 6px;
        }

        .tt-field.full {
            grid-column: 1 / -1;
        }

        .tt-field label {
            color: #334155;
            font-size: 12px;
            font-weight: 850;
        }

        .tt-field input,
        .tt-field select,
        .tt-field textarea {
            min-height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            color: #0f172a;
            padding: 9px 10px;
            width: 100%;
        }

        .tt-field textarea {
            min-height: 118px;
            resize: vertical;
        }

        .tt-upload-note {
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .tt-check-list,
        .tt-recent-list {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .tt-check-list {
            color: #1e3a8a;
        }

        .tt-recent-list li {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
        }

        .tt-recent-list li:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .tt-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 8px;
            background: #e0f2fe;
            color: #075985;
            font-size: 12px;
            font-weight: 850;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .tt-status.approved { background: #dcfce7; color: #166534; }
        .tt-status.revisions_requested,
        .tt-status.rejected { background: #fee2e2; color: #991b1b; }

        @media (max-width: 1100px) {
            .tt-upload-grid,
            .tt-field-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

<x-think-tank.partials.shell :member="$member" title="Upload Report and Finding">
    <div class="tt-upload-shell">
        <section class="tt-upload-hero">
            <span class="tt-upload-kicker"><i class="feather-upload-cloud"></i> Secretariat submission</span>
            <h1>Upload Report and Finding</h1>
            <p class="mb-0">
                Submit a report file, findings, progress details, and supporting evidence for {{ $member->name }}.
            </p>
        </section>

        <section class="tt-upload-grid">
            <div class="tt-upload-panel">
                <h2>Report upload</h2>
                <p>Attach the report or finding document and complete the fields needed for Secretariat review.</p>

                <form method="POST" action="{{ $reportAction }}" enctype="multipart/form-data">
                    @csrf
                    <div class="tt-field-grid">
                        <div class="tt-field full">
                            <label for="title">Report title</label>
                            <input id="title" name="title" value="{{ old('title') }}" placeholder="Monthly report and key findings" required>
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
                            <label for="funds_spent">Funds spent this period ({{ $currency }})</label>
                            <input id="funds_spent" type="number" min="0" step="0.01" name="funds_spent" value="{{ old('funds_spent') }}" placeholder="0.00">
                        </div>

                        <div class="tt-field">
                            <label for="evidence_title">File title</label>
                            <input id="evidence_title" name="evidence_title" value="{{ old('evidence_title') }}" placeholder="Report and findings document">
                        </div>

                        <div class="tt-field full">
                            <label for="evidence_files">Upload report and finding files</label>
                            <input id="evidence_files" type="file" name="evidence_files[]" multiple required>
                        </div>

                        <div class="tt-field full">
                            <label for="summary">Key findings / summary</label>
                            <textarea id="summary" name="summary" placeholder="Summarise the main findings and implementation progress.">{{ old('summary') }}</textarea>
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

                    <div class="d-flex justify-content-end gap-2 flex-wrap mt-3">
                        <a class="btn btn-light border" href="{{ route('think-tank.reports', $portalRouteParams) }}">View reports</a>
                        <button type="reset" class="btn btn-light border">Clear form</button>
                        <button type="submit" class="btn btn-primary"><i class="feather-send me-1"></i> Submit Upload</button>
                    </div>
                </form>
            </div>

            <aside class="d-grid gap-3">
                <div class="tt-upload-note">
                    <h2>Before submitting</h2>
                    <p>Make sure the upload is ready for Secretariat review.</p>
                    <ul class="tt-check-list">
                        <li><i class="feather-check-circle me-1"></i> Attach the report or findings document.</li>
                        <li><i class="feather-check-circle me-1"></i> Use the correct reporting period.</li>
                        <li><i class="feather-check-circle me-1"></i> Add the key findings in the summary field.</li>
                        <li><i class="feather-check-circle me-1"></i> Enter spending in {{ $currency }} where applicable.</li>
                    </ul>
                </div>

                <div class="tt-upload-recent">
                    <h2>Recent uploads</h2>
                    <p>Latest report records for this think tank.</p>
                    <ul class="tt-recent-list">
                        @forelse($reportRecords->take(5) as $report)
                            <li>
                                <div class="fw-bold">{{ $report->title }}</div>
                                <div class="text-muted small mb-1">
                                    {{ $report->submitted_at?->format('d M Y') ?? $report->created_at?->format('d M Y') }}
                                    @if($report->evidence->count())
                                        | {{ number_format($report->evidence->count()) }} file(s)
                                    @endif
                                </div>
                                <span class="tt-status {{ $report->status }}">{{ str_replace('_', ' ', $report->status) }}</span>
                            </li>
                        @empty
                            <li class="text-muted">No report uploads yet.</li>
                        @endforelse
                    </ul>
                </div>
            </aside>
        </section>
    </div>
</x-think-tank.partials.shell>
