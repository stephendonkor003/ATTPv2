@extends('layouts.app')
@section('title', 'Edit Project')

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
        .section-card { border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 8px 24px rgba(15,23,42,0.04); }
        .indicator-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; position: relative; }
        .indicator-card::before { content: ''; position: absolute; left: 0; top: 10px; bottom: 10px; width: 4px; border-radius: 20px; background: var(--stripe, #2563eb); }
        .indicator-chip { font-weight: 700; font-size: 13px; padding: 6px 10px; border-radius: 10px; color: #0f172a; background: #e0f2fe; }
    </style>

    <main class="nxl-container">
        <div class="nxl-content">

            <div class="program-hero mb-4 d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-light text-primary">Budget · Projects</span>
                        <span class="badge bg-info text-dark">Edit</span>
                    </div>
                    <h4 class="mb-1">Edit Project</h4>
                    <p class="mb-0" style="opacity:0.9;">Update project details and indicators.</p>
                </div>
                <a href="{{ route('budget.projects.index') }}" class="btn btn-light text-primary border-0 shadow-sm">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back to Projects
                </a>
            </div>

            <div class="card shadow-sm section-card">
                <div class="card-body">
                    <form action="{{ route('budget.projects.update', $project->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Project Name</label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $project->name) }}" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Total Budget (GHS)</label>
                                <input type="number" step="0.01" name="total_budget" class="form-control"
                                    value="{{ old('total_budget', $project->total_budget) }}" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Duration (Years)</label>
                                <input type="number" name="duration_years" class="form-control" min="1"
                                    max="10" value="{{ old('duration_years', $project->duration_years) }}" required>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $project->description) }}</textarea>
                            </div>

                            <!-- Program Indicators (Read-Only) -->
                            <div class="col-12 mt-4">
                                <h6 class="fw-semibold mb-3">Program Indicators</h6>
                                <div id="programIndicatorsSection">
                                    @if ($project->program && $project->program->indicators && $project->program->indicators->count() > 0)
                                        <div class="row g-2">
                                            @foreach ($project->program->indicators as $indicator)
                                                <div class="col-md-6">
                                                    <div class="p-3 border border-light rounded bg-light">
                                                        <p class="fw-semibold mb-0"><i
                                                                class="bi bi-bullseye me-2"></i>{{ $indicator->name }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-muted small">This program has no indicators.</p>
                                    @endif
                                </div>
                            </div>

                            <!-- Project Indicators Section -->
            <div class="col-12 mt-4">
                <h6 class="fw-semibold mb-3">Project Indicators</h6>
                <div id="indicatorsSection">
                    <button type="button" id="addIndicatorBtn" class="btn btn-sm btn-outline-primary mb-3">
                        <i class="bi bi-plus-circle me-1"></i> Add Indicator
                    </button>
                    <div id="indicatorsList" class="row g-3"></div>
                    <template id="indicatorRowTpl">
                        <div class="col-12">
                            <div class="indicator-card indicator-row" style="--stripe:#2563eb;">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="indicator-chip">Indicator #__NUM__</span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Indicator Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control"
                                            name="indicators[__IDX__][name]" placeholder="Enter indicator name"
                                            required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Baseline Type</label>
                    +#+                                        <select class="form-select baseline-type" name="indicators[__IDX__][baseline_type]" data-idx="__IDX__">
                                            <option value="year">Year</option>
                                            <option value="quarter">Quarter</option>
                                            <option value="month">Month</option>
                                            <option value="week">Week</option>
                                            <option value="day">Day</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Baseline Period</label>
                                        <input type="text" class="form-control baseline-period"
                                            name="indicators[__IDX__][baseline_year]"
                                            data-idx="__IDX__"
                                            placeholder="e.g., 2026">
                                    </div>
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
                                    <div class="col-md-6">
                                        <label class="form-label">Methodology</label>
                                        <input type="text" class="form-control"
                                            name="indicators[__IDX__][methodology]"
                                            placeholder="How is this indicator measured?">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Responsible Party</label>
                                        <input type="text" class="form-control"
                                            name="indicators[__IDX__][responsible_party]"
                                            placeholder="Who is responsible for reporting?">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Primary Source</label>
                                        <input type="text" class="form-control"
                                            name="indicators[__IDX__][primary_source]"
                                            placeholder="Where is the data sourced from?">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" name="indicators[__IDX__][notes]" rows="2" placeholder="Additional notes"></textarea>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Definitions</label>
                                        <textarea class="form-control" name="indicators[__IDX__][definitions]" rows="2"
                                            placeholder="Indicator definitions and terms"></textarea>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button type="button" class="btn btn-sm btn-danger remove-indicator"
                                            title="Remove indicator">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <a href="{{ route('budget.projects.index') }}"
                                class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save2 me-1"></i> Update Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const indicatorPalette = ['#2563eb','#16a34a','#f59e0b','#ec4899','#0ea5e9','#ef4444','#10b981','#6366f1'];
        const indicatorUnits = @json($indicatorUnits);
        let indicatorIndex = 0;
        const indicatorsList = document.getElementById('indicatorsList');
        const addIndicatorBtn = document.getElementById('addIndicatorBtn');
        const indicatorRowTpl = document.getElementById('indicatorRowTpl').innerHTML;

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
            }
            const setVal = (sel, val) => {
                const el = card.querySelector(sel);
                if (el && val !== undefined && val !== null) el.value = val;
            };
            setVal('input[name*="[name]"]', indicator?.name);
            setVal('select[name*="[baseline_type]"]', indicator?.baseline_type || 'year');
            setVal('input[name*="[baseline_year]"]', indicator?.baseline_year);
            setVal('input[name*="[baseline_value]"]', indicator?.baseline_value);
            setVal('select[name*="[indicator_level_id]"]', indicator?.indicator_level_id);
            setVal('select[name*="[unit_id]"]', indicator?.unit_id);
            setVal('select[name*="[frequency_of_reporting_id]"]', indicator?.frequency_of_reporting_id);
            setVal('input[name*="[methodology]"]', indicator?.methodology);
            setVal('input[name*="[responsible_party]"]', indicator?.responsible_party);
            setVal('input[name*="[primary_source]"]', indicator?.primary_source);
            const notes = card.querySelector('textarea[name*="[notes]"]');
            if (notes && indicator?.notes) notes.value = indicator.notes;
            const defs = card.querySelector('textarea[name*="[definitions]"]');
            if (defs && indicator?.definitions) defs.value = indicator.definitions;

            function updateBaselinePlaceholder(idx, type) {
                const field = card.querySelector(`input[name="indicators[${idx}][baseline_year]"]`);
                if (!field) return;
                switch (type) {
                    case 'day': field.type = 'date'; field.placeholder = 'YYYY-MM-DD'; break;
                    case 'month': field.type = 'month'; field.placeholder = 'YYYY-MM'; break;
                    case 'quarter': field.type = 'text'; field.placeholder = 'YYYY-Q1'; break;
                    case 'week': field.type = 'week'; field.placeholder = 'YYYY-W01'; break;
                    default: field.type = 'number'; field.placeholder = 'YYYY';
                }
            }
            card.querySelectorAll('.baseline-type').forEach(sel => {
                sel.addEventListener('change', (e) => updateBaselinePlaceholder(index, e.target.value));
            });
            updateBaselinePlaceholder(index, indicator?.baseline_type || 'year');

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

            card.querySelector('.remove-indicator')?.addEventListener('click', (e) => { e.preventDefault(); card.remove(); });
            return wrapper.firstElementChild;
        }

        addIndicatorBtn?.addEventListener('click', (e) => { e.preventDefault(); indicatorsList.appendChild(renderIndicatorRow()); });

        const existingIndicators = @json($project->indicators ?? []);
        if (existingIndicators && existingIndicators.length > 0) {
            existingIndicators.forEach((indicator, idx) => indicatorsList.appendChild(renderIndicatorRow(indicator, idx)));
            indicatorIndex = existingIndicators.length;
        } else {
            indicatorsList.appendChild(renderIndicatorRow());
        }
    });
    </script>
@endsection
