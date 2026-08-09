@extends('layouts.app')

@section('title', 'Create Performance Report')
@section('lean_admin_scripts', '1')

@push('styles')
    <style>
        .me-report-create {
            --report-green: #0b5c45;
            --report-ink: #173c31;
            --report-muted: #64756f;
            --report-border: #dce8e3;
            max-width: 1380px;
            margin: 0 auto;
        }

        .me-report-create .report-hero {
            position: relative;
            overflow: hidden;
            padding: 1.5rem;
            border-radius: 1rem;
            color: #fff;
            background: linear-gradient(120deg, #073f30, #0b6d50);
            box-shadow: 0 16px 35px rgba(7, 63, 48, .16);
        }

        .me-report-create .report-hero::after {
            position: absolute;
            top: -75px;
            right: -45px;
            width: 210px;
            height: 210px;
            border: 32px solid rgba(255, 255, 255, .08);
            border-radius: 50%;
            content: "";
        }

        .me-report-create .report-hero p {
            max-width: 720px;
            margin: .45rem 0 0;
            color: rgba(255, 255, 255, .78);
        }

        .me-report-create .report-card {
            border: 1px solid var(--report-border);
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 10px 25px rgba(28, 65, 53, .06);
        }

        .me-report-create .report-step {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            margin-right: .45rem;
            border-radius: 50%;
            color: #fff;
            background: var(--report-green);
            font-size: .78rem;
            font-weight: 800;
        }

        .me-report-create .form-label {
            color: var(--report-ink);
            font-weight: 750;
        }

        .me-report-create .form-select,
        .me-report-create .form-control {
            min-height: 46px;
            border-color: #cfddd7;
            border-radius: .7rem;
        }

        .me-report-create .form-select:focus,
        .me-report-create .form-control:focus {
            border-color: #2f8b6d;
            box-shadow: 0 0 0 .2rem rgba(47, 139, 109, .13);
        }

        .me-report-create .template-preview {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            padding: 1rem;
            border: 1px solid #dbe9e3;
            border-radius: .85rem;
            background: #f6fbf8;
        }

        .me-report-create .preview-item small {
            display: block;
            margin-bottom: .18rem;
            color: var(--report-muted);
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .me-report-create .preview-item strong {
            display: block;
            color: var(--report-ink);
            line-height: 1.4;
        }

        .me-report-create .frequency-list {
            margin-top: .75rem;
            padding-top: .75rem;
            border-top: 1px solid var(--report-border);
            color: #496258;
            font-size: .8rem;
        }

        .me-report-create .report-guide {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
            margin-bottom: 1rem;
        }

        .me-report-create .guide-step {
            display: flex;
            gap: .6rem;
            align-items: flex-start;
            padding: .75rem;
            border: 1px solid var(--report-border);
            border-radius: .75rem;
            background: #fbfdfc;
        }

        .me-report-create .guide-step span {
            display: grid;
            flex: 0 0 auto;
            width: 1.65rem;
            height: 1.65rem;
            place-items: center;
            border-radius: 50%;
            color: #fff;
            background: var(--report-green);
            font-size: .68rem;
            font-weight: 850;
        }

        .me-report-create .guide-step strong,
        .me-report-create .guide-step small {
            display: block;
        }

        .me-report-create .guide-step strong {
            color: var(--report-ink);
            font-size: .74rem;
        }

        .me-report-create .guide-step small {
            margin-top: .15rem;
            color: var(--report-muted);
            font-size: .66rem;
            line-height: 1.4;
        }

        .me-report-create .preview-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
            margin-top: 1rem;
            padding: .35rem;
            border: 1px solid var(--report-border);
            border-radius: .75rem;
            background: #f4f8f6;
        }

        .me-report-create .preview-tab {
            padding: .55rem .75rem;
            border: 0;
            border-radius: .55rem;
            color: #536a61;
            background: transparent;
            font-size: .72rem;
            font-weight: 800;
        }

        .me-report-create .preview-tab.is-active {
            color: #fff;
            background: var(--report-green);
            box-shadow: 0 5px 12px rgba(11, 92, 69, .16);
        }

        .me-report-create .preview-panel {
            display: none;
            margin-top: .8rem;
            padding: 1rem;
            border: 1px solid var(--report-border);
            border-radius: .85rem;
            background: #fff;
        }

        .me-report-create .preview-panel.is-active {
            display: block;
        }

        .me-report-create .preview-panel-head {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .8rem;
        }

        .me-report-create .preview-panel-head h5 {
            margin: 0;
            color: var(--report-ink);
            font-size: .88rem;
            font-weight: 850;
        }

        .me-report-create .preview-panel-head p {
            margin: .2rem 0 0;
            color: var(--report-muted);
            font-size: .7rem;
        }

        .me-report-create .indicator-plan-table {
            width: 100%;
            table-layout: fixed;
            font-size: .7rem;
        }

        .me-report-create .indicator-plan-table th {
            color: #52685f;
            background: #f3f8f5;
            font-size: .62rem;
            letter-spacing: .035em;
            text-transform: uppercase;
        }

        .me-report-create .indicator-plan-table th,
        .me-report-create .indicator-plan-table td {
            padding: .65rem;
            border: 1px solid #e1ebe6;
            vertical-align: top;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .me-report-create .indicator-plan-table .indicator-title {
            display: block;
            color: var(--report-ink);
            font-weight: 800;
        }

        .me-report-create .indicator-plan-table .indicator-code {
            display: inline-block;
            margin-bottom: .25rem;
            color: var(--report-green);
            font-size: .64rem;
            font-weight: 850;
        }

        .me-report-create .scope-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .me-report-create .scope-field {
            min-width: 0;
            padding: .7rem;
            border: 1px solid #dce8e2;
            border-radius: .7rem;
            background: #fbfdfc;
        }

        .me-report-create .scope-field.is-required {
            border-color: #e2ad43;
            background: #fffaf0;
        }

        .me-report-create .scope-requirement {
            float: right;
            color: #64756f;
            font-size: .58rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .me-report-create .scope-field.is-required .scope-requirement {
            color: #945d05;
        }

        .me-report-create .scope-note {
            display: flex;
            gap: .6rem;
            align-items: flex-start;
            margin-top: .8rem;
            padding: .75rem;
            border-radius: .7rem;
            color: #285d49;
            background: #edf8f3;
            font-size: .7rem;
            line-height: 1.5;
        }

        .me-report-create .report-section-map {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .55rem;
        }

        .me-report-create .section-map-item {
            display: flex;
            gap: .6rem;
            padding: .65rem;
            border: 1px solid #e0eae5;
            border-radius: .65rem;
            background: #fbfdfc;
        }

        .me-report-create .section-map-item b {
            color: var(--report-green);
        }

        .me-report-create .section-map-item strong,
        .me-report-create .section-map-item small {
            display: block;
        }

        .me-report-create .section-map-item strong {
            color: var(--report-ink);
            font-size: .72rem;
        }

        .me-report-create .section-map-item small {
            margin-top: .12rem;
            color: var(--report-muted);
            font-size: .64rem;
        }

        .me-report-create .btn-create-report {
            min-height: 46px;
            padding-inline: 1.2rem;
            border: 0;
            border-radius: .72rem;
            background: var(--report-green);
            font-weight: 750;
        }

        @media (max-width: 767.98px) {
            .me-report-create .template-preview {
                grid-template-columns: 1fr;
            }

            .me-report-create .report-guide,
            .me-report-create .scope-grid,
            .me-report-create .report-section-map {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container">
        <div class="me-report-create">
            <header class="report-hero mb-4">
                <div class="position-relative" style="z-index: 1">
                    <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'reports']) }}" class="text-white-50 text-decoration-none small">
                        <i class="feather-arrow-left me-1" aria-hidden="true"></i>Performance reports
                    </a>
                    <h3 class="fw-bold mt-3 mb-0">Create a Performance Report</h3>
                    <p>Build an indicator-based report with its approved definition, targets, evidence rules and disaggregation plan carried into one guided reporting workspace.</p>
                </div>
            </header>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <div class="fw-bold mb-1">The report could not be created.</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="report-card p-3 p-lg-4">
                @if ($forms->isEmpty())
                    <div class="text-center py-5">
                        <i class="feather-file-text text-muted" style="font-size: 2.5rem" aria-hidden="true"></i>
                        <h5 class="mt-3">No eligible reporting form is available</h5>
                        <p class="text-muted">Publish a form that has a Project Component and linked performance indicators first.</p>
                        <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'forms', 'action' => 'create']) }}#data-entry-workspace" class="btn btn-primary">
                            Configure a reporting form
                        </a>
                    </div>
                @else
                    @php
                        $formProfiles = $forms->mapWithKeys(function ($form): array {
                            $indicators = $form->indicators->map(function ($indicator): array {
                                return [
                                    'code' => $indicator->indicator_code,
                                    'name' => $indicator->name,
                                    'definition' => $indicator->definitions ?: 'Definition not configured.',
                                    'results_level' => str($indicator->results_level ?: 'Not configured')->headline()->toString(),
                                    'value_type' => str($indicator->value_type ?: 'number')->headline()->toString(),
                                    'unit' => $indicator->unit?->symbol ?: $indicator->unit?->name ?: 'Not configured',
                                    'baseline' => $indicator->baseline_value,
                                    'annual_target' => $indicator->annual_target,
                                    'programme_target' => $indicator->life_of_programme_target,
                                    'frequency' => $indicator->frequency?->indicatorCadenceLabel() ?: 'Not configured',
                                    'collection_method' => $indicator->data_collection_method ?: 'Not configured',
                                    'mov' => $indicator->meansOfVerificationFolder?->name ?: 'No MOV folder linked',
                                    'mov_documents' => $indicator->meansOfVerificationFolder?->documents?->count() ?? 0,
                                    'requires_evidence' => (bool) $indicator->requires_evidence,
                                    'dimensions' => $indicator->disaggregationRequirements
                                        ->map(fn ($requirement): array => [
                                            'code' => $requirement->dimension?->code,
                                            'name' => $requirement->dimension?->name,
                                            'required' => (bool) $requirement->is_required,
                                        ])
                                        ->filter(fn (array $dimension): bool => filled($dimension['code']))
                                        ->values()
                                        ->all(),
                                ];
                            })->values();

                            return [(string) $form->id => [
                                'indicators' => $indicators->all(),
                                'required_dimensions' => $indicators
                                    ->flatMap(fn (array $indicator): array => collect($indicator['dimensions'])
                                        ->where('required', true)
                                        ->pluck('code')
                                        ->all())
                                    ->filter()
                                    ->unique()
                                    ->values()
                                    ->all(),
                            ]];
                        });
                    @endphp

                    <div class="report-guide" aria-label="Performance report creation guide">
                        <div class="guide-step"><span>1</span><div><strong>Select the approved form</strong><small>The linked indicators define what must be reported.</small></div></div>
                        <div class="guide-step"><span>2</span><div><strong>Review the indicator plan</strong><small>Check definitions, targets, frequency and evidence.</small></div></div>
                        <div class="guide-step"><span>3</span><div><strong>Set reporting scope</strong><small>Select the reporter's starting disaggregation context.</small></div></div>
                        <div class="guide-step"><span>4</span><div><strong>Create and complete</strong><small>Enter results, achievements, beneficiary rows and evidence.</small></div></div>
                    </div>

                    <form method="POST" action="{{ route('budget.me.performance-reports.store') }}" class="row g-4" id="performance-report-create-form">
                        @csrf

                        <div class="col-12">
                            <label for="report-form" class="form-label"><span class="report-step">1</span>Reporting form</label>
                            <select name="form_id" id="report-form" class="form-select @error('form_id') is-invalid @enderror" required>
                                <option value="">Select a published reporting form</option>
                                @foreach ($forms as $form)
                                    @php
                                        $frequencySummary = $form->indicators
                                            ->map(fn ($indicator) => $indicator->indicator_code.' — '.($indicator->frequency?->indicatorCadenceLabel() ?: 'Not configured'))
                                            ->values();
                                    @endphp
                                    <option
                                        value="{{ $form->id }}"
                                        data-portfolio="{{ $form->portfolio?->name }}"
                                        data-component="{{ $form->projectComponent?->name }}"
                                        data-directorate="{{ $form->projectComponent?->governanceNode?->name }}"
                                        data-indicator-count="{{ $form->indicators->count() }}"
                                        data-frequencies='@json($frequencySummary)'
                                        @selected(old('form_id', request('form_id')) === (string) $form->id)
                                    >{{ $form->code }} · {{ $form->title }}</option>
                                @endforeach
                            </select>
                            @error('form_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 d-none" id="template-preview" aria-live="polite">
                            <div class="template-preview">
                                <div class="preview-item"><small>Portfolio</small><strong data-preview="portfolio">—</strong></div>
                                <div class="preview-item"><small>Project Component</small><strong data-preview="component">—</strong></div>
                                <div class="preview-item"><small>Responsible Directorate</small><strong data-preview="directorate">—</strong></div>
                                <div class="preview-item">
                                    <small>Linked indicators</small>
                                    <strong><span data-preview="indicator-count">0</span> approved indicator(s)</strong>
                                </div>
                                <div class="preview-item frequency-list grid-column-full" style="grid-column: 1 / -1">
                                    <small>Approved reporting frequencies</small>
                                    <div data-preview="frequencies">—</div>
                                </div>
                            </div>

                            <div class="preview-tabs" role="tablist" aria-label="Selected reporting form details">
                                <button type="button" class="preview-tab is-active" data-preview-tab="indicators"><i class="feather-list me-1" aria-hidden="true"></i>Indicator plan</button>
                                <button type="button" class="preview-tab" data-preview-tab="disaggregation"><i class="feather-filter me-1" aria-hidden="true"></i>Reporter disaggregation</button>
                                <button type="button" class="preview-tab" data-preview-tab="structure"><i class="feather-layout me-1" aria-hidden="true"></i>Report structure</button>
                            </div>

                            <div class="preview-panel is-active" data-preview-panel="indicators">
                                <div class="preview-panel-head">
                                    <div><h5>Indicator measurement plan</h5><p>The report creates result rows only for indicators due in the selected period.</p></div>
                                    <span class="badge bg-success-subtle text-success"><span data-preview="indicator-count">0</span> linked</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="indicator-plan-table">
                                        <thead><tr><th style="width:20%">Indicator</th><th style="width:22%">Definition</th><th style="width:16%">Measurement</th><th style="width:17%">Targets</th><th style="width:25%">Reporting, disaggregation &amp; evidence</th></tr></thead>
                                        <tbody data-indicator-plan-body></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="preview-panel" data-preview-panel="disaggregation">
                                <div class="preview-panel-head">
                                    <div><h5>Reporter disaggregation selection</h5><p>Choose the starting reporting scope. Required categories are highlighted from the linked indicators.</p></div>
                                </div>
                                <datalist id="report-country-options">@foreach($reportingTaxonomy['countries'] as $country)<option value="{{ $country }}"></option>@endforeach</datalist>
                                <div class="scope-grid">
                                    <div class="scope-field" data-scope-field="geographic_scope"><label class="form-label" for="scope-geographic"><span class="scope-requirement">Optional</span>Geographic scope</label><select id="scope-geographic" name="reporting_scope[geographic_scope]" class="form-select @error('reporting_scope.geographic_scope') is-invalid @enderror"><option value="">Select scope</option>@foreach($reportingTaxonomy['geographic_scopes'] as $value=>$label)<option value="{{ $value }}" @selected(old('reporting_scope.geographic_scope')===$value)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="scope-field" data-scope-field="country"><label class="form-label" for="scope-country"><span class="scope-requirement">Optional</span>Country</label><input id="scope-country" name="reporting_scope[country]" class="form-control @error('reporting_scope.country') is-invalid @enderror" list="report-country-options" value="{{ old('reporting_scope.country') }}" placeholder="Select or enter country"></div>
                                    <div class="scope-field" data-scope-field="rec"><label class="form-label" for="scope-rec"><span class="scope-requirement">Optional</span>Regional Economic Community</label><select id="scope-rec" name="reporting_scope[rec]" class="form-select @error('reporting_scope.rec') is-invalid @enderror"><option value="">Not applicable</option>@foreach($reportingTaxonomy['recs'] as $value=>$label)<option value="{{ $value }}" @selected(old('reporting_scope.rec')===$value)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="scope-field" data-scope-field="priority_theme"><label class="form-label" for="scope-theme"><span class="scope-requirement">Optional</span>ATTP priority theme</label><select id="scope-theme" name="reporting_scope[priority_theme]" class="form-select @error('reporting_scope.priority_theme') is-invalid @enderror"><option value="">All applicable themes</option>@foreach($reportingTaxonomy['priority_themes'] as $value=>$label)<option value="{{ $value }}" @selected(old('reporting_scope.priority_theme')===$value)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="scope-field" data-scope-field="gender"><label class="form-label" for="scope-gender"><span class="scope-requirement">Optional</span>Gender focus</label><select id="scope-gender" name="reporting_scope[gender]" class="form-select @error('reporting_scope.gender') is-invalid @enderror"><option value="">All genders</option>@foreach($reportingTaxonomy['genders'] as $value=>$label)<option value="{{ $value }}" @selected(old('reporting_scope.gender')===$value)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="scope-field" data-scope-field="age_group"><label class="form-label" for="scope-age"><span class="scope-requirement">Optional</span>Age group focus</label><select id="scope-age" name="reporting_scope[age_group]" class="form-select @error('reporting_scope.age_group') is-invalid @enderror"><option value="">All age groups</option>@foreach($reportingTaxonomy['age_groups'] as $value=>$label)<option value="{{ $value }}" @selected(old('reporting_scope.age_group')===$value)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="scope-field" data-scope-field="stakeholder_category"><label class="form-label" for="scope-stakeholder"><span class="scope-requirement">Optional</span>Stakeholder category</label><select id="scope-stakeholder" name="reporting_scope[stakeholder_category]" class="form-select @error('reporting_scope.stakeholder_category') is-invalid @enderror"><option value="">All stakeholder groups</option>@foreach($reportingTaxonomy['stakeholder_categories'] as $value=>$label)<option value="{{ $value }}" @selected(old('reporting_scope.stakeholder_category')===$value)>{{ $label }}</option>@endforeach</select></div>
                                </div>
                                <div class="scope-note"><i class="feather-info" aria-hidden="true"></i><div><strong>How this is used:</strong> this scope is saved on the report and pre-fills achievement and beneficiary-disaggregation rows. The reporter can still add multiple combinations, such as Female + Youth + Government and Male + Adult + Civil Society.</div></div>
                            </div>

                            <div class="preview-panel" data-preview-panel="structure">
                                <div class="preview-panel-head"><div><h5>Detailed report sections</h5><p>After creation, the reporter completes these controlled sections before submission.</p></div></div>
                                <div class="report-section-map">
                                    <div class="section-map-item"><b>1</b><div><strong>Indicator results</strong><small>Period result, cumulative performance and target progress.</small></div></div>
                                    <div class="section-map-item"><b>2</b><div><strong>Achievements and variance</strong><small>Concrete outputs, results and explanations for variance.</small></div></div>
                                    <div class="section-map-item"><b>3</b><div><strong>Evidence</strong><small>MOV notes, repository links and supporting files.</small></div></div>
                                    <div class="section-map-item"><b>4</b><div><strong>Overall assessment</strong><small>Performance rating, assessment and conclusion.</small></div></div>
                                    <div class="section-map-item"><b>5</b><div><strong>Challenges and mitigation</strong><small>Constraints, response actions and ownership.</small></div></div>
                                    <div class="section-map-item"><b>6</b><div><strong>Learning and adaptation</strong><small>Lessons learned and management changes.</small></div></div>
                                    <div class="section-map-item"><b>7</b><div><strong>Next-period priorities</strong><small>Forward plan for outstanding and new actions.</small></div></div>
                                    <div class="section-map-item"><b>+</b><div><strong>Detailed registers</strong><small>Indicators, achievements, beneficiaries, disaggregation and evidence.</small></div></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="report-period-type" class="form-label"><span class="report-step">2</span>Reporting frequency</label>
                            <select name="reporting_period_type" id="report-period-type" class="form-select @error('reporting_period_type') is-invalid @enderror" required>
                                @foreach ($periodTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('reporting_period_type', 'quarter') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('reporting_period_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="report-period-label" class="form-label"><span class="report-step">3</span>Reporting period</label>
                            <select name="reporting_period_label" id="report-period-label" class="form-select @error('reporting_period_label') is-invalid @enderror" required></select>
                            @error('reporting_period_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="report-year" class="form-label"><span class="report-step">4</span>Reporting year</label>
                            <input type="number" name="reporting_year" id="report-year" min="2000" max="2100" class="form-control @error('reporting_year') is-invalid @enderror" value="{{ old('reporting_year', $defaultYear) }}" required>
                            @error('reporting_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 d-flex flex-wrap justify-content-end gap-2 pt-2">
                            <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'reports']) }}" class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-primary btn-create-report">
                                <i class="feather-plus-circle me-1" aria-hidden="true"></i>Create Report
                            </button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const formSelect = document.getElementById('report-form');
            const preview = document.getElementById('template-preview');
            const periodType = document.getElementById('report-period-type');
            const periodLabel = document.getElementById('report-period-label');
            const periodLabels = @json($periodLabels);
            const formProfiles = @json($formProfiles ?? collect());
            const oldPeriodLabel = @json(old('reporting_period_label', 'Q1'));
            if (!formSelect || !preview) {
                return;
            }

            const addLines = (cell, lines) => {
                lines.filter((line) => line !== null && line !== undefined && line !== '').forEach((line) => {
                    const item = document.createElement('div');
                    item.className = 'mb-1';
                    item.textContent = line;
                    cell.appendChild(item);
                });
            };

            const renderIndicatorPlan = (profile) => {
                const body = preview.querySelector('[data-indicator-plan-body]');
                if (!body) return;
                body.replaceChildren();

                (profile?.indicators || []).forEach((indicator) => {
                    const row = document.createElement('tr');
                    const identity = document.createElement('td');
                    const code = document.createElement('span');
                    code.className = 'indicator-code';
                    code.textContent = indicator.code || 'No code';
                    const title = document.createElement('span');
                    title.className = 'indicator-title';
                    title.textContent = indicator.name || 'Unnamed indicator';
                    identity.append(code, title);

                    const definition = document.createElement('td');
                    definition.textContent = indicator.definition || 'Definition not configured.';

                    const measurement = document.createElement('td');
                    addLines(measurement, [
                        `Level: ${indicator.results_level}`,
                        `Type: ${indicator.value_type}`,
                        `Unit: ${indicator.unit}`,
                        `Baseline: ${indicator.baseline ?? 'Not set'}`,
                    ]);

                    const targets = document.createElement('td');
                    addLines(targets, [
                        `Annual: ${indicator.annual_target ?? 'Not set'}`,
                        `Programme end: ${indicator.programme_target ?? 'Not set'}`,
                    ]);

                    const reporting = document.createElement('td');
                    const dimensions = (indicator.dimensions || [])
                        .map((dimension) => `${dimension.name}${dimension.required ? ' (required)' : ''}`)
                        .join(', ');
                    addLines(reporting, [
                        `Frequency: ${indicator.frequency}`,
                        `Collection: ${indicator.collection_method}`,
                        `Disaggregation: ${dimensions || 'Standard reporting scope'}`,
                        `MOV: ${indicator.mov} (${indicator.mov_documents || 0} document(s))`,
                    ]);

                    row.append(identity, definition, measurement, targets, reporting);
                    body.appendChild(row);
                });

                if (!body.children.length) {
                    const row = document.createElement('tr');
                    const cell = document.createElement('td');
                    cell.colSpan = 5;
                    cell.className = 'text-center text-muted py-4';
                    cell.textContent = 'No linked indicator detail is available.';
                    row.appendChild(cell);
                    body.appendChild(row);
                }

                const requiredDimensions = new Set(profile?.required_dimensions || []);
                preview.querySelectorAll('[data-scope-field]').forEach((field) => {
                    const required = requiredDimensions.has(field.dataset.scopeField);
                    field.classList.toggle('is-required', required);
                    const label = field.querySelector('.scope-requirement');
                    if (label) label.textContent = required ? 'Required by indicator' : 'Optional';
                });
            };

            const updatePreview = () => {
                const option = formSelect.options[formSelect.selectedIndex];
                const selected = Boolean(option?.value);
                preview.classList.toggle('d-none', !selected);
                if (!selected) {
                    return;
                }

                ['portfolio', 'component', 'directorate', 'indicator-count'].forEach((key) => {
                    preview.querySelectorAll(`[data-preview="${key}"]`).forEach((output) => {
                        output.textContent = option.dataset[key] || 'Not assigned';
                    });
                });

                const frequencyOutput = preview.querySelector('[data-preview="frequencies"]');
                if (frequencyOutput) {
                    try {
                        const frequencies = JSON.parse(option.dataset.frequencies || '[]');
                        frequencyOutput.textContent = frequencies.length ? frequencies.join(' • ') : 'No approved frequency';
                    } catch (error) {
                        frequencyOutput.textContent = 'Frequency details unavailable';
                    }
                }

                renderIndicatorPlan(formProfiles[option.value] || null);
            };

            preview.querySelectorAll('[data-preview-tab]').forEach((button) => {
                button.addEventListener('click', () => {
                    preview.querySelectorAll('[data-preview-tab]').forEach((tab) => {
                        tab.classList.toggle('is-active', tab === button);
                    });
                    preview.querySelectorAll('[data-preview-panel]').forEach((panel) => {
                        panel.classList.toggle('is-active', panel.dataset.previewPanel === button.dataset.previewTab);
                    });
                });
            });

            formSelect.addEventListener('change', updatePreview);
            updatePreview();

            const updatePeriodLabels = () => {
                const options = periodLabels[periodType?.value] || {};
                const selected = periodLabel?.value || oldPeriodLabel;
                if (!periodLabel) return;
                periodLabel.innerHTML = '';
                Object.entries(options).forEach(([value, label]) => {
                    const option = new Option(label, value, false, selected === value);
                    periodLabel.add(option);
                });
            };
            periodType?.addEventListener('change', updatePeriodLabels);
            updatePeriodLabels();
        });
    </script>
@endpush
