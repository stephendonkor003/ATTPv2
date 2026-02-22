@extends('layouts.app')
@php($program = $program ?? null)
@section('title', $program ? 'Projects under ' . ($program->name ?? 'Program') : 'Projects')

@section('content')
    <style>
        .project-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.22rem 0.62rem;
            font-size: 0.72rem;
            font-weight: 600;
            border: 1px solid rgba(248, 250, 252, 0.38);
            background: rgba(248, 250, 252, 0.18);
            color: #f8fafc;
        }
    </style>

    @if ($program)
        <div class="modal fade" id="programInfoModal" tabindex="-1" aria-labelledby="programInfoModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="programInfoModalLabel">
                            <i class="feather-folder me-2"></i> Program Information
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Program Name:</strong> {{ $program->name }}</p>
                        <p><strong>Sector:</strong> {{ $program->sector->name ?? 'N/A' }}</p>
                        <p><strong>Description:</strong></p>
                        <p class="text-muted">{{ $program->description ?? 'No description provided.' }}</p>
                        <hr>
                        <p class="mb-0">
                            <strong>Created On:</strong>
                            {{ $program->created_at ? $program->created_at->format('d M, Y') : 'N/A' }}
                        </p>
                        <p><strong>Total Projects:</strong> {{ $program->projects->count() }}</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="feather-x-circle me-1"></i> Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header">
                <div class="page-header-left">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="project-chip">Budget - Projects</span>
                        <span class="project-chip">{{ $program ? 'Within Program' : 'All Programs' }}</span>
                    </div>
                    <h5 class="m-b-10">Projects Overview</h5>
                    <p class="mb-0">
                        @if ($program)
                            Manage every project under <strong>{{ $program->name }}</strong>, including budgets and timelines.
                        @else
                            Manage all budget projects across programs with quick actions and insights.
                        @endif
                    </p>
                </div>
                <div class="page-header-right ms-auto">
                    @can('project.create')
                        <a href="{{ route('budget.projects.create') }}" class="btn btn-light text-primary border-0 shadow-sm">
                            <i class="bi bi-plus-circle me-1"></i> New Project
                        </a>
                    @endcan
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="feather-check-circle me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="feather-alert-triangle me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-semibold mb-0">
                            <i class="feather-list me-2 text-primary"></i> Total Projects:
                            <span class="text-dark">{{ $projects->count() }}</span>
                        </h6>
                        @if ($program)
                            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
                                data-bs-target="#programInfoModal">
                                <i class="feather-info me-1"></i> Program Details
                            </button>
                        @endif
                    </div>

                    <x-data-table id="projectsTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Project ID</th>
                                <th>Project Name</th>
                                <th>Program</th>
                                <th>Total Budget (GHS)</th>
                                <th>Duration (Yrs)</th>
                                <th>Created On</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($projects as $project)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><span class="fw-semibold text-primary">{{ $project->project_id }}</span></td>
                                    <td>{{ $project->name }}</td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success">
                                            {{ $project->program->name ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($project->total_budget, 2) }}</td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            {{ $project->total_years ?? $project->duration_years }}
                                        </span>
                                    </td>
                                    <td>{{ $project->created_at->format('d M, Y') }}</td>
                                    <td class="text-center">
                                        @can('project.view')
                                            <a href="{{ route('budget.projects.show', $project->id) }}"
                                                class="btn btn-sm btn-outline-info" title="View Details">
                                                <i class="feather-eye"></i>
                                            </a>
                                        @endcan
                                        @can('project.edit')
                                            <a href="{{ route('budget.projects.edit', $project->id) }}"
                                                class="btn btn-sm btn-outline-warning" title="Edit Project">
                                                <i class="feather-edit"></i>
                                            </a>
                                        @endcan
                                        @can('project.delete')
                                            <form action="{{ route('budget.projects.destroy', $project->id) }}" method="POST"
                                                class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Are you sure you want to delete this project?')"
                                                    title="Delete Project">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="feather-info"></i>
                                        No projects have been added under this program yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-data-table>
                </div>
            </div>
        </div>
    </main>
@endsection
