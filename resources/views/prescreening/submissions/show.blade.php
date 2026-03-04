@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center">
            <h4 class="fw-bold">
                Prescreening Evaluation
            </h4>
            <div class="d-flex gap-2">
                @can('prescreening.reports.view_all')
                    <a href="{{ route('reports.prescreening.submission.pdf', $submission) }}" class="btn btn-sm btn-success">
                        Download PDF
                    </a>
                @endcan
                <a href="{{ route('prescreening.submissions.index') }}" class="btn btn-sm btn-outline-secondary">
                    Back
                </a>
            </div>
        </div>

        @php
            $officialName = $submission->values->firstWhere('field_key', 'official_name')?->value ?: $submission->submitter?->name;
            $officialEmail = $submission->values->firstWhere('field_key', 'official_email')?->value ?: $submission->submitter?->email;
            $submittedValues = $submission->values->keyBy('field_key');
            $formFields = $submission->form?->fields ?? collect();
        @endphp

        {{-- ================= INFO BANNER ================= --}}
        @if (!$canEdit)
            <div class="alert alert-info mt-3">
                This evaluation is <strong>locked</strong> and can only be edited if a rework is requested.
            </div>
        @endif

        <div class="card mt-3 border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Submission Code</small>
                        <span class="fw-semibold">{{ $submission->procurement_submission_code }}</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Official Name</small>
                        <span class="fw-semibold">{{ $officialName ?: '—' }}</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Official Email</small>
                        @if ($officialEmail)
                            <a href="mailto:{{ $officialEmail }}" class="fw-semibold">{{ $officialEmail }}</a>
                        @else
                            <span class="fw-semibold">—</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= APPLICANT SUBMISSION ================= --}}
        <div class="card mt-3 shadow-sm border-0">
            <div class="card-header bg-light">
                <h6 class="mb-1 fw-bold">Applicant Submission Details</h6>
                <small class="text-muted">Review all submitted fields before scoring the criteria.</small>
            </div>
            <div class="card-body">
                @if ($formFields->isNotEmpty())
                    <div class="row g-3">
                        @foreach ($formFields as $field)
                            @php
                                $valueObj = $submittedValues->get($field->field_key);
                                $value = $valueObj?->value;
                            @endphp

                            <div class="col-lg-6 col-md-6 col-12">
                                <div class="h-100 p-3 border rounded bg-white">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="text-muted small">{{ $field->label }}</div>
                                        @if ($field->is_required)
                                            <span class="badge bg-danger-subtle text-danger small">Required</span>
                                        @endif
                                    </div>

                                    <div class="fw-medium text-dark">
                                        @if ($field->field_type === 'file')
                                            @if ($valueObj && $value)
                                                <a href="{{ route('procurement.submissions.values.download', ['submission' => $submission->id, 'value' => $valueObj->id]) }}" target="_blank"
                                                    class="btn btn-sm btn-outline-primary">
                                                    View Attachment
                                                </a>
                                            @else
                                                —
                                            @endif
                                        @elseif (in_array($field->field_type, ['checkbox', 'multiselect', 'checkbox_group'], true))
                                            @php
                                                $items = is_array($value) ? $value : json_decode((string) $value, true);
                                                $items = is_array($items) ? array_filter($items, fn($item) => filled($item)) : [];
                                            @endphp
                                            @if (!empty($items))
                                                <ul class="mb-0 ps-3">
                                                    @foreach ($items as $item)
                                                        <li>{{ $item }}</li>
                                                    @endforeach
                                                </ul>
                                            @elseif (filled($value))
                                                {{ $value }}
                                            @else
                                                —
                                            @endif
                                        @elseif ($field->field_type === 'textarea')
                                            <div class="bg-light rounded p-2" style="white-space: pre-line; line-height: 1.7;">
                                                {{ filled($value) ? $value : '—' }}
                                            </div>
                                        @else
                                            {{ filled($value) ? $value : '—' }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif ($submission->values->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Field</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($submission->values as $rawValue)
                                    <tr>
                                        <td>{{ $rawValue->field_key }}</td>
                                        <td>{{ filled($rawValue->value) ? $rawValue->value : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning mb-0">
                        No submitted fields found for this applicant.
                    </div>
                @endif
            </div>
        </div>

        {{-- ================= EVALUATION FORM ================= --}}
        <form method="POST" action="{{ route('prescreening.submissions.store', $submission) }}">
            @csrf

            @foreach ($template->criteria as $criterion)
                @php
                    $evaluation = $evaluations[$criterion->id] ?? null;
                @endphp

                <div class="card mt-3 shadow-sm">
                    <div class="card-body">

                        <h6 class="fw-bold mb-3">
                            {{ $criterion->name }}
                        </h6>

                        {{-- PASS / FAIL --}}
                        <div class="mb-3">
                            <label class="me-4">
                                <input type="radio" name="criteria[{{ $criterion->id }}][passed]" value="1"
                                    {{ optional($evaluation)->is_passed === 1 ? 'checked' : '' }}
                                    {{ !$canEdit ? 'disabled' : '' }}>
                                <strong>YES</strong>
                            </label>

                            <label>
                                <input type="radio" name="criteria[{{ $criterion->id }}][passed]" value="0"
                                    {{ optional($evaluation)->is_passed === 0 ? 'checked' : '' }}
                                    {{ !$canEdit ? 'disabled' : '' }}>
                                <strong>NO</strong>
                            </label>
                        </div>

                        {{-- REMARKS --}}
                        <div>
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" rows="3" name="criteria[{{ $criterion->id }}][remarks]"
                                {{ !$canEdit ? 'readonly' : '' }}>{{ optional($evaluation)->remarks }}</textarea>
                        </div>

                    </div>
                </div>
            @endforeach


            {{-- ================= SUBMIT BUTTON ================= --}}
            @if ($canEdit)
                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-success">
                        Save Evaluation
                    </button>
                </div>
            @endif
        </form>

        {{-- ================= REQUEST REWORK ================= --}}
        @can('prescreening.request_rework')
            @if ($result && $result->is_locked)
                <div class="mt-4">
                    <form method="POST" action="{{ route('prescreening.submissions.rework', $submission) }}">
                        @csrf
                        <button class="btn btn-warning">
                            Request Rework
                        </button>
                    </form>
                </div>
            @endif
        @endcan

    </div>
@endsection
