@extends('layouts.app')
@section('title', 'Applications')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Applications</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item">All Applications</li>
                    </ul>
                </div>
            </div>

            <!-- Compact Cards (at the top) -->
            <div class="main-content mb-4">
                <h6 class="mb-3 fw-bold">Applications Overview</h6>
                <div class="row g-3">
                    @forelse ($applicants as $index => $applicant)
                        @php
                            $colors = ['primary', 'success', 'warning', 'info', 'danger'];
                            $color = $colors[$index % count($colors)];
                            $subRegions = $applicant->sub_region ? json_decode($applicant->sub_region, true) : [];
                        @endphp

                        <div class="col-md-3 col-sm-6">
                            <div class="card border-0 shadow-sm h-100 app-card bg-light">
                                <div class="card-body position-relative p-3 text-center">
                                    <!-- Short Vital Info -->
                                    <h6 class="fw-bold text-{{ $color }}">{{ $applicant->code }}</h6>
                                    <p class="mb-1 small"><strong>Consortium:</strong>
                                        {{ Str::limit($applicant->consortium_name, 15) ?? '—' }}</p>
                                    <p class="mb-1 small"><strong>Email:</strong> {{ Str::limit($applicant->email, 18) }}
                                    </p>
                                    <p class="mb-1 small">
                                        <strong>Countries:</strong>
                                        <span
                                            class="badge bg-{{ $color }}">{{ $applicant->covered_count ?? 0 }}</span>
                                    </p>

                                    <!-- Overlay (hidden until hover) -->
                                    <div class="app-card-overlay text-start">
                                        <h6 class="fw-bold text-{{ $color }}">Details</h6>
                                        <p><strong>Think Tank:</strong> {{ $applicant->think_tank_name }}</p>
                                        <p><strong>Lead Think Tank:</strong> {{ $applicant->lead_think_tank_name }}</p>
                                        {{-- <p><strong>Lead Country:</strong> {{ $applicant->lead_think_tank_country ?? '—' }} --}}
                                        </p>
                                        <p><strong>Sub Regions:</strong> {{ implode(', ', $subRegions) ?: '—' }}</p>
                                        <p><strong>Consortium Region:</strong> {{ $applicant->consortium_region ?? '—' }}
                                        </p>
                                        <p><strong>Covered Countries:</strong> {{ $applicant->covered_list ?? '—' }}</p>
                                        <p><strong>Submitted:</strong> {{ $applicant->created_at->format('d M, Y') }}</p>
                                        <div class="mt-2">
                                            <a href="{{ route('applicants.show', $applicant->id) }}"
                                                class="btn btn-sm btn-outline-{{ $color }}">View</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center">No applications found.</p>
                    @endforelse
                </div>
            </div>

            <!-- Table (full details) -->
            <div class="main-content">
                <div class="card">
                    <div class="card-body table-responsive">
                        <h6 class="mb-3 fw-bold">Applications Table</h6>
                        <table class="display nowrap table table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Think Tank</th>
                                    <th>Country</th>
                                    <th>Consortium</th>
                                    <th>Email</th>
                                    <th>Countries Covered</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($applicants as $index => $applicant)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $applicant->lead_think_tank_name }}</td>
                                        <td>{{ $applicant->country }}</td>
                                        <td>{{ $applicant->consortium_name ?? '-' }}</td>
                                        <td>{{ $applicant->email }}</td>
                                        <td>
                                            @if ($applicant->covered_count > 0)
                                                <span class="badge bg-info text-dark" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="{{ $applicant->covered_list }}">
                                                    {{ $applicant->covered_count }}
                                                </span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td>{{ $applicant->created_at->format('d M, Y') }}</td>
                                        <td>
                                            <a href="{{ route('applicants.show', $applicant->id) }}"
                                                class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">No applications found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <div class="mt-3">
                            {{ $applicants->withQueryString()->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>


    <style>
        .app-card {
            min-height: 160px;
            /* compact size */
            overflow: hidden;
            border-radius: 12px;
            transition: transform 0.2s ease-in-out;
        }

        .app-card:hover {
            transform: translateY(-5px);
        }

        /* Overlay hidden by default */
        .app-card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.98);
            padding: 12px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease-in-out;
            overflow-y: auto;
            border-radius: 12px;
            font-size: 0.85rem;
        }

        .app-card:hover .app-card-overlay {
            opacity: 1;
            visibility: visible;
        }
    </style>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
    </script>
@endsection
