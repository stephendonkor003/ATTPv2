<div
    class="modal fade me-disaggregation-modal"
    id="indicatorDisaggregationModal"
    tabindex="-1"
    aria-labelledby="indicatorDisaggregationModalLabel"
    aria-describedby="indicatorDisaggregationModalDescription"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <form method="POST" action="#" data-disaggregation-form>
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <div class="me-disaggregation-heading">
                        <span class="me-disaggregation-heading-icon" aria-hidden="true">
                            <i class="feather-filter"></i>
                        </span>
                        <div>
                            <h2 class="modal-title" id="indicatorDisaggregationModalLabel">Indicator disaggregation</h2>
                            <p id="indicatorDisaggregationModalDescription">
                                Define how this indicator should be broken down for reporting.
                            </p>
                            <div class="me-disaggregation-indicator" data-disaggregation-indicator-name aria-live="polite"></div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="me-disaggregation-note">
                        <i class="feather-info" aria-hidden="true"></i>
                        <span>Choose dimensions in order. Secondary and tertiary dimensions become available as you complete the level above.</span>
                    </div>

                    <datalist id="indicator-disaggregation-dimensions">
                        @foreach ($disaggregationDimensions as $dimension)
                            <option value="{{ $dimension }}"></option>
                        @endforeach
                    </datalist>

                    <div class="me-disaggregation-level">
                        <span class="me-disaggregation-step" aria-hidden="true">1</span>
                        <div class="me-disaggregation-field">
                            <label for="primary-disaggregation" class="form-label">Primary disaggregation</label>
                            <input
                                id="primary-disaggregation"
                                name="primary_disaggregation"
                                class="form-control"
                                list="indicator-disaggregation-dimensions"
                                maxlength="120"
                                placeholder="e.g. Gender"
                                data-disaggregation-level="primary"
                            >
                            <div class="form-text">The main dimension used to split reported results.</div>
                        </div>
                    </div>

                    <div class="me-disaggregation-level">
                        <span class="me-disaggregation-step" aria-hidden="true">2</span>
                        <div class="me-disaggregation-field">
                            <label for="secondary-disaggregation" class="form-label">
                                Secondary disaggregation <span class="me-disaggregation-optional">Optional</span>
                            </label>
                            <input
                                id="secondary-disaggregation"
                                name="secondary_disaggregation"
                                class="form-control"
                                list="indicator-disaggregation-dimensions"
                                maxlength="120"
                                placeholder="e.g. Age Group"
                                data-disaggregation-level="secondary"
                            >
                            <div class="form-text">Adds a second breakdown beneath the primary level.</div>
                        </div>
                    </div>

                    <div class="me-disaggregation-level">
                        <span class="me-disaggregation-step" aria-hidden="true">3</span>
                        <div class="me-disaggregation-field">
                            <label for="tertiary-disaggregation" class="form-label">
                                Tertiary disaggregation <span class="me-disaggregation-optional">Optional</span>
                            </label>
                            <input
                                id="tertiary-disaggregation"
                                name="tertiary_disaggregation"
                                class="form-control"
                                list="indicator-disaggregation-dimensions"
                                maxlength="120"
                                placeholder="e.g. Geographic Location"
                                data-disaggregation-level="tertiary"
                            >
                            <div class="form-text">Adds the most detailed breakdown beneath the secondary level.</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="me-disaggregation-save">
                        <i class="feather-save" aria-hidden="true"></i>
                        Save disaggregation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
