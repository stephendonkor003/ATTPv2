@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-dark">Edit Project Budget — {{ $project->name }}</h4>

            <a href="{{ route('budget.projects.show', $project->id) }}" class="btn btn-secondary">
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
                    $allocationsByYear = $project->allocations->keyBy(fn ($allocation) => (int) $allocation->year);
                    $activityTotalsByYear = $project->activities
                        ->flatMap(fn ($activity) => $activity->allocations)
                        ->groupBy(fn ($allocation) => (int) $allocation->year)
                        ->map(fn ($allocations) => (float) $allocations->sum('amount'));
                    $currency = $project->currency ?? $project->program?->currency ?? 'USD';
                @endphp

                <form method="POST" action="{{ route('budget.projects.allocations.update', $project->id) }}">
                    @csrf

                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Year</th>
                                <th>Allocation ({{ $project->program->currency }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($project->years() as $year)
                                @php
                                    $year = (int) $year;
                                    $allocation = $allocationsByYear->get($year);
                                    $activityTotal = (float) ($activityTotalsByYear[$year] ?? 0);
                                    $currentAmount = old('allocations.' . $year, optional($allocation)->amount ?? 0);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $year }}</div>
                                        <small class="text-muted">
                                            Activity total: {{ number_format($activityTotal, 2) }} {{ $currency }}
                                        </small>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0"
                                            name="allocations[{{ $year }}]"
                                            value="{{ $currentAmount }}"
                                            class="form-control text-end allocation-input"
                                            data-child-total="{{ $activityTotal }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="text-end">
                        <span class="text-muted">Project allocation total:</span>
                        <strong id="allocationTotal">0.00</strong> {{ $currency }}
                        <span class="text-muted">/ {{ number_format((float) $project->total_budget, 2) }}</span>
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
                    const childTotal = parseFloat(input.dataset.childTotal) || 0;
                    const amount = parseFloat(input.value) || 0;
                    input.classList.toggle('is-invalid', amount < childTotal);
                });
            }

            inputs.forEach(input => input.addEventListener('input', updateTotal));
            updateTotal();
        });
    </script>
@endsection
