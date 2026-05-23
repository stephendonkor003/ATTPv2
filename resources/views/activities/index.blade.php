@extends('layouts.app')

@section('title', 'Activities Overview')

@section('content')

    <style>
        .tree-level-1 {
            background: #eef4ff;
            margin-bottom: 10px;
            border-left: 4px solid #0d6efd;
        }

        .tree-level-2 {
            background: #f8f9ff;
            border-left: 4px solid #6f42c1;
        }

        .tree-level-3 {
            background: #ffffff;
            border-left: 4px solid #198754;
        }

        .allocation-line {
            padding-left: 45px;
        }

        .hover-row:hover {
            background: #f2f6ff;
        }
    </style>

    <main class="nxl-container fade-in">
        <div class="nxl-content">

            <!-- PAGE HEADER -->
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold">Activities – Nested View</h4>
                    <p class="text-muted">Program → Project → Activity → Allocations</p>
                </div>

                <a href="{{ route('budget.projects.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back to Projects
                </a>
            </div>

            <!-- SEARCH -->
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-body p-3">
                    <form method="GET" action="{{ route('budget.activities.index') }}" class="d-flex">
                        <input type="text" name="search" class="form-control form-control-lg me-2"
                            placeholder="Search program, project, or activity..." value="{{ $search }}">
                        <button class="btn btn-primary btn-lg">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- NESTED TREE -->
            <div class="accordion" id="programAccordion">

                @forelse ($programs as $prIndex => $program)
                    @php
                        $programHeadingId = 'headingProgram' . $program->id;
                        $programCollapseId = 'collapseProgram' . $program->id;
                    @endphp
                    <div class="accordion-item mb-3 border-0 tree-level-1 rounded shadow-sm">

                        <!-- PROGRAM -->
                        <h2 class="accordion-header" id="{{ $programHeadingId }}">
                            <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse"
                                data-bs-target="#{{ $programCollapseId }}" aria-expanded="false"
                                aria-controls="{{ $programCollapseId }}">
                                📘 PROGRAM: {{ $program->name }}
                            </button>
                        </h2>

                        <!-- PROGRAM BODY -->
                        <div id="{{ $programCollapseId }}" class="accordion-collapse collapse"
                            aria-labelledby="{{ $programHeadingId }}" data-bs-parent="#programAccordion">

                            <div class="accordion-body">

                                @forelse ($program->projects as $pjIndex => $project)
                                    @php
                                        $projectHeadingId = 'headingProject' . $project->id;
                                        $projectCollapseId = 'collapseProject' . $project->id;
                                    @endphp
                                    <div class="accordion mb-2" id="projectAccordion{{ $project->id }}">

                                        <!-- PROJECT -->
                                        <div class="accordion-item tree-level-2 rounded">
                                            <h2 class="accordion-header" id="{{ $projectHeadingId }}">
                                                <button class="accordion-button collapsed" type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $projectCollapseId }}" aria-expanded="false"
                                                    aria-controls="{{ $projectCollapseId }}">
                                                    📌 {{ $project->project_id }} — {{ $project->name }}
                                                    &nbsp;&nbsp;
                                                    <small class="text-muted">
                                                        ({{ $project->start_year }} → {{ $project->end_year }})
                                                    </small>
                                                </button>
                                            </h2>

                                            <!-- PROJECT BODY -->
                                            <div id="{{ $projectCollapseId }}" class="accordion-collapse collapse"
                                                aria-labelledby="{{ $projectHeadingId }}">

                                                <div class="accordion-body">

                                                    @forelse ($project->activities as $acIndex => $activity)
                                                        @php
                                                            $activityHeadingId = 'headingActivity' . $activity->id;
                                                            $activityCollapseId = 'collapseActivity' . $activity->id;
                                                        @endphp
                                                        <div class="accordion tree-level-3 mb-2"
                                                            id="activityAccordion{{ $activity->id }}">

                                                            <!-- ACTIVITY -->
                                                            <div class="accordion-item">
                                                                <h2 class="accordion-header"
                                                                    id="{{ $activityHeadingId }}">
                                                                    <button class="accordion-button collapsed"
                                                                        type="button" data-bs-toggle="collapse"
                                                                        data-bs-target="#{{ $activityCollapseId }}"
                                                                        aria-expanded="false"
                                                                        aria-controls="{{ $activityCollapseId }}">
                                                                        🎯 {{ $activity->name }}
                                                                        &nbsp;—&nbsp;
                                                                        <small class="text-muted">
                                                                            Total:
                                                                            {{ number_format($activity->allocations->sum('amount'), 2) }}
                                                                            {{ $project->currency }}
                                                                        </small>
                                                                    </button>
                                                                </h2>

                                                                <!-- ACTIVITY BODY -->
                                                                <div id="{{ $activityCollapseId }}"
                                                                    class="accordion-collapse collapse"
                                                                    aria-labelledby="{{ $activityHeadingId }}">
                                                                    <div class="accordion-body">

                                                                        {{-- ALLOCATION TABLE --}}
                                                                        <table class="table table-striped table-bordered">
                                                                            <thead class="table-light">
                                                                                <tr>
                                                                                    <th>Year</th>
                                                                                    <th>Amount ({{ $project->currency }})
                                                                                    </th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                @foreach ($activity->allocations as $alloc)
                                                                                    <tr class="hover-row">
                                                                                        <td class="fw-semibold">
                                                                                            {{ $alloc->year }}</td>
                                                                                        <td>{{ number_format($alloc->amount, 2) }}
                                                                                        </td>
                                                                                    </tr>
                                                                                @endforeach
                                                                            </tbody>
                                                                        </table>

                                                                        <div class="text-end mt-2">
                                                                            @can('activities.edit')
                                                                                <a href="{{ route('budget.activities.edit', $activity->id) }}"
                                                                                    class="btn btn-sm btn-primary">
                                                                                    <i class="bi bi-sliders"></i> Edit
                                                                                    Allocations
                                                                                </a>
                                                                            @endcan
                                                                            <a href="{{ route('budget.activities.show', $activity->id) }}"
                                                                                class="btn btn-sm btn-info">
                                                                                <i class="bi bi-eye"></i> View Details
                                                                            </a>
                                                                        </div>

                                                                    </div>
                                                                </div>

                                                            </div>

                                                        </div>

                                                    @empty
                                                        <p class="text-muted ps-4">No activities found.</p>
                                                    @endforelse

                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                @empty
                                    <p class="text-muted">No projects found under this program.</p>
                                @endforelse

                            </div>
                        </div>
                    </div>

                @empty
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="text-muted">No programs, projects, or activities available.</p>
                    </div>
                @endforelse

            </div>

        </div>
    </main>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('#programAccordion .accordion-collapse').forEach((collapse) => {
                    collapse.addEventListener('hidden.bs.collapse', (event) => {
                        if (event.target !== collapse) {
                            return;
                        }

                        collapse.querySelectorAll('.accordion-collapse.show').forEach((child) => {
                            bootstrap.Collapse.getOrCreateInstance(child, { toggle: false }).hide();
                        });

                        collapse.querySelectorAll('.accordion-button:not(.collapsed)').forEach((button) => {
                            button.classList.add('collapsed');
                            button.setAttribute('aria-expanded', 'false');
                        });
                    });
                });
            });
        </script>
    @endpush

@endsection
