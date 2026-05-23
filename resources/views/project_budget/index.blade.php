@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0 fw-bold text-primary"><i class="bi bi-folder2-open me-2"></i>Project Budgetary Allocations</h4>
            <a href="{{ route('project_budget.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Project
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
        @endif

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Project ID</th>
                            <th>Project Name</th>
                            <th>Total Budget (USD)</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($projects as $index => $project)
                            <tr>
                                <td>{{ $projects->firstItem() + $index }}</td>
                                <td class="fw-bold text-primary">{{ $project->project_id }}</td>
                                <td>{{ $project->project_name }}</td>
                                <td>{{ number_format($project->total_budget, 2) }}</td>
                                <td>{{ $project->start_year }} - {{ $project->end_year }}</td>
                                <td>
                                    <span class="badge bg-success">{{ ucfirst($project->status) }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('project_budget.show', $project->id) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <form action="{{ route('project_budget.destroy', $project->id) }}" method="POST"
                                        class="d-inline" onsubmit="return confirm('Delete this project?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No projects found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-3">{{ $projects->links() }}</div>
            </div>
        </div>
    </div>
@endsection
