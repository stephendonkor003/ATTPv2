@php
    $currency = $member->consortium?->currency ?? 'USD';
    $recentReports = collect($reportRecords)->take(6);
@endphp

@push('styles')
    <style>
        .tt-reports-page {
            display: grid;
            gap: 1rem;
        }

        .tt-reports-intro,
        .tt-report-form,
        .tt-report-guide,
        .tt-report-history {
            border: 1px solid var(--tt-border, #dfe8e3);
            border-radius: 10px;
            background: var(--tt-surface, #fff);
            box-shadow: none;
        }

        .tt-reports-intro {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0 0 1rem;
            border-width: 0 0 1px;
            border-radius: 0;
            background: transparent;
        }

        .tt-reports-eyebrow {
            color: var(--tt-brand, #176b4b);
            font-size: .72rem;
            font-weight: 850;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .tt-reports-intro h1,
        .tt-report-form h2,
        .tt-report-guide h2,
        .tt-report-history h2 {
            color: var(--tt-ink, #17241d);
            font-weight: 850;
        }

        .tt-reports-intro h1 {
            margin: .2rem 0 .3rem;
            font-size: clamp(1.45rem, 2vw, 1.9rem);
        }

        .tt-reports-intro p,
        .tt-panel-copy {
            margin: 0;
            color: var(--tt-muted, #607066);
            font-size: .86rem;
        }

        .tt-report-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(270px, .34fr);
            gap: 1rem;
            align-items: start;
        }

        .tt-report-form,
        .tt-report-guide,
        .tt-report-history {
            padding: 1.1rem;
        }

        .tt-report-form h2,
        .tt-report-guide h2,
        .tt-report-history h2 {
            margin: 0 0 .2rem;
            font-size: 1rem;
        }

        .tt-report-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .78rem;
            margin-top: 1rem;
        }

        .tt-field {
            display: grid;
            gap: .32rem;
        }

        .tt-field.is-wide {
            grid-column: 1 / -1;
        }

        .tt-field label {
            color: #42544a;
            font-size: .72rem;
            font-weight: 800;
        }

        .tt-field input,
        .tt-field select,
        .tt-field textarea {
            width: 100%;
            min-height: 42px;
            border: 1px solid var(--tt-border-strong, #cbd9d1);
            border-radius: 10px;
            background: #fff;
            color: var(--tt-ink, #17241d);
            font: inherit;
            font-size: .8rem;
            padding: .62rem .7rem;
        }

        .tt-field textarea {
            min-height: 96px;
            resize: vertical;
        }

        .tt-upload-box {
            position: relative;
            display: grid;
            min-height: 150px;
            place-items: center;
            padding: 1.1rem;
            border: 1.5px dashed #a9c3b5;
            border-radius: 13px;
            background: #f7faf8;
            color: #496056;
            text-align: center;
            transition: border-color .16s ease, background .16s ease;
        }

        .tt-upload-box:hover,
        .tt-upload-box:focus-within {
            border-color: var(--tt-brand, #176b4b);
            background: #f0f7f3;
        }

        .tt-upload-box input {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            opacity: 0;
        }

        .tt-upload-icon {
            display: inline-flex;
            width: 42px;
            height: 42px;
            align-items: center;
            justify-content: center;
            margin-bottom: .5rem;
            border-radius: 12px;
            background: var(--tt-brand-soft, #e7f2ec);
            color: var(--tt-brand, #176b4b);
        }

        .tt-file-selection {
            display: block;
            margin-top: .3rem;
            color: var(--tt-muted, #607066);
            font-size: .7rem;
        }

        .tt-guide-list,
        .tt-report-list {
            display: grid;
            gap: .65rem;
            margin: .8rem 0 0;
            padding: 0;
            list-style: none;
        }

        .tt-guide-list li {
            display: flex;
            gap: .5rem;
            padding: .7rem;
            border-radius: 11px;
            background: #f7faf8;
            color: #4f6157;
            font-size: .75rem;
        }

        .tt-guide-list i {
            flex: 0 0 auto;
            margin-top: .12rem;
            color: var(--tt-brand, #176b4b);
        }

        .tt-due-note {
            margin-top: .8rem;
            padding: .75rem;
            border-radius: 11px;
            background: #fff8e7;
            color: #765b18;
            font-size: .74rem;
        }

        .tt-report-list li {
            padding: .72rem;
            border: 1px solid #e5ece8;
            border-radius: 11px;
        }

        .tt-report-list strong {
            display: block;
            color: var(--tt-ink, #17241d);
            font-size: .78rem;
        }

        .tt-report-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem .65rem;
            margin-top: .2rem;
            color: var(--tt-muted, #607066);
            font-size: .68rem;
        }

        .tt-report-status {
            display: inline-flex;
            margin-top: .4rem;
            padding: .24rem .5rem;
            border-radius: 999px;
            background: #edf4f0;
            color: #416050;
            font-size: .64rem;
            font-weight: 800;
            text-transform: capitalize;
        }

        @media (max-width: 991.98px) {
            .tt-report-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .tt-report-fields {
                grid-template-columns: 1fr;
            }

            .tt-field.is-wide {
                grid-column: auto;
            }
        }

        @media (max-width: 575.98px) {
            .tt-reports-intro {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
@endpush

<x-think-tank.partials.shell :member="$member" title="Report Uploads">
    <div class="tt-reports-page">
        <header class="tt-reports-intro">
            <div>
                <div class="tt-reports-eyebrow">Reporting workspace</div>
                <h1>Upload an activity report</h1>
                <p>Send implementation progress and supporting documents from {{ $member->name }} to the ATTP Secretariat.</p>
            </div>
        </header>

        <section class="tt-report-grid">
            <div class="tt-report-form">
                <h2>Report details</h2>
                <p class="tt-panel-copy">Complete the short form and attach the final report or supporting evidence.</p>

                <form method="POST" action="{{ route('think-tank.report-uploads.store', $portalRouteParams) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="tt-report-fields">
                        <div class="tt-field is-wide">
                            <label for="report-title">Report title <span class="text-danger">*</span></label>
                            <input id="report-title" name="title" value="{{ old('title') }}" maxlength="255" placeholder="Monthly implementation progress report" required>
                        </div>
                        <div class="tt-field">
                            <label for="report-workplan">Related workplan</label>
                            <select id="report-workplan" name="workplan_id">
                                <option value="">Not linked to a workplan</option>
                                @foreach ($workplans as $workplan)
                                    <option value="{{ $workplan->id }}" @selected((string) old('workplan_id') === (string) $workplan->id)>{{ $workplan->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="tt-field">
                            <label for="report-progress">Progress achieved (%)</label>
                            <input id="report-progress" type="number" name="progress_percent" value="{{ old('progress_percent') }}" min="0" max="100" step="0.01" placeholder="75">
                        </div>
                        <div class="tt-field">
                            <label for="report-start">Reporting period start</label>
                            <input id="report-start" type="date" name="reporting_period_start" value="{{ old('reporting_period_start', now()->startOfMonth()->toDateString()) }}">
                        </div>
                        <div class="tt-field">
                            <label for="report-end">Reporting period end</label>
                            <input id="report-end" type="date" name="reporting_period_end" value="{{ old('reporting_period_end', now()->endOfMonth()->toDateString()) }}">
                        </div>
                        <div class="tt-field">
                            <label for="report-spend">Funds spent ({{ $currency }})</label>
                            <input id="report-spend" type="number" name="funds_spent" value="{{ old('funds_spent') }}" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="tt-field">
                            <label for="report-file-title">Document title</label>
                            <input id="report-file-title" name="evidence_title" value="{{ old('evidence_title') }}" maxlength="255" placeholder="Final activity report">
                        </div>
                        <div class="tt-field is-wide">
                            <label for="report-summary">Progress summary</label>
                            <textarea id="report-summary" name="summary" placeholder="Summarise the main progress made during this period.">{{ old('summary') }}</textarea>
                        </div>
                        <div class="tt-field">
                            <label for="report-achievements">Key achievements</label>
                            <textarea id="report-achievements" name="achievements" placeholder="What was completed successfully?">{{ old('achievements') }}</textarea>
                        </div>
                        <div class="tt-field">
                            <label for="report-challenges">Challenges</label>
                            <textarea id="report-challenges" name="challenges" placeholder="What affected delivery?">{{ old('challenges') }}</textarea>
                        </div>
                        <div class="tt-field is-wide">
                            <label for="report-next-steps">Next steps</label>
                            <textarea id="report-next-steps" name="next_steps" placeholder="What will happen in the next reporting period?">{{ old('next_steps') }}</textarea>
                        </div>
                        <div class="tt-field is-wide">
                            <label for="report-files">Report files <span class="text-danger">*</span></label>
                            <div class="tt-upload-box">
                                <input id="report-files" type="file" name="evidence_files[]" multiple required data-report-files aria-describedby="report-file-selection">
                                <div>
                                    <span class="tt-upload-icon"><i class="feather-upload-cloud" aria-hidden="true"></i></span>
                                    <strong class="d-block">Choose report files</strong>
                                    <span class="tt-file-selection" id="report-file-selection" data-file-selection>PDF, Word, Excel, images, or supporting evidence. Maximum 20 MB per file.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button class="btn btn-success" type="submit">
                            <i class="feather-send me-1" aria-hidden="true"></i> Submit report
                        </button>
                    </div>
                </form>
            </div>

            <aside class="d-grid gap-3">
                <div class="tt-report-guide">
                    <h2>Before submitting</h2>
                    <ul class="tt-guide-list">
                        <li><i class="feather-check-circle" aria-hidden="true"></i><span>Use the correct reporting dates and workplan.</span></li>
                        <li><i class="feather-check-circle" aria-hidden="true"></i><span>Give a short, factual progress summary.</span></li>
                        <li><i class="feather-check-circle" aria-hidden="true"></i><span>Attach the final report and useful evidence.</span></li>
                        <li><i class="feather-check-circle" aria-hidden="true"></i><span>Review the information before submitting.</span></li>
                    </ul>
                    @if ($monthlyReportDue)
                        <div class="tt-due-note">
                            <i class="feather-calendar me-1" aria-hidden="true"></i>
                            Current monthly due date: <strong>{{ $monthlyReportDue->format('d M Y') }}</strong>
                        </div>
                    @endif
                </div>

                <div class="tt-report-history">
                    <h2>Recent uploads</h2>
                    <p class="tt-panel-copy">Latest reports from this think tank.</p>
                    <ul class="tt-report-list">
                        @forelse ($recentReports as $report)
                            <li>
                                <strong>{{ $report->title }}</strong>
                                <div class="tt-report-meta">
                                    <span>{{ $report->submitted_at?->format('d M Y') ?? $report->created_at?->format('d M Y') }}</span>
                                    <span>{{ number_format($report->evidence?->count() ?? 0) }} {{ \Illuminate\Support\Str::plural('file', $report->evidence?->count() ?? 0) }}</span>
                                </div>
                                <span class="tt-report-status">{{ str_replace('_', ' ', $report->status ?: 'submitted') }}</span>
                            </li>
                        @empty
                            <li class="text-muted small">No report has been uploaded yet.</li>
                        @endforelse
                    </ul>
                </div>
            </aside>
        </section>
    </div>
</x-think-tank.partials.shell>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.querySelector('[data-report-files]');
            const output = document.querySelector('[data-file-selection]');

            if (!input || !output) return;

            input.addEventListener('change', function () {
                const files = Array.from(input.files || []);
                output.textContent = files.length
                    ? files.map((file) => file.name).join(', ')
                    : 'PDF, Word, Excel, images, or supporting evidence. Maximum 20 MB per file.';
            });
        });
    </script>
@endpush
