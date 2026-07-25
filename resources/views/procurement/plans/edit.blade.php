@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">Edit Procurement Plan</h4>
                <p class="text-muted mb-0">
                    Update procurement plan: <strong>{{ $plan->procurement_code }}</strong>
                </p>
            </div>

            <a href="{{ route('procurement.plans.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back to Plans
            </a>
        </div>

        {{-- ================= FLASH MESSAGES ================= --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="feather-check-circle me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="feather-alert-circle me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="feather-alert-circle me-2"></i>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- ================= FORM ================= --}}
        <form action="{{ route('procurement.plans.update', $plan) }}" method="POST" id="procurementPlanForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="is_code_auto_generated" id="is_code_auto_generated"
                value="{{ old('is_code_auto_generated', $plan->is_code_auto_generated ? '1' : '0') }}">

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Plan Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        {{-- Procurement Code --}}
                        <div class="col-md-6">
                            <label for="procurement_code" class="form-label">Procurement Code <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('procurement_code') is-invalid @enderror"
                                    id="procurement_code" name="procurement_code"
                                    value="{{ old('procurement_code', $plan->procurement_code) }}"
                                    placeholder="ET-AUC-XXXXXX-CS-CQS" required>
                                <button class="btn btn-outline-primary" type="button" id="generateCodeBtn">
                                    <i class="feather-refresh-cw me-1"></i> Regenerate
                                </button>
                            </div>
                            <small class="text-muted">Format: ET-AUC-XXXXXX-CS-CQS</small>
                            @error('procurement_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Title --}}
                        <div class="col-md-6">
                            <label for="title" class="form-label">Procurement Title <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title"
                                name="title" value="{{ old('title', $plan->title) }}"
                                placeholder="Enter procurement title" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Activity --}}
                        <div class="col-md-6">
                            <label for="activity_id" class="form-label">Activity <span class="text-danger">*</span></label>
                            <select class="form-select @error('activity_id') is-invalid @enderror" id="activity_id"
                                name="activity_id" required>
                                <option value="">Select Activity</option>
                                @foreach ($activities as $activity)
                                    @php
                                        $activityNodeId = $activity->governance_node_id
                                            ?? $activity->project?->governance_node_id;
                                        $activityNodeName = $activity->governanceNode?->name
                                            ?? $activity->project?->governanceNode?->name;
                                    @endphp
                                    <option value="{{ $activity->id }}"
                                        data-node-id="{{ $activityNodeId }}"
                                        {{ old('activity_id', $plan->activity_id) == $activity->id ? 'selected' : '' }}>
                                        {{ $activity->name }}
                                        @if ($activityNodeName)
                                            — {{ $activityNodeName }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('activity_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Sub Activity --}}
                        <div class="col-md-6">
                            <label for="sub_activity_id" class="form-label">Sub Activity</label>
                            <select class="form-select @error('sub_activity_id') is-invalid @enderror" id="sub_activity_id"
                                name="sub_activity_id">
                                <option value="">Select Sub Activity</option>
                                @foreach ($subActivities as $subActivity)
                                    <option value="{{ $subActivity->id }}"
                                        {{ old('sub_activity_id', $plan->sub_activity_id) == $subActivity->id ? 'selected' : '' }}>
                                        {{ $subActivity->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('sub_activity_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted" id="subActivityHelp">
                                Only sub-activities with an approved commitment are offered after changing the activity.
                            </small>
                        </div>

                        {{-- Method Planned --}}
                        <div class="col-md-6">
                            <label for="method_planned_id" class="form-label">Procurement Method <span
                                    class="text-danger">*</span></label>
                            <select class="form-select @error('method_planned_id') is-invalid @enderror"
                                id="method_planned_id" name="method_planned_id" required>
                                <option value="">Select Method</option>
                                @foreach ($methods as $method)
                                    <option value="{{ $method->id }}"
                                        data-target-days="{{ $method->method_target_days }}"
                                        {{ old('method_planned_id', $plan->method_planned_id) == $method->id ? 'selected' : '' }}>
                                        {{ $method->method_name }} ({{ $method->method_target_days }} days)
                                    </option>
                                @endforeach
                            </select>
                            @error('method_planned_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Program Plan --}}
                        <div class="col-md-6">
                            <label for="program_plan_id" class="form-label">Procurement Plan <span
                                    class="text-danger">*</span></label>
                            <select class="form-select @error('program_plan_id') is-invalid @enderror"
                                id="program_plan_id" name="program_plan_id" required>
                                <option value="">Select procurement plan</option>
                                @foreach ($programPlans as $programPlan)
                                    <option value="{{ $programPlan->id }}"
                                        data-node-id="{{ $programPlan->governance_node_id }}"
                                        {{ old('program_plan_id', $plan->program_plan_id) == $programPlan->id ? 'selected' : '' }}>
                                        {{ $programPlan->name }}
                                        @if ($programPlan->governanceNode)
                                            — {{ $programPlan->governanceNode->name }}
                                        @endif
                                        @unless ($programPlan->is_active)
                                            (Archived)
                                        @endunless
                                    </option>
                                @endforeach
                            </select>
                            @error('program_plan_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Geographic --}}
                        <div class="col-md-6">
                            <label for="geographic_id" class="form-label">Geographic Location</label>
                            <select class="form-select @error('geographic_id') is-invalid @enderror" id="geographic_id"
                                name="geographic_id">
                                <option value="">Select Geographic</option>
                                @foreach ($geographics as $geographic)
                                    <option value="{{ $geographic->id }}"
                                        {{ old('geographic_id', $plan->geographic_id) == $geographic->id ? 'selected' : '' }}>
                                        {{ $geographic->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('geographic_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Is Launched --}}
                        <div class="col-md-4">
                            <label for="is_launched" class="form-label">Is Launched <span
                                    class="text-danger">*</span></label>
                            <select class="form-select @error('is_launched') is-invalid @enderror" id="is_launched"
                                name="is_launched" required>
                                <option value="0"
                                    {{ old('is_launched', $plan->is_launched) == false ? 'selected' : '' }}>No</option>
                                <option value="1"
                                    {{ old('is_launched', $plan->is_launched) == true ? 'selected' : '' }}>Yes</option>
                            </select>
                            @error('is_launched')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Estimated Start Date --}}
                        <div class="col-md-4">
                            <label for="estimated_start_date" class="form-label">Estimated Start Date <span
                                    class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('estimated_start_date') is-invalid @enderror"
                                id="estimated_start_date" name="estimated_start_date"
                                value="{{ old('estimated_start_date', $plan->estimated_start_date?->format('Y-m-d')) }}"
                                required>
                            @error('estimated_start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Estimated End Date --}}
                        <div class="col-md-4">
                            <label for="estimated_end_date" class="form-label">Estimated End Date</label>
                            <input type="date" class="form-control @error('estimated_end_date') is-invalid @enderror"
                                id="estimated_end_date" name="estimated_end_date"
                                value="{{ old('estimated_end_date', $plan->estimated_end_date?->format('Y-m-d')) }}"
                                readonly>
                            <small class="text-muted">Auto-calculated based on method</small>
                            @error('estimated_end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Estimated Budget --}}
                        <div class="col-md-4">
                            <label for="estimated_budget" class="form-label">Estimated Budget</label>
                            <input type="number" step="0.01"
                                class="form-control @error('estimated_budget') is-invalid @enderror" id="estimated_budget"
                                name="estimated_budget" value="{{ old('estimated_budget', $plan->estimated_budget) }}"
                                placeholder="0.00">
                            @error('estimated_budget')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="currency" class="form-label">Currency</label>
                            <input type="text" maxlength="10"
                                class="form-control text-uppercase @error('currency') is-invalid @enderror"
                                id="currency" name="currency" value="{{ old('currency', $plan->currency ?: 'USD') }}"
                                placeholder="USD">
                            @error('currency')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="fiscal_year" class="form-label">Fiscal Year</label>
                            <input type="number" min="2000" max="2100"
                                class="form-control @error('fiscal_year') is-invalid @enderror"
                                id="fiscal_year" name="fiscal_year"
                                value="{{ old('fiscal_year', $plan->fiscal_year) }}">
                            @error('fiscal_year')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Procurement Stage --}}
                        <div class="col-md-4">
                            <label for="stage_id" class="form-label">Procurement Stage</label>
                            <select class="form-select @error('stage_id') is-invalid @enderror" id="stage_id"
                                name="stage_id">
                                <option value="">Select Stage</option>
                                @foreach ($stages as $stage)
                                    <option value="{{ $stage->id }}"
                                        {{ old('stage_id', $plan->stage_id) == $stage->id ? 'selected' : '' }}>
                                        {{ $stage->stage_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('stage_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Procurement Status --}}
                        <div class="col-md-4">
                            <label for="status_id" class="form-label">Procurement Status</label>
                            <select class="form-select @error('status_id') is-invalid @enderror" id="status_id"
                                name="status_id">
                                <option value="">Select Status</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->id }}"
                                        {{ old('status_id', $plan->status_id) == $status->id ? 'selected' : '' }}>
                                        {{ $status->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Step Stage --}}
                        <div class="col-md-6">
                            <label for="step_stage_id" class="form-label">Step Stage</label>
                            <select class="form-select @error('step_stage_id') is-invalid @enderror" id="step_stage_id"
                                name="step_stage_id">
                                <option value="">Select Step Stage</option>
                                @foreach ($stepStages as $stepStage)
                                    <option value="{{ $stepStage->id }}"
                                        {{ old('step_stage_id', $plan->step_stage_id) == $stepStage->id ? 'selected' : '' }}>
                                        {{ $stepStage->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('step_stage_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Step Approval --}}
                        <div class="col-md-6">
                            <label for="step_approval_id" class="form-label">Step Approval Process</label>
                            <select class="form-select @error('step_approval_id') is-invalid @enderror"
                                id="step_approval_id" name="step_approval_id">
                                <option value="">Select Step Approval</option>
                                @foreach ($stepApprovals as $stepApproval)
                                    <option value="{{ $stepApproval->id }}"
                                        data-node-id="{{ $stepApproval->governance_node_id }}"
                                        {{ old('step_approval_id', $plan->step_approval_id) == $stepApproval->id ? 'selected' : '' }}>
                                        {{ $stepApproval->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('step_approval_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                rows="3" placeholder="Enter procurement plan description">{{ old('description', $plan->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Remarks --}}
                        <div class="col-12">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control @error('remarks') is-invalid @enderror" id="remarks" name="remarks" rows="2"
                                placeholder="Additional notes">{{ old('remarks', $plan->remarks) }}</textarea>
                            @error('remarks')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
                <div class="card-footer bg-white border-top">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('procurement.plans.index') }}" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" id="saveProcurementPlanBtn">
                            <i class="feather-save me-1"></i> Update Plan
                        </button>
                    </div>
                </div>
            </div>
        </form>

    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('procurementPlanForm');
            const programPlanSelect = document.getElementById('program_plan_id');
            const activitySelect = document.getElementById('activity_id');
            const subActivitySelect = document.getElementById('sub_activity_id');
            const subActivityHelp = document.getElementById('subActivityHelp');
            const stepApprovalSelect = document.getElementById('step_approval_id');
            const methodSelect = document.getElementById('method_planned_id');
            const startDateInput = document.getElementById('estimated_start_date');
            const endDateInput = document.getElementById('estimated_end_date');
            const generateCodeBtn = document.getElementById('generateCodeBtn');
            const procurementCodeInput = document.getElementById('procurement_code');
            const autoGeneratedInput = document.getElementById('is_code_auto_generated');
            const saveButton = document.getElementById('saveProcurementPlanBtn');

            async function fetchJson(url) {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`Request failed with status ${response.status}`);
                }

                return response.json();
            }

            async function generateCode() {
                if (!window.confirm('Generate a new procurement code for this item?')) {
                    return;
                }

                generateCodeBtn.disabled = true;
                generateCodeBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating';

                try {
                    const data = await fetchJson('{{ route('procurement.plans.generate-code') }}');
                    procurementCodeInput.value = data.code;
                    autoGeneratedInput.value = '1';
                } catch (error) {
                    window.alert('A new procurement code could not be generated. Please try again.');
                } finally {
                    generateCodeBtn.disabled = false;
                    generateCodeBtn.innerHTML = '<i class="feather-refresh-cw me-1"></i> Regenerate';
                }
            }

            function filterOptionsByPortfolio(select, nodeId) {
                Array.from(select.options).forEach(option => {
                    if (!option.value) {
                        return;
                    }

                    const optionNodeId = option.dataset.nodeId || '';
                    const incompatible = Boolean(nodeId && optionNodeId && optionNodeId !== nodeId);
                    option.hidden = incompatible;
                    option.disabled = incompatible;
                });

                if (select.selectedOptions[0]?.disabled) {
                    select.value = '';
                }
            }

            function clearSubActivities(message = 'Only sub-activities with an approved commitment are available.') {
                subActivitySelect.innerHTML = '<option value="">Select Sub Activity</option>';
                subActivityHelp.textContent = message;
            }

            function applyPortfolioFilter() {
                const nodeId = programPlanSelect.selectedOptions[0]?.dataset.nodeId || '';
                const previousActivityId = activitySelect.value;

                filterOptionsByPortfolio(activitySelect, nodeId);
                filterOptionsByPortfolio(stepApprovalSelect, nodeId);

                if (previousActivityId && !activitySelect.value) {
                    clearSubActivities('Choose an activity in the selected plan portfolio.');
                }
            }

            async function loadSubActivities(selectedId = '') {
                const activityId = this.value;
                clearSubActivities();

                if (!activityId) {
                    return;
                }

                subActivitySelect.disabled = true;
                subActivityHelp.textContent = 'Loading approved sub-activities…';

                try {
                    const data = await fetchJson(`{{ url('procurement/plans/sub-activities') }}/${activityId}`);
                    data.forEach(subActivity => {
                        const option = document.createElement('option');
                        option.value = subActivity.id;
                        option.textContent = subActivity.name;
                        option.selected = String(subActivity.id) === String(selectedId);
                        subActivitySelect.appendChild(option);
                    });

                    subActivityHelp.textContent = data.length
                        ? 'Only sub-activities with an approved commitment are available.'
                        : 'No approved committed sub-activities are available for this activity.';
                } catch (error) {
                    subActivityHelp.textContent = 'Sub-activities could not be loaded. Retry by selecting the activity again.';
                } finally {
                    subActivitySelect.disabled = false;
                }
            }

            function calculateEndDate() {
                const methodOption = methodSelect.options[methodSelect.selectedIndex];
                const startDate = startDateInput.value;

                if (methodOption && methodOption.dataset.targetDays && startDate) {
                    const targetDays = parseInt(methodOption.dataset.targetDays);
                    const [year, month, day] = startDate.split('-').map(Number);
                    const start = new Date(Date.UTC(year, month - 1, day));
                    start.setUTCDate(start.getUTCDate() + targetDays);

                    const endDate = start.toISOString().split('T')[0];
                    endDateInput.value = endDate;
                } else {
                    endDateInput.value = '';
                }
            }

            generateCodeBtn.addEventListener('click', generateCode);
            procurementCodeInput.addEventListener('input', () => autoGeneratedInput.value = '0');
            programPlanSelect.addEventListener('change', function() {
                applyPortfolioFilter();
                loadSubActivities.call(activitySelect);
            });
            activitySelect.addEventListener('change', function() {
                loadSubActivities.call(activitySelect);
            });
            methodSelect.addEventListener('change', calculateEndDate);
            startDateInput.addEventListener('change', calculateEndDate);

            form.addEventListener('submit', function() {
                if (!form.checkValidity()) {
                    return;
                }

                saveButton.disabled = true;
                saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving…';
            });

            applyPortfolioFilter();
            calculateEndDate();
        });
    </script>
@endpush
