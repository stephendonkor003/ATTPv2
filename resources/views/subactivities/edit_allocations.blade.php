@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-dark">Edit Sub-Activity Budget — {{ $sub->name }}</h4>

            <a href="{{ route('budget.projects.show', $sub->activity->project_id) }}" class="btn btn-secondary">
                Back
            </a>
        </div>

        <div class="card mt-3 shadow-sm">
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
                    $allocationsByYear = $sub->allocations->keyBy(fn ($allocation) => (int) $allocation->year);
                    $activityAllocationsByYear = $sub->activity->allocations->keyBy(fn ($allocation) => (int) $allocation->year);
                    $otherSubActivityTotalsByYear = $sub->activity->subActivities
                        ->where('id', '!=', $sub->id)
                        ->flatMap(fn ($otherSubActivity) => $otherSubActivity->allocations)
                        ->groupBy(fn ($allocation) => (int) $allocation->year)
                        ->map(fn ($allocations) => (float) $allocations->sum('amount'));
                    $currency = $sub->activity->project->currency ?? $sub->activity->project->program->currency ?? 'USD';
                @endphp

                <div class="alert alert-info">
                    Update the yearly amounts for this sub-activity. The totals cannot exceed the parent activity
                    allocation for each year.
                </div>

                <form action="{{ route('budget.subactivities.allocations.update', $sub->id) }}" method="POST" autocomplete="off">
                    @csrf

                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Year</th>
                                <th>Allocation ({{ $currency }})</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($sub->activity->years() as $year)
                                @php
                                    $year = (int) $year;
                                    $allocation = $allocationsByYear->get($year);
                                    $activityYearBudget = (float) optional($activityAllocationsByYear->get($year))->amount;
                                    $otherSubActivityYearTotal = (float) ($otherSubActivityTotalsByYear[$year] ?? 0);
                                    $availableForThisSubActivity = max($activityYearBudget - $otherSubActivityYearTotal, 0);
                                    $savedAmount = round((float) optional($allocation)->amount, 2);
                                    $currentAmount = old('allocations.' . $year, number_format($savedAmount, 2, '.', ''));
                                    $unusedParentCapacity = max($availableForThisSubActivity - $savedAmount, 0);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $year }}</div>
                                        <small class="text-muted">Saved: {{ number_format($savedAmount, 2) }} {{ $currency }}</small>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="{{ number_format($availableForThisSubActivity, 2, '.', '') }}"
                                            name="allocations[{{ $year }}]"
                                            value="{{ $currentAmount }}"
                                            autocomplete="off"
                                            class="form-control text-end allocation-input"
                                            data-saved-amount="{{ number_format($savedAmount, 2, '.', '') }}"
                                            data-available="{{ $availableForThisSubActivity }}">
                                        <div class="form-text">
                                            Allowed ceiling: {{ number_format($availableForThisSubActivity, 2) }} {{ $currency }}.
                                            @if ($unusedParentCapacity > 0.004)
                                                Unused capacity: {{ number_format($unusedParentCapacity, 2) }} {{ $currency }}; not allocated automatically.
                                            @else
                                                No unused capacity is allocated automatically.
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="text-end">
                        <span class="text-muted">Entered allocation total (unused parent capacity excluded):</span>
                        <strong id="allocationTotal">0.00</strong> {{ $currency }}
                    </div>

                    <button class="btn btn-primary mt-3">Save Changes</button>
                </form>


            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const inputs = Array.from(document.querySelectorAll('.allocation-input'));
            const total = document.getElementById('allocationTotal');

            function updateTotal() {
                const sum = inputs.reduce((carry, input) => carry + (parseFloat(input.value) || 0), 0);
                total.textContent = sum.toFixed(2);

                inputs.forEach(input => {
                    const available = parseFloat(input.dataset.available) || 0;
                    const amount = parseFloat(input.value) || 0;
                    input.classList.toggle('is-invalid', amount > available);
                });
            }

            inputs.forEach(input => input.addEventListener('input', updateTotal));
            updateTotal();
        });
    </script>
@endsection
