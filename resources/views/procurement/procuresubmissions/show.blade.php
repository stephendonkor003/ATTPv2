@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        @php
            $screening = $submission->screening;
        @endphp

        {{-- =====================================================
        PAGE HEADER
    ===================================================== --}}
        <div class="page-header d-flex justify-content-between align-items-start mb-3">
            <div>
                <h4 class="fw-bold mb-1">Procurement Form Submission</h4>
                <div class="text-muted small">
                    Submission Code:
                    <span class="fw-semibold text-primary">
                        {{ $submission->procurement_submission_code }}
                    </span>
                </div>
            </div>

            <div class="d-flex gap-2">
                @if ($screeningConfigured)
                    <a href="{{ route('procurement.submissions.screening.report', ['submission' => $submission, 'run' => $screening ? null : 1]) }}"
                        class="btn btn-primary btn-sm">
                        <i class="feather-shield me-1"></i>
                        {{ $screening ? 'Open Screening Report' : 'Check Applicant' }}
                    </a>
                @endif

                <a href="{{ url()->previous() }}" class="btn btn-light btn-sm">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        {{-- =====================================================
        SUBMISSION META INFORMATION
    ===================================================== --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-lg-3 col-md-6">
                        <div class="text-muted small">Form Name</div>
                        <div class="fw-semibold">
                            {{ $submission->form->name ?? '—' }}
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <div class="text-muted small">Submitted By</div>
                        <div class="fw-semibold">
                            {{ optional($submission->submitter)->name ?? '—' }}
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <div class="text-muted small">Submission Status</div>
                        <span class="badge bg-info-subtle text-info fw-semibold">
                            {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                        </span>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <div class="text-muted small">Submitted At</div>
                        <div class="fw-semibold">
                            {{ optional($submission->submitted_at)->format('d M Y, H:i') ?? '—' }}
                        </div>
                    </div>

                </div>
            </div>
        </div>

        @php
            $riskColors = [
                'clear' => 'success',
                'low' => 'info',
                'medium' => 'warning',
                'high' => 'danger',
                'critical' => 'dark',
            ];
            $matches = $screening?->response_payload['matches'] ?? [];
        @endphp

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">International Screening</h6>
                <div class="d-flex gap-2 align-items-center">
                    @if (!$screeningConfigured)
                        <span class="badge bg-secondary-subtle text-secondary">Not Configured</span>
                    @endif
                    @if ($screeningConfigured)
                        <a href="{{ route('procurement.submissions.screening.report', ['submission' => $submission, 'run' => $screening ? null : 1]) }}"
                            class="btn btn-sm btn-outline-primary">
                            <i class="feather-external-link me-1"></i> Full Report
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if (!$screeningConfigured)
                    <div class="alert alert-warning mb-0">
                        International screening is not configured in this environment.
                    </div>
                @elseif (!$screening)
                    <div class="alert alert-light border mb-0">
                        This applicant has not been screened yet.
                    </div>
                @elseif ($screening->request_status === 'error')
                    <div class="alert alert-danger mb-0">
                        <div class="fw-semibold mb-1">Screening failed</div>
                        <div>{{ $screening->error_message ?: 'International screening did not complete successfully.' }}</div>
                        <div class="small mt-2">
                            Last attempted: {{ optional($screening->last_checked_at)->format('d M Y, H:i') ?? '—' }}
                        </div>
                    </div>
                @else
                    <div class="row g-3 mb-3">
                        <div class="col-lg-3 col-md-6">
                            <div class="text-muted small">Entity Screened</div>
                            <div class="fw-semibold">{{ $screening->entity_name ?: '—' }}</div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="text-muted small">Country</div>
                            <div class="fw-semibold">{{ $screening->entity_country ?: '—' }}</div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="text-muted small">Risk Level</div>
                            <span class="badge bg-{{ $riskColors[$screening->risk_level] ?? 'secondary' }} px-3 py-1">
                                {{ strtoupper($screening->risk_level ?? 'clear') }}
                            </span>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="text-muted small">Checked At</div>
                            <div class="fw-semibold">
                                {{ optional($screening->last_checked_at)->format('d M Y, H:i') ?? '—' }}
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="text-muted small">Decision</div>
                            @if ($screening->review_decision === 'fit')
                                <span class="badge bg-success px-3 py-1">Fit</span>
                            @elseif ($screening->review_decision === 'not_fit')
                                <span class="badge bg-danger px-3 py-1">Not Fit</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary px-3 py-1">Pending Review</span>
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        <span class="badge bg-primary-subtle text-primary px-3 py-2">
                            {{ $screening->total_matches }} match{{ $screening->total_matches === 1 ? '' : 'es' }} returned
                        </span>
                    </div>

                    @if ($screening->review_notes)
                        <div class="alert alert-light border mb-3">
                            <div class="fw-semibold mb-1">Reviewer Notes</div>
                            <div>{{ $screening->review_notes }}</div>
                        </div>
                    @endif

                    @if (!empty($matches))
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Matched Entity</th>
                                        <th>Dataset</th>
                                        <th>Country</th>
                                        <th>Program</th>
                                        <th>Score</th>
                                        <th>Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($matches as $match)
                                        <tr>
                                            <td class="fw-medium">{{ $match['name'] ?? '—' }}</td>
                                            <td>{{ $match['dataset'] ?? '—' }}</td>
                                            <td>{{ $match['country'] ?? '—' }}</td>
                                            <td>{{ $match['program'] ?? '—' }}</td>
                                            <td>
                                                @if (isset($match['match_score']))
                                                    {{ round(((float) $match['match_score']) * 100) }}%
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                @if (!empty($match['source_url']))
                                                    <a href="{{ $match['source_url'] }}" target="_blank" rel="noopener">
                                                        View Source
                                                    </a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-success mb-0">
                            No sanctions matches were returned for this applicant.
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- =====================================================
        SUBMITTED FORM DATA
    ===================================================== --}}
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0 fw-bold">Submitted Information</h6>
            </div>

            <div class="card-body">

                @php
                    $submittedValues = $submission->values->keyBy('field_key');
                @endphp

                <div class="row g-4">

                    @forelse ($submission->form->fields as $field)
                        @php
                            $valueObj = $submittedValues->get($field->field_key);
                            $value = $valueObj?->value;
                        @endphp

                        <div class="col-lg-6 col-md-6 col-12">

                            <div class="h-100 p-3 border rounded bg-white">

                                {{-- FIELD LABEL --}}
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="text-muted small">
                                        {{ $field->label }}
                                    </div>

                                    @if ($field->is_required)
                                        <span class="badge bg-danger-subtle text-danger small">
                                            Required
                                        </span>
                                    @endif
                                </div>

                                {{-- FIELD VALUE --}}
                                <div class="fw-medium text-dark">

                                    @switch($field->field_type)
                                        {{-- TEXTAREA --}}
                                        @case('textarea')
                                            <div class="bg-light rounded p-2" style="white-space: pre-line; line-height: 1.7;">
                                                {{ $value ?: '—' }}
                                            </div>
                                        @break

                                        {{-- SELECT / RADIO --}}
                                        @case('select')
                                        @case('radio')
                                            {{ $value ?: '—' }}
                                        @break

                                        {{-- CHECKBOX --}}
                                        @case('checkbox')
                                            @php
                                                $items = is_array($value) ? $value : json_decode($value, true);
                                            @endphp

                                            @if (!empty($items))
                                                <ul class="mb-0 ps-3">
                                                    @foreach ($items as $item)
                                                        <li>{{ $item }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                —
                                            @endif
                                        @break

                                        {{-- FILE --}}
                                        @case('file')
                                            @if ($valueObj && $value)
                                                <a href="{{ route('procurement.submissions.values.download', ['submission' => $submission->id, 'value' => $valueObj->id]) }}" target="_blank"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="feather-paperclip me-1"></i>
                                                    View Attachment
                                                </a>
                                            @else
                                                —
                                            @endif
                                        @break

                                        {{-- DEFAULT --}}

                                        @default
                                            {{ $value ?: '—' }}
                                    @endswitch

                                </div>

                            </div>

                        </div>

                        @empty
                            <div class="col-12">
                                <div class="alert alert-warning mb-0">
                                    No fields were defined for this form.
                                </div>
                            </div>
                        @endforelse

                    </div>

                </div>
            </div>

        </div>
    @endsection
