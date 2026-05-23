@extends('layouts.app')
@section('title', 'Sub-Activities')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <!-- Header -->
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Sub-Activities under {{ $activity->name }}</h4>
                    <p class="text-muted mb-0">Manage all sub-activities associated with this activity.</p>
                </div>
                <a href="{{ route('subactivities.create', $activity->id) }}" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i> Add Sub-Activity
                </a>
            </div>

            <!-- Table -->
            <div class="card shadow-sm">
                <div class="card-body table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Sub-Activity ID</th>
                                <th>Name</th>
                                <th>Total Budget (USD)</th>
                                <th>Created</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subs as $index => $sub)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><span class="fw-semibold text-primary">{{ $sub->sub_activity_id }}</span></td>
                                    <td>{{ $sub->name }}</td>
                                    <td>{{ number_format($sub->total_budget, 2) }}</td>
                                    <td>{{ $sub->created_at->format('d M, Y') }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('subactivities.show', $sub->id) }}"
                                            class="btn btn-sm btn-outline-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('subactivities.edit', $sub->id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('subactivities.destroy', $sub->id) }}" method="POST"
                                            class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Are you sure?')">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="bi bi-info-circle me-1"></i> No sub-activities found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
@endsection
