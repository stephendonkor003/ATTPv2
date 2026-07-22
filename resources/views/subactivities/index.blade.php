@extends('layouts.app')

@section('title', 'Sub-Activities')

@push('styles')
    <style>
        .sub-flow {
            color: #0f172a;
        }

        .sub-flow-hero {
            border-radius: 8px;
            padding: 18px 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 58%, #522b39 100%);
            box-shadow: 0 14px 28px rgba(6, 63, 54, 0.14);
        }

        .sub-flow-hero h4,
        .sub-flow-hero p {
            color: #ffffff;
        }

        .sub-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .sub-chip {
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

        .sub-summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
        }

        .sub-summary-card,
        .sub-search-card,
        .sub-program-node {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .sub-summary-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            min-height: 72px;
        }

        .sub-summary-icon {
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

        .sub-summary-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .sub-summary-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .sub-summary-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .sub-summary-icon.slate {
            color: #334155;
            background: #e2e8f0;
        }

        .sub-summary-label {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .sub-summary-value {
            color: #0f172a;
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .sub-search-card {
            padding: 14px;
        }

        .sub-program-node {
            overflow: hidden;
            border-color: #0f766e;
            background: #ecfdf5;
        }

        .sub-node-toggle {
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

        .sub-node-toggle:hover {
            background: #f8fafc;
        }

        .sub-chevron {
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

        .sub-node-toggle:not(.collapsed) .sub-chevron,
        .sub-icon-button:not(.collapsed) .sub-chevron-inline {
            transform: rotate(0deg);
        }

        .sub-program-node > .sub-node-toggle {
            background: #064e3b;
        }

        .sub-program-node > .sub-node-toggle:hover {
            background: #065f46;
        }

        .sub-program-node > .sub-node-toggle .sub-kicker,
        .sub-program-node > .sub-node-toggle .sub-node-title,
        .sub-program-node > .sub-node-toggle .sub-node-subtitle {
            color: #ffffff !important;
        }

        .sub-program-node > .sub-node-toggle .sub-node-subtitle {
            color: #ccfbf1 !important;
        }

        .sub-program-node > .sub-node-toggle .sub-node-metrics span {
            color: #ecfdf5;
            background: rgba(255, 255, 255, 0.14);
        }

        .sub-program-node > .sub-node-toggle .sub-chevron {
            color: #064e3b;
            background: #d1fae5;
        }

        .sub-node-title {
            color: #0f172a;
            font-weight: 800;
        }

        .sub-node-subtitle {
            color: #64748b;
            font-size: 0.84rem;
            margin-top: 2px;
        }

        .sub-node-metrics {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 6px;
        }

        .sub-node-metrics span {
            border-radius: 999px;
            padding: 0.22rem 0.55rem;
            color: #475569;
            background: #f1f5f9;
            font-size: 0.74rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .sub-program-body {
            border-top: 1px solid #0f766e;
            padding: 12px;
            background: #d1fae5;
        }

        .sub-project-node {
            border: 1px solid #7dd3fc;
            border-radius: 8px;
            overflow: hidden;
            background: #f0f9ff;
        }

        .sub-project-node + .sub-project-node {
            margin-top: 10px;
        }

        .sub-project-toggle {
            background: #f0f9ff;
        }

        .sub-project-toggle:hover {
            background: #e0f2fe;
        }

        .sub-project-toggle .sub-kicker,
        .sub-project-toggle .sub-node-title {
            color: #075985 !important;
        }

        .sub-project-toggle .sub-node-metrics span {
            color: #075985;
            background: #e0f2fe;
        }

        .sub-project-toggle .sub-chevron {
            color: #075985;
            background: #e0f2fe;
        }

        .sub-project-body {
            border-top: 1px solid #bae6fd;
            padding: 12px;
            background: #f8fafc;
        }

        .sub-activity-node {
            border: 1px solid #fde68a;
            border-radius: 8px;
            overflow: hidden;
            background: #fffbeb;
        }

        .sub-activity-node + .sub-activity-node {
            margin-top: 10px;
        }

        .sub-activity-toggle {
            background: #fffbeb;
        }

        .sub-activity-toggle:hover {
            background: #fef3c7;
        }

        .sub-activity-toggle .sub-kicker,
        .sub-activity-toggle .sub-node-title {
            color: #78350f !important;
        }

        .sub-activity-toggle .sub-node-metrics span {
            color: #92400e;
            background: #fef3c7;
        }

        .sub-activity-toggle .sub-chevron {
            color: #92400e;
            background: #fef3c7;
        }

        .sub-activity-body {
            border-top: 1px solid #fde68a;
            padding: 12px;
            background: #fff7ed;
        }

        .sub-card {
            border: 1px solid #ead1da;
            border-radius: 8px;
            overflow: hidden;
            background: #fbf1f5;
        }

        .sub-card + .sub-card {
            margin-top: 8px;
        }

        .sub-card-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 12px;
            background: #fbf1f5;
        }

        .sub-card-title {
            color: #522b39;
            font-weight: 800;
        }

        .sub-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        .sub-card-meta span {
            border-radius: 999px;
            padding: 0.18rem 0.48rem;
            color: #522b39;
            background: #f8e8ef;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .sub-card-actions {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .sub-icon-button {
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

        .sub-icon-button:hover {
            border-color: #1d4ed8;
            color: #ffffff;
            background: #1d4ed8;
        }

        .sub-icon-button.warning {
            border-color: #fde68a;
            color: #92400e;
            background: #fffbeb;
        }

        .sub-icon-button.warning:hover {
            border-color: #d97706;
            color: #ffffff;
            background: #d97706;
        }

        .sub-icon-button.danger {
            border-color: #fecaca;
            color: #b91c1c;
            background: #fef2f2;
        }

        .sub-icon-button.danger:hover {
            border-color: #b91c1c;
            color: #ffffff;
            background: #b91c1c;
        }

        .sub-chevron-inline {
            transform: rotate(-90deg);
            transition: transform 0.16s ease;
        }

        .sub-allocation-panel {
            border-top: 1px solid #ead1da;
            padding: 12px;
            background: #ffffff;
        }

        .sub-allocation-panel .table-light th {
            color: #522b39;
            background: #f8e8ef;
        }

        .sub-empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            color: #64748b;
            background: #ffffff;
        }

        .reallocation-alert {
            border: 1px solid #f59e0b;
            border-radius: 8px;
            overflow: hidden;
            background: #fffbeb;
            box-shadow: 0 10px 24px rgba(146, 64, 14, 0.08);
        }

        .reallocation-alert-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 16px;
            color: #78350f;
            background: #fef3c7;
        }

        .reallocation-issue-list {
            display: grid;
            gap: 10px;
            padding: 12px;
        }

        .reallocation-issue {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px;
            background: #ffffff;
        }

        .reallocation-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 999px;
            padding: 0.22rem 0.55rem;
            color: #991b1b;
            background: #fee2e2;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .reallocation-route {
            color: #475569;
            font-size: 0.82rem;
        }

        @media (max-width: 1199.98px) {
            .sub-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sub-node-metrics {
                justify-content: flex-start;
            }
        }

        @media (max-width: 767.98px) {
            .sub-summary-grid {
                grid-template-columns: 1fr;
            }

            .sub-node-toggle,
            .sub-card-header {
                grid-template-columns: 1fr;
                align-items: flex-start;
                flex-direction: column;
            }

            .sub-card-actions {
                justify-content: flex-start;
            }

            .reallocation-issue {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container sub-flow">
        <div class="sub-flow-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="sub-kicker mb-2">ATTP Sub-Activity Management</div>
                    <h4 class="fw-bold mb-2">Nested Sub-Activities Flow</h4>
                    <p class="mb-0">
                        Follow the complete structure from Program to Project to Activity, then review each sub-activity and its yearly allocations.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="sub-chip"><i class="feather-layers"></i> Program</span>
                        <span class="sub-chip"><i class="feather-folder"></i> Project</span>
                        <span class="sub-chip"><i class="feather-git-branch"></i> Activity</span>
                        <span class="sub-chip"><i class="feather-list"></i> Sub-Activity</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    <a href="{{ route('budget.activities.index') }}" class="btn btn-light">
                        <i class="feather-git-branch me-1"></i> Activities
                    </a>
                    <a href="{{ route('budget.projects.index') }}" class="btn btn-success">
                        <i class="feather-folder me-1"></i> Projects
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

        @if ($reallocationIssues->isNotEmpty())
            <section class="reallocation-alert mt-3" aria-labelledby="reallocationIssuesHeading">
                <div class="reallocation-alert-header">
                    <div class="d-flex gap-2">
                        <i class="feather-alert-triangle mt-1"></i>
                        <div>
                            <div class="fw-bold" id="reallocationIssuesHeading">
                                {{ $reallocationIssues->count() }} reallocation {{ Str::plural('issue', $reallocationIssues->count()) }}
                                {{ $reallocationIssues->count() === 1 ? 'needs' : 'need' }} attention
                            </div>
                            <div class="small mt-1">
                                These moves failed, were interrupted, or were detected with incomplete allocation links. Retry them here; no manual database correction is required.
                            </div>
                        </div>
                    </div>
                    <span class="badge bg-warning text-dark">Action required</span>
                </div>

                <div class="reallocation-issue-list">
                    @foreach ($reallocationIssues as $issue)
                        @php
                            $issueActivity = $issue['activity'];
                            $issueTarget = $issue['target_project'];
                            $issueSource = $issue['source_project'];
                            $issueCurrency = $issueTarget->currency ?: ($issueTarget->program?->currency ?? 'USD');
                            $attemptedWhen = $issue['last_attempted_at']
                                ? \Illuminate\Support\Carbon::parse($issue['last_attempted_at'])->diffForHumans()
                                : 'detected automatically';
                        @endphp
                        <article class="reallocation-issue" id="{{ $issue['key'] }}">
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="reallocation-status">
                                        <i class="feather-alert-circle"></i> {{ $issue['status'] }}
                                    </span>
                                    <strong>{{ $issueActivity->name }}</strong>
                                </div>
                                <div class="reallocation-route mb-1">
                                    @if ($issue['repair'])
                                        Current component: {{ $issueTarget->project_id }} - {{ $issueTarget->name }}
                                    @else
                                        {{ $issueSource?->project_id ?? 'Unknown' }} - {{ $issueSource?->name ?? 'Unknown source' }}
                                        <i class="feather-arrow-right mx-1"></i>
                                        {{ $issueTarget->project_id }} - {{ $issueTarget->name }}
                                    @endif
                                </div>
                                <div class="small text-danger mb-1">{{ $issue['reason'] }}</div>
                                <div class="small text-muted">
                                    {{ number_format((float) $issue['amount'], 2) }} {{ $issueCurrency }}
                                    <span class="mx-1">&bull;</span> {{ $attemptedWhen }}
                                    @if ($issue['attempt_count'])
                                        <span class="mx-1">&bull;</span> {{ $issue['attempt_count'] }} {{ Str::plural('attempt', $issue['attempt_count']) }}
                                    @endif
                                </div>
                            </div>

                            @can('activities.edit')
                                <form action="{{ route('budget.activities.reallocate', $issueActivity->id) }}" method="POST"
                                    onsubmit="return confirm('Retry this reallocation now? The activity, allocation, and sub-activities will be verified together.');">
                                    @csrf
                                    <input type="hidden" name="project_id" value="{{ $issueTarget->id }}">
                                    @if ($issue['attempt_id'])
                                        <input type="hidden" name="attempt_id" value="{{ $issue['attempt_id'] }}">
                                    @endif
                                    @if ($issue['repair'])
                                        <input type="hidden" name="repair" value="1">
                                    @endif
                                    <button type="submit" class="btn btn-warning btn-sm text-nowrap">
                                        <i class="feather-refresh-cw me-1"></i> Retry reallocation
                                    </button>
                                </form>
                            @endcan
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="sub-summary-grid mt-3">
            <div class="sub-summary-card">
                <span class="sub-summary-icon amber"><i class="feather-layers"></i></span>
                <div>
                    <div class="sub-summary-label">Programs</div>
                    <div class="sub-summary-value">{{ number_format($subActivityStats['programs']) }}</div>
                </div>
            </div>
            <div class="sub-summary-card">
                <span class="sub-summary-icon blue"><i class="feather-folder"></i></span>
                <div>
                    <div class="sub-summary-label">Projects</div>
                    <div class="sub-summary-value">{{ number_format($subActivityStats['projects']) }}</div>
                </div>
            </div>
            <div class="sub-summary-card">
                <span class="sub-summary-icon"><i class="feather-git-branch"></i></span>
                <div>
                    <div class="sub-summary-label">Activities</div>
                    <div class="sub-summary-value">{{ number_format($subActivityStats['activities']) }}</div>
                </div>
            </div>
            <div class="sub-summary-card">
                <span class="sub-summary-icon wine"><i class="feather-list"></i></span>
                <div>
                    <div class="sub-summary-label">Sub-Activities</div>
                    <div class="sub-summary-value">{{ number_format($subActivityStats['sub_activities']) }}</div>
                </div>
            </div>
            <div class="sub-summary-card">
                <span class="sub-summary-icon slate"><i class="feather-dollar-sign"></i></span>
                <div>
                    <div class="sub-summary-label">Allocated</div>
                    <div class="sub-summary-value" style="font-size: 1.02rem;">{{ number_format((float) $subActivityStats['allocation_total'], 2) }}</div>
                </div>
            </div>
        </div>

        <div class="sub-search-card mt-3">
            <form method="GET" action="{{ route('budget.subactivities.index') }}" class="d-flex flex-column flex-lg-row gap-2">
                <input type="text" name="search" class="form-control"
                    placeholder="Search program, project, activity, or sub-activity..." value="{{ $search }}">
                <button class="btn btn-success">
                    <i class="feather-search me-1"></i> Search
                </button>
                @if ($search)
                    <a href="{{ route('budget.subactivities.index') }}" class="btn btn-light border">
                        <i class="feather-x me-1"></i> Clear
                    </a>
                @endif
            </form>
        </div>

        <div class="sub-flow-tree mt-3">
            @forelse ($programs as $program)
                @php
                    $programProjects = $program->projects;
                    $programActivities = $programProjects->flatMap->activities;
                    $programSubActivities = $programActivities->flatMap->subActivities;
                    $programAllocation = $programSubActivities->sum('allocation_total');
                    $programCollapseId = 'programSubFlow' . $program->id;
                @endphp

                <div class="sub-program-node mb-3">
                    <button class="sub-node-toggle collapsed" type="button"
                        data-flow-target="{{ $programCollapseId }}" aria-expanded="false"
                        aria-controls="{{ $programCollapseId }}">
                        <div class="d-flex align-items-start gap-3">
                            <span class="sub-chevron"><i class="feather-chevron-down"></i></span>
                            <div>
                                <div class="sub-kicker mb-1">Program</div>
                                <div class="sub-node-title">{{ $program->name }}</div>
                                <div class="sub-node-subtitle">{{ $program->sector->name ?? 'No portfolio assigned' }}</div>
                            </div>
                        </div>
                        <div class="sub-node-metrics">
                            <span>{{ number_format($programProjects->count()) }} projects</span>
                            <span>{{ number_format($programActivities->count()) }} activities</span>
                            <span>{{ number_format($programSubActivities->count()) }} sub</span>
                            <span>{{ number_format((float) $programAllocation, 2) }} allocated</span>
                        </div>
                    </button>

                    <div id="{{ $programCollapseId }}" class="collapse sub-program-body">
                        @forelse ($programProjects as $project)
                            @php
                                $projectActivities = $project->activities;
                                $projectSubActivities = $projectActivities->flatMap->subActivities;
                                $projectAllocation = $projectSubActivities->sum('allocation_total');
                                $projectCollapseId = 'projectSubFlow' . $project->id;
                            @endphp

                            <div class="sub-project-node">
                                <button class="sub-node-toggle sub-project-toggle collapsed" type="button"
                                    data-flow-target="{{ $projectCollapseId }}" aria-expanded="false"
                                    aria-controls="{{ $projectCollapseId }}">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="sub-chevron"><i class="feather-chevron-down"></i></span>
                                        <div>
                                            <div class="sub-kicker mb-1">Project</div>
                                            <div class="sub-node-title">{{ $project->project_id }} - {{ $project->name }}</div>
                                            <div class="sub-node-subtitle">{{ $project->start_year ?? 'N/A' }} to {{ $project->end_year ?? 'N/A' }}</div>
                                        </div>
                                    </div>
                                    <div class="sub-node-metrics">
                                        <span>{{ number_format($projectActivities->count()) }} activities</span>
                                        <span>{{ number_format($projectSubActivities->count()) }} sub</span>
                                        <span>{{ number_format((float) $projectAllocation, 2) }} allocated</span>
                                    </div>
                                </button>

                                <div id="{{ $projectCollapseId }}" class="collapse sub-project-body">
                                    @forelse ($projectActivities as $activity)
                                        @php
                                            $activitySubActivities = $activity->subActivities;
                                            $activityAllocation = (float) $activity->allocation_total;
                                            $activitySubAllocation = (float) $activity->sub_activity_allocation_total;
                                            $activityUsagePercent = $activityAllocation > 0 ? min(100, round(($activitySubAllocation / $activityAllocation) * 100, 1)) : 0;
                                            $activityCollapseId = 'activitySubFlow' . $activity->id;
                                            $currency = $project->currency ?: ($program->currency ?? 'USD');
                                        @endphp

                                        <div class="sub-activity-node">
                                            <button class="sub-node-toggle sub-activity-toggle collapsed" type="button"
                                                data-flow-target="{{ $activityCollapseId }}" aria-expanded="false"
                                                aria-controls="{{ $activityCollapseId }}">
                                                <div class="d-flex align-items-start gap-3">
                                                    <span class="sub-chevron"><i class="feather-chevron-down"></i></span>
                                                    <div>
                                                        <div class="sub-kicker mb-1">Activity</div>
                                                        <div class="sub-node-title">{{ $activity->name }}</div>
                                                        <div class="sub-node-subtitle">
                                                            {{ $activity->description ? Str::limit($activity->description, 110) : 'No description has been added.' }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="sub-node-metrics">
                                                    <span>{{ number_format($activitySubActivities->count()) }} sub</span>
                                                    <span>{{ number_format($activitySubAllocation, 2) }} {{ $currency }}</span>
                                                    <span>{{ $activityUsagePercent }}% used</span>
                                                </div>
                                            </button>

                                            <div id="{{ $activityCollapseId }}" class="collapse sub-activity-body">
                                                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                                                    <div class="text-muted small">
                                                        Activity allocation: <strong>{{ number_format($activityAllocation, 2) }} {{ $currency }}</strong>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        @can('subactivities.create')
                                                            <a href="{{ route('budget.subactivities.create', $activity->id) }}" class="btn btn-success btn-sm">
                                                                <i class="feather-plus-circle me-1"></i> Add Sub-Activity
                                                            </a>
                                                        @endcan

                                                        @can('activities.edit')
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                                                data-bs-target="#reallocateModal"
                                                                data-activity-id="{{ $activity->id }}"
                                                                data-project-id="{{ $project->id }}"
                                                                data-activity-name="{{ $activity->name }}">
                                                                <i class="feather-move me-1"></i> Reallocate
                                                            </button>
                                                        @endcan
                                                    </div>
                                                </div>

                                                @forelse ($activitySubActivities as $subActivity)
                                                    @php
                                                        $subCollapseId = 'subAllocationFlow' . $subActivity->id;
                                                        $subTotal = (float) $subActivity->allocation_total;
                                                        $subUsagePercent = $activityAllocation > 0 ? min(100, round(($subTotal / $activityAllocation) * 100, 1)) : 0;
                                                        $outcome = match ($subActivity->expected_outcome_type) {
                                                            'percentage' => ($subActivity->expected_outcome_value !== null ? $subActivity->expected_outcome_value . '%' : 'Not set'),
                                                            'text' => $subActivity->expected_outcome_value ?: 'Not set',
                                                            default => 'Not set',
                                                        };
                                                    @endphp

                                                    <div class="sub-card">
                                                        <div class="sub-card-header">
                                                            <div>
                                                                <div class="sub-kicker mb-1" style="color: #522b39;">Sub-Activity</div>
                                                                <div class="sub-card-title">{{ $subActivity->name }}</div>
                                                                <div class="sub-node-subtitle">
                                                                    {{ $subActivity->description ? Str::limit($subActivity->description, 120) : 'No description has been added.' }}
                                                                </div>
                                                                <div class="sub-card-meta">
                                                                    <span>{{ number_format($subTotal, 2) }} {{ $currency }}</span>
                                                                    <span>{{ number_format($subActivity->allocations->count()) }} allocation years</span>
                                                                    <span>{{ $subUsagePercent }}% of activity</span>
                                                                </div>
                                                            </div>

                                                            <div class="sub-card-actions">
                                                                <button type="button" class="sub-icon-button collapsed"
                                                                    data-flow-target="{{ $subCollapseId }}"
                                                                    aria-expanded="false"
                                                                    aria-controls="{{ $subCollapseId }}"
                                                                    title="View Allocations">
                                                                    <i class="feather-chevron-down sub-chevron-inline"></i>
                                                                </button>
                                                                @canany(['subactivity.edit', 'subactivities.edit'])
                                                                    <a href="{{ route('budget.subactivities.edit', $subActivity->id) }}"
                                                                        class="sub-icon-button warning" title="Edit Sub-Activity">
                                                                        <i class="feather-edit"></i>
                                                                    </a>
                                                                    <a href="{{ route('budget.subactivities.allocations.edit', $subActivity->id) }}"
                                                                        class="sub-icon-button" title="Edit Allocations">
                                                                        <i class="feather-sliders"></i>
                                                                    </a>
                                                                @endcanany
                                                                @canany(['subactivity.delete', 'subactivities.delete'])
                                                                    <form action="{{ route('budget.subactivities.destroy', $subActivity->id) }}" method="POST"
                                                                        onsubmit="return confirm('Delete this sub-activity?')">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="sub-icon-button danger" title="Delete Sub-Activity">
                                                                            <i class="feather-trash-2"></i>
                                                                        </button>
                                                                    </form>
                                                                @endcanany
                                                            </div>
                                                        </div>

                                                        <div id="{{ $subCollapseId }}" class="collapse sub-allocation-panel">
                                                            <div class="mb-2">
                                                                <span class="fw-semibold">Expected Outcome:</span>
                                                                <span class="text-muted">{{ Str::limit($outcome, 160) }}</span>
                                                            </div>

                                                            @if ($subActivity->allocations->count())
                                                                <div class="table-responsive">
                                                                    <table class="table table-sm table-bordered mb-0">
                                                                        <thead class="table-light">
                                                                            <tr>
                                                                                <th>Year</th>
                                                                                <th>Amount</th>
                                                                                <th>Currency</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @foreach ($subActivity->allocations as $allocation)
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
                                                                <div class="sub-empty-state">No yearly allocations have been entered for this sub-activity.</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @empty
                                                    <div class="sub-empty-state">No sub-activities have been created under this activity.</div>
                                                @endforelse
                                            </div>
                                        </div>
                                    @empty
                                        <div class="sub-empty-state">No activities have been created under this project.</div>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <div class="sub-empty-state">No projects have been created under this program.</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="sub-empty-state text-center">
                    No programs, projects, activities, or sub-activities are available.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Kept inside the content section so no modal markup is emitted before the page layout. -->
    <div class="modal fade" id="reallocateModal" tabindex="-1" aria-labelledby="reallocateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="reallocateForm" method="POST" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="reallocateModalLabel">Reallocate Activity</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">
                            Select the target project. The activity, its allocation, and all sub-activities will move together.
                            Sub-activity amounts remain a breakdown of the activity allocation and are not added a second time.
                            The activity's budget envelope moves from the source component to the target component, while the programme-wide budget remains unchanged.
                        </p>

                        <div class="mb-3">
                            <label class="form-label">Target Project</label>
                            <select name="project_id" id="reallocateProject" class="form-select" required>
                                <option value="">Select project</option>
                                @foreach($programs as $p)
                                    <optgroup label="{{ $p->name }}">
                                        @foreach($p->projects as $proj)
                                            <option value="{{ $proj->id }}">{{ $proj->project_id }} - {{ $proj->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Reallocate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
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

            // Reallocate modal behavior
            const reallocateModalEl = document.getElementById('reallocateModal');
            if (reallocateModalEl) {
                reallocateModalEl.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const activityId = button?.getAttribute('data-activity-id');
                    const currentProjectId = button?.getAttribute('data-project-id');
                    const activityName = button?.getAttribute('data-activity-name') || 'Activity';
                    const form = document.getElementById('reallocateForm');
                    if (form && activityId) {
                        form.action = `/budget/activities/${activityId}/reallocate`;
                    }
                    const title = document.getElementById('reallocateModalLabel');
                    if (title) title.textContent = `Reallocate: ${activityName}`;

                    const projectSelect = document.getElementById('reallocateProject');
                    if (projectSelect) {
                        projectSelect.value = '';
                        Array.from(projectSelect.options).forEach(option => {
                            option.disabled = option.value !== '' && option.value === currentProjectId;
                        });
                    }
                });
            }
        });
    </script>
@endpush
