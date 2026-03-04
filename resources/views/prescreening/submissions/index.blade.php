@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-file-text text-primary me-2"></i>
                    Prescreening Evaluations
                </h4>
                <p class="text-muted mb-0">
                    Review prescreening outcomes submitted by evaluators.
                    Locked evaluations can only be edited when a rework is requested.
                </p>
            </div>
        </div>

        {{-- ================= STATUS LEGEND ================= --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Status Guide</h6>

                <div class="row g-3">
                    <div class="col-md-4">
                        <span class="badge bg-secondary me-2">Submitted</span>
                        <small class="text-muted">Awaiting prescreening.</small>
                    </div>

                    <div class="col-md-4">
                        <span class="badge bg-success me-2">Prescreen Passed</span>
                        <small class="text-muted">All criteria satisfied.</small>
                    </div>

                    <div class="col-md-4">
                        <span class="badge bg-danger me-2">Prescreen Failed</span>
                        <small class="text-muted">One or more criteria failed.</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= TABLE CARD ================= --}}
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <x-data-table id="evaluationTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Submission Code</th>
                            <th>Official Name</th>
                            <th>Official Email</th>
                            <th>Procurement</th>
                            <th class="text-center">Status</th>
                            <th>Evaluator</th>
                            <th width="160" class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($submissions as $submission)
                            @php
                                $statusColors = [
                                    'submitted' => 'secondary',
                                    'prescreen_passed' => 'success',
                                    'prescreen_failed' => 'danger',
                                    'under_review' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                ];

                                $status = $submission->status;
                                $badge = $statusColors[$status] ?? 'info';
                                $officialName = $submission->values->firstWhere('field_key', 'official_name')?->value;
                                $officialEmail = $submission->values->firstWhere('field_key', 'official_email')?->value;
                                $displayName = $officialName ?: $submission->submitter?->name;
                                $displayEmail = $officialEmail ?: $submission->submitter?->email;
                            @endphp

                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold text-primary">{{ $submission->procurement_submission_code }}</div>
                                    <small class="text-muted">
                                        {{ $submission->created_at?->diffForHumans() }}
                                    </small>
                                </td>

                                <td>{{ $displayName ?: '—' }}</td>

                                <td>
                                    @if ($displayEmail)
                                        <a href="mailto:{{ $displayEmail }}">{{ $displayEmail }}</a>
                                    @else
                                        —
                                    @endif
                                </td>

                                <td>
                                    <div class="fw-medium">{{ $submission->procurement->title }}</div>
                                    <small class="text-muted">{{ $submission->procurement->reference_no ?? '—' }}</small>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-{{ $badge }} px-3 py-1">
                                        {{ str_replace('_', ' ', ucfirst($status)) }}
                                    </span>

                                    @if ($submission->prescreeningResult?->is_locked)
                                        <br>
                                        <small class="text-muted">
                                            <i class="feather-lock"></i> Locked
                                        </small>
                                    @endif
                                </td>

                                <td>
                                    {{ optional($submission->prescreeningResult?->evaluator)->name ?? '—' }}
                                </td>

                                <td class="text-center">
                                    <a href="{{ route('prescreening.submissions.show', $submission) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="feather-eye me-1"></i> View
                                    </a>

                                    @can('prescreening.request_rework')
                                        @if ($submission->prescreeningResult && $submission->prescreeningResult->is_locked)
                                            <form method="POST"
                                                action="{{ route('prescreening.submissions.rework', $submission) }}"
                                                class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-warning"
                                                    onclick="return confirm('Request rework for this evaluation?')">
                                                    Rework
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>

    </div>
@endsection
