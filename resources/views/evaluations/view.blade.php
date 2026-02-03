@extends('layouts.app')

@section('content')
    <div class="nxl-container evaluation-view">

        {{-- ================= HEADER ================= --}}
        <div class="page-header mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-1">Submitted Evaluation</h4>
                <p class="text-muted mb-0">
                    Read-only view of a completed evaluation. No changes are permitted.
                </p>
            </div>
            <div class="d-flex gap-2">
                @can('evaluations.view_all')
                    <a href="{{ route('reports.evaluations.submission.pdf', $submission) }}" class="btn btn-success btn-sm">
                        Download PDF
                    </a>
                @endcan
                <a href="{{ route('eval.assign.hub') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>
</div>

        {{-- ================= STATUS ================= --}}
        <div class="alert alert-success d-flex align-items-start mb-4">
            <i class="feather-check-circle fs-4 me-3 mt-1"></i>
            <div>
                <strong>Evaluation Submitted</strong><br>
                Finalized on
                <strong>{{ optional($submission->submitted_at)->format('d M Y, H:i') }}</strong>
            </div>
        </div>

        @php
            $isGoods = $assignment->evaluation->type === 'goods';
            $colors = ['primary', 'success', 'info', 'warning', 'danger'];
            $overallMax = 0;
        @endphp

        <div class="row g-4">

            {{-- ================= LEFT ================= --}}
            <div class="col-lg-9">

                {{-- PROCUREMENT --}}
                <div class="card shadow-sm mb-4 border-primary">
                    <div class="card-header bg-primary text-white fw-bold">
                        {{ $assignment->procurement->title }}
                        <span class="badge bg-light text-dark ms-2">
                            {{ $assignment->evaluation->name }}
                        </span>
                        <span class="badge bg-{{ $isGoods ? 'warning' : 'success' }} ms-1">
                            {{ ucfirst($assignment->evaluation->type) }}
                        </span>
                    </div>
                </div>

                {{-- APPLICANT --}}
                <div class="card shadow-sm mb-4 border-info">
                    <div class="card-header fw-bold bg-info-subtle">
                        Applicant Submitted Information
                    </div>

                    <div class="card-body">

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Submission Code</strong><br>
                                {{ $applicant->procurement_submission_code }}
                            </div>
                            <div class="col-md-4">
                                <strong>Applicant</strong><br>
                                {{ optional($applicant->submitter)->name ?? '—' }}
                            </div>
                            <div class="col-md-4">
                                <strong>Date</strong><br>
                                {{ $applicant->created_at->format('d M Y, H:i') }}
                            </div>
                        </div>

                        <table class="table table-sm table-bordered">
                            @foreach ($applicant->values as $value)
                                <tr>
                                    <th width="30%">
                                        {{ ucwords(str_replace('_', ' ', $value->field_key)) }}
                                    </th>
                                    <td>{{ $value->value }}</td>
                                </tr>
                            @endforeach
                        </table>

                    </div>
                </div>

                {{-- ================= EVALUATION DETAILS ================= --}}
                @foreach ($assignment->evaluation->sections as $i => $section)
                    @php
                        $sectionScore = $submission->sectionScores->firstWhere('evaluation_section_id', $section->id);

                        if (!$isGoods) {
                            $sectionMax = $section->criteria->sum('max_score');
                            $overallMax += $sectionMax;
                        }
                    @endphp

                    <div class="card shadow-sm mb-4 border-start border-{{ $colors[$i % 5] }} border-4">
                        <div class="card-header bg-{{ $colors[$i % 5] }} text-white d-flex justify-content-between">
                            <span>{{ $section->name }}</span>

                            @if (!$isGoods)
                                <span>
                                    {{ number_format($sectionScore->section_score ?? 0, 2) }}
                                    / {{ $sectionMax }}
                                </span>
                            @endif
                        </div>

                        <div class="card-body">

                            {{-- ================= SERVICES ================= --}}
                            @if (!$isGoods)
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Criteria</th>
                                            <th>Max</th>
                                            <th>Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($section->criteria as $criteria)
                                            @php
                                                $score = $submission->criteriaScores->firstWhere(
                                                    'evaluation_criteria_id',
                                                    $criteria->id,
                                                );
                                            @endphp
                                            <tr>
                                                <td>{{ $criteria->name }}</td>
                                                <td>{{ $criteria->max_score }}</td>
                                                <td>{{ number_format($score->score ?? 0, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                {{-- ================= GOODS ================= --}}
                            @else
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Criteria</th>
                                            <th>Decision</th>
                                            <th>Comment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($section->criteria as $criteria)
                                            @php
                                                $score = $submission->criteriaScores->firstWhere(
                                                    'evaluation_criteria_id',
                                                    $criteria->id,
                                                );
                                            @endphp
                                            <tr>
                                                <td>{{ $criteria->name }}</td>
                                                <td>
                                                    @if ($score?->decision === 1)
                                                        <span class="badge bg-success">YES</span>
                                                    @elseif($score?->decision === 0)
                                                        <span class="badge bg-danger">NO</span>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td>{{ $score->comment ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif

                            {{-- SECTION NOTES --}}
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <strong>Strengths</strong>
                                    <div class="form-control bg-light">
                                        {{ $sectionScore->strengths ?? '—' }}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Weaknesses</strong>
                                    <div class="form-control bg-light">
                                        {{ $sectionScore->weaknesses ?? '—' }}
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                @endforeach

                {{-- ================= OVERALL (SERVICES ONLY) ================= --}}
                @if (!$isGoods)
                    <div class="card shadow-sm border-success">
                        <div class="card-body text-end fw-bold">
                            Overall Score:
                            <span class="text-success">
                                {{ number_format($submission->overall_score, 2) }}
                            </span>
                            / {{ $overallMax }}
                        </div>
                    </div>
                @endif

            </div>

            {{-- ================= RIGHT ================= --}}
            <div class="col-lg-3">

                <div class="soft-card mb-3 p-3">
                    <strong>Status</strong><br>
                    <span class="text-success">Finalized</span><br>
                    {{ optional($submission->submitted_at)->format('d M Y, H:i') }}
                </div>

                <div class="soft-card p-3">
                    <strong>Identity Proof</strong>

                    @if ($submission->video_path)
                        <video class="w-100 mt-2" controls>
                            <source src="{{ route('eval.assign.video', [$assignment->id, $applicant->id]) }}">
                        </video>
                    @else
                        <div class="alert alert-warning mt-2">
                            No identity video recorded.
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
@endsection
