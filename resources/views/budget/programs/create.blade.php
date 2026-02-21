@extends('layouts.app')

@section('title', 'Create Program')

@section('content')
    <style>
        .program-hero {
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 45%, #7c3aed 100%);
            color: #fff;
            border-radius: 18px;
            padding: 18px 22px;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.25);
        }
        .program-hero .badge-soft { background: rgba(255, 255, 255, 0.18); color: #fff; border: 1px solid rgba(255,255,255,0.25); }
        .section-card { border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04); }
        .section-title { font-weight: 700; color: #0f172a; }
        .pill { border-radius: 999px; padding: 6px 12px; font-weight: 600; }
        .pill-info { background: #e0f2fe; color: #075985; }
        .pill-success { background: #dcfce7; color: #166534; }
        .pill-warning { background: #fef9c3; color: #854d0e; }
        .indicator-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; position: relative; }
        .indicator-card::before { content: ''; position: absolute; left: 0; top: 10px; bottom: 10px; width: 4px; border-radius: 20px; background: var(--stripe, #2563eb); }
        .indicator-chip { font-weight: 700; font-size: 13px; padding: 6px 10px; border-radius: 10px; color: #0f172a; background: #e0f2fe; }
        .indicator-actions { position: absolute; right: 10px; top: 10px; }
        .help-hint { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 12px; }
        /* Responsible users multiselect styling */
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
    @push('styles')
        <link rel="stylesheet" href="{{ asset('admin/assets/css/select2-custom.css') }}">
    @endpush

    @push('scripts')
        <script src="{{ asset('admin/assets/js/checkbox-multiselect.js') }}"></script>
    @endpush
    <main class="nxl-container">
        <div class="nxl-content">

            <!-- HEADER -->
            <div class="program-hero mb-4 d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge badge-soft">Budget · Programs</span>
                        <span class="pill pill-info">Auto allocations</span>
                    </div>
                    <h4 class="mb-1">Create New Program</h4>
                    <p class="mb-0" style="opacity:0.9;">Link an approved funding line, set expected outcomes, and add indicators with colorful clarity.</p>
                </div>
                <a href="{{ route('budget.programs.index') }}" class="btn btn-light text-primary border-0 shadow-sm">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back
                </a>
            </div>

            {{-- GLOBAL ERROR DISPLAY --}}
            @if ($errors->any())
                <div class="alert alert-danger mt-3">
                    <strong>Error:</strong>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger mt-3">{{ session('error') }}</div>
            @endif


            <!-- FORM -->
            <div class="card shadow-sm border-0 section-card">
                <div class="card-body">

                    <form action="{{ route('budget.programs.store') }}" method="POST" id="programForm">
                        @csrf

                        <div class="row g-4">

                            <!-- LEFT COLUMN -->
                            <div class="col-lg-8">
                                <div class="row g-3">
                                    <!-- SECTOR -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Sector <span class="text-danger">*</span></label>
                                        <select name="sector_id" class="form-select" required>
                                            <option value="">-- Select Sector --</option>
                                            @foreach ($sectors as $sector)
                                                <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- PROGRAM ID -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Program ID <span class="text-danger">*</span></label>
                                        <input type="text" name="program_id" class="form-control" placeholder="PROG001" required>
                                    </div>

                                    <!-- PROGRAM NAME -->
                                    <div class="col-md-12">
                                        <label class="form-label fw-semibold">Program Name <span
                                                class="text-danger">*</span></label>
                                        <select name="program_name" id="programNameSelect" class="form-select" required>
                                            <option value="">-- Select Approved Program --</option>
                                            @foreach ($approvedPrograms as $programName)
                                                @php
                                                    $funding = $approvedProgramFunding[$programName] ?? null;
                                                @endphp
                                                <option value="{{ $programName }}" data-currency="{{ $funding['currency'] ?? '' }}"
                                                    data-start-year="{{ $funding['start_year'] ?? '' }}"
                                                    data-end-year="{{ $funding['end_year'] ?? '' }}"
                                                    data-total-budget="{{ $funding['total_budget'] ?? '' }}"
                                                    @selected(old('program_name') === $programName)>
                                                    {{ $programName }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted d-block mt-1">
                                            Program names come from approved funding records.
                                        </small>
                                    </div>

                                    <!-- CURRENCY + BUDGET -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                                        <select id="currencySelect" class="form-select" required disabled>
                                            <option value="">-- Select --</option>
                                            <option value="USD">USD</option>
                                            <option value="EUR">EUR</option>
                                            <option value="GHS">GHS</option>
                                            <option value="NGN">NGN</option>
                                            <option value="ZAR">ZAR</option>
                                        </select>
                                        <input type="hidden" name="currency" id="currencyHidden" value="{{ old('currency') }}">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Total Budget <span
                                                class="text-danger">*</span></label>
                                        <input type="number" name="total_budget" id="totalBudget" class="form-control"
                                            step="0.01" min="0" placeholder="0.00" required readonly>
                                    </div>

                                    <!-- YEARS -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Start Year <span class="text-danger">*</span></label>
                                        <input type="number" name="start_year" id="startYear" class="form-control" min="1900"
                                            max="2100" placeholder="2025" required readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">End Year <span class="text-danger">*</span></label>
                                        <input type="number" name="end_year" id="endYear" class="form-control" min="1900"
                                            max="2100" placeholder="2030" required readonly>
                                    </div>
                                    <!-- CALCULATED TOTAL YEARS -->
                                    <input type="hidden" name="total_years" id="totalYears">

                                    <!-- MODE -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Allocation Mode <span
                                                class="text-danger">*</span></label>
                                        <select name="mode" id="allocationMode" class="form-select" required>
                                            <option value="amount" selected>Amount</option>
                                            <option value="percentage">Percentage (%)</option>
                                        </select>
                                    </div>

                                    <!-- DESCRIPTION -->
                                    <div class="col-md-12">
                                        <label class="form-label fw-semibold">Description</label>
                                        <textarea name="description" class="form-control" rows="3" placeholder="Optional details"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN -->
                            <div class="col-lg-4">
                                <div class="help-hint mb-3">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="bi bi-lightbulb text-warning"></i>
                                        <strong>Quick tips</strong>
                                    </div>
                                    <ul class="mb-0 ps-3 small text-muted">
                                        <li>Select an approved program to auto-fill budget + years.</li>
                                        <li>Allocation rows appear once start/end years are set.</li>
                                        <li>Add indicators with clear baseline types & units.</li>
                                    </ul>
                                </div>

                                <!-- EXPECTED OUTCOME -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Expected Outcome Type <span
                                            class="text-danger">*</span></label>
                                    <select name="expected_outcome_type" id="expectedOutcomeType" class="form-select"
                                        required>
                                        <option value="">-- Select Type --</option>
                                        <option value="percentage" @selected(old('expected_outcome_type') === 'percentage')>
                                            Percentage
                                        </option>
                                        <option value="text" @selected(old('expected_outcome_type') === 'text')>
                                            Text
                                        </option>
                                    </select>
                                </div>

                                <div class="mb-3" id="expectedOutcomePercentageWrap" style="display:none;">
                                    <label class="form-label fw-semibold">Expected Outcome (%) <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="expected_outcome_percentage"
                                            id="expectedOutcomePercentage" class="form-control" min="0"
                                            max="100" step="0.01" value="{{ old('expected_outcome_percentage') }}"
                                            placeholder="0 - 100">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Example: 0% malaria rate by end of program.</small>
                                </div>

                                <div class="mb-3" id="expectedOutcomeTextWrap" style="display:none;">
                                    <label class="form-label fw-semibold">Expected Outcome (Text) <span
                                            class="text-danger">*</span></label>
                                    <textarea name="expected_outcome_text" id="expectedOutcomeText" class="form-control" rows="3"
                                        placeholder="Describe the expected outcome">{{ old('expected_outcome_text') }}</textarea>
                                    <small class="text-muted">Example: Send 2,000 students to school.</small>
                                </div>
                            </div>

                            <!-- INDICATORS -->
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div>
                                        <h6 class="section-title mb-0">Indicators</h6>
                                        <p class="text-muted small mb-0">Add indicators sourced from your M&E tables (levels, units, frequencies).</p>
                                    </div>
                                    <button type="button" id="addIndicatorBtn"
                                        class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg me-1"></i> Add Indicator</button>
                                </div>

                                <div id="indicatorsSection" class="mt-2">
                                    <div id="indicatorsList" class="row g-3"></div>

                                    <!-- template -->
                                    <template id="indicatorRowTpl">
                                        <div class="col-12">
                                            <div class="indicator-card indicator-row" style="--stripe:#2563eb;">
                                                <div class="indicator-actions">
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-indicator">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="d-flex align-items-center gap-2 mb-3">
                                                    <span class="indicator-chip">Indicator #__NUM__</span>
                                                    <span class="badge bg-light text-primary border">M&E ready</span>
                                                </div>
                                                <div class="row g-2">
                                                    {{-- Indicator Name --}}
                                                    <div class="col-md-6">
                                                        <label class="form-label">Indicator Name <span
                                                                class="text-danger">*</span></label>
                                                        <input type="text" name="indicators[__IDX__][name]"
                                                            class="form-control" placeholder="e.g., Malaria Incidence Rate"
                                                            required>
                                                    </div>

                                                    {{-- Baseline Period --}}
                                                    <div class="col-md-6">
                                                        <label class="form-label">Baseline Type</label>
                                                        <select name="indicators[__IDX__][baseline_type]"
                                                            class="form-select baseline-type" data-idx="__IDX__">
                                                            <option value="year">Year</option>
                                                            <option value="quarter">Quarter</option>
                                                            <option value="month">Month</option>
                                                            <option value="week">Week</option>
                                                            <option value="day">Day</option>
                                                        </select>
                                                    </div>

                                                    {{-- Baseline Period --}}
                                                    <div class="col-md-3">
                                                        <label class="form-label">Baseline Period</label>
                                                        <input type="text" name="indicators[__IDX__][baseline_year]"
                                                            class="form-control baseline-period" data-idx="__IDX__"
                                                            placeholder="e.g., 2026" >
                                                    </div>

                                                    {{-- Baseline Value --}}
                                                    <div class="col-md-3">
                                                        <label class="form-label">Baseline Value</label>
                                                        <div class="input-group">
                                                            <input type="number" step="0.01"
                                                                name="indicators[__IDX__][baseline_value]"
                                                                class="form-control baseline-value" data-idx="__IDX__"
                                                                placeholder="0.00">
                                                            <span class="input-group-text baseline-unit-label" data-idx="__IDX__">—</span>
                                                        </div>
                                                    </div>

                                                    {{-- Indicator Level --}}
                                                    <div class="col-md-3">
                                                        <label class="form-label">Indicator Level</label>
                                                        <select name="indicators[__IDX__][indicator_level_id]"
                                                            class="form-select">
                                                            <option value="">Select Level</option>
                                                            @foreach ($indicatorLevels as $level)
                                                                <option value="{{ $level->id }}">{{ $level->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Unit --}}
                                                    <div class="col-md-3">
                                                        <label class="form-label">Unit</label>
                                                        <select name="indicators[__IDX__][unit_id]" class="form-select">
                                                            <option value="">Select Unit</option>
                                                            @foreach ($indicatorUnits as $unit)
                                                                <option value="{{ $unit->id }}">
                                                                    {{ $unit->name }}{{ $unit->symbol ? ' (' . $unit->symbol . ')' : '' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Frequency of Reporting --}}
                                                    <div class="col-md-3">
                                                        <label class="form-label">Reporting Frequency</label>
                                                        <select name="indicators[__IDX__][frequency_of_reporting_id]"
                                                            class="form-select">
                                                            <option value="">Select Frequency</option>
                                                            @foreach ($reportingFrequencies as $freq)
                                                                <option value="{{ $freq->id }}">{{ $freq->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Methodology --}}
                                                    <div class="col-md-6">
                                                        <label class="form-label">Methodology</label>
                                                        <select name="indicators[__IDX__][methodology_id]" class="form-select">
                                                            <option value="">Select Methodology</option>
                                                            @foreach ($indicatorMethodologies as $meth)
                                                                <option value="{{ $meth->id }}">{{ $meth->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Responsible Party --}}
                                                    <div class="col-md-6">
                                                        <label class="form-label">Responsible Users</label>
                                                        <select name="indicators[__IDX__][responsible_user_ids][]" class="form-select checkbox-multiselect-target" multiple
                                                            data-type="responsible-users"
                                                            data-placeholder="Select responsible users...">
                                                            @foreach ($responsibleUsers as $user)
                                                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                                            @endforeach
                                                        </select>
                                                        <small class="text-muted">Select one or more users; they will receive reminder emails.</small>
                                                    </div>

                                                    {{-- Primary Source --}}
                                                    <div class="col-md-6">
                                                        <label class="form-label">Primary Source</label>
                                                        <div class="input-group">
                                                            <select name="indicators[__IDX__][primary_source_type]" class="form-select" style="max-width:150px;">
                                                                <option value="manual">Manual</option>
                                                                <option value="external">External System</option>
                                                                <option value="file">File Location</option>
                                                            </select>
                                                            <input type="text" name="indicators[__IDX__][primary_source_detail]"
                                                                class="form-control"
                                                                placeholder="API URL / file path / note">
                                                        </div>
                                                        <small class="text-muted">Specify how data arrives, matching the reporting frequency.</small>
                                                    </div>

                                                    {{-- Notes --}}
                                                    <div class="col-md-6">
                                                        <label class="form-label">Notes</label>
                                                        <textarea name="indicators[__IDX__][notes]" class="form-control" rows="2" placeholder="Additional notes"></textarea>
                                                    </div>

                                                    {{-- Definitions --}}
                                                    <div class="col-md-12">
                                                        <label class="form-label">Definition / Formula</label>
                                                        <select name="indicators[__IDX__][definition_id]" class="form-select mb-2">
                                                            <option value="">Select Definition</option>
                                                            @foreach ($indicatorDefinitions as $def)
                                                                <option value="{{ $def->id }}">{{ $def->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <textarea name="indicators[__IDX__][definitions]" class="form-control" rows="2"
                                                            placeholder="Override or add description (optional)"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                        </div>

                        <!-- DYNAMIC YEARLY ALLOCATIONS -->
                        <div id="allocationsContainer" class="mt-5" style="display:none;">
                            <h6 class="section-title mb-3">
                                Yearly Allocation (<span id="currencyLabel">--</span>)
                            </h6>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 140px;">Year</th>
                                            <th>Allocation <span id="allocationLabel">(Amount)</span></th>
                                        </tr>
                                    </thead>
                                    <tbody id="allocationTableBody"></tbody>
                                </table>
                            </div>

                            <div class="alert alert-info mt-3 d-flex align-items-center gap-2">
                                <i class="bi bi-pie-chart-fill text-primary"></i>
                                <div>
                                    Remaining: <strong id="remainingValue">0.00</strong>
                                    <span id="remainingCurrency">--</span>
                                    <span class="text-muted ms-2" id="remainingPercent"></span>
                                </div>
                            </div>
                        </div>

                        <!-- ACTIONS -->
                        <div class="mt-4 d-flex justify-content-end">
                            <a href="{{ route('budget.programs.index') }}" class="btn btn-light border me-2">Cancel</a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check2-circle me-1"></i> Save Program
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </main>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        /* DOM ELEMENTS */
        const startYear = document.getElementById('startYear');
        const endYear = document.getElementById('endYear');
        const totalYears = document.getElementById('totalYears');
        const container = document.getElementById('allocationsContainer');
        const body = document.getElementById('allocationTableBody');
        const totalBudget = document.getElementById('totalBudget');
        const remainingValue = document.getElementById('remainingValue');
        const currencySelect = document.getElementById('currencySelect');
        const currencyHidden = document.getElementById('currencyHidden');
        const remainingCurrency = document.getElementById('remainingCurrency');
        const currencyLabel = document.getElementById('currencyLabel');
        const modeSelect = document.getElementById('allocationMode');
        const programNameSelect = document.getElementById('programNameSelect');
        const allocationLabel = document.getElementById('allocationLabel');
        const remainingPercent = document.getElementById('remainingPercent');
        const indicatorUnits = @json($indicatorUnits);
        const indicatorPalette = ['#2563eb','#16a34a','#f59e0b','#ec4899','#0ea5e9','#ef4444','#10b981','#6366f1'];

        function updateCurrency(value) {
            if (!value) return;
            const exists = Array.from(currencySelect.options).some(opt => opt.value === value);
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = value;
                opt.textContent = value;
                currencySelect.appendChild(opt);
            }
            currencySelect.value = value;
            currencyHidden.value = value;
            currencyLabel.textContent = value;
            remainingCurrency.textContent = value;
        }

        /* Calculate total years from start + end */
        function calculateYears() {
            let s = parseInt(startYear.value);
            let e = parseInt(endYear.value);

            if (!s || !e || e < s) {
                container.style.display = "none";
                return;
            }

            let years = (e - s) + 1;
            totalYears.value = years;

            generateRows(s, e);
        }

        function applyFundingDefaults() {
            const selected = programNameSelect.options[programNameSelect.selectedIndex];
            if (!selected) return;

            const currency = selected.dataset.currency || '';
            const start = selected.dataset.startYear || '';
            const end = selected.dataset.endYear || '';
            const total = selected.dataset.totalBudget || '';

            updateCurrency(currency);
            startYear.value = start;
            endYear.value = end;
            totalBudget.value = total;

            calculateYears();
        }

        programNameSelect.addEventListener('change', applyFundingDefaults);

        // Baseline helpers
        function updateBaselinePlaceholder(idx, type) {
            const field = document.querySelector(`input[name="indicators[${idx}][baseline_year]"]`);
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
            const unitSelect = document.querySelector(`select[name="indicators[${idx}][unit_id]"]`);
            const badge = document.querySelector(`.baseline-unit-label[data-idx="${idx}"]`);
            if (!unitSelect || !badge) return;
            const selected = indicatorUnits.find(u => String(u.id) === String(unitSelect.value));
            badge.textContent = selected ? (selected.symbol ? selected.symbol : selected.name) : '—';
        }

        // delegate for dynamic rows
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('baseline-type')) {
                const idx = e.target.dataset.idx;
                updateBaselinePlaceholder(idx, e.target.value);
            }
            if (e.target.name?.includes('[unit_id]')) {
                const match = e.target.name.match(/indicators\[(.+?)\]\[unit_id\]/);
                if (match) updateBaselineUnit(match[1]);
            }
        });

        /* Generate allocation rows */
        function generateRows(start, end) {
            body.innerHTML = "";
            container.style.display = "block";

            for (let year = start; year <= end; year++) {
                body.innerHTML += `
            <tr>
                <td><strong>${year}</strong></td>
                <td>
                    <input type="number" class="form-control allocation-input"
                        name="allocations[${year}]"
                        step="0.01" min="0" value="0">
                </td>
            </tr>`;
            }

            document.querySelectorAll('.allocation-input')
                .forEach(inp => inp.addEventListener('input', calculateRemaining));

            calculateRemaining();
        }

        /* Calculate remaining balance */
        function calculateRemaining() {
            const budget = parseFloat(totalBudget.value) || 0;
            let total = 0;
            let totalPercent = 0;

            document.querySelectorAll('.allocation-input').forEach(input => {
                let val = parseFloat(input.value) || 0;

                if (modeSelect.value === "percentage") {
                    totalPercent += val;
                    val = budget * (val / 100);
                }
                total += val;
            });

            const remaining = budget - total;
            remainingValue.textContent = remaining.toFixed(2);
            remainingPercent.textContent = '';

            if (modeSelect.value === "percentage") {
                const remainingPct = 100 - totalPercent;
                remainingPercent.textContent = `(${remainingPct.toFixed(2)}%)`;
            }

            if (remaining < 0) {
                remainingValue.classList.add('text-danger');
            } else {
                remainingValue.classList.remove('text-danger');
            }
        }

        /* Change label for amount/percentage */
        modeSelect.addEventListener('change', () => {
            allocationLabel.textContent =
                modeSelect.value === "percentage" ? "Percentage (%)" : "Amount";
            calculateRemaining();
        });

        function toggleExpectedOutcomeFields() {
            const type = document.getElementById('expectedOutcomeType').value;
            const percentWrap = document.getElementById('expectedOutcomePercentageWrap');
            const textWrap = document.getElementById('expectedOutcomeTextWrap');

            percentWrap.style.display = type === 'percentage' ? 'block' : 'none';
            textWrap.style.display = type === 'text' ? 'block' : 'none';
        }

        document.getElementById('expectedOutcomeType').addEventListener('change', toggleExpectedOutcomeFields);
        toggleExpectedOutcomeFields();

        /* Indicators dynamic list */
        const addIndicatorBtn = document.getElementById('addIndicatorBtn');
        const indicatorsList = document.getElementById('indicatorsList');
        const indicatorTpl = document.getElementById('indicatorRowTpl').innerHTML;
        let indicatorIndex = 0;

        function renderIndicatorRow(data = {}) {
            const idx = indicatorIndex++;
            let html = indicatorTpl.replace(/__IDX__/g, idx).replace(/__NUM__/g, idx + 1);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;
            const stripeColor = indicatorPalette[idx % indicatorPalette.length];
            const card = wrapper.querySelector('.indicator-row');
            card.style.setProperty('--stripe', stripeColor);
            const chip = wrapper.querySelector('.indicator-chip');
            if (chip) {
                chip.style.background = `${stripeColor}1A`;
                chip.style.border = `1px solid ${stripeColor}`;
                chip.style.color = '#0f172a';
            }
            // fill values if provided
            if (data.name) wrapper.querySelector(`[name='indicators[${idx}][name]']`).value = data.name;

            // attach remove handler
            wrapper.querySelector('.remove-indicator').addEventListener('click', function() {
                wrapper.remove();
            });

            indicatorsList.appendChild(wrapper);
            if (typeof initMultiSelects === 'function') {
                initMultiSelects();
            } else if (typeof ensureMultiSelectLoaded === 'function') {
                ensureMultiSelectLoaded();
            }
        }

        addIndicatorBtn.addEventListener('click', () => renderIndicatorRow());

        // initialize one empty row
        renderIndicatorRow();

        applyFundingDefaults();

        // checkbox multi-select init (reuse Finance Program Funding behaviour)
        function initMultiSelects() {
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

        if (window.CheckboxMultiSelect) {
            initMultiSelects();
        }
    });
    </script>
    @endpush

@endsection
