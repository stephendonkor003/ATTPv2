@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Edit Structure Node</h4>
                <p class="text-muted mb-0">{{ $node->name }}</p>
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
                <form method="POST" action="{{ route('finance.governance.nodes.update', $node) }}" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Level</label>
                        <select name="level_id" class="form-select" required>
                            @foreach ($levels as $level)
                                <option value="{{ $level->id }}" @selected(old('level_id', $node->level_id) == $level->id)>{{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="active" @selected(old('status', $node->status) === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $node->status) === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Name</label>
                        <input type="text" class="form-control" name="name" value="{{ old('name', $node->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Code</label>
                        <input type="text" class="form-control" name="code" value="{{ old('code', $node->code) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Effective Start</label>
                        <input type="date" class="form-control" name="effective_start" value="{{ old('effective_start', optional($node->effective_start)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" name="description" rows="3">{{ old('description', $node->description) }}</textarea>
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
