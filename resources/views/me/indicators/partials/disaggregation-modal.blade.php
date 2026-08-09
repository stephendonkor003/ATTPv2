<div class="modal fade me-disaggregation-modal" id="indicatorDisaggregationModal" tabindex="-1" aria-labelledby="indicatorDisaggregationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <form method="POST" action="#" data-disaggregation-form>
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <div class="me-disaggregation-heading">
                        <span class="me-disaggregation-heading-icon" aria-hidden="true"><i class="feather-grid"></i></span>
                        <div>
                            <h2 class="modal-title" id="indicatorDisaggregationModalLabel">Combined disaggregation requirements</h2>
                            <p>Select every dimension that applies. Reporting teams can combine all selected dimensions in one beneficiary row.</p>
                            <div class="me-disaggregation-indicator" data-disaggregation-indicator-name aria-live="polite"></div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="me-disaggregation-note mb-3">
                        <i class="feather-info" aria-hidden="true"></i>
                        <span><strong>Required</strong> blocks submission when the dimension is missing. <strong>Count</strong> identifies beneficiary dimensions used in the calculated beneficiary total. The reporting period is captured automatically from the quarterly, semi-annual or annual report.</span>
                    </div>

                    <div class="table-responsive border rounded-3">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Use</th>
                                    <th>Dimension</th>
                                    <th>Purpose</th>
                                    <th class="text-center">Required</th>
                                    <th class="text-center">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($disaggregationDimensions as $dimension)
                                    <tr>
                                        <td><input class="form-check-input" type="checkbox" name="dimensions[]" value="{{ $dimension->id }}" data-dimension-use="{{ $dimension->id }}"></td>
                                        <td><strong>{{ $dimension->name }}</strong><div class="small text-muted">{{ str_replace('_', ' ', $dimension->code) }}</div></td>
                                        <td class="small text-muted">{{ $dimension->description }}</td>
                                        <td class="text-center"><input class="form-check-input" type="checkbox" name="required_dimensions[]" value="{{ $dimension->id }}" data-dimension-required="{{ $dimension->id }}"></td>
                                        <td class="text-center"><input class="form-check-input" type="checkbox" name="numeric_dimensions[]" value="{{ $dimension->id }}" data-dimension-numeric="{{ $dimension->id }}" @checked($dimension->dimension_group === 'beneficiary')></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="me-disaggregation-save"><i class="feather-save" aria-hidden="true"></i>Save requirements</button>
                </div>
            </form>
        </div>
    </div>
</div>
