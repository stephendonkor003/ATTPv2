@extends('layouts.app')

@section('title', 'Edit Member State')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Edit Member State</h4>
                    <p class="text-muted mb-0">Update {{ $memberState->name }}</p>
                </div>
                <a href="{{ route('settings.au.member-states.index') }}" class="btn btn-outline-secondary">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form action="{{ route('settings.au.member-states.update', $memberState->id) }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Country Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name', $memberState->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ISO Alpha-3 Code</label>
                                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                    value="{{ old('code', $memberState->code) }}" maxlength="3">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ISO Alpha-2 Code</label>
                                <input type="text" name="code_alpha2"
                                    class="form-control @error('code_alpha2') is-invalid @enderror"
                                    value="{{ old('code_alpha2', $memberState->code_alpha2) }}" maxlength="2">
                                @error('code_alpha2')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sort Order</label>
                                <input type="number" name="sort_order"
                                    class="form-control @error('sort_order') is-invalid @enderror"
                                    value="{{ old('sort_order', $memberState->sort_order) }}" min="0">
                                @error('sort_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Flag Image</label>
                                <input type="file" name="flag_image"
                                    class="form-control @error('flag_image') is-invalid @enderror"
                                    accept="image/*">
                                <small class="text-muted">Upload a new image to replace the current flag.</small>
                                @error('flag_image')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                @if ($memberState->flag_url)
                                    <div class="mt-2 d-flex align-items-center gap-2">
                                        <img src="{{ $memberState->flag_url }}" alt="{{ $memberState->name }} flag"
                                            style="width: 46px; height: 32px; object-fit: cover; border: 1px solid #d1d5db; border-radius: 4px;">
                                        <small class="text-muted">Current flag</small>
                                    </div>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                        id="is_active" {{ old('is_active', $memberState->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-between">
                            <a href="{{ route('settings.au.member-states.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-save me-1"></i> Update Member State
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
@endsection
