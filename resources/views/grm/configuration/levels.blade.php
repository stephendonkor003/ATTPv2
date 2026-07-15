@extends('layouts.app')

@section('title', 'Grievance Configuration')

@section('content')
    <style>
        .grm-hero { border-radius: 8px; padding: 22px; color: #fff; background: linear-gradient(135deg, #064e3b 0%, #0f766e 60%, #522b39 100%); box-shadow: 0 18px 36px rgba(6,78,59,.18); }
        .grm-hero h4, .grm-hero p { color: #fff; }
        .grm-card { border: 1px solid #dbe5df; border-radius: 8px; background: #fff; box-shadow: 0 12px 26px rgba(15,23,42,.06); }
        .grm-card-header { padding: 16px 18px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; border-radius: 8px 8px 0 0; }
        .grm-card-body { padding: 18px; }
        .grm-dot { width: 14px; height: 14px; display: inline-block; border-radius: 999px; }
        .grm-table td, .grm-table th { vertical-align: middle; }
    </style>

    <div class="container-fluid">
        <div class="grm-hero mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <span class="badge bg-light text-success mb-2">GRM Configuration</span>
                    <h4 class="mb-1">Grievance Levels</h4>
                    <p class="mb-0">Define severity levels, response clocks, and resolution clocks per program.</p>
                </div>
                <a href="{{ route('grm.escalations.index') }}" class="btn btn-light text-success fw-bold">
                    <i class="feather-clock me-1"></i> Escalations
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm">{{ $errors->first() }}</div>
        @endif

        <div class="grm-card mb-3">
            <div class="grm-card-header">
                <h6 class="fw-bold mb-0">Create Level</h6>
            </div>
            <form method="POST" action="{{ route('grm.levels.store') }}" class="grm-card-body">
                @csrf
                <div class="row g-3">
                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Program Scope</label>
                        <select name="program_id" class="form-select" @if (! $canCreateGlobal) required @endif>
                            @if ($canCreateGlobal)
                                <option value="">Global default</option>
                            @else
                                <option value="">Select program</option>
                            @endif
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}">{{ $program->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="High" required>
                    </div>
                    <div class="col-lg-1">
                        <label class="form-label fw-semibold">Color</label>
                        <input type="color" name="color" class="form-control form-control-color w-100" value="#0f766e">
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Priority *</label>
                        <input type="number" name="priority" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Response Hours</label>
                        <input type="number" name="response_due_hours" class="form-control" min="1" placeholder="24">
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Resolution Hours</label>
                        <input type="number" name="resolution_due_hours" class="form-control" min="1" placeholder="120">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="levelActive" checked>
                            <label for="levelActive" class="form-check-label">Active</label>
                        </div>
                        <button class="btn btn-success"><i class="feather-save me-1"></i> Save Level</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="grm-card">
            <div class="grm-card-header">
                <h6 class="fw-bold mb-0">Configured Levels</h6>
            </div>
            @foreach ($levels as $level)
                <form id="level-update-{{ $level->id }}" method="POST" action="{{ route('grm.levels.update', $level) }}" class="d-none">
                    @csrf
                    @method('PUT')
                </form>
            @endforeach
            <div class="table-responsive">
                <table class="table grm-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Level</th>
                            <th>Scope</th>
                            <th>Priority</th>
                            <th>Response</th>
                            <th>Resolution</th>
                            <th>Status</th>
                            <th class="text-end">Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($levels as $level)
                            <tr>
                                <td style="min-width: 220px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="grm-dot" style="background: {{ $level->color }}"></span>
                                        <input form="level-update-{{ $level->id }}" type="text" name="name" value="{{ $level->name }}" class="form-control form-control-sm" required>
                                        <input form="level-update-{{ $level->id }}" type="color" name="color" value="{{ $level->color }}" class="form-control form-control-color form-control-sm">
                                    </div>
                                </td>
                                <td>{{ $level->program?->name ?? 'Global default' }}</td>
                                <td><input form="level-update-{{ $level->id }}" type="number" name="priority" value="{{ $level->priority }}" min="1" class="form-control form-control-sm"></td>
                                <td><input form="level-update-{{ $level->id }}" type="number" name="response_due_hours" value="{{ $level->response_due_hours }}" min="1" class="form-control form-control-sm"></td>
                                <td><input form="level-update-{{ $level->id }}" type="number" name="resolution_due_hours" value="{{ $level->resolution_due_hours }}" min="1" class="form-control form-control-sm"></td>
                                <td>
                                    <input form="level-update-{{ $level->id }}" type="hidden" name="is_active" value="0">
                                    <div class="form-check form-switch">
                                        <input form="level-update-{{ $level->id }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($level->is_active)>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <input form="level-update-{{ $level->id }}" type="hidden" name="description" value="{{ $level->description }}">
                                    <button form="level-update-{{ $level->id }}" class="btn btn-sm btn-outline-success"><i class="feather-check"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No grievance levels configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
