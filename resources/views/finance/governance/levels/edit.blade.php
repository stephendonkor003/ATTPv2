@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Edit Governance Level</h4>
                <p class="text-muted mb-0">Update level details.</p>
            </div>
            <a href="{{ route('finance.governance.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mt-3">{{ $errors->first() }}</div>
        @endif

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <form method="POST" action="{{ route('finance.governance.levels.update', $level) }}" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Key</label>
                        <input type="text" class="form-control" name="key" value="{{ old('key', $level->key) }}" readonly required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Name</label>
                        <input type="text" class="form-control" name="name" value="{{ old('name', $level->name) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" class="form-control" name="sort_order" value="{{ old('sort_order', $level->sort_order) }}" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" class="form-control" name="description" value="{{ old('description', $level->description) }}">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <a href="{{ route('finance.governance.index') }}" class="btn btn-light">Cancel</a>
                        <button class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
