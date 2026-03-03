@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold">Create Prescreening Template</h4>
                <p class="text-muted mb-0">
                    Define prescreening rules and criteria
                </p>
            </div>

            <a href="{{ route('prescreening.templates.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Unable to save template.</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('prescreening.templates.store') }}">
            @csrf
            <input type="hidden" name="criteria_payload" id="criteriaPayload">

            {{-- ================= TEMPLATE INFO ================= --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body row g-3">

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Template Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                            <label class="form-check-label">Active Template</label>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" rows="3" class="form-control"></textarea>
                    </div>

                </div>
            </div>

            {{-- ================= CRITERIA ================= --}}
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="fw-semibold mb-0">Prescreening Criteria</h6>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addCriterionRow()">
                        <i class="feather-plus me-1"></i> Add Criterion
                    </button>
                </div>

                <div class="card-body p-0">
                    <table class="table table-bordered align-middle mb-0" id="criteriaTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Label</th>
                                <th>Field Key</th>
                                <th>Type</th>
                                <th>Min Value</th>
                                <th>Mandatory</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button class="btn btn-success">
                    <i class="feather-save me-1"></i> Save Template
                </button>
            </div>

        </form>
    </div>

    {{-- ================= JS ================= --}}
    <script>
        let rowIndex = 0;

        function slugify(text) {
            return text
                .toLowerCase()
                .trim()
                .replace(/[^\w\s]/gi, '')
                .replace(/\s+/g, '_');
        }

        function toggleMinValue(selectEl) {
            const minInput = selectEl.closest('tr').querySelector('.min-value');
            if (selectEl.value === 'numeric') {
                minInput.removeAttribute('disabled');
                minInput.closest('td').style.display = '';
            } else {
                minInput.value = '';
                minInput.setAttribute('disabled', 'disabled');
                minInput.closest('td').style.display = 'none';
            }
        }

        function parseMandatory(value, defaultValue = true) {
            if (value === undefined || value === null || value === '') {
                return defaultValue;
            }

            if (typeof value === 'boolean') {
                return value;
            }

            return !['0', 'false', 'off'].includes(String(value).toLowerCase());
        }

        function addCriterionRow(seed = null) {
            const tbody = document.querySelector('#criteriaTable tbody');

            const row = document.createElement('tr');
            row.innerHTML = `
        <td>${rowIndex + 1}</td>

        <td>
            <input type="text"
                   name="criteria[${rowIndex}][name]"
                   class="form-control label-input"
                   required>
        </td>

        <td>
            <input type="text"
                   name="criteria[${rowIndex}][field_key]"
                   class="form-control field-key"
                   readonly required>
        </td>

        <td>
            <select name="criteria[${rowIndex}][evaluation_type]"
                    class="form-select"
                    onchange="toggleMinValue(this)">
                <option value="yes_no">Yes / No</option>
                <option value="exists">Exists</option>
                <option value="numeric">Numeric</option>
            </select>
        </td>

        <td style="display:none;">
            <input type="number"
                   step="0.01"
                   name="criteria[${rowIndex}][min_value]"
                   class="form-control min-value"
                   disabled>
        </td>

        <td class="text-center">
            <input type="checkbox"
                   name="criteria[${rowIndex}][is_mandatory]"
                   value="1"
                   checked>
        </td>

        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="this.closest('tr').remove()">
                <i class="feather-trash"></i>
            </button>
        </td>
    `;

            tbody.appendChild(row);

            const labelInput = row.querySelector('.label-input');
            const fieldKeyInput = row.querySelector('.field-key');
            const typeInput = row.querySelector('select[name*="[evaluation_type]"]');
            const minValueInput = row.querySelector('.min-value');
            const mandatoryInput = row.querySelector('input[type="checkbox"][name*="[is_mandatory]"]');

            if (seed && typeof seed === 'object') {
                labelInput.value = (seed.name ?? '').toString();
                fieldKeyInput.value = (seed.field_key ?? '').toString();

                typeInput.value = (seed.evaluation_type ?? 'yes_no').toString();
                toggleMinValue(typeInput);

                if (typeInput.value === 'numeric' && seed.min_value !== null && seed.min_value !== undefined) {
                    minValueInput.value = seed.min_value;
                }

                mandatoryInput.checked = parseMandatory(seed.is_mandatory, true);
            }

            labelInput.addEventListener('input', function() {
                fieldKeyInput.value = slugify(this.value);
            });

            rowIndex++;
        }

        function buildCriteriaPayload() {
            return Array.from(document.querySelectorAll('#criteriaTable tbody tr'))
                .map(row => {
                    const labelInput = row.querySelector('.label-input');
                    const fieldKeyInput = row.querySelector('.field-key');
                    const typeInput = row.querySelector('select[name*="[evaluation_type]"]');
                    const minValueInput = row.querySelector('.min-value');
                    const mandatoryInput = row.querySelector('input[type="checkbox"][name*="[is_mandatory]"]');

                    const name = (labelInput?.value || '').trim();
                    const evaluationType = typeInput?.value || 'yes_no';
                    const minValue = evaluationType === 'numeric'
                        ? ((minValueInput?.value ?? '') === '' ? null : minValueInput.value)
                        : null;

                    return {
                        name,
                        field_key: ((fieldKeyInput?.value || '').trim() || slugify(name)),
                        evaluation_type: evaluationType,
                        min_value: minValue,
                        is_mandatory: !!mandatoryInput?.checked,
                    };
                })
                .filter(row => row.name !== '' || row.field_key !== '');
        }

        document.querySelector('form[action="{{ route('prescreening.templates.store') }}"]')
            ?.addEventListener('submit', function(event) {
                const criteria = buildCriteriaPayload();
                if (criteria.length === 0) {
                    event.preventDefault();
                    alert('Add at least one criterion before saving.');
                    return;
                }

                const payloadInput = document.getElementById('criteriaPayload');
                payloadInput.value = JSON.stringify(criteria);

                // Prevent max_input_vars truncation for large criteria sets.
                this.querySelectorAll('#criteriaTable [name^="criteria["]')
                    .forEach(el => el.disabled = true);
            });

        (() => {
            const payload = @json(old('criteria_payload'));
            if (!payload || typeof payload !== 'string') {
                return;
            }

            try {
                const decoded = JSON.parse(payload);
                if (!Array.isArray(decoded) || decoded.length === 0) {
                    return;
                }

                const tbody = document.querySelector('#criteriaTable tbody');
                tbody.innerHTML = '';
                rowIndex = 0;
                decoded.forEach(item => addCriterionRow(item));
            } catch (e) {
                // Ignore invalid old payload and let the user re-enter.
            }
        })();
    </script>
@endsection
