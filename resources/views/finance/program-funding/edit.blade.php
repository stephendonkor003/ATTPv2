@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/assets/css/select2-custom.css') }}">
@endpush

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Edit Program Funding</h4>
                <p class="text-muted mb-0">
                    All approved and draft records can be adjusted; approvals remain intact unless reset.
                </p>
            </div>
            <a href="{{ route('finance.program-funding.show', $programFunding) }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

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

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <form method="POST" action="{{ route('finance.program-funding.update', $programFunding) }}">
                    @csrf
                    @method('PUT')

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Program Name</label>
                            <input type="text" name="program_name" class="form-control"
                                value="{{ old('program_name', $programFunding->program_name) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Funder</label>
                            <select name="funder_id" class="form-select" required>
                                <option value="">-- Select Funder --</option>
                                @foreach ($funders as $funder)
                                    <option value="{{ $funder->id }}"
                                        @selected(old('funder_id', $programFunding->funder_id) == $funder->id)>
                                        {{ $funder->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Governance Node</label>
                            <select name="governance_node_id" class="form-select" required>
                                <option value="">-- Select Node --</option>
                                @foreach ($nodes as $node)
                                    <option value="{{ $node->id }}"
                                        @selected($programFunding->governance_node_id == $node->id)>
                                        {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Approved Amount</label>
                            <input type="number" step="0.01" name="approved_amount"
                                value="{{ $programFunding->approved_amount }}" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Currency</label>
                            <input type="text" class="form-control currency-search mb-2"
                                placeholder="Search currency">
                            <select name="currency" class="form-select currency-select" required>
                                @php
                                    $currencyOptions = ['USD','EUR','GBP','GHS','KES','NGN','ZAR','UGX','TZS','RWF','XOF','XAF','EGP','MAD'];
                                @endphp
                                <option value="">-- Select Currency --</option>
                                @foreach ($currencyOptions as $currency)
                                    <option value="{{ $currency }}"
                                        @selected($programFunding->currency === $currency)>
                                        {{ $currency }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Funding Type</label>
                            <select name="funding_type" class="form-select" required>
                                <option value="">-- Select Type --</option>
                                <option value="grant" @selected(old('funding_type', $programFunding->funding_type) === 'grant')>
                                    Grant
                                </option>
                                <option value="allocation" @selected(old('funding_type', $programFunding->funding_type) === 'allocation')>
                                    Government Allocation
                                </option>
                                <option value="capital" @selected(old('funding_type', $programFunding->funding_type) === 'capital')>
                                    Capital Injection
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label>Start Year</label>
                            <input name="start_year" value="{{ $programFunding->start_year }}" class="form-control"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label>End Year</label>
                            <input name="end_year" value="{{ $programFunding->end_year }}" class="form-control" required>
                        </div>
                    </div>

                    {{-- ================= AU STRATEGIC ALIGNMENT ================= --}}
                    <div class="au-alignment-section">
                        <h6 class="fw-bold text-primary mb-3 mt-4">
                            <i class="feather-globe me-1"></i> AU Strategic Alignment
                        </h6>

                    @php
                        $selectedMemberStates = old('member_state_ids', $programFunding->memberStates->pluck('id')->toArray());
                        $selectedRegionalBlocks = old('regional_block_ids', $programFunding->regionalBlocks->pluck('id')->toArray());
                        $selectedAspirations = old('aspiration_ids', $programFunding->aspirations->pluck('id')->toArray());
                        $selectedGoals = old('goal_ids', $programFunding->goals->pluck('id')->toArray());
                        $selectedFlagshipProjects = old('flagship_project_ids', $programFunding->flagshipProjects->pluck('id')->toArray());
                    @endphp

                    <div class="row mb-4">
                        {{-- CONTINENTAL INITIATIVE --}}
                        <div class="col-md-12 mb-3">
                            <div class="continental-check-wrapper">
                                <div class="form-check">
                                    <input type="checkbox" name="is_continental_initiative" value="1"
                                        class="form-check-input" id="is_continental_initiative"
                                        {{ old('is_continental_initiative', $programFunding->is_continental_initiative) ? 'checked' : '' }}>
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
                                        {{ in_array($state->id, $selectedMemberStates) ? 'selected' : '' }}>
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
                                        {{ in_array($block->id, $selectedRegionalBlocks) ? 'selected' : '' }}>
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
                                        {{ in_array($aspiration->id, $selectedAspirations) ? 'selected' : '' }}>
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
                                        {{ in_array($goal->id, $selectedGoals) ? 'selected' : '' }}>
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
                                        {{ in_array($project->id, $selectedFlagshipProjects) ? 'selected' : '' }}>
                                        #{{ $project->number }}: {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    </div>{{-- End au-alignment-section --}}

                    <div class="alert alert-warning">
                        Editing is locked once submitted for approval.
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">
                            <i class="feather-save me-1"></i> Update Funding
                        </button>
                        <a href="{{ route('finance.program-funding.show', $programFunding) }}" class="btn btn-light">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="{{ asset('admin/assets/js/checkbox-multiselect.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Currency search
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
                toggleMemberStates();
            }
        });
    </script>
@endsection
