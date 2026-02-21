@extends('layouts.app')
@section('title', 'Create Project')

@section('content')
    @push('styles')
        <link rel="stylesheet" href="{{ asset('admin/assets/css/select2-custom.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('admin/assets/js/checkbox-multiselect.js') }}"></script>
    @endpush
    <style>
        .program-hero {
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 45%, #7c3aed 100%);
            color: #fff;
            border-radius: 18px;
            padding: 18px 22px;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.25);
        }

        .section-card {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }

        .indicator-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            position: relative;
        }

        .indicator-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 10px;
            bottom: 10px;
            width: 4px;
            border-radius: 20px;
            background: var(--stripe, #2563eb);
        }

        .indicator-chip {
            font-weight: 700;
            font-size: 13px;
            padding: 6px 10px;
            border-radius: 10px;
            color: #0f172a;
            background: #e0f2fe;
            border: 1px solid #bfdbfe;
        }
        /* Responsible users multiselect styling (match programs) */
        .checkbox-multiselect[data-type="responsible-users"] .checkbox-multiselect-toggle {
            border-color: #dbeafe;
            background: #eef2ff;
            color: #1e3a8a;
            box-shadow: 0 4px 12px rgba(59,130,246,0.12);
        }
        .checkbox-multiselect[data-type="responsible-users"] .checkbox-multiselect-toggle:hover {
            border-color: #bfdbfe;
        }
        .checkbox-multiselect[data-type="responsible-users"] .selected-tag {
            background: #dbeafe;
            color: #1e3a8a;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 4px 8px;
            font-weight: 600;
        }
        .checkbox-multiselect[data-type="responsible-users"] .checkbox-option.selected .checkbox-custom {
            background: #2563eb;
            border-color: #1d4ed8;
        }
        .checkbox-multiselect[data-type="responsible-users"] .checkbox-option.selected {
            background: #f1f5f9;
        }
    </style>
    <main class="nxl-container">
        <div class="nxl-content">

            <!-- Header -->
            <div class="program-hero mb-4 d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-light text-primary">Budget · Projects</span>
                        <span class="badge bg-info text-dark">Create</span>
                    </div>
                    <h4 class="mb-1">Create New Project</h4>
                    <p class="mb-0" style="opacity:0.9;">Assign under a program and set duration, budget and indicators.
                    </p>
                </div>
                <a href="{{ route('budget.projects.index') }}" class="btn btn-light text-primary border-0 shadow-sm">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back to Projects
                </a>
            </div>

            <!-- Alerts -->
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Form Card -->
            <div class="card shadow-sm border-0 section-card">
                <div class="card-body">
                    <form action="{{ route('budget.projects.store') }}" method="POST">
                        @csrf

                        {{-- SECTION 1: Project Information --}}
                        <div class="mb-4">
                            <h5 class="fw-bold text-dark mb-3">Project Information</h5>
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Sector <span class="text-danger">*</span></label>
                                    <select id="sectorSelect" name="sector_id" class="form-select" required>
                                        <option value="">-- Select Sector --</option>
                                        @foreach ($sectors as $sector)
                                            <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Program <span class="text-danger">*</span></label>
                                    <select name="program_id" id="programSelect" class="form-select" required>
                                        <option value="">-- Select Program --</option>
                                    </select>
                                    @error('program_id')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Project Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                                        required placeholder="Enter project name">
                                    @error('name')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Total Budget (GHS) <span class="text-danger">*</span></label>
                                    <input type="number" name="total_budget" id="totalBudget" class="form-control" step="0.01" min="0"
                                        value="{{ old('total_budget') }}" required placeholder="0.00">
                                    @error('total_budget')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Start Year <span class="text-danger">*</span></label>
                                    <input type="number" name="start_year" id="startYear" class="form-control" min="2000" max="2100"
                                        value="{{ old('start_year', now()->year) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">End Year <span class="text-danger">*</span></label>
                                    <input type="number" name="end_year" id="endYear" class="form-control" min="2000" max="2100"
                                        value="{{ old('end_year', now()->year + 1) }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Expected Outcome Type <span class="text-danger">*</span></label>
                                    <select name="expected_outcome_type" id="expectedOutcomeType" class="form-select" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="percentage" {{ old('expected_outcome_type') === 'percentage' ? 'selected' : '' }}>Percentage</option>
                                        <option value="text" {{ old('expected_outcome_type') === 'text' ? 'selected' : '' }}>Text</option>
                                    </select>
                                </div>
                                <div class="col-md-4" id="expectedOutcomePercentageBox" style="display:none;">
                                    <label class="form-label fw-semibold">Outcome (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" name="expected_outcome_percentage" class="form-control" value="{{ old('expected_outcome_percentage') }}" placeholder="0 - 100">
                                </div>
                                <div class="col-md-12" id="expectedOutcomeTextBox" style="display:none;">
                                    <label class="form-label fw-semibold">Outcome Description</label>
                                    <textarea name="expected_outcome_text" rows="3" class="form-control" placeholder="Describe expected outcome">{{ old('expected_outcome_text') }}</textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" rows="3" class="form-control" placeholder="Optional project description">{{ old('description') }}</textarea>
                                </div>

                                <div class="col-12 mt-2">
                                    <h6 class="fw-semibold mb-2">Program Indicators</h6>
                                    <div id="programIndicatorsSection">
                                        <p class="text-muted small mb-0">Select a program to view its indicators.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- SECTION 2: Allocations --}}
                        <div class="mb-4">
                            <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-wallet2 text-primary"></i> Allocations
                                <small class="text-muted fw-normal" id="allocationsHint">Enter budget & years to generate yearly splits.</small>
                            </h5>
                            <div id="allocationsSection" class="d-none">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="allocation_mode" id="allocModeAmount" value="amount" checked>
                                        <label class="form-check-label" for="allocModeAmount">Enter Amounts</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="allocation_mode" id="allocModePercent" value="percent">
                                        <label class="form-check-label" for="allocModePercent">Enter Percentages</label>
                                    </div>
                                    <div class="text-end flex-grow-1">
                                        <small class="text-muted">Totals cannot exceed the project budget.</small>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:120px;">Year</th>
                                                <th style="width:180px;">Amount (GHS)</th>
                                                <th style="width:120px;">Percent</th>
                                            </tr>
                                        </thead>
                                    <tbody id="allocationsTableBody"></tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div id="allocationSummary" class="text-muted small"></div>
                                    <div id="allocationError" class="text-danger fw-semibold"></div>
                                </div>
                            </div>
                        </div>

                        {{-- SECTION 3: Indicators --}}
                        <div>
                            <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-bullseye text-primary"></i> Indicators
                            </h5>
                            <div class="mb-3">
                                <p class="text-muted small mb-1">Program indicators will appear below once a program is selected.</p>
                            </div>
                            <div id="projectIndicatorsContainer" class="row g-3"></div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-4 text-end">
                            <a href="{{ route('budget.projects.index') }}" class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check2-circle me-1"></i> Save Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sectorPrograms = @json($sectors);
            const programsWithIndicators = @json($programsWithIndicators);
            const indicatorPalette = ['#2563eb', '#16a34a', '#f59e0b', '#ec4899', '#0ea5e9', '#ef4444', '#10b981', '#6366f1'];
            const indicatorUnits = @json($indicatorUnits);

            const startYearInput = document.getElementById('startYear');
            const endYearInput = document.getElementById('endYear');
            const totalBudgetInput = document.getElementById('totalBudget');
            const expectedType = document.getElementById('expectedOutcomeType');
            const pctBox = document.getElementById('expectedOutcomePercentageBox');
            const txtBox = document.getElementById('expectedOutcomeTextBox');
            const allocationsSection = document.getElementById('allocationsSection');
            const allocationsTableBody = document.getElementById('allocationsTableBody');
            const allocationSummary = document.getElementById('allocationSummary');
            const allocationError = document.getElementById('allocationError');
            const sectorSelect = document.getElementById('sectorSelect');
            const programSelect = document.getElementById('programSelect');
            const projectIndicatorsContainer = document.getElementById('projectIndicatorsContainer');
            const programIndicatorsSection = document.getElementById('programIndicatorsSection');

            function toggleOutcomeInputs() {
                const val = expectedType.value;
                pctBox.style.display = val === 'percentage' ? 'block' : 'none';
                txtBox.style.display = val === 'text' ? 'block' : 'none';
            }
            expectedType?.addEventListener('change', toggleOutcomeInputs);
            toggleOutcomeInputs();

            const allocationsCache = {};
            function rebuildAllocations() {
                allocationsTableBody.innerHTML = '';
                allocationSummary.textContent = '';
                allocationError.textContent = '';
                allocationsSection.classList.add('d-none');

                const s = parseInt(startYearInput.value, 10);
                const e = parseInt(endYearInput.value, 10);
                const total = parseFloat(totalBudgetInput?.value || '0');
                if (Number.isNaN(s) || Number.isNaN(e) || e < s || total <= 0) return;

                allocationsSection.classList.remove('d-none');
                const years = e - s + 1;
                const mode = document.querySelector('input[name="allocation_mode"]:checked')?.value || 'amount';
                for (let y = s; y <= e; y++) {
                    const prev = allocationsCache[y] ?? (total / years);
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="fw-semibold">${y}</td>
                        <td>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm alloc-amount" data-year="${y}" value="${mode === 'amount' ? prev.toFixed(2) : ''}" ${mode === 'percent' ? 'readonly' : ''}>
                            <input type="hidden" name="allocations[${y}]" value="${prev.toFixed(2)}">
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0" max="100" class="form-control alloc-percent" data-year="${y}" value="${mode === 'percent' ? (prev / total * 100).toFixed(2) : ''}" ${mode === 'amount' ? 'readonly' : ''}>
                                <span class="input-group-text">%</span>
                            </div>
                        </td>
                    `;
                    allocationsTableBody.appendChild(tr);
                }
                updateAllocationSummary();
            }

            function updateAllocationSummary() {
                const rows = allocationsTableBody.querySelectorAll('tr');
                let sum = 0;
                const total = parseFloat(totalBudgetInput?.value || '0');
                rows.forEach(row => {
                    const hidden = row.querySelector('input[type="hidden"]');
                    if (hidden) sum += parseFloat(hidden.value || '0');
                });
                const percent = total > 0 ? (sum / total * 100) : 0;
                allocationSummary.textContent = `Allocated: ${sum.toFixed(2)} / ${total.toFixed(2)} (${percent.toFixed(1)}%)`;
                allocationError.textContent = sum > total + 0.0001 ? 'Allocations exceed total budget.' : '';
            }

            allocationsTableBody.addEventListener('input', (e) => {
                const row = e.target.closest('tr');
                const year = row?.querySelector('.alloc-amount')?.dataset.year;
                const hidden = row?.querySelector('input[type="hidden"]');
                const total = parseFloat(totalBudgetInput?.value || '0');
                if (e.target.classList.contains('alloc-amount')) {
                    const val = parseFloat(e.target.value || '0');
                    allocationsCache[year] = val;
                    if (hidden) hidden.value = val.toFixed(2);
                    const percentInput = row.querySelector('.alloc-percent');
                    if (percentInput && total > 0) percentInput.value = ((val / total) * 100).toFixed(2);
                } else if (e.target.classList.contains('alloc-percent')) {
                    const pct = parseFloat(e.target.value || '0');
                    const amt = total * (pct / 100);
                    allocationsCache[year] = amt;
                    if (hidden) hidden.value = amt.toFixed(2);
                    const amountInput = row.querySelector('.alloc-amount');
                    if (amountInput) amountInput.value = amt.toFixed(2);
                }
                updateAllocationSummary();
            });

            document.querySelectorAll('input[name="allocation_mode"]').forEach(r => r.addEventListener('change', rebuildAllocations));
            ['input', 'change'].forEach(evt => {
                startYearInput?.addEventListener(evt, rebuildAllocations);
                endYearInput?.addEventListener(evt, rebuildAllocations);
                totalBudgetInput?.addEventListener(evt, rebuildAllocations);
            });

            sectorSelect.addEventListener('change', function() {
                const sectorId = this.value;
                programSelect.innerHTML = '<option value="">-- Select Program --</option>';
                projectIndicatorsContainer.innerHTML = '';
                programIndicatorsSection.innerHTML = '<p class="text-muted small mb-0">Select a program to view its indicators.</p>';
                if (sectorId) {
                    const selectedSector = sectorPrograms.find(s => String(s.id) === String(sectorId));
                    if (selectedSector && selectedSector.programs?.length) {
                        selectedSector.programs.forEach(program => {
                            const option = document.createElement('option');
                            option.value = program.id;
                            option.textContent = program.name;
                            programSelect.appendChild(option);
                        });
                    } else {
                        const opt = document.createElement('option');
                        opt.textContent = 'No programs available';
                        programSelect.appendChild(opt);
                    }
                }
            });

            programSelect.addEventListener('change', function() {
                const programId = this.value;
                projectIndicatorsContainer.innerHTML = '';
                if (!programId) {
                    programIndicatorsSection.innerHTML = '<p class="text-muted small mb-0">Select a program to view its indicators.</p>';
                    return;
                }
                const program = programsWithIndicators.find(p => String(p.id) === String(programId));
                if (!program || !program.indicators?.length) {
                    programIndicatorsSection.innerHTML = '<div class="alert alert-warning mb-0">This program has no indicators configured.</div>';
                    return;
                }
                let infoHtml = '<div class="row g-2">';
                program.indicators.forEach(indicator => {
                    infoHtml += `<div class="col-md-6"><div class="p-3 border border-light rounded bg-light"><p class="fw-semibold mb-0"><i class="bi bi-bullseye me-2"></i>${indicator.name}</p></div></div>`;
                });
                infoHtml += '</div>';
                programIndicatorsSection.innerHTML = infoHtml;

                program.indicators.forEach((indicator, idx) => {
                    projectIndicatorsContainer.appendChild(renderProgramIndicatorBlock(indicator, idx));
                });
            });

            function renderProgramIndicatorBlock(programIndicator, blockIdx) {
                const wrapper = document.createElement('div');
                wrapper.className = 'col-12';
                wrapper.innerHTML = `
                    <div class="indicator-card" style="--stripe:${indicatorPalette[blockIdx % indicatorPalette.length]};">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="indicator-chip">${programIndicator.name}</span>
                                <small class="text-muted">Program Indicator</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary add-project-indicator" data-block="${blockIdx}" data-prog="${programIndicator.id}">
                                <i class="bi bi-plus-circle me-1"></i> Add Project Indicator
                            </button>
                        </div>
                        <div class="row g-3 project-indicator-list" data-block="${blockIdx}"></div>
                    </div>
                `;
                const list = wrapper.querySelector('.project-indicator-list');
                list.appendChild(renderProjectIndicatorRow(programIndicator.id, blockIdx, 0));
                return wrapper;
            }

            function renderProjectIndicatorRow(programIndicatorId, blockIdx, idx) {
                const row = document.createElement('div');
                row.className = 'col-12';
                row.innerHTML = `
                    <div class="row g-2 align-items-end border rounded p-3">
                        <div class="col-md-6">
                            <label class="form-label">Indicator Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="indicators[${programIndicatorId}][project_indicators][${idx}][name]" placeholder="Enter indicator name" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Baseline Type</label>
                            <select class="form-select baseline-type" data-idx="${programIndicatorId}_${idx}" name="indicators[${programIndicatorId}][project_indicators][${idx}][baseline_type]">
                                <option value="year">Year</option>
                                <option value="quarter">Quarter</option>
                                <option value="month">Month</option>
                                <option value="week">Week</option>
                                <option value="day">Day</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Baseline Period</label>
                            <input type="text" class="form-control baseline-period" data-idx="${programIndicatorId}_${idx}" name="indicators[${programIndicatorId}][project_indicators][${idx}][baseline_year]" placeholder="YYYY">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Baseline Value</label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control baseline-value" data-idx="${programIndicatorId}_${idx}" name="indicators[${programIndicatorId}][project_indicators][${idx}][baseline_value]" placeholder="0.00">
                                <span class="input-group-text baseline-unit-label" data-idx="${programIndicatorId}_${idx}">—</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Indicator Level</label>
                            <select class="form-select" name="indicators[${programIndicatorId}][project_indicators][${idx}][indicator_level_id]">
                                <option value="">Select Level</option>
                                @foreach ($indicatorLevels as $level)
                                    <option value="{{ $level->id }}">{{ $level->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit</label>
                            <select class="form-select unit-select" data-idx="${programIndicatorId}_${idx}" name="indicators[${programIndicatorId}][project_indicators][${idx}][unit_id]">
                                <option value="">Select Unit</option>
                                @foreach ($indicatorUnits as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->symbol ? ' (' . $unit->symbol . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Reporting Frequency</label>
                            <select class="form-select" name="indicators[${programIndicatorId}][project_indicators][${idx}][frequency_of_reporting_id]">
                                <option value="">Select Frequency</option>
                                @foreach ($reportingFrequencies as $freq)
                                    <option value="{{ $freq->id }}">{{ $freq->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Methodology</label>
                            <select class="form-select" name="indicators[${programIndicatorId}][project_indicators][${idx}][methodology_id]">
                                <option value="">Select Methodology</option>
                                @foreach ($indicatorMethodologies as $meth)
                                    <option value="{{ $meth->id }}">{{ $meth->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Responsible Users</label>
                            <select name="indicators[${programIndicatorId}][project_indicators][${idx}][responsible_user_ids][]" class="form-select checkbox-multiselect-target" multiple data-type="responsible-users" data-placeholder="Select responsible users...">
                                @foreach ($responsibleUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Select one or more users; they will receive reminder emails.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Primary Source</label>
                            <div class="input-group">
                                <select class="form-select" name="indicators[${programIndicatorId}][project_indicators][${idx}][primary_source_type]" style="max-width:140px;">
                                    <option value="manual">Manual</option>
                                    <option value="external">External System</option>
                                    <option value="file">File Location</option>
                                </select>
                                <input type="text" class="form-control" name="indicators[${programIndicatorId}][project_indicators][${idx}][primary_source_detail]" placeholder="API URL / file path / note">
                            </div>
                            <small class="text-muted">Specify how data arrives, matching the reporting frequency.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="indicators[${programIndicatorId}][project_indicators][${idx}][notes]" rows="2" placeholder="Additional notes"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Definition / Formula</label>
                            <select class="form-select mb-2" name="indicators[${programIndicatorId}][project_indicators][${idx}][definition_id]">
                                <option value="">Select Definition</option>
                                @foreach ($indicatorDefinitions as $def)
                                    <option value="{{ $def->id }}">{{ $def->name }}</option>
                                @endforeach
                            </select>
                            <textarea class="form-control" name="indicators[${programIndicatorId}][project_indicators][${idx}][definitions]" rows="2" placeholder="Override or add description (optional)"></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-project-indicator"><i class="bi bi-x-circle"></i> Remove</button>
                        </div>
                    </div>
                `;

                const baselineType = row.querySelector('.baseline-type');
                const baselineYear = row.querySelector('.baseline-period');
                const unitSelect = row.querySelector('.unit-select');
                const badge = row.querySelector('.baseline-unit-label');

                function updatePlaceholder(type) {
                    if (!baselineYear) return;
                    switch (type) {
                        case 'day':
                            baselineYear.type = 'date'; baselineYear.placeholder = 'YYYY-MM-DD'; break;
                        case 'month':
                            baselineYear.type = 'month'; baselineYear.placeholder = 'YYYY-MM'; break;
                        case 'quarter':
                            baselineYear.type = 'text'; baselineYear.placeholder = 'YYYY-Q1'; break;
                        case 'week':
                            baselineYear.type = 'week'; baselineYear.placeholder = 'YYYY-W01'; break;
                        default:
                            baselineYear.type = 'number'; baselineYear.placeholder = 'YYYY';
                    }
                }
                baselineType?.addEventListener('change', (e) => updatePlaceholder(e.target.value));
                updatePlaceholder('year');

                unitSelect?.addEventListener('change', () => {
                    const selected = indicatorUnits.find(u => String(u.id) === String(unitSelect.value));
                    badge.textContent = selected ? (selected.symbol ? selected.symbol : selected.name) : '—';
                });

                row.querySelector('.remove-project-indicator')?.addEventListener('click', (e) => {
                    e.preventDefault();
                    row.remove();
                });
                // Initialize multiselect inside this row
                initMultiSelects();
                return row;
            }

            projectIndicatorsContainer.addEventListener('click', (e) => {
                const addBtn = e.target.closest('.add-project-indicator');
                if (addBtn) {
                    const block = addBtn.dataset.block;
                    const progId = addBtn.dataset.prog;
                    const list = projectIndicatorsContainer.querySelector(`.project-indicator-list[data-block="${block}"]`);
                    const nextIdx = list.querySelectorAll('.row.g-2').length;
                    list.appendChild(renderProjectIndicatorRow(progId, block, nextIdx));
                }
            });

            function initMultiSelects() {
                document.querySelectorAll('.checkbox-multiselect-target').forEach(select => {
                    if (select.dataset.enhanced === '1') return;
                    const id = select.id || `multi-${Math.random().toString(16).slice(2)}`;
                    select.id = id;
                    const type = select.dataset.type || 'default';
                    const placeholder = select.dataset.placeholder || 'Select options...';
                    if (window.CheckboxMultiSelect) {
                        new CheckboxMultiSelect(select, {
                            type,
                            placeholder,
                            searchPlaceholder: 'Type to search...',
                            showTags: true,
                            maxTagsVisible: 4
                        });
                        select.dataset.enhanced = '1';
                    }
                });
            }

            rebuildAllocations();
            initMultiSelects();
        });
    </script>

@push('scripts')
    <script>
        (function () {
            function enhanceMultiSelects() {
                if (!window.CheckboxMultiSelect) return;
                document.querySelectorAll('.checkbox-multiselect-target').forEach(select => {
                    if (select.dataset.enhanced === '1') return;
                    const id = select.id || `multi-${Math.random().toString(16).slice(2)}`;
                    select.id = id;
                    const type = select.dataset.type || 'default';
                    const placeholder = select.dataset.placeholder || 'Select options...';
                    new CheckboxMultiSelect(select, {
                        type,
                        placeholder,
                        searchPlaceholder: 'Type to search...',
                        showTags: true,
                        maxTagsVisible: 4
                    });
                    select.dataset.enhanced = '1';
                });
            }
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(enhanceMultiSelects, 0);
                setTimeout(enhanceMultiSelects, 250);
            });
            window.addEventListener('load', enhanceMultiSelects);
        })();
    </script>
@endpush

@endsection
