@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/assets/css/select2-custom.css') }}">
@endpush

@section('content')
    <div class="nxl-container">

        {{-- ================= HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Create Program Funding</h4>
                <p class="text-muted mb-0">
                    Register approved funding sources before budget allocation and commitments
                </p>
            </div>

            <a href="{{ route('finance.program-funding.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        {{-- ================= ERRORS ================= --}}
        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                <strong>Please correct the following:</strong>
                <ul class="mt-2 mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ================= MAIN CARD ================= --}}
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">

                <form method="POST" action="{{ route('finance.program-funding.store') }}" enctype="multipart/form-data">
                    @csrf

                    {{-- ================= SECTION 1 ================= --}}
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="feather-users me-1"></i> Ownership & Responsibility
                    </h6>

                    <div class="row mb-4">
                        @php
                            $currentNodeId = optional(auth()->user())->governance_node_id;
                        @endphp
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Governance Node *</label>
                            <select name="governance_node_id" class="form-select" required disabled>
                                <option value="">-- Select Node --</option>
                                @foreach ($nodes as $node)
                                    <option value="{{ $node->id }}"
                                        @selected(old('governance_node_id', $currentNodeId) == $node->id)>
                                        {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="governance_node_id"
                                value="{{ old('governance_node_id', $currentNodeId) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Program *</label>
                            <input type="text" name="program_name" class="form-control"
                                value="{{ old('program_name') }}" placeholder="Enter program name" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Funder *</label>
                            <select name="funder_id" class="form-select" required>
                                <option value="">-- Select Funder --</option>
                                @foreach ($funders as $funder)
                                    <option value="{{ $funder->id }}" @selected(old('funder_id') == $funder->id)>
                                        {{ $funder->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- ================= SECTION 2 ================= --}}
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="feather-dollar-sign me-1"></i> Funding Details
                    </h6>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Funding Type *</label>
                            <select name="funding_type" class="form-select" required>
                                <option value="">-- Select Type --</option>
                                <option value="grant" @selected(old('funding_type') == 'grant')>Grant</option>
                                <option value="allocation" @selected(old('funding_type') == 'allocation')>Government Allocation</option>
                                <option value="capital" @selected(old('funding_type') == 'capital')>Capital Injection</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Approved Amount *</label>
                            <input type="number" step="0.01" name="approved_amount" value="{{ old('approved_amount') }}"
                                class="form-control" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Currency *</label>
                            <input type="text" class="form-control currency-search mb-2"
                                placeholder="Search currency">
                            <select name="currency" class="form-select currency-select" required>
                                @php
                                    $currencyOptions = ['USD','EUR','GBP','GHS','KES','NGN','ZAR','UGX','TZS','RWF','XOF','XAF','EGP','MAD'];
                                @endphp
                                <option value="">-- Select Currency --</option>
                                @foreach ($currencyOptions as $currency)
                                    <option value="{{ $currency }}" @selected(old('currency') == $currency)>
                                        {{ $currency }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Funding Period *</label>
                            <div class="d-flex gap-2">
                                <input type="number" name="start_year" value="{{ old('start_year') }}"
                                    class="form-control" placeholder="Start Year" required>
                                <input type="number" name="end_year" value="{{ old('end_year') }}" class="form-control"
                                    placeholder="End Year" required>
                            </div>
                        </div>
                    </div>


                    {{-- ================= SECTION 3: AU STRATEGIC ALIGNMENT ================= --}}
                    <div class="au-alignment-section">
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="feather-globe me-1"></i> AU Strategic Alignment
                        </h6>

                    <div class="row mb-4">
                        {{-- CONTINENTAL INITIATIVE --}}
                        <div class="col-md-12 mb-3">
                            <div class="continental-check-wrapper">
                                <div class="form-check">
                                    <input type="checkbox" name="is_continental_initiative" value="1"
                                        class="form-check-input" id="is_continental_initiative"
                                        {{ old('is_continental_initiative') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_continental_initiative">
                                        <strong>Continental Initiative</strong>
                                        <small class="text-muted d-block">
                                            Check this if the program applies to all 55 AU member states
                                        </small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- BENEFICIARY MEMBER STATES --}}
                        <div class="col-md-6" id="memberStatesWrapper">
                            <label class="form-label fw-semibold">Beneficiary Member States</label>
                            <select name="member_state_ids[]" class="form-select checkbox-multiselect-target" multiple
                                id="memberStatesSelect"
                                data-type="member-states"
                                data-placeholder="Select member states...">
                                @foreach ($memberStates as $state)
                                    <option value="{{ $state->id }}"
                                        {{ in_array($state->id, old('member_state_ids', [])) ? 'selected' : '' }}>
                                        {{ $state->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- REGIONAL BLOCKS --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Regional Blocks (RECs)</label>
                            <select name="regional_block_ids[]" class="form-select checkbox-multiselect-target" multiple
                                id="regionalBlocksSelect"
                                data-type="regional-blocks"
                                data-placeholder="Select regional blocks...">
                                @foreach ($regionalBlocks as $block)
                                    <option value="{{ $block->id }}"
                                        {{ in_array($block->id, old('regional_block_ids', [])) ? 'selected' : '' }}>
                                        {{ $block->display_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4">
                        {{-- ASPIRATIONS --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Agenda 2063 Aspirations</label>
                            <select name="aspiration_ids[]" class="form-select checkbox-multiselect-target" multiple
                                id="aspirationsSelect"
                                data-type="aspirations"
                                data-placeholder="Select aspirations...">
                                @foreach ($aspirations as $aspiration)
                                    <option value="{{ $aspiration->id }}"
                                        {{ in_array($aspiration->id, old('aspiration_ids', [])) ? 'selected' : '' }}>
                                        Aspiration {{ $aspiration->number }}: {{ $aspiration->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- GOALS --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Agenda 2063 Goals</label>
                            <select name="goal_ids[]" class="form-select checkbox-multiselect-target" multiple
                                id="goalsSelect"
                                data-type="goals"
                                data-placeholder="Select goals...">
                                @foreach ($goals as $goal)
                                    <option value="{{ $goal->id }}"
                                        data-aspiration="{{ $goal->aspiration_id }}"
                                        {{ in_array($goal->id, old('goal_ids', [])) ? 'selected' : '' }}>
                                        Goal {{ $goal->number }}: {{ $goal->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4">
                        {{-- FLAGSHIP PROJECTS --}}
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">AU Flagship Projects</label>
                            <select name="flagship_project_ids[]" class="form-select checkbox-multiselect-target" multiple
                                id="flagshipProjectsSelect"
                                data-type="flagship-projects"
                                data-placeholder="Select flagship projects...">
                                @foreach ($flagshipProjects as $project)
                                    <option value="{{ $project->id }}"
                                        {{ in_array($project->id, old('flagship_project_ids', [])) ? 'selected' : '' }}>
                                        #{{ $project->number }}: {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    </div>{{-- End au-alignment-section --}}


                    {{-- ================= SECTION 4: SUPPORTING DOCUMENTS ================= --}}
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="feather-paperclip me-1"></i> Supporting Documents
                    </h6>

                    <div id="documents-wrapper">

                        <div class="row g-2 align-items-end mb-2 document-row">

                            {{-- DOCUMENT TYPE --}}
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Document Type *</label>
                                <select name="document_types[]" class="form-select" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="MoU">MoU</option>
                                    <option value="Grant Agreement">Grant Agreement</option>
                                    <option value="Approval Letter">Approval Letter</option>
                                    <option value="Budget Approval">Budget Approval</option>
                                    <option value="Supporting Document">Supporting Document</option>
                                </select>
                            </div>

                            {{-- DOCUMENT NAME --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Document Name *</label>
                                <input type="text" name="document_names[]" class="form-control"
                                    placeholder="e.g. Signed Grant Agreement 2025" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Document Description</label>
                                <input type="text" name="document_descriptions[]" class="form-control"
                                    placeholder="Summary or purpose (optional)">
                            </div>

                            {{-- FILE --}}
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Upload File *</label>
                                <input type="file" name="documents[]" class="form-control"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png" required>
                            </div>

                            {{-- REMOVE --}}
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-document"
                                    style="display:none;">
                                    <i class="feather-trash"></i>
                                </button>
                            </div>

                        </div>

                    </div>

                    <button type="button" id="add-document" class="btn btn-outline-primary btn-sm mt-2">
                        <i class="feather-plus me-1"></i> Add Another Document
                    </button>

                    <small class="text-muted d-block mt-2">
                        Each document must have a type, a clear name, and an uploaded file.
                    </small>




                    {{-- ================= NOTICE ================= --}}
                    <div class="alert alert-info d-flex align-items-start mt-4">
                        <i class="feather-info me-2 mt-1"></i>
                        <div class="small">
                            Funding will be saved as <strong>DRAFT</strong> and must be approved before use.
                        </div>
                    </div>

                    {{-- ================= ACTIONS ================= --}}
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('finance.program-funding.index') }}" class="btn btn-light">Cancel</a>
                        <button class="btn btn-primary">
                            <i class="feather-save me-1"></i> Save Funding
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    {{-- ================= JS ================= --}}
    <script src="{{ asset('admin/assets/js/checkbox-multiselect.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // ============ DOCUMENTS MANAGEMENT ============
            const wrapper = document.getElementById('documents-wrapper');
            const addBtn = document.getElementById('add-document');

            addBtn.addEventListener('click', () => {

                const row = document.createElement('div');
                row.className = 'row g-2 align-items-end mb-2 document-row';

                row.innerHTML = `
            <div class="col-md-3">
                <label class="form-label fw-semibold">Document Type *</label>
                <select name="document_types[]" class="form-select" required>
                    <option value="">-- Select Type --</option>
                    <option value="MoU">MoU</option>
                    <option value="Grant Agreement">Grant Agreement</option>
                    <option value="Approval Letter">Approval Letter</option>
                    <option value="Budget Approval">Budget Approval</option>
                    <option value="Supporting Document">Supporting Document</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">Document Name *</label>
                <input type="text"
                       name="document_names[]"
                       class="form-control"
                       placeholder="e.g. Signed Agreement"
                       required>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">Document Description</label>
                <input type="text"
                       name="document_descriptions[]"
                       class="form-control"
                       placeholder="Summary or purpose (optional)">
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold">Upload File *</label>
                <input type="file"
                       name="documents[]"
                       class="form-control"
                       required>
            </div>

            <div class="col-md-1 text-end">
                <button type="button"
                        class="btn btn-outline-danger btn-sm remove-document">
                    <i class="feather-trash"></i>
                </button>
            </div>
        `;

                wrapper.appendChild(row);
                toggleRemove();
            });

            wrapper.addEventListener('click', e => {
                if (e.target.closest('.remove-document')) {
                    e.target.closest('.document-row').remove();
                    toggleRemove();
                }
            });

            function toggleRemove() {
                const rows = wrapper.querySelectorAll('.document-row');
                rows.forEach(row => {
                    row.querySelector('.remove-document').style.display =
                        rows.length > 1 ? 'inline-block' : 'none';
                });
            }

            toggleRemove();

            // ============ CURRENCY SEARCH ============
            document.querySelectorAll('.currency-search').forEach(input => {
                const select = input.parentElement.querySelector('.currency-select');
                if (!select) return;

                input.addEventListener('input', () => {
                    const term = input.value.toLowerCase();
                    Array.from(select.options).forEach(option => {
                        if (option.value === '') {
                            option.hidden = false;
                            return;
                        }
                        option.hidden = term && !option.value.toLowerCase().includes(term);
                    });
                });
            });

            // ============ INITIALIZE CHECKBOX MULTI-SELECT ============
            const multiSelectInstances = {};

            document.querySelectorAll('.checkbox-multiselect-target').forEach(select => {
                const id = select.id;
                const type = select.dataset.type || 'default';
                const placeholder = select.dataset.placeholder || 'Select options...';

                multiSelectInstances[id] = new CheckboxMultiSelect(select, {
                    type: type,
                    placeholder: placeholder,
                    searchPlaceholder: 'Type to search...',
                    showTags: true,
                    maxTagsVisible: 4
                });
            });

            // ============ CONTINENTAL INITIATIVE TOGGLE ============
            const continentalCheckbox = document.getElementById('is_continental_initiative');
            const memberStatesWrapper = document.getElementById('memberStatesWrapper');

            function toggleMemberStates() {
                const memberStatesInstance = multiSelectInstances['memberStatesSelect'];
                if (continentalCheckbox.checked) {
                    memberStatesWrapper.style.opacity = '0.5';
                    if (memberStatesInstance) {
                        memberStatesInstance.setDisabled(true);
                        memberStatesInstance.clearAll();
                    }
                } else {
                    memberStatesWrapper.style.opacity = '1';
                    if (memberStatesInstance) {
                        memberStatesInstance.setDisabled(false);
                    }
                }
            }

            if (continentalCheckbox) {
                continentalCheckbox.addEventListener('change', toggleMemberStates);
                toggleMemberStates(); // Initial state
            }
        });
    </script>

@endsection
