@extends('layouts.app')

@section('title', 'Create Program Initialization / Creation')

@section('content')
    <style>
        .program-hero {
            background: linear-gradient(135deg, #063f36 0%, #0f766e 56%, #522b39 100%);
            color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 18px 36px rgba(6, 63, 54, 0.16);
        }
        .program-hero .badge-soft { background: rgba(255, 255, 255, 0.18); color: #fff; border: 1px solid rgba(255,255,255,0.25); }
        .program-hero h4,
        .program-hero p { color: #fff; }
        .program-hero-stat {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 999px;
            padding: 8px 12px;
            color: #effff9;
            background: rgba(255, 255, 255, 0.1);
            font-weight: 700;
        }
        .program-create-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 16px;
            align-items: start;
        }
        .section-card { border: 1px solid #dbe3ea; border-radius: 8px; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05); }
        .section-title { font-weight: 700; color: #0f172a; }
        .pill { border-radius: 999px; padding: 6px 12px; font-weight: 600; }
        .pill-info { background: #dff5ee; color: #064e3b; }
        .pill-success { background: #dcfce7; color: #166534; }
        .pill-warning { background: #fef9c3; color: #854d0e; }
        .help-hint { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 12px; }
        .form-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #0f172a;
            font-size: 0.9rem;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .form-section-title span {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border-radius: 8px;
            color: #006b3f;
            background: #dff5ee;
        }
        .funding-card {
            position: sticky;
            top: 88px;
            border: 1px solid #cfe5da;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 12px 28px rgba(6, 63, 54, 0.08);
        }
        .funding-card-header {
            padding: 16px;
            color: #fff;
            background: linear-gradient(135deg, #006b3f 0%, #0f766e 58%, #522b39 100%);
        }
        .funding-partner-name {
            font-size: 1.02rem;
            font-weight: 900;
            line-height: 1.25;
        }
        .funding-card-body { padding: 16px; }
        .funding-metric-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .funding-metric {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            background: #f8fafc;
        }
        .funding-metric span {
            display: block;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .funding-metric strong {
            display: block;
            color: #0f172a;
            margin-top: 3px;
        }
        .funder-list {
            display: grid;
            gap: 8px;
            margin-top: 12px;
        }
        .funder-list-item {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            background: #ffffff;
        }
        .funder-list-item strong { color: #0f172a; }
        .funder-list-item small { color: #64748b; }
        .allocation-card {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }
        .allocation-card .table {
            margin-bottom: 0;
        }
        @media (max-width: 1199.98px) {
            .program-create-shell { grid-template-columns: 1fr; }
            .funding-card { position: static; }
        }
    </style>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('admin/assets/css/select2-custom.css') }}">
    @endpush
    <main class="nxl-container">
        <div class="nxl-content">

            <!-- HEADER -->
            <div class="program-hero mb-4 d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge badge-soft">Budget - Programs</span>
                        <span class="pill pill-info">Auto allocations</span>
                    </div>
                    <h4 class="mb-1">Create Program Initialization / Creation</h4>
                    <p class="mb-0" style="opacity:0.9;">Select an approved program, confirm the funding partner, assign the TTL, and initialize the program structure.</p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="program-hero-stat"><i class="feather-briefcase"></i> Portfolio aligned</span>
                        <span class="program-hero-stat"><i class="feather-users"></i> Partner visible</span>
                        <span class="program-hero-stat"><i class="feather-user-check"></i> TTL workspace ready</span>
                    </div>
                </div>
                <a href="{{ route('budget.programs.index') }}" class="btn btn-light text-primary border-0 shadow-sm">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back
                </a>
            </div>

            {{-- GLOBAL ERROR DISPLAY --}}
            @if ($errors->any())
                <div class="alert alert-danger mt-3">
                    <strong>Error:</strong>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger mt-3">{{ session('error') }}</div>
            @endif


            <!-- FORM -->
            <div class="card shadow-sm border-0 section-card">
                <div class="card-body">

                    <form action="{{ route('budget.programs.store') }}" method="POST" id="programForm">
                        @csrf

                        <div class="row g-4">

                            <!-- LEFT COLUMN -->
                            <div class="col-lg-8">
                                <div class="form-section-title">
                                    <span><i class="feather-layers"></i></span>
                                    Program Setup
                                </div>
                                <div class="row g-3">
                                    <!-- PORTFOLIO -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Portfolio <span class="text-danger">*</span></label>
                                        <select name="sector_id" class="form-select" required>
                                            <option value="">-- Select Portfolio --</option>
                                            @foreach ($sectors as $sector)
                                                <option value="{{ $sector->id }}" @selected(old('sector_id') == $sector->id)>{{ $sector->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- PROGRAM ID -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Program ID <span class="text-danger">*</span></label>
                                        <input type="text" name="program_id" class="form-control" placeholder="PROG001" value="{{ old('program_id') }}" required>
                                    </div>

                                    <!-- PROGRAM NAME -->
                                    <div class="col-md-12">
                                        <label class="form-label fw-semibold">Program Name <span
                                                class="text-danger">*</span></label>
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
                                                    data-funders="{{ e(json_encode($funding['funders'] ?? [])) }}"
                                                    data-funder-summary="{{ $funding['funder_summary'] ?? '' }}"
                                                    data-funding-type="{{ $funding['funding_type'] ?? '' }}"
                                                    data-approved-at="{{ $funding['approved_at'] ?? '' }}"
                                                    @selected(old('program_name') === $programName)>
                                                    {{ $programName }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted d-block mt-1">
                                            Program names come from approved funding records.
                                        </small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">TTL Name <span class="text-danger">*</span></label>
                                        <input type="text" name="ttl_name" class="form-control" value="{{ old('ttl_name') }}"
                                            placeholder="Task Team Leader full name" required>
                                        <small class="text-muted d-block mt-1">This person will oversee the program from the TTL portal.</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">TTL Email <span class="text-danger">*</span></label>
                                        <input type="email" name="ttl_email" class="form-control" value="{{ old('ttl_email') }}"
                                            placeholder="ttl@example.org" required>
                                        <small class="text-muted d-block mt-1">Credentials or assignment details will be emailed here.</small>
                                    </div>

                                    <!-- CURRENCY + BUDGET -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                                        <select id="currencySelect" class="form-select" required disabled>
                                            <option value="">-- Select --</option>
                                            <option value="USD">USD</option>
                                        </select>
                                        <input type="hidden" name="currency" id="currencyHidden" value="{{ old('currency') }}">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Total Budget <span
                                                class="text-danger">*</span></label>
                                        <input type="number" name="total_budget" id="totalBudget" class="form-control"
                                            step="0.01" min="0" placeholder="0.00" required readonly>
                                    </div>

                                    <!-- YEARS -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Start Year <span class="text-danger">*</span></label>
                                        <input type="number" name="start_year" id="startYear" class="form-control" min="1900"
                                            max="2100" placeholder="2025" required readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">End Year <span class="text-danger">*</span></label>
                                        <input type="number" name="end_year" id="endYear" class="form-control" min="1900"
                                            max="2100" placeholder="2030" required readonly>
                                    </div>
                                    <!-- CALCULATED TOTAL YEARS -->
                                    <input type="hidden" name="total_years" id="totalYears">

                                    <!-- MODE -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Allocation Mode <span
                                                class="text-danger">*</span></label>
                                        <select name="mode" id="allocationMode" class="form-select" required>
                                            <option value="amount" selected>Amount</option>
                                            <option value="percentage">Percentage (%)</option>
                                        </select>
                                    </div>

                                    <!-- DESCRIPTION -->
                                    <div class="col-md-12">
                                        <label class="form-label fw-semibold">Description</label>
                                        <textarea name="description" class="form-control" rows="3" placeholder="Optional details">{{ old('description') }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN -->
                            <div class="col-lg-4">
                                <aside class="funding-card mb-3" id="fundingPartnerCard">
                                    <div class="funding-card-header">
                                        <div class="small text-uppercase fw-bold" style="letter-spacing:.08em;color:#d9fff4;">Funding Partner</div>
                                        <div class="funding-partner-name" id="fundingPartnerName">Select an approved program</div>
                                        <div class="small mt-1" id="fundingPartnerMeta" style="color:#e6fff3;">Partner details will appear here automatically.</div>
                                    </div>
                                    <div class="funding-card-body">
                                        <div class="funding-metric-grid">
                                            <div class="funding-metric">
                                                <span>Approved Amount</span>
                                                <strong id="fundingPartnerAmount">--</strong>
                                            </div>
                                            <div class="funding-metric">
                                                <span>Funding Period</span>
                                                <strong id="fundingPartnerPeriod">--</strong>
                                            </div>
                                            <div class="funding-metric">
                                                <span>Funding Type</span>
                                                <strong id="fundingPartnerType">--</strong>
                                            </div>
                                            <div class="funding-metric">
                                                <span>Approved On</span>
                                                <strong id="fundingPartnerApprovedAt">--</strong>
                                            </div>
                                        </div>
                                        <div class="funder-list" id="fundingPartnerRows">
                                            <div class="funder-list-item">
                                                <strong>No program selected</strong>
                                                <br><small>Choose a program to identify the partner funding it.</small>
                                            </div>
                                        </div>
                                    </div>
                                </aside>

                                <div class="help-hint mb-3">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="bi bi-lightbulb text-warning"></i>
                                        <strong>Quick tips</strong>
                                    </div>
                                    <ul class="mb-0 ps-3 small text-muted">
                                        <li>Select an approved program to auto-fill budget + years.</li>
                                        <li>The TTL receives a secure portal assignment email.</li>
                                        <li>Allocation rows appear once start/end years are set.</li>
                                        <li>Configure indicators later from Monitoring &amp; Evaluation.</li>
                                    </ul>
                                </div>

                                <!-- EXPECTED OUTCOME -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Expected Outcome Type <span
                                            class="text-danger">*</span></label>
                                    <select name="expected_outcome_type" id="expectedOutcomeType" class="form-select"
                                        required>
                                        <option value="">-- Select Type --</option>
                                        <option value="percentage" @selected(old('expected_outcome_type') === 'percentage')>
                                            Percentage
                                        </option>
                                        <option value="text" @selected(old('expected_outcome_type') === 'text')>
                                            Text
                                        </option>
                                    </select>
                                </div>

                                <div class="mb-3" id="expectedOutcomePercentageWrap" style="display:none;">
                                    <label class="form-label fw-semibold">Expected Outcome (%) <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="expected_outcome_percentage"
                                            id="expectedOutcomePercentage" class="form-control" min="0"
                                            max="100" step="0.01" value="{{ old('expected_outcome_percentage') }}"
                                            placeholder="0 - 100">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Example: 0% malaria rate by end of program.</small>
                                </div>

                                <div class="mb-3" id="expectedOutcomeTextWrap" style="display:none;">
                                    <label class="form-label fw-semibold">Expected Outcome (Text) <span
                                            class="text-danger">*</span></label>
                                    <textarea name="expected_outcome_text" id="expectedOutcomeText" class="form-control" rows="3"
                                        placeholder="Describe the expected outcome">{{ old('expected_outcome_text') }}</textarea>
                                    <small class="text-muted">Example: Send 2,000 students to school.</small>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    Indicators are managed from <strong>M&amp;E &rarr; Indicators</strong>.
                                </div>
                            </div>

                        </div>

                        <!-- DYNAMIC YEARLY ALLOCATIONS -->
                        <div id="allocationsContainer" class="mt-5" style="display:none;">
                            <div class="allocation-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                        <div>
                                            <h6 class="section-title mb-1">
                                                Yearly Allocation (<span id="currencyLabel">--</span>)
                                            </h6>
                                            <div class="small text-muted">The allocation rows follow the approved funding period automatically.</div>
                                        </div>
                                        <span class="badge bg-success-subtle text-success">Auto-generated</span>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 140px;">Year</th>
                                                    <th>Allocation <span id="allocationLabel">(Amount)</span></th>
                                                </tr>
                                            </thead>
                                            <tbody id="allocationTableBody"></tbody>
                                        </table>
                                    </div>

                                    <div class="alert alert-success mt-3 mb-0 d-flex align-items-center gap-2">
                                        <i class="bi bi-pie-chart-fill"></i>
                                        <div>
                                            Remaining: <strong id="remainingValue">0.00</strong>
                                            <span id="remainingCurrency">--</span>
                                            <span class="text-muted ms-2" id="remainingPercent"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ACTIONS -->
                        <div class="mt-4 d-flex justify-content-end">
                            <a href="{{ route('budget.programs.index') }}" class="btn btn-light border me-2">Cancel</a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check2-circle me-1"></i> Save Program
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </main>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        /* DOM ELEMENTS */
        const startYear = document.getElementById('startYear');
        const endYear = document.getElementById('endYear');
        const totalYears = document.getElementById('totalYears');
        const container = document.getElementById('allocationsContainer');
        const body = document.getElementById('allocationTableBody');
        const totalBudget = document.getElementById('totalBudget');
        const remainingValue = document.getElementById('remainingValue');
        const currencySelect = document.getElementById('currencySelect');
        const currencyHidden = document.getElementById('currencyHidden');
        const remainingCurrency = document.getElementById('remainingCurrency');
        const currencyLabel = document.getElementById('currencyLabel');
        const modeSelect = document.getElementById('allocationMode');
        const programNameSelect = document.getElementById('programNameSelect');
        const allocationLabel = document.getElementById('allocationLabel');
        const remainingPercent = document.getElementById('remainingPercent');
        const fundingPartnerName = document.getElementById('fundingPartnerName');
        const fundingPartnerMeta = document.getElementById('fundingPartnerMeta');
        const fundingPartnerAmount = document.getElementById('fundingPartnerAmount');
        const fundingPartnerPeriod = document.getElementById('fundingPartnerPeriod');
        const fundingPartnerType = document.getElementById('fundingPartnerType');
        const fundingPartnerApprovedAt = document.getElementById('fundingPartnerApprovedAt');
        const fundingPartnerRows = document.getElementById('fundingPartnerRows');

        function money(amount, currency) {
            const value = parseFloat(amount) || 0;
            return `${currency || 'USD'} ${value.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;
        }

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value || '';
            return div.innerHTML;
        }

        function parseFunders(selected) {
            try {
                return JSON.parse(selected.dataset.funders || '[]');
            } catch (error) {
                return [];
            }
        }

        function updateFundingPartnerCard(selected) {
            const funders = parseFunders(selected);
            const currency = selected.dataset.currency || 'USD';
            const total = selected.dataset.totalBudget || '';
            const start = selected.dataset.startYear || '';
            const end = selected.dataset.endYear || '';
            const fundingType = selected.dataset.fundingType || '';
            const approvedAt = selected.dataset.approvedAt || '';
            const funderSummary = selected.dataset.funderSummary || '';

            if (!selected.value) {
                fundingPartnerName.textContent = 'Select an approved program';
                fundingPartnerMeta.textContent = 'Partner details will appear here automatically.';
                fundingPartnerAmount.textContent = '--';
                fundingPartnerPeriod.textContent = '--';
                fundingPartnerType.textContent = '--';
                fundingPartnerApprovedAt.textContent = '--';
                fundingPartnerRows.innerHTML = `
                    <div class="funder-list-item">
                        <strong>No program selected</strong>
                        <br><small>Choose a program to identify the partner funding it.</small>
                    </div>`;
                return;
            }

            fundingPartnerName.textContent = funderSummary || 'Funding partner not assigned';
            fundingPartnerMeta.textContent = `${selected.value}`;
            fundingPartnerAmount.textContent = money(total, currency);
            fundingPartnerPeriod.textContent = start && end ? `${start} - ${end}` : '--';
            fundingPartnerType.textContent = fundingType ? fundingType.replace(/_/g, ' ') : '--';
            fundingPartnerApprovedAt.textContent = approvedAt || '--';

            if (funders.length === 0) {
                fundingPartnerRows.innerHTML = `
                    <div class="funder-list-item">
                        <strong>Funding partner not assigned</strong>
                        <br><small>This approved funding record has no partner linked yet.</small>
                    </div>`;
                return;
            }

            fundingPartnerRows.innerHTML = funders.map((funder) => `
                <div class="funder-list-item">
                    <strong>${escapeHtml(funder.name || 'Unassigned funding partner')}</strong>
                    <br><small>${escapeHtml(money(funder.amount, funder.currency || currency))} - ${escapeHtml((funder.funding_type || 'grant').replace(/_/g, ' '))} - ${escapeHtml(funder.period || 'N/A')}</small>
                </div>
            `).join('');
        }

        function updateCurrency(value) {
            if (!value) {
                currencySelect.value = '';
                currencyHidden.value = '';
                currencyLabel.textContent = '--';
                remainingCurrency.textContent = '--';
                return;
            }
            const exists = Array.from(currencySelect.options).some(opt => opt.value === value);
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = value;
                opt.textContent = value;
                currencySelect.appendChild(opt);
            }
            currencySelect.value = value;
            currencyHidden.value = value;
            currencyLabel.textContent = value;
            remainingCurrency.textContent = value;
        }

        /* Calculate total years from start + end */
        function calculateYears() {
            let s = parseInt(startYear.value);
            let e = parseInt(endYear.value);

            if (!s || !e || e < s) {
                container.style.display = "none";
                return;
            }

            let years = (e - s) + 1;
            totalYears.value = years;

            generateRows(s, e);
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
            updateFundingPartnerCard(selected);

            calculateYears();
        }

        programNameSelect.addEventListener('change', applyFundingDefaults);

        /* Generate allocation rows */
        function generateRows(start, end) {
            body.innerHTML = "";
            container.style.display = "block";

            for (let year = start; year <= end; year++) {
                body.innerHTML += `
            <tr>
                <td><strong>${year}</strong></td>
                <td>
                    <input type="number" class="form-control allocation-input"
                        name="allocations[${year}]"
                        step="0.01" min="0" value="0">
                </td>
            </tr>`;
            }

            document.querySelectorAll('.allocation-input')
                .forEach(inp => inp.addEventListener('input', calculateRemaining));

            calculateRemaining();
        }

        /* Calculate remaining balance */
        function calculateRemaining() {
            const budget = parseFloat(totalBudget.value) || 0;
            let total = 0;
            let totalPercent = 0;

            document.querySelectorAll('.allocation-input').forEach(input => {
                let val = parseFloat(input.value) || 0;

                if (modeSelect.value === "percentage") {
                    totalPercent += val;
                    val = budget * (val / 100);
                }
                total += val;
            });

            const remaining = budget - total;
            remainingValue.textContent = remaining.toFixed(2);
            remainingPercent.textContent = '';

            if (modeSelect.value === "percentage") {
                const remainingPct = 100 - totalPercent;
                remainingPercent.textContent = `(${remainingPct.toFixed(2)}%)`;
            }

            if (remaining < 0) {
                remainingValue.classList.add('text-danger');
            } else {
                remainingValue.classList.remove('text-danger');
            }
        }

        /* Change label for amount/percentage */
        modeSelect.addEventListener('change', () => {
            allocationLabel.textContent =
                modeSelect.value === "percentage" ? "Percentage (%)" : "Amount";
            calculateRemaining();
        });

        function toggleExpectedOutcomeFields() {
            const type = document.getElementById('expectedOutcomeType').value;
            const percentWrap = document.getElementById('expectedOutcomePercentageWrap');
            const textWrap = document.getElementById('expectedOutcomeTextWrap');

            percentWrap.style.display = type === 'percentage' ? 'block' : 'none';
            textWrap.style.display = type === 'text' ? 'block' : 'none';
        }

        document.getElementById('expectedOutcomeType').addEventListener('change', toggleExpectedOutcomeFields);
        toggleExpectedOutcomeFields();

        applyFundingDefaults();
    });
    </script>
    @endpush

@endsection
