@extends('layouts.app')

@section('title', 'Edit Vendor Category')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-edit text-primary me-2"></i>
                    Edit Vendor Category
                </h4>
                <p class="text-muted mb-0">Update vendor category details.</p>
            </div>
            <a href="{{ route('vendors.categories.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('vendors.categories.update', $category) }}">
            @csrf
            @method('PUT')
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Category Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" class="form-control"
                                value="{{ old('name', $category->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                    {{ old('is_active', $category->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" rows="4" class="form-control">{{ old('description', $category->description) }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light d-flex justify-content-end gap-2">
                    <a href="{{ route('vendors.categories.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-1"></i> Update Category
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
