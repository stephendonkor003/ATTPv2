@extends('layouts.app')
@section('title', 'Create Reporting Frequency')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-clock text-primary me-2"></i>
                    Create Reporting Frequency
                </h4>
                <p class="text-muted mb-0">Add how often indicators should be reported.</p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form action="{{ route('budget.me-configuration.frequencies.store') }}" method="POST" class="row g-3">
                    @csrf

                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                               value="{{ old('code') }}" required>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Interval Unit <span class="text-danger">*</span></label>
                        <select name="interval_unit" id="intervalUnitSelect"
                            class="form-select @error('interval_unit') is-invalid @enderror" required>
                            @foreach ($intervalOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('interval_unit', 'day') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('interval_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6" id="intervalValueWrap">
                        <label class="form-label">Interval Value</label>
                        <input type="number" name="interval_value" id="intervalValueInput" min="1"
                               class="form-control @error('interval_value') is-invalid @enderror"
                               value="{{ old('interval_value', 1) }}">
                        <small class="text-muted" id="intervalValueHint">
                            Example: 1 = every selected unit, 2 = every two selected units.
                        </small>
                        @error('interval_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" min="0"
                               class="form-control @error('sort_order') is-invalid @enderror"
                               value="{{ old('sort_order', 0) }}">
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                   {{ old('is_active', true) ? 'checked' : '' }}>
                            <span class="form-check-label">Active</span>
                        </label>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('budget.me-configuration.frequencies.index') }}" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="feather-save me-1"></i> Save Frequency</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const unitSelect = document.getElementById('intervalUnitSelect');
        const valueWrap = document.getElementById('intervalValueWrap');
        const valueInput = document.getElementById('intervalValueInput');
        const hint = document.getElementById('intervalValueHint');

        if (!unitSelect || !valueWrap || !valueInput || !hint) {
            return;
        }

        const fixedUnits = ['quarterly', 'year', 'annual', 'quinquennial'];

        function applyIntervalMode() {
            const unit = unitSelect.value;
            const isOnce = unit === 'once';
            const isFixed = fixedUnits.includes(unit);

            valueWrap.classList.toggle('d-none', isOnce);
            valueInput.disabled = isOnce;

            if (isOnce) {
                valueInput.value = '';
                hint.textContent = 'Once does not require an interval value.';
                return;
            }

            if (!valueInput.value || Number(valueInput.value) < 1) {
                valueInput.value = '1';
            }

            if (isFixed) {
                hint.textContent = 'Use 1 for standard ' + unit + ' reporting.';
            } else {
                hint.textContent = 'Example: 1 = every selected unit, 2 = every two selected units.';
            }
        }

        unitSelect.addEventListener('change', applyIntervalMode);
        applyIntervalMode();
    });
</script>
@endpush
