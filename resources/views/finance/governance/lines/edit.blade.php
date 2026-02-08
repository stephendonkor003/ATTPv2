@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Edit Reporting Line</h4>
                <p class="text-muted mb-0">Update parent/child relationship.</p>
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
                <form method="POST" action="{{ route('finance.governance.lines.update', $line) }}" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Child Node</label>
                        <select name="child_node_id" class="form-select" required>
                            @foreach ($nodes as $node)
                                <option value="{{ $node->id }}" @selected(old('child_node_id', $line->child_node_id) == $node->id)>
                                    {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Parent Node</label>
                        <select name="parent_node_id" class="form-select" required>
                            @foreach ($nodes as $node)
                                <option value="{{ $node->id }}" @selected(old('parent_node_id', $line->parent_node_id) == $node->id)>
                                    {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Line Type</label>
                        <select name="line_type" class="form-select" required>
                            <option value="primary" @selected(old('line_type', $line->line_type) === 'primary')>Primary (Hierarchy)</option>
                            <option value="dotted" @selected(old('line_type', $line->line_type) === 'dotted')>Dotted (Matrix)</option>
                            <option value="advisory" @selected(old('line_type', $line->line_type) === 'advisory')>Advisory</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Effective Start</label>
                        <input type="date" class="form-control" name="effective_start" value="{{ old('effective_start', optional($line->effective_start)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Effective End</label>
                        <input type="date" class="form-control" name="effective_end" value="{{ old('effective_end', optional($line->effective_end)->format('Y-m-d')) }}">
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
