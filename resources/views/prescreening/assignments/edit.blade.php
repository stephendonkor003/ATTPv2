@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header mb-4">
            <h4 class="fw-bold">Assign Prescreening Users</h4>
            <p class="text-muted mb-0">
                Procurement: <strong>{{ $procurement->title }}</strong>
            </p>
        </div>

        <form method="POST" action="{{ route('prescreening.assignments.store', $procurement) }}">
            @csrf

            <div class="card shadow-sm">
                <div class="card-body">

                    @php
                        $assignmentType = $assignedProcurementUserId ? 'procurement' : ($assignedSubmission ? 'submission' : 'procurement');
                    @endphp

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assignment Scope</label>
                        <div class="d-flex flex-wrap gap-3">
                            <label class="form-check">
                                <input class="form-check-input" type="radio" name="assignment_type"
                                    value="procurement" {{ $assignmentType === 'procurement' ? 'checked' : '' }}>
                                <span class="form-check-label">Entire procurement (all submissions)</span>
                            </label>
                            <label class="form-check">
                                <input class="form-check-input" type="radio" name="assignment_type"
                                    value="submission" {{ $assignmentType === 'submission' ? 'checked' : '' }}>
                                <span class="form-check-label">Specific submission</span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Prescreener</label>
                        <select name="user_id" class="form-select" required>
                            <option value="" disabled {{ ($assignedProcurementUserId || $assignedSubmission) ? '' : 'selected' }}>
                                -- Choose one user --
                            </option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}"
                                    {{ ($assignedProcurementUserId === $user->id || ($assignedSubmission && $assignedSubmission->assigned_prescreener_id === $user->id)) ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Vendor and think tank accounts are not eligible for prescreening assignments.</div>
                        @error('user_id')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" id="submissionSelectWrap" style="{{ $assignmentType === 'submission' ? '' : 'display:none;' }}">
                        <label class="form-label fw-semibold">Select Submission</label>
                        <select name="submission_id" class="form-select">
                            <option value="">-- Choose submission --</option>
                            @foreach ($submissions as $submission)
                                <option value="{{ $submission->id }}"
                                    {{ $assignedSubmission && $assignedSubmission->id === $submission->id ? 'selected' : '' }}>
                                    {{ $submission->procurement_submission_code }} — {{ $submission->created_at?->format('Y-m-d') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <div class="card-footer text-end">
                    <button class="btn btn-success">
                        Save Assignments
                    </button>
                </div>
            </div>
        </form>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const scopeRadios = document.querySelectorAll('input[name="assignment_type"]');
            const submissionWrap = document.getElementById('submissionSelectWrap');
            const submissionSelect = submissionWrap.querySelector('select[name="submission_id"]');

            function toggleSubmission() {
                const selected = document.querySelector('input[name="assignment_type"]:checked');
                if (!selected) return;
                const isSubmission = selected.value === 'submission';
                submissionWrap.style.display = isSubmission ? '' : 'none';
                submissionSelect.required = isSubmission;
            }

            scopeRadios.forEach(radio => {
                radio.addEventListener('change', toggleSubmission);
            });

            toggleSubmission();
        });
    </script>
@endsection
