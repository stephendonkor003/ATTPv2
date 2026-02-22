@extends('layouts.app')
@section('title', 'Create Project')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/css/select2-custom.css') }}">
@endpush

@section('content')
    <style>
        .project-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.22rem 0.62rem;
            font-size: 0.72rem;
            font-weight: 600;
            border: 1px solid rgba(248, 250, 252, 0.38);
            background: rgba(248, 250, 252, 0.18);
            color: #f8fafc;
        }
        .section-card {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }

    </style>
    <main class="nxl-container">
        <div class="nxl-content">

            <!-- Header -->
            <div class="page-header">
                <div class="page-header-left">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="project-chip">Budget - Projects</span>
                        <span class="project-chip">Create</span>
                    </div>
                    <h5 class="m-b-10">Create New Project</h5>
                    <p class="mb-0">Assign under a program and set duration, budget and outcomes.</p>
                </div>
                <div class="page-header-right ms-auto">
                    <a href="{{ route('budget.projects.index') }}" class="btn btn-light text-primary border-0 shadow-sm">
                        <i class="bi bi-arrow-left-circle me-1"></i> Back to Projects
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Form Card -->
            <div class="card shadow-sm border-0 section-card">
                <div class="card-body">
                    <form action="{{ route('budget.projects.store') }}" method="POST">
                        @csrf

                        {{-- SECTION 1: Project Information --}}
                        <div class="mb-4">
                            <h5 class="fw-bold text-dark mb-3">Project Information</h5>
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Sector <span class="text-danger">*</span></label>
                                    <select id="sectorSelect" name="sector_id" class="form-select" required>
                                        <option value="">-- Select Sector --</option>
                                        @foreach ($sectors as $sector)
                                            <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Program <span class="text-danger">*</span></label>
                                    <select name="program_id" id="programSelect" class="form-select" required>
                                        <option value="">-- Select Program --</option>
                                    </select>
                                    @error('program_id')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Project Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                                        required placeholder="Enter project name">
                                    @error('name')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Total Budget (GHS) <span class="text-danger">*</span></label>
                                    <input type="number" name="total_budget" id="totalBudget" class="form-control" step="0.01" min="0"
                                        value="{{ old('total_budget') }}" required placeholder="0.00">
                                    @error('total_budget')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Start Year <span class="text-danger">*</span></label>
                                    <input type="number" name="start_year" id="startYear" class="form-control" min="2000" max="2100"
                                        value="{{ old('start_year', now()->year) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">End Year <span class="text-danger">*</span></label>
                                    <input type="number" name="end_year" id="endYear" class="form-control" min="2000" max="2100"
                                        value="{{ old('end_year', now()->year + 1) }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Expected Outcome Type <span class="text-danger">*</span></label>
                                    <select name="expected_outcome_type" id="expectedOutcomeType" class="form-select" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="percentage" {{ old('expected_outcome_type') === 'percentage' ? 'selected' : '' }}>Percentage</option>
                                        <option value="text" {{ old('expected_outcome_type') === 'text' ? 'selected' : '' }}>Text</option>
                                    </select>
                                </div>
                                <div class="col-md-4" id="expectedOutcomePercentageBox" style="display:none;">
                                    <label class="form-label fw-semibold">Outcome (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" name="expected_outcome_percentage" class="form-control" value="{{ old('expected_outcome_percentage') }}" placeholder="0 - 100">
                                </div>
                                <div class="col-md-12" id="expectedOutcomeTextBox" style="display:none;">
                                    <label class="form-label fw-semibold">Outcome Description</label>
                                    <textarea name="expected_outcome_text" rows="3" class="form-control" placeholder="Describe expected outcome">{{ old('expected_outcome_text') }}</textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" rows="3" class="form-control" placeholder="Optional project description">{{ old('description') }}</textarea>
                                </div>

                                <div class="col-12 mt-2">
                                    <div class="alert alert-info mb-0">
                                        Indicators are managed from <strong>M&amp;E &rarr; Indicators</strong>.
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- SECTION 2: Allocations --}}
                        <div class="mb-4">
                            <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-wallet2 text-primary"></i> Allocations
                                <small class="text-muted fw-normal" id="allocationsHint">Enter budget & years to generate yearly splits.</small>
                            </h5>
                            <div id="allocationsSection" class="d-none">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="allocation_mode" id="allocModeAmount" value="amount" checked>
                                        <label class="form-check-label" for="allocModeAmount">Enter Amounts</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="allocation_mode" id="allocModePercent" value="percent">
                                        <label class="form-check-label" for="allocModePercent">Enter Percentages</label>
                                    </div>
                                    <div class="text-end flex-grow-1">
                                        <small class="text-muted">Totals cannot exceed the project budget.</small>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:120px;">Year</th>
                                                <th style="width:180px;">Amount (GHS)</th>
                                                <th style="width:120px;">Percent</th>
                                            </tr>
                                        </thead>
                                    <tbody id="allocationsTableBody"></tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div id="allocationSummary" class="text-muted small"></div>
                                    <div id="allocationError" class="text-danger fw-semibold"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-4 text-end">
                            <a href="{{ route('budget.projects.index') }}" class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check2-circle me-1"></i> Save Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sectorPrograms = @json($sectors);

            const startYearInput = document.getElementById('startYear');
            const endYearInput = document.getElementById('endYear');
            const totalBudgetInput = document.getElementById('totalBudget');
            const expectedType = document.getElementById('expectedOutcomeType');
            const pctBox = document.getElementById('expectedOutcomePercentageBox');
            const txtBox = document.getElementById('expectedOutcomeTextBox');
            const allocationsSection = document.getElementById('allocationsSection');
            const allocationsTableBody = document.getElementById('allocationsTableBody');
            const allocationSummary = document.getElementById('allocationSummary');
            const allocationError = document.getElementById('allocationError');
            const sectorSelect = document.getElementById('sectorSelect');
            const programSelect = document.getElementById('programSelect');

            function toggleOutcomeInputs() {
                const val = expectedType.value;
                pctBox.style.display = val === 'percentage' ? 'block' : 'none';
                txtBox.style.display = val === 'text' ? 'block' : 'none';
            }
            expectedType?.addEventListener('change', toggleOutcomeInputs);
            toggleOutcomeInputs();

            const allocationsCache = {};
            function rebuildAllocations() {
                allocationsTableBody.innerHTML = '';
                allocationSummary.textContent = '';
                allocationError.textContent = '';
                allocationsSection.classList.add('d-none');

                const s = parseInt(startYearInput.value, 10);
                const e = parseInt(endYearInput.value, 10);
                const total = parseFloat(totalBudgetInput?.value || '0');
                if (Number.isNaN(s) || Number.isNaN(e) || e < s || total <= 0) return;

                allocationsSection.classList.remove('d-none');
                const years = e - s + 1;
                const mode = document.querySelector('input[name="allocation_mode"]:checked')?.value || 'amount';
                for (let y = s; y <= e; y++) {
                    const prev = allocationsCache[y] ?? (total / years);
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="fw-semibold">${y}</td>
                        <td>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm alloc-amount" data-year="${y}" value="${mode === 'amount' ? prev.toFixed(2) : ''}" ${mode === 'percent' ? 'readonly' : ''}>
                            <input type="hidden" name="allocations[${y}]" value="${prev.toFixed(2)}">
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0" max="100" class="form-control alloc-percent" data-year="${y}" value="${mode === 'percent' ? (prev / total * 100).toFixed(2) : ''}" ${mode === 'amount' ? 'readonly' : ''}>
                                <span class="input-group-text">%</span>
                            </div>
                        </td>
                    `;
                    allocationsTableBody.appendChild(tr);
                }
                updateAllocationSummary();
            }

            function updateAllocationSummary() {
                const rows = allocationsTableBody.querySelectorAll('tr');
                let sum = 0;
                const total = parseFloat(totalBudgetInput?.value || '0');
                rows.forEach(row => {
                    const hidden = row.querySelector('input[type="hidden"]');
                    if (hidden) sum += parseFloat(hidden.value || '0');
                });
                const percent = total > 0 ? (sum / total * 100) : 0;
                allocationSummary.textContent = `Allocated: ${sum.toFixed(2)} / ${total.toFixed(2)} (${percent.toFixed(1)}%)`;
                allocationError.textContent = sum > total + 0.0001 ? 'Allocations exceed total budget.' : '';
            }

            allocationsTableBody.addEventListener('input', (e) => {
                const row = e.target.closest('tr');
                const year = row?.querySelector('.alloc-amount')?.dataset.year;
                const hidden = row?.querySelector('input[type="hidden"]');
                const total = parseFloat(totalBudgetInput?.value || '0');
                if (e.target.classList.contains('alloc-amount')) {
                    const val = parseFloat(e.target.value || '0');
                    allocationsCache[year] = val;
                    if (hidden) hidden.value = val.toFixed(2);
                    const percentInput = row.querySelector('.alloc-percent');
                    if (percentInput && total > 0) percentInput.value = ((val / total) * 100).toFixed(2);
                } else if (e.target.classList.contains('alloc-percent')) {
                    const pct = parseFloat(e.target.value || '0');
                    const amt = total * (pct / 100);
                    allocationsCache[year] = amt;
                    if (hidden) hidden.value = amt.toFixed(2);
                    const amountInput = row.querySelector('.alloc-amount');
                    if (amountInput) amountInput.value = amt.toFixed(2);
                }
                updateAllocationSummary();
            });

            document.querySelectorAll('input[name="allocation_mode"]').forEach(r => r.addEventListener('change', rebuildAllocations));
            ['input', 'change'].forEach(evt => {
                startYearInput?.addEventListener(evt, rebuildAllocations);
                endYearInput?.addEventListener(evt, rebuildAllocations);
                totalBudgetInput?.addEventListener(evt, rebuildAllocations);
            });

            sectorSelect.addEventListener('change', function() {
                const sectorId = this.value;
                programSelect.innerHTML = '<option value="">-- Select Program --</option>';
                if (sectorId) {
                    const selectedSector = sectorPrograms.find(s => String(s.id) === String(sectorId));
                    if (selectedSector && selectedSector.programs?.length) {
                        selectedSector.programs.forEach(program => {
                            const option = document.createElement('option');
                            option.value = program.id;
                            option.textContent = program.name;
                            programSelect.appendChild(option);
                        });
                    } else {
                        const opt = document.createElement('option');
                        opt.textContent = 'No programs available';
                        programSelect.appendChild(opt);
                    }
                }
            });

            rebuildAllocations();
        });
    </script>

@endsection


