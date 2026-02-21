@extends('layouts.app')
@section('title', 'Edit Program')

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
        .section-card { border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04); }
        .indicator-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; position: relative; }
        .indicator-card::before { content: ''; position: absolute; left: 0; top: 10px; bottom: 10px; width: 4px; border-radius: 20px; background: var(--stripe, #2563eb); }
        .indicator-chip { font-weight: 700; font-size: 13px; padding: 6px 10px; border-radius: 10px; color: #0f172a; background: #e0f2fe; }
        .checkbox-multiselect[data-type="responsible-users"] .checkbox-multiselect-toggle {
            border-color: #dbeafe;
            background: #eef2ff;
            color: #1e3a8a;
            box-shadow: 0 4px 12px rgba(59,130,246,0.12);
        }
        .checkbox-multiselect[data-type="responsible-users"] .selected-tag {
            background: #dbeafe;
            color: #1e3a8a;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 4px 8px;
            font-weight: 600;
        }
    </style>

    <main class="nxl-container">
        <div class="nxl-content">

            <div class="program-hero mb-4 d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-light text-primary">Budget · Programs</span>
                        <span class="badge bg-info text-dark">Edit</span>
                    </div>
                    <h4 class="mb-1">Edit Program</h4>
                    <p class="mb-0" style="opacity:0.9;">Update funding details and indicators.</p>
                </div>
                <a href="{{ route('budget.programs.index') }}" class="btn btn-light text-primary border-0 shadow-sm">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back
                </a>
            </div>

            <!-- Card -->
            <div class="card shadow-sm section-card">
                <div class="card-body">
                    <form action="{{ route('budget.programs.update', $program->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-4">
                            <!-- Sector -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sector <span class="text-danger">*</span></label>
                                <select name="sector_id" class="form-select" required>
                                    @foreach ($sectors as $sector)
                                        <option value="{{ $sector->id }}"
                                            {{ $program->sector_id == $sector->id ? 'selected' : '' }}>
                                            {{ $sector->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sector_id')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <!-- Program Name -->
                            <div class="col-md-6">
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
                                            @selected(old('program_name', $program->name) === $programName)>
                                            {{ $programName }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('program_name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <!-- Currency -->
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
                                <input type="hidden" name="currency" id="currencyHidden"
                                    value="{{ old('currency', $program->currency) }}">
                            </div>

                            <!-- Total Budget (read-only) -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Total Budget</label>
                                <input type="number" id="totalBudget" class="form-control" readonly>
                            </div>

                            <!-- Start Year -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Start Year <span class="text-danger">*</span></label>
                                <input type="number" name="start_year" id="startYear" class="form-control" min="1900"
                                    max="2100" required readonly value="{{ old('start_year', $program->start_year) }}">
                            </div>

                            <!-- End Year -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">End Year <span class="text-danger">*</span></label>
                                <input type="number" name="end_year" id="endYear" class="form-control" min="1900"
                                    max="2100" required readonly value="{{ old('end_year', $program->end_year) }}">
                            </div>

                            <!-- Description -->
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $program->description) }}</textarea>
                            </div>

                            <!-- EXPECTED OUTCOME -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Expected Outcome Type <span
                                        class="text-danger">*</span></label>
                                <select name="expected_outcome_type" id="expectedOutcomeType" class="form-select" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="percentage" @selected(old('expected_outcome_type', $program->expected_outcome_type) === 'percentage')>
                                        Percentage
                                    </option>
                                    <option value="text" @selected(old('expected_outcome_type', $program->expected_outcome_type) === 'text')>
                                        Text
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6" id="expectedOutcomePercentageWrap" style="display:none;">
                                <label class="form-label fw-semibold">Expected Outcome (%) <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="expected_outcome_percentage" id="expectedOutcomePercentage"
                                        class="form-control" min="0" max="100" step="0.01"
                                        value="{{ old('expected_outcome_percentage', $program->expected_outcome_type === 'percentage' ? $program->expected_outcome_value : '') }}"
                                        placeholder="0 - 100">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                            <div class="col-md-12" id="expectedOutcomeTextWrap" style="display:none;">
                                <label class="form-label fw-semibold">Expected Outcome (Text) <span
                                        class="text-danger">*</span></label>
                                <textarea name="expected_outcome_text" id="expectedOutcomeText" class="form-control" rows="2"
                                    placeholder="Describe the expected outcome">{{ old('expected_outcome_text', $program->expected_outcome_type === 'text' ? $program->expected_outcome_value : '') }}</textarea>
                            </div>
                        </div>

                        <!-- Indicators Section -->
                        <div class="col-12 mt-4">
                            <h6 class="fw-semibold mb-3">Indicators</h6>
                            <div id="indicatorsSection">
                                <button type="button" id="addIndicatorBtn" class="btn btn-sm btn-outline-primary mb-3">
                                    <i class="bi bi-plus-circle me-1"></i> Add Indicator
                                </button>
                                <div id="indicatorsList" class="row g-3"></div>
                                <template id="indicatorRowTpl">
                                    <div class="col-12">
                                        <div class="indicator-card indicator-row" style="--stripe:#2563eb;">
                                            <div class="d-flex align-items-center gap-2 mb-3">
                                                <span class="indicator-chip">Indicator #__NUM__</span>
                                            </div>
                                            <div class="row g-2">
                                                {{-- Indicator Name --}}
                                                <div class="col-md-6">
                                                    <label class="form-label">Indicator Name <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" class="form-control"
                                                        name="indicators[__IDX__][name]"
                                                        placeholder="e.g., Malaria Incidence Rate" required>
                                                </div>

                                                {{-- Baseline Type --}}
                                                <div class="col-md-3">
                                                    <label class="form-label">Baseline Type</label>
                                                    <select class="form-select baseline-type" name="indicators[__IDX__][baseline_type]" data-idx="__IDX__">
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
                                                    <input type="text" class="form-control baseline-period"
                                                        name="indicators[__IDX__][baseline_year]"
                                                        data-idx="__IDX__"
                                                        placeholder="e.g., 2026">
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
                                                    <select class="form-select"
                                                        name="indicators[__IDX__][indicator_level_id]">
                                                        <option value="">Select Level</option>
                                                        @foreach ($indicatorLevels as $level)
                                                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                {{-- Unit --}}
                                                <div class="col-md-3">
                                                    <label class="form-label">Unit</label>
                                                    <select class="form-select" name="indicators[__IDX__][unit_id]" data-idx="__IDX__">
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
                                                    <select class="form-select"
                                                        name="indicators[__IDX__][frequency_of_reporting_id]">
                                                        <option value="">Select Frequency</option>
                                                        @foreach ($reportingFrequencies as $freq)
                                                            <option value="{{ $freq->id }}">{{ $freq->name }}</option>
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

                                                {{-- Responsible Users --}}
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
                                                </div>

                                                {{-- Notes --}}
                                                <div class="col-md-6">
                                                    <label class="form-label">Notes</label>
                                                    <textarea class="form-control" name="indicators[__IDX__][notes]" rows="2" placeholder="Additional notes"></textarea>
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
                                                    <textarea class="form-control" name="indicators[__IDX__][definitions]" rows="2"
                                                        placeholder="Override or add description (optional)"></textarea>
                                                </div>

                                                {{-- Remove Button --}}
                                                <div class="col-md-12 text-end">
                                                    <button type="button" class="btn btn-sm btn-danger remove-indicator"
                                                        title="Remove indicator">
                                                        <i class="bi bi-trash"></i> Remove Indicator
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <a href="{{ route('budget.programs.index') }}" class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save2 me-1"></i> Update Program
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const programNameSelect = document.getElementById('programNameSelect');
        const currencySelect = document.getElementById('currencySelect');
        const currencyHidden = document.getElementById('currencyHidden');
        const startYear = document.getElementById('startYear');
        const endYear = document.getElementById('endYear');
        const totalBudget = document.getElementById('totalBudget');
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
        }
        programNameSelect.addEventListener('change', applyFundingDefaults);

        function toggleExpectedOutcomeFields() {
            const type = document.getElementById('expectedOutcomeType').value;
            const percentWrap = document.getElementById('expectedOutcomePercentageWrap');
            const textWrap = document.getElementById('expectedOutcomeTextWrap');
            percentWrap.style.display = type === 'percentage' ? 'block' : 'none';
            textWrap.style.display = type === 'text' ? 'block' : 'none';
        }
        document.getElementById('expectedOutcomeType').addEventListener('change', toggleExpectedOutcomeFields);
        toggleExpectedOutcomeFields();

        let indicatorIndex = 0;
        const indicatorsList = document.getElementById('indicatorsList');
        const addIndicatorBtn = document.getElementById('addIndicatorBtn');
        const indicatorRowTpl = document.getElementById('indicatorRowTpl').innerHTML;

        function updateBaselinePlaceholder(idx, type) {
            const field = document.querySelector(`input[name="indicators[${idx}][baseline_year]"]`);
            if (!field) return;
            switch (type) {
                case 'day': field.type = 'date'; field.placeholder = 'YYYY-MM-DD'; break;
                case 'month': field.type = 'month'; field.placeholder = 'YYYY-MM'; break;
                case 'quarter': field.type = 'text'; field.placeholder = 'YYYY-Q1'; break;
                case 'week': field.type = 'week'; field.placeholder = 'YYYY-W01'; break;
                default: field.type = 'number'; field.placeholder = 'YYYY';
            }
        }

        function renderIndicatorRow(indicator = null, index = null) {
            if (index === null) index = indicatorIndex++;
            const html = indicatorRowTpl.replace(/__IDX__/g, index).replace(/__NUM__/g, index + 1);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;
            const card = wrapper.querySelector('.indicator-row');

            const stripeColor = indicatorPalette[index % indicatorPalette.length];
            card.style.setProperty('--stripe', stripeColor);
            const chip = card.querySelector('.indicator-chip');
            if (chip) {
                chip.style.background = `${stripeColor}1A`;
                chip.style.border = `1px solid ${stripeColor}`;
                chip.style.color = '#0f172a';
            }

            const setVal = (sel, val) => {
                const el = card.querySelector(sel);
                if (el && val !== undefined && val !== null) el.value = val;
            };

            const ps = indicator?.primary_source || '';
            let psType = 'manual', psDetail = '';
            if (ps.includes(':')) [psType, psDetail] = ps.split(':', 2); else psDetail = ps;

            setVal('input[name*="[name]"]', indicator?.name);
            setVal('select[name*="[baseline_type]"]', indicator?.baseline_type || 'year');
            setVal('input[name*="[baseline_year]"]', indicator?.baseline_year);
            setVal('input[name*="[baseline_value]"]', indicator?.baseline_value);
            setVal('select[name*="[indicator_level_id]"]', indicator?.indicator_level_id);
            setVal('select[name*="[unit_id]"]', indicator?.unit_id);
            setVal('select[name*="[frequency_of_reporting_id]"]', indicator?.frequency_of_reporting_id);
            setVal('select[name*="[methodology_id]"]', null);
            setVal('select[name*="[definition_id]"]', null);
            setVal('select[name*="[primary_source_type]"]', psType);
            setVal('input[name*="[primary_source_detail]"]', psDetail);

            const notes = card.querySelector('textarea[name*="[notes]"]');
            if (notes && indicator?.notes) notes.value = indicator.notes;
            const defs = card.querySelector('textarea[name*="[definitions]"]');
            if (defs && indicator?.definitions) defs.value = indicator.definitions;

            const respSel = card.querySelector('.checkbox-multiselect-target');
            if (respSel) {
                const ids = Array.isArray(indicator?.responsible_user_ids) ? indicator.responsible_user_ids : [];
                ids.forEach(id => {
                    const opt = respSel.querySelector(`option[value="${id}"]`);
                    if (opt) opt.selected = true;
                });
            }

            // Unit badge
            const unitSelect = card.querySelector('select[name*="[unit_id]"]');
            const badge = card.querySelector('.baseline-unit-label');
            if (unitSelect && badge) {
                unitSelect.addEventListener('change', () => {
                    const selected = indicatorUnits.find(u => String(u.id) === String(unitSelect.value));
                    badge.textContent = selected ? (selected.symbol ? selected.symbol : selected.name) : '—';
                });
                const initSel = indicatorUnits.find(u => String(u.id) === String(indicator?.unit_id));
                badge.textContent = initSel ? (initSel.symbol ? initSel.symbol : initSel.name) : '—';
            }

            card.querySelectorAll('.baseline-type').forEach(sel => {
                sel.addEventListener('change', (e) => updateBaselinePlaceholder(index, e.target.value));
            });
            updateBaselinePlaceholder(index, indicator?.baseline_type || 'year');

            const removeBtn = card.querySelector('.remove-indicator');
            if (removeBtn) removeBtn.addEventListener('click', () => card.remove());

            if (window.CheckboxMultiSelect) {
                const msel = card.querySelector('.checkbox-multiselect-target');
                if (msel && !msel.dataset.enhanced) {
                    new CheckboxMultiSelect(msel, {
                        type: 'responsible-users',
                        placeholder: 'Select responsible users...',
                        searchPlaceholder: 'Type to search...',
                        showTags: true,
                        maxTagsVisible: 4
                    });
                    msel.dataset.enhanced = '1';
                }
            }

            return wrapper.firstElementChild;
        }

        addIndicatorBtn?.addEventListener('click', () => {
            indicatorsList.appendChild(renderIndicatorRow());
        });

        const existingIndicatorsRaw = @json($program->indicators ?? []);
        const existingIndicators = (existingIndicatorsRaw || []).map(ind => {
            let resp = [];
            if (ind.responsible_party) { try { resp = JSON.parse(ind.responsible_party) ?? []; } catch (e) {} }
            return { ...ind, responsible_user_ids: resp };
        });

        if (existingIndicators.length) {
            existingIndicators.forEach((indicator, idx) => indicatorsList.appendChild(renderIndicatorRow(indicator, idx)));
            indicatorIndex = existingIndicators.length;
        } else {
            indicatorsList.appendChild(renderIndicatorRow());
        }

        updateCurrency('{{ $program->currency }}');
        totalBudget.value = '{{ $program->total_budget }}';
        startYear.value = '{{ $program->start_year }}';
        endYear.value = '{{ $program->end_year }}';
    });
    </script>
@endsection
