@extends('layouts.app')

@section('title', 'Activities')

@push('styles')
    <style>
        .activity-flow {
            color: #0f172a;
        }

        .activity-flow-hero {
            border-radius: 8px;
            padding: 18px 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 58%, #522b39 100%);
            box-shadow: 0 14px 28px rgba(6, 63, 54, 0.14);
        }

        .activity-flow-hero h4,
        .activity-flow-hero p {
            color: #ffffff;
        }

        .activity-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .activity-chip {
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

        .activity-summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
        }

        .activity-summary-card,
        .activity-search-card,
        .activity-program-node {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .activity-summary-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            min-height: 72px;
        }

        .activity-summary-icon {
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

        .activity-summary-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .activity-summary-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .activity-summary-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .activity-summary-icon.slate {
            color: #334155;
            background: #e2e8f0;
        }

        .activity-summary-label {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .activity-summary-value {
            color: #0f172a;
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .activity-search-card {
            padding: 14px;
        }

        .activity-program-node {
            overflow: hidden;
            border-color: #0f766e;
            background: #ecfdf5;
        }

        .activity-node-toggle {
            width: 100%;
            border: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 15px 16px;
            text-align: left;
            background: #ffffff;
        }

        .activity-node-toggle:hover {
            background: #f8fafc;
        }

        .activity-node-toggle .activity-chevron {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #047857;
            background: #ecfdf5;
            transform: rotate(-90deg);
            transition: transform 0.16s ease;
        }

        .activity-program-node > .activity-node-toggle {
            background: #064e3b;
        }

        .activity-program-node > .activity-node-toggle:hover {
            background: #065f46;
        }

        .activity-program-node > .activity-node-toggle .activity-kicker,
        .activity-program-node > .activity-node-toggle .activity-node-title,
        .activity-program-node > .activity-node-toggle .activity-node-subtitle {
            color: #ffffff !important;
        }

        .activity-program-node > .activity-node-toggle .activity-node-subtitle {
            color: #ccfbf1 !important;
        }

        .activity-program-node > .activity-node-toggle .activity-node-metrics span {
            color: #ecfdf5;
            background: rgba(255, 255, 255, 0.14);
        }

        .activity-program-node > .activity-node-toggle .activity-chevron {
            color: #064e3b;
            background: #d1fae5;
        }

        .activity-node-toggle:not(.collapsed) .activity-chevron {
            transform: rotate(0deg);
        }

        .activity-node-title {
            color: #0f172a;
            font-weight: 800;
        }

        .activity-node-subtitle {
            color: #64748b;
            font-size: 0.84rem;
            margin-top: 2px;
        }

        .activity-node-metrics {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 6px;
        }

        .activity-node-metrics span {
            border-radius: 999px;
            padding: 0.22rem 0.55rem;
            color: #475569;
            background: #f1f5f9;
            font-size: 0.74rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .activity-program-body {
            border-top: 1px solid #0f766e;
            padding: 12px;
            background: #d1fae5;
        }

        .activity-project-node {
            border: 1px solid #7dd3fc;
            border-radius: 8px;
            overflow: hidden;
            background: #f0f9ff;
        }

        .activity-project-node + .activity-project-node {
            margin-top: 10px;
        }

        .activity-project-toggle {
            background: #f0f9ff;
        }

        .activity-project-toggle:hover {
            background: #e0f2fe;
        }

        .activity-project-toggle .activity-kicker,
        .activity-project-toggle .activity-node-title {
            color: #075985 !important;
        }

        .activity-project-toggle .activity-node-subtitle {
            color: #475569;
        }

        .activity-project-toggle .activity-node-metrics span {
            color: #075985;
            background: #e0f2fe;
        }

        .activity-project-toggle .activity-chevron {
            color: #075985;
            background: #e0f2fe;
        }

        .activity-project-body {
            border-top: 1px solid #bae6fd;
            padding: 12px;
            background: #f8fafc;
        }

        .activity-card {
            border: 1px solid #fde68a;
            border-radius: 8px;
            background: #fffbeb;
            overflow: hidden;
        }

        .activity-card + .activity-card {
            margin-top: 8px;
        }

        .activity-card-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 12px;
            background: #fffbeb;
        }

        .activity-card-title {
            color: #78350f;
            font-weight: 800;
        }

        .activity-card .activity-kicker {
            color: #92400e !important;
        }

        .activity-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        .activity-card-meta span {
            border-radius: 999px;
            padding: 0.18rem 0.48rem;
            color: #92400e;
            background: #fef3c7;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .activity-card-actions {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .activity-icon-button {
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

        .activity-icon-button:hover {
            border-color: #1d4ed8;
            color: #ffffff;
            background: #1d4ed8;
        }

        .activity-icon-button.warning {
            border-color: #fde68a;
            color: #92400e;
            background: #fffbeb;
        }

        .activity-icon-button.warning:hover {
            border-color: #d97706;
            color: #ffffff;
            background: #d97706;
        }

        .activity-delete-button {
            min-height: 30px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.3rem 0.6rem;
            color: #b91c1c;
            background: #fef2f2;
            font-size: 0.78rem;
            font-weight: 800;
            line-height: 1;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .activity-delete-button:hover {
            border-color: #b91c1c;
            color: #ffffff;
            background: #b91c1c;
        }

        .activity-delete-warning {
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 12px;
            color: #7f1d1d;
            background: #fef2f2;
        }

        .activity-allocation-panel {
            border-top: 1px solid #ead1da;
            padding: 12px;
            background: #fbf1f5;
        }

        .activity-allocation-panel .table-light th {
            color: #522b39;
            background: #f8e8ef;
        }

        .activity-allocation-table {
            margin-bottom: 0;
        }

        .activity-allocation-table td,
        .activity-allocation-table th {
            vertical-align: middle;
        }

        .activity-empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            color: #64748b;
            background: #ffffff;
        }

        @media (max-width: 1199.98px) {
            .activity-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .activity-node-metrics {
                justify-content: flex-start;
            }
        }

        @media (max-width: 767.98px) {
            .activity-summary-grid {
                grid-template-columns: 1fr;
            }

            .activity-node-toggle,
            .activity-card-header {
                grid-template-columns: 1fr;
            }

            .activity-node-toggle {
                align-items: flex-start;
                flex-direction: column;
            }

            .activity-card-actions {
                justify-content: flex-start;
            }
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container activity-flow">
        <div class="activity-flow-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="activity-kicker mb-2">ATTP Activity Management</div>
                    <h4 class="fw-bold mb-2">Nested Activities Flow</h4>
                    <p class="mb-0">
                        Follow the structure from Program to Project to Activity, then open allocation years under each activity.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="activity-chip"><i class="feather-layers"></i> Program</span>
                        <span class="activity-chip"><i class="feather-folder"></i> Project</span>
                        <span class="activity-chip"><i class="feather-git-branch"></i> Activity</span>
                        <span class="activity-chip"><i class="feather-dollar-sign"></i> Allocations</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    <a href="{{ route('budget.projects.index') }}" class="btn btn-light">
                        <i class="feather-folder me-1"></i> Projects
                    </a>
                    <a href="{{ route('budget.programs.index') }}" class="btn btn-success">
                        <i class="feather-layers me-1"></i> Programs
                    </a>
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

        <div class="activity-summary-grid mt-3">
            <div class="activity-summary-card">
                <span class="activity-summary-icon amber"><i class="feather-layers"></i></span>
                <div>
                    <div class="activity-summary-label">Programs</div>
                    <div class="activity-summary-value">{{ number_format($activityStats['programs']) }}</div>
                </div>
            </div>
            <div class="activity-summary-card">
                <span class="activity-summary-icon blue"><i class="feather-folder"></i></span>
                <div>
                    <div class="activity-summary-label">Projects</div>
                    <div class="activity-summary-value">{{ number_format($activityStats['projects']) }}</div>
                </div>
            </div>
            <div class="activity-summary-card">
                <span class="activity-summary-icon"><i class="feather-git-branch"></i></span>
                <div>
                    <div class="activity-summary-label">Activities</div>
                    <div class="activity-summary-value">{{ number_format($activityStats['activities']) }}</div>
                </div>
            </div>
            <div class="activity-summary-card">
                <span class="activity-summary-icon slate"><i class="feather-list"></i></span>
                <div>
                    <div class="activity-summary-label">Sub-Activities</div>
                    <div class="activity-summary-value">{{ number_format($activityStats['sub_activities']) }}</div>
                </div>
            </div>
            <div class="activity-summary-card">
                <span class="activity-summary-icon wine"><i class="feather-dollar-sign"></i></span>
                <div>
                    <div class="activity-summary-label">Allocated</div>
                    <div class="activity-summary-value" style="font-size: 1.02rem;">{{ number_format((float) $activityStats['allocation_total'], 2) }}</div>
                </div>
            </div>
        </div>

        <div class="activity-search-card mt-3">
            <form method="GET" action="{{ route('budget.activities.index') }}" class="d-flex flex-column flex-lg-row gap-2">
                <input type="text" name="search" class="form-control"
                    placeholder="Search program, project, or activity..." value="{{ $search }}">
                <button class="btn btn-success">
                    <i class="feather-search me-1"></i> Search
                </button>
                @if ($search)
                    <a href="{{ route('budget.activities.index') }}" class="btn btn-light border">
                        <i class="feather-x me-1"></i> Clear
                    </a>
                @endif
            </form>
        </div>

        <div class="activity-flow-tree mt-3" id="activityFlowTree">
            @forelse ($programs as $program)
                @php
                    $programProjects = $program->projects;
                    $programActivities = $programProjects->flatMap->activities;
                    $programAllocation = $programActivities->sum(fn ($activity) => (float) $activity->allocation_total);
                    $programCollapseId = 'programFlow' . $program->id;
                @endphp

                <div class="activity-program-node mb-3">
                    <button class="activity-node-toggle collapsed" type="button"
                        data-flow-target="{{ $programCollapseId }}" aria-expanded="false"
                        aria-controls="{{ $programCollapseId }}">
                        <div class="d-flex align-items-start gap-3">
                            <span class="activity-chevron"><i class="feather-chevron-down"></i></span>
                            <div>
                                <div class="activity-kicker text-success mb-1">Program</div>
                                <div class="activity-node-title">{{ $program->name }}</div>
                                <div class="activity-node-subtitle">{{ $program->sector->name ?? 'No portfolio assigned' }}</div>
                            </div>
                        </div>
                        <div class="activity-node-metrics">
                            <span>{{ number_format($programProjects->count()) }} projects</span>
                            <span>{{ number_format($programActivities->count()) }} activities</span>
                            <span>{{ number_format($programAllocation, 2) }} allocated</span>
                        </div>
                    </button>

                    <div id="{{ $programCollapseId }}" class="collapse activity-program-body">
                        @forelse ($programProjects as $project)
                            @php
                                $projectActivities = $project->activities;
                                $projectAllocation = $projectActivities->sum(fn ($activity) => (float) $activity->allocation_total);
                                $projectCollapseId = 'projectFlow' . $project->id;
                            @endphp

                            <div class="activity-project-node">
                                <button class="activity-node-toggle activity-project-toggle collapsed" type="button"
                                    data-flow-target="{{ $projectCollapseId }}" aria-expanded="false"
                                    aria-controls="{{ $projectCollapseId }}">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="activity-chevron"><i class="feather-chevron-down"></i></span>
                                        <div>
                                            <div class="activity-kicker text-success mb-1">Project</div>
                                            <div class="activity-node-title">{{ $project->project_id }} - {{ $project->name }}</div>
                                            <div class="activity-node-subtitle">
                                                {{ $project->start_year ?? 'N/A' }} to {{ $project->end_year ?? 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="activity-node-metrics">
                                        <span>{{ number_format($projectActivities->count()) }} activities</span>
                                        <span>{{ number_format((float) ($project->total_budget ?? 0), 2) }} {{ $project->currency ?? $program->currency ?? 'USD' }}</span>
                                        <span>{{ number_format($projectAllocation, 2) }} allocated</span>
                                    </div>
                                </button>

                                <div id="{{ $projectCollapseId }}" class="collapse activity-project-body">
                                    @forelse ($projectActivities as $activity)
                                        @php
                                            $activityCollapseId = 'activityFlow' . $activity->id;
                                            $currency = $project->currency ?: ($program->currency ?? 'USD');
                                        @endphp

                                        <div class="activity-card">
                                            <div class="activity-card-header">
                                                <div>
                                                    <div class="activity-kicker text-success mb-1">Activity</div>
                                                    <div class="activity-card-title">{{ $activity->name }}</div>
                                                    <div class="activity-node-subtitle">
                                                        {{ $activity->description ? Str::limit($activity->description, 120) : 'No description has been added.' }}
                                                    </div>
                                                    <div class="activity-card-meta">
                                                        <span>{{ number_format((float) $activity->allocation_total, 2) }} {{ $currency }}</span>
                                                        <span>{{ number_format($activity->sub_activities_count) }} sub-activities</span>
                                                        <span>{{ number_format($activity->allocations->count()) }} allocation years</span>
                                                    </div>
                                                </div>

                                                <div class="activity-card-actions">
                                                    <button type="button" class="activity-icon-button"
                                                        data-flow-target="{{ $activityCollapseId }}"
                                                        aria-expanded="false"
                                                        aria-controls="{{ $activityCollapseId }}"
                                                        title="View Allocations">
                                                        <i class="feather-list"></i>
                                                    </button>
                                                    @can('activities.view')
                                                        <a href="{{ route('budget.activities.show', $activity->id) }}"
                                                            class="activity-icon-button" title="View Activity">
                                                            <i class="feather-eye"></i>
                                                        </a>
                                                    @endcan
                                                    @can('activities.edit')
                                                        <a href="{{ route('budget.activities.edit', $activity->id) }}"
                                                            class="activity-icon-button warning" title="Edit Allocations">
                                                            <i class="feather-edit"></i>
                                                        </a>
                                                    @endcan
                                                    @can('activities.delete')
                                                        <button type="button" class="activity-delete-button"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteActivityModal"
                                                            data-delete-url="{{ route('budget.activities.destroy', $activity->id) }}"
                                                            data-activity-id="{{ $activity->id }}"
                                                            data-activity-name="{{ $activity->name }}"
                                                            data-sub-activity-count="{{ $activity->sub_activities_count }}"
                                                            aria-label="Delete {{ $activity->name }}">
                                                            <i class="feather-trash-2"></i>
                                                            <span>Delete</span>
                                                        </button>
                                                    @endcan
                                                </div>
                                            </div>

                                            <div id="{{ $activityCollapseId }}" class="collapse activity-allocation-panel">
                                                @if ($activity->allocations->count())
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered activity-allocation-table">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Year</th>
                                                                    <th>Amount</th>
                                                                    <th>Currency</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($activity->allocations as $allocation)
                                                                    <tr>
                                                                        <td class="fw-semibold">{{ $allocation->year }}</td>
                                                                        <td>{{ number_format((float) $allocation->amount, 2) }}</td>
                                                                        <td>{{ $currency }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <div class="activity-empty-state">No yearly allocations have been entered for this activity.</div>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="activity-empty-state">No activities have been created under this project.</div>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <div class="activity-empty-state">No projects have been created under this program.</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="activity-empty-state text-center">
                    No programs, projects, or activities are available.
                </div>
            @endforelse
        </div>
    </div>

    @can('activities.delete')
        <div class="modal fade" id="deleteActivityModal" tabindex="-1"
            aria-labelledby="deleteActivityModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form id="deleteActivityForm" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="confirmed_activity_id" id="confirmedActivityId">

                        <div class="modal-header">
                            <h5 class="modal-title text-danger" id="deleteActivityModalLabel">
                                <i class="feather-alert-triangle me-1"></i>
                                Confirm activity deletion
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">
                                Are you sure you want to delete
                                <strong id="deleteActivityName">this activity</strong>?
                            </p>
                            <div class="activity-delete-warning">
                                <div class="fw-bold mb-1" id="deleteActivityImpact"></div>
                                <div class="small">
                                    The activity, its yearly allocations, and every associated sub-activity
                                    allocation will be permanently removed. This action cannot be undone.
                                    Deletion is blocked when commitments or purchase requests are linked.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-danger" id="confirmDeleteActivityButton">
                                <i class="feather-trash-2 me-1"></i>
                                Yes, delete activity
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const buttons = Array.from(document.querySelectorAll('[data-flow-target]'));

            function toggleNode(button) {
                const target = document.getElementById(button.dataset.flowTarget);
                if (!target) {
                    return;
                }

                const isOpen = target.classList.contains('show');

                if (window.bootstrap?.Collapse) {
                    const instance = bootstrap.Collapse.getOrCreateInstance(target, { toggle: false });
                    isOpen ? instance.hide() : instance.show();
                } else {
                    target.classList.toggle('show', !isOpen);
                }

                button.classList.toggle('collapsed', isOpen);
                button.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            }

            buttons.forEach(button => {
                button.addEventListener('click', () => toggleNode(button));
            });

            const deleteModal = document.getElementById('deleteActivityModal');
            const deleteForm = document.getElementById('deleteActivityForm');
            const confirmedActivityId = document.getElementById('confirmedActivityId');
            const deleteActivityName = document.getElementById('deleteActivityName');
            const deleteActivityImpact = document.getElementById('deleteActivityImpact');
            const confirmDeleteButton = document.getElementById('confirmDeleteActivityButton');

            deleteModal?.addEventListener('show.bs.modal', event => {
                const trigger = event.relatedTarget;

                if (!trigger || !deleteForm || !confirmedActivityId) {
                    event.preventDefault();
                    return;
                }

                const activityName = trigger.dataset.activityName || 'this activity';
                const subActivityCount = Number(trigger.dataset.subActivityCount || 0);

                deleteForm.action = trigger.dataset.deleteUrl || '';
                confirmedActivityId.value = trigger.dataset.activityId || '';
                deleteActivityName.textContent = `"${activityName}"`;
                deleteActivityImpact.textContent = subActivityCount === 0
                    ? 'This activity has no associated sub-activities.'
                    : (subActivityCount === 1
                        ? '1 associated sub-activity will also be deleted.'
                        : `${subActivityCount} associated sub-activities will also be deleted.`);

                if (confirmDeleteButton) {
                    confirmDeleteButton.disabled = false;
                    confirmDeleteButton.innerHTML = '<i class="feather-trash-2 me-1"></i> Yes, delete activity';
                }
            });

            deleteForm?.addEventListener('submit', event => {
                if (!deleteForm.action || !confirmedActivityId?.value) {
                    event.preventDefault();
                    return;
                }

                if (confirmDeleteButton) {
                    confirmDeleteButton.disabled = true;
                    confirmDeleteButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Deleting...';
                }
            });
        });
    </script>
@endpush
