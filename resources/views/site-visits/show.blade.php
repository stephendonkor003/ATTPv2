@extends('layouts.app')
@section('title', 'Site Visit Details')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            {{-- ================= HEADER ================= --}}
            <div class="page-header">
                <div class="page-header-left">
                    <h5 class="m-b-10">Site Visit Details</h5>
                    <p class="text-muted mb-0">
                        Submission:
                        <strong>{{ $siteVisit->submission->procurement_submission_code }}</strong>
                    </p>
                </div>

                <div class="page-header-right">
                    <span class="badge bg-secondary">
                        {{ ucfirst($siteVisit->status) }}
                    </span>
                </div>
            </div>

            <div class="main-content">

                {{-- ================= BASIC INFO ================= --}}
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">

                            <div class="col-md-4">
                                <strong>Procurement</strong><br>
                                {{ $siteVisit->procurement->title ?? '-' }}
                            </div>

                            <div class="col-md-4">
                                <strong>Visit Date</strong><br>
                                {{ $siteVisit->visit_date->format('d M Y') }}
                            </div>

                            <div class="col-md-4">
                                <strong>Assignment Type</strong><br>
                                {{ ucfirst($siteVisit->assignment_type) }}
                            </div>

                        </div>
                    </div>
                </div>

                {{-- ================= ASSIGNMENT ================= --}}
                <div class="card mb-3">
                    <div class="card-header">
                        Assignment Details
                    </div>
                    <div class="card-body">

                        {{-- INDIVIDUAL --}}
                        @if ($siteVisit->assignment_type === 'individual')
                            <p class="mb-0">
                                <strong>Assigned To:</strong><br>
                                {{ $siteVisit->assignment->user->name ?? '-' }} <br>
                                <small class="text-muted">
                                    {{ $siteVisit->assignment->user->email ?? '' }}
                                </small>
                            </p>
                        @endif

                        {{-- GROUP --}}
                        @if ($siteVisit->assignment_type === 'group')
                            <p>
                                <strong>Group Name:</strong>
                                {{ $siteVisit->group->group_name }}
                            </p>

                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th width="150">Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($siteVisit->group->members as $member)
                                        <tr>
                                            <td>
                                                {{ $member->user->name }} <br>
                                                <small class="text-muted">
                                                    {{ $member->user->email }}
                                                </small>
                                            </td>
                                            <td>
                                                @if ($member->role === 'leader')
                                                    <span class="badge bg-primary">
                                                        Group Leader
                                                    </span>
                                                @else
                                                    Member
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                    </div>
                </div>

                {{-- ================= OBSERVATIONS ================= --}}
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Observations</span>

                        @can('site_visits.observe')
                            @if ($siteVisit->status === 'draft')
                                <a href="{{ route('site-visits.observations.create', $siteVisit) }}"
                                    class="btn btn-sm btn-outline-primary">
                                    Add Observation
                                </a>
                            @endif
                        @endcan

                    </div>

                    <div class="card-body">

                        @forelse($siteVisit->observations as $observation)
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ $observation->category }}</strong>
                                    <span class="badge bg-warning">
                                        {{ ucfirst($observation->severity) }}
                                    </span>
                                </div>

                                <p class="mt-2 mb-2">
                                    {{ $observation->description }}
                                </p>

                                @if ($observation->media->count())
                                    <div>
                                        <strong>Evidence:</strong>
                                        <ul class="mb-0">
                                            @foreach ($observation->media as $media)
                                                <li>
                                                    <a href="{{ route('site-visits.media.download', [$siteVisit, $media]) }}" target="_blank">
                                                        {{ basename($media->file_path) }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">
                                No observations added yet.
                            </p>
                        @endforelse

                    </div>
                </div>

                {{-- ================= APPROVAL ================= --}}
                <div class="card mb-3">
                    <div class="card-header">
                        Approval History
                    </div>

                    <div class="card-body">

                        @forelse($siteVisit->approvals as $approval)
                            <div class="mb-3">
                                <strong>{{ $approval->reviewer->name }}</strong>
                                <span class="badge bg-info">
                                    {{ ucfirst($approval->status) }}
                                </span>
                                <p class="mb-0 mt-1">
                                    {{ $approval->remarks }}
                                </p>
                            </div>
                        @empty
                            <p class="text-muted mb-0">
                                No approval actions recorded.
                            </p>
                        @endforelse

                        {{-- APPROVAL ACTION --}}
                        @if ($siteVisit->status === 'submitted')
                            <hr>
                            <form method="POST" action="{{ route('site-visits.approve', $siteVisit) }}">
                                @csrf

                                <div class="row">
                                    @can('site_visits.approve')
                                        @if ($siteVisit->status === 'submitted')
                                            <div class="col-md-4 mb-2">
                                                <select name="status" class="form-control" required>
                                                    <option value="">-- Select Action --</option>
                                                    <option value="approved">Approve</option>
                                                    <option value="rejected">Reject</option>
                                                </select>
                                            </div>
                                        @endif
                                    @endcan

                                    <div class="col-md-8 mb-2">
                                        <textarea name="remarks" class="form-control" placeholder="Remarks (optional)"></textarea>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    Submit Decision
                                </button>
                            </form>
                        @endif

                    </div>
                </div>

                {{-- ================= SUBMIT ================= --}}
                @can('site_visits.submit')
                    @if ($siteVisit->status === 'draft')
                        <form method="POST" action="{{ route('site-visits.submit', $siteVisit) }}">
                            @csrf
                            <button class="btn btn-primary">Submit Site Visit</button>
                        </form>
                    @endif
                @endcan


            </div>
        </div>
    </main>
@endsection
