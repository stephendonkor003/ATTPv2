@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-file-text text-primary me-2"></i>
                    Procurement Submissions
                </h4>
                <p class="text-muted mb-0">
                    View and manage all bid submissions
                </p>
            </div>
            <div class="d-flex gap-2">
                @if ($screeningConfigured)
                    <form method="POST" action="{{ route('procurement.submissions.screen-all') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="feather-shield me-1"></i> Check All Applicants
                        </button>
                    </form>
                @else
                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                        <i class="feather-slash me-1"></i> Screening Not Configured
                    </button>
                @endif
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <x-data-table id="submissionsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Submission Code</th>
                            <th>Official Name</th>
                            <th>Official Email</th>
                            <th>Procurement</th>
                            <th>Form</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">International Screening</th>
                            <th>Submitted At</th>
                            <th class="text-center" width="180">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($submissions as $submission)
                            @php
                                $officialName = $submission->values->firstWhere('field_key', 'official_name')?->value;
                                $officialEmail = $submission->values->firstWhere('field_key', 'official_email')?->value;
                                $screening = $submission->screening;
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-semibold text-primary">
                                        {{ $submission->procurement_submission_code }}
                                    </span>
                                </td>

                                <td>{{ $officialName ?: '—' }}</td>

                                <td>
                                    @if ($officialEmail)
                                        <a href="mailto:{{ $officialEmail }}">{{ $officialEmail }}</a>
                                    @else
                                        —
                                    @endif
                                </td>

                                <td>
                                    <div class="fw-medium">{{ $submission->procurement->title }}</div>
                                    <small class="text-muted">{{ $submission->procurement->reference_no ?? '—' }}</small>
                                </td>

                                <td>{{ $submission->form->name }}</td>

                                <td class="text-center">
                                    @php
                                        $statusColors = [
                                            'draft' => 'secondary',
                                            'submitted' => 'primary',
                                            'under_review' => 'info',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $statusColors[$submission->status] ?? 'primary' }} px-3 py-1">
                                        {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    @if ($screening)
                                        @php
                                            $riskColors = [
                                                'clear' => 'success',
                                                'low' => 'info',
                                                'medium' => 'warning',
                                                'high' => 'danger',
                                                'critical' => 'dark',
                                            ];
                                        @endphp

                                        @if ($screening->request_status === 'error')
                                            <span class="badge bg-danger-subtle text-danger px-3 py-1">Check Failed</span>
                                            <div class="small text-muted mt-1">
                                                {{ \Illuminate\Support\Str::limit($screening->error_message, 40) }}
                                            </div>
                                        @else
                                            <span class="badge bg-{{ $riskColors[$screening->risk_level] ?? 'secondary' }} px-3 py-1">
                                                {{ strtoupper($screening->risk_level ?? 'clear') }}
                                            </span>
                                            <div class="small text-muted mt-1">
                                                {{ $screening->total_matches }} match{{ $screening->total_matches === 1 ? '' : 'es' }}
                                            </div>
                                            @if ($screening->review_decision)
                                                <div class="small mt-1">
                                                    <span class="badge bg-{{ $screening->review_decision === 'fit' ? 'success' : 'danger' }}-subtle text-{{ $screening->review_decision === 'fit' ? 'success' : 'danger' }}">
                                                        {{ $screening->review_decision === 'fit' ? 'Fit' : 'Not Fit' }}
                                                    </span>
                                                </div>
                                            @endif
                                            <div class="small text-muted">
                                                {{ optional($screening->last_checked_at)->format('d M Y, H:i') ?? '—' }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary px-3 py-1">Not Checked</span>
                                    @endif
                                </td>

                                <td>{{ $submission->submitted_at?->format('d M Y, H:i') ?? '—' }}</td>

                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('procurement.submissions.show', $submission) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="feather-eye me-1"></i> View
                                        </a>

                                        @if ($screeningConfigured)
                                            <a href="{{ route('procurement.submissions.screening.report', ['submission' => $submission, 'run' => $screening ? null : 1]) }}"
                                                class="btn btn-sm btn-outline-dark">
                                                <i class="feather-shield me-1"></i>
                                                {{ $screening ? 'Report' : 'Check' }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>

                <div class="mt-3">
                    {{ $submissions->links() }}
                </div>
            </div>
        </div>

    </div>
@endsection
