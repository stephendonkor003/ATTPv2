@php
    $currency = $member->consortium?->currency ?? 'USD';
    $recentReports = collect($reportRecords)->take(6);
@endphp


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
