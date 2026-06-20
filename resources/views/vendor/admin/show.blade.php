@extends('layouts.app')

@section('title', 'Vendor Details')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-briefcase text-primary me-2"></i>
                    Vendor Details
                </h4>
                <p class="text-muted mb-0">{{ $vendor->name }} funding source assignments.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('vendors.edit', $vendor) }}" class="btn btn-primary btn-sm">
                    <i class="feather-edit me-1"></i> Edit Vendor
                </a>
                <a href="{{ route('vendors.index') }}" class="btn btn-light btn-sm">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Vendor</div>
                        <h5 class="fw-bold mb-1">{{ $vendor->name }}</h5>
                        <div class="text-muted mb-3">{{ $vendor->email }}</div>

                        <div class="mb-3">
                            <div class="text-muted small">Category</div>
                            <div class="fw-semibold">{{ $vendor->vendor_category ?: 'Not set' }}</div>
                        </div>

                        <div>
                            <div class="text-muted small">Status</div>
                            @if ($vendor->is_blacklisted)
                                <span class="badge bg-danger">Blacklisted</span>
                            @elseif ($vendor->is_disabled)
                                <span class="badge bg-warning text-dark">Disabled</span>
                            @else
                                <span class="badge bg-success">Active</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="fw-semibold mb-0">Funding Sources</h6>
                        <span class="badge bg-secondary">{{ $vendor->vendorSubActivityAssignments->count() }}</span>
                    </div>
                    <div class="card-body">
                        @if ($vendor->vendorSubActivityAssignments->isEmpty())
                            <p class="text-muted mb-0">No funding sources assigned yet.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Program</th>
                                            <th>Project</th>
                                            <th>Activity</th>
                                            <th>Sub Activity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($vendor->vendorSubActivityAssignments as $assignment)
                                            @php
                                                $subActivity = $assignment->subActivity;
                                                $activity = $assignment->activity ?: $subActivity?->activity;
                                                $project = $assignment->project ?: $activity?->project;
                                                $program = $assignment->program ?: $project?->program;
                                            @endphp
                                            <tr>
                                                <td>{{ $program?->name ?? 'N/A' }}</td>
                                                <td>{{ $project?->name ?? 'N/A' }}</td>
                                                <td>{{ $activity?->name ?? 'N/A' }}</td>
                                                <td>{{ $subActivity?->name ?? 'N/A' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
