@extends('layouts.app')

@section('title', 'Projects')

@push('styles')
    <style>
        .project-register {
            color: #0f172a;
        }

        .project-register-header {
            border-radius: 8px;
            padding: 18px 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 60%, #522b39 100%);
            box-shadow: 0 14px 28px rgba(6, 63, 54, 0.14);
        }

        .project-register-header h4,
        .project-register-header p {
            color: #ffffff;
        }

        .project-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .project-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            color: #effff9;
            background: rgba(255, 255, 255, 0.1);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .project-summary-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .project-summary-item,
        .project-register-card,
        .project-toolbar {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .project-summary-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            min-height: 72px;
        }

        .project-summary-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            color: #065f46;
            background: #d1fae5;
        }

        .project-summary-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .project-summary-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .project-summary-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .project-summary-label {
            color: #64748b;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .project-summary-value {
            color: #0f172a;
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .project-toolbar {
            padding: 14px;
        }

        .project-toolbar .form-select {
            min-width: 260px;
        }

        .project-register-card .table td {
            vertical-align: middle;
        }

        .project-name-cell {
            min-width: 260px;
        }

        .project-muted-line {
            color: #64748b;
            font-size: 0.82rem;
        }

        .project-code {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.2rem 0.55rem;
            color: #047857;
            background: #ecfdf5;
            font-size: 0.76rem;
            font-weight: 800;
        }

        .project-program-cell {
            min-width: 240px;
        }

        .project-plain-metric {
            color: #0f172a;
            font-weight: 800;
        }

        .project-compact-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .project-compact-meta span {
            border-radius: 999px;
            padding: 0.18rem 0.48rem;
            color: #475569;
            background: #f1f5f9;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .project-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            white-space: nowrap;
        }

        .project-icon-action {
            width: 30px;
            height: 30px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #1d4ed8;
            background: #eff6ff;
            font-size: 0.9rem;
            line-height: 1;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .project-icon-action:hover {
            border-color: #1d4ed8;
            color: #ffffff;
            background: #1d4ed8;
        }

        .project-icon-action.warning {
            border-color: #fde68a;
            color: #92400e;
            background: #fffbeb;
        }

        .project-icon-action.warning:hover {
            border-color: #d97706;
            color: #ffffff;
            background: #d97706;
        }

        .project-icon-action.danger {
            border-color: #fecaca;
            color: #b91c1c;
            background: #fef2f2;
        }

        .project-icon-action.danger:hover {
            border-color: #b91c1c;
            color: #ffffff;
            background: #b91c1c;
        }

        .project-empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            color: #64748b;
            background: #f8fafc;
        }

        @media (max-width: 1199.98px) {
            .project-summary-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .project-summary-strip {
                grid-template-columns: 1fr;
            }

            .project-toolbar .form-select {
                min-width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $allocationCoverage = $projectStats['total_budget'] > 0
            ? min(100, round(($projectStats['allocation_total'] / $projectStats['total_budget']) * 100))
            : 0;
        $programOptions = $projects
            ->map(fn ($project) => $project->program?->name)
            ->filter()
            ->unique()
            ->sort()
            ->values();
    @endphp

    <div class="nxl-container project-register">
        <div class="project-register-header">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="project-kicker mb-2">ATTP Project Register</div>
                    <h4 class="fw-bold mb-2">Projects By Program</h4>
                    <p class="mb-0">
                        Projects belong under programs. Filter by program first, then open a project for activities, sub-activities, indicators, and allocations.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="project-chip"><i class="feather-layers"></i> {{ number_format($projectStats['programs']) }} programs</span>
                        <span class="project-chip"><i class="feather-folder"></i> {{ number_format($projectStats['total']) }} projects</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    <a href="{{ route('budget.programs.index') }}" class="btn btn-light">
                        <i class="feather-layers me-1"></i> Programs
                    </a>
                    @can('project.create')
                        <a href="{{ route('budget.projects.create') }}" class="btn btn-success">
                            <i class="feather-plus-circle me-1"></i> New Project
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mt-3">
                <i class="feather-check-circle me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show mt-3">
                <i class="feather-alert-triangle me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="project-summary-strip mt-3">
            <div class="project-summary-item">
                <span class="project-summary-icon"><i class="feather-folder"></i></span>
                <div>
                    <div class="project-summary-label">Projects</div>
                    <div class="project-summary-value">{{ number_format($projectStats['total']) }}</div>
                </div>
            </div>
            <div class="project-summary-item">
                <span class="project-summary-icon blue"><i class="feather-layers"></i></span>
                <div>
                    <div class="project-summary-label">Programs</div>
                    <div class="project-summary-value">{{ number_format($projectStats['programs']) }}</div>
                </div>
            </div>
            <div class="project-summary-item">
                <span class="project-summary-icon amber"><i class="feather-dollar-sign"></i></span>
                <div>
                    <div class="project-summary-label">Project Budget</div>
                    <div class="project-summary-value" style="font-size: 1.05rem;">{{ number_format((float) $projectStats['total_budget'], 2) }}</div>
                </div>
            </div>
            <div class="project-summary-item">
                <span class="project-summary-icon wine"><i class="feather-pie-chart"></i></span>
                <div>
                    <div class="project-summary-label">Allocated</div>
                    <div class="project-summary-value">{{ $allocationCoverage }}%</div>
                </div>
            </div>
        </div>

        <div class="project-toolbar mt-3">
            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3">
                <div>
                    <label for="projectProgramFilter" class="form-label fw-semibold mb-1">Program Filter</label>
                    <select id="projectProgramFilter" class="form-select">
                        <option value="">All programs</option>
                        @foreach ($programOptions as $programName)
                            <option value="{{ $programName }}">{{ $programName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="text-xl-end">
                    <div class="fw-semibold text-dark">Program-first register</div>
                    <div class="project-muted-line">The table is sorted by program, then project name, to keep large project lists readable.</div>
                </div>
            </div>
        </div>

        <div class="card mt-3 shadow-sm border-0 project-register-card">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Project Register</h6>
                        <p class="text-muted small mb-0">A compact register of projects under their programs.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">{{ number_format($projects->count()) }} records</span>
                </div>

                @if ($projects->isEmpty())
                    <div class="project-empty-state text-center mt-3">
                        No projects have been added yet.
                    </div>
                @else
                    <x-data-table
                        id="projectsTable"
                        :config="[
                            'pageLength' => 25,
                            'order' => [[2, 'asc'], [1, 'asc']],
                            'columnDefs' => [
                                ['targets' => [0, 6], 'orderable' => false, 'searchable' => false],
                            ],
                        ]"
                    >
                        <thead class="table-light">
                            <tr>
                                <th width="50">#</th>
                                <th>Project</th>
                                <th>Program</th>
                                <th>Timeline</th>
                                <th>Budget</th>
                                <th>Delivery</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($projects as $project)
                                @php
                                    $programName = $project->program?->name ?? 'Unassigned';
                                    $currency = $project->currency ?: ($project->program?->currency ?? 'USD');
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="project-name-cell">
                                            <div class="fw-semibold text-dark">{{ $project->name }}</div>
                                            <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                                <span class="project-code">{{ $project->project_id }}</span>
                                                <span class="project-muted-line">{{ $project->description ? Str::limit($project->description, 15, '...') : 'No description.' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-search="{{ $programName }}">
                                        <div class="project-program-cell">
                                            <div class="fw-semibold text-dark">{{ $programName }}</div>
                                            <div class="project-muted-line">{{ $project->program?->sector?->name ?? 'No portfolio' }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="project-plain-metric">
                                            {{ $project->start_year ?? 'N/A' }} - {{ $project->end_year ?? 'N/A' }}
                                        </div>
                                        <div class="project-muted-line">
                                            {{ $project->duration_years_display ?? 'N/A' }} year{{ ($project->duration_years_display ?? 0) == 1 ? '' : 's' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="project-plain-metric">{{ number_format((float) ($project->total_budget ?? 0), 2) }}</div>
                                        <div class="project-muted-line">{{ $currency }}</div>
                                    </td>
                                    <td>
                                        <div class="project-compact-meta">
                                            <span>{{ number_format($project->activities_count) }} activities</span>
                                            <span>{{ number_format($project->sub_activities_count) }} sub</span>
                                            <span>{{ number_format($project->indicators->count()) }} indicators</span>
                                        </div>
                                        <div class="project-muted-line mt-1">
                                            Allocated: {{ number_format((float) $project->allocations_total, 2) }}
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="project-actions">
                                            @can('project.view')
                                                <a href="{{ route('budget.projects.show', $project->id) }}"
                                                    class="project-icon-action" title="View Details">
                                                    <i class="feather-eye"></i>
                                                </a>
                                            @endcan
                                            @can('project.edit')
                                                <a href="{{ route('budget.projects.edit', $project->id) }}"
                                                    class="project-icon-action warning" title="Edit Project">
                                                    <i class="feather-edit"></i>
                                                </a>
                                            @endcan
                                            @can('project.delete')
                                                <form action="{{ route('budget.projects.destroy', $project->id) }}" method="POST"
                                                    onsubmit="return confirm('Are you sure you want to delete this project?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="project-icon-action danger" title="Delete Project">
                                                        <i class="feather-trash-2"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-data-table>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const filter = document.getElementById('projectProgramFilter');
            if (!filter || !window.jQuery || !$.fn.DataTable) {
                return;
            }

            filter.addEventListener('change', () => {
                const table = $.fn.DataTable.isDataTable('#projectsTable')
                    ? $('#projectsTable').DataTable()
                    : null;

                if (!table) {
                    return;
                }

                table.column(2).search(filter.value).draw();
            });
        });
    </script>
@endpush
