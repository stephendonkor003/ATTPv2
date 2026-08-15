@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-clipboard text-primary me-2"></i>
                    My Evaluations
                </h4>
                <p class="text-muted mb-0">
                    You are assigned to evaluate the following procurements.
                </p>
            </div>

            <span class="badge bg-primary px-3 py-2 fs-6">
                {{ $assignments->count() }} Assignments
            </span>
        </div>

        {{-- ================= INFO BANNER ================= --}}
        <div class="alert alert-info d-flex align-items-start mb-4">
            <i class="feather-info fs-4 me-3 mt-1"></i>
            <div>
                <strong>Evaluation Guidelines</strong>
                <ul class="mb-0 ps-3 small">
                    <li>Each applicant is evaluated independently</li>
                    <li>You may save drafts before final submission</li>
                    <li>Once submitted, an applicant's evaluation is locked</li>
                    <li><em>Services use numeric scores, Goods use Yes/No, and EOI evaluations use qualification categories.</em></li>
                </ul>
            </div>
        </div>

        {{-- ================= ASSIGNMENTS ================= --}}
        @forelse ($assignments as $assignment)
            @php
                $evaluation = $assignment->evaluation;
                $typeColor = $evaluation?->typeColor() ?? 'secondary';
                $typeLabel = $evaluation?->typeLabel() ?? 'Evaluation';

                $assignmentSubmissions = $assignment->form_submission_id
                    ? $submissions->where('id', $assignment->form_submission_id)
                    : $submissions->where('procurement_id', $assignment->procurement_id);
            @endphp

            <div class="card shadow-sm border-0 mb-4">

                {{-- PROCUREMENT HEADER --}}
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold">{{ $assignment->procurement->title }}</span>
                        <span class="badge bg-secondary ms-2">{{ $assignment->evaluation->name }}</span>
                        <span class="badge bg-{{ $typeColor }} ms-1">{{ $typeLabel }}</span>
                    </div>

                    <div class="d-flex gap-2">
                        @if ($assignment->form_submission_id)
                            <span class="badge bg-info">Specific Submission</span>
                        @else
                            <span class="badge bg-info">Entire Procurement</span>
                        @endif

                        <span class="badge bg-{{ $assignment->status === 'submitted' ? 'success' : 'warning text-dark' }}">
                            {{ ucfirst($assignment->status) }}
                        </span>
                    </div>
                </div>

                {{-- SUBMISSIONS TABLE --}}
                <div class="card-body">
                    <x-data-table :id="'submissionsTable' . $assignment->id">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Submission Code</th>
                                <th>Form</th>
                                <th>Applicant</th>
                                <th>Date</th>
                                <th width="140" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assignmentSubmissions as $submission)
                                @php
                                    $evalSubmission = \App\Models\EvaluationSubmission::where([
                                        'evaluation_id' => $assignment->evaluation_id,
                                        'procurement_id' => $assignment->procurement_id,
                                        'evaluator_id' => auth()->id(),
                                        'form_submission_id' => $submission->id,
                                    ])->first();
                                @endphp

                                <tr>
                                    <td class="ps-4">
                                        <span class="badge bg-light text-dark border px-3 py-1">
                                            {{ $submission->procurement_submission_code }}
                                        </span>
                                    </td>

                                    <td>{{ $submission->form->name }}</td>

                                    <td>{{ optional($submission->submitter)->name ?? '—' }}</td>

                                    <td>{{ $submission->created_at->format('d M Y') }}</td>

                                    <td class="text-center">
                                        @if ($evalSubmission?->submitted_at)
                                            <a href="{{ route('eval.assign.view', [$assignment->id, $submission->id]) }}"
                                                class="btn btn-outline-success btn-sm">
                                                <i class="feather-eye me-1"></i> View
                                            </a>
                                        @elseif ($evalSubmission)
                                            <a href="{{ route('eval.assign.start', [$assignment->id, $submission->id]) }}"
                                                class="btn btn-outline-primary btn-sm">
                                                <i class="feather-edit me-1"></i> Continue
                                            </a>
                                        @else
                                            <a href="{{ route('eval.assign.start', [$assignment->id, $submission->id]) }}"
                                                class="btn btn-primary btn-sm">
                                                <i class="feather-play me-1"></i> Start
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-data-table>
                </div>

            </div>

        @empty
            <div class="text-center py-5">
                <i class="feather-inbox fs-1 text-muted mb-2"></i>
                <p class="mb-0 text-muted">
                    You currently have no evaluation assignments.
                </p>
            </div>
        @endforelse

    </div>
@endsection
