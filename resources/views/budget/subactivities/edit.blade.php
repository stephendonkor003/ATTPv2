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

                    <form action="{{ route('budget.subactivities.update', $subActivity->id) }}" method="POST">
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
                                                            <small class="text-muted">
                                                                Available: {{ number_format($availableForThisSubActivity, 2) }} {{ $currency }}
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" min="0"
                                                                name="allocations[{{ $year }}]"
                                                                value="{{ $currentAmount }}"
                                                                class="form-control text-end allocation-input"
                                                                data-available="{{ $availableForThisSubActivity }}">
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
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <a href="{{ route('budget.activities.show', $subActivity->activity_id) }}"
                                class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-primary">
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
            const inputs = Array.from(document.querySelectorAll('.allocation-input'));
            const total = document.getElementById('allocationTotal');

            function toggleExpectedOutcome() {
                const type = expectedOutcomeType.value;
                expectedOutcomePercentage.style.display = type === 'percentage' ? 'block' : 'none';
                expectedOutcomeText.style.display = type === 'text' ? 'block' : 'none';
            }

            function updateTotal() {
                const sum = inputs.reduce((carry, input) => carry + (parseFloat(input.value) || 0), 0);
                total.textContent = sum.toFixed(2);

                inputs.forEach(input => {
                    const available = parseFloat(input.dataset.available) || 0;
                    const amount = parseFloat(input.value) || 0;
                    input.classList.toggle('is-invalid', amount > available);
                });
            }

            expectedOutcomeType.addEventListener('change', toggleExpectedOutcome);
            inputs.forEach(input => input.addEventListener('input', updateTotal));
            toggleExpectedOutcome();
            updateTotal();
        });
    </script>
@endsection
