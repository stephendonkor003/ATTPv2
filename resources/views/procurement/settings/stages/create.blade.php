@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Create Procurement Stage</h4>
                <p class="text-muted mb-0">
                    Add a new procurement stage
                </p>
            </div>
            <a href="{{ route('procurement.settings.stages.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        {{-- ================= VALIDATION ERRORS ================= --}}
        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                <strong>Please fix the following issues:</strong>
                <ul class="mb-0 mt-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ================= FORM CARD ================= --}}
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <form method="POST" action="{{ route('procurement.settings.stages.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            Stage Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="stage_name" class="form-control"
                            placeholder="e.g. Planning, Tendering, Evaluation" value="{{ old('stage_name') }}" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" rows="3" class="form-control"
                            placeholder="Brief description of this stage">{{ old('description') }}</textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control"
                            placeholder="e.g. 1, 2, 3" value="{{ old('sort_order', 0) }}" min="0">
                        <small class="text-muted">Lower numbers appear first</small>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="{{ route('procurement.settings.stages.index') }}" class="btn btn-light">Cancel</a>
                        <button class="btn btn-primary">
                            <i class="feather-save me-1"></i> Save Stage
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
