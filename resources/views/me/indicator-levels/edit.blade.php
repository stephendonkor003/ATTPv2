@extends('layouts.app')
@section('title', 'Edit Indicator Level')

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-layers text-primary me-2"></i>
                    Edit Indicator Level
                </h4>
                <p class="text-muted mb-0">Update level details and status.</p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form action="{{ route('budget.me-configuration.indicator-levels.update', $level) }}" method="POST" class="row g-3">
                    @csrf
                    @method('PUT')

                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" name="name"
                               value="{{ old('name', $level->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Sort Order</label>
                        <input type="number" class="form-control @error('sort_order') is-invalid @enderror"
                               name="sort_order" value="{{ old('sort_order', $level->sort_order) }}" min="0">
                        @error('sort_order')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="3">{{ old('description', $level->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" value="1"
                                   {{ old('is_active', $level->is_active) ? 'checked' : '' }}>
                            <span class="form-check-label">Active</span>
                        </label>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('budget.me-configuration.indicator-levels.index') }}" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-save me-1"></i> Update Level
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
