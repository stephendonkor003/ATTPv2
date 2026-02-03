@extends('layouts.app')
@section('title', 'Comprehensive Site Visit Report')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            {{-- HEADER --}}
            <div class="page-header">
                <div class="page-header-left">
                    <h5 class="m-b-10">Site Visit Comprehensive Report</h5>
                    <p class="text-muted mb-0">
                        Procurement: <strong>{{ $procurement->title }}</strong>
                    </p>
                </div>
            </div>

            <div class="main-content">

                {{-- SUMMARY --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Total Visits</strong><br>
                                {{ $siteVisits->count() }}
                            </div>
                            <div class="col-md-3">
                                <strong>Approved</strong><br>
                                {{ $siteVisits->where('status', 'approved')->count() }}
                            </div>
                            <div class="col-md-3">
                                <strong>Submitted</strong><br>
                                {{ $siteVisits->where('status', 'submitted')->count() }}
                            </div>
                            <div class="col-md-3">
                                <strong>Draft</strong><br>
                                {{ $siteVisits->where('status', 'draft')->count() }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SITE VISITS --}}
                @forelse($siteVisits as $visit)
                    <div class="card mb-4">

                        <div class="card-header d-flex justify-content-between">
                            <div>
                                <strong>Submission:</strong>
                                {{ $visit->submission->procurement_submission_code }}
                            </div>
                            <span class="badge bg-secondary">
                                {{ ucfirst($visit->status) }}
                            </span>
                        </div>

                        <div class="card-body">

                            {{-- BASIC INFO --}}
                            <p>
                                <strong>Visit Date:</strong>
                                {{ $visit->visit_date->format('d M Y') }} <br>

                                <strong>Assignment Type:</strong>
                                {{ ucfirst($visit->assignment_type) }}
                            </p>

                            {{-- ASSIGNMENT DETAILS --}}
                            @if ($visit->assignment_type === 'individual')
                                <p>
                                    <strong>Site Officer:</strong><br>
                                    {{ $visit->assignment->user->name }} <br>
                                    <small class="text-muted">
                                        {{ $visit->assignment->user->email }}
                                    </small>
                                </p>
                            @else
                                <p>
                                    <strong>Group:</strong>
                                    {{ $visit->group->group_name }} <br>

                                    <strong>Leader:</strong>
                                    {{ $visit->group->leader->name }}
                                </p>

                                <ul>
                                    @foreach ($visit->group->members as $member)
                                        <li>
                                            {{ $member->user->name }}
                                            @if ($member->role === 'leader')
                                                <strong>(Leader)</strong>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <hr>

                            {{-- OBSERVATIONS --}}
                            <h6>Observations</h6>

                            @forelse($visit->observations as $obs)
                                <div class="border rounded p-3 mb-2">
                                    <strong>{{ $obs->category }}</strong>
                                    <span class="badge bg-warning ms-1">
                                        {{ ucfirst($obs->severity) }}
                                    </span>

                                    <p class="mt-2 mb-1">
                                        {{ $obs->description }}
                                    </p>

                                    <small>
                                        Action Required:
                                        {{ $obs->action_required ? 'Yes' : 'No' }}
                                    </small>

                                    @if ($obs->media->count())
                                        <div class="mt-2">
                                            <strong>Evidence:</strong>
                                            <ul>
                                                @foreach ($obs->media as $media)
                                                    <li>
                                                        <a href="{{ route('site-visits.media.download', [$visit, $media]) }}"
                                                            target="_blank">
                                                            {{ basename($media->file_path) }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-muted">
                                    No observations recorded.
                                </p>
                            @endforelse

                        </div>
                    </div>
                @empty
                    <div class="alert alert-warning">
                        No site visits recorded for this procurement.
                    </div>
                @endforelse

            </div>
        </div>
    </main>
@endsection
