@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Edit Step Stage</h4>
                <p class="text-muted mb-0">
                    Update step stage information
                </p>
            </div>
            <a href="{{ route('procurement.settings.step-stages.index') }}" class="btn btn-light">
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
                <form method="POST" action="{{ route('procurement.settings.step-stages.update', $stepStage) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" class="form-control"
                            value="{{ old('name', $stepStage->name) }}" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Parent Stage</label>
                        <select name="stage_id" class="form-select">
                            <option value="">-- Select Stage --</option>
                            @foreach($stages as $stage)
                                <option value="{{ $stage->id }}"
                                    {{ old('stage_id', $stepStage->stage_id) == $stage->id ? 'selected' : '' }}>
                                    {{ $stage->stage_name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">The procurement stage this step belongs to</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" rows="3" class="form-control"
                            placeholder="Brief description of this step stage">{{ old('description', $stepStage->description) }}</textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control"
                            value="{{ old('sort_order', $stepStage->sort_order) }}" min="0">
                        <small class="text-muted">Lower numbers appear first</small>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                {{ old('is_active', $stepStage->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="{{ route('procurement.settings.step-stages.index') }}" class="btn btn-light">Cancel</a>
                        <button class="btn btn-primary">
                            <i class="feather-save me-1"></i> Update Step Stage
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
