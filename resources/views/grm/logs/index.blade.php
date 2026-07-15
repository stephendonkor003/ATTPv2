@extends('layouts.app')

@section('title', 'Grievance Logs')

@section('content')
    <style>
        .grm-hero { border-radius: 8px; padding: 22px; color: #fff; background: linear-gradient(135deg, #064e3b 0%, #0f766e 60%, #522b39 100%); box-shadow: 0 18px 36px rgba(6,78,59,.18); }
        .grm-hero h4, .grm-hero p { color: #fff; }
        .grm-stat { border: 1px solid #dbe5df; border-radius: 8px; padding: 16px; background: #fff; box-shadow: 0 10px 24px rgba(15,23,42,.05); }
        .grm-stat small { color: #64748b; font-weight: 700; text-transform: uppercase; }
        .grm-stat strong { display: block; color: #0f172a; font-size: 1.6rem; }
        .grm-card { border: 1px solid #dbe5df; border-radius: 8px; background: #fff; box-shadow: 0 12px 26px rgba(15,23,42,.06); }
        .grm-card-header { padding: 16px 18px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; border-radius: 8px 8px 0 0; }
        .grm-table td, .grm-table th { vertical-align: middle; }
        .grm-case { font-weight: 800; color: #0f766e; }
    </style>

    <div class="container-fluid">
        <div class="grm-hero mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <span class="badge bg-light text-success mb-2">GRM Records</span>
                    <h4 class="mb-1">Grievance Logs</h4>
                    <p class="mb-0">Track all cases by number, program, severity, status, and response deadline.</p>
                </div>
                <a href="{{ route('grm.submissions.create') }}" class="btn btn-light text-success fw-bold">
                    <i class="feather-plus me-1"></i> New Case
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="grm-stat"><small>Total cases</small><strong>{{ number_format($stats['total']) }}</strong></div></div>
            <div class="col-md-3"><div class="grm-stat"><small>Unattended</small><strong>{{ number_format($stats['unattended']) }}</strong></div></div>
            <div class="col-md-3"><div class="grm-stat"><small>Responded</small><strong>{{ number_format($stats['responded']) }}</strong></div></div>
            <div class="col-md-3"><div class="grm-stat"><small>Resolved</small><strong>{{ number_format($stats['resolved']) }}</strong></div></div>
        </div>

        <div class="grm-card">
            <div class="grm-card-header">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-lg-3">
                        <label class="form-label small fw-bold">Search</label>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Case, subject, name, email">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label small fw-bold">Program</label>
                        <select name="program_id" class="form-select">
                            <option value="">All programs</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" @selected(request('program_id') === $program->id)>{{ $program->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label small fw-bold">Level</label>
                        <select name="level_id" class="form-select">
                            <option value="">All levels</option>
                            @foreach ($levels as $level)
                                <option value="{{ $level->id }}" @selected(request('level_id') === $level->id)>{{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label small fw-bold">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 d-flex gap-2">
                        <button class="btn btn-success flex-fill"><i class="feather-filter me-1"></i> Filter</button>
                        <a href="{{ route('grm.logs.index') }}" class="btn btn-outline-secondary"><i class="feather-x"></i></a>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table grm-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Case Number</th>
                            <th>Program</th>
                            <th>Submitter</th>
                            <th>Level</th>
                            <th>Status</th>
                            <th>Response Due</th>
                            <th>Submitted</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grievances as $grievance)
                            <tr>
                                <td><a class="grm-case" href="{{ route('grm.logs.show', $grievance) }}">{{ $grievance->case_number }}</a></td>
                                <td>
                                    <div class="fw-semibold">{{ $grievance->program?->name ?? 'No program' }}</div>
                                    <small class="text-muted">{{ $grievance->program?->sector?->name }}</small>
                                </td>
                                <td>
                                    <div>{{ $grievance->is_anonymous ? 'Anonymous' : ($grievance->submitter_name ?: 'Not provided') }}</div>
                                    <small class="text-muted">{{ $grievance->submitter_email }}</small>
                                </td>
                                <td>
                                    <span class="badge" style="background: {{ $grievance->level?->color ?? '#0f766e' }}">{{ $grievance->level?->name ?? 'Unclassified' }}</span>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $grievance->status_label }}</span></td>
                                <td>
                                    <span class="{{ $grievance->due_response_at && $grievance->due_response_at->isPast() && $grievance->is_unattended ? 'text-danger fw-bold' : '' }}">
                                        {{ $grievance->due_response_at?->format('d M Y H:i') ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>{{ $grievance->submitted_at?->format('d M Y H:i') ?? $grievance->created_at?->format('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('grm.logs.show', $grievance) }}" class="btn btn-sm btn-outline-success">
                                        <i class="feather-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No grievance records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $grievances->links() }}
            </div>
        </div>
    </div>
@endsection
