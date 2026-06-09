@extends('layouts.app')
@section('title', 'Add Sub-Activity')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Create New Sub-Activity</h4>
                    <p class="text-muted mb-0">Add a new sub-activity under <strong>{{ $activity->name }}</strong>.</p>
                </div>
                <a href="{{ route('budget.activities.show', $activity->id) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back to Sub-Activities
                </a>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="{{ route('budget.subactivities.store') }}" method="POST">
                        @csrf

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sub-Activity Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required
                                    placeholder="Enter sub-activity name">
                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Total Budget (USD) <span
                                        class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="total_budget" class="form-control" required>
                                @error('total_budget')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Optional details"></textarea>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <a href="{{ route('budget.activities.show', $activity->id) }}"
                                class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check2-circle me-1"></i> Save Sub-Activity
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
@endsection
