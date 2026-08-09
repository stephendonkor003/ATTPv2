@php
    $inlinePortfolioOptions = $portfolios ?? collect();
    $inlineSinglePortfolio = $inlinePortfolioOptions->count() === 1
        ? $inlinePortfolioOptions->first()
        : null;
    $inlineFrequencyIntervals = $frequencyIntervalOptions
        ?? \App\Models\ReportingFrequency::intervalOptions();
@endphp

<div
    class="modal fade me-inline-config-modal"
    id="indicatorUnitCreateModal"
    tabindex="-1"
    aria-labelledby="indicatorUnitCreateModalTitle"
    aria-describedby="indicatorUnitCreateModalDescription"
    aria-hidden="true"
    data-inline-config-modal="unit"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <form
                method="POST"
                action="{{ route('budget.me-configuration.units.store') }}"
                data-inline-config-form="unit"
                data-inline-target-select="indicator-unit"
            >
                @csrf
                <input type="hidden" name="is_active" value="1">

                <div class="modal-header">
                    <div class="me-inline-modal-heading">
                        <span class="me-inline-modal-icon" aria-hidden="true"><i class="feather-bar-chart-2"></i></span>
                        <div>
                            <h2 class="modal-title" id="indicatorUnitCreateModalTitle">Create unit of measurement</h2>
                            <p id="indicatorUnitCreateModalDescription">Add a unit and select it immediately for this indicator.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-danger me-inline-modal-errors d-none" role="alert" tabindex="-1" data-inline-error-summary>
                        <div class="fw-bold">We could not create this unit.</div>
                        <ul class="mb-0 mt-1 ps-3" data-inline-error-list></ul>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="unit-modal-portfolio">Portfolio <span class="text-danger">*</span></label>
                            @if ($inlineSinglePortfolio)
                                <input
                                    type="hidden"
                                    id="unit-modal-portfolio"
                                    name="portfolio_id"
                                    value="{{ $inlineSinglePortfolio->id }}"
                                    data-inline-config-field
                                >
                                <div class="me-inline-locked-value">
                                    <i class="feather-briefcase" aria-hidden="true"></i>
                                    <span>{{ $inlineSinglePortfolio->name }}</span>
                                </div>
                                <small class="me-inline-modal-help">This is the only portfolio available to your account.</small>
                            @else
                                <select
                                    id="unit-modal-portfolio"
                                    name="portfolio_id"
                                    class="form-select"
                                    data-inline-config-field
                                    required
                                >
                                    <option value="">Select portfolio</option>
                                    @foreach ($inlinePortfolioOptions as $portfolio)
                                        <option value="{{ $portfolio->id }}">{{ $portfolio->name }}</option>
                                    @endforeach
                                </select>
                                <small class="me-inline-modal-help">The unit will be available to indicators in this portfolio.</small>
                            @endif
                            <div class="invalid-feedback" id="unit-modal-portfolio-error" data-inline-error-for="portfolio_id"></div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label" for="unit-modal-name">Unit name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                id="unit-modal-name"
                                name="name"
                                class="form-control"
                                maxlength="255"
                                placeholder="e.g. Percentage, People, Kilometres"
                                autocomplete="off"
                                data-inline-config-field
                                data-inline-autofocus
                                required
                            >
                            <div class="invalid-feedback" id="unit-modal-name-error" data-inline-error-for="name"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="unit-modal-symbol">Symbol</label>
                            <input
                                type="text"
                                id="unit-modal-symbol"
                                name="symbol"
                                class="form-control"
                                maxlength="20"
                                placeholder="e.g. %, km"
                                autocomplete="off"
                                data-inline-config-field
                            >
                            <div class="invalid-feedback" id="unit-modal-symbol-error" data-inline-error-for="symbol"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="unit-modal-description">Description <span class="me-inline-optional">Optional</span></label>
                            <textarea
                                id="unit-modal-description"
                                name="description"
                                class="form-control"
                                rows="3"
                                maxlength="5000"
                                placeholder="Briefly explain how this unit should be used."
                                data-inline-config-field
                            ></textarea>
                            <div class="invalid-feedback" id="unit-modal-description-error" data-inline-error-for="description"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="me-inline-modal-submit" data-inline-submit>
                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-inline-submit-spinner></span>
                        <i class="feather-plus" aria-hidden="true" data-inline-submit-icon></i>
                        <span data-inline-submit-label>Create and select</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div
    class="modal fade me-inline-config-modal"
    id="indicatorEvidenceFolderCreateModal"
    tabindex="-1"
    aria-labelledby="indicatorEvidenceFolderCreateModalTitle"
    aria-describedby="indicatorEvidenceFolderCreateModalDescription"
    aria-hidden="true"
    data-inline-config-modal="evidence"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <form
                method="POST"
                action="{{ route('budget.me.knowledge-evidence.folders.store') }}"
                data-inline-config-form="evidence"
                data-inline-target-select="indicator-means-of-verification"
            >
                @csrf
                <input type="hidden" name="indicator_creation" value="1">

                <div class="modal-header">
                    <div class="me-inline-modal-heading">
                        <span class="me-inline-modal-icon" aria-hidden="true"><i class="feather-folder-plus"></i></span>
                        <div>
                            <h2 class="modal-title" id="indicatorEvidenceFolderCreateModalTitle">Create Means of Verification folder</h2>
                            <p id="indicatorEvidenceFolderCreateModalDescription">Create the evidence folder and select it without leaving this indicator.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-danger me-inline-modal-errors d-none" role="alert" tabindex="-1" data-inline-error-summary>
                        <div class="fw-bold">We could not create this folder.</div>
                        <ul class="mb-0 mt-1 ps-3" data-inline-error-list></ul>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="evidence-modal-portfolio">Portfolio <span class="text-danger">*</span></label>
                            @if ($inlineSinglePortfolio)
                                <input
                                    type="hidden"
                                    id="evidence-modal-portfolio"
                                    name="portfolio_id"
                                    value="{{ $inlineSinglePortfolio->id }}"
                                    data-inline-config-field
                                >
                                <div class="me-inline-locked-value">
                                    <i class="feather-briefcase" aria-hidden="true"></i>
                                    <span>{{ $inlineSinglePortfolio->name }}</span>
                                </div>
                                <small class="me-inline-modal-help">The folder will belong to this portfolio.</small>
                            @else
                                <select
                                    id="evidence-modal-portfolio"
                                    name="portfolio_id"
                                    class="form-select"
                                    data-inline-config-field
                                    required
                                >
                                    <option value="">Select portfolio</option>
                                    @foreach ($inlinePortfolioOptions as $portfolio)
                                        <option value="{{ $portfolio->id }}">{{ $portfolio->name }}</option>
                                    @endforeach
                                </select>
                                <small class="me-inline-modal-help">The selected indicator portfolio is used automatically.</small>
                            @endif
                            <div class="invalid-feedback" id="evidence-modal-portfolio-error" data-inline-error-for="portfolio_id"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="evidence-modal-name">Folder name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                id="evidence-modal-name"
                                name="name"
                                class="form-control"
                                maxlength="180"
                                placeholder="e.g. INTC2.5 — Research output evidence"
                                autocomplete="off"
                                data-inline-config-field
                                data-inline-autofocus
                                required
                            >
                            <small class="me-inline-modal-help">Use a clear name that identifies the indicator and the evidence kept in the folder.</small>
                            <div class="invalid-feedback" id="evidence-modal-name-error" data-inline-error-for="name"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="evidence-modal-description">Evidence guidance <span class="me-inline-optional">Optional</span></label>
                            <textarea
                                id="evidence-modal-description"
                                name="description"
                                class="form-control"
                                rows="4"
                                maxlength="5000"
                                placeholder="Describe the publications, peer-review records or other documents that belong here."
                                data-inline-config-field
                            ></textarea>
                            <div class="invalid-feedback" id="evidence-modal-description-error" data-inline-error-for="description"></div>
                        </div>
                    </div>

                    <div class="alert alert-light border mt-3 mb-0 small">
                        The folder is created now. Saving the indicator will link the folder to it automatically.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="me-inline-modal-submit" data-inline-submit>
                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-inline-submit-spinner></span>
                        <i class="feather-folder-plus" aria-hidden="true" data-inline-submit-icon></i>
                        <span data-inline-submit-label>Create and select</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div
    class="modal fade me-inline-config-modal"
    id="indicatorFrequencyCreateModal"
    tabindex="-1"
    aria-labelledby="indicatorFrequencyCreateModalTitle"
    aria-describedby="indicatorFrequencyCreateModalDescription"
    aria-hidden="true"
    data-inline-config-modal="frequency"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <form
                method="POST"
                action="{{ route('budget.me-configuration.frequencies.store') }}"
                data-inline-config-form="frequency"
                data-inline-target-select="indicator-frequency"
            >
                @csrf
                <input type="hidden" name="is_active" value="1">
                <input type="hidden" name="sort_order" value="0">

                <div class="modal-header">
                    <div class="me-inline-modal-heading">
                        <span class="me-inline-modal-icon" aria-hidden="true"><i class="feather-clock"></i></span>
                        <div>
                            <h2 class="modal-title" id="indicatorFrequencyCreateModalTitle">Create reporting frequency</h2>
                            <p id="indicatorFrequencyCreateModalDescription">Define a reporting cycle and select it immediately.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-danger me-inline-modal-errors d-none" role="alert" tabindex="-1" data-inline-error-summary>
                        <div class="fw-bold">We could not create this reporting frequency.</div>
                        <ul class="mb-0 mt-1 ps-3" data-inline-error-list></ul>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="frequency-modal-portfolio">Portfolio <span class="text-danger">*</span></label>
                            @if ($inlineSinglePortfolio)
                                <input
                                    type="hidden"
                                    id="frequency-modal-portfolio"
                                    name="portfolio_id"
                                    value="{{ $inlineSinglePortfolio->id }}"
                                    data-inline-config-field
                                >
                                <div class="me-inline-locked-value">
                                    <i class="feather-briefcase" aria-hidden="true"></i>
                                    <span>{{ $inlineSinglePortfolio->name }}</span>
                                </div>
                                <small class="me-inline-modal-help">This is the only portfolio available to your account.</small>
                            @else
                                <select
                                    id="frequency-modal-portfolio"
                                    name="portfolio_id"
                                    class="form-select"
                                    data-inline-config-field
                                    required
                                >
                                    <option value="">Select portfolio</option>
                                    @foreach ($inlinePortfolioOptions as $portfolio)
                                        <option value="{{ $portfolio->id }}">{{ $portfolio->name }}</option>
                                    @endforeach
                                </select>
                                <small class="me-inline-modal-help">The frequency will be available to indicators in this portfolio.</small>
                            @endif
                            <div class="invalid-feedback" id="frequency-modal-portfolio-error" data-inline-error-for="portfolio_id"></div>
                        </div>

                        <div class="col-md-7">
                            <label class="form-label" for="frequency-modal-name">Frequency name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                id="frequency-modal-name"
                                name="name"
                                class="form-control"
                                maxlength="255"
                                placeholder="e.g. Monthly, Quarterly"
                                autocomplete="off"
                                data-inline-config-field
                                data-inline-autofocus
                                required
                            >
                            <div class="invalid-feedback" id="frequency-modal-name-error" data-inline-error-for="name"></div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label" for="frequency-modal-code">Code <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                id="frequency-modal-code"
                                name="code"
                                class="form-control text-uppercase"
                                maxlength="255"
                                placeholder="e.g. MONTHLY"
                                autocomplete="off"
                                data-inline-config-field
                                required
                            >
                            <div class="invalid-feedback" id="frequency-modal-code-error" data-inline-error-for="code"></div>
                        </div>

                        <div class="col-md-7">
                            <label class="form-label" for="frequency-modal-interval-unit">Interval unit <span class="text-danger">*</span></label>
                            <select
                                id="frequency-modal-interval-unit"
                                name="interval_unit"
                                class="form-select"
                                aria-describedby="frequency-modal-interval-hint"
                                data-inline-config-field
                                data-frequency-interval-unit
                                required
                            >
                                @foreach ($inlineFrequencyIntervals as $value => $label)
                                    <option value="{{ $value }}" @selected($value === 'month')>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="frequency-modal-interval-unit-error" data-inline-error-for="interval_unit"></div>
                        </div>

                        <div class="col-md-5" data-frequency-interval-value-wrap>
                            <label class="form-label" for="frequency-modal-interval-value">Interval value <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                id="frequency-modal-interval-value"
                                name="interval_value"
                                class="form-control"
                                min="1"
                                step="1"
                                value="1"
                                aria-describedby="frequency-modal-interval-hint"
                                data-inline-config-field
                                data-frequency-interval-value
                                required
                            >
                            <div class="invalid-feedback" id="frequency-modal-interval-value-error" data-inline-error-for="interval_value"></div>
                        </div>

                        <div class="col-12">
                            <small class="me-inline-modal-help me-inline-interval-hint" id="frequency-modal-interval-hint" data-frequency-interval-hint>
                                Example: 1 month means reporting every month.
                            </small>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="frequency-modal-description">Description <span class="me-inline-optional">Optional</span></label>
                            <textarea
                                id="frequency-modal-description"
                                name="description"
                                class="form-control"
                                rows="3"
                                maxlength="5000"
                                placeholder="Add any guidance about when this reporting cycle applies."
                                data-inline-config-field
                            ></textarea>
                            <div class="invalid-feedback" id="frequency-modal-description-error" data-inline-error-for="description"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="me-inline-modal-submit" data-inline-submit>
                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-inline-submit-spinner></span>
                        <i class="feather-plus" aria-hidden="true" data-inline-submit-icon></i>
                        <span data-inline-submit-label>Create and select</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
