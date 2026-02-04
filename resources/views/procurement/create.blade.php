@extends('layouts.app')

    @push('styles')
        <style>
        .procurement-create .page-hero {
            background: linear-gradient(120deg, #0f766e 0%, #0b5f59 40%, #0e7490 100%);
            color: #fff;
            border: none;
        }

        .procurement-create .page-hero .subtitle {
            color: rgba(255, 255, 255, 0.75);
        }

        .procurement-create .section-card {
            border: 1px solid #e6edf2;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
        }

        .procurement-create .section-header {
            background: #f8fafc;
            border-bottom: 1px solid #e6edf2;
        }

        .procurement-create .section-step {
            font-size: 0.75rem;
            font-weight: 600;
            color: #0f766e;
            background: rgba(15, 118, 110, 0.12);
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
        }

        .procurement-create .plan-results .list-group-item {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 0.5rem;
        }

        .procurement-create .plan-results .list-group-item:hover {
            border-color: #14b8a6;
            background: rgba(20, 184, 166, 0.08);
        }

        .procurement-create .plan-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.75rem;
        }

        @media (max-width: 1400px) {
            .procurement-create .plan-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        @media (max-width: 1200px) {
            .procurement-create .plan-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 992px) {
            .procurement-create .plan-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .procurement-create .plan-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .procurement-create .plan-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }
        }

        .procurement-create .plan-tile {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 0.75rem;
            text-align: left;
            background: #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .procurement-create .plan-tile:hover {
            transform: translateY(-2px);
            border-color: #14b8a6;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12);
        }

        .procurement-create .plan-color-0 {
            background: #f0f9ff;
        }

        .procurement-create .plan-color-1 {
            background: #ecfdf5;
        }

        .procurement-create .plan-color-2 {
            background: #fff7ed;
        }

        .procurement-create .plan-color-3 {
            background: #fdf4ff;
        }

        .procurement-create .plan-color-4 {
            background: #fef2f2;
        }

        .procurement-create .plan-color-5 {
            background: #f8fafc;
        }

        .procurement-create .plan-color-6 {
            background: #f1f5f9;
        }

        .procurement-create .selected-plan {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem;
            background: #f8fafc;
        }

        .procurement-create .selected-plan .selected-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #0f172a;
        }

        .procurement-create .selected-plan .selected-meta {
            font-size: 0.85rem;
            color: #475569;
        }

        .procurement-create .summary-card {
            border: 1px solid #e6edf2;
            background: #ffffff;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
        }

        .procurement-create .summary-card .summary-title {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
        }

        .procurement-create .summary-list {
            list-style: none;
            padding-left: 0;
            margin-bottom: 1.5rem;
        }

        .procurement-create .summary-list li {
            padding: 0.6rem 0.75rem;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 0.6rem;
            color: #475569;
            font-size: 0.9rem;
        }

        .procurement-create .helper-panel {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 1rem;
            color: #334155;
        }

        .procurement-create .ck-editor__editable {
            min-height: 220px;
        }

        .procurement-create .vendor-multiselect {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            background: #ffffff;
            min-height: 48px;
            font-size: 0.95rem;
        }

        .procurement-create .vendor-multiselect:focus {
            border-color: #14b8a6;
            box-shadow: 0 0 0 2px rgba(20, 184, 166, 0.15);
            outline: none;
        }

        .procurement-create .select2-container--default .select2-selection--multiple {
            min-height: 48px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 6px 10px;
            background: #ffffff;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
        }

        .procurement-create .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 0;
            margin: 0;
        }

        .procurement-create .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: linear-gradient(135deg, rgba(20, 184, 166, 0.12), rgba(59, 130, 246, 0.12));
            border: 1px solid rgba(20, 184, 166, 0.35);
            color: #0f766e;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.78rem;
            font-weight: 600;
            margin: 0;
        }

        .procurement-create .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #0f766e;
            margin-right: 6px;
        }

        .procurement-create .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #14b8a6;
            box-shadow: 0 0 0 2px rgba(20, 184, 166, 0.15);
        }

        .procurement-create .select2-container--default .select2-search--inline .select2-search__field {
            margin-top: 2px;
            font-size: 0.9rem;
            font-family: inherit;
            color: #0f172a;
        }

        .procurement-create .select2-container--default .select2-selection--multiple .select2-search--inline {
            flex: 1 1 auto;
        }

        .procurement-create .select2-container--default .select2-selection--multiple .select2-selection__placeholder {
            color: #94a3b8;
            font-size: 0.85rem;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof $ === 'undefined' || !$.fn.select2) {
                return;
            }

            const $vendorSelect = $('#vendor_categories');
            if (!$vendorSelect.length) {
                return;
            }

            $vendorSelect.select2({
                width: '100%',
                placeholder: 'Select vendor categories',
                allowClear: true,
                closeOnSelect: false
            });
        });
    </script>
@endpush

@section('content')
    <div class="nxl-container procurement-create">

        {{-- ================= HERO ================= --}}
        <div class="card page-hero mb-4">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center">
                <div>
                    <h4 class="mb-2">Create Procurement</h4>
                    <p class="subtitle mb-0">
                        Capture the procurement basics, confirm the plan reference, and publish a clean record.
                    </p>
                </div>
                <a href="{{ route('procurements.index') }}" class="btn btn-light mt-3 mt-lg-0">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        {{-- ================= FORM ================= --}}
        <form method="POST" action="{{ route('procurements.store') }}" id="procurementForm">
            @csrf

            {{-- ================= ERRORS ================= --}}
            @if ($errors->any())
                <div class="alert alert-danger mb-4">
                    <h6 class="fw-semibold mb-2">Please fix the following errors:</h6>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-4">
                <div class="col-lg-8">
                    {{-- ================= SECTION: BASICS ================= --}}
                    <div class="card section-card">
                        <div class="card-header section-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Procurement Basics</h6>
                                <small class="text-muted">Set the category and budget context.</small>
                            </div>
                            <span class="section-step">Step 1</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Procurement Category <span class="text-danger">*</span>
                                    </label>
                                    <select name="resource_id" class="form-control" required>
                                        <option value="">-- Select Category --</option>
                                        @foreach ($resources as $r)
                                            <option value="{{ $r->id }}"
                                                {{ old('resource_id') == $r->id ? 'selected' : '' }}>
                                                {{ $r->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">
                                        Determines the type of procurement.
                                    </small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Fiscal Year <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="fiscal_year" value="{{ old('fiscal_year') }}"
                                        class="form-control" placeholder="e.g. 2025 / 2026" required>
                                    <small class="text-muted">
                                        Financial year for this procurement.
                                    </small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Estimated Budget
                                    </label>
                                    <input type="number" step="0.01" name="estimated_budget"
                                        value="{{ old('estimated_budget') }}" class="form-control" placeholder="0.00">
                                    <small class="text-muted">
                                        Optional initial estimate.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ================= SECTION: REFERENCE ================= --}}
                    <div class="card section-card mt-4">
                        <div class="card-header section-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Plan Reference</h6>
                                <small class="text-muted">Find the procurement plan code to link.</small>
                            </div>
                            <span class="section-step">Step 2</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">
                                        Reference Number
                                    </label>

                                    <div class="input-group">
                                        <input type="text" name="reference_no" id="reference_no"
                                            value="{{ old('reference_no') }}" class="form-control"
                                            placeholder="Search or select a procurement plan code">

                                        <button type="button" class="btn btn-outline-primary" id="searchPlanBtn">
                                            <i class="feather-search me-1"></i>
                                            Search Codes
                                        </button>
                                    </div>

                                    <div id="planSearchResults" class="plan-results mt-3"></div>

                                    <div class="helper-panel mt-3">
                                        Search codes by title or code, verify the plan details, then append it as the
                                        reference number. Use the Change Procurement Plan ID button to reselect.
                                    </div>

                                    <div id="selectedPlanSummary" class="selected-plan mt-3 d-none">
                                        <div class="selected-title">Selected Procurement Plan</div>
                                        <div class="selected-meta" id="selectedPlanMeta">N/A</div>
                                        <button type="button" class="btn btn-outline-dark btn-sm mt-2"
                                            id="changePlanBtn">
                                            Change Procurement Plan ID
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ================= SECTION: VISIBILITY ================= --}}
                    <div class="card section-card mt-4">
                        <div class="card-header section-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Visibility &amp; Vendor Access</h6>
                                <small class="text-muted">Decide whether this procurement is public or vendor-only.</small>
                            </div>
                            <span class="section-step">Step 3</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Visibility <span class="text-danger">*</span>
                                    </label>
                                    <select name="visibility_type" id="visibility_type" class="form-control" required>
                                        <option value="public" {{ old('visibility_type', 'public') === 'public' ? 'selected' : '' }}>
                                            Public (shown on public portal)
                                        </option>
                                        <option value="vendor_group" {{ old('visibility_type') === 'vendor_group' ? 'selected' : '' }}>
                                            Selected Vendor Group (vendor portal only)
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="vendorCategoryWrapper"
                                    style="{{ old('visibility_type', 'public') === 'vendor_group' ? '' : 'display:none;' }}">
                                    <label class="form-label fw-semibold">
                                        Vendor Categories <span class="text-danger">*</span>
                                    </label>
                                    <select name="vendor_categories[]" id="vendor_categories"
                                        class="form-control vendor-multiselect select2-multiple" multiple>
                                        @forelse ($vendorCategories as $category)
                                            <option value="{{ $category }}"
                                                {{ collect(old('vendor_categories', []))->contains($category) ? 'selected' : '' }}>
                                                {{ $category }}
                                            </option>
                                        @empty
                                            <option value="" disabled>No vendor categories configured</option>
                                        @endforelse
                                    </select>
                                    <small class="text-muted">
                                        Vendors in these categories will see and apply in the vendor portal.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ================= SECTION: APPLICATION WINDOW ================= --}}
                    <div class="card section-card mt-4">
                        <div class="card-header section-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Application Window</h6>
                                <small class="text-muted">Define how long vendors can submit or edit applications.</small>
                            </div>
                            <span class="section-step">Step 4</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Application Start Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" name="application_start_date" id="application_start_date"
                                        value="{{ old('application_start_date') }}" class="form-control" required>
                                    <small class="text-muted">
                                        First day vendors can submit applications.
                                    </small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Duration (Days) <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" name="application_duration_days" id="application_duration_days"
                                        value="{{ old('application_duration_days') }}" class="form-control" min="1"
                                        max="365" required>
                                    <small class="text-muted">
                                        Number of days the application stays open.
                                    </small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Application End Date
                                    </label>
                                    <input type="date" name="application_end_date" id="application_end_date"
                                        value="{{ old('application_end_date') }}" class="form-control" readonly>
                                    <small class="text-muted">
                                        Calculated automatically from the start date and duration.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ================= SECTION: DETAILS ================= --}}
                    <div class="card section-card mt-4">
                        <div class="card-header section-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Procurement Details</h6>
                                <small class="text-muted">Provide a clear title and scope.</small>
                            </div>
                            <span class="section-step">Step 5</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">
                                        Procurement Title <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="title" value="{{ old('title') }}" class="form-control"
                                        placeholder="e.g. Supply of ICT Equipment" required>
                                    <small class="text-muted">
                                        This will be visible to evaluators and bidders.
                                    </small>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">
                                        Procurement Description <span class="text-danger">*</span>
                                    </label>
                                    <textarea name="description" id="procurement_description" rows="6"
                                        class="form-control">{{ old('description') }}</textarea>
                                    <small class="text-muted">
                                        Provide detailed scope, requirements, timelines, and expectations.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card summary-card position-sticky" style="top: 1.5rem;">
                        <div class="card-body">
                            <div class="summary-title mb-3">Review &amp; Submit</div>
                            <ul class="summary-list">
                                <li>Select a procurement category and fiscal year.</li>
                                <li>Verify the procurement plan code before appending.</li>
                                <li>Choose whether the procurement is public or vendor-only.</li>
                                <li>Set the application window and duration for vendors.</li>
                                <li>Use a descriptive title that matches the plan scope.</li>
                                <li>Keep the description concise and actionable.</li>
                            </ul>

                            <button class="btn btn-success w-100" id="saveBtn">
                                <i class="feather-save me-1"></i>
                                Save Procurement
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>

    {{-- ================= CKEDITOR ================= --}}
    <script src="https://cdn.ckeditor.com/ckeditor5/40.2.0/classic/ckeditor.js"></script>

    <script>
        let procurementEditor;

        document.addEventListener('DOMContentLoaded', function() {
            ClassicEditor
                .create(document.querySelector('#procurement_description'), {
                    toolbar: [
                        'heading', '|',
                        'bold', 'italic', 'underline', 'link',
                        'bulletedList', 'numberedList',
                        'blockQuote', '|',
                        'insertTable', 'undo', 'redo'
                    ]
                })
                .then(editor => {
                    procurementEditor = editor;
                })
                .catch(error => {
                    console.error(error);
                });

            // 🔑 Ensure editor content is submitted
            document.getElementById('procurementForm')
                .addEventListener('submit', function() {

                    if (procurementEditor) {
                        document.getElementById('procurement_description').value =
                            procurementEditor.getData();
                    }
                });

            const startDateInput = document.getElementById('application_start_date');
            const durationInput = document.getElementById('application_duration_days');
            const endDateInput = document.getElementById('application_end_date');
            const visibilityInput = document.getElementById('visibility_type');
            const vendorCategoryWrapper = document.getElementById('vendorCategoryWrapper');
            const vendorCategoriesInput = document.getElementById('vendor_categories');

            const toggleVendorCategories = () => {
                if (!visibilityInput || !vendorCategoryWrapper || !vendorCategoriesInput) {
                    return;
                }

                const isVendorGroup = visibilityInput.value === 'vendor_group';
                vendorCategoryWrapper.style.display = isVendorGroup ? 'block' : 'none';
                vendorCategoriesInput.required = isVendorGroup;

                if (!isVendorGroup) {
                    Array.from(vendorCategoriesInput.options).forEach(option => {
                        option.selected = false;
                    });
                }
            };

            const updateEndDate = () => {
                if (!startDateInput || !durationInput || !endDateInput) {
                    return;
                }

                const startValue = startDateInput.value;
                const durationValue = parseInt(durationInput.value, 10);

                if (!startValue || !durationValue || durationValue < 1) {
                    endDateInput.value = '';
                    return;
                }

                const startDate = new Date(`${startValue}T00:00:00`);
                startDate.setDate(startDate.getDate() + durationValue);

                const year = startDate.getFullYear();
                const month = String(startDate.getMonth() + 1).padStart(2, '0');
                const day = String(startDate.getDate()).padStart(2, '0');

                endDateInput.value = `${year}-${month}-${day}`;
            };

            if (startDateInput) {
                startDateInput.addEventListener('change', updateEndDate);
            }

            if (durationInput) {
                durationInput.addEventListener('input', updateEndDate);
            }

            updateEndDate();
            toggleVendorCategories();

            // Select2 init moved to the scripts stack so it runs after assets load.

            if (visibilityInput) {
                visibilityInput.addEventListener('change', toggleVendorCategories);
            }

            const referenceInput = document.getElementById('reference_no');
            const searchPlanBtn = document.getElementById('searchPlanBtn');
            const planResults = document.getElementById('planSearchResults');
            const selectedPlanSummary = document.getElementById('selectedPlanSummary');
            const selectedPlanMeta = document.getElementById('selectedPlanMeta');
            const changePlanBtn = document.getElementById('changePlanBtn');
            const planLookupUrl = "{{ route('procurement.plans.lookup') }}";
            const planModalEl = document.getElementById('planVerifyModal');
            const planModal = planModalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(planModalEl) : null;
            let selectedPlan = null;
            let searchTimer = null;

        const setModalValue = (id, value) => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value || 'N/A';
            }
        };

        const updateSelectedPlanSummary = (plan) => {
            if (!selectedPlanSummary || !selectedPlanMeta) {
                return;
            }

            if (!plan) {
                selectedPlanSummary.classList.add('d-none');
                selectedPlanMeta.textContent = 'N/A';
                return;
            }

            const metaParts = [
                plan.procurement_code,
                plan.title,
                plan.fiscal_year ? `FY ${plan.fiscal_year}` : null
            ].filter(Boolean);

            selectedPlanMeta.textContent = metaParts.length ? metaParts.join(' • ') : 'N/A';
            selectedPlanSummary.classList.remove('d-none');
        };

        const setSelectionState = (plan) => {
            if (!referenceInput || !searchPlanBtn) {
                return;
            }

            if (plan) {
                referenceInput.value = plan.procurement_code || '';
                referenceInput.readOnly = true;
                searchPlanBtn.disabled = true;
                updateSelectedPlanSummary(plan);
            } else {
                referenceInput.readOnly = false;
                searchPlanBtn.disabled = false;
                updateSelectedPlanSummary(null);
            }
        };

        const renderPlanResults = (plans) => {
            planResults.innerHTML = '';

            if (!plans.length) {
                planResults.innerHTML = '<div class="text-muted small">No matching procurement codes found.</div>';
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'plan-grid';

            plans.forEach((plan, index) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = `plan-tile plan-color-${index % 7}`;
                item.innerHTML = `
                    <div class="fw-semibold text-dark">${plan.procurement_code ?? 'N/A'}</div>
                    <div class="small text-muted">${plan.title ?? 'Untitled Plan'}</div>
                    <div class="small text-muted mt-1">${plan.fiscal_year ? `FY ${plan.fiscal_year}` : 'N/A'}</div>
                `;
                item.addEventListener('click', () => {
                    selectedPlan = plan;
                    setModalValue('verifyCode', plan.procurement_code);
                    setModalValue('verifyTitle', plan.title);
                    setModalValue('verifyFiscalYear', plan.fiscal_year);
                    setModalValue('verifyBudget', plan.estimated_budget);
                    setModalValue('verifyProgramPlan', plan.program_plan);
                    setModalValue('verifyActivity', plan.activity);
                    setModalValue('verifySubActivity', plan.sub_activity);
                    setModalValue('verifyMethod', plan.method);
                    setModalValue('verifyGeographic', plan.geographic);
                    setModalValue('verifyStage', plan.stage);
                    setModalValue('verifyStatus', plan.status);
                    setModalValue('verifyStartDate', plan.estimated_start_date);
                    setModalValue('verifyEndDate', plan.estimated_end_date);

                    if (planModal) {
                        planModal.show();
                    }
                });
                grid.appendChild(item);
            });

            planResults.appendChild(grid);
        };

        const fetchPlanCodes = async (query = '') => {
            const url = new URL(planLookupUrl, window.location.origin);
            if (query) {
                url.searchParams.set('q', query);
            }

            const response = await fetch(url.toString());
            if (!response.ok) {
                throw new Error('Failed to load procurement codes.');
            }

            const plans = await response.json();
            renderPlanResults(plans);
        };

        if (searchPlanBtn) {
            searchPlanBtn.addEventListener('click', () => {
                fetchPlanCodes(referenceInput?.value.trim() || '')
                    .catch(() => {
                        planResults.innerHTML = '<div class="text-danger small">Unable to load procurement codes.</div>';
                    });
            });
        }

        if (referenceInput) {
            referenceInput.addEventListener('input', () => {
                const query = referenceInput.value.trim();
                if (query.length < 2) {
                    if (planResults) {
                        planResults.innerHTML = '';
                    }
                    return;
                }

                if (searchTimer) {
                    clearTimeout(searchTimer);
                }

                searchTimer = setTimeout(() => {
                    fetchPlanCodes(query)
                        .catch(() => {
                            if (planResults) {
                                planResults.innerHTML = '<div class="text-danger small">Unable to load procurement codes.</div>';
                            }
                        });
                }, 350);
            });
        }

        const appendPlanBtn = document.getElementById('appendPlanBtn');
        if (appendPlanBtn) {
            appendPlanBtn.addEventListener('click', () => {
                if (!selectedPlan || !referenceInput) {
                    return;
                }

                setSelectionState(selectedPlan);

                if (planResults) {
                    planResults.innerHTML = '';
                }

                selectedPlan = null;

                if (planModal) {
                    planModal.hide();
                }
            });
        }

        if (changePlanBtn) {
            changePlanBtn.addEventListener('click', () => {
                setSelectionState(null);
                selectedPlan = null;
                if (referenceInput) {
                    referenceInput.value = '';
                    referenceInput.focus();
                }
            });
        }

            if (referenceInput && referenceInput.value.trim()) {
                setSelectionState({
                    procurement_code: referenceInput.value.trim()
                });
            }
        });
    </script>
@endsection

@push('modals')
    <div class="modal fade" id="planVerifyModal" tabindex="-1" aria-labelledby="planVerifyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="planVerifyModalLabel">Verify Procurement Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Procurement Code</div>
                            <div class="fw-semibold" id="verifyCode">N/A</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Title</div>
                            <div class="fw-semibold" id="verifyTitle">N/A</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Fiscal Year</div>
                            <div class="fw-semibold" id="verifyFiscalYear">N/A</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Estimated Budget</div>
                            <div class="fw-semibold" id="verifyBudget">N/A</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Program Plan</div>
                            <div class="fw-semibold" id="verifyProgramPlan">N/A</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Activity</div>
                            <div class="fw-semibold" id="verifyActivity">N/A</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Sub Activity</div>
                            <div class="fw-semibold" id="verifySubActivity">N/A</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Method</div>
                            <div class="fw-semibold" id="verifyMethod">N/A</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Geographic</div>
                            <div class="fw-semibold" id="verifyGeographic">N/A</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Stage / Status</div>
                            <div class="fw-semibold">
                                <span id="verifyStage">N/A</span> / <span id="verifyStatus">N/A</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Estimated Start Date</div>
                            <div class="fw-semibold" id="verifyStartDate">N/A</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Estimated End Date</div>
                            <div class="fw-semibold" id="verifyEndDate">N/A</div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        Confirm these details match the procurement plan you want to use as the reference number.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="appendPlanBtn">
                        <i class="feather-check-circle me-1"></i>
                        Append Data
                    </button>
                </div>
            </div>
        </div>
    </div>
@endpush
