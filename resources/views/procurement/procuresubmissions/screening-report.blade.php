@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        @php
            $screening = $submission->screening;
            $officialName = $submission->values->firstWhere('field_key', 'official_name')?->value;
            $officialEmail = $submission->values->firstWhere('field_key', 'official_email')?->value;
            $riskColors = [
                'clear' => 'success',
                'low' => 'info',
                'medium' => 'warning',
                'high' => 'danger',
                'critical' => 'dark',
            ];
            $matches = $screening?->response_payload['matches'] ?? [];
            $providerLabel = match (strtolower((string) $screening?->provider)) {
                '3pap' => 'International Screening',
                '' => '—',
                default => ucwords(str_replace(['_', '-'], ' ', (string) $screening->provider)),
            };
        @endphp

        <div class="page-header d-flex justify-content-between align-items-start mb-3">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-shield text-primary me-2"></i>
                    International Screening Report
                </h4>
                <div class="text-muted small">
                    Submission Code:
                    <span class="fw-semibold text-primary">{{ $submission->procurement_submission_code }}</span>
                </div>
            </div>

            <div class="d-flex gap-2">
                @if ($screeningConfigured)
                    <form method="POST" action="{{ route('procurement.submissions.screen', $submission) }}">
                        @csrf
                        <input type="hidden" name="to_report" value="1">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="feather-refresh-cw me-1"></i>
                            {{ $screening ? 'Re-run Screening' : 'Run Screening' }}
                        </button>
                    </form>
                @endif

                <a href="{{ route('procurement.submissions.show', $submission) }}" class="btn btn-outline-primary btn-sm">
                    <i class="feather-file-text me-1"></i> Submission
                </a>

                <a href="{{ route('procurement.submissions.index') }}" class="btn btn-light btn-sm">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-bold">Applicant Overview</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-4 col-md-6">
                                <div class="text-muted small">Official Name</div>
                                <div class="fw-semibold">{{ $officialName ?: ($screening?->entity_name ?: '—') }}</div>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <div class="text-muted small">Official Email</div>
                                <div class="fw-semibold">
                                    @if ($officialEmail)
                                        <a href="mailto:{{ $officialEmail }}">{{ $officialEmail }}</a>
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <div class="text-muted small">Country</div>
                                <div class="fw-semibold">{{ $screening?->entity_country ?: '—' }}</div>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <div class="text-muted small">Procurement</div>
                                <div class="fw-semibold">{{ $submission->procurement->title ?? '—' }}</div>
                                <div class="small text-muted">{{ $submission->procurement->reference_no ?? '—' }}</div>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <div class="text-muted small">Form</div>
                                <div class="fw-semibold">{{ $submission->form->name ?? '—' }}</div>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <div class="text-muted small">Submitted At</div>
                                <div class="fw-semibold">{{ optional($submission->submitted_at)->format('d M Y, H:i') ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">Screening Report</h6>
                        @if ($screening?->last_checked_at)
                            <span class="small text-muted">
                                Checked {{ optional($screening->last_checked_at)->format('d M Y, H:i') }}
                            </span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if (!$screeningConfigured)
                            <div class="alert alert-warning mb-0">
                                International screening is not configured in this environment.
                            </div>
                        @elseif (!$screening)
                            <div class="alert alert-light border mb-0">
                                No screening report has been generated for this applicant yet. Use the run button above to create one.
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
                            <div class="row g-3 mb-4">
                                <div class="col-lg-3 col-md-6">
                                    <div class="border rounded p-3 h-100 bg-light-subtle">
                                        <div class="text-muted small mb-1">Risk Level</div>
                                        <span class="badge bg-{{ $riskColors[$screening->risk_level] ?? 'secondary' }} px-3 py-2">
                                            {{ strtoupper($screening->risk_level ?? 'clear') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="border rounded p-3 h-100 bg-light-subtle">
                                        <div class="text-muted small mb-1">Matches Returned</div>
                                        <div class="fw-bold fs-5">{{ $screening->total_matches }}</div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="border rounded p-3 h-100 bg-light-subtle">
                                        <div class="text-muted small mb-1">Checked By</div>
                                        <div class="fw-semibold">{{ $screening->checker?->name ?? 'System' }}</div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="border rounded p-3 h-100 bg-light-subtle">
                                        <div class="text-muted small mb-1">Provider</div>
                                        <div class="fw-semibold">{{ $providerLabel }}</div>
                                    </div>
                                </div>
                            </div>

                            @if (!empty($matches))
                                <div class="table-responsive">
                                    <table class="table align-middle">
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
            </div>

            <div class="col-xl-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-bold">Fit Decision</h6>
                    </div>
                    <div class="card-body">
                        @if (!$screening)
                            <div class="alert alert-light border mb-0">
                                Run the screening first, then record whether the applicant is fit or not fit.
                            </div>
                        @else
                            <div class="mb-3">
                                <div class="text-muted small">Current Decision</div>
                                <div class="mt-1">
                                    @if ($screening->review_decision === 'fit')
                                        <span class="badge bg-success px-3 py-2">Fit</span>
                                    @elseif ($screening->review_decision === 'not_fit')
                                        <span class="badge bg-danger px-3 py-2">Not Fit</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary px-3 py-2">Pending Review</span>
                                    @endif
                                </div>
                            </div>

                            @if ($screening->reviewed_at)
                                <div class="small text-muted mb-3">
                                    Reviewed by {{ $screening->reviewer?->name ?? '—' }}
                                    on {{ optional($screening->reviewed_at)->format('d M Y, H:i') ?? '—' }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('procurement.submissions.screening.decision', $submission) }}">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Decision</label>
                                    <div class="d-grid gap-2">
                                        <label class="border rounded p-3">
                                            <input type="radio" name="review_decision" value="fit" class="form-check-input me-2"
                                                {{ old('review_decision', $screening->review_decision) === 'fit' ? 'checked' : '' }}>
                                            <span class="fw-semibold text-success">Fit</span>
                                            <div class="small text-muted mt-1">
                                                Applicant is cleared to continue in the procurement process.
                                            </div>
                                        </label>

                                        <label class="border rounded p-3">
                                            <input type="radio" name="review_decision" value="not_fit" class="form-check-input me-2"
                                                {{ old('review_decision', $screening->review_decision) === 'not_fit' ? 'checked' : '' }}>
                                            <span class="fw-semibold text-danger">Not Fit</span>
                                            <div class="small text-muted mt-1">
                                                Applicant should not proceed based on the screening review.
                                            </div>
                                        </label>
                                    </div>
                                    @error('review_decision')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="review_notes" class="form-label fw-semibold">Reviewer Notes</label>
                                    <textarea id="review_notes" name="review_notes" rows="5" class="form-control @error('review_notes') is-invalid @enderror"
                                        placeholder="Add the reason for the fit or not-fit decision">{{ old('review_notes', $screening->review_notes) }}</textarea>
                                    @error('review_notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-dark w-100">
                                    <i class="feather-save me-1"></i> Save Decision
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
