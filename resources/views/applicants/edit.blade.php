@extends('layouts.app')
@section('title', 'Edit Submission')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h5 class="m-b-10">Edit Submission Document</h5>
                </div>
            </div>

            <form action="{{ route('applicants.update', $applicant->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Think Tank Information</h6>
                    </div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Name</label>
                            <input type="text" name="think_tank_name" value="{{ $applicant->think_tank_name }}"
                                class="form-control" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" value="{{ $applicant->country }}" class="form-control"
                                readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sub-Region</label>
                            <input type="text" name="sub_region" value="{{ $applicant->sub_region }}"
                                class="form-control" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Focus Areas</label>
                            <input type="text" name="focus_areas" value="{{ $applicant->focus_areas }}"
                                class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Partnership</label>
                            <select name="is_partnership" class="form-select">
                                <option value="1" {{ $applicant->is_partnership ? 'selected' : '' }}>Yes</option>
                                <option value="0" {{ !$applicant->is_partnership ? 'selected' : '' }}>No</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" value="{{ $applicant->email }}" class="form-control"
                                required>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Consortium Information</h6>
                    </div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Consortium Name</label>
                            <input type="text" name="consortium_name" value="{{ $applicant->consortium_name }}"
                                class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Lead Think Tank</label>
                            <input type="text" name="lead_think_tank_name" value="{{ $applicant->lead_think_tank_name }}"
                                class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Lead Country</label>
                            <input type="text" name="lead_think_tank_country"
                                value="{{ $applicant->lead_think_tank_country }}" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Region</label>
                            <input type="text" name="consortium_region" value="{{ $applicant->consortium_region }}"
                                class="form-control"readonly>
                        </div>
                        {{-- <div class="col-md-8">
                            <label class="form-label">Covered Countries</label>
                            <select name="covered_countries[]" class="form-select select2" multiple>
                                @php
                                    $countries = ['Ghana', 'Nigeria', 'Kenya', 'South Africa', 'Ethiopia', 'Uganda']; // add full list
                                    $selected = json_decode($applicant->covered_countries, true) ?? [];
                                @endphp
                                @foreach ($countries as $country)
                                    <option value="{{ $country }}"
                                        {{ in_array($country, $selected) ? 'selected' : '' }}>{{ $country }}</option>
                                @endforeach
                            </select>
                        </div> --}}
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Update Documents</h6>
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
                                <label class="form-label">{{ $label }}</label>
                                @if ($applicant->$field)
                                    <div class="mb-2">
                                        <a href="{{ route('applicants.documents.download', [$applicant->id, $field]) }}" target="_blank"
                                            class="btn btn-sm btn-outline-success">
                                            <i class="feather-eye"></i> View Current
                                        </a>
                                    </div>
                                @endif
                                <input type="file" name="{{ $field }}" class="form-control">
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary"><i class="feather-save"></i> Update Submission</button>
                </div>
            </form>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%'
            });
        });
    </script>
@endpush
