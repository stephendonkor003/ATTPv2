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
                    ??? Back
                </a>
            </div>
</div>

        @php
            $officialName = $submission->values->firstWhere('field_key', 'official_name')?->value ?: $submission->submitter?->name;
            $officialEmail = $submission->values->firstWhere('field_key', 'official_email')?->value ?: $submission->submitter?->email;
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
