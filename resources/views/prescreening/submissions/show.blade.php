@extends('layouts.app')

@section('title', 'Prescreening Evaluation')

@section('content')
    @php
        $officialName = $submission->display_name;
        $officialEmail = $submission->values->firstWhere('field_key', 'official_email')?->value ?: $submission->submitter?->email;
        $submittedValues = $submission->values->keyBy('field_key');
        $formFields = $submission->form?->fields ?? collect();
        $criteria = $template->sections->isNotEmpty()
            ? $template->sections->flatMap(fn ($section) => $section->criteria)->values()
            : $template->criteria;
        $totalCriteria = $result?->total_criteria ?? $criteria->count();
        $passedCriteria = $result?->passed_criteria ?? $evaluations->filter(fn ($evaluation) => (bool) $evaluation->is_passed)->count();
        $failedCriteria = $result?->failed_criteria ?? $evaluations->filter(fn ($evaluation) => ! (bool) $evaluation->is_passed)->count();
        $completionPercent = $totalCriteria > 0 ? min(100, round((($passedCriteria + $failedCriteria) / $totalCriteria) * 100, 1)) : 0;
        $passPercent = $totalCriteria > 0 ? min(100, round(($passedCriteria / $totalCriteria) * 100, 1)) : 0;
        $status = $submission->status ?? 'submitted';
        $statusColors = [
            'submitted' => 'secondary',
            'prescreen_passed' => 'success',
            'prescreen_failed' => 'danger',
            'under_review' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
        ];
        $statusColor = $statusColors[$status] ?? 'info';
        $sectionGroups = $template->sections->isNotEmpty()
            ? $template->sections
            : collect([(object) [
                'name' => 'Prescreening Criteria',
                'description' => null,
                'criteria' => $template->criteria,
            ]]);
    @endphp

    <main class="nxl-container prescreening-show-page">
        <div class="page-header">
            <div class="page-header-left">
                <h4 class="fw-bold mb-1">
                    <i class="feather-check-square me-2"></i>
                    Prescreening Evaluation
                </h4>
                <p class="mb-0">{{ $officialName }} | {{ $submission->procurement_submission_code }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('prescreening.submissions.index') }}" class="btn btn-light btn-sm">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
                <a href="{{ route('prescreening.submissions.pdf', $submission) }}" class="btn btn-success btn-sm">
                    <i class="feather-download me-1"></i> PDF
                </a>
                <a href="{{ route('prescreening.submissions.anonymised-pdf', $submission) }}" class="btn btn-dark btn-sm">
                    <i class="feather-shield me-1"></i> Anonymised PDF
                </a>
            </div>
        </div>

        @if (!$canEdit)
            <div class="alert alert-info report-alert">
                <i class="feather-lock me-1"></i>
                This evaluation is locked and can only be edited if a rework is requested.
            </div>
        @endif

        <section class="applicant-overview">
            <div class="overview-main">
                <span class="report-eyebrow">Applicant</span>
                <h5>{{ $officialName ?: 'Applicant' }}</h5>
                <div class="overview-meta">
                    <span>{{ $submission->procurement_submission_code }}</span>
                    <span>{{ $submission->procurement?->reference_no ?? 'No reference' }}</span>
                    <span>{{ \Illuminate\Support\Str::headline($status) }}</span>
                </div>
            </div>
            <div class="overview-score">
                <span>Pass Rate</span>
                <strong>{{ number_format($passPercent, 1) }}%</strong>
                <div class="score-track">
                    <div style="width: {{ $passPercent }}%"></div>
                </div>
            </div>
        </section>

        <div class="summary-grid">
            <div class="summary-card summary-card--procurement">
                <span>Procurement</span>
                <strong>{{ $submission->procurement?->title ?? '-' }}</strong>
            </div>
            <div class="summary-card summary-card--status">
                <span>Status</span>
                <strong>
                    <span class="status-pill bg-{{ $statusColor }}">{{ \Illuminate\Support\Str::headline($status) }}</span>
                </strong>
            </div>
            <div class="summary-card summary-card--evaluator">
                <span>Evaluator</span>
                <strong>{{ $result?->evaluator?->name ?? '-' }}</strong>
            </div>
            <div class="summary-card summary-card--date">
                <span>Evaluated</span>
                <strong>{{ optional($result?->evaluated_at)->format('d M Y, H:i') ?? '-' }}</strong>
            </div>
        </div>

        <div class="result-strip">
            <div>
                <span>Total Criteria</span>
                <strong>{{ $totalCriteria }}</strong>
            </div>
            <div>
                <span>Passed</span>
                <strong>{{ $passedCriteria }}</strong>
            </div>
            <div>
                <span>Failed</span>
                <strong>{{ $failedCriteria }}</strong>
            </div>
            <div>
                <span>Completed</span>
                <strong>{{ number_format($completionPercent, 1) }}%</strong>
            </div>
            <div>
                <span>Email</span>
                <strong>
                    @if ($officialEmail)
                        <a href="mailto:{{ $officialEmail }}">{{ $officialEmail }}</a>
                    @else
                        -
                    @endif
                </strong>
            </div>
        </div>

        <section class="detail-section">
            <div class="section-heading">
                <div>
                    <span class="report-eyebrow">Applicant Submission</span>
                    <h5>Submitted Details</h5>
                </div>
            </div>

            @if ($formFields->isNotEmpty())
                <div class="submitted-grid">
                    @foreach ($formFields as $field)
                        @php
                            $valueObj = $submittedValues->get($field->field_key);
                            $value = $valueObj?->value;
                        @endphp

                        <article class="submitted-field">
                            <div class="submitted-field__head">
                                <span>{{ $field->label }}</span>
                                @if ($field->is_required)
                                    <strong>Required</strong>
                                @endif
                            </div>

                            <div class="submitted-field__value">
                                @if ($field->field_type === 'file')
                                    @if ($valueObj && $value)
                                        <a href="{{ route('procurement.submissions.values.download', ['submission' => $submission->id, 'value' => $valueObj->id]) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="feather-paperclip me-1"></i> View Attachment
                                        </a>
                                    @else
                                        -
                                    @endif
                                @elseif (in_array($field->field_type, ['checkbox', 'multiselect', 'checkbox_group'], true))
                                    @php
                                        $items = is_array($value) ? $value : json_decode((string) $value, true);
                                        $items = is_array($items) ? array_filter($items, fn ($item) => filled($item)) : [];
                                    @endphp
                                    @if (!empty($items))
                                        <ul>
                                            @foreach ($items as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    @elseif (filled($value))
                                        {{ $value }}
                                    @else
                                        -
                                    @endif
                                @elseif ($field->field_type === 'textarea')
                                    <p>{{ filled($value) ? $value : '-' }}</p>
                                @else
                                    {{ filled($value) ? $value : '-' }}
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @elseif ($submission->values->isNotEmpty())
                <div class="submitted-grid">
                    @foreach ($submission->values as $rawValue)
                        <article class="submitted-field">
                            <div class="submitted-field__head">
                                <span>{{ \Illuminate\Support\Str::headline($rawValue->field_key) }}</span>
                            </div>
                            <div class="submitted-field__value">
                                {{ filled($rawValue->value) ? $rawValue->value : '-' }}
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="empty-panel">No submitted fields found for this applicant.</div>
            @endif
        </section>

        <form method="POST" action="{{ route('prescreening.submissions.store', $submission) }}" class="criteria-form">
            @csrf

            @foreach ($sectionGroups as $sectionIndex => $section)
                <section class="criteria-section">
                    <div class="criteria-section__head">
                        <div>
                            <span class="report-eyebrow">Section {{ $sectionIndex + 1 }}</span>
                            <h5>{{ $section->name }}</h5>
                            @if (!empty($section->description))
                                <p>{{ $section->description }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="criteria-list">
                        @forelse ($section->criteria as $criterion)
                            @php
                                $evaluation = $evaluations[$criterion->id] ?? null;
                                $passedState = $evaluation ? (bool) $evaluation->is_passed : null;
                            @endphp

                            <article class="criterion-card">
                                <div class="criterion-card__title">
                                    <div>
                                        <h6>{{ $criterion->name }}</h6>
                                        @if ($criterion->description)
                                            <p>{{ $criterion->description }}</p>
                                        @endif
                                    </div>
                                    @if ($evaluation)
                                        <span class="result-pill {{ $passedState ? 'result-pill--passed' : 'result-pill--failed' }}">
                                            {{ $passedState ? 'Passed' : 'Failed' }}
                                        </span>
                                    @else
                                        <span class="result-pill">Pending</span>
                                    @endif
                                </div>

                                <div class="decision-control">
                                    <label class="{{ $passedState === true ? 'is-selected' : '' }}">
                                        <input type="radio" name="criteria[{{ $criterion->id }}][passed]" value="1"
                                            {{ $passedState === true ? 'checked' : '' }}
                                            {{ !$canEdit ? 'disabled' : '' }}>
                                        <span>Yes</span>
                                    </label>

                                    <label class="{{ $passedState === false ? 'is-selected' : '' }}">
                                        <input type="radio" name="criteria[{{ $criterion->id }}][passed]" value="0"
                                            {{ $passedState === false ? 'checked' : '' }}
                                            {{ !$canEdit ? 'disabled' : '' }}>
                                        <span>No</span>
                                    </label>
                                </div>

                                <label class="remarks-label" for="remarks-{{ $criterion->id }}">Remarks</label>
                                <textarea id="remarks-{{ $criterion->id }}" class="form-control" rows="3" name="criteria[{{ $criterion->id }}][remarks]"
                                    {{ !$canEdit ? 'readonly' : '' }}>{{ optional($evaluation)->remarks }}</textarea>
                            </article>
                        @empty
                            <div class="empty-panel">No criteria configured for this section.</div>
                        @endforelse
                    </div>
                </section>
            @endforeach

            <div class="form-actions">
                @if ($canEdit)
                    <button type="submit" class="btn btn-success">
                        <i class="feather-save me-1"></i> Save Evaluation
                    </button>
                @endif

                @can('prescreening.request_rework')
                    @if ($result && $result->is_locked)
                        <button type="submit" class="btn btn-warning" form="rework-form">
                            <i class="feather-refresh-ccw me-1"></i> Request Rework
                        </button>
                    @endif
                @endcan
            </div>
        </form>

        @can('prescreening.request_rework')
            @if ($result && $result->is_locked)
                <form id="rework-form" method="POST" action="{{ route('prescreening.submissions.rework', $submission) }}" class="d-none">
                    @csrf
                </form>
            @endif
        @endcan
    </main>
@endsection

@push('styles')
    <style>
        .prescreening-show-page {
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
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .report-alert {
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            margin-bottom: 14px;
        }

        .applicant-overview,
        .detail-section,
        .criteria-section {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, .05);
        }

        .applicant-overview {
            align-items: stretch;
            border-left: 4px solid var(--green);
            display: grid;
            gap: 18px;
            grid-template-columns: 1fr 260px;
            margin-bottom: 14px;
            padding: 18px;
        }

        .report-eyebrow,
        .summary-card span,
        .result-strip span,
        .overview-score span,
        .submitted-field__head span,
        .remarks-label {
            color: var(--muted);
            display: block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .overview-main h5,
        .section-heading h5,
        .criteria-section__head h5 {
            color: var(--ink);
            font-size: 18px;
            font-weight: 800;
            line-height: 1.3;
            margin: 6px 0 0;
        }

        .overview-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .overview-meta span {
            background: #eef2f6;
            border-radius: 999px;
            color: #344054;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 9px;
        }

        .overview-score {
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 15px;
        }

        .overview-score strong {
            color: var(--ink);
            display: block;
            font-size: 28px;
            line-height: 1.1;
            margin-top: 8px;
        }

        .score-track {
            background: #e5e7eb;
            border-radius: 999px;
            height: 8px;
            margin-top: 13px;
            overflow: hidden;
        }

        .score-track div {
            background: linear-gradient(90deg, var(--green), var(--blue));
            height: 100%;
        }

        .summary-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
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

        .summary-card strong {
            color: var(--ink);
            display: block;
            font-size: 14px;
            line-height: 1.35;
            margin-top: 7px;
        }

        .summary-card--procurement { border-left-color: var(--green); }
        .summary-card--status { border-left-color: var(--blue); }
        .summary-card--evaluator { border-left-color: var(--orange); }
        .summary-card--date { border-left-color: var(--red); }

        .status-pill,
        .result-pill {
            border-radius: 999px;
            color: #fff;
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            padding: 5px 8px;
            text-transform: uppercase;
        }

        .result-pill {
            background: #eef2f6;
            color: #344054;
        }

        .result-pill--passed {
            background: #dcfce7;
            color: #166534;
        }

        .result-pill--failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .result-strip {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            margin-bottom: 16px;
            overflow: hidden;
        }

        .result-strip div {
            border-right: 1px solid var(--line);
            padding: 13px 15px;
        }

        .result-strip div:last-child {
            border-right: 0;
        }

        .result-strip strong {
            color: var(--ink);
            display: block;
            font-size: 14px;
            line-height: 1.35;
            margin-top: 6px;
            word-break: break-word;
        }

        .detail-section,
        .criteria-section {
            margin-bottom: 16px;
            padding: 18px;
        }

        .section-heading,
        .criteria-section__head {
            margin-bottom: 14px;
        }

        .criteria-section__head p {
            color: var(--muted);
            margin: 5px 0 0;
        }

        .submitted-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
        }

        .submitted-field {
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 13px 14px;
        }

        .submitted-field__head {
            align-items: center;
            display: flex;
            gap: 8px;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .submitted-field__head strong {
            background: #fee2e2;
            border-radius: 999px;
            color: #991b1b;
            font-size: 10px;
            padding: 4px 7px;
            text-transform: uppercase;
        }

        .submitted-field__value {
            color: #344054;
            font-weight: 600;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .submitted-field__value p {
            margin: 0;
            white-space: pre-line;
        }

        .submitted-field__value ul {
            margin: 0;
            padding-left: 18px;
        }

        .criteria-list {
            display: grid;
            gap: 12px;
        }

        .criterion-card {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px;
        }

        .criterion-card__title {
            align-items: flex-start;
            display: flex;
            gap: 12px;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .criterion-card h6 {
            color: var(--ink);
            font-size: 15px;
            font-weight: 800;
            margin: 0;
        }

        .criterion-card p {
            color: var(--muted);
            margin: 5px 0 0;
        }

        .decision-control {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .decision-control label {
            align-items: center;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: var(--ink);
            cursor: pointer;
            display: inline-flex;
            gap: 7px;
            min-width: 98px;
            padding: 9px 11px;
        }

        .decision-control label.is-selected {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, .12);
        }

        .decision-control input {
            margin: 0;
        }

        .remarks-label {
            margin-bottom: 6px;
        }

        .form-actions {
            justify-content: flex-end;
            margin-top: 18px;
        }

        .empty-panel {
            background: #fff;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            color: var(--muted);
            padding: 18px;
            text-align: center;
        }

        @media (max-width: 992px) {
            .applicant-overview,
            .result-strip {
                grid-template-columns: 1fr;
            }

            .result-strip div {
                border-bottom: 1px solid var(--line);
                border-right: 0;
            }

            .result-strip div:last-child {
                border-bottom: 0;
            }
        }

        @media (max-width: 720px) {
            .page-actions,
            .form-actions,
            .criterion-card__title {
                display: block;
            }

            .page-actions .btn,
            .form-actions .btn {
                margin-top: 8px;
                width: 100%;
            }

            .result-pill {
                margin-top: 10px;
            }
        }
    </style>
@endpush
