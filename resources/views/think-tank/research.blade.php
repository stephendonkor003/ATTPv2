@php
    $isAdminView = auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin();
    $researchAction = route('think-tank.research.store', $portalRouteParams);
    $resetParams = $isAdminView ? ['think_tank_member_id' => $member->id] : [];
@endphp


<x-think-tank.partials.shell :member="$member" title="Research Outputs">
    <div class="tt-research-shell">
        <section class="tt-research-search">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h2 class="tt-search-title">Research Output Search</h2>
                    <p class="tt-search-subtitle">Select a think tank and run the search to generate a full research output profile.</p>
                </div>
                <a class="btn btn-dark fw-bold" href="{{ route('think-tank.research.download', $researchQueryParams) }}">
                    <i class="feather-download me-1"></i> Download Report
                </a>
            </div>

            <form method="GET" action="{{ route('think-tank.research') }}">
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
                        <label for="q">Search text</label>
                        <input id="q" name="q" value="{{ $keyword }}" placeholder="Title, abstract, link">
                    </div>
                    <div class="tt-field">
                        <label for="output_type">Type</label>
                        <select id="output_type" name="output_type">
                            <option value="">All types</option>
                            @foreach([
                                'research' => 'Research',
                                'policy_brief' => 'Policy brief',
                                'report' => 'Report',
                                'working_paper' => 'Working paper',
                                'dataset' => 'Dataset',
                                'publication' => 'Publication',
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected($typeFilter === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
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
                    <div class="tt-field">
                        <label for="filter_month">Month</label>
                        <input id="filter_month" type="month" name="filter_month" value="{{ $dashboardFilter['month'] }}">
                    </div>
                    <div class="tt-field">
                        <label for="date_from">From</label>
                        <input id="date_from" type="date" name="date_from" value="{{ $dashboardFilter['date_from'] }}">
                    </div>
                    <div class="tt-field">
                        <label for="date_to">To</label>
                        <input id="date_to" type="date" name="date_to" value="{{ $dashboardFilter['date_to'] }}">
                    </div>
                    <div class="tt-search-actions">
                        <button class="btn btn-primary fw-bold" type="submit">
                            <i class="feather-search me-1"></i> Run Search
                        </button>
                        <a class="btn btn-light border fw-bold" href="{{ route('think-tank.research', $resetParams) }}">Reset</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="tt-research-hero">
            <div class="tt-hero-grid">
                <div>
                    <span class="tt-kicker"><i class="feather-book-open"></i> {{ $dashboardFilter['label'] }} research profile</span>
                    <h1>{{ $member->name }}</h1>
                    <p class="mb-2">{{ $member->consortium?->name ?? 'Consortium not linked' }}{{ $member->country ? ' / ' . $member->country : '' }}</p>
                    <div class="tt-hero-meta">Research outputs, publication status, attached files, and review outcomes generated from selected think tank records.</div>
                </div>
                <div class="tt-hero-facts">
                    <div class="tt-hero-fact">
                        <span>Most common type</span>
                        <strong>{{ $outputTypes->first()?->output_type ? ucfirst(str_replace('_', ' ', $outputTypes->first()->output_type)) : 'No output yet' }}</strong>
                    </div>
                    <div class="tt-hero-fact">
                        <span>Files attached</span>
                        <strong>{{ number_format($researchStats['with_files']) }}</strong>
                    </div>
                    <div class="tt-hero-fact">
                        <span>External links</span>
                        <strong>{{ number_format($researchStats['with_links']) }}</strong>
                    </div>
                    <div class="tt-hero-fact">
                        <span>Generated</span>
                        <strong>{{ now()->format('M d, Y H:i') }}</strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="tt-kpi-grid">
            <article class="tt-kpi-card">
                <span class="tt-kpi-icon"><i class="feather-book-open"></i></span>
                <div class="tt-kpi-value">{{ number_format($researchStats['total']) }}</div>
                <div class="tt-kpi-label">Outputs in selected view</div>
            </article>
            <article class="tt-kpi-card green">
                <span class="tt-kpi-icon"><i class="feather-check-circle"></i></span>
                <div class="tt-kpi-value">{{ number_format($researchStats['approved']) }}</div>
                <div class="tt-kpi-label">Approved outputs</div>
            </article>
            <article class="tt-kpi-card amber">
                <span class="tt-kpi-icon"><i class="feather-paperclip"></i></span>
                <div class="tt-kpi-value">{{ number_format($researchStats['with_files']) }}</div>
                <div class="tt-kpi-label">Outputs with attached files</div>
            </article>
            <article class="tt-kpi-card teal">
                <span class="tt-kpi-icon"><i class="feather-globe"></i></span>
                <div class="tt-kpi-value">{{ number_format($researchStats['published']) }}</div>
                <div class="tt-kpi-label">Publication dates captured</div>
            </article>
        </section>

        <section class="tt-main-grid">
            <div>
                <div class="tt-research-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Graphs and Research Analysis</h2>
                            <p>Output mix, review status, submission timeline, access format, and publication readiness.</p>
                        </div>
                    </div>
                    <div class="tt-chart-grid">
                        <div class="tt-chart-box">
                            <h3>Output Type Mix</h3>
                            <div id="ttResearchTypeChart"></div>
                        </div>
                        <div class="tt-chart-box">
                            <h3>Review Status</h3>
                            <div id="ttResearchStatusChart"></div>
                        </div>
                        <div class="tt-chart-box">
                            <h3>Monthly Submissions</h3>
                            <div id="ttResearchTimelineChart"></div>
                        </div>
                        <div class="tt-chart-box">
                            <h3>File and Link Coverage</h3>
                            <div id="ttResearchAccessChart"></div>
                        </div>
                    </div>
                </div>

                <section class="tt-research-tabs" id="research-workspace">
                    <ul class="nav nav-tabs" id="ttResearchTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="research-history-tab" data-bs-toggle="tab" data-bs-target="#research-history-pane" type="button" role="tab" aria-controls="research-history-pane" aria-selected="true">
                                <i class="feather-list me-1"></i> Research Register
                            </button>
                        </li>
                        @can('think_tank.research.submit')
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="research-submit-tab" data-bs-toggle="tab" data-bs-target="#research-submit-pane" type="button" role="tab" aria-controls="research-submit-pane" aria-selected="false">
                                    <i class="feather-edit-3 me-1"></i> Submit Research
                                </button>
                            </li>
                        @endcan
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="research-guide-tab" data-bs-toggle="tab" data-bs-target="#research-guide-pane" type="button" role="tab" aria-controls="research-guide-pane" aria-selected="false">
                                <i class="feather-help-circle me-1"></i> Submission Guide
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content tt-tab-body">
                        <div class="tab-pane fade show active" id="research-history-pane" role="tabpanel" aria-labelledby="research-history-tab" tabindex="0">
                            <div class="tt-table-wrap">
                                <table class="tt-table">
                                    <thead>
                                    <tr>
                                        <th>Output</th>
                                        <th>Type</th>
                                        <th>File or link</th>
                                        <th>Status</th>
                                        <th>Published</th>
                                        <th>Annex B QASC</th>
                                        <th>Submitted</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($outputs as $output)
                                        <tr>
                                            <td>
                                                <strong>{{ $output->title }}</strong>
                                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit(strip_tags($output->abstract ?? 'No abstract provided.'), 100) }}</div>
                                            </td>
                                            <td>{{ str_replace('_', ' ', ucfirst($output->output_type)) }}</td>
                                            <td>
                                                @if($output->file_path)
                                                    <span class="tt-status approved">Attached</span>
                                                @elseif($output->external_url)
                                                    <a href="{{ $output->external_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border">Open link</a>
                                                @else
                                                    <span class="text-muted">No file</span>
                                                @endif
                                            </td>
                                            <td><span class="tt-status {{ $output->status }}">{{ str_replace('_', ' ', $output->status) }}</span></td>
                                            <td>{{ $output->published_on?->format('d M Y') ?? 'N/A' }}</td>
                                            <td>
                                                @if($output->qasc_pdf_path || $output->qasc_data)
                                                    <a class="btn btn-sm btn-light border fw-bold" target="_blank" href="{{ route('think-tank.research.qasc.preview', array_merge($portalRouteParams, ['output' => $output])) }}">
                                                        <i class="feather-eye me-1"></i> Preview
                                                    </a>
                                                    @if($output->qasc_email_sent_at)
                                                        <div class="text-muted small mt-1">Emailed {{ $output->qasc_email_sent_at->format('d M Y') }}</div>
                                                    @endif
                                                @else
                                                    <span class="text-muted">Not captured</span>
                                                @endif
                                            </td>
                                            <td>{{ $output->submitted_at?->format('d M Y') ?? $output->created_at?->format('d M Y') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7"><div class="tt-empty">No research outputs match the selected search.</div></td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">{{ $outputs->links() }}</div>
                        </div>

                        @can('think_tank.research.submit')
                            <div class="tab-pane fade" id="research-submit-pane" role="tabpanel" aria-labelledby="research-submit-tab" tabindex="0">
                                <form method="POST" action="{{ $researchAction }}" enctype="multipart/form-data">
                                    @csrf
                                    <div class="tt-form-grid">
                                        <div>
                                            <div class="tt-form-card">
                                                <h3>Output details</h3>
                                                <p>Capture the identity, type, and publication status of the research output.</p>
                                                <div class="tt-field-grid">
                                                    <div class="tt-field full">
                                                        <label for="title">Research title</label>
                                                        <input id="title" name="title" value="{{ old('title') }}" placeholder="Policy options for regional food systems financing" required>
                                                    </div>
                                                    <div class="tt-field">
                                                        <label for="submit_output_type">Output type</label>
                                                        <select id="submit_output_type" name="output_type" required>
                                                            @foreach([
                                                                'research' => 'Research',
                                                                'policy_brief' => 'Policy brief',
                                                                'report' => 'Report',
                                                                'working_paper' => 'Working paper',
                                                                'dataset' => 'Dataset',
                                                                'publication' => 'Publication',
                                                            ] as $value => $label)
                                                                <option value="{{ $value }}" @selected(old('output_type', 'research') === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="tt-field">
                                                        <label for="published_on">Publication date</label>
                                                        <input id="published_on" type="date" name="published_on" value="{{ old('published_on') }}">
                                                    </div>
                                                    <div class="tt-field full">
                                                        <label for="external_url">External link</label>
                                                        <input id="external_url" type="url" name="external_url" value="{{ old('external_url') }}" placeholder="https://example.org/research-output">
                                                    </div>
                                                    <div class="tt-field full">
                                                        <label for="file">Attach research file</label>
                                                        <input id="file" type="file" name="file">
                                                    </div>
                                                    <div class="tt-field full">
                                                        <label for="abstract">Abstract</label>
                                                        <textarea id="abstract" name="abstract" placeholder="Summarize the research question, method, key findings, and policy relevance.">{{ old('abstract') }}</textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="tt-form-card">
                                                <h3>Annex B: Quality Assurance Self-Certification</h3>
                                                <p>Complete the publication-readiness certification required for ATTP-supported research outputs.</p>
                                                <div class="tt-qasc-banner">
                                                    The generated Annex B PDF will open after submission and will be emailed to the think tank with the uploaded electronic signatures.
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

                                                <div class="tt-table-wrap mt-3">
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

                                                <div class="tt-field-grid mt-3">
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

                                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                <button type="reset" class="btn btn-light border">Clear form</button>
                                                <button type="submit" class="btn btn-primary"><i class="feather-send me-1"></i> Submit to Secretariat</button>
                                            </div>
                                        </div>

                                        <aside class="tt-side-note">
                                            <h3>Quality checklist</h3>
                                            <p>Make sure the Secretariat can review and report the output.</p>
                                            <ul class="tt-check-list">
                                                <li><i class="feather-check-circle me-1"></i> Use a clear title that matches the document.</li>
                                                <li><i class="feather-check-circle me-1"></i> Choose the correct output type.</li>
                                                <li><i class="feather-check-circle me-1"></i> Attach the file or add a public URL.</li>
                                                <li><i class="feather-check-circle me-1"></i> Explain the policy value in the abstract.</li>
                                            </ul>
                                        </aside>
                                    </div>
                                </form>
                            </div>
                        @endcan

                        <div class="tab-pane fade" id="research-guide-pane" role="tabpanel" aria-labelledby="research-guide-tab" tabindex="0">
                            <div class="tt-chart-grid">
                                <div class="tt-form-card">
                                    <h3>What to submit</h3>
                                    <p>Research papers, policy briefs, working papers, datasets, technical reports, and published articles linked to consortium work.</p>
                                </div>
                                <div class="tt-form-card">
                                    <h3>Review purpose</h3>
                                    <p>The Secretariat uses these outputs to verify delivery, report to partners, and maintain the knowledge product record.</p>
                                </div>
                                <div class="tt-form-card">
                                    <h3>Good abstract</h3>
                                    <p>State the policy issue, method, key findings, target audience, and contribution to ATTP objectives.</p>
                                </div>
                                <div class="tt-form-card">
                                    <h3>Access standards</h3>
                                    <p>Attach a file or provide a stable external link so reviewers can inspect the product.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside>
                <div class="tt-research-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Output Mix</h2>
                            <p>Research products grouped by output type.</p>
                        </div>
                    </div>
                    <div class="tt-status-list">
                        @forelse($outputTypes as $type)
                            <div class="tt-status-row">
                                <span>{{ ucfirst(str_replace('_', ' ', $type->output_type ?? 'Research')) }}</span>
                                <strong>{{ number_format($type->total) }}</strong>
                            </div>
                        @empty
                            <div class="tt-empty">No output types yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="tt-research-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Review Status</h2>
                            <p>Current review status split for selected outputs.</p>
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

                <div class="tt-research-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Access Summary</h2>
                            <p>File and publication access readiness.</p>
                        </div>
                    </div>
                    <div class="tt-status-list">
                        <div class="tt-status-row"><span>Attached files</span><strong>{{ number_format($researchStats['with_files']) }}</strong></div>
                        <div class="tt-status-row"><span>External links</span><strong>{{ number_format($researchStats['with_links']) }}</strong></div>
                        <div class="tt-status-row"><span>Published date set</span><strong>{{ number_format($researchStats['published']) }}</strong></div>
                        <div class="tt-status-row"><span>Missing publication date</span><strong>{{ number_format($researchStats['draft_unpublished']) }}</strong></div>
                    </div>
                </div>

                <div class="tt-research-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Quick Links</h2>
                            <p>Move between research reporting surfaces.</p>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <a class="btn btn-primary" href="#research-workspace"><i class="feather-edit-3 me-1"></i> Open research workspace</a>
                        <a class="btn btn-light border" href="{{ route('think-tank.dashboard', $portalRouteParams) }}"><i class="feather-activity me-1"></i> Dashboard overview</a>
                        <a class="btn btn-dark" href="{{ route('think-tank.research.download', $researchQueryParams) }}"><i class="feather-download me-1"></i> Download research PDF</a>
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
            const baseOptions = {
                chart: { toolbar: { show: false }, fontFamily: 'Inter, Arial, sans-serif' },
                dataLabels: { enabled: false },
                colors: ['#0f766e', '#2563eb', '#f59e0b', '#ef4444'],
                grid: { borderColor: '#e2e8f0' },
                legend: { position: 'bottom' }
            };

            new ApexCharts(document.querySelector('#ttResearchTypeChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'donut', height: 250 },
                series: chartData.types.values,
                labels: chartData.types.labels
            }).render();

            new ApexCharts(document.querySelector('#ttResearchStatusChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'donut', height: 250 },
                series: chartData.status.values,
                labels: chartData.status.labels
            }).render();

            new ApexCharts(document.querySelector('#ttResearchTimelineChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'area', height: 250 },
                stroke: { curve: 'smooth', width: 3 },
                fill: { opacity: .18 },
                series: [{ name: 'Research outputs', data: chartData.timeline.values }],
                xaxis: { categories: chartData.timeline.labels }
            }).render();

            new ApexCharts(document.querySelector('#ttResearchAccessChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'bar', height: 250 },
                series: [{ name: 'Outputs', data: chartData.access.values }],
                xaxis: { categories: chartData.access.labels },
                plotOptions: { bar: { horizontal: true, borderRadius: 5 } }
            }).render();
        });
    </script>
@endpush
