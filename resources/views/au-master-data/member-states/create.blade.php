@extends('layouts.app')

@section('title', 'Add Member State')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Add Member State</h4>
                    <p class="text-muted mb-0">Add a new AU member state.</p>
                </div>
                <a href="{{ route('settings.au.member-states.index') }}" class="btn btn-outline-secondary">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form action="{{ route('settings.au.member-states.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Country Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}" placeholder="e.g. Ghana" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ISO Alpha-3 Code</label>
                                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                    value="{{ old('code') }}" placeholder="e.g. GHA" maxlength="3">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ISO Alpha-2 Code</label>
                                <input type="text" name="code_alpha2"
                                    class="form-control @error('code_alpha2') is-invalid @enderror"
                                    value="{{ old('code_alpha2') }}" placeholder="e.g. GH" maxlength="2">
                                @error('code_alpha2')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sort Order</label>
                                <input type="number" name="sort_order"
                                    class="form-control @error('sort_order') is-invalid @enderror"
                                    value="{{ old('sort_order', 0) }}" min="0">
                                @error('sort_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Flag Image</label>
                                <input type="file" name="flag_image"
                                    class="form-control @error('flag_image') is-invalid @enderror"
                                    accept="image/*">
                                <small class="text-muted">Upload any flag image format (JPG, PNG, WEBP, SVG, GIF).</small>
                                @error('flag_image')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                        id="is_active" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-between">
                            <a href="{{ route('settings.au.member-states.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-success">
                                <i class="feather-check me-1"></i> Save Member State
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
@endsection
