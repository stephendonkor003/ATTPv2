@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Edit Assignment</h4>
                <p class="text-muted mb-0">{{ $assignment->user->name ?? 'Assignment' }}</p>
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
                <form method="POST" action="{{ route('finance.governance.assignments.update', $assignment) }}" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Node</label>
                        <select name="node_id" class="form-select" required>
                            @foreach ($nodes as $node)
                                <option value="{{ $node->id }}" @selected(old('node_id', $assignment->node_id) == $node->id)>
                                    {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Employee</label>
                        <select name="user_id" class="form-select" required>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(old('user_id', $assignment->user_id) == $user->id)>
                                    {{ $user->name }} - {{ $user->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Role Title</label>
                        <input type="text" class="form-control" name="role_title" value="{{ old('role_title', $assignment->role_title) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Effective Start</label>
                        <input type="date" class="form-control" name="effective_start" value="{{ old('effective_start', optional($assignment->effective_start)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Effective End</label>
                        <input type="date" class="form-control" name="effective_end" value="{{ old('effective_end', optional($assignment->effective_end)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" name="is_primary" id="isPrimary"
                                @checked(old('is_primary', $assignment->is_primary))>
                            <label class="form-check-label fw-semibold" for="isPrimary">Primary Assignment</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" name="notify_user" id="notifyUser">
                            <label class="form-check-label fw-semibold" for="notifyUser">Email notification</label>
                        </div>
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
