@extends('layouts.app')
@section('title', 'Edit Program')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/css/select2-custom.css') }}">
@endpush

@section('content')
    <style>
        .program-chip {
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
            <div class="page-header">
                <div class="page-header-left">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="program-chip">Budget - Programs</span>
                        <span class="program-chip">Edit</span>
                    </div>
                    <h5 class="m-b-10">Edit Program</h5>
                    <p class="mb-0">Update funding details and expected outcomes.</p>
                </div>
                <div class="page-header-right ms-auto">
                    <a href="{{ route('budget.programs.index') }}" class="btn btn-light text-primary border-0 shadow-sm">
                        <i class="bi bi-arrow-left-circle me-1"></i> Back
                    </a>
                </div>
            </div>

            <div class="card shadow-sm section-card">
                <div class="card-body">
                    <form action="{{ route('budget.programs.update', $program->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sector <span class="text-danger">*</span></label>
                                <select name="sector_id" class="form-select" required>
                                    @foreach ($sectors as $sector)
                                        <option value="{{ $sector->id }}"
                                            {{ $program->sector_id == $sector->id ? 'selected' : '' }}>
                                            {{ $sector->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sector_id')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Program Name <span class="text-danger">*</span></label>
                                <select name="program_name" id="programNameSelect" class="form-select" required>
                                    <option value="">-- Select Approved Program --</option>
                                    @foreach ($approvedPrograms as $programName)
                                        @php
                                            $funding = $approvedProgramFunding[$programName] ?? null;
                                        @endphp
                                        <option value="{{ $programName }}" data-currency="{{ $funding['currency'] ?? '' }}"
                                            data-start-year="{{ $funding['start_year'] ?? '' }}"
                                            data-end-year="{{ $funding['end_year'] ?? '' }}"
                                            data-total-budget="{{ $funding['total_budget'] ?? '' }}"
                                            @selected(old('program_name', $program->name) === $programName)>
                                            {{ $programName }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('program_name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                                <select id="currencySelect" class="form-select" required disabled>
                                    <option value="">-- Select --</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="GHS">GHS</option>
                                    <option value="NGN">NGN</option>
                                    <option value="ZAR">ZAR</option>
                                </select>
                                <input type="hidden" name="currency" id="currencyHidden"
                                    value="{{ old('currency', $program->currency) }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Total Budget</label>
                                <input type="number" id="totalBudget" class="form-control" readonly>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Start Year <span class="text-danger">*</span></label>
                                <input type="number" name="start_year" id="startYear" class="form-control" min="1900"
                                    max="2100" required readonly value="{{ old('start_year', $program->start_year) }}">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">End Year <span class="text-danger">*</span></label>
                                <input type="number" name="end_year" id="endYear" class="form-control" min="1900"
                                    max="2100" required readonly value="{{ old('end_year', $program->end_year) }}">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $program->description) }}</textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Expected Outcome Type <span
                                        class="text-danger">*</span></label>
                                <select name="expected_outcome_type" id="expectedOutcomeType" class="form-select" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="percentage" @selected(old('expected_outcome_type', $program->expected_outcome_type) === 'percentage')>
                                        Percentage
                                    </option>
                                    <option value="text" @selected(old('expected_outcome_type', $program->expected_outcome_type) === 'text')>
                                        Text
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6" id="expectedOutcomePercentageWrap" style="display:none;">
                                <label class="form-label fw-semibold">Expected Outcome (%) <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="expected_outcome_percentage" id="expectedOutcomePercentage"
                                        class="form-control" min="0" max="100" step="0.01"
                                        value="{{ old('expected_outcome_percentage', $program->expected_outcome_type === 'percentage' ? $program->expected_outcome_value : '') }}"
                                        placeholder="0 - 100">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                            <div class="col-md-12" id="expectedOutcomeTextWrap" style="display:none;">
                                <label class="form-label fw-semibold">Expected Outcome (Text) <span
                                        class="text-danger">*</span></label>
                                <textarea name="expected_outcome_text" id="expectedOutcomeText" class="form-control" rows="2"
                                    placeholder="Describe the expected outcome">{{ old('expected_outcome_text', $program->expected_outcome_type === 'text' ? $program->expected_outcome_value : '') }}</textarea>
                            </div>
                        </div>

                        <div class="col-12 mt-4">
                            <div class="alert alert-info mb-0">
                                Indicators are managed from <strong>M&amp;E &rarr; Indicators</strong>.
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <a href="{{ route('budget.programs.index') }}" class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save2 me-1"></i> Update Program
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const programNameSelect = document.getElementById('programNameSelect');
            const currencySelect = document.getElementById('currencySelect');
            const currencyHidden = document.getElementById('currencyHidden');
            const startYear = document.getElementById('startYear');
            const endYear = document.getElementById('endYear');
            const totalBudget = document.getElementById('totalBudget');

            function updateCurrency(value) {
                if (!value) return;
                const exists = Array.from(currencySelect.options).some(opt => opt.value === value);
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = value;
                    currencySelect.appendChild(opt);
                }
                currencySelect.value = value;
                currencyHidden.value = value;
            }

            function applyFundingDefaults() {
                const selected = programNameSelect.options[programNameSelect.selectedIndex];
                if (!selected) return;
                const currency = selected.dataset.currency || '';
                const start = selected.dataset.startYear || '';
                const end = selected.dataset.endYear || '';
                const total = selected.dataset.totalBudget || '';
                updateCurrency(currency);
                startYear.value = start;
                endYear.value = end;
                totalBudget.value = total;
            }
            programNameSelect.addEventListener('change', applyFundingDefaults);

            function toggleExpectedOutcomeFields() {
                const type = document.getElementById('expectedOutcomeType').value;
                const percentWrap = document.getElementById('expectedOutcomePercentageWrap');
                const textWrap = document.getElementById('expectedOutcomeTextWrap');
                percentWrap.style.display = type === 'percentage' ? 'block' : 'none';
                textWrap.style.display = type === 'text' ? 'block' : 'none';
            }
            document.getElementById('expectedOutcomeType').addEventListener('change', toggleExpectedOutcomeFields);
            toggleExpectedOutcomeFields();

            updateCurrency('{{ $program->currency }}');
            totalBudget.value = '{{ $program->total_budget }}';
            startYear.value = '{{ $program->start_year }}';
            endYear.value = '{{ $program->end_year }}';
        });
    </script>
@endsection
