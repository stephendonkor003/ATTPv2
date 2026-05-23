@extends('layouts.app')
@section('title', 'Edit Sub-Activity')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Edit Sub-Activity</h4>
                    <p class="text-muted mb-0">Update details for this sub-activity.</p>
                </div>
                <a href="{{ route('subactivities.index', $subActivity->activity_id) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back to Sub-Activities
                </a>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="{{ route('subactivities.update', $subActivity->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sub-Activity Name</label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $subActivity->name) }}" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Total Budget (USD)</label>
                                <input type="number" step="0.01" name="total_budget" class="form-control"
                                    value="{{ old('total_budget', $subActivity->total_budget) }}" required>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $subActivity->description) }}</textarea>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <a href="{{ route('subactivities.index', $subActivity->activity_id) }}"
                                class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save2 me-1"></i> Update Sub-Activity
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
@endsection
