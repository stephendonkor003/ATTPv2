@extends('layouts.app')

@section('title', 'Edit Activity')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            {{-- PAGE HEADER --}}
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Edit Activity: {{ $activity->name }}</h4>
                    <p class="text-muted">
                        Project: {{ $activity->project->project_id }} — {{ $activity->project->name }}
                    </p>
                </div>

                <a href="{{ route('budget.activities.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back
                </a>
            </div>

            {{-- ALERTS --}}
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

            {{-- FORM --}}
            <form action="{{ route('budget.activities.update', $activity->id) }}" method="POST" id="editActivityForm">
                @csrf
                @method('PUT')

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">

                        {{-- Basic Fields --}}
                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Activity Name</label>
                                <input type="text" class="form-control" name="name" value="{{ $activity->name }}"
                                    required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Description</label>
                                <input type="text" class="form-control" name="description"
                                    value="{{ $activity->description }}">
                            </div>

                        </div>

                    </div>
                </div>

                {{-- ALLOCATION PANEL --}}
                <div class="card shadow-sm border-0">
                    <div class="card-body">

                        <h5 class="fw-bold mb-3">
                            Activity Budget Allocation
                            <span class="text-muted">({{ $activity->project->currency }})</span>
                        </h5>

                        @php
                            $allocationsByYear = $activity->allocations->keyBy(fn ($allocation) => (int) $allocation->year);
                            $projectAllocationsByYear = $activity->project->allocations->keyBy(fn ($allocation) => (int) $allocation->year);
                            $otherActivityTotalsByYear = $activity->project->activities
                                ->where('id', '!=', $activity->id)
                                ->flatMap(fn ($otherActivity) => $otherActivity->allocations)
                                ->groupBy(fn ($allocation) => (int) $allocation->year)
                                ->map(fn ($allocations) => (float) $allocations->sum('amount'));
                            $subActivityTotalsByYear = $activity->subActivities
                                ->flatMap(fn ($subActivity) => $subActivity->allocations)
                                ->groupBy(fn ($allocation) => (int) $allocation->year)
                                ->map(fn ($allocations) => (float) $allocations->sum('amount'));
                            $currency = $activity->project->currency ?? 'USD';
                        @endphp

                        <div class="alert alert-info py-2">
                            Update the yearly amounts for this activity. The totals cannot exceed the parent project
                            allocation, and they cannot be lower than existing sub-activity allocations.
                        </div>

                        {{-- ALLOCATION TABLE --}}
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Year</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>

                            <tbody id="allocationTable">
                                @foreach ($activity->project->years() as $year)
                                    @php
                                        $year = (int) $year;
                                        $allocation = $allocationsByYear->get($year);
                                        $projectYearBudget = (float) optional($projectAllocationsByYear->get($year))->amount;
                                        $otherActivityYearTotal = (float) ($otherActivityTotalsByYear[$year] ?? 0);
                                        $subActivityYearTotal = (float) ($subActivityTotalsByYear[$year] ?? 0);
                                        $availableForThisActivity = max($projectYearBudget - $otherActivityYearTotal, 0);
                                        $currentAmount = old('allocations.' . $year, optional($allocation)->amount ?? 0);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $year }}</div>
                                            <small class="text-muted d-block">
                                                Available: {{ number_format($availableForThisActivity, 2) }} {{ $currency }}
                                            </small>
                                            <small class="text-muted d-block">
                                                Sub-activity total: {{ number_format($subActivityYearTotal, 2) }} {{ $currency }}
                                            </small>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0"
                                                name="allocations[{{ $year }}]"
                                                class="form-control alloc-input"
                                                data-year="{{ $year }}"
                                                data-available="{{ $availableForThisActivity }}"
                                                data-child-total="{{ $subActivityYearTotal }}"
                                                value="{{ $currentAmount }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        {{-- Remaining Budget --}}
                        <div id="remainingBox" class="alert alert-warning mt-2">
                            This activity total: <strong id="activityTotalValue">0.00</strong>
                            {{ $currency }}
                        </div>

                    </div>
                </div>

                {{-- SAVE BUTTON --}}
                <div class="mt-4 d-flex justify-content-end">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Save Changes
                    </button>
                </div>

            </form>

        </div>
    </main>

    {{-- Allocation interaction script --}}
    <script>
        const inputs = Array.from(document.querySelectorAll('.alloc-input'));
        const activityTotalValue = document.getElementById('activityTotalValue');
        const remainingBox = document.getElementById('remainingBox');

        function recalc() {
            const total = inputs.reduce((carry, input) => carry + (parseFloat(input.value) || 0), 0);
            activityTotalValue.innerText = total.toFixed(2);

            let hasInvalidAmount = false;
            inputs.forEach(input => {
                const available = parseFloat(input.dataset.available) || 0;
                const childTotal = parseFloat(input.dataset.childTotal) || 0;
                const amount = parseFloat(input.value) || 0;
                const invalid = amount > available || amount < childTotal;

                input.classList.toggle('is-invalid', invalid);
                hasInvalidAmount = hasInvalidAmount || invalid;
            });

            remainingBox.className = hasInvalidAmount ? 'alert alert-danger mt-2' : 'alert alert-success mt-2';
        }

        inputs.forEach(inp => {
            inp.addEventListener('input', recalc);
        });

        recalc();
    </script>

@endsection
