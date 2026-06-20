@php
    $fundingPrograms = collect($vendorFundingPrograms ?? []);
    $assignmentRows = collect(old('assignments', $vendorFundingAssignments ?? []))
        ->map(fn ($row) => is_array($row) ? $row : [])
        ->values();

    if ($assignmentRows->isEmpty()) {
        $assignmentRows = collect([[]]);
    }

    $fundingHierarchy = $fundingPrograms->map(fn ($program) => [
        'id' => $program->id,
        'name' => $program->name ?: 'Untitled Program',
        'projects' => $program->projects->map(fn ($project) => [
            'id' => $project->id,
            'name' => $project->name ?: 'Untitled Project',
            'activities' => $project->activities->map(fn ($activity) => [
                'id' => $activity->id,
                'name' => $activity->name ?: 'Untitled Activity',
                'subActivities' => $activity->subActivities->map(fn ($subActivity) => [
                    'id' => $subActivity->id,
                    'name' => $subActivity->name ?: 'Untitled Funding Source',
                ])->values(),
            ])->values(),
        ])->values(),
    ])->values();
@endphp

@once
    @push('styles')
        <style>
            .vendor-funding-panel {
                border-top: 1px solid #e2e8f0;
                margin-top: 18px;
                padding-top: 18px;
            }

            .vendor-funding-row {
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                background: #f8fafc;
                padding: 14px;
            }

            .vendor-funding-row + .vendor-funding-row {
                margin-top: 12px;
            }

            .vendor-funding-remove {
                width: 36px;
                height: 36px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        </style>
    @endpush
@endonce

<div class="vendor-funding-panel" data-vendor-funding-manager data-next-index="{{ $assignmentRows->count() }}">
    <script type="application/json" data-vendor-funding-hierarchy>@json($fundingHierarchy)</script>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h6 class="fw-bold mb-1">Vendor Funding Sources</h6>
            <p class="text-muted mb-0 small">
                Link this vendor to the fund source that will appear to them as procurement.
            </p>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" data-vendor-funding-add>
            <i class="feather-plus me-1"></i> Add Source
        </button>
    </div>

    @if ($fundingPrograms->isEmpty())
        <div class="alert alert-warning mb-3">
            No program/project/activity funding sources were found. Create the budget hierarchy before assigning vendors.
        </div>
    @endif

    <div data-vendor-funding-rows>
        @foreach ($assignmentRows as $index => $assignment)
            <div class="vendor-funding-row" data-vendor-funding-row
                data-selected-program="{{ $assignment['program_id'] ?? '' }}"
                data-selected-project="{{ $assignment['project_id'] ?? '' }}"
                data-selected-activity="{{ $assignment['activity_id'] ?? '' }}"
                data-selected-sub-activity="{{ $assignment['sub_activity_id'] ?? '' }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Program</label>
                        <select name="assignments[{{ $index }}][program_id]" class="form-select"
                            data-vendor-funding-program>
                            <option value="">-- Select Program --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Project</label>
                        <select name="assignments[{{ $index }}][project_id]" class="form-select"
                            data-vendor-funding-project>
                            <option value="">-- Select Project --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Activity</label>
                        <select name="assignments[{{ $index }}][activity_id]" class="form-select"
                            data-vendor-funding-activity>
                            <option value="">-- Select Activity --</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Sub Activity</label>
                        <select name="assignments[{{ $index }}][sub_activity_id]" class="form-select"
                            data-vendor-funding-sub-activity>
                            <option value="">-- Select Sub Activity --</option>
                        </select>
                    </div>
                    <div class="col-md-1 text-md-end">
                        <button type="button" class="btn btn-outline-danger vendor-funding-remove"
                            data-vendor-funding-remove title="Remove source">
                            <i class="feather-trash-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-vendor-funding-manager]').forEach((manager) => {
                    const rowsContainer = manager.querySelector('[data-vendor-funding-rows]');
                    const addButton = manager.querySelector('[data-vendor-funding-add]');
                    const hierarchyScript = manager.querySelector('[data-vendor-funding-hierarchy]');
                    const hierarchy = JSON.parse(hierarchyScript?.textContent || '[]');

                    const findProgram = (id) => hierarchy.find((program) => program.id === id);
                    const findProject = (program, id) => (program?.projects || []).find((project) => project.id === id);
                    const findActivity = (project, id) => (project?.activities || []).find((activity) => activity.id === id);

                    const resetSelect = (select, label, disabled = false) => {
                        select.innerHTML = '';
                        select.appendChild(new Option(label, ''));
                        select.disabled = disabled;
                    };

                    const fillSelect = (select, label, items, selectedId) => {
                        resetSelect(select, label, items.length === 0);
                        items.forEach((item) => select.appendChild(new Option(item.name, item.id)));
                        if (selectedId && items.some((item) => item.id === selectedId)) {
                            select.value = selectedId;
                        }
                    };

                    const hydrateRow = (row) => {
                        const programSelect = row.querySelector('[data-vendor-funding-program]');
                        const projectSelect = row.querySelector('[data-vendor-funding-project]');
                        const activitySelect = row.querySelector('[data-vendor-funding-activity]');
                        const subActivitySelect = row.querySelector('[data-vendor-funding-sub-activity]');

                        const selectedProgramId = row.dataset.selectedProgram || '';
                        const selectedProjectId = row.dataset.selectedProject || '';
                        const selectedActivityId = row.dataset.selectedActivity || '';
                        const selectedSubActivityId = row.dataset.selectedSubActivity || '';

                        fillSelect(programSelect, '-- Select Program --', hierarchy, selectedProgramId);

                        const program = findProgram(programSelect.value);
                        fillSelect(projectSelect, '-- Select Project --', program?.projects || [], selectedProjectId);

                        const project = findProject(program, projectSelect.value);
                        fillSelect(activitySelect, '-- Select Activity --', project?.activities || [], selectedActivityId);

                        const activity = findActivity(project, activitySelect.value);
                        fillSelect(subActivitySelect, '-- Select Sub Activity --', activity?.subActivities || [], selectedSubActivityId);
                    };

                    const refreshRemoveButtons = () => {
                        const rows = rowsContainer.querySelectorAll('[data-vendor-funding-row]');
                        rows.forEach((row) => {
                            const button = row.querySelector('[data-vendor-funding-remove]');
                            if (button) {
                                button.disabled = rows.length === 1;
                            }
                        });
                    };

                    const createRow = () => {
                        const index = Number(manager.dataset.nextIndex || '0');
                        manager.dataset.nextIndex = String(index + 1);

                        const row = document.createElement('div');
                        row.className = 'vendor-funding-row';
                        row.dataset.vendorFundingRow = 'true';
                        row.innerHTML = `
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Program</label>
                                    <select name="assignments[${index}][program_id]" class="form-select" data-vendor-funding-program></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Project</label>
                                    <select name="assignments[${index}][project_id]" class="form-select" data-vendor-funding-project></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Activity</label>
                                    <select name="assignments[${index}][activity_id]" class="form-select" data-vendor-funding-activity></select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Sub Activity</label>
                                    <select name="assignments[${index}][sub_activity_id]" class="form-select" data-vendor-funding-sub-activity></select>
                                </div>
                                <div class="col-md-1 text-md-end">
                                    <button type="button" class="btn btn-outline-danger vendor-funding-remove" data-vendor-funding-remove title="Remove source">
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </div>
                            </div>`;

                        rowsContainer.appendChild(row);
                        hydrateRow(row);
                        refreshRemoveButtons();
                    };

                    manager.addEventListener('change', (event) => {
                        const row = event.target.closest('[data-vendor-funding-row]');
                        if (!row) {
                            return;
                        }

                        const programSelect = row.querySelector('[data-vendor-funding-program]');
                        const projectSelect = row.querySelector('[data-vendor-funding-project]');
                        const activitySelect = row.querySelector('[data-vendor-funding-activity]');
                        const subActivitySelect = row.querySelector('[data-vendor-funding-sub-activity]');

                        if (event.target.matches('[data-vendor-funding-program]')) {
                            const program = findProgram(programSelect.value);
                            fillSelect(projectSelect, '-- Select Project --', program?.projects || [], '');
                            resetSelect(activitySelect, '-- Select Activity --', true);
                            resetSelect(subActivitySelect, '-- Select Sub Activity --', true);
                        }

                        if (event.target.matches('[data-vendor-funding-project]')) {
                            const program = findProgram(programSelect.value);
                            const project = findProject(program, projectSelect.value);
                            fillSelect(activitySelect, '-- Select Activity --', project?.activities || [], '');
                            resetSelect(subActivitySelect, '-- Select Sub Activity --', true);
                        }

                        if (event.target.matches('[data-vendor-funding-activity]')) {
                            const program = findProgram(programSelect.value);
                            const project = findProject(program, projectSelect.value);
                            const activity = findActivity(project, activitySelect.value);
                            fillSelect(subActivitySelect, '-- Select Sub Activity --', activity?.subActivities || [], '');
                        }
                    });

                    manager.addEventListener('click', (event) => {
                        const removeButton = event.target.closest('[data-vendor-funding-remove]');
                        if (!removeButton) {
                            return;
                        }

                        const row = removeButton.closest('[data-vendor-funding-row]');
                        if (row && rowsContainer.querySelectorAll('[data-vendor-funding-row]').length > 1) {
                            row.remove();
                            refreshRemoveButtons();
                        }
                    });

                    addButton?.addEventListener('click', createRow);
                    rowsContainer.querySelectorAll('[data-vendor-funding-row]').forEach(hydrateRow);
                    refreshRemoveButtons();
                });
            });
        </script>
    @endpush
@endonce
