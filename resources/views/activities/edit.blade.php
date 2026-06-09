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
                    <ul class="m-0">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
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

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Expected Outcome Type <span class="text-danger">*</span></label>
                                <select name="expected_outcome_type" id="expectedOutcomeType" class="form-select" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="percentage" {{ old('expected_outcome_type', $activity->expected_outcome_type) === 'percentage' ? 'selected' : '' }}>Percentage</option>
                                    <option value="text" {{ old('expected_outcome_type', $activity->expected_outcome_type) === 'text' ? 'selected' : '' }}>Text</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Expected Outcome <span class="text-danger">*</span></label>
                                <div id="expectedOutcomePercentage" style="display:none;">
                                    <div class="input-group">
                                        <input type="number" name="expected_outcome_percentage" class="form-control" min="0" max="100" step="0.01"
                                            value="{{ old('expected_outcome_percentage', $activity->expected_outcome_type === 'percentage' ? $activity->expected_outcome_value : '') }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div id="expectedOutcomeText" style="display:none;">
                                    <textarea name="expected_outcome_text" class="form-control" rows="2">{{ old('expected_outcome_text', $activity->expected_outcome_type === 'text' ? $activity->expected_outcome_value : '') }}</textarea>
                                </div>
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

                        {{-- MODE SWITCH --}}
                        <div class="d-flex align-items-center mb-3">
                            <label class="fw-semibold me-3">Allocation Mode:</label>

                            <select id="allocationMode" class="form-select w-auto">
                                <option value="amount">Amount</option>
                                <option value="percentage">Percentage (%)</option>
                            </select>
                        </div>

                        {{-- Total Project Budget Info --}}
                        <div class="alert alert-info py-2">
                            Project Total Budget:
                            <strong>{{ number_format($activity->project->total_budget, 2) }}
                                {{ $activity->project->currency }}</strong>
                        </div>

                        {{-- ALLOCATION TABLE --}}
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Year</th>
                                    <th>Amount</th>
                                    <th>AI Assist</th>
                                </tr>
                            </thead>

                            <tbody id="allocationTable">
                                @foreach ($activity->allocations as $alloc)
                                    <tr>
                                        <td class="fw-semibold">{{ $alloc->year }}</td>
                                        <td>
                                            <input type="number" step="0.01" name="allocations[{{ $alloc->year }}]"
                                                class="form-control alloc-input" data-year="{{ $alloc->year }}"
                                                value="{{ $alloc->amount }}">
                                        </td>

                                        <td>
                                            <span class="badge bg-success ai-icon d-none" id="ai-{{ $alloc->year }}">
                                                <i class="bi bi-magic"></i> AI Adjusted
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        {{-- Remaining Budget --}}
                        <div id="remainingBox" class="alert alert-warning mt-2">
                            Remaining: <strong id="remainingValue">0.00</strong>
                            {{ $activity->project->currency }}
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

    {{-- SMART AI & INTERACTION SCRIPT --}}
    <script>
        const projectBudget = {{ $activity->project->total_budget }};
        const inputs = document.querySelectorAll('.alloc-input');
        const modeSelect = document.getElementById('allocationMode');
        const remainingValue = document.getElementById('remainingValue');
        const remainingBox = document.getElementById('remainingBox');
        const expectedOutcomeType = document.getElementById("expectedOutcomeType");
        const expectedOutcomePercentage = document.getElementById("expectedOutcomePercentage");
        const expectedOutcomeText = document.getElementById("expectedOutcomeText");

        function recalc() {
            let total = 0;

            inputs.forEach(inp => {
                let val = parseFloat(inp.value) || 0;
                total += val;
            });

            const remaining = projectBudget - total;
            remainingValue.innerText = remaining.toFixed(2);

            remainingBox.className =
                remaining < 0 ? "alert alert-danger mt-2" : "alert alert-success mt-2";
        }

        // Smart Auto Adjust Mode
        function applySmartAI() {
            let remaining = projectBudget;
            let editable = [...inputs];

            editable.forEach(inp => {
                let autoValue = remaining / editable.length;
                inp.classList.add("ai-blink");
                inp.value = autoValue.toFixed(2);

                let year = inp.dataset.year;
                document.getElementById("ai-" + year).classList.remove('d-none');

                setTimeout(() => inp.classList.remove("ai-blink"), 1200);
            });

            recalc();
        }

        modeSelect.addEventListener('change', () => {
            if (modeSelect.value === "percentage") {
                let per = 100 / inputs.length;
                inputs.forEach(inp => inp.value = per);
            } else {
                applySmartAI();
            }
            recalc();
        });

        inputs.forEach(inp => {
            inp.addEventListener('input', recalc);
        });

        recalc();

        function toggleExpectedOutcome() {
            const type = expectedOutcomeType.value;
            expectedOutcomePercentage.style.display = type === "percentage" ? "block" : "none";
            expectedOutcomeText.style.display = type === "text" ? "block" : "none";
        }

        expectedOutcomeType.addEventListener("change", toggleExpectedOutcome);
        toggleExpectedOutcome();
    </script>

    <style>
        .ai-blink {
            animation: blinkGlow 1.5s ease-out 1;
            border: 2px solid #28a745 !important;
        }

        @keyframes blinkGlow {
            0% {
                box-shadow: 0 0 0px #28a745;
            }

            50% {
                box-shadow: 0 0 10px #28a745;
            }

            100% {
                box-shadow: 0 0 0px #28a745;
            }
        }
    </style>

@endsection
