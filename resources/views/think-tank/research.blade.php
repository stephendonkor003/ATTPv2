@php
    $portalRouteParams = (auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
        ? ['think_tank_member_id' => $member->id]
        : [];
    $researchAction = route('think-tank.research.store', $portalRouteParams);
@endphp

@push('styles')
    <style>
        .tt-research-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(290px, .65fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        .tt-research-banner,
        .tt-research-side,
        .tt-research-stat,
        .tt-research-tabs,
        .tt-research-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
        }

        .tt-research-banner {
            min-height: 220px;
            padding: 26px;
            color: #fff;
            background:
                linear-gradient(120deg, rgba(15, 23, 42, .96), rgba(13, 148, 136, .84)),
                url("{{ asset('admin/assets/images/gallery/3.png') }}");
            background-size: cover;
            background-position: center;
        }

        .tt-research-banner h1 {
            max-width: 790px;
            margin: 10px 0;
            color: #fff;
            font-size: 30px;
            line-height: 1.18;
            letter-spacing: 0;
        }

        .tt-research-banner p {
            max-width: 840px;
            margin: 0;
            color: rgba(255, 255, 255, .86);
        }

        .tt-research-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, .28);
            background: rgba(255, 255, 255, .13);
            border-radius: 999px;
            padding: 7px 11px;
            font-weight: 850;
            font-size: 12px;
            text-transform: uppercase;
        }

        .tt-research-side {
            padding: 22px;
            display: grid;
            gap: 14px;
            align-content: center;
            background: linear-gradient(180deg, #f8fafc, #fff);
        }

        .tt-output-list {
            display: grid;
            gap: 9px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .tt-output-list li {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            background: #fff;
            color: #334155;
            font-weight: 800;
        }

        .tt-research-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .tt-research-stat {
            padding: 18px;
            min-height: 108px;
        }

        .tt-research-stat .label {
            color: #64748b;
            font-size: 13px;
            font-weight: 850;
        }

        .tt-research-stat .value {
            color: #0f172a;
            font-size: 25px;
            font-weight: 900;
            margin-top: 8px;
        }

        .tt-research-tabs {
            overflow: hidden;
        }

        .tt-research-tabs .nav {
            gap: 8px;
            padding: 14px 16px 0;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .tt-research-tabs .nav-link {
            border: 1px solid transparent;
            border-radius: 8px 8px 0 0;
            color: #475569;
            font-weight: 850;
            padding: 10px 14px;
        }

        .tt-research-tabs .nav-link.active {
            color: #0f172a;
            background: #fff;
            border-color: #e2e8f0 #e2e8f0 #fff;
            box-shadow: 0 -4px 10px rgba(15, 23, 42, .04);
        }

        .tt-research-tab-body {
            padding: 20px;
        }

        .tt-research-form-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, .55fr);
            gap: 20px;
            align-items: start;
        }

        .tt-research-card {
            padding: 18px;
            margin-bottom: 16px;
        }

        .tt-research-card h2 {
            color: #0f172a;
            font-size: 17px;
            font-weight: 900;
            margin: 0 0 4px;
        }

        .tt-research-card .hint {
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
            width: 100%;
            border: 1px solid #d8dee8;
            border-radius: 7px;
            padding: 11px 12px;
            background: #fff;
            color: #0f172a;
        }

        .tt-field textarea {
            min-height: 150px;
            resize: vertical;
        }

        .tt-research-note {
            border: 1px solid #ccfbf1;
            border-radius: 9px;
            padding: 16px;
            background: #f0fdfa;
            color: #134e4a;
        }

        .tt-research-note h3 {
            color: #134e4a;
            font-size: 15px;
            font-weight: 900;
            margin: 0 0 10px;
        }

        .tt-research-note ul {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .tt-research-note li {
            display: flex;
            gap: 9px;
            line-height: 1.45;
        }

        .tt-research-note i {
            margin-top: 3px;
            color: #0f766e;
        }

        .tt-research-table-wrap {
            overflow-x: auto;
        }

        .tt-research-table th {
            background: #f8fafc;
            color: #475569;
        }

        .tt-output-title {
            color: #0f172a;
            font-weight: 850;
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

        .tt-status.rejected,
        .tt-status.revisions_requested {
            background: #fee2e2;
            color: #991b1b;
        }

        .tt-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 9px;
            padding: 26px;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
        }

        @media (max-width: 1100px) {
            .tt-research-hero,
            .tt-research-form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .tt-research-stats,
            .tt-field-grid {
                grid-template-columns: 1fr;
            }

            .tt-research-banner h1 {
                font-size: 23px;
            }
        }
    </style>
@endpush

<x-think-tank.partials.shell :member="$member" title="Research Outputs">
    <section class="tt-research-hero">
        <div class="tt-research-banner">
            <span class="tt-research-kicker"><i class="feather-book-open"></i> Research Submission</span>
            <h1>Submit research, datasets, working papers, and policy outputs for Secretariat review.</h1>
            <p>
                Keep the ATTP Secretariat and funding partners informed with a clean record of every
                knowledge product produced by your think tank under the consortium.
            </p>
        </div>
        <aside class="tt-research-side">
            <div>
                <div class="tt-muted fw-bold mb-2">Output mix</div>
                <ul class="tt-output-list">
                    @forelse($outputTypes as $type)
                        <li>
                            <span>{{ str_replace('_', ' ', ucfirst($type->output_type ?? 'Research')) }}</span>
                            <strong>{{ number_format($type->total) }}</strong>
                        </li>
                    @empty
                        <li><span>No submitted output yet</span><strong>0</strong></li>
                    @endforelse
                </ul>
            </div>
            <a class="btn btn-primary" href="#submit-research">
                <i class="feather-upload-cloud me-1"></i> Submit research
            </a>
        </aside>
    </section>

    <section class="tt-research-stats">
        <div class="tt-research-stat">
            <div class="label">Total outputs</div>
            <div class="value">{{ number_format($researchStats['total']) }}</div>
        </div>
        <div class="tt-research-stat">
            <div class="label">Awaiting review</div>
            <div class="value">{{ number_format($researchStats['submitted']) }}</div>
        </div>
        <div class="tt-research-stat">
            <div class="label">Approved outputs</div>
            <div class="value">{{ number_format($researchStats['approved']) }}</div>
        </div>
        <div class="tt-research-stat">
            <div class="label">With attached files</div>
            <div class="value">{{ number_format($researchStats['with_files']) }}</div>
        </div>
    </section>

    <section class="tt-research-tabs" id="submit-research">
        <ul class="nav nav-tabs" id="ttResearchTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="research-submit-tab" data-bs-toggle="tab" data-bs-target="#research-submit-pane" type="button" role="tab" aria-controls="research-submit-pane" aria-selected="true">
                    <i class="feather-edit-3 me-1"></i> Submit Research
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="research-history-tab" data-bs-toggle="tab" data-bs-target="#research-history-pane" type="button" role="tab" aria-controls="research-history-pane" aria-selected="false">
                    <i class="feather-list me-1"></i> Research Submitted
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="research-guide-tab" data-bs-toggle="tab" data-bs-target="#research-guide-pane" type="button" role="tab" aria-controls="research-guide-pane" aria-selected="false">
                    <i class="feather-help-circle me-1"></i> Submission Guide
                </button>
            </li>
        </ul>

        <div class="tab-content tt-research-tab-body">
            <div class="tab-pane fade show active" id="research-submit-pane" role="tabpanel" aria-labelledby="research-submit-tab" tabindex="0">
                <form method="POST" action="{{ $researchAction }}" enctype="multipart/form-data">
                    @csrf
                    <div class="tt-research-form-grid">
                        <div>
                            <div class="tt-research-card">
                                <h2>Output details</h2>
                                <p class="hint">Capture the identity, type, and publication status of the research output.</p>
                                <div class="tt-field-grid">
                                    <div class="tt-field full">
                                        <label for="title">Research title</label>
                                        <input id="title" name="title" value="{{ old('title') }}" placeholder="Policy options for regional food systems financing" required>
                                    </div>
                                    <div class="tt-field">
                                        <label for="output_type">Output type</label>
                                        <select id="output_type" name="output_type" required>
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
                                        <small>Use this when the research is already published on your website or a journal platform.</small>
                                    </div>
                                    <div class="tt-field full">
                                        <label for="file">Attach research file</label>
                                        <input id="file" type="file" name="file">
                                        <small>Accepted: PDF, Word, Excel, PowerPoint, or ZIP up to 20MB.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="tt-research-card">
                                <h2>Abstract and relevance</h2>
                                <p class="hint">Help reviewers understand the output and its link to the consortium work.</p>
                                <div class="tt-field full">
                                    <label for="abstract">Abstract</label>
                                    <textarea id="abstract" name="abstract" placeholder="Summarize the research question, method, key findings, and policy relevance.">{{ old('abstract') }}</textarea>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <button type="reset" class="btn btn-light border">Clear form</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather-send me-1"></i> Submit to Secretariat
                                </button>
                            </div>
                        </div>

                        <aside class="tt-research-note">
                            <h3>Quality checklist</h3>
                            <ul>
                                <li><i class="feather-check-circle"></i><span>Use a clear title that matches the document or publication.</span></li>
                                <li><i class="feather-check-circle"></i><span>Choose the correct output type for partner reporting.</span></li>
                                <li><i class="feather-check-circle"></i><span>Attach the file or add a public URL so reviewers can access it.</span></li>
                                <li><i class="feather-check-circle"></i><span>Include an abstract that explains the policy value of the output.</span></li>
                            </ul>
                        </aside>
                    </div>
                </form>
            </div>

            <div class="tab-pane fade" id="research-history-pane" role="tabpanel" aria-labelledby="research-history-tab" tabindex="0">
                <div class="tt-research-table-wrap">
                    <table class="tt-research-table">
                        <thead>
                            <tr>
                                <th>Output</th>
                                <th>Type</th>
                                <th>File</th>
                                <th>Status</th>
                                <th>Published</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($outputs as $output)
                            <tr>
                                <td>
                                    <div class="tt-output-title">{{ $output->title }}</div>
                                    <div class="tt-muted">{{ \Illuminate\Support\Str::limit(strip_tags($output->abstract ?? 'No abstract provided.'), 95) }}</div>
                                </td>
                                <td>{{ str_replace('_', ' ', ucfirst($output->output_type)) }}</td>
                                <td>
                                    @if($output->file_path)
                                        <span class="tt-status approved">Attached</span>
                                    @elseif($output->external_url)
                                        <a href="{{ $output->external_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border">Open link</a>
                                    @else
                                        <span class="tt-muted">No file</span>
                                    @endif
                                </td>
                                <td><span class="tt-status {{ $output->status }}">{{ str_replace('_', ' ', $output->status) }}</span></td>
                                <td>{{ $output->published_on?->format('d M Y') ?? 'N/A' }}</td>
                                <td>{{ $output->submitted_at?->format('d M Y') ?? $output->created_at?->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="tt-empty">No research output has been submitted yet.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $outputs->links() }}
                </div>
            </div>

            <div class="tab-pane fade" id="research-guide-pane" role="tabpanel" aria-labelledby="research-guide-tab" tabindex="0">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="tt-research-card h-100">
                            <h2>What to submit</h2>
                            <p class="hint mb-0">Research papers, policy briefs, working papers, datasets, technical reports, and published articles linked to consortium work.</p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="tt-research-card h-100">
                            <h2>Review purpose</h2>
                            <p class="hint mb-0">The Secretariat uses these submissions to verify outputs, report to partners, and maintain a full knowledge product record.</p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="tt-research-card h-100">
                            <h2>Good abstract</h2>
                            <p class="hint mb-0">State the policy issue, method, key findings, target audience, and how the work contributes to ATTP objectives.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-think-tank.partials.shell>
