@extends('layouts.app')

@section('content')
    <div class="nxl-container">

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

            <a href="{{ url()->previous() }}" class="btn btn-light btn-sm">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
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
