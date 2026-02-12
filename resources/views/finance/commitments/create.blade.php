@extends('layouts.app')

@php
    $isEdit = isset($commitment);
    $purchaseRequest = $purchaseRequest ?? ($isEdit ? $commitment->purchaseRequest : null);
    $items = $items ?? [];
    $defaults = $defaults ?? [];
@endphp

@section('content')
    <div class="nxl-container">

        {{-- ===================== PAGE HEADER ===================== --}}
        <div class="page-header">
            <h4 class="fw-bold">{{ $isEdit ? 'Edit Budget Commitment' : 'Create Budget Commitment' }}</h4>
            <p class="text-muted mb-0">
                {{ $isEdit ? 'Update draft commitment details' : 'Commit approved allocations to specific resources' }}
            </p>
        </div>

        {{-- ===================== GLOBAL ERROR SUMMARY ===================== --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <h6 class="fw-bold mb-2">
                    <i class="feather-alert-triangle me-1"></i>
                    Please fix the following errors:
                </h6>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
            action="{{ $isEdit ? route('finance.commitments.update', $commitment) : route('finance.commitments.store') }}"
            id="commitmentForm">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            {{-- ===================== PROGRAM FUNDING ===================== --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Program Funding</h6>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Approved Program Funding</label>
                            <select name="program_funding_id"
                                class="form-select @error('program_funding_id') is-invalid @enderror" required>
                                <option value="">Select Program Funding</option>
                                @foreach ($fundings as $funding)
                                    <option value="{{ $funding->id }}"
                                        {{ old('program_funding_id', $isEdit ? $commitment->program_funding_id : null) == $funding->id ? 'selected' : '' }}>
                                        {{ $funding->program->name ?? $funding->program_name ?? 'Program' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('program_funding_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== COMMITMENT REFERENCE ===================== --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold text-secondary mb-3">
                        Commitment Reference
                    </h6>

                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Commitment Ref</label>
                            <input type="text" id="commitment_ref" class="form-control" readonly
                                value="{{ $isEdit ? ($purchaseRequest->reference_no ?? '—') : '' }}">
                            <small class="text-muted">
                                System generated (for tracking)
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== ALLOCATION CONTEXT ===================== --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body">

                    <h6 class="fw-bold text-primary mb-3">
                        Allocation Context
                    </h6>

	                    <div class="row g-3">

	                        {{-- Allocation Level --}}
	                        <div class="col-md-4">
	                            <label class="form-label">Allocation Level</label>
	                            <input type="text" class="form-control" value="Sub-Activity" readonly>
	                            <input type="hidden" name="allocation_level" id="allocation_level" value="sub_activity">
	                        </div>

	                        {{-- Project --}}
                            <div class="col-md-4 d-none" id="projectWrap">
                                <label class="form-label">Project</label>
                                <select class="form-select" id="project_id" name="project_id"
                                data-old="{{ old('project_id', $defaults['project_id'] ?? '') }}"></select>
                            </div>

	                        {{-- Activity --}}
                            <div class="col-md-4 d-none" id="activityWrap">
                                <label class="form-label">Activity</label>
                                <select class="form-select" id="activity_id" name="activity_id"
                                data-old="{{ old('activity_id', $defaults['activity_id'] ?? '') }}"></select>
                            </div>

	                        {{-- Sub-Activity --}}
                            <div class="col-md-4 d-none" id="subActivityWrap">
                                <label class="form-label">Sub-Activity</label>
                                <select class="form-select" id="sub_activity_id" name="sub_activity_id"
                                data-old="{{ old('sub_activity_id', old('allocation_id', $defaults['sub_activity_id'] ?? '')) }}"></select>
                            </div>

	                        {{-- Year --}}
                            <div class="col-md-4 d-none" id="yearWrap">
                                <label class="form-label">Start Year</label>
                                <select class="form-select @error('commitment_year') is-invalid @enderror" id="commitment_year"
                                name="commitment_year" data-old="{{ old('commitment_year', $defaults['year'] ?? '') }}" required></select>
                                @error('commitment_year')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                        <input type="hidden" name="allocation_id" id="allocation_id" value="{{ old('allocation_id', $defaults['sub_activity_id'] ?? ($commitment->allocation_id ?? '')) }}">
                    </div>

                    {{-- ===================== ALLOCATION PREVIEW ===================== --}}
	                    <div class="row g-3 mt-4 d-none" id="allocationPreview">

	                        <div class="col-md-4">
	                            <div class="alert alert-light border">
	                                <strong>Total Allocated (from Start Year)</strong><br>
	                                <span id="allocatedAmount">—</span>
	                            </div>
	                        </div>

	                        <div class="col-md-4">
	                            <div class="alert alert-warning border">
	                                <strong>Total Remaining (from Start Year)</strong><br>
	                                <span id="remainingAmount">—</span>
	                            </div>
	                        </div>

	                        <div class="col-md-12">
	                            <div class="alert alert-info mb-0">
	                                You are committing resources to:
	                                <strong id="confirmText"></strong>
	                            </div>
	                        </div>

	                    </div>

	                    {{-- ===================== YEAR DISTRIBUTION ===================== --}}
	                    <div class="mt-4 d-none" id="distributionWrap">
	                        <div class="d-flex justify-content-between align-items-center mb-2">
	                            <h6 class="fw-bold mb-0">Year Contribution (Auto)</h6>
	                            <small class="text-muted">Spreads from start year forward</small>
	                        </div>

	                        <div class="alert alert-danger d-none" id="distributionError"></div>

	                        <div class="table-responsive">
	                            <table class="table table-sm table-bordered align-middle mb-0">
	                                <thead class="table-light">
	                                    <tr>
	                                        <th style="width: 90px;">Year</th>
	                                        <th>Allocated</th>
	                                        <th>Committed</th>
	                                        <th>Remaining</th>
	                                        <th>This Commitment</th>
	                                    </tr>
	                                </thead>
	                                <tbody id="distributionBody"></tbody>
	                            </table>
	                        </div>
	                    </div>

	                </div>
	            </div>

	            {{-- ===================== PURCHASE REQUEST ITEMS ===================== --}}
	            <div class="card shadow-sm mb-4 d-none" id="resourceSection">
	                <div class="card-body">
	                    <h6 class="fw-bold text-success mb-3">
	                        Purchase Request (Auto-created)
	                    </h6>
	
	                    <div class="row g-3">
	                        <div class="col-12">
	                            <label class="form-label">Description (optional)</label>
	                            <textarea
                                name="description"
                                id="description"
                                rows="3"
                                class="form-control @error('description') is-invalid @enderror"
                                placeholder="Brief description of this purchase request...">{{ old('description', $isEdit ? ($commitment->description ?? $purchaseRequest->description ?? '') : '') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Delivery Date</label>
                            <input type="date"
                                name="delivery_date"
                                id="delivery_date"
                                value="{{ old('delivery_date', optional($purchaseRequest?->delivery_date)->format('Y-m-d')) }}"
                                class="form-control @error('delivery_date') is-invalid @enderror"
                                required>
                            @error('delivery_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
	                        </div>
	                    </div>
	
	                    <div class="table-responsive mt-3">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 22%;">Resource Category</th>
                                        <th style="width: 22%;">Resource Item</th>
                                        <th style="width: 20%;">Milestone / Description</th>
                                        <th style="width: 14%;">Milestone Date</th>
                                        <th style="width: 170px;" class="text-end">Price / Amount</th>
                                        <th style="width: 80px;" class="text-center">Action</th>
                                    </tr>
                                </thead>
	                            <tbody id="itemsBody"></tbody>
	                        </table>
	                    </div>
	
	                    <div class="d-flex justify-content-between align-items-center mt-3">
	                        <button type="button" class="btn btn-sm btn-outline-secondary" id="addItemBtn">
	                            <i class="feather-plus me-1"></i> Add Item
	                        </button>
	
                                <div style="min-width: 260px;">
                                    <label class="form-label mb-1">Total Commitment Amount</label>
                                    <input type="number" step="0.01" id="commitment_amount"
                                        class="form-control @error('commitment_amount') is-invalid @enderror text-end fw-bold"
                                        name="commitment_amount" value="{{ old('commitment_amount', $isEdit ? ($purchaseRequest->total_amount ?? 0) : 0) }}" readonly>
                                    <small class="text-muted">Auto-calculated from items</small>
                                    @error('commitment_amount')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
	                    </div>
	                </div>
	            </div>

            {{-- ===================== ACTION ===================== --}}
            <div class="text-end">
                <button class="btn btn-primary px-4" id="saveCommitmentBtn" type="submit">
                    <i class="feather-save me-1"></i>
                    {{ $isEdit ? 'Update Commitment' : 'Save Commitment' }}
                </button>
            </div>

        </form>
    </div>

	    {{-- ===================== SCRIPT ===================== --}}
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const refInput = document.getElementById('commitment_ref');
                if (refInput && !refInput.value) {
                    refInput.value = 'COM-' + Date.now().toString().slice(-6);
                }
                const existingCommitmentId = @json($isEdit ? ($commitment->id ?? null) : null);

	            const allocationLevel = document.getElementById('allocation_level'); // hidden, always sub_activity
	            const projectSelect = document.getElementById('project_id');
	            const activitySelect = document.getElementById('activity_id');
	            const subActivitySelect = document.getElementById('sub_activity_id');
	            const yearSelect = document.getElementById('commitment_year');
	            const allocationIdInput = document.getElementById('allocation_id');

	            const allocationPreview = document.getElementById('allocationPreview');
		            const distributionWrap = document.getElementById('distributionWrap');
		            const distributionBody = document.getElementById('distributionBody');
		            const distributionError = document.getElementById('distributionError');
		            const resourceSection = document.getElementById('resourceSection');
		            const itemsBody = document.getElementById('itemsBody');
		            const addItemBtn = document.getElementById('addItemBtn');
		            const commitmentAmountInput = document.getElementById('commitment_amount');
		            const saveBtn = document.getElementById('saveCommitmentBtn');

		            const formatter = new Intl.NumberFormat(undefined, {
		                minimumFractionDigits: 2,
		                maximumFractionDigits: 2,
		            });
	
		            const categoryOptionsHtml = `
		                <option value="">Select Category</option>
		                @foreach ($resourceCategories as $cat)
		                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
		                @endforeach
		            `;

	            let breakdown = [];

	            function formatNumber(value) {
	                const number = Number(value);
	                if (!Number.isFinite(number)) return '—';
	                return formatter.format(number);
	            }

	            function show(id) {
	                document.getElementById(id).classList.remove('d-none');
	            }

	            function hide(id) {
	                document.getElementById(id).classList.add('d-none');
	            }

	            function fillSelect(selectEl, data, { raw = false } = {}) {
	                selectEl.innerHTML = '<option value="">Select</option>';
	                data.forEach(item => {
	                    const value = raw ? item : item.id;
	                    const label = raw ? item : item.name;
	                    const option = document.createElement('option');
	                    option.value = value;
	                    option.textContent = label;
	                    selectEl.appendChild(option);
	                });
	            }

	            function resetFromProject() {
	                activitySelect.innerHTML = '<option value="">Select</option>';
	                subActivitySelect.innerHTML = '<option value="">Select</option>';
	                yearSelect.innerHTML = '<option value="">Select</option>';
	                allocationIdInput.value = '';
	                breakdown = [];

	                hide('activityWrap');
	                hide('subActivityWrap');
	                hide('yearWrap');
	                hide('allocationPreview');
	                hide('distributionWrap');
	                hide('resourceSection');

	                distributionError.textContent = '';
	                distributionError.classList.add('d-none');
	                saveBtn.disabled = false;
	            }

	            function resetFromActivity() {
	                subActivitySelect.innerHTML = '<option value="">Select</option>';
	                yearSelect.innerHTML = '<option value="">Select</option>';
	                allocationIdInput.value = '';
	                breakdown = [];

	                hide('subActivityWrap');
	                hide('yearWrap');
	                hide('allocationPreview');
	                hide('distributionWrap');
	                hide('resourceSection');

	                distributionError.textContent = '';
	                distributionError.classList.add('d-none');
	                saveBtn.disabled = false;
	            }

	            function resetFromSubActivity() {
	                yearSelect.innerHTML = '<option value="">Select</option>';
	                allocationIdInput.value = '';
	                breakdown = [];

	                hide('yearWrap');
	                hide('allocationPreview');
	                hide('distributionWrap');
	                hide('resourceSection');

	                distributionError.textContent = '';
	                distributionError.classList.add('d-none');
	                saveBtn.disabled = false;
	            }

	            function loadProjects() {
	                show('projectWrap');
	                fetch('/finance/commitments/ajax/projects')
	                    .then(r => r.json())
	                    .then(d => {
	                        fillSelect(projectSelect, d);
	                        const old = projectSelect.dataset.old;
	                        if (old) {
	                            projectSelect.value = old;
	                            projectSelect.dataset.old = '';
	                            projectSelect.dispatchEvent(new Event('change'));
	                        }
	                    });
	            }

	            function loadActivities(projectId) {
	                show('activityWrap');
	                fetch(`/finance/commitments/ajax/activities/${projectId}`)
	                    .then(r => r.json())
	                    .then(d => {
	                        fillSelect(activitySelect, d);
	                        const old = activitySelect.dataset.old;
	                        if (old) {
	                            activitySelect.value = old;
	                            activitySelect.dataset.old = '';
	                            activitySelect.dispatchEvent(new Event('change'));
	                        }
	                    });
	            }

	            function loadSubActivities(activityId) {
	                show('subActivityWrap');
	                fetch(`/finance/commitments/ajax/sub-activities/${activityId}`)
	                    .then(r => r.json())
	                    .then(d => {
	                        fillSelect(subActivitySelect, d);
	                        const old = subActivitySelect.dataset.old;
	                        if (old) {
	                            subActivitySelect.value = old;
	                            subActivitySelect.dataset.old = '';
	                            subActivitySelect.dispatchEvent(new Event('change'));
	                        }
	                    });
	            }

            function loadBreakdown(subActivityId) {
                const level = allocationLevel.value || 'sub_activity';
                const url = `/finance/commitments/ajax/allocation-breakdown/${level}/${subActivityId}` +
                    (existingCommitmentId ? `?exclude=${existingCommitmentId}` : '');
                fetch(url)
	                    .then(r => r.json())
	                    .then(d => {
	                        breakdown = Array.isArray(d) ? d : [];

	                        const years = breakdown
	                            .map(row => row.year)
	                            .filter(year => Number.isFinite(Number(year)))
	                            .sort((a, b) => Number(a) - Number(b));

	                        show('yearWrap');
	                        fillSelect(yearSelect, years, { raw: true });

	                        const oldYear = yearSelect.dataset.old;
	                        if (oldYear) {
	                            yearSelect.value = oldYear;
	                            yearSelect.dataset.old = '';
	                        } else if (years.length) {
	                            yearSelect.value = years[0];
	                        }

	                        show('allocationPreview');
	                        show('resourceSection');
	                        updateDistribution();
	                    })
	                    .catch(() => {
	                        breakdown = [];
	                        hide('allocationPreview');
	                        hide('distributionWrap');
	                    });
	            }

		            function updateDistribution() {
		                distributionBody.innerHTML = '';
		                distributionError.textContent = '';
		                distributionError.classList.add('d-none');
		                hide('distributionWrap');
		                saveBtn.disabled = true;

		                const startYear = Number(yearSelect.value);
		                const amount = Number(commitmentAmountInput.value);

		                if (!breakdown.length || !Number.isFinite(startYear) || !Number.isFinite(amount) || amount <= 0) {
		                    return;
		                }
	
		                saveBtn.disabled = false;

	                const rows = breakdown
	                    .filter(r => Number(r.year) >= startYear)
	                    .sort((a, b) => Number(a.year) - Number(b.year))
	                    .map(r => ({
	                        year: Number(r.year),
	                        allocated: Number(r.allocated) || 0,
	                        committed: Number(r.committed) || 0,
	                        remaining: Number(r.remaining) || 0,
	                    }));

	                const totalAllocated = rows.reduce((sum, r) => sum + r.allocated, 0);
                const totalRemaining = rows.reduce((sum, r) => sum + Math.max(0, r.remaining), 0);

                document.getElementById('allocatedAmount').innerText = formatNumber(totalAllocated);
                document.getElementById('remainingAmount').innerText = formatNumber(totalRemaining);
                document.getElementById('confirmText').innerText = `Sub-Activity – Start year ${startYear}`;

	                if (amount > totalRemaining) {
	                    show('distributionWrap');
	                    distributionError.textContent =
	                        `Insufficient remaining budget from ${startYear}. Available: ${formatNumber(totalRemaining)}.`;
	                    distributionError.classList.remove('d-none');
	                    saveBtn.disabled = true;
	                    return;
	                }

	                let remainingToAllocate = amount;

	                rows.forEach(r => {
	                    const available = Math.max(0, r.remaining);
	                    const use = Math.min(available, remainingToAllocate);
	                    remainingToAllocate = Math.max(0, remainingToAllocate - use);

	                    const tr = document.createElement('tr');
	                    tr.innerHTML = `
	                        <td>${r.year}</td>
	                        <td>${formatNumber(r.allocated)}</td>
	                        <td>${formatNumber(r.committed)}</td>
	                        <td>${formatNumber(r.remaining)}</td>
	                        <td class="fw-semibold">${formatNumber(use)}</td>
	                    `;
	                    distributionBody.appendChild(tr);
	                });

	                show('distributionWrap');
	            }

	            projectSelect.addEventListener('change', e => {
	                resetFromProject();
	                const projectId = e.target.value;
	                if (!projectId) return;
	                loadActivities(projectId);
	            });

	            activitySelect.addEventListener('change', e => {
	                resetFromActivity();
	                const activityId = e.target.value;
	                if (!activityId) return;
	                loadSubActivities(activityId);
	            });

	            subActivitySelect.addEventListener('change', e => {
	                resetFromSubActivity();
	                const subActivityId = e.target.value;
	                if (!subActivityId) return;

	                allocationIdInput.value = subActivityId;
	                loadBreakdown(subActivityId);
	            });

		            yearSelect.addEventListener('change', updateDistribution);
		            commitmentAmountInput.addEventListener('input', updateDistribution);
	
		            function roundMoney(value) {
		                const number = Number(value);
		                if (!Number.isFinite(number)) return 0;
		                return Math.round((number + Number.EPSILON) * 100) / 100;
		            }
	
		            function renumberItemRows() {
		                Array.from(itemsBody.querySelectorAll('tr')).forEach((row, index) => {
		                    row.querySelectorAll('[data-field]').forEach(el => {
		                        el.name = `items[${index}][${el.dataset.field}]`;
		                    });
		                });
		            }
	
		            function loadResourcesForRow(row, categoryId, selectedResourceId = '') {
		                const resourceSelect = row.querySelector('.item-resource');
		                if (!categoryId) {
		                    resourceSelect.innerHTML = '<option value="">Select Resource</option>';
		                    resourceSelect.value = '';
		                    return;
		                }
	
		                resourceSelect.innerHTML = '<option value="">Loading...</option>';
		                fetch(`/finance/resources/ajax/resources/${categoryId}`)
		                    .then(r => r.json())
		                    .then(d => {
		                        resourceSelect.innerHTML = '<option value="">Select Resource</option>';
		                        d.forEach(i => {
		                            const option = document.createElement('option');
		                            option.value = i.id;
		                            option.textContent = i.name;
		                            resourceSelect.appendChild(option);
		                        });
	
		                        if (selectedResourceId) {
		                            resourceSelect.value = selectedResourceId;
		                        }
		                    })
		                    .catch(() => {
		                        resourceSelect.innerHTML = '<option value="">Select Resource</option>';
		                    });
		            }
	
		            function setTotalFromItems() {
		                const total = roundMoney(
		                    Array.from(itemsBody.querySelectorAll('.item-amount')).reduce((sum, input) => {
		                        const value = Number(input.value);
		                        return sum + (Number.isFinite(value) ? value : 0);
		                    }, 0)
		                );
	
		                commitmentAmountInput.value = (total > 0 ? total : 0).toFixed(2);
		                updateDistribution();
		            }
	
                        function addItemRow(item = null) {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>
                                    <select class="form-select item-category" data-field="resource_category_id" required>
                                        ${categoryOptionsHtml}
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select item-resource" data-field="resource_id" required>
                                        <option value="">Select Resource</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text"
                                        class="form-control item-milestone"
                                        data-field="milestone"
                                        placeholder="Milestone / description"
                                        maxlength="255">
                                </td>
                                <td>
                                    <input type="date"
                                        class="form-control item-milestone-date"
                                        data-field="milestone_date">
                                </td>
                                <td>
                                    <input type="number" min="0.01" step="0.01"
                                        class="form-control text-end item-amount" data-field="amount" required>
                                </td>
                                <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" title="Remove">
                                            <i class="feather-trash-2"></i>
                                        </button>
                                    </td>
                                `;

                            itemsBody.appendChild(tr);
                            renumberItemRows();

                            const categorySelect = tr.querySelector('.item-category');
                            const amountInput = tr.querySelector('.item-amount');
                            const milestoneInput = tr.querySelector('.item-milestone');
                            const milestoneDateInput = tr.querySelector('.item-milestone-date');

                            if (item && item.resource_category_id) {
                                categorySelect.value = item.resource_category_id;
                                loadResourcesForRow(tr, item.resource_category_id, item.resource_id || '');
                            }

                            if (item && item.amount) {
                                amountInput.value = item.amount;
                            }

                            if (item && item.milestone) {
                                milestoneInput.value = item.milestone;
                            }

                            if (item && item.milestone_date) {
                                milestoneDateInput.value = item.milestone_date;
                            }

                            setTotalFromItems();
                        }
	
		            addItemBtn.addEventListener('click', () => addItemRow());
	
		            itemsBody.addEventListener('change', e => {
		                if (!e.target.classList.contains('item-category')) return;
		                const row = e.target.closest('tr');
		                loadResourcesForRow(row, e.target.value, '');
		            });
	
		            itemsBody.addEventListener('input', e => {
		                if (!e.target.classList.contains('item-amount')) return;
		                setTotalFromItems();
		            });
	
		            itemsBody.addEventListener('click', e => {
		                const removeBtn = e.target.closest('.remove-item-btn');
		                if (!removeBtn) return;
	
		                const row = removeBtn.closest('tr');
		                const rows = itemsBody.querySelectorAll('tr');
                if (rows.length <= 1) {
                    row.querySelector('.item-category').value = '';
                    row.querySelector('.item-resource').innerHTML = '<option value="">Select Resource</option>';
                    row.querySelector('.item-amount').value = '';
                    row.querySelector('.item-milestone').value = '';
                    row.querySelector('.item-milestone-date').value = '';
                    setTotalFromItems();
                    return;
                }
	
		                row.remove();
		                renumberItemRows();
		                setTotalFromItems();
		            });
	
            function initItemRows() {
                const oldItems = @json(old('items', $isEdit ? $items : []));
                const legacyCategory = @json(old('resource_category_id'));
                const legacyResource = @json(old('resource_id'));
                const legacyAmount = @json(old('commitment_amount'));
	
		                if (Array.isArray(oldItems) && oldItems.length) {
		                    oldItems.forEach(item => addItemRow(item));
		                    return;
		                }
	
		                if (legacyCategory) {
		                    addItemRow({
		                        resource_category_id: legacyCategory,
		                        resource_id: legacyResource,
		                        amount: legacyAmount,
		                    });
		                    return;
		                }
	
		                // Default: start with 2 rows (user can add/remove)
		                addItemRow();
		                addItemRow();
		            }
	
		            initItemRows();
		            setTotalFromItems();

		            loadProjects();
	        });
	    </script>
	@endsection
