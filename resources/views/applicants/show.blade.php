@extends('layouts.app')
@section('title', 'Application Details')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left">
                    <h5 class="m-b-10">Application Details</h5>
                </div>
            </div>

            <div class="main-content">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Think Tank Information</h6>
                    </div>
                    <div class="card-body row g-3">
                        <div class="col-md-4"><strong>Name:</strong> {{ $applicant->lead_think_tank_name }}</div>
                        <div class="col-md-4"><strong>Country:</strong> {{ $applicant->country }}</div>
                        <div class="col-md-4"><strong>Sub-Region:</strong> {{ $applicant->sub_region }}</div>
                        <div class="col-md-4"><strong>Focus Areas:</strong> {{ $applicant->focus_areas }}</div>
                        <div class="col-md-4"><strong>Partnership:</strong> {{ $applicant->is_partnership ? 'Yes' : 'No' }}
                        </div>
                        <div class="col-md-4"><strong>Email:</strong> {{ $applicant->email }}</div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Consortium Information</h6>
                    </div>
                    <div class="card-body row g-3">
                        <div class="col-md-4"><strong>Consortium Name:</strong> {{ $applicant->consortium_name }}</div>
                        <div class="col-md-4"><strong>Lead Think Tank:</strong> {{ $applicant->lead_think_tank_name }}</div>
                        <div class="col-md-4"><strong>Lead Country:</strong> {{ $applicant->lead_think_tank_country }}</div>
                        <div class="col-md-4"><strong>Region:</strong> {{ $applicant->consortium_region }}</div>
                        <div class="col-md-4"><strong>Covered Countries:</strong>
                            @if ($applicant->covered_countries)
                                {{ implode(', ', json_decode($applicant->covered_countries)) }}
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </div>
                        <div class="col-md-4"><strong>Consortium Members:</strong> {{ $applicant->members_names }}</div>

                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Attached Documents</h6>
                    </div>
                    <div class="card-body row g-4">
                        @foreach ([
            'application_form' => 'Application Form',
            'legal_registration' => 'Legal Registration',
            'trustees_formation' => 'Trustees Formation',
            'audited_reports' => 'Audited Reports',
            'commitment_letter' => 'Commitment Letter',
            'work_plan_budget' => 'Work Plan & Budget',
            'cv_coordinator' => 'CV - Coordinator',
            'cv_deputy' => 'CV - Deputy',
            'cv_team_members' => 'CVs - Team Members',
            'past_research' => 'Past Research',
        ] as $field => $label)
                            <div class="col-md-4">
                                <strong>{{ $label }}:</strong><br>
                                @if ($applicant->$field)
                                    <a href="{{ route('applicants.documents.download', [$applicant->id, $field]) }}" target="_blank"
                                        class="btn btn-sm btn-outline-success mt-2">
                                        <i class="feather-eye"></i> View
                                    </a>
                                @else
                                    <span class="text-muted d-block mt-2">Not submitted</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @if (Auth::user()->user_type === 'applicant')
                    <div class="text-end mt-4">
                        <a href="{{ route('applicants.edit', $applicant->id) }}" class="btn btn-primary">
                            <i class="feather-edit"></i> Edit Submission Document
                        </a>
                    </div>
                @endif

            </div>
        </div>
    </main>
@endsection
