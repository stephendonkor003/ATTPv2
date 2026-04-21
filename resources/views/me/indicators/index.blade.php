@extends('layouts.app')
@section('title', 'Indicators')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/css/select2-custom.css') }}">
    <style>
        .form-helper {
            font-size: 0.82rem;
            color: #64748b;
        }

        .checkbox-multiselect[data-type="responsible-users"] .checkbox-multiselect-toggle {
            border-color: #bfdbfe;
            background: #eff6ff;
            color: #1e3a8a;
            box-shadow: 0 3px 10px rgba(59, 130, 246, 0.12);
        }

        .checkbox-multiselect[data-type="responsible-users"] .selected-tag {
            background: #dbeafe;
            color: #1e3a8a;
            border: 1px solid #93c5fd;
            font-weight: 600;
        }

        .checkbox-multiselect[data-type="responsible-users"] .checkbox-option.selected {
            background: #eff6ff;
        }

        .checkbox-multiselect[data-type="responsible-users"] .checkbox-option.selected .checkbox-custom {
            background: #2563eb;
            border-color: #1d4ed8;
        }

        .indicator-guide-hero {
            background: linear-gradient(130deg, #0f172a 0%, #1d4ed8 55%, #38bdf8 100%);
            border-radius: 16px;
            padding: 1.4rem;
            color: #f8fafc;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.2);
        }

        .indicator-guide-hero .badge {
            font-size: 0.72rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .indicator-guide-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            font-size: 0.75rem;
            margin: 0.2rem 0.4rem 0 0;
        }

        .indicator-guide-callout {
            background: rgba(15, 23, 42, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 0.9rem 1rem;
        }

        .indicator-guide-title {
            color: #0f172a;
            font-size: 0.95rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .indicator-flow {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .indicator-flow-step {
            position: relative;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 1rem;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
            min-height: 195px;
        }

        .indicator-flow-step::before {
            content: attr(data-step);
            position: absolute;
            top: -11px;
            left: 12px;
            background: #1d4ed8;
            color: #ffffff;
            border-radius: 999px;
            padding: 0.15rem 0.62rem;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .indicator-flow-step .flow-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 1rem;
            margin-bottom: 0.55rem;
        }

        .indicator-flow-step h6 {
            color: #0f172a;
            font-size: 0.95rem;
        }

        .indicator-flow-step p {
            color: #475569;
            font-size: 0.84rem;
            margin-bottom: 0.55rem;
        }

        .indicator-flow-step small {
            display: block;
            color: #0f172a;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 0.35rem 0.45rem;
            line-height: 1.25;
        }

        .indicator-guide-panel {
            border: 1px solid #dbeafe;
            border-radius: 12px;
            background: #f8fafc;
            padding: 1rem;
            height: 100%;
        }

        .indicator-guide-panel h6 {
            color: #0f172a;
            margin-bottom: 0.55rem;
        }

        .indicator-guide-list {
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }

        .indicator-guide-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            color: #334155;
            margin-bottom: 0.55rem;
            font-size: 0.84rem;
        }

        .indicator-guide-list li:last-child {
            margin-bottom: 0;
        }

        .indicator-guide-list li i {
            color: #2563eb;
            margin-top: 0.12rem;
        }

        .indicator-guide-format th,
        .indicator-guide-format td {
            font-size: 0.8rem;
            vertical-align: middle;
            white-space: nowrap;
        }

        .indicator-guide-actions .btn {
            min-width: 170px;
        }

        @media (min-width: 992px) {
            .indicator-flow-step:not(:last-child)::after {
                content: "";
                position: absolute;
                right: -14px;
                top: 50%;
                width: 24px;
                height: 24px;
                transform: translateY(-50%) rotate(45deg);
                background: #e2e8f0;
                border-top: 1px solid #cbd5e1;
                border-right: 1px solid #cbd5e1;
                z-index: 2;
            }
        }

        @media (max-width: 991.98px) {
            .indicator-flow {
                grid-template-columns: 1fr;
            }
        }

        .me-report-sheet {
            min-width: 2200px;
            font-size: 0.82rem;
        }

        .me-report-sheet thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #e2e8f0;
            color: #0f172a;
            border-bottom: 2px solid #94a3b8;
            white-space: nowrap;
        }

        .me-report-sheet td {
            white-space: nowrap;
            vertical-align: top;
        }

        .me-report-sheet td.wrap {
            white-space: normal;
            min-width: 210px;
        }

        .report-group-row td {
            font-weight: 700;
            border-top: 2px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }

        .report-group-program td {
            background: #dbeafe;
            color: #1e3a8a;
        }

        .report-group-project td {
            background: #dcfce7;
            color: #166534;
        }

        .report-group-activity td {
            background: #fef3c7;
            color: #92400e;
        }

        .report-group-subactivity td {
            background: #ede9fe;
            color: #5b21b6;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-target text-primary me-2"></i>
                    Indicators
                </h4>
                <p class="text-muted mb-0">Centralized M&E indicator management workspace.</p>
            </div>
            <a href="{{ route('budget.me.indicators.index', ['tab' => 'settings']) }}" class="btn btn-primary btn-sm">
                <i class="feather-plus me-1"></i> New Indicator
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @php
            $tabLabels = [
                'description' => 'Description',
                'settings' => 'Setting Indicators',
                'pictorial' => 'Pictorial View',
                'status' => 'Indicators Status',
            ];
            $activeTab = $tab ?? 'description';
            $totalIndicators = $statusSummary['total'] ?? 0;
        @endphp

        <ul class="nav nav-tabs mb-4">
            @foreach ($tabLabels as $key => $label)
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === $key ? 'active' : '' }}"
                        href="{{ route('budget.me.indicators.index', ['tab' => $key]) }}">
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($activeTab === 'description')
            <div class="card shadow-sm border-0 overflow-hidden">
                <div class="card-body p-4 p-lg-5">
                    <div class="indicator-guide-hero mb-4">
                        <div class="row g-3 align-items-center">
                            <div class="col-lg-8">
                                <span class="badge bg-light text-primary fw-semibold mb-2">M&E Quick Guide</span>
                                <h4 class="fw-bold mb-2">How To Use Indicators</h4>
                                <p class="mb-0">
                                    Build strong indicators once, then monitor progress consistently from Program to
                                    Sub-Activity. This page is the central workspace for setup, tracking, and management
                                    reporting.
                                </p>
                                <div class="mt-3">
                                    <span class="indicator-guide-chip"><i class="bi bi-link-45deg"></i> Program -> Project ->
                                        Activity -> Sub-Activity</span>
                                    <span class="indicator-guide-chip"><i class="bi bi-clipboard2-check"></i> Standardized
                                        indicator fields</span>
                                    <span class="indicator-guide-chip"><i class="bi bi-graph-up-arrow"></i> Status and
                                        management reporting</span>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="indicator-guide-callout">
                                    <p class="fw-semibold mb-1">Recommended Start</p>
                                    <p class="mb-0 small">
                                        Open the <strong>Setting Indicators</strong> tab, create your indicator, then use
                                        <strong>Indicators Status</strong> and <strong>Pictorial View</strong> to monitor and
                                        report to management.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="indicator-guide-title mb-3">Indicator Workflow</h6>
                    <div class="indicator-flow mb-4">
                        <div class="indicator-flow-step" data-step="Step 1">
                            <span class="flow-icon"><i class="bi bi-card-checklist"></i></span>
                            <h6 class="fw-semibold mb-2">Create Indicator Profile</h6>
                            <p class="mb-2">
                                Enter a clear name and select the owner level so the indicator sits in the correct
                                hierarchy.
                            </p>
                            <small>Fill: Indicator Name + Owner (Program/Project/Activity/Sub-Activity)</small>
                        </div>
                        <div class="indicator-flow-step" data-step="Step 2">
                            <span class="flow-icon"><i class="bi bi-rulers"></i></span>
                            <h6 class="fw-semibold mb-2">Set Measurement Rules</h6>
                            <p class="mb-2">
                                Choose baseline type, period format, unit, level, and reporting frequency to standardize
                                measurement.
                            </p>
                            <small>Keep unit and baseline aligned, for example 6 kg or Yes/No for binary units.</small>
                        </div>
                        <div class="indicator-flow-step" data-step="Step 3">
                            <span class="flow-icon"><i class="bi bi-people"></i></span>
                            <h6 class="fw-semibold mb-2">Assign Accountability</h6>
                            <p class="mb-2">
                                Select responsible party/person and specify methodology plus verified primary source.
                            </p>
                            <small>Use multi-select users so reporting responsibility is clear and auditable.</small>
                        </div>
                        <div class="indicator-flow-step" data-step="Step 4">
                            <span class="flow-icon"><i class="bi bi-bar-chart-line"></i></span>
                            <h6 class="fw-semibold mb-2">Track and Report</h6>
                            <p class="mb-2">
                                Compare target versus actual values, review achievement status, and export management
                                sheets.
                            </p>
                            <small>Use search, Excel export, and landscape PDF for executive reporting.</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-lg-6">
                            <div class="indicator-guide-panel">
                                <h6 class="fw-semibold">Complete These Fields For Every Indicator</h6>
                                <ul class="indicator-guide-list">
                                    <li><i class="bi bi-check2-circle"></i> Owner, Level, Frequency, Unit, and Baseline
                                        values must define how the indicator is measured.</li>
                                    <li><i class="bi bi-check2-circle"></i> Responsible Party/Person should include all users
                                        who provide or verify data.</li>
                                    <li><i class="bi bi-check2-circle"></i> Methodology and Primary Source must be consistent
                                        with your data collection plan.</li>
                                    <li><i class="bi bi-check2-circle"></i> Definition can be selected from the table or added
                                        as a custom definition when needed.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="indicator-guide-panel">
                                <h6 class="fw-semibold">Baseline Period Format Guide</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 indicator-guide-format">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Baseline Type</th>
                                                <th>Required Format</th>
                                                <th>Example</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Year</td>
                                                <td>YYYY</td>
                                                <td>2026</td>
                                            </tr>
                                            <tr>
                                                <td>Quarter</td>
                                                <td>YYYY-Q1..Q4</td>
                                                <td>2026-Q2</td>
                                            </tr>
                                            <tr>
                                                <td>Month</td>
                                                <td>YYYY-MM</td>
                                                <td>2026-08</td>
                                            </tr>
                                            <tr>
                                                <td>Week</td>
                                                <td>YYYY-W01..W53</td>
                                                <td>2026-W14</td>
                                            </tr>
                                            <tr>
                                                <td>Day</td>
                                                <td>YYYY-MM-DD</td>
                                                <td>2026-08-17</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 indicator-guide-actions">
                        <a href="{{ route('budget.me.indicators.index', ['tab' => 'settings']) }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-sliders me-1"></i> Open Setting Indicators
                        </a>
                        <a href="{{ route('budget.me.indicators.index', ['tab' => 'pictorial']) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-grid-3x3-gap me-1"></i> Open Pictorial View
                        </a>
                        <a href="{{ route('budget.me.indicators.index', ['tab' => 'status']) }}" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-activity me-1"></i> Open Indicators Status
                        </a>
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        <strong>Reminder:</strong> Indicator setup and edits are centralized on this M&E Indicators page to
                        keep standards consistent across all programs and projects.
                    </div>
                </div>
            </div>
        @endif

        @if ($activeTab === 'settings')
            @php
                $isEditing = !is_null($editingIndicator);
                $formAction = $isEditing
                    ? route('budget.me.indicators.update', $editingIndicator)
                    : route('budget.me.indicators.store');
                $ownerReferenceValue = old('owner_reference', $editingOwnerReference);
                $selectedResponsibleUserIds = collect(old('responsible_user_ids', $editingResponsibleUserIds ?? []))
                    ->map(fn($id) => (string) $id)
                    ->all();

                $methodologyValue = (string) old('methodology', $editingIndicator->methodology ?? '');
                $methodologyExists = $methodologies->contains(fn($m) => (string) $m->name === $methodologyValue);

                $primarySourceTypeValue = old('primary_source_type', $editingPrimarySourceType);
                $primarySourceValue = old('primary_source_value', $editingPrimarySourceValue);

                $definitionIdValue = (string) old('definition_id', $editingDefinitionId ?? '');
                $definitionCustomValue = old('definition_custom', $editingDefinitionCustom);

                $baselineValueRaw = (string) old('baseline_value', $editingIndicator->baseline_value ?? '');
                $binaryBaselineSelection = $baselineValueRaw === '1' ? '1' : ($baselineValueRaw === '0' ? '0' : '');

                $indicatorSurveyConfig = (array) ($editingIndicator->survey_config ?? []);
                $indicatorSurveyEnabled = old('survey_public_enabled', data_get($indicatorSurveyConfig, 'enabled', true));
                $indicatorSurveyTitle = old('survey_title', data_get($indicatorSurveyConfig, 'title', ($editingIndicator->name ?? 'Public Survey')));
                $indicatorSurveyIntro = old('survey_intro', data_get($indicatorSurveyConfig, 'intro', ''));
                $indicatorSurveyQuestionsJson = old('survey_questions_json', json_encode(data_get($indicatorSurveyConfig, 'questions', [])));
            @endphp

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-0">
                            <h6 class="mb-0 fw-semibold">{{ $isEditing ? 'Edit Indicator' : 'Create Indicator' }}</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ $formAction }}" data-indicator-settings-form>
                                @csrf
                                @if ($isEditing)
                                    @method('PUT')
                                @endif

                                <div class="mb-3">
                                    <label class="form-label">Indicator Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control"
                                        value="{{ old('name', $editingIndicator->name ?? '') }}"
                                        placeholder="e.g., Malaria incidence rate" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Owner (Program / Project / Activity / Sub-Activity)</label>
                                    <select name="owner_reference" class="form-select">
                                        <option value="">Unlinked</option>

                                        <optgroup label="Programs">
                                            @foreach ($programs as $program)
                                                @php $value = 'program:' . $program->id; @endphp
                                                <option value="{{ $value }}" @selected($ownerReferenceValue === $value)>
                                                    {{ $program->program_id }} - {{ $program->name }}
                                                </option>
                                            @endforeach
                                        </optgroup>

                                        <optgroup label="Projects">
                                            @foreach ($projects as $project)
                                                @php $value = 'project:' . $project->id; @endphp
                                                <option value="{{ $value }}" @selected($ownerReferenceValue === $value)>
                                                    {{ $project->project_id }} - {{ $project->name }}
                                                </option>
                                            @endforeach
                                        </optgroup>

                                        <optgroup label="Activities">
                                            @foreach ($activities as $activity)
                                                @php $value = 'activity:' . $activity->id; @endphp
                                                <option value="{{ $value }}" @selected($ownerReferenceValue === $value)>
                                                    {{ $activity->name }}
                                                    @if ($activity->project)
                                                        - Project: {{ $activity->project->name }}
                                                    @endif
                                                </option>
                                            @endforeach
                                        </optgroup>

                                        <optgroup label="Sub-Activities">
                                            @foreach ($subActivities as $subActivity)
                                                @php $value = 'sub_activity:' . $subActivity->id; @endphp
                                                <option value="{{ $value }}" @selected($ownerReferenceValue === $value)>
                                                    {{ $subActivity->name }}
                                                    @if ($subActivity->activity)
                                                        - Activity: {{ $subActivity->activity->name }}
                                                    @endif
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Baseline Type</label>
                                        @php $baselineType = old('baseline_type', $editingIndicator->baseline_type ?? 'year'); @endphp
                                        <select name="baseline_type" id="baselineTypeSelect" class="form-select">
                                            <option value="year" @selected($baselineType === 'year')>Year</option>
                                            <option value="quarter" @selected($baselineType === 'quarter')>Quarter</option>
                                            <option value="month" @selected($baselineType === 'month')>Month</option>
                                            <option value="week" @selected($baselineType === 'week')>Week</option>
                                            <option value="day" @selected($baselineType === 'day')>Day</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Baseline Period</label>
                                        <input type="text" name="baseline_year" id="baselinePeriodInput"
                                            class="form-control"
                                            value="{{ old('baseline_year', $editingIndicator->baseline_year ?? '') }}"
                                            placeholder="YYYY">
                                        <small id="baselinePeriodHint" class="form-helper">Format changes automatically by
                                            Baseline Type.</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Indicator Level</label>
                                        <select name="indicator_level_id" class="form-select">
                                            <option value="">Select Level</option>
                                            @foreach ($levels as $level)
                                                <option value="{{ $level->id }}" @selected((string) old('indicator_level_id', $editingIndicator->indicator_level_id ?? '') === (string) $level->id)>
                                                    {{ $level->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Reporting Frequency</label>
                                        <select name="frequency_of_reporting_id" class="form-select">
                                            <option value="">Select Frequency</option>
                                            @foreach ($frequencies as $frequency)
                                                <option value="{{ $frequency->id }}" @selected((string) old('frequency_of_reporting_id', $editingIndicator->frequency_of_reporting_id ?? '') === (string) $frequency->id)>
                                                    {{ $frequency->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Unit</label>
                                        <select name="unit_id" id="unitSelect" class="form-select">
                                            <option value="">Select Unit</option>
                                            @foreach ($units as $unit)
                                                <option value="{{ $unit->id }}"
                                                    data-symbol="{{ $unit->symbol ?? '' }}" @selected((string) old('unit_id', $editingIndicator->unit_id ?? '') === (string) $unit->id)>
                                                    {{ $unit->name }}{{ $unit->symbol ? ' (' . $unit->symbol . ')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Baseline Value</label>
                                        <div id="baselineValueNumericWrap">
                                            <div class="input-group">
                                                <input type="number" step="0.01" id="baselineValueNumber"
                                                    name="baseline_value" class="form-control"
                                                    value="{{ $baselineValueRaw }}" placeholder="0.00">
                                                <span class="input-group-text" id="baselineValueUnitAddon">Unit</span>
                                            </div>
                                        </div>
                                        <div id="baselineValueBinaryWrap" class="d-none">
                                            <select id="baselineValueBinary" class="form-select">
                                                <option value="">Select Yes/No</option>
                                                <option value="1" @selected($binaryBaselineSelection === '1')>Yes</option>
                                                <option value="0" @selected($binaryBaselineSelection === '0')>No</option>
                                            </select>
                                            <small class="form-helper">Binary unit detected. Baseline value uses
                                                Yes/No.</small>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">Responsible Party/Person</label>
                                        <select id="responsibleUsersSelect" name="responsible_user_ids[]"
                                            class="form-select checkbox-multiselect-target" multiple
                                            data-type="responsible-users"
                                            data-placeholder="Select responsible party/person...">
                                            @foreach ($users as $user)
                                                <option value="{{ $user->id }}" @selected(in_array((string) $user->id, $selectedResponsibleUserIds, true))>
                                                    {{ $user->name }}{{ $user->email ? ' (' . $user->email . ')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="form-helper">Select one or more users responsible for
                                            reporting.</small>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">Methodology</label>
                                        <select name="methodology" id="methodologySelect" class="form-select">
                                            <option value="">Select Methodology</option>
                                            @if ($methodologyValue !== '' && !$methodologyExists)
                                                <option value="{{ $methodologyValue }}" selected>
                                                    {{ $methodologyValue }} (Current)
                                                </option>
                                            @endif
                                            @foreach ($methodologies as $methodology)
                                                <option value="{{ $methodology->name }}" @selected($methodologyValue === (string) $methodology->name)>
                                                    {{ $methodology->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Survey design guidance (visible when methodology contains "survey") --}}
                                    <div class="col-12 {{ str_contains(strtolower($methodologyValue), 'survey') ? '' : 'd-none' }}" id="indicatorSurveyBlock">
                                        <div class="border rounded-4 p-3 bg-light-subtle">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                                <div>
                                                    <div class="fw-semibold mb-1">Public Survey Linked To Methodology</div>
                                                    <small class="text-muted">
                                                        This indicator can publish a public survey, but the survey sections, questions, skip logic,
                                                        and intro message are now designed from the methodology record so one survey definition stays consistent.
                                                    </small>
                                                </div>
                                            </div>

                                            <div class="row g-3 align-items-center">
                                                <div class="col-md-8">
                                                    <div class="small text-muted">
                                                        After saving the indicator, use the survey link action to generate the public URL, QR code,
                                                        and response collection flow for this survey-enabled methodology.
                                                    </div>
                                                </div>
                                                <div class="col-md-4 text-md-end">
                                                    <a href="{{ route('budget.me-configuration.methodologies.index') }}" class="btn btn-sm btn-outline-primary">
                                                        <i class="feather-settings me-1"></i> Manage Methodologies
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">Primary Source Type</label>
                                        <select name="primary_source_type" id="primarySourceType" class="form-select">
                                            <option value="">Select Source Type</option>
                                            <option value="file_location" @selected($primarySourceTypeValue === 'file_location')>File Location
                                            </option>
                                            <option value="link" @selected($primarySourceTypeValue === 'link')>Link</option>
                                            <option value="external_system_connector" @selected($primarySourceTypeValue === 'external_system_connector')>
                                                External System Connector
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">Primary Source Value</label>
                                        <input type="text" name="primary_source_value" id="primarySourceValue"
                                            class="form-control" value="{{ $primarySourceValue }}"
                                            placeholder="Provide source details">
                                        <small id="primarySourceHint" class="form-helper">Choose source type to get
                                            guidance.</small>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="border rounded p-3 bg-light-subtle">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                <div>
                                                    <div class="fw-semibold">Data Source Bridge Template</div>
                                                    <small class="text-muted">
                                                        Download a ready file structure for cross-server data sync. Fill it
                                                        and place/expose it at the configured Primary Source Value.
                                                    </small>
                                                </div>
                                                @php
                                                    $templateBaseHref =
                                                        $isEditing && $editingIndicator
                                                            ? route('budget.me.data-sources.template.download', $editingIndicator)
                                                            : route('budget.me.data-sources.template.generic');

                                                    $templateHref =
                                                        $isEditing && $editingIndicator
                                                            ? route('budget.me.data-sources.template.download', [
                                                                'indicator' => $editingIndicator,
                                                                'source_type' => $primarySourceTypeValue,
                                                                'source_value' => $primarySourceValue,
                                                            ])
                                                            : route('budget.me.data-sources.template.generic', [
                                                                'source_type' => $primarySourceTypeValue,
                                                                'source_value' => $primarySourceValue,
                                                            ]);
                                                @endphp
                                                <a id="dataSourceTemplateBtn" data-base-href="{{ $templateBaseHref }}"
                                                    href="{{ $templateHref }}" class="btn btn-outline-primary btn-sm">
                                                    <i class="feather-download me-1"></i> Download Template
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">Definition From Table</label>
                                        <select name="definition_id" id="definitionSelect" class="form-select">
                                            <option value="">Select Definition</option>
                                            @foreach ($definitions as $definition)
                                                <option value="{{ $definition->id }}" @selected($definitionIdValue === (string) $definition->id)>
                                                    {{ $definition->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">Definition (Custom)</label>
                                        <textarea name="definition_custom" id="definitionCustom" class="form-control" rows="2"
                                            placeholder="Type custom definition (optional)">{{ $definitionCustomValue }}</textarea>
                                        <small id="definitionHint" class="form-helper">
                                            Select a table definition or type your own custom definition.
                                        </small>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Notes</label>
                                        <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes">{{ old('notes', $editingIndicator->notes ?? '') }}</textarea>
                                    </div>
                                </div>

                                <div class="mt-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="feather-save me-1"></i>
                                        {{ $isEditing ? 'Update Indicator' : 'Save Indicator' }}
                                    </button>
                                    @if ($isEditing)
                                        <a href="{{ route('budget.me.indicators.index', ['tab' => 'settings']) }}"
                                            class="btn btn-light border btn-sm">Cancel Edit</a>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card shadow-sm border-0" id="survey-indicators">
                        <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-semibold">Existing Indicators</h6>
                            <span class="badge bg-light text-dark">{{ $indicators->total() }} total</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Owner</th>
                                            <th>Level</th>
                                            <th>Frequency</th>
                                            <th>Survey Link</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($indicators as $indicator)
                                            @php
                                                $ownerLabel = 'Unlinked';
                                                if ($indicator->indicatorable_type === \App\Models\Program::class) {
                                                    $ownerLabel =
                                                        'Program: ' . ($indicator->indicatorable->name ?? 'Unknown');
                                                } elseif (
                                                    $indicator->indicatorable_type === \App\Models\Project::class
                                                ) {
                                                    $ownerLabel =
                                                        'Project: ' . ($indicator->indicatorable->name ?? 'Unknown');
                                                } elseif (
                                                    $indicator->indicatorable_type === \App\Models\Activity::class
                                                ) {
                                                    $ownerLabel =
                                                        'Activity: ' . ($indicator->indicatorable->name ?? 'Unknown');
                                                } elseif (
                                                    $indicator->indicatorable_type === \App\Models\SubActivity::class
                                                ) {
                                                    $ownerLabel =
                                                        'Sub-Activity: ' .
                                                        ($indicator->indicatorable->name ?? 'Unknown');
                                                }
                                                $surveyState = $surveyStatusByIndicatorId[$indicator->id] ?? [
                                                    'is_survey' => false,
                                                    'has_link' => false,
                                                    'public_url' => null,
                                                ];
                                                $surveyQrUrl = $surveyState['public_url']
                                                    ? \App\Support\MeSurvey::qrCodeUrl($surveyState['public_url'])
                                                    : null;
                                            @endphp
                                            <tr>
                                                <td class="fw-semibold">{{ $indicator->name }}</td>
                                                <td>{{ $ownerLabel }}</td>
                                                <td>{{ $indicator->level->name ?? 'Unassigned' }}</td>
                                                <td>{{ $indicator->frequency->name ?? 'Unassigned' }}</td>
                                                <td>
                                                    @if ($surveyState['is_survey'])
                                                        <div class="d-flex flex-column gap-1">
                                                            @if ($surveyState['has_link'] && $surveyState['public_url'])
                                                                <div class="d-flex flex-wrap gap-1">
                                                                    <a href="{{ $surveyState['public_url'] }}"
                                                                        class="btn btn-sm btn-outline-primary" target="_blank">
                                                                        <i class="bi bi-box-arrow-up-right me-1"></i> Open
                                                                    </a>
                                                                    <button type="button"
                                                                        class="btn btn-sm btn-outline-secondary js-copy-survey-link"
                                                                        data-link="{{ $surveyState['public_url'] }}">
                                                                        <i class="bi bi-clipboard me-1"></i> Copy
                                                                    </button>
                                                                    <button type="button"
                                                                        class="btn btn-sm btn-outline-dark js-open-survey-qr"
                                                                        data-link="{{ $surveyState['public_url'] }}"
                                                                        data-qr="{{ $surveyQrUrl }}"
                                                                        data-title="{{ $indicator->name }}">
                                                                        <i class="bi bi-qr-code me-1"></i> QR
                                                                    </button>
                                                                    <a href="{{ route('budget.me.indicators.survey-responses', $indicator) }}"
                                                                        class="btn btn-sm btn-outline-dark">
                                                                        <i class="bi bi-table me-1"></i> Responses
                                                                    </a>
                                                                </div>
                                                                <small class="text-muted">
                                                                    {{ $indicator->survey_responses_count }} response{{ $indicator->survey_responses_count === 1 ? '' : 's' }}
                                                                </small>
                                                            @else
                                                                <form method="POST"
                                                                    action="{{ route('budget.me.indicators.survey-link.generate', $indicator) }}"
                                                                    class="d-inline">
                                                                    @csrf
                                                                    <button type="submit"
                                                                        class="btn btn-sm btn-outline-primary">
                                                                        <i class="bi bi-link-45deg me-1"></i> Generate Link
                                                                    </button>
                                                                </form>
                                                                <small class="text-muted d-block">No public link yet.</small>
                                                                @if ($indicator->survey_responses_count > 0)
                                                                    <a href="{{ route('budget.me.indicators.survey-responses', $indicator) }}"
                                                                        class="btn btn-sm btn-outline-dark mt-1">
                                                                        <i class="bi bi-table me-1"></i> Responses
                                                                    </a>
                                                                @endif
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="text-muted">N/A</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex gap-1">
                                                        <a href="{{ route('budget.me.indicators.index', ['tab' => 'settings', 'edit' => $indicator->id]) }}"
                                                            class="btn btn-sm btn-outline-primary" title="Edit">
                                                            <i class="feather-edit-2"></i>
                                                        </a>
                                                        <form method="POST"
                                                            action="{{ route('budget.me.indicators.destroy', $indicator) }}"
                                                            onsubmit="return confirm('Delete this indicator?');"
                                                            class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                title="Delete">
                                                                <i class="feather-trash-2"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">No indicators
                                                    configured yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if ($indicators->hasPages())
                                <div class="mt-3">
                                    {{ $indicators->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($activeTab === 'pictorial')
            <div class="row g-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Indicators</p>
                            <h4 class="mb-0">{{ $statusSummary['total'] }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Achieved</p>
                            <h4 class="mb-0 text-success">{{ $statusSummary['achieved'] }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">On Track</p>
                            <h4 class="mb-0 text-primary">{{ $statusSummary['on_track'] }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Behind</p>
                            <h4 class="mb-0 text-danger">{{ $statusSummary['behind'] }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0 fw-semibold">Distribution By Level</h6>
                        </div>
                        <div class="card-body">
                            @forelse ($levelBreakdown as $levelName => $count)
                                @php
                                    $percent = $totalIndicators > 0 ? round(($count / $totalIndicators) * 100, 1) : 0;
                                @endphp
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>{{ $levelName }}</span>
                                        <span>{{ $count }} ({{ $percent }}%)</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar"
                                            style="width: {{ $percent }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No level distribution data available.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0 fw-semibold">Distribution By Ownership</h6>
                        </div>
                        <div class="card-body">
                            @forelse ($ownershipBreakdown as $ownerType => $count)
                                @php
                                    $percent = $totalIndicators > 0 ? round(($count / $totalIndicators) * 100, 1) : 0;
                                @endphp
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>{{ $ownerType }}</span>
                                        <span>{{ $count }} ({{ $percent }}%)</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-info" role="progressbar"
                                            style="width: {{ $percent }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No ownership data available.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div
                            class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h6 class="mb-0 fw-semibold">M&E Management Report Sheet</h6>
                                <small class="text-muted">
                                    Grouped hierarchy: Program -> Project -> Activity -> Sub-Activity
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                <a id="meReportExcelBtn"
                                    data-base-href="{{ route('budget.me.indicators.report.excel') }}"
                                    href="{{ route('budget.me.indicators.report.excel', ['q' => request('q')]) }}"
                                    class="btn btn-success btn-sm">
                                    <i class="bi bi-file-earmark-excel me-1"></i> Excel
                                </a>
                                <a id="meReportPdfBtn" data-base-href="{{ route('budget.me.indicators.report.pdf') }}"
                                    href="{{ route('budget.me.indicators.report.pdf', ['q' => request('q')]) }}"
                                    class="btn btn-danger btn-sm">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                                </a>
                            </div>
                        </div>
                        <div class="card-body pt-3">
                            <div class="row g-2 mb-3 align-items-center">
                                <div class="col-lg-12">
                                    <label for="managementReportSearch" class="form-label mb-1 fw-semibold">Search Report
                                        Sheet</label>
                                    <input type="text" id="managementReportSearch"
                                        class="form-control form-control-sm"
                                        placeholder="Search program, project, activity, indicator, responsible person, status..."
                                        value="{{ request('q') }}">
                                </div>
                                <div class="col-lg-6 text-lg-end">
                                    <small class="text-muted" id="managementReportCount"></small>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="managementReportTable"
                                    class="table table-bordered table-sm me-report-sheet align-middle">
                                    <thead>
                                        <tr>
                                            <th>Program</th>
                                            <th>Project</th>
                                            <th>Activity</th>
                                            <th>Sub-Activity</th>
                                            <th>Indicator</th>
                                            <th>Owner Type</th>
                                            <th>Level</th>
                                            <th>Frequency</th>
                                            <th>Baseline Type</th>
                                            <th>Baseline Period</th>
                                            <th>Baseline Value</th>
                                            <th>Responsible Party/Person</th>
                                            <th>Methodology</th>
                                            <th>Primary Source Type</th>
                                            <th>Primary Source Value</th>
                                            <th>Definition</th>
                                            <th>Target</th>
                                            <th>Actual</th>
                                            <th>Achievement</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $lastProgramKey = null;
                                            $lastProjectKey = null;
                                            $lastActivityKey = null;
                                            $lastSubActivityKey = null;
                                        @endphp

                                        @forelse ($managementReportRows as $row)
                                            @if ($row['program_key'] !== $lastProgramKey)
                                                <tr class="report-group-row report-group-program">
                                                    <td colspan="21">
                                                        Program Group: {{ $row['program'] }}
                                                    </td>
                                                </tr>
                                                @php
                                                    $lastProgramKey = $row['program_key'];
                                                    $lastProjectKey = null;
                                                    $lastActivityKey = null;
                                                    $lastSubActivityKey = null;
                                                @endphp
                                            @endif

                                            @if ($row['project'] !== '—' && $row['project_key'] !== $lastProjectKey)
                                                <tr class="report-group-row report-group-project">
                                                    <td colspan="21">
                                                        Project: {{ $row['project'] }}
                                                    </td>
                                                </tr>
                                                @php
                                                    $lastProjectKey = $row['project_key'];
                                                    $lastActivityKey = null;
                                                    $lastSubActivityKey = null;
                                                @endphp
                                            @endif

                                            @if ($row['activity'] !== '—' && $row['activity_key'] !== $lastActivityKey)
                                                <tr class="report-group-row report-group-activity">
                                                    <td colspan="21">
                                                        Activity: {{ $row['activity'] }}
                                                    </td>
                                                </tr>
                                                @php
                                                    $lastActivityKey = $row['activity_key'];
                                                    $lastSubActivityKey = null;
                                                @endphp
                                            @endif

                                            @if ($row['sub_activity'] !== '—' && $row['sub_activity_key'] !== $lastSubActivityKey)
                                                <tr class="report-group-row report-group-subactivity">
                                                    <td colspan="21">
                                                        Sub-Activity: {{ $row['sub_activity'] }}
                                                    </td>
                                                </tr>
                                                @php
                                                    $lastSubActivityKey = $row['sub_activity_key'];
                                                @endphp
                                            @endif

                                            <tr class="report-data-row">
                                                <td>{{ $row['program'] }}</td>
                                                <td>{{ $row['project'] }}</td>
                                                <td>{{ $row['activity'] }}</td>
                                                <td>{{ $row['sub_activity'] }}</td>
                                                <td class="fw-semibold wrap">{{ $row['indicator_name'] }}</td>
                                                <td>{{ $row['owner_type'] }}</td>
                                                <td>{{ $row['indicator_level'] }}</td>
                                                <td>{{ $row['frequency'] }}</td>
                                                <td>{{ $row['baseline_type'] }}</td>
                                                <td>{{ $row['baseline_period'] }}</td>
                                                <td>{{ $row['baseline_value'] }}</td>
                                                <td class="wrap">{{ $row['responsible'] }}</td>
                                                <td class="wrap">{{ $row['methodology'] }}</td>
                                                <td>{{ $row['primary_source_type'] }}</td>
                                                <td class="wrap">{{ $row['primary_source_value'] }}</td>
                                                <td class="wrap">{{ $row['definition'] }}</td>
                                                <td>{{ $row['target'] }}</td>
                                                <td>{{ $row['actual'] }}</td>
                                                <td>{{ $row['achievement'] }}</td>
                                                <td>
                                                    <span
                                                        class="badge bg-{{ $row['status_class'] }}">{{ $row['status'] }}</span>
                                                </td>
                                                <td class="wrap">{{ $row['notes'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="21" class="text-center text-muted py-4">
                                                    No indicators available for the management report sheet.
                                                </td>
                                            </tr>
                                        @endforelse
                                        <tr id="managementReportNoResults" class="d-none">
                                            <td colspan="21" class="text-center text-muted py-4">
                                                No matching records for your search.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($activeTab === 'status')
            <div class="row g-4 mb-3">
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-3">
                            <small class="text-muted d-block">Achieved</small>
                            <strong class="text-success">{{ $statusSummary['achieved'] }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-3">
                            <small class="text-muted d-block">On Track</small>
                            <strong class="text-primary">{{ $statusSummary['on_track'] }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-3">
                            <small class="text-muted d-block">Behind</small>
                            <strong class="text-danger">{{ $statusSummary['behind'] }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-3">
                            <small class="text-muted d-block">Pending</small>
                            <strong class="text-warning">{{ $statusSummary['pending'] }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-3">
                            <small class="text-muted d-block">Not Started</small>
                            <strong class="text-secondary">{{ $statusSummary['not_started'] }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-3">
                            <small class="text-muted d-block">Reported / No Target</small>
                            <strong class="text-info">{{ $statusSummary['reported_without_target'] }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="mb-0 fw-semibold">Indicator Status Register</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Owner</th>
                                    <th>Level</th>
                                    <th>Target</th>
                                    <th>Actual</th>
                                    <th>Achievement</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($statusRows as $row)
                                    <tr>
                                        <td class="fw-semibold">{{ $row['name'] }}</td>
                                        <td>{{ $row['owner'] }}</td>
                                        <td>{{ $row['level'] }}</td>
                                        <td>{{ $row['target'] ?? '—' }}</td>
                                        <td>{{ $row['actual'] ?? '—' }}</td>
                                        <td>{{ isset($row['achievement']) ? $row['achievement'] . '%' : '—' }}</td>
                                        <td>
                                            <span
                                                class="badge bg-{{ $row['status_class'] }}">{{ $row['status'] }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No indicator status records
                                            available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
    <div class="modal fade" id="indicatorSurveyQrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="indicatorSurveyQrModalTitle">Survey QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="Survey QR code" id="indicatorSurveyQrModalImage" class="img-fluid rounded border p-2 bg-white">
                    <div class="small text-muted mt-3" id="indicatorSurveyQrModalLink"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary js-copy-indicator-survey-qr-link">Copy Link</button>
                    <button type="button" class="btn btn-primary js-download-indicator-survey-qr">Download QR</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('admin/assets/js/checkbox-multiselect.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('form[data-indicator-settings-form]');
            if (!form) {
                return;
            }

            const baselineTypeSelect = document.getElementById('baselineTypeSelect');
            const baselinePeriodInput = document.getElementById('baselinePeriodInput');
            const baselinePeriodHint = document.getElementById('baselinePeriodHint');

            const unitSelect = document.getElementById('unitSelect');
            const baselineValueNumericWrap = document.getElementById('baselineValueNumericWrap');
            const baselineValueBinaryWrap = document.getElementById('baselineValueBinaryWrap');
            const baselineValueNumber = document.getElementById('baselineValueNumber');
            const baselineValueBinary = document.getElementById('baselineValueBinary');
            const baselineValueUnitAddon = document.getElementById('baselineValueUnitAddon');

            const primarySourceType = document.getElementById('primarySourceType');
            const primarySourceValue = document.getElementById('primarySourceValue');
            const primarySourceHint = document.getElementById('primarySourceHint');
            const dataSourceTemplateBtn = document.getElementById('dataSourceTemplateBtn');

            const definitionSelect = document.getElementById('definitionSelect');
            const definitionCustom = document.getElementById('definitionCustom');
            const definitionHint = document.getElementById('definitionHint');
            const methodologySelect = document.getElementById('methodologySelect');

            // Survey builder elements
            const surveyBlock = document.getElementById('indicatorSurveyBlock');
            const surveyEnabled = document.getElementById('indicatorSurveyEnabled');
            const surveyPanel = document.getElementById('indicatorSurveyPanel');
            const surveyTitle = document.getElementById('indicatorSurveyTitle');
            const surveyIntro = document.getElementById('indicatorSurveyIntro');
            const questionsJsonInput = document.getElementById('indicatorSurveyQuestionsJson');
            const questionsContainer = document.getElementById('indicatorSurveyQuestionsContainer');
            const addQuestionBtn = document.getElementById('addIndicatorSurveyQuestionBtn');

            let surveyQuestions = [];
            try {
                const parsed = JSON.parse(questionsJsonInput?.value || '[]');
                if (Array.isArray(parsed)) {
                    surveyQuestions = parsed;
                }
            } catch (_) {
                surveyQuestions = [];
            }

            const questionTypes = [
                {value: 'text', label: 'Text'},
                {value: 'textarea', label: 'Long Text'},
                {value: 'number', label: 'Number'},
                {value: 'email', label: 'Email'},
                {value: 'date', label: 'Date'},
                {value: 'select', label: 'Dropdown'},
                {value: 'radio', label: 'Single Choice'},
                {value: 'checkbox', label: 'Multi Choice'},
            ];

            function normalizeOptions(raw) {
                return (raw || [])
                    .map((item) => (item || '').toString().trim())
                    .filter((item, index, array) => item !== '' && array.indexOf(item) === index);
            }

            function escapeHtml(value) {
                return (value || '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function renderSurveyQuestions() {
                if (!questionsContainer) return;
                questionsContainer.innerHTML = '';
                if (!surveyQuestions.length) {
                    const empty = document.createElement('div');
                    empty.className = 'text-muted small border rounded p-3 bg-white';
                    empty.textContent = 'No questions yet. Add at least one question.';
                    questionsContainer.appendChild(empty);
                    return;
                }
                surveyQuestions.forEach((q, idx) => {
                    const card = document.createElement('div');
                    card.className = 'survey-question-card';
                    const optionBlockVisible = ['select', 'radio', 'checkbox'].includes(q.type);
                    const optionsText = (q.options || []).join('\\n');
                    card.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Question ${idx + 1}</strong>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-remove="${idx}">
                                <i class="feather-trash-2"></i>
                            </button>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Question Label</label>
                                <input type="text" class="form-control form-control-sm" data-label="${idx}" value="${escapeHtml(q.label || '')}" placeholder="Shown to respondents">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Input Type</label>
                                <select class="form-select form-select-sm" data-type="${idx}">
                                    ${questionTypes.map((type) => `<option value="${type.value}" ${type.value === q.type ? 'selected' : ''}>${type.label}</option>`).join('')}
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label d-block">Required</label>
                                <div class="form-check form-switch mt-1">
                                    <input class="form-check-input" type="checkbox" data-required="${idx}" ${q.required ? 'checked' : ''}>
                                    <label class="form-check-label">Mandatory</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Help Text (Optional)</label>
                                <input type="text" class="form-control form-control-sm" data-hint="${idx}" value="${escapeHtml(q.hint || '')}" placeholder="Helper text under the question">
                            </div>
                            <div class="col-12 ${optionBlockVisible ? '' : 'd-none'}" data-options-wrap="${idx}">
                                <label class="form-label">Options (one per line)</label>
                                <textarea class="form-control form-control-sm" rows="3" data-options="${idx}" placeholder="Option 1&#10;Option 2">${escapeHtml(optionsText)}</textarea>
                            </div>
                        </div>
                    `;
                    questionsContainer.appendChild(card);
                });
            }

            function syncSurveyQuestionsFromDom() {
                if (!questionsContainer) return;
                surveyQuestions = surveyQuestions.map((q, idx) => {
                    const label = form.querySelector(`[data-label="${idx}"]`)?.value || '';
                    const type = form.querySelector(`[data-type="${idx}"]`)?.value || 'text';
                    const required = !!form.querySelector(`[data-required="${idx}"]`)?.checked;
                    const hint = form.querySelector(`[data-hint="${idx}"]`)?.value || '';
                    const optionsRaw = (form.querySelector(`[data-options="${idx}"]`)?.value || '').split('\\n');
                    return {
                        label: label.trim(),
                        type: type.trim(),
                        required,
                        hint: hint.trim(),
                        options: ['select', 'radio', 'checkbox'].includes(type) ? normalizeOptions(optionsRaw) : [],
                    };
                });
                if (questionsJsonInput) {
                    questionsJsonInput.value = JSON.stringify(surveyQuestions);
                }
            }

            function applySurveyVisibility() {
                const isSurvey = (methodologySelect?.value || '').toLowerCase().includes('survey');
                if (!surveyBlock) return;

                if (!isSurvey) {
                    surveyBlock.classList.add('d-none');
                    if (questionsJsonInput) questionsJsonInput.value = '[]';
                    surveyQuestions = [];
                    renderSurveyQuestions();
                    if (surveyEnabled) surveyEnabled.checked = false;
                    if (surveyPanel) surveyPanel.classList.add('d-none');
                    return;
                }

                surveyBlock.classList.remove('d-none');
                if (surveyEnabled && surveyEnabled.checked) {
                    surveyPanel?.classList.remove('d-none');
                    if (surveyQuestions.length === 0) {
                        surveyQuestions.push({label: '', type: 'text', required: true, hint: '', options: []});
                    }
                    renderSurveyQuestions();
                    syncSurveyQuestionsFromDom();
                } else {
                    surveyPanel?.classList.add('d-none');
                }
            }

            addQuestionBtn?.addEventListener('click', () => {
                surveyQuestions.push({label: '', type: 'text', required: true, hint: '', options: []});
                renderSurveyQuestions();
                syncSurveyQuestionsFromDom();
            });

            questionsContainer?.addEventListener('input', (event) => {
                const target = event.target;
                if (target?.matches('[data-type]')) {
                    const idx = Number(target.getAttribute('data-type'));
                    const wrap = questionsContainer.querySelector(`[data-options-wrap="${idx}"]`);
                    if (wrap) {
                        wrap.classList.toggle('d-none', !['select', 'radio', 'checkbox'].includes(target.value));
                    }
                }
                syncSurveyQuestionsFromDom();
            });

            questionsContainer?.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-remove]');
                if (!trigger) return;
                const idx = Number(trigger.getAttribute('data-remove'));
                surveyQuestions.splice(idx, 1);
                renderSurveyQuestions();
                syncSurveyQuestionsFromDom();
            });

            surveyEnabled?.addEventListener('change', () => {
                if (!surveyPanel) return;
                const on = surveyEnabled.checked;
                surveyPanel.classList.toggle('d-none', !on);
                if (on && surveyQuestions.length === 0) {
                    surveyQuestions.push({label: '', type: 'text', required: true, hint: '', options: []});
                }
                renderSurveyQuestions();
                syncSurveyQuestionsFromDom();
            });

            methodologySelect?.addEventListener('change', () => {
                applySurveyVisibility();
            });

            document.querySelectorAll('.checkbox-multiselect-target').forEach((select, index) => {
                if (select.dataset.enhanced === '1' || !window.CheckboxMultiSelect) {
                    return;
                }

                if (!select.id) {
                    select.id = `indicator-multiselect-${index + 1}`;
                }

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

            const baselineConfigs = {
                year: {
                    inputType: 'number',
                    placeholder: 'YYYY',
                    hint: 'Use a 4-digit year, e.g., 2026.',
                    pattern: /^\d{4}$/
                },
                quarter: {
                    inputType: 'text',
                    placeholder: 'YYYY-Q1',
                    hint: 'Use quarter format, e.g., 2026-Q3.',
                    pattern: /^\d{4}-Q[1-4]$/
                },
                month: {
                    inputType: 'month',
                    placeholder: 'YYYY-MM',
                    hint: 'Use month format, e.g., 2026-03.',
                    pattern: /^\d{4}-(0[1-9]|1[0-2])$/
                },
                week: {
                    inputType: 'week',
                    placeholder: 'YYYY-W01',
                    hint: 'Use ISO week format, e.g., 2026-W09.',
                    pattern: /^\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$/
                },
                day: {
                    inputType: 'date',
                    placeholder: 'YYYY-MM-DD',
                    hint: 'Use date format, e.g., 2026-03-15.',
                    pattern: /^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/
                }
            };

            function applyBaselineTypeMode() {
                const type = baselineTypeSelect.value || 'year';
                const config = baselineConfigs[type] || baselineConfigs.year;

                baselinePeriodInput.type = config.inputType;
                baselinePeriodInput.placeholder = config.placeholder;
                baselinePeriodHint.textContent = config.hint;

                if (type === 'year') {
                    baselinePeriodInput.min = '1900';
                    baselinePeriodInput.max = '2100';
                    baselinePeriodInput.step = '1';
                } else {
                    baselinePeriodInput.removeAttribute('min');
                    baselinePeriodInput.removeAttribute('max');
                    baselinePeriodInput.removeAttribute('step');
                }

                validateBaselinePeriod();
            }

            function validateBaselinePeriod() {
                const type = baselineTypeSelect.value || 'year';
                const config = baselineConfigs[type] || baselineConfigs.year;
                const value = (baselinePeriodInput.value || '').trim();

                baselinePeriodInput.setCustomValidity('');
                if (value === '') {
                    return;
                }

                if (!config.pattern.test(value)) {
                    baselinePeriodInput.setCustomValidity(
                        'Baseline period format is invalid for the selected baseline type.');
                }
            }

            function getUnitLabel(option) {
                if (!option || !option.value) {
                    return 'Unit';
                }

                const symbol = (option.dataset.symbol || '').trim();
                if (symbol !== '') {
                    return symbol;
                }

                const label = option.textContent || '';
                const match = label.match(/\(([^)]+)\)/);
                if (match && match[1]) {
                    return match[1].trim();
                }

                return label.trim();
            }

            function unitLooksBinary(option) {
                if (!option || !option.value) {
                    return false;
                }

                const unitText = `${option.textContent || ''} ${option.dataset.symbol || ''}`.toLowerCase();
                return /(binary|yes\/no|yes-no|boolean|bool)/.test(unitText);
            }

            function toggleBaselineValueInput() {
                const selectedOption = unitSelect.options[unitSelect.selectedIndex];
                const isBinary = unitLooksBinary(selectedOption);
                baselineValueUnitAddon.textContent = getUnitLabel(selectedOption);

                if (isBinary) {
                    baselineValueNumericWrap.classList.add('d-none');
                    baselineValueBinaryWrap.classList.remove('d-none');

                    baselineValueNumber.removeAttribute('name');
                    baselineValueBinary.setAttribute('name', 'baseline_value');

                    if (baselineValueBinary.value === '' && baselineValueNumber.value !== '') {
                        baselineValueBinary.value = parseFloat(baselineValueNumber.value) > 0 ? '1' : '0';
                    }
                } else {
                    baselineValueNumericWrap.classList.remove('d-none');
                    baselineValueBinaryWrap.classList.add('d-none');

                    baselineValueBinary.removeAttribute('name');
                    baselineValueNumber.setAttribute('name', 'baseline_value');

                    if (baselineValueBinary.value !== '') {
                        baselineValueNumber.value = baselineValueBinary.value;
                    }
                }
            }

            function updatePrimarySourceUI() {
                const sourceType = primarySourceType.value;
                const configs = {
                    file_location: {
                        placeholder: 'e.g., C:\\reports\\baseline.xlsx or /mnt/data/baseline.csv',
                        type: 'text',
                        hint: 'Provide the exact file location or storage path.'
                    },
                    link: {
                        placeholder: 'e.g., https://example.org/api/indicator-data',
                        type: 'url',
                        hint: 'Provide a valid URL link.'
                    },
                    external_system_connector: {
                        placeholder: 'e.g., DHIS2 connector: /api/dataValueSets',
                        type: 'text',
                        hint: 'Provide connector/system endpoint details.'
                    }
                };

                if (!sourceType || !configs[sourceType]) {
                    primarySourceValue.type = 'text';
                    primarySourceValue.placeholder = 'Provide source details';
                    primarySourceHint.textContent = 'Choose source type to get guidance.';
                    updateDataSourceTemplateLink();
                    return;
                }

                primarySourceValue.type = configs[sourceType].type;
                primarySourceValue.placeholder = configs[sourceType].placeholder;
                primarySourceHint.textContent = configs[sourceType].hint;
                updateDataSourceTemplateLink();
            }

            function updateDataSourceTemplateLink() {
                if (!dataSourceTemplateBtn) {
                    return;
                }

                const baseHref = dataSourceTemplateBtn.dataset.baseHref || dataSourceTemplateBtn.getAttribute('href');
                if (!baseHref) {
                    return;
                }

                const url = new URL(baseHref, window.location.origin);
                const sourceType = (primarySourceType.value || '').trim();
                const sourceValue = (primarySourceValue.value || '').trim();

                if (sourceType !== '') {
                    url.searchParams.set('source_type', sourceType);
                } else {
                    url.searchParams.delete('source_type');
                }

                if (sourceValue !== '') {
                    url.searchParams.set('source_value', sourceValue);
                } else {
                    url.searchParams.delete('source_value');
                }

                dataSourceTemplateBtn.setAttribute('href', `${url.pathname}${url.search}`);
            }

            function updateDefinitionHint() {
                const selectedOption = definitionSelect.options[definitionSelect.selectedIndex];
                const hasSelection = !!(selectedOption && selectedOption.value);

                if (hasSelection) {
                    definitionHint.textContent =
                        `Selected table definition: ${selectedOption.textContent.trim()}. Custom text overrides this selection.`;
                    return;
                }

                definitionHint.textContent = 'Select a table definition or type your own custom definition.';
            }

            baselineTypeSelect.addEventListener('change', applyBaselineTypeMode);
            baselinePeriodInput.addEventListener('input', validateBaselinePeriod);
            baselinePeriodInput.addEventListener('blur', validateBaselinePeriod);

            unitSelect.addEventListener('change', toggleBaselineValueInput);
            baselineValueBinary.addEventListener('change', () => {
                if (!baselineValueNumericWrap.classList.contains('d-none')) {
                    return;
                }
                baselineValueNumber.value = baselineValueBinary.value;
            });

            primarySourceType.addEventListener('change', updatePrimarySourceUI);
            primarySourceValue.addEventListener('input', updateDataSourceTemplateLink);
            definitionSelect.addEventListener('change', updateDefinitionHint);
            definitionCustom.addEventListener('input', updateDefinitionHint);

            applyBaselineTypeMode();
            toggleBaselineValueInput();
            updatePrimarySourceUI();
            updateDataSourceTemplateLink();
            updateDefinitionHint();

            // Init survey block
            applySurveyVisibility();
            renderSurveyQuestions();
        });
    </script>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('managementReportSearch');
            const table = document.getElementById('managementReportTable');
            if (!searchInput || !table) {
                return;
            }

            const tbody = table.querySelector('tbody');
            if (!tbody) {
                return;
            }

            const allRows = Array.from(tbody.querySelectorAll('tr'));
            const groupRows = allRows.filter((row) => row.classList.contains('report-group-row'));
            const dataRows = allRows.filter((row) => row.classList.contains('report-data-row'));
            const noResultsRow = document.getElementById('managementReportNoResults');
            const countLabel = document.getElementById('managementReportCount');
            const exportExcelBtn = document.getElementById('meReportExcelBtn');
            const exportPdfBtn = document.getElementById('meReportPdfBtn');

            const normalize = (value) => (value || '').toString().toLowerCase().trim();

            function updateExportLinks(searchTerm) {
                [exportExcelBtn, exportPdfBtn].forEach((button) => {
                    if (!button) {
                        return;
                    }

                    const baseHref = button.dataset.baseHref || button.getAttribute('href');
                    if (!baseHref) {
                        return;
                    }

                    const url = new URL(baseHref, window.location.origin);
                    if (searchTerm) {
                        url.searchParams.set('q', searchTerm);
                    } else {
                        url.searchParams.delete('q');
                    }

                    button.setAttribute('href', `${url.pathname}${url.search}`);
                });
            }

            function applyReportFilter() {
                const term = normalize(searchInput.value);
                let visibleCount = 0;

                groupRows.forEach((row) => {
                    row.style.display = term === '' ? '' : 'none';
                });

                dataRows.forEach((row) => {
                    const matches = term === '' || normalize(row.textContent).includes(term);
                    row.style.display = matches ? '' : 'none';
                    if (matches) {
                        visibleCount++;
                    }
                });

                if (term !== '') {
                    dataRows.forEach((row) => {
                        if (row.style.display === 'none') {
                            return;
                        }

                        let previous = row.previousElementSibling;
                        while (previous) {
                            if (previous.classList.contains('report-group-row')) {
                                previous.style.display = '';
                            }
                            previous = previous.previousElementSibling;
                        }
                    });
                }

                if (noResultsRow) {
                    noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
                }

                if (countLabel) {
                    countLabel.textContent = `${visibleCount} result${visibleCount === 1 ? '' : 's'}`;
                }

                updateExportLinks(term);
            }

            searchInput.addEventListener('input', applyReportFilter);
            applyReportFilter();
        });
    </script>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const copyButtons = Array.from(document.querySelectorAll('.js-copy-survey-link'));
            if (!copyButtons.length) {
                return;
            }

            copyButtons.forEach((button) => {
                button.addEventListener('click', async () => {
                    const link = button.dataset.link || '';
                    if (!link) {
                        return;
                    }

                    const defaultLabel = button.innerHTML;
                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(link);
                        } else {
                            const textarea = document.createElement('textarea');
                            textarea.value = link;
                            textarea.style.position = 'fixed';
                            textarea.style.opacity = '0';
                            document.body.appendChild(textarea);
                            textarea.focus();
                            textarea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textarea);
                        }

                        button.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied';
                        setTimeout(() => {
                            button.innerHTML = defaultLabel;
                        }, 1800);
                    } catch (error) {
                        button.innerHTML = '<i class="bi bi-x-circle me-1"></i>Copy failed';
                        setTimeout(() => {
                            button.innerHTML = defaultLabel;
                        }, 1800);
                    }
                });
            });

            const surveyQrModalElement = document.getElementById('indicatorSurveyQrModal');
            const surveyQrModal = surveyQrModalElement && window.bootstrap ? new bootstrap.Modal(surveyQrModalElement) : null;
            const surveyQrImage = document.getElementById('indicatorSurveyQrModalImage');
            const surveyQrTitle = document.getElementById('indicatorSurveyQrModalTitle');
            const surveyQrLink = document.getElementById('indicatorSurveyQrModalLink');
            const surveyQrState = { link: '', qr: '', title: 'Survey QR Code' };

            document.querySelectorAll('.js-open-survey-qr').forEach((button) => {
                button.addEventListener('click', () => {
                    surveyQrState.link = button.dataset.link || '';
                    surveyQrState.qr = button.dataset.qr || '';
                    surveyQrState.title = button.dataset.title || 'Survey QR Code';

                    if (surveyQrTitle) surveyQrTitle.textContent = surveyQrState.title;
                    if (surveyQrImage) surveyQrImage.src = surveyQrState.qr;
                    if (surveyQrLink) surveyQrLink.textContent = surveyQrState.link;

                    surveyQrModal?.show();
                });
            });

            document.querySelector('.js-copy-indicator-survey-qr-link')?.addEventListener('click', async () => {
                if (!surveyQrState.link) return;
                try {
                    await navigator.clipboard.writeText(surveyQrState.link);
                } catch (error) {
                    window.prompt('Copy survey link:', surveyQrState.link);
                }
            });

            document.querySelector('.js-download-indicator-survey-qr')?.addEventListener('click', async () => {
                if (!surveyQrState.qr) return;
                try {
                    const response = await fetch(surveyQrState.qr);
                    const blob = await response.blob();
                    const objectUrl = URL.createObjectURL(blob);
                    const anchor = document.createElement('a');
                    anchor.href = objectUrl;
                    anchor.download = `${(surveyQrState.title || 'survey-qr').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`;
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                    URL.revokeObjectURL(objectUrl);
                } catch (error) {
                    window.open(surveyQrState.qr, '_blank', 'noopener');
                }
            });
        });
    </script>
@endpush
