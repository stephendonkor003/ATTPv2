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
                            <th>Submitted At</th>
                            <th class="text-center" width="100">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($submissions as $submission)
                            @php
                                $officialName = $submission->values->firstWhere('field_key', 'official_name')?->value;
                                $officialEmail = $submission->values->firstWhere('field_key', 'official_email')?->value;
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

                                <td>{{ $submission->submitted_at?->format('d M Y, H:i') ?? '—' }}</td>

                                <td class="text-center">
                                    <a href="{{ route('procurement.submissions.show', $submission) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="feather-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>

    </div>
@endsection
