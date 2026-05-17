@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-dark">Edit Sub-Activity Budget — {{ $sub->name }}</h4>

            <a href="{{ route('budget.projects.show', $sub->activity->project_id) }}" class="btn btn-secondary">
                Back
            </a>
        </div>

        <div class="card mt-3 shadow-sm">
            <div class="card-body">

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <form action="{{ route('budget.subactivities.allocations.update', $sub->id) }}" method="POST">
                    @csrf

                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Year</th>
                                <th>Allocation ({{ $sub->activity->project->program->currency }})</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($sub->allocations as $alloc)
                                <tr>
                                    <td>{{ $alloc->year }}</td>
                                    <td>
                                        <input type="number" step="0.01" name="allocations[{{ $alloc->id }}]"
                                            value="{{ $alloc->amount }}" class="form-control text-end">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <button class="btn btn-primary mt-3">Save Changes</button>
                </form>


            </div>
        </div>

    </div>
@endsection
