@extends('layouts.app')
@section('title','Create Methodology')

@section('content')
<div class="nxl-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-book-open text-primary me-2"></i>Create Methodology</h4>
            <p class="text-muted mb-0">Define a reusable measurement approach.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('budget.me-configuration.methodologies.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Active</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label">Enabled</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Steps / Guidance</label>
                    <textarea name="steps" rows="5" class="form-control" placeholder="E.g., data sources, calculation notes">{{ old('steps') }}</textarea>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('budget.me-configuration.methodologies.index') }}" class="btn btn-light border">Cancel</a>
                    <button class="btn btn-primary" type="submit"><i class="feather-save me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
