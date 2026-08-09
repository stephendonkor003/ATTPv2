@php
    $currency = 'USD';
    $reportAction = route('think-tank.reports.store', $portalRouteParams);
    $researchAction = route('think-tank.research.store', $portalRouteParams);
    $activeUploadTab = old('upload_tab', request('tab', 'report'));
    $isResearchTab = $activeUploadTab === 'research';
@endphp


<x-think-tank.partials.shell :member="$member" title="Upload Report and Finding" :show-portal-tabs="false">
    <div class="tt-upload-shell">
        <section class="tt-upload-hero">
            <span class="tt-upload-kicker"><i class="feather-upload-cloud"></i> Secretariat submission</span>
            <h1>Upload Report and Finding</h1>
            <p class="mb-0">
                Submit activity reports or Annex B research findings for {{ $member->name }}.
            </p>
        </section>

        <section class="tt-upload-tabs">
            <ul class="nav nav-tabs" id="ttUploadTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $isResearchTab ? '' : 'active' }}" id="upload-report-tab" data-bs-toggle="tab" data-bs-target="#upload-report-pane" type="button" role="tab" aria-controls="upload-report-pane" aria-selected="{{ $isResearchTab ? 'false' : 'true' }}">
                        <i class="feather-file-text me-1"></i> Upload Reports
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $isResearchTab ? 'active' : '' }}" id="upload-research-tab" data-bs-toggle="tab" data-bs-target="#upload-research-pane" type="button" role="tab" aria-controls="upload-research-pane" aria-selected="{{ $isResearchTab ? 'true' : 'false' }}">
                        <i class="feather-book-open me-1"></i> Upload Research Finding
                    </button>
                </li>
            </ul>

            <div class="tab-content tt-tab-body">
                <div class="tab-pane fade {{ $isResearchTab ? '' : 'show active' }}" id="upload-report-pane" role="tabpanel" aria-labelledby="upload-report-tab" tabindex="0">
                    <section class="tt-upload-grid">
                        <div class="tt-upload-panel">
                            <h2>Report upload</h2>
                            <p>Attach the report document and complete the fields needed for Secretariat review.</p>

                            <form method="POST" action="{{ $reportAction }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="upload_tab" value="report">

                                <div class="tt-form-section">
                                    <div class="tt-field-grid">
                                        <div class="tt-field full">
                                            <label for="report_title">Report title</label>
                                            <input id="report_title" name="title" value="{{ old('upload_tab') === 'report' ? old('title') : '' }}" placeholder="Monthly implementation progress report" required>
                                        </div>

                                        <div class="tt-field">
                                            <label for="report_workplan_id">Workplan</label>
                                            <select id="report_workplan_id" name="workplan_id">
                                                <option value="">Select workplan</option>
                                                @foreach($workplans as $workplan)
                                                    <option value="{{ $workplan->id }}" @selected(old('upload_tab') === 'report' && (string) old('workplan_id') === (string) $workplan->id)>{{ $workplan->title }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="tt-field">
                                            <label for="report_progress_percent">Progress percentage</label>
                                            <input id="report_progress_percent" type="number" min="0" max="100" step="0.01" name="progress_percent" value="{{ old('upload_tab') === 'report' ? old('progress_percent') : '' }}" placeholder="75">
                                        </div>

                                        <div class="tt-field">
                                            <label for="reporting_period_start">Reporting period start</label>
                                            <input id="reporting_period_start" type="date" name="reporting_period_start" value="{{ old('upload_tab') === 'report' ? old('reporting_period_start', now()->startOfMonth()->toDateString()) : now()->startOfMonth()->toDateString() }}">
                                        </div>

                                        <div class="tt-field">
                                            <label for="reporting_period_end">Reporting period end</label>
                                            <input id="reporting_period_end" type="date" name="reporting_period_end" value="{{ old('upload_tab') === 'report' ? old('reporting_period_end', now()->endOfMonth()->toDateString()) : now()->endOfMonth()->toDateString() }}">
                                        </div>

                                        <div class="tt-field">
                                            <label for="report_funds_spent">Funds spent this period ({{ $currency }})</label>
                                            <input id="report_funds_spent" type="number" min="0" step="0.01" name="funds_spent" value="{{ old('upload_tab') === 'report' ? old('funds_spent') : '' }}" placeholder="0.00">
                                        </div>

                                        <div class="tt-field">
                                            <label for="report_evidence_title">File title</label>
                                            <input id="report_evidence_title" name="evidence_title" value="{{ old('upload_tab') === 'report' ? old('evidence_title') : '' }}" placeholder="Report document">
                                        </div>

                                        <div class="tt-field full">
                                            <label for="report_evidence_files">Upload report files</label>
                                            <input id="report_evidence_files" type="file" name="evidence_files[]" multiple required>
                                        </div>

                                        <div class="tt-field full">
                                            <label for="report_summary">Summary</label>
                                            <textarea id="report_summary" name="summary" placeholder="Summarise the implementation progress.">{{ old('upload_tab') === 'report' ? old('summary') : '' }}</textarea>
                                        </div>

                                        <div class="tt-field">
                                            <label for="report_achievements">Achievements</label>
                                            <textarea id="report_achievements" name="achievements">{{ old('upload_tab') === 'report' ? old('achievements') : '' }}</textarea>
                                        </div>

                                        <div class="tt-field">
                                            <label for="report_challenges">Challenges</label>
                                            <textarea id="report_challenges" name="challenges">{{ old('upload_tab') === 'report' ? old('challenges') : '' }}</textarea>
                                        </div>

                                        <div class="tt-field full">
                                            <label for="report_next_steps">Next steps</label>
                                            <textarea id="report_next_steps" name="next_steps">{{ old('upload_tab') === 'report' ? old('next_steps') : '' }}</textarea>
                                        </div>
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
                                    <li><i class="feather-check-circle me-1"></i> Attach the report document.</li>
                                    <li><i class="feather-check-circle me-1"></i> Use the correct reporting period.</li>
                                    <li><i class="feather-check-circle me-1"></i> Add a clear progress summary.</li>
                                    <li><i class="feather-check-circle me-1"></i> Enter spending in {{ $currency }} where applicable.</li>
                                </ul>
                            </div>

                            <div class="tt-upload-recent">
                                <h2>Recent reports</h2>
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

                <div class="tab-pane fade {{ $isResearchTab ? 'show active' : '' }}" id="upload-research-pane" role="tabpanel" aria-labelledby="upload-research-tab" tabindex="0">
                    <section class="tt-upload-grid">
                        <div class="tt-upload-panel">
                            <h2>Research finding upload</h2>
                            <p>Complete the Annex B research submission fields and upload the signed evidence.</p>

                            <form method="POST" action="{{ $researchAction }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="upload_tab" value="research">

                                <div class="tt-form-section">
                                    <h3>Research output</h3>
                                    <p>These fields identify the research finding submitted for Secretariat review.</p>
                                    <div class="tt-field-grid">
                                        <div class="tt-field full">
                                            <label for="research_title">Output title</label>
                                            <input id="research_title" name="title" value="{{ old('upload_tab') === 'research' ? old('title') : '' }}" placeholder="Research finding title" required>
                                        </div>

                                        <div class="tt-field">
                                            <label for="research_output_type">Output type</label>
                                            <select id="research_output_type" name="output_type" required>
                                                @foreach([
                                                    'research' => 'Research',
                                                    'policy_brief' => 'Policy brief',
                                                    'report' => 'Report',
                                                    'working_paper' => 'Working paper',
                                                    'dataset' => 'Dataset',
                                                    'publication' => 'Publication',
                                                ] as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('upload_tab') === 'research' && old('output_type', 'research') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="tt-field">
                                            <label for="research_published_on">Publication date</label>
                                            <input id="research_published_on" type="date" name="published_on" value="{{ old('upload_tab') === 'research' ? old('published_on') : '' }}">
                                        </div>

                                        <div class="tt-field full">
                                            <label for="research_external_url">External link</label>
                                            <input id="research_external_url" type="url" name="external_url" value="{{ old('upload_tab') === 'research' ? old('external_url') : '' }}" placeholder="https://example.org/research-finding">
                                        </div>

                                        <div class="tt-field full">
                                            <label for="research_file">Attach research finding file</label>
                                            <input id="research_file" type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip">
                                        </div>

                                        <div class="tt-field full">
                                            <label for="research_abstract">Abstract / key finding</label>
                                            <textarea id="research_abstract" name="abstract" placeholder="Summarize the research question, method, key findings, and policy relevance.">{{ old('upload_tab') === 'research' ? old('abstract') : '' }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="tt-form-section">
                                    <h3>Annex B: ATTP Quality Assurance Self-Certification</h3>
                                    <p>Complete the publication-readiness certification required for ATTP-supported research outputs.</p>
                                    <div class="tt-qasc-banner">
                                        The Annex B PDF will be generated after submission and emailed with the uploaded electronic signatures.
                                    </div>

                                    <div class="tt-field-grid">
                                        <div class="tt-field full">
                                            <label for="qasc_lead_authors">Lead author(s)</label>
                                            <input id="qasc_lead_authors" name="qasc[lead_authors]" value="{{ old('qasc.lead_authors') }}" placeholder="Full name(s) of lead author(s)" required>
                                        </div>

                                        <div class="tt-field">
                                            <label for="qasc_lead_think_tank">Lead think tank</label>
                                            <input id="qasc_lead_think_tank" name="qasc[lead_think_tank]" value="{{ old('qasc.lead_think_tank', $member->name) }}" required>
                                        </div>

                                        <div class="tt-field">
                                            <label for="qasc_consortium">Consortium</label>
                                            <select id="qasc_consortium" name="qasc[consortium]" required>
                                                <option value="">Select consortium</option>
                                                @foreach($qascConsortiumOptions as $option)
                                                    <option value="{{ $option }}" @selected(old('qasc.consortium') === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="tt-field">
                                            <label for="qasc_track_classification">Track classification</label>
                                            <select id="qasc_track_classification" name="qasc[track_classification]" required>
                                                <option value="">Select track</option>
                                                @foreach($qascTrackOptions as $option)
                                                    <option value="{{ $option }}" @selected(old('qasc.track_classification') === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="tt-field">
                                            <label for="qasc_original_language">Original language</label>
                                            <select id="qasc_original_language" name="qasc[original_language]" required>
                                                <option value="">Select language</option>
                                                @foreach($qascLanguageOptions as $option)
                                                    <option value="{{ $option }}" @selected(old('qasc.original_language', 'English') === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="tt-field">
                                            <label for="qasc_intended_publication_date">Intended publication date</label>
                                            <input id="qasc_intended_publication_date" type="date" name="qasc[intended_publication_date]" value="{{ old('qasc.intended_publication_date', old('published_on')) }}" required>
                                        </div>
                                    </div>

                                    <div class="tt-table-wrap">
                                        <table class="tt-table">
                                            <thead>
                                            <tr>
                                                <th>Checklist item</th>
                                                <th>Confirmed / N/A</th>
                                                <th>Signatory name</th>
                                                <th>Date</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($qascChecklist as $key => $item)
                                                <tr>
                                                    <td>
                                                        <strong>{{ $item['number'] }}. {{ $item['title'] }}</strong>
                                                        <div class="text-muted small">{{ $item['description'] }}</div>
                                                        <div class="text-muted small">{{ $item['applies_to'] }}</div>
                                                    </td>
                                                    <td>
                                                        <select name="qasc[checklist][{{ $key }}][status]" required>
                                                            <option value="confirmed" @selected(old("qasc.checklist.$key.status", 'confirmed') === 'confirmed')>Confirmed</option>
                                                            <option value="not_applicable" @selected(old("qasc.checklist.$key.status") === 'not_applicable')>N/A</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input name="qasc[checklist][{{ $key }}][signed_by]" value="{{ old("qasc.checklist.$key.signed_by") }}" placeholder="Name" required>
                                                    </td>
                                                    <td>
                                                        <input type="date" name="qasc[checklist][{{ $key }}][signed_date]" value="{{ old("qasc.checklist.$key.signed_date", now()->toDateString()) }}" required>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="tt-form-section">
                                    <h3>Electronic signatures</h3>
                                    <p>Type the names and dates, then upload the author and think tank representative signatures.</p>
                                    <div class="tt-field-grid">
                                        <div class="tt-field">
                                            <label for="qasc_lead_author_name">Lead author name</label>
                                            <input id="qasc_lead_author_name" name="qasc[lead_author_name]" value="{{ old('qasc.lead_author_name') }}" required>
                                        </div>

                                        <div class="tt-field">
                                            <label for="qasc_lead_author_date">Lead author signature date</label>
                                            <input id="qasc_lead_author_date" type="date" name="qasc[lead_author_date]" value="{{ old('qasc.lead_author_date', now()->toDateString()) }}" required>
                                        </div>

                                        <div class="tt-field full">
                                            <label for="qasc_author_signature">Lead author electronic signature</label>
                                            <input id="qasc_author_signature" type="file" name="qasc_author_signature" accept="image/png,image/jpeg,image/webp" required>
                                        </div>

                                        <div class="tt-field">
                                            <label for="qasc_lead_think_tank_representative_name">Lead think tank representative name</label>
                                            <input id="qasc_lead_think_tank_representative_name" name="qasc[lead_think_tank_representative_name]" value="{{ old('qasc.lead_think_tank_representative_name') }}" required>
                                        </div>

                                        <div class="tt-field">
                                            <label for="qasc_lead_think_tank_date">Lead think tank signature date</label>
                                            <input id="qasc_lead_think_tank_date" type="date" name="qasc[lead_think_tank_date]" value="{{ old('qasc.lead_think_tank_date', now()->toDateString()) }}" required>
                                        </div>

                                        <div class="tt-field full">
                                            <label for="qasc_think_tank_signature">Lead think tank electronic signature</label>
                                            <input id="qasc_think_tank_signature" type="file" name="qasc_think_tank_signature" accept="image/png,image/jpeg,image/webp" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 flex-wrap mt-3">
                                    <a class="btn btn-light border" href="{{ route('think-tank.research', $portalRouteParams) }}">View research</a>
                                    <button type="reset" class="btn btn-light border">Clear form</button>
                                    <button type="submit" class="btn btn-primary"><i class="feather-send me-1"></i> Submit Research Finding</button>
                                </div>
                            </form>
                        </div>

                        <aside class="d-grid gap-3">
                            <div class="tt-upload-note">
                                <h2>Annex B requirements</h2>
                                <p>The fields follow the ATTP QASC research submission layout.</p>
                                <ul class="tt-check-list">
                                    <li><i class="feather-check-circle me-1"></i> Consortium, track, and language are controlled dropdowns.</li>
                                    <li><i class="feather-check-circle me-1"></i> Each checklist item needs a status, name, and date.</li>
                                    <li><i class="feather-check-circle me-1"></i> Author and think tank electronic signatures are required.</li>
                                    <li><i class="feather-check-circle me-1"></i> The generated Annex B PDF opens after submission.</li>
                                </ul>
                            </div>

                            <div class="tt-upload-recent">
                                <h2>Recent research findings</h2>
                                <p>Latest research records for this think tank.</p>
                                <ul class="tt-recent-list">
                                    @forelse($researchRecords->take(5) as $output)
                                        <li>
                                            <div class="fw-bold">{{ $output->title }}</div>
                                            <div class="text-muted small mb-1">
                                                {{ $output->submitted_at?->format('d M Y') ?? $output->created_at?->format('d M Y') }}
                                                | {{ ucfirst(str_replace('_', ' ', $output->output_type ?? 'research')) }}
                                            </div>
                                            <span class="tt-status {{ $output->status }}">{{ str_replace('_', ' ', $output->status) }}</span>
                                        </li>
                                    @empty
                                        <li class="text-muted">No research findings uploaded yet.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </aside>
                    </section>
                </div>
            </div>
        </section>
    </div>
</x-think-tank.partials.shell>
