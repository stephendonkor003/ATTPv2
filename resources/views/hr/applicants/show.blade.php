@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header mb-4 d-flex justify-content-between">
            <div>
                <h4 class="fw-bold">
                    {{ $applicant->full_name }}
                </h4>
                <p class="text-muted mb-0">
                    {{ $applicant->email }} | {{ $applicant->phone }}
                </p>
            </div>

            <a href="{{ route('hr.vacancies.applicants', $applicant->vacancy_id) }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        <div class="row g-4">

            {{-- PROFILE --}}
            <div class="col-md-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3">Applicant Profile</h6>

                        <p><strong>Gender:</strong> {{ ucfirst($applicant->gender ?? '-') }}</p>
                        <p><strong>Nationality:</strong> {{ $applicant->nationality ?? '-' }}</p>
                        <p><strong>Status:</strong>
                            <span class="badge bg-info">{{ ucfirst($applicant->status) }}</span>
                        </p>

                        @if ($shortlist)
                            <p><strong>AI Score:</strong> {{ $shortlist->score }}%</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- CV PREVIEW --}}
            <div class="col-md-7">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3">CV Preview</h6>

                        @if ($applicant->cv_path)
                            <iframe src="{{ route('hr.applicants.files', [$applicant->id, 'cv']) }}"
                                style="width:100%; height:500px; border:none;">
                            </iframe>
                        @else
                            <div class="alert alert-warning mb-0">No CV uploaded.</div>
                        @endif
                    </div>
                </div>
            </div>

        </div>

    </div>
@endsection
