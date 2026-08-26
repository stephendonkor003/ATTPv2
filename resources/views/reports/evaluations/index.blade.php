@extends('layouts.app')

@section('title', 'Evaluation Reports')

@section('content')
    @php
        $totalSubmissions = $submissions->count();
        $procurementCount = $submissions->pluck('procurement_id')->filter()->unique()->count();
        $evaluatorCount = $submissions->pluck('evaluator_id')->filter()->unique()->count();
        $averageScore = $submissions->whereNotNull('overall_score')->avg('overall_score');
        $latestSubmission = $submissions->first();
        $eoiProcurementIds = $eoiProcurements
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(string) $id => true]);
    @endphp

    <main class="nxl-container evaluation-report-page">
        <div class="page-header">
            <div class="page-header-left">
                <h4 class="fw-bold mb-1">
                    <i class="feather-file-text me-2"></i>
                    Evaluation Reports
                </h4>
                <p class="mb-0">Submissions, procurements, and consolidated evaluation records.</p>
            </div>
            <div class="report-header-actions">
                <a href="{{ route('reports.evaluations.consolidated') }}" class="btn btn-light btn-sm">
                    <i class="feather-eye me-1"></i> View Consolidated
                </a>
                <a href="{{ route('reports.evaluations.consolidated.pdf') }}" class="btn btn-success btn-sm">
                    <i class="feather-download me-1"></i> Consolidated PDF
                </a>
            </div>
        </div>

        <div class="report-kpi-grid">
            <div class="report-kpi report-kpi--total">
                <span>Submitted Reports</span>
                <strong>{{ number_format($totalSubmissions) }}</strong>
            </div>
            <div class="report-kpi report-kpi--procurement">
                <span>Procurements</span>
                <strong>{{ number_format($procurementCount) }}</strong>
            </div>
            <div class="report-kpi report-kpi--evaluators">
                <span>Evaluators</span>
                <strong>{{ number_format($evaluatorCount) }}</strong>
            </div>
            <div class="report-kpi report-kpi--score">
                <span>Average Numeric Score</span>
                <strong>{{ $averageScore !== null ? number_format($averageScore, 2) : '-' }}</strong>
            </div>
        </div>

        <section class="eoi-report-library" aria-labelledby="eoiReportHeading">
            <div class="eoi-report-library__head">
                <div>
                    <span class="report-eyebrow">Expression of Interest</span>
                    <h5 id="eoiReportHeading">EOI Qualification Gate Reports</h5>
                    <p>Applicant-level panel outcomes, evaluator coverage, veto decisions, and Technical Evaluation eligibility.</p>
                </div>
                @if ($eoiProcurements->isNotEmpty())
                    <label class="eoi-report-search" for="eoiProcurementSearch">
                        <i class="feather-search" aria-hidden="true"></i>
                        <span class="visually-hidden">Search EOI procurements</span>
                        <input id="eoiProcurementSearch" type="search" placeholder="Search title or reference">
                    </label>
                @endif
            </div>

            @if ($eoiProcurements->isNotEmpty())
                <div class="eoi-procurement-grid" id="eoiProcurementGrid">
                    @foreach ($eoiProcurements as $eoiProcurement)
                        @php
                            $eoiStats = $eoiProcurementStats->get((string) $eoiProcurement->id, []);
                            $eoiSearchText = Str::lower(trim(
                                $eoiProcurement->title.' '.($eoiProcurement->reference_no ?? '')
                            ));
                        @endphp
                        <article class="eoi-procurement-card" data-eoi-card data-search="{{ $eoiSearchText }}">
                            <div class="eoi-procurement-card__top">
                                <span class="eoi-method-badge"><i class="feather-check-square"></i> EOI</span>
                                <span class="eoi-reference">{{ $eoiProcurement->reference_no ?: 'No reference' }}</span>
                            </div>
                            <div class="eoi-procurement-card__body">
                                <h6>
                                    <a href="{{ route('reports.evaluations.eoi.procurement', $eoiProcurement) }}">
                                        {{ $eoiProcurement->title }}
                                    </a>
                                </h6>
                                <p>
                                    @if (collect($eoiStats['templates'] ?? [])->isNotEmpty())
                                        {{ collect($eoiStats['templates'])->join(', ') }}
                                    @else
                                        Expression of Interest qualification panel
                                    @endif
                                </p>
                            </div>
                            <div class="eoi-procurement-card__metrics">
                                <span><strong>{{ number_format($eoiStats['applicants'] ?? 0) }}</strong> Applicants</span>
                                <span><strong>{{ number_format($eoiStats['panel_members'] ?? 0) }}</strong> Panel members</span>
                                <span><strong>{{ number_format($eoiStats['completed_reports'] ?? 0) }}</strong> Reports filed</span>
                            </div>
                            <div class="eoi-procurement-card__actions">
                                <a href="{{ route('reports.evaluations.eoi.procurement', $eoiProcurement) }}" class="eoi-open-link">
                                    Open qualification report <i class="feather-arrow-right"></i>
                                </a>
                                <a href="{{ route('reports.evaluations.eoi.procurement.pdf', $eoiProcurement) }}"
                                   class="eoi-pdf-link" title="Download EOI qualification PDF"
                                   aria-label="Download {{ $eoiProcurement->title }} EOI qualification PDF">
                                    <i class="feather-download"></i>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="eoi-no-results d-none" id="eoiNoResults">
                    <i class="feather-search"></i>
                    <strong>No matching EOI procurement</strong>
                    <span>Try a different title or reference.</span>
                </div>
            @else
                <div class="eoi-library-empty">
                    <span class="eoi-library-empty__icon"><i class="feather-inbox"></i></span>
                    <div>
                        <strong>No EOI procurements are ready for reporting</strong>
                        <p>They will appear here as soon as an Expression of Interest template is linked, assigned, or submitted.</p>
                    </div>
                </div>
            @endif
        </section>

        <section class="report-spotlight">
            <div>
                <span class="report-eyebrow">Consolidated Report</span>
                <h5>All Evaluation Results</h5>
                <p>Cross-procurement summary for submitted evaluations.</p>
            </div>
            <div class="report-action-group">
                <a href="{{ route('reports.evaluations.consolidated') }}" class="btn btn-outline-primary">
                    <i class="feather-eye me-1"></i> View Report
                </a>
                <a href="{{ route('reports.evaluations.consolidated.pdf') }}" class="btn btn-success">
                    <i class="feather-download me-1"></i> Download PDF
                </a>
            </div>
        </section>

        <div class="report-panel-grid">
            <section class="report-panel">
                <div class="report-panel__head">
                    <div class="report-icon report-icon--procurement">
                        <i class="feather-briefcase"></i>
                    </div>
                    <div>
                        <span class="report-eyebrow">Procurement Report</span>
                        <h5>Procurement Evaluation Summary</h5>
                    </div>
                </div>

                <label for="procurementSelect" class="report-label">Select Procurement</label>
                <select id="procurementSelect" class="form-control report-select">
                    <option value="">Choose procurement</option>
                    @foreach ($procurements as $procurement)
                        @php
                            $isEoiProcurement = $eoiProcurementIds->has((string) $procurement->id);
                            $procurementViewUrl = $isEoiProcurement
                                ? route('reports.evaluations.eoi.procurement', $procurement)
                                : route('reports.evaluations.procurement', $procurement);
                            $procurementPdfUrl = $isEoiProcurement
                                ? route('reports.evaluations.eoi.procurement.pdf', $procurement)
                                : route('reports.evaluations.procurement.pdf', $procurement);
                        @endphp
                        <option
                            value="{{ $procurement->slug }}"
                            data-title="{{ $procurement->title }}"
                            data-reference="{{ $procurement->reference_no ?? 'No reference' }}"
                            data-view-url="{{ $procurementViewUrl }}"
                            data-pdf-url="{{ $procurementPdfUrl }}"
                        >
                            {{ $procurement->title }}
                            @if ($procurement->reference_no)
                                - {{ $procurement->reference_no }}
                            @endif
                        </option>
                    @endforeach
                </select>

                <div class="selected-report-preview" id="procurementPreview">
                    <span>No procurement selected</span>
                    <strong>-</strong>
                    <small>-</small>
                </div>

                <div class="report-action-group">
                    <a id="procurementViewBtn" href="#" class="btn btn-outline-primary disabled" aria-disabled="true">
                        <i class="feather-eye me-1"></i> View Report
                    </a>
                    <a id="procurementPdfBtn" href="#" class="btn btn-success disabled" aria-disabled="true">
                        <i class="feather-download me-1"></i> Download PDF
                    </a>
                </div>
            </section>

            <section class="report-panel report-panel--accent">
                <div class="report-panel__head">
                    <div class="report-icon report-icon--submission">
                        <i class="feather-user-check"></i>
                    </div>
                    <div>
                        <span class="report-eyebrow">Individual Submission Report</span>
                        <h5>Single Evaluation Record</h5>
                    </div>
                </div>

                <label for="submissionSelect" class="report-label">Select Evaluation Submission</label>
                <select id="submissionSelect" class="form-control report-select">
                    <option value="">Choose submission</option>
                    @foreach ($submissions as $submission)
                        @php
                            $applicantName = $submission->applicant?->display_name ?? 'Applicant';
                            $submissionCode = $submission->applicant?->procurement_submission_code ?? 'No code';
                            $procurementTitle = $submission->procurement?->title ?? 'N/A';
                            $evaluatorName = $submission->evaluator?->name ?? 'Unassigned evaluator';
                            $result = $submission->evaluation?->usesNumericScoring()
                                ? (($submission->overall_score !== null ? number_format($submission->overall_score, 2) : 'No').' score')
                                : (($submission->evaluation?->typeLabel() ?? 'Categorical').' decisions');
                            $submittedAt = optional($submission->submitted_at)->format('d M Y') ?? 'No date';
                        @endphp
                        <option
                            value="{{ $submission->id }}"
                            data-name="{{ $applicantName }}"
                            data-code="{{ $submissionCode }}"
                            data-procurement="{{ $procurementTitle }}"
                            data-evaluator="{{ $evaluatorName }}"
                            data-result="{{ $result }}"
                            data-submitted="{{ $submittedAt }}"
                        >
                            {{ $applicantName }} - {{ $submissionCode }} - {{ $procurementTitle }}
                        </option>
                    @endforeach
                </select>

                <div class="selected-report-preview" id="submissionPreview">
                    <span>No submission selected</span>
                    <strong>-</strong>
                    <small>-</small>
                </div>

                <div class="report-action-group">
                    <a id="submissionViewBtn" href="#" class="btn btn-outline-primary disabled" aria-disabled="true">
                        <i class="feather-eye me-1"></i> View Report
                    </a>
                    <a id="submissionPdfBtn" href="#" class="btn btn-success disabled" aria-disabled="true">
                        <i class="feather-download me-1"></i> Download PDF
                    </a>
                </div>
            </section>
        </div>

        @if ($latestSubmission)
            @php
                $latestName = $latestSubmission->applicant?->display_name ?? 'Applicant';
                $latestCode = $latestSubmission->applicant?->procurement_submission_code ?? 'No code';
            @endphp
            <div class="report-footnote">
                <i class="feather-clock"></i>
                Latest submitted report: <strong>{{ $latestName }}</strong>
                <span>{{ $latestCode }}</span>
                <span>{{ optional($latestSubmission->submitted_at)->format('d M Y') }}</span>
            </div>
        @endif
    </main>
@endsection

@push('styles')
    <style>
        .evaluation-report-page {
            --ink: #172033;
            --muted: #667085;
            --line: #d9e2ec;
            --soft: #f6f8fb;
            --green: #0f766e;
            --blue: #2563eb;
            --orange: #b45309;
            --red: #b42318;
        }

        .report-header-actions,
        .report-action-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .report-kpi-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            margin-bottom: 16px;
        }

        .report-kpi {
            background: #fff;
            border: 1px solid var(--line);
            border-left: 4px solid var(--ink);
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .04);
            padding: 15px 16px;
        }

        .report-kpi span,
        .report-eyebrow,
        .report-label {
            color: var(--muted);
            display: block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .report-kpi strong {
            color: var(--ink);
            display: block;
            font-size: 25px;
            line-height: 1.1;
            margin-top: 8px;
        }

        .report-kpi--total { border-left-color: var(--blue); }
        .report-kpi--procurement { border-left-color: var(--green); }
        .report-kpi--evaluators { border-left-color: var(--orange); }
        .report-kpi--score { border-left-color: var(--red); }

        .eoi-report-library {
            background: linear-gradient(135deg, #111827 0%, #1f2937 58%, #312e81 100%);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 12px;
            box-shadow: 0 18px 38px rgba(15, 23, 42, .16);
            margin-bottom: 16px;
            overflow: hidden;
            padding: 20px;
        }

        .eoi-report-library__head {
            align-items: flex-end;
            display: flex;
            gap: 20px;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .eoi-report-library .report-eyebrow { color: #c4b5fd; }

        .eoi-report-library__head h5 {
            color: #fff;
            font-size: 20px;
            font-weight: 800;
            margin: 5px 0;
        }

        .eoi-report-library__head p {
            color: #cbd5e1;
            margin: 0;
            max-width: 720px;
        }

        .eoi-report-search {
            align-items: center;
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 8px;
            color: #cbd5e1;
            display: flex;
            flex: 0 1 320px;
            gap: 9px;
            padding: 0 11px;
        }

        .eoi-report-search input {
            background: transparent;
            border: 0;
            color: #fff;
            min-height: 40px;
            outline: 0;
            width: 100%;
        }

        .eoi-report-search input::placeholder { color: #94a3b8; }

        .eoi-procurement-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        .eoi-procurement-card {
            background: #fff;
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .eoi-procurement-card:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, .2);
            transform: translateY(-2px);
        }

        .eoi-procurement-card__top,
        .eoi-procurement-card__actions {
            align-items: center;
            display: flex;
            justify-content: space-between;
        }

        .eoi-procurement-card__top { padding: 14px 15px 0; }

        .eoi-method-badge {
            background: #ede9fe;
            border-radius: 999px;
            color: #6d28d9;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .06em;
            padding: 5px 8px;
        }

        .eoi-reference {
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
        }

        .eoi-procurement-card__body { padding: 14px 15px; }

        .eoi-procurement-card__body h6 {
            font-size: 15px;
            font-weight: 800;
            line-height: 1.4;
            margin: 0;
        }

        .eoi-procurement-card__body h6 a { color: #172033; }
        .eoi-procurement-card__body h6 a:hover { color: #6d28d9; }

        .eoi-procurement-card__body p {
            color: #64748b;
            font-size: 12px;
            line-height: 1.45;
            margin: 7px 0 0;
        }

        .eoi-procurement-card__metrics {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            border-top: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
        }

        .eoi-procurement-card__metrics span {
            color: #64748b;
            font-size: 10px;
            padding: 10px 8px;
            text-align: center;
        }

        .eoi-procurement-card__metrics span + span { border-left: 1px solid #e2e8f0; }

        .eoi-procurement-card__metrics strong {
            color: #172033;
            display: block;
            font-size: 16px;
            margin-bottom: 2px;
        }

        .eoi-procurement-card__actions { margin-top: auto; padding: 12px 15px; }

        .eoi-open-link {
            color: #5b21b6;
            font-size: 12px;
            font-weight: 800;
        }

        .eoi-pdf-link {
            align-items: center;
            background: #ecfdf5;
            border-radius: 7px;
            color: #047857;
            display: inline-flex;
            height: 32px;
            justify-content: center;
            width: 32px;
        }

        .eoi-no-results,
        .eoi-library-empty {
            align-items: center;
            background: rgba(255, 255, 255, .08);
            border: 1px dashed rgba(255, 255, 255, .2);
            border-radius: 9px;
            color: #e2e8f0;
            display: flex;
            gap: 12px;
            padding: 18px;
        }

        .eoi-no-results { flex-direction: column; text-align: center; }
        .eoi-no-results span, .eoi-library-empty p { color: #94a3b8; margin: 3px 0 0; }

        .eoi-library-empty__icon {
            align-items: center;
            background: rgba(255, 255, 255, .1);
            border-radius: 9px;
            display: inline-flex;
            flex: 0 0 44px;
            height: 44px;
            justify-content: center;
        }

        .report-spotlight,
        .report-panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, .05);
        }

        .report-spotlight {
            align-items: center;
            border-left: 4px solid var(--green);
            display: flex;
            gap: 18px;
            justify-content: space-between;
            margin-bottom: 16px;
            padding: 18px;
        }

        .report-spotlight h5,
        .report-panel h5 {
            color: var(--ink);
            font-size: 17px;
            font-weight: 800;
            margin: 5px 0 0;
        }

        .report-spotlight p {
            color: var(--muted);
            margin: 5px 0 0;
        }

        .report-panel-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .report-panel {
            display: flex;
            flex-direction: column;
            min-height: 100%;
            padding: 18px;
        }

        .report-panel--accent {
            border-top: 3px solid var(--blue);
        }

        .report-panel__head {
            align-items: center;
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
        }

        .report-icon {
            align-items: center;
            border-radius: 8px;
            display: inline-flex;
            flex: 0 0 42px;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .report-icon i {
            font-size: 19px;
        }

        .report-icon--procurement {
            background: #ecfdf5;
            color: var(--green);
        }

        .report-icon--submission {
            background: #eff6ff;
            color: var(--blue);
        }

        .report-label {
            margin-bottom: 7px;
        }

        .report-select {
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            color: var(--ink);
            min-height: 42px;
        }

        .report-select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .selected-report-preview {
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 8px;
            margin: 14px 0 16px;
            min-height: 86px;
            padding: 13px 14px;
        }

        .selected-report-preview span {
            color: var(--muted);
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .selected-report-preview strong {
            color: var(--ink);
            display: block;
            font-size: 15px;
            line-height: 1.35;
        }

        .selected-report-preview small {
            color: #475467;
            display: block;
            font-size: 12px;
            line-height: 1.4;
            margin-top: 6px;
        }

        .report-panel .report-action-group {
            margin-top: auto;
        }

        .report-footnote {
            align-items: center;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--muted);
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 16px;
            padding: 12px 14px;
        }

        .report-footnote strong {
            color: var(--ink);
        }

        .report-footnote span {
            background: #eef2f6;
            border-radius: 999px;
            color: #344054;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 8px;
        }

        @media (max-width: 992px) {
            .report-panel-grid {
                grid-template-columns: 1fr;
            }

            .report-spotlight {
                align-items: flex-start;
                flex-direction: column;
            }

            .eoi-report-library__head {
                align-items: stretch;
                flex-direction: column;
            }

            .eoi-report-search { flex-basis: auto; }
        }

        @media (max-width: 640px) {
            .report-header-actions,
            .report-action-group {
                width: 100%;
            }

            .report-action-group .btn,
            .report-header-actions .btn {
                flex: 1 1 100%;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const procurementBaseUrl = @json(url('reports/evaluations/procurement'));
            const submissionBaseUrl = @json(url('reports/evaluations/submission'));

            const setButtonState = (viewButton, pdfButton, viewUrl, pdfUrl = null) => {
                const isActive = Boolean(viewUrl);
                [viewButton, pdfButton].forEach((button) => {
                    button.classList.toggle('disabled', !isActive);
                    button.setAttribute('aria-disabled', isActive ? 'false' : 'true');
                });

                viewButton.href = isActive ? viewUrl : '#';
                pdfButton.href = isActive ? (pdfUrl || `${viewUrl}/pdf`) : '#';
            };

            const setPreview = (preview, label, title, meta) => {
                preview.querySelector('span').textContent = label;
                preview.querySelector('strong').textContent = title;
                preview.querySelector('small').textContent = meta;
            };

            const procurementSelect = document.getElementById('procurementSelect');
            const procurementPreview = document.getElementById('procurementPreview');
            const procurementView = document.getElementById('procurementViewBtn');
            const procurementPdf = document.getElementById('procurementPdfBtn');

            const submissionSelect = document.getElementById('submissionSelect');
            const submissionPreview = document.getElementById('submissionPreview');
            const submissionView = document.getElementById('submissionViewBtn');
            const submissionPdf = document.getElementById('submissionPdfBtn');

            procurementSelect.addEventListener('change', function () {
                const selected = this.options[this.selectedIndex];
                const value = this.value;

                if (!value) {
                    setPreview(procurementPreview, 'No procurement selected', '-', '-');
                    setButtonState(procurementView, procurementPdf, null);
                    return;
                }

                setPreview(
                    procurementPreview,
                    'Selected procurement',
                    selected.dataset.title || selected.textContent.trim(),
                    selected.dataset.reference || '-'
                );
                setButtonState(
                    procurementView,
                    procurementPdf,
                    selected.dataset.viewUrl || `${procurementBaseUrl}/${value}`,
                    selected.dataset.pdfUrl || null
                );
            });

            submissionSelect.addEventListener('change', function () {
                const selected = this.options[this.selectedIndex];
                const value = this.value;

                if (!value) {
                    setPreview(submissionPreview, 'No submission selected', '-', '-');
                    setButtonState(submissionView, submissionPdf, null);
                    return;
                }

                setPreview(
                    submissionPreview,
                    selected.dataset.code || 'Submission',
                    selected.dataset.name || selected.textContent.trim(),
                    `${selected.dataset.procurement || '-'} | ${selected.dataset.evaluator || '-'} | ${selected.dataset.result || '-'} | ${selected.dataset.submitted || '-'}`
                );
                setButtonState(submissionView, submissionPdf, `${submissionBaseUrl}/${value}`);
            });

            const eoiSearch = document.getElementById('eoiProcurementSearch');
            const eoiCards = Array.from(document.querySelectorAll('[data-eoi-card]'));
            const eoiNoResults = document.getElementById('eoiNoResults');

            eoiSearch?.addEventListener('input', function () {
                const term = this.value.trim().toLowerCase();
                let visible = 0;

                eoiCards.forEach((card) => {
                    const matches = !term || (card.dataset.search || '').includes(term);
                    card.classList.toggle('d-none', !matches);
                    visible += matches ? 1 : 0;
                });

                eoiNoResults?.classList.toggle('d-none', visible > 0);
            });
        });
    </script>
@endpush
