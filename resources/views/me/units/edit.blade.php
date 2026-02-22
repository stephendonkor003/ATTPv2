@extends('layouts.app')
@section('title', 'Edit Indicator Unit')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-ruler text-primary me-2"></i>
                    Edit Indicator Unit
                </h4>
                <p class="text-muted mb-0">Update measurement unit details.</p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form action="{{ route('budget.me-configuration.units.update', $unit) }}" method="POST" class="row g-3">
                    @csrf
                    @method('PUT')

                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $unit->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Symbol</label>
                        <input type="text" name="symbol" class="form-control @error('symbol') is-invalid @enderror"
                               value="{{ old('symbol', $unit->symbol) }}" maxlength="20">
                        @error('symbol')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Sort Order</label>
                        <input type="text" class="form-control" value="{{ $unit->sort_order }}" readonly>
                        <small class="text-muted">Sort order is managed automatically by the system.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $unit->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                   {{ old('is_active', $unit->is_active) ? 'checked' : '' }}>
                            <span class="form-check-label">Active</span>
                        </label>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('budget.me-configuration.units.index') }}" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="feather-save me-1"></i> Update Unit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
