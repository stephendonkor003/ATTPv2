@php
    $isEditing = (bool) $editingIndicator;
    $formAction = $isEditing
        ? route('budget.me.indicators.update', $editingIndicator)
        : route('budget.me.indicators.store');
    $selectedResponsibleUser = (string) old(
        'responsible_user_id',
        $editingResponsibleUserIds[0] ?? ''
    );
    $selectedOwner = (string) old('owner_reference', $editingOwnerReference ?? '');
    $ownerPortfolioMap = $ownerPortfolioMap ?? [];
    $selectedPortfolioId = (string) old(
        'portfolio_id',
        $editingPortfolioId ?? ($ownerPortfolioMap[$selectedOwner] ?? '')
    );
    if ($selectedPortfolioId === '' && ($portfolios ?? collect())->count() === 1) {
        $selectedPortfolioId = (string) $portfolios->first()->id;
    }
    if ($selectedOwner === 'portfolio:'.$selectedPortfolioId) {
        $selectedOwner = '';
    }
    $selectedComponentId = (string) old(
        'project_component_id',
        $editingIndicator->project_component_id ?? ''
    );
    $selectedResultsLevel = (string) old(
        'results_level',
        $editingIndicator->results_level ?? ''
    );
    $selectedMeansOfVerificationFolderId = (string) old(
        'means_of_verification_folder_id',
        $editingIndicator->means_of_verification_folder_id ?? ''
    );
    $targetValue = old('target_value', $editingTargetValue ?? '');
    $dataCollectionMethod = old(
        'data_collection_method',
        $editingDataCollectionMethod ?? $editingPrimarySourceValue ?? ''
    );
    $hasSubmittedDisaggregation = old('disaggregation_configuration_present') !== null;
    $savedRequirements = $editingIndicator?->disaggregationRequirements ?? collect();
    $selectedDimensionIds = collect(
        $hasSubmittedDisaggregation
            ? old('dimensions', [])
            : $savedRequirements->pluck('dimension_id')->all()
    )->map(fn ($id) => (string) $id)->all();
    $requiredDimensionIds = collect(
        $hasSubmittedDisaggregation
            ? old('required_dimensions', [])
            : $savedRequirements->where('is_required', true)->pluck('dimension_id')->all()
    )->map(fn ($id) => (string) $id)->all();
    $showConfigurationPortfolio = ($portfolios ?? collect())->count() > 1;
@endphp

<section class="me-panel me-indicator-form-panel" id="indicator-form" aria-labelledby="indicator-form-title">
    <div class="me-panel-header">
        <div>
            <h2 class="me-panel-title" id="indicator-form-title">
                {{ $isEditing ? 'Edit indicator' : 'Add a new indicator' }}
            </h2>
            <p class="me-panel-subtitle">Complete the essential measurement and accountability information.</p>
        </div>
        <a href="{{ route('budget.me.indicators.index') }}" class="btn btn-sm btn-light border">
            <i class="feather-x me-1"></i> Close form
        </a>
    </div>

    <div class="me-panel-body">
        <div class="me-required-note" role="note">
            <i class="feather-info mt-1" aria-hidden="true"></i>
            <span>All fields marked with <strong>*</strong> are required. Keep definitions concise and use the same unit for the baseline and target.</span>
        </div>

        <form method="POST" action="{{ $formAction }}" class="me-indicator-editor" novalidate data-indicator-form>
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif

            <div class="me-form-section">
                <h3 class="me-form-section-title">1. Portfolio and hierarchy</h3>
                <div class="me-scope-card" data-indicator-scope>
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <label class="form-label" for="indicator-portfolio">Portfolio <span class="text-danger">*</span></label>
                            <select
                                id="indicator-portfolio"
                                name="portfolio_id"
                                class="form-select @error('portfolio_id') is-invalid @enderror"
                                aria-describedby="indicator-portfolio-help @error('portfolio_id') indicator-portfolio-error @enderror"
                                data-indicator-portfolio
                                required
                            >
                                <option value="">Select portfolio first</option>
                                @foreach ($portfolios as $portfolio)
                                    <option value="{{ $portfolio->id }}" @selected($selectedPortfolioId === (string) $portfolio->id)>{{ $portfolio->name }}</option>
                                @endforeach
                            </select>
                            <small class="me-field-help" id="indicator-portfolio-help">This selection controls the available hierarchy, unit and reporting frequency.</small>
                            @error('portfolio_id')
                                <div class="invalid-feedback" id="indicator-portfolio-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-lg-7">
                            <label class="form-label" for="indicator-owner-reference">Results hierarchy owner <span class="text-muted fw-normal">(optional)</span></label>
                            <select
                                id="indicator-owner-reference"
                                name="owner_reference"
                                class="form-select @error('owner_reference') is-invalid @enderror"
                                aria-describedby="indicator-owner-help indicator-scope-status @error('owner_reference') indicator-owner-error @enderror"
                                data-indicator-owner
                            >
                                <option value="">Use the selected portfolio</option>
                                @if ($programs->isNotEmpty())
                                    <optgroup label="Programmes">
                                        @foreach ($programs as $program)
                                            @php $ownerValue = 'program:'.$program->id; @endphp
                                            <option value="{{ $ownerValue }}" data-portfolio-id="{{ $ownerPortfolioMap[$ownerValue] ?? '' }}" @selected($selectedOwner === $ownerValue)>{{ $program->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if ($projects->isNotEmpty())
                                    <optgroup label="Projects">
                                        @foreach ($projects as $project)
                                            @php $ownerValue = 'project:'.$project->id; @endphp
                                            <option value="{{ $ownerValue }}" data-portfolio-id="{{ $ownerPortfolioMap[$ownerValue] ?? '' }}" data-component-id="{{ $ownerComponentMap[$ownerValue] ?? '' }}" @selected($selectedOwner === $ownerValue)>{{ $project->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if ($activities->isNotEmpty())
                                    <optgroup label="Activities">
                                        @foreach ($activities as $activity)
                                            @php $ownerValue = 'activity:'.$activity->id; @endphp
                                            <option value="{{ $ownerValue }}" data-portfolio-id="{{ $ownerPortfolioMap[$ownerValue] ?? '' }}" data-component-id="{{ $ownerComponentMap[$ownerValue] ?? '' }}" @selected($selectedOwner === $ownerValue)>{{ $activity->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if ($subActivities->isNotEmpty())
                                    <optgroup label="Sub-activities">
                                        @foreach ($subActivities as $subActivity)
                                            @php $ownerValue = 'sub_activity:'.$subActivity->id; @endphp
                                            <option value="{{ $ownerValue }}" data-portfolio-id="{{ $ownerPortfolioMap[$ownerValue] ?? '' }}" data-component-id="{{ $ownerComponentMap[$ownerValue] ?? '' }}" @selected($selectedOwner === $ownerValue)>{{ $subActivity->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            <small class="me-field-help" id="indicator-owner-help">Leave this as “Use the selected portfolio” unless the indicator belongs to a specific programme, project or activity.</small>
                            @error('owner_reference')<div class="invalid-feedback" id="indicator-owner-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="indicator-project-component">Project component <span class="text-danger">*</span></label>
                            <select
                                id="indicator-project-component"
                                name="project_component_id"
                                class="form-select @error('project_component_id') is-invalid @enderror"
                                data-indicator-component
                                data-indicator-portfolio-dependent
                                data-dependent-kind="components"
                                required
                            >
                                <option value="">Select project component</option>
                                @foreach ($projects as $component)
                                    <option
                                        value="{{ $component->id }}"
                                        data-portfolio-id="{{ $component->program?->sector_id }}"
                                        @selected($selectedComponentId === (string) $component->id)
                                    >
                                        {{ $component->project_id ? $component->project_id.' — ' : '' }}{{ $component->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="me-field-help">Used to filter, aggregate and report indicator results by project component. Project, activity and sub-activity owners automatically lock this selection.</small>
                            @error('project_component_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="me-scope-status" id="indicator-scope-status" data-indicator-scope-status role="status" aria-live="polite"></div>
                </div>
            </div>

            <div class="me-form-section">
                <h3 class="me-form-section-title">2. Indicator identity</h3>
                <div class="row g-3">
                    <div class="col-lg-3">
                        <label class="form-label" for="indicator-code">Indicator ID</label>
                        <input
                            type="text"
                            id="indicator-code"
                            name="indicator_code"
                            class="form-control text-uppercase"
                            value="{{ old('indicator_code', $editingIndicator->indicator_code ?? '') }}"
                            maxlength="80"
                            pattern="[A-Za-z0-9][A-Za-z0-9._/-]*"
                            placeholder="e.g. PDO-2 or IR-3.1"
                            aria-describedby="indicator-code-help"
                            required
                        >
                        <small class="me-field-help" id="indicator-code-help">Authorized users define the unique code. The system keeps an audit history whenever it changes.</small>
                        @error('indicator_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label" for="indicator-results-level">Results level <span class="text-danger">*</span></label>
                        <select
                            id="indicator-results-level"
                            name="results_level"
                            class="form-select @error('results_level') is-invalid @enderror"
                            required
                        >
                            <option value="">Select level</option>
                            <option value="pdo" @selected($selectedResultsLevel === 'pdo')>PDO</option>
                            <option value="intermediate_results" @selected($selectedResultsLevel === 'intermediate_results')>Intermediate Results</option>
                        </select>
                        @error('results_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-6">
                        <label class="form-label" for="indicator-name">Indicator name <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="indicator-name"
                            name="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $editingIndicator->name ?? '') }}"
                            maxlength="255"
                            placeholder="State exactly what will be measured"
                            @error('name') aria-describedby="indicator-name-error" @enderror
                            required
                        >
                        @error('name')
                            <div class="invalid-feedback" id="indicator-name-error">{{ $message }}</div>
                        @enderror
                    </div>

                    @if ($editingIndicator)
                        <div class="col-12">
                            <label class="form-label" for="indicator-code-change-reason">Reason for code change</label>
                            <input
                                type="text"
                                id="indicator-code-change-reason"
                                name="indicator_code_change_reason"
                                class="form-control @error('indicator_code_change_reason') is-invalid @enderror"
                                value="{{ old('indicator_code_change_reason') }}"
                                maxlength="1000"
                                placeholder="Required operational context when changing the indicator code"
                            >
                            @error('indicator_code_change_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div class="col-12">
                        <label class="form-label" for="indicator-definition">Definition <span class="text-danger">*</span></label>
                        <textarea
                            id="indicator-definition"
                            name="definition"
                            class="form-control @error('definition') is-invalid @enderror"
                            rows="3"
                            maxlength="5000"
                            placeholder="Explain what is measured, who or what is included, and how the value should be interpreted."
                            aria-describedby="indicator-definition-help @error('definition') indicator-definition-error @enderror"
                            required
                        >{{ old('definition', $editingIndicator->definitions ?? '') }}</textarea>
                        <small class="me-field-help" id="indicator-definition-help">Use one clear definition so all reporting teams measure the indicator consistently.</small>
                        @error('definition')
                            <div class="invalid-feedback" id="indicator-definition-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="me-form-section">
                <h3 class="me-form-section-title">3. Measurement plan</h3>
                <div class="row g-3">
                    <div class="col-xl col-lg-6">
                        <div class="me-field-label-row">
                            <label class="form-label" for="indicator-unit">Unit of measurement <span class="text-danger">*</span></label>
                            <a
                                href="{{ route('budget.me-configuration.units.create') }}"
                                class="me-inline-create-link"
                                data-inline-config-open="unit"
                                aria-controls="indicatorUnitCreateModal"
                                aria-haspopup="dialog"
                                aria-label="Create a new unit of measurement without leaving this indicator"
                            >
                                <i class="feather-plus" aria-hidden="true"></i> New unit
                            </a>
                        </div>
                        <select
                            id="indicator-unit"
                            name="unit_id"
                            class="form-select @error('unit_id') is-invalid @enderror"
                            aria-describedby="indicator-unit-help indicator-unit-selection-status @error('unit_id') indicator-unit-error @enderror"
                            data-indicator-portfolio-dependent
                            data-dependent-kind="units"
                            required
                        >
                            <option value="">Select unit</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" data-portfolio-id="{{ $unit->portfolio_id }}" @selected((string) old('unit_id', $editingIndicator->unit_id ?? '') === (string) $unit->id)>
                                    {{ $unit->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('unit_id')<div class="invalid-feedback" id="indicator-unit-error">{{ $message }}</div>@enderror
                        <small class="me-field-help" id="indicator-unit-help">The exact configured value is used, for example Number, Percentage, Yes/No or Milestone.</small>
                        <small
                            class="me-inline-selection-status"
                            id="indicator-unit-selection-status"
                            data-inline-selection-status="unit"
                            role="status"
                            aria-live="polite"
                        ></small>
                    </div>

                    <div class="col-xl col-lg-6">
                        <label class="form-label" for="indicator-baseline">Baseline <span class="text-danger">*</span></label>
                        <input
                            type="number"
                            id="indicator-baseline"
                            name="baseline_value"
                            class="form-control @error('baseline_value') is-invalid @enderror"
                            value="{{ old('baseline_value', $editingIndicator->baseline_value ?? '') }}"
                            step="any"
                            placeholder="Starting value"
                            required
                        >
                        @error('baseline_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-xl col-lg-6">
                        <label class="form-label" for="indicator-annual-target">Annual target <span class="text-danger">*</span></label>
                        <input
                            type="number"
                            id="indicator-annual-target"
                            name="annual_target"
                            class="form-control @error('annual_target') is-invalid @enderror"
                            value="{{ old('annual_target', $editingIndicator->annual_target ?? '') }}"
                            step="any"
                            placeholder="Target for one reporting year"
                            required
                        >
                        @error('annual_target')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-xl col-lg-6">
                        <label class="form-label" for="indicator-target">Life-of-programme target <span class="text-danger">*</span></label>
                        <input
                            type="number"
                            id="indicator-target"
                            name="target_value"
                            class="form-control @error('target_value') is-invalid @enderror"
                            value="{{ $targetValue }}"
                            step="any"
                            placeholder="Expected achievement by programme end"
                            required
                        >
                        @error('target_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-xl col-lg-6">
                        <label class="form-label" for="indicator-extra-target">Extra target <span class="text-muted fw-normal">(optional)</span></label>
                        <input
                            type="number"
                            id="indicator-extra-target"
                            name="extra_target"
                            class="form-control @error('extra_target') is-invalid @enderror"
                            value="{{ old('extra_target', $editingIndicator->extra_target ?? '') }}"
                            step="any"
                            placeholder="Optional stretch target"
                            aria-describedby="indicator-extra-target-help"
                        >
                        @error('extra_target')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="me-field-help" id="indicator-extra-target-help">Use only when delivery may intentionally exceed the programme target. Enter the total stretch target, not only the additional amount.</small>
                    </div>

                </div>
            </div>

            <div class="me-form-section">
                <h3 class="me-form-section-title">4. Required disaggregation</h3>
                <p class="text-muted mb-3">
                    Select the meaningful categories used to break down this indicator, such as Country or ATTP priority theme.
                    These categories describe the result; they do not calculate or add it.
                </p>
                <input type="hidden" name="disaggregation_configuration_present" value="1">
                @error('dimensions')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                @error('dimensions.*')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                @if (($disaggregationDimensions ?? collect())->isEmpty())
                    <div class="alert alert-warning mb-0">
                        No approved disaggregation categories are configured. Add them in M&amp;E Configuration before creating the indicator.
                    </div>
                @else
                    <div class="row g-3">
                        @foreach ($disaggregationDimensions as $dimension)
                            @php
                                $dimensionId = (string) $dimension->id;
                                $isSelected = in_array($dimensionId, $selectedDimensionIds, true);
                                $isRequired = in_array($dimensionId, $requiredDimensionIds, true);
                            @endphp
                            <div class="col-xl-4 col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="dimensions[]"
                                            value="{{ $dimension->id }}"
                                            id="indicator-dimension-{{ $dimension->id }}"
                                            data-indicator-dimension-use
                                            @checked($isSelected)
                                        >
                                        <label class="form-check-label fw-semibold" for="indicator-dimension-{{ $dimension->id }}">
                                            {{ $dimension->name }}
                                        </label>
                                    </div>
                                    @if ($dimension->description)
                                        <div class="small text-muted mt-1">{{ $dimension->description }}</div>
                                    @endif
                                    <div class="form-check mt-2">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="required_dimensions[]"
                                            value="{{ $dimension->id }}"
                                            id="indicator-dimension-required-{{ $dimension->id }}"
                                            data-indicator-dimension-required
                                            @checked($isRequired)
                                            @disabled(! $isSelected)
                                        >
                                        <label class="form-check-label small" for="indicator-dimension-required-{{ $dimension->id }}">
                                            Required in every applicable report
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <small class="me-field-help d-block mt-3">Select only categories required by the indicator definition and approved Indicator Reference Sheet.</small>
            </div>

            <div class="me-form-section">
                <h3 class="me-form-section-title">5. Reporting and accountability</h3>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="indicator-frequency">Reporting frequency <span class="text-danger">*</span></label>
                        <select
                            id="indicator-frequency"
                            name="frequency_of_reporting_id"
                            class="form-select @error('frequency_of_reporting_id') is-invalid @enderror"
                            aria-describedby="indicator-frequency-selection-status @error('frequency_of_reporting_id') indicator-frequency-error @enderror"
                            data-indicator-portfolio-dependent
                            data-dependent-kind="frequencies"
                            required
                        >
                            <option value="">Select frequency</option>
                            @foreach ($frequencies as $frequency)
                                <option value="{{ $frequency->id }}" data-portfolio-id="{{ $frequency->portfolio_id }}" @selected((string) old('frequency_of_reporting_id', $editingIndicator->frequency_of_reporting_id ?? '') === (string) $frequency->id)>
                                    {{ $frequency->indicatorCadenceLabel() }}@if($showConfigurationPortfolio && $frequency->portfolio?->name) &mdash; {{ $frequency->portfolio->name }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('frequency_of_reporting_id')<div class="invalid-feedback" id="indicator-frequency-error">{{ $message }}</div>@enderror
                        <small
                            class="me-inline-selection-status"
                            id="indicator-frequency-selection-status"
                            data-inline-selection-status="frequency"
                            role="status"
                            aria-live="polite"
                        ></small>
                    </div>

                    <div class="col-lg-6">
                        <label class="form-label" for="indicator-data-collection-method">Data Collection Method/Data Source <span class="text-danger">*</span></label>
                        <textarea
                            id="indicator-data-collection-method"
                            name="data_collection_method"
                            class="form-control @error('data_collection_method') is-invalid @enderror"
                            rows="2"
                            maxlength="2000"
                            placeholder="e.g. household survey, DHIS2 extract, quarterly monitoring report"
                            required
                        >{{ $dataCollectionMethod }}</textarea>
                        @error('data_collection_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-6">
                        <div class="me-field-label-row">
                            <label class="form-label" for="indicator-means-of-verification">Means of Verification folder <span class="text-danger">*</span></label>
                            <button
                                type="button"
                                class="me-inline-create-link"
                                data-inline-config-open="evidence"
                                aria-controls="indicatorEvidenceFolderCreateModal"
                                aria-haspopup="dialog"
                                aria-label="Create a Means of Verification folder without leaving this indicator"
                            >
                                <i class="feather-folder-plus" aria-hidden="true"></i> New folder
                            </button>
                        </div>
                        <select
                            id="indicator-means-of-verification"
                            name="means_of_verification_folder_id"
                            class="form-select @error('means_of_verification_folder_id') is-invalid @enderror"
                            aria-describedby="indicator-evidence-help indicator-evidence-selection-status @error('means_of_verification_folder_id') indicator-evidence-error @enderror"
                            data-indicator-portfolio-dependent
                            data-dependent-kind="evidence"
                            required
                        >
                            <option value="">Select indicator-linked folder</option>
                            @foreach ($repositoryFolders as $folder)
                                <option value="{{ $folder->id }}" data-portfolio-id="{{ $folder->portfolio_id }}" @selected($selectedMeansOfVerificationFolderId === (string) $folder->id)>
                                    {{ $folder->name }} ({{ $folder->documents_count }} documents)@if($showConfigurationPortfolio && $folder->portfolio?->name) &mdash; {{ $folder->portfolio->name }}@endif
                                </option>
                            @endforeach
                        </select>
                        <small class="me-field-help" id="indicator-evidence-help">The folder—not one individual file—is linked to this indicator. All current and future documents in it remain available as evidence.</small>
                        @error('means_of_verification_folder_id')<div class="invalid-feedback" id="indicator-evidence-error">{{ $message }}</div>@enderror
                        <small
                            class="me-inline-selection-status"
                            id="indicator-evidence-selection-status"
                            data-inline-selection-status="evidence"
                            role="status"
                            aria-live="polite"
                        ></small>
                    </div>

                    <div class="col-lg-6">
                        <label class="form-label" for="indicator-responsible-person">Responsible person <span class="text-danger">*</span></label>
                        <select id="indicator-responsible-person" name="responsible_user_id" class="form-select @error('responsible_user_id') is-invalid @enderror" required>
                            <option value="">Select indicator owner</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected($selectedResponsibleUser === (string) $user->id)>
                                    {{ $user->name }}{{ $user->email ? ' — '.$user->email : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('responsible_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="me-form-actions">
                <a href="{{ route('budget.me.indicators.index') }}" class="btn btn-light border">Cancel</a>
                <button type="submit" class="me-primary-action">
                    <i class="feather-save" aria-hidden="true"></i>
                    {{ $isEditing ? 'Save changes' : 'Create indicator' }}
                </button>
            </div>
        </form>
    </div>
</section>
