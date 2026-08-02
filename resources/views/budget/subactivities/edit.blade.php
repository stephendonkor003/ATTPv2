@extends('layouts.app')
@section('title', 'Edit Sub-Activity')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Edit Sub-Activity</h4>
                    <p class="text-muted mb-0">Update details for this sub-activity.</p>
                </div>
                <a href="{{ route('budget.activities.show', $subActivity->activity_id) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back to Sub-Activities
                </a>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (($fundingAllocationReconciliation['status'] ?? 'unavailable') === 'ready')
                        <div class="alert alert-info">
                            <div class="fw-semibold mb-1">Automatic audited allocation repair available</div>
                            <p class="mb-2">
                                This server has the audited USD 24.5M legacy schedule and compatible annual budget
                                capacity. The repair will rephase it across 2026-2028 and move the USD 24,800 sibling
                                residue out of 2025.
                            </p>
                            <div class="small mb-2">
                                Resulting target schedule: 2026 - USD 9,678,500; 2027 - USD 9,678,500;
                                2028 - USD 5,143,000. The parent schedule will be adjusted only where required, while
                                preserving its valid server-side envelope and staying within every project year.
                            </div>
                            <form method="POST"
                                action="{{ route('budget.subactivities.reconcile-funding-allocation', $subActivity->id) }}"
                                onsubmit="return confirm('Automatically spread USD 24,500,000 across 2026, 2027, and 2028? The parent and sibling schedules will be reconciled in the same transaction.');">
                                @csrf
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-calendar2-range me-1"></i> Automatically Spread USD 24,500,000
                                </button>
                            </form>
                        </div>
                    @elseif (($fundingAllocationReconciliation['status'] ?? 'unavailable') === 'complete')
                        <div class="alert alert-success">
                            <div class="fw-semibold">Allocation reconciliation complete</div>
                            <div class="small">
                                The $24.5M schedule, parent envelope, and sibling residue are balanced. Re-running the
                                repair would make no database changes.
                            </div>
                        </div>
                    @endif

                    @php
                        $allocationsByYear = $subActivity->allocations->keyBy(fn ($allocation) => (int) $allocation->year);
                        $activityAllocationsByYear = $subActivity->activity->allocations->keyBy(fn ($allocation) => (int) $allocation->year);
                        $otherSubActivityTotalsByYear = $subActivity->activity->subActivities
                            ->where('id', '!=', $subActivity->id)
                            ->flatMap(fn ($otherSubActivity) => $otherSubActivity->allocations)
                            ->groupBy(fn ($allocation) => (int) $allocation->year)
                            ->map(fn ($allocations) => (float) $allocations->sum('amount'));
                        $currency = $subActivity->activity->project->currency ?? $subActivity->activity->project->program->currency ?? 'USD';
                    @endphp

                    <form id="subActivityEditForm" action="{{ route('budget.subactivities.update', $subActivity->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sub-Activity Name</label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $subActivity->name) }}" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Expected Outcome Type <span class="text-danger">*</span></label>
                                <select name="expected_outcome_type" id="expectedOutcomeType" class="form-select" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="percentage" @selected(old('expected_outcome_type', $subActivity->expected_outcome_type) === 'percentage')>Percentage</option>
                                    <option value="text" @selected(old('expected_outcome_type', $subActivity->expected_outcome_type) === 'text')>Text</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $subActivity->description) }}</textarea>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Expected Outcome <span class="text-danger">*</span></label>
                                <div id="expectedOutcomePercentage" style="display:none;">
                                    <div class="input-group">
                                        <input type="number" name="expected_outcome_percentage" class="form-control"
                                            min="0" max="100" step="0.01"
                                            value="{{ old('expected_outcome_percentage', $subActivity->expected_outcome_type === 'percentage' ? $subActivity->expected_outcome_value : '') }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div id="expectedOutcomeText" style="display:none;">
                                    <textarea name="expected_outcome_text" class="form-control" rows="2">{{ old('expected_outcome_text', $subActivity->expected_outcome_type === 'text' ? $subActivity->expected_outcome_value : '') }}</textarea>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <h6 class="fw-semibold mb-3">Yearly Allocations ({{ $currency }})</h6>
                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Year</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($subActivity->activity->years() as $year)
                                                    @php
                                                        $year = (int) $year;
                                                        $allocation = $allocationsByYear->get($year);
                                                        $activityYearBudget = (float) optional($activityAllocationsByYear->get($year))->amount;
                                                        $otherSubActivityYearTotal = (float) ($otherSubActivityTotalsByYear[$year] ?? 0);
                                                        $availableForThisSubActivity = max($activityYearBudget - $otherSubActivityYearTotal, 0);
                                                        $currentAmount = old('allocations.' . $year, optional($allocation)->amount ?? 0);
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold">{{ $year }}</div>
                                                        </td>
                                                        <td>
                                                            <div class="input-group has-validation">
                                                                <input type="number" step="0.01" min="0"
                                                                    max="{{ number_format($availableForThisSubActivity, 2, '.', '') }}"
                                                                    name="allocations[{{ $year }}]"
                                                                    value="{{ $currentAmount }}"
                                                                    class="form-control text-end allocation-input"
                                                                    aria-label="{{ $year }} allocation amount"
                                                                    data-year="{{ $year }}"
                                                                    data-currency="{{ $currency }}"
                                                                    data-available="{{ number_format($availableForThisSubActivity, 2, '.', '') }}">
                                                                <span class="input-group-text">
                                                                    Max {{ number_format($availableForThisSubActivity, 2) }} {{ $currency }}
                                                                </span>
                                                                <div class="invalid-feedback allocation-feedback"></div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end">
                                        <span class="text-muted">This sub-activity total:</span>
                                        <strong id="allocationTotal">0.00</strong> {{ $currency }}
                                    </div>
                                    <div id="allocationValidationMessage" class="alert alert-danger py-2 mt-3 mb-0 d-none"
                                        role="alert">
                                        Reduce the highlighted yearly amount to its displayed maximum before saving.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <a href="{{ route('budget.activities.show', $subActivity->activity_id) }}"
                                class="btn btn-light border">Cancel</a>
                            <button type="submit" id="updateSubActivityButton" class="btn btn-primary">
                                <i class="bi bi-save2 me-1"></i> Update Sub-Activity
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const expectedOutcomeType = document.getElementById('expectedOutcomeType');
            const expectedOutcomePercentage = document.getElementById('expectedOutcomePercentage');
            const expectedOutcomeText = document.getElementById('expectedOutcomeText');
            const form = document.getElementById('subActivityEditForm');
            const inputs = Array.from(document.querySelectorAll('.allocation-input'));
            const total = document.getElementById('allocationTotal');
            const validationMessage = document.getElementById('allocationValidationMessage');
            const submitButton = document.getElementById('updateSubActivityButton');

            function toggleExpectedOutcome() {
                const type = expectedOutcomeType.value;
                expectedOutcomePercentage.style.display = type === 'percentage' ? 'block' : 'none';
                expectedOutcomeText.style.display = type === 'text' ? 'block' : 'none';
            }

            function updateTotal() {
                const sum = inputs.reduce((carry, input) => carry + (parseFloat(input.value) || 0), 0);
                total.textContent = sum.toFixed(2);
                let hasExceededAmount = false;

                inputs.forEach(input => {
                    const available = parseFloat(input.dataset.available) || 0;
                    const amount = parseFloat(input.value) || 0;
                    const hasExceeded = amount > available + 0.004;
                    const feedback = input.closest('.input-group').querySelector('.allocation-feedback');

                    input.classList.toggle('is-invalid', hasExceeded);
                    input.setCustomValidity(hasExceeded
                        ? `Year ${input.dataset.year} cannot exceed ${available.toFixed(2)} ${input.dataset.currency}.`
                        : '');
                    feedback.textContent = input.validationMessage;
                    hasExceededAmount ||= hasExceeded;
                });

                validationMessage.classList.toggle('d-none', !hasExceededAmount);
                submitButton.disabled = hasExceededAmount;
            }

            expectedOutcomeType.addEventListener('change', toggleExpectedOutcome);
            inputs.forEach(input => input.addEventListener('input', updateTotal));
            form.addEventListener('submit', event => {
                updateTotal();
                if (!form.checkValidity()) {
                    event.preventDefault();
                    form.reportValidity();
                }
            });
            toggleExpectedOutcome();
            updateTotal();
        });
    </script>
@endsection
