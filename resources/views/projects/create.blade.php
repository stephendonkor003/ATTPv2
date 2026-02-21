@extends('layouts.app')

@section('title', 'Create Project')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <!-- PAGE HEADER -->
            <div class="page-header d-flex justify-content-between mb-4">
                <div>
                    <h4 class="mb-1">Create New Project</h4>
                    <p class="text-muted m-0">Assign project details and allocate budget year by year.</p>
                </div>
                <a href="{{ route('budget.projects.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back
                </a>
            </div>

            <!-- VALIDATION ERRORS -->
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>There were errors:</strong>
                    <ul class="my-2">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <!-- FORM -->
            <form action="{{ route('budget.projects.store') }}" method="POST">
                @csrf

                <!-- PROJECT DETAILS -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">

                        <div class="row g-4">

                            <!-- PROGRAM -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Program <span class="text-danger">*</span></label>
                                <select name="program_id" id="programSelect" class="form-select" required>
                                    <option value="">-- Select Program --</option>
                                    @foreach ($programs as $p)
                                        <option value="{{ $p->id }}" data-start="{{ $p->start_year }}"
                                            data-end="{{ $p->end_year }}" data-currency="{{ $p->currency }}">
                                            {{ $p->program_id }} â€” {{ $p->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- PROJECT NAME -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Project Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required>
                            </div>

                            <!-- TOTAL PROJECT BUDGET -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Total Project Budget <span
                                        class="text-danger">*</span></label>
                                <input type="number" name="total_budget" id="totalBudget" class="form-control"
                                    min="0" step="0.01" required>
                            </div>

                            <!-- START YEAR -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Start Year <span class="text-danger">*</span></label>
                                <input type="number" name="start_year" id="startYear" class="form-control" required>
                            </div>

                            <!-- END YEAR -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">End Year <span class="text-danger">*</span></label>
                                <input type="number" name="end_year" id="endYear" class="form-control" required>
                            </div>

                            <!-- ALLOCATION MODE -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Allocation Mode <span
                                        class="text-danger">*</span></label>
                                <select name="allocation_mode" id="allocationMode" class="form-select" required>
                                    <option value="">-- Select Mode --</option>
                                    <option value="amount">Amount Allocation</option>
                                    <option value="percentage">Percentage Allocation (%)</option>
                                </select>
                            </div>

                            <!-- DESCRIPTION -->
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Description (Optional)</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>

                            <!-- EXPECTED OUTCOME -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Expected Outcome Type <span
                                        class="text-danger">*</span></label>
                                <select name="expected_outcome_type" id="expectedOutcomeType" class="form-select" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="percentage"
                                        {{ old('expected_outcome_type') === 'percentage' ? 'selected' : '' }}>Percentage
                                    </option>
                                    <option value="text" {{ old('expected_outcome_type') === 'text' ? 'selected' : '' }}>
                                        Text</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Expected Outcome <span
                                        class="text-danger">*</span></label>
                                <div id="expectedOutcomePercentage" style="display:none;">
                                    <div class="input-group">
                                        <input type="number" name="expected_outcome_percentage" class="form-control"
                                            min="0" max="100" step="0.01" placeholder="e.g. 0"
                                            value="{{ old('expected_outcome_percentage') }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div id="expectedOutcomeText" style="display:none;">
                                    <textarea name="expected_outcome_text" class="form-control" rows="2"
                                        placeholder="e.g. By end of program, malaria rate at 0% or 10,000 learners enrolled">{{ old('expected_outcome_text') }}</textarea>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>

                <!-- ALLOCATION TABLE -->
                <div id="allocSection" style="display:none;">
                    <div class="card shadow-sm">
                        <div class="card-body">

                            <h5 class="fw-semibold mb-3">
                                Yearly Budget Allocation
                                (<span id="currencyLabel">--</span>)
                            </h5>

                            <div class="alert alert-info p-2">
                                Total Project Budget: <strong id="totalBudgetLabel">0.00</strong>
                            </div>

                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Year</th>
                                        <th id="allocHeader">Allocation</th>
                                    </tr>
                                </thead>
                                <tbody id="allocTableBody"></tbody>
                            </table>

                            <div id="remainingAlert" class="alert alert-info mt-3">
                                Remaining: <strong id="remainingValue">0.00</strong>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- INDICATORS CARD -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Indicators</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Select a program to set up project indicators under each program
                            indicator.</p>
                        <div id="indicatorsByProgramSection">
                            <p class="text-muted small">No program selected yet.</p>
                        </div>
                    </div>
                </div>

                <!-- BUTTON -->
                <div class="mt-4 d-flex justify-content-end">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Save Project
                    </button>
                </div>

            </form>

        </div>
    </main>

    <!-- STYLES -->
    <style>
        .ai-blink {
            animation: aiGlow 1.5s ease-out 2;
            border: 2px solid #28a745 !important;
        }

        @keyframes aiGlow {
            0% {
                box-shadow: 0 0 0px #28a745;
            }

            50% {
                box-shadow: 0 0 12px #28a745;
            }

            100% {
                box-shadow: 0 0 0px #28a745;
            }
        }
    </style>

    <!-- SCRIPT -->
    <script>
        let programStart = 0;
        let programEnd = 0;
        let currency = "";
        let manual = {};

        const programSelect = document.getElementById("programSelect");
        const startYear = document.getElementById("startYear");
        const endYear = document.getElementById("endYear");
        const budget = document.getElementById("totalBudget");
        const allocMode = document.getElementById("allocationMode");
        const tableBody = document.getElementById("allocTableBody");
        const allocSection = document.getElementById("allocSection");
        const remainingValue = document.getElementById("remainingValue");
        const totalBudgetLabel = document.getElementById("totalBudgetLabel");
        const allocHeader = document.getElementById("allocHeader");
        const currencyLabel = document.getElementById("currencyLabel");
        const expectedOutcomeType = document.getElementById("expectedOutcomeType");
        const expectedOutcomePercentage = document.getElementById("expectedOutcomePercentage");
        const expectedOutcomeText = document.getElementById("expectedOutcomeText");

        programSelect.addEventListener("change", function() {
            programStart = parseInt(this.selectedOptions[0].dataset.start);
            programEnd = parseInt(this.selectedOptions[0].dataset.end);
            currency = this.selectedOptions[0].dataset.currency;

            startYear.value = programStart;
            endYear.value = programEnd;
            currencyLabel.innerText = currency;

            generateTable();
        });

        budget.addEventListener("input", updateAllocations);
        startYear.addEventListener("input", generateTable);
        endYear.addEventListener("input", generateTable);
        allocMode.addEventListener("change", generateTable);
        expectedOutcomeType.addEventListener("change", toggleExpectedOutcome);

        function generateTable() {
            let s = parseInt(startYear.value);
            let e = parseInt(endYear.value);

            if (!s || !e || e < s || !allocMode.value) {
                allocSection.style.display = "none";
                return;
            }

            allocHeader.innerText = allocMode.value === "percentage" ? "Allocation (%)" : "Allocation";
            tableBody.innerHTML = "";
            manual = {}; // reset manual edits

            for (let year = s; year <= e; year++) {
                tableBody.innerHTML += `
                    <tr>
                        <td>${year}</td>
                        <td>
                            <input type="number" min="0" step="0.01"
                                   class="form-control allocInput"
                                   name="allocations[${year}]"
                                   data-year="${year}"
                                   value="0">
                        </td>
                    </tr>
                `;
            }

            allocSection.style.display = "block";
            attachInputListeners();
            updateAllocations();
        }

        function attachInputListeners() {
            document.querySelectorAll('.allocInput').forEach(input => {
                input.addEventListener('input', function() {
                    manual[this.dataset.year] = true;
                    updateAllocations();
                });
            });
        }

        function updateAllocations() {
            let total = parseFloat(budget.value) || 0;
            totalBudgetLabel.innerText = total.toFixed(2);

            let inputs = document.querySelectorAll('.allocInput');
            let manualTotal = 0;

            inputs.forEach(i => {
                let v = parseFloat(i.value) || 0;
                if (manual[i.dataset.year]) {
                    // convert percentage to amount
                    if (allocMode.value === "percentage") {
                        v = (v / 100) * total;
                    }
                    manualTotal += v;
                }
            });

            let autoYears = [...inputs].filter(i => !manual[i.dataset.year]);
            let remaining = total - manualTotal;

            if (autoYears.length > 0) {
                let autoAmount = remaining / autoYears.length;

                autoYears.forEach(i => {
                    let year = i.dataset.year;
                    i.value = allocMode.value === "percentage" ?
                        ((autoAmount / total) * 100).toFixed(2) :
                        autoAmount.toFixed(2);

                    i.classList.add("ai-blink");
                    setTimeout(() => i.classList.remove("ai-blink"), 2000);
                });
            }

            // final total check
            let finalTotal = 0;
            inputs.forEach(i => {
                let v = parseFloat(i.value) || 0;
                if (allocMode.value === "percentage") {
                    v = (v / 100) * total;
                }
                finalTotal += v;
            });

            remainingValue.innerText = (total - finalTotal).toFixed(2);

            if (finalTotal > total) {
                remainingAlert.className = "alert alert-danger";
            } else {
                remainingAlert.className = "alert alert-info";
            }
        }

        function toggleExpectedOutcome() {
            const type = expectedOutcomeType.value;
            expectedOutcomePercentage.style.display = type === "percentage" ? "block" : "none";
            expectedOutcomeText.style.display = type === "text" ? "block" : "none";
        }

        toggleExpectedOutcome();

        // === Hierarchical Indicators Management ===
        const programsWithIndicators = @json($programsWithIndicators ?? []);
        const indicatorLevels = @json($indicatorLevels ?? []);
        const indicatorUnits = @json($indicatorUnits ?? []);
        const reportingFrequencies = @json($reportingFrequencies ?? []);
        const baselineTypes = [
            { value: 'year', label: 'Year' },
            { value: 'quarter', label: 'Quarter' },
            { value: 'month', label: 'Month' },
            { value: 'week', label: 'Week' },
            { value: 'day', label: 'Day' },
        ];

        const projectIndicatorCounters = {};

        function h(str = '') {
            return String(str ?? '').replace(/[&<>\"']/g, (ch) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[ch]));
        }

        function optionList(list, selected, labelFn) {
            const sel = selected ?? '';
            return list.map(item => {
                const value = item.value ?? item.id ?? '';
                const label = labelFn ? labelFn(item) : (item.label ?? item.name ?? value);
                const isSelected = String(value) === String(sel) ? 'selected' : '';
                return `<option value="${h(value)}" ${isSelected}>${h(label)}</option>`;
            }).join('');
        }

        function updateBaselinePlaceholder(idx, type) {
            const field = document.querySelector(`input.baseline-period[data-idx="${idx}"]`);
            if (!field) return;
            switch (type) {
                case 'day':
                    field.type = 'date';
                    field.placeholder = 'YYYY-MM-DD';
                    break;
                case 'month':
                    field.type = 'month';
                    field.placeholder = 'YYYY-MM';
                    break;
                case 'quarter':
                    field.type = 'text';
                    field.placeholder = 'YYYY-Q1';
                    break;
                case 'week':
                    field.type = 'week';
                    field.placeholder = 'YYYY-W01';
                    break;
                default:
                    field.type = 'number';
                    field.placeholder = 'YYYY';
            }
        }

        function updateBaselineUnit(idx) {
            const unitSelect = document.querySelector(`select[name="indicators[${idx}][unit_id]"]`) ||
                document.querySelector(`select[name$="${idx}][unit_id]"]`);
            const badge = document.querySelector(`.baseline-unit-label[data-idx="${idx}"]`);
            if (!badge) return;
            const selected = indicatorUnits.find(u => String(u.id) === String(unitSelect?.value));
            badge.textContent = selected ? (selected.symbol ? selected.symbol : selected.name) : '—';
        }

        function updateBaselinePlaceholder(idx, type) {
            const field = document.querySelector(`input.baseline-period[data-idx="${idx}"]`);
            if (!field) return;
            switch (type) {
                case 'day':
                    field.type = 'date';
                    field.placeholder = 'YYYY-MM-DD';
                    break;
                case 'month':
                    field.type = 'month';
                    field.placeholder = 'YYYY-MM';
                    break;
                case 'quarter':
                    field.type = 'text';
                    field.placeholder = 'YYYY-Q1';
                    break;
                default:
                    field.type = 'number';
                    field.placeholder = 'YYYY';
            }
        }

        function updateBaselineUnit(idx) {
            const unitSelect = document.querySelector(`select[name*="[unit_id]"][name$="${idx}][unit_id]"]`) ||
                document.querySelector(`select[data-idx="${idx}"].baseline-unit-select`);
            const badge = document.querySelector(`.baseline-unit-label[data-idx="${idx}"]`);
            if (!badge) return;
            const selected = indicatorUnits.find(u => String(u.id) === String(unitSelect?.value));
            badge.textContent = selected ? (selected.symbol ? selected.symbol : selected.name) : '—';
        }

        function addProjectIndicator(programIndicatorId, data = {}) {
            if (!projectIndicatorCounters[programIndicatorId]) {
                projectIndicatorCounters[programIndicatorId] = 0;
            }

            const idx = projectIndicatorCounters[programIndicatorId]++;
            const listDiv = document.getElementById(`project-indicators-${programIndicatorId}`);
            if (!listDiv) return;

            const row = document.createElement('div');
            row.className = 'card mb-2 p-3 project-indicator-row';
            row.innerHTML = `
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">Project Indicator <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="indicators[${programIndicatorId}][project_indicators][${idx}][name]"
                               value="${h(data.name)}" placeholder="Enter indicator name" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Baseline Period</label>
                        <input type="text" class="form-control baseline-period" data-idx="${programIndicatorId}_${idx}" name="indicators[${programIndicatorId}][project_indicators][${idx}][baseline_year]"
                               value="${h(data.baseline_year)}" placeholder="YYYY">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Baseline Type</label>
                        <select class="form-select baseline-type" data-idx="${programIndicatorId}_${idx}" name="indicators[${programIndicatorId}][project_indicators][${idx}][baseline_type]">
                            <option value="">Select</option>
                            ${optionList(baselineTypes, data.baseline_type || 'year')}
                        </select>
                    </div>
                            <div class="col-md-3">
                                <label class="form-label">Baseline Value</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control baseline-value" data-idx="${programIndicatorId}_${idx}"
                                           name="indicators[${programIndicatorId}][project_indicators][${idx}][baseline_value]" value="${h(data.baseline_value)}" placeholder="0.00">
                                    <span class="input-group-text baseline-unit-label" data-idx="${programIndicatorId}_${idx}">—</span>
                                </div>
                            </div>
                    <div class="col-md-4">
                        <label class="form-label">Indicator Level</label>
                        <select class="form-select" name="indicators[${programIndicatorId}][project_indicators][${idx}][indicator_level_id]">
                            <option value="">Select Level</option>
                            ${optionList(indicatorLevels, data.indicator_level_id)}
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Unit</label>
                        <select class="form-select" name="indicators[${programIndicatorId}][project_indicators][${idx}][unit_id]">
                            <option value="">Select Unit</option>
                            ${optionList(indicatorUnits, data.unit_id, (u) => u.symbol ? `${u.name} (${u.symbol})` : u.name)}
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reporting Frequency</label>
                        <select class="form-select" name="indicators[${programIndicatorId}][project_indicators][${idx}][frequency_of_reporting_id]">
                            <option value="">Select</option>
                            ${optionList(reportingFrequencies, data.frequency_of_reporting_id)}
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Responsible Party</label>
                        <input type="text" class="form-control" name="indicators[${programIndicatorId}][project_indicators][${idx}][responsible_party]"
                               value="${h(data.responsible_party)}" placeholder="Who reports?">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Primary Source</label>
                        <input type="text" class="form-control" name="indicators[${programIndicatorId}][project_indicators][${idx}][primary_source]"
                               value="${h(data.primary_source)}" placeholder="e.g., DHIS2, survey">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Methodology</label>
                        <input type="text" class="form-control" name="indicators[${programIndicatorId}][project_indicators][${idx}][methodology]"
                               value="${h(data.methodology)}" placeholder="How is it measured?">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="indicators[${programIndicatorId}][project_indicators][${idx}][notes]" rows="2"
                                  placeholder="Additional notes">${h(data.notes)}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Definitions</label>
                        <textarea class="form-control" name="indicators[${programIndicatorId}][project_indicators][${idx}][definitions]" rows="2"
                                  placeholder="Definitions and terms">${h(data.definitions)}</textarea>
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="button" class="btn btn-sm btn-danger remove-project-indicator" title="Remove indicator">
                            <i class="bi bi-trash"></i> Remove Indicator
                        </button>
                    </div>
                </div>
            `;

            row.querySelector('.remove-project-indicator').addEventListener('click', function(e) {
                e.preventDefault();
                row.remove();
            });

            listDiv.appendChild(row);
        }

        function renderProgramIndicators(programId) {
            const section = document.getElementById('indicatorsByProgramSection');
            const program = programsWithIndicators.find(p => String(p.id) === String(programId));

            // reset counters
            Object.keys(projectIndicatorCounters).forEach(key => delete projectIndicatorCounters[key]);

            if (!program) {
                section.innerHTML = '<p class="text-muted small">No program selected yet.</p>';
                return;
            }

            if (!program.indicators || program.indicators.length === 0) {
                section.innerHTML = '<p class="text-muted small">This program has no indicators.</p>';
                return;
            }

            let html = '';
            program.indicators.forEach((programInd) => {
                html += `
                    <div class="card border-primary mb-4">
                        <div class="card-header bg-light-primary d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-bullseye me-2"></i>${h(programInd.name)}</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary add-project-indicator-btn" data-program-indicator-id="${programInd.id}">
                                <i class="bi bi-plus-circle me-1"></i> Add Project Indicator
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">Capture project-level indicator details (baseline, unit, frequency, sources).</p>
                            <div id="project-indicators-${programInd.id}" class="project-indicators-list"></div>
                        </div>
                    </div>
                `;
            });
            section.innerHTML = html;

            document.querySelectorAll('.add-project-indicator-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    addProjectIndicator(this.dataset.programIndicatorId);
                });
            });
        }

        // Attach listener after helper is defined
        programSelect.addEventListener('change', () => renderProgramIndicators(programSelect.value));
        if (programSelect.value) {
            renderProgramIndicators(programSelect.value);
        }
    </script>

@endsection
