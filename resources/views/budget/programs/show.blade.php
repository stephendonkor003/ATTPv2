@extends('layouts.app')
@section('title', 'Program Details')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <!-- Header -->
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Program Details</h4>
                    <p class="text-muted mb-0">Detailed information about this program and its projects.</p>
                </div>
                <a href="{{ route('budget.programs.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back to Programs
                </a>
            </div>

            <!-- Program Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">{{ $program->name }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <p class="fw-semibold mb-1 text-muted">Program ID</p>
                            <p>{{ $program->program_id }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="fw-semibold mb-1 text-muted">Sector</p>
                            <p>{{ $program->sector->name ?? '—' }}</p>
                        </div>
                        <div class="col-md-12">
                            <p class="fw-semibold mb-1 text-muted">Description</p>
                            <p>{{ $program->description ?? 'No description provided.' }}</p>
                        </div>
                        <div class="col-md-12">
                            <p class="fw-semibold mb-1 text-muted">Expected Outcome</p>
                            <p>
                                @if ($program->expected_outcome_type === 'percentage')
                                    {{ $program->expected_outcome_value }}%
                                @elseif ($program->expected_outcome_type === 'text')
                                    {{ $program->expected_outcome_value }}
                                @else
                                    Not set.
                                @endif
                            </p>
                        </div>
                        <div class="col-md-12">
                            <p class="fw-semibold mb-1 text-muted">Created At</p>
                            <p> {{ $program->created_at ? $program->created_at->format('d M, Y') : 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Indicators Section -->
            @if ($program->indicators && $program->indicators->count() > 0)
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bi bi-bullseye me-2"></i> Indicators
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach ($program->indicators as $indicator)
                                @php
                                    $resp = [];
                                    if ($indicator->responsible_party) {
                                        $resp = json_decode($indicator->responsible_party, true) ?? [];
                                        if (!is_array($resp)) { $resp = [$indicator->responsible_party]; }
                                    }
                                    $psType = 'manual'; $psDetail = '';
                                    if ($indicator->primary_source && str_contains($indicator->primary_source, ':')) {
                                        [$psType, $psDetail] = explode(':', $indicator->primary_source, 2);
                                    } elseif ($indicator->primary_source) {
                                        $psDetail = $indicator->primary_source;
                                    }
                                @endphp
                                <div class="col-md-6">
                                    <div class="p-3 border rounded indicator-card" style="--stripe:#2563eb;">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="indicator-chip">{{ $indicator->name }}</span>
                                            @if ($indicator->level)
                                                <span class="badge bg-light text-primary border">{{ $indicator->level->name }}</span>
                                            @endif
                                        </div>
                                        <ul class="list-unstyled small text-muted mb-2">
                                            @if ($indicator->baseline_year)
                                                <li>Baseline: {{ $indicator->baseline_year }} ({{ $indicator->baseline_type ?? 'year' }})</li>
                                            @endif
                                            @if ($indicator->baseline_value !== null)
                                                <li>Baseline Value: {{ rtrim(rtrim(number_format($indicator->baseline_value, 2), '0'), '.') }}
                                                    @if ($indicator->unit)
                                                        {{ $indicator->unit->symbol ?? $indicator->unit->name }}
                                                    @endif
                                                </li>
                                            @endif
                                            @if ($indicator->frequency)
                                                <li>Reporting: {{ $indicator->frequency->name }}</li>
                                            @endif
                                            @if (!empty($resp))
                                                <li>Responsible:
                                                    @foreach ($resp as $id)
                                                        <span class="badge bg-primary-subtle text-primary border">{{ $id }}</span>
                                                    @endforeach
                                                </li>
                                            @endif
                                            @if ($psDetail)
                                                <li>Source: {{ ucfirst($psType) }} — {{ $psDetail }}</li>
                                            @endif
                                            @if ($indicator->definitions)
                                                <li>Definition: {{ Str::limit($indicator->definitions, 120) }}</li>
                                            @endif
                                        </ul>
                                        <small class="text-muted">Created:
                                            {{ $indicator->created_at ? $indicator->created_at->format('d M, Y') : 'N/A' }}</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Projects under this program -->
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Projects under {{ $program->name }}</h5>
                    <a href="{{ route('budget.projects.create', $program->id) }}" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle me-1"></i> Add Project
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Project ID</th>
                                    <th>Project Name</th>
                                    <th>Budget (GHS)</th>
                                    <th>Duration (Years)</th>
                                    <th>Created At</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($program->projects as $index => $project)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td class="fw-semibold text-primary">{{ $project->project_id }}</td>
                                        <td>{{ $project->name }}</td>
                                        <td>{{ number_format($project->total_budget, 2) }}</td>
                                        <td>{{ $project->duration_years }}</td>
                                        <td>{{ $program->created_at ? $program->created_at->format('d M, Y') : 'N/A' }}
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('budget.projects.show', $project->id) }}"
                                                class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('budget.projects.edit', $project->id) }}"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-info-circle me-1"></i> No projects found for this program.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>
@endsection
