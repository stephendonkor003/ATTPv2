@extends('layouts.app')

@section('title', 'Create Program Funding')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/css/select2-custom.css') }}">
    <style>
        .funding-create-workspace {
            color: #0f172a;
        }

        .funding-create-hero {
            border-radius: 8px;
            padding: 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 58%, #522b39 100%);
            box-shadow: 0 16px 32px rgba(6, 63, 54, 0.14);
        }

        .funding-create-hero h4,
        .funding-create-hero p {
            color: #ffffff;
        }

        .funding-create-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .funding-create-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            color: #effff9;
            background: rgba(255, 255, 255, 0.1);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .funding-create-btn {
            border-radius: 8px;
            font-weight: 800;
        }

        .funding-create-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(340px, 0.55fr);
            gap: 14px;
            align-items: start;
        }

        .funding-create-panel {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .funding-create-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 8px 8px 0 0;
        }

        .funding-create-panel-body {
            padding: 18px;
        }

        .funding-create-section-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #065f46;
            background: #d1fae5;
            flex: 0 0 auto;
        }

        .funding-create-section-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .funding-create-section-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .funding-create-section-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .funding-create-title {
            margin-bottom: 0.15rem;
            color: #0f172a;
            font-weight: 800;
        }

        .funding-create-muted {
            color: #64748b;
            font-size: 0.84rem;
        }

        .funding-create-workspace .form-label {
            color: #334155;
            font-size: 0.82rem;
            font-weight: 800;
        }

        .funding-create-workspace .form-control,
        .funding-create-workspace .form-select {
            border-color: #cbd5e1;
            border-radius: 8px;
            min-height: 42px;
        }

        .funding-create-workspace .form-control:focus,
        .funding-create-workspace .form-select:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 0.18rem rgba(15, 118, 110, 0.14);
        }

        .funding-create-workspace textarea.form-control {
            min-height: 118px;
        }

        .funding-create-choice {
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 13px 14px;
            background: #f0fdf4;
        }

        .funding-create-choice .form-check-input {
            border-color: #0f766e;
        }

        .funding-create-choice .form-check-input:checked {
            background-color: #0f766e;
            border-color: #0f766e;
        }

        .funding-create-help-list {
            display: grid;
            gap: 10px;
        }

        .funding-create-help-item {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            background: #f8fafc;
        }

        .funding-create-help-item strong {
            display: block;
            color: #0f172a;
            font-size: 0.86rem;
        }

        .funding-create-help-item span {
            color: #64748b;
            font-size: 0.8rem;
        }

        .funding-document-row {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            background: #ffffff;
        }

        .funding-document-row + .funding-document-row {
            margin-top: 12px;
        }

        .funding-document-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.28rem 0.62rem;
            color: #075985;
            background: #e0f2fe;
            font-size: 0.75rem;
            font-weight: 800;
        }

        .funding-remove-document {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .funding-create-actions {
            position: sticky;
            bottom: 0;
            z-index: 5;
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 -10px 24px rgba(15, 23, 42, 0.08);
        }

        .funding-create-actions .btn-success {
            background: #0f766e;
            border-color: #0f766e;
        }

        .funding-create-actions .btn-success:hover {
            background: #0b5f59;
            border-color: #0b5f59;
        }

        .funding-create-panel .checkbox-multiselect {
            width: 100%;
        }

        .funding-create-panel .checkbox-multiselect-toggle {
            min-height: 42px;
            border-radius: 8px;
            border-color: #cbd5e1;
        }

        .funding-create-panel .checkbox-multiselect-dropdown {
            z-index: 30;
        }

        .funding-create-workspace .is-disabled {
            opacity: 0.64;
        }

        @media (max-width: 1199.98px) {
            .funding-create-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .funding-create-hero {
                padding: 16px;
            }

            .funding-create-panel-header,
            .funding-create-panel-body {
                padding: 14px;
            }

            .funding-create-actions {
                position: static;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $currentUser = auth()->user();
        $currentNodeId = optional($currentUser)->governance_node_id;
        $canChooseGovernanceNode = (bool) ($currentUser?->isAdmin() || $currentUser?->isSuperAdmin());
        $selectedGovernanceNodeId = old('governance_node_id', $canChooseGovernanceNode ? null : $currentNodeId);

        $selectedMemberStates = collect(old('member_state_ids', []))->map(fn ($id) => (string) $id)->all();
        $selectedRegionalBlocks = collect(old('regional_block_ids', []))->map(fn ($id) => (string) $id)->all();
        $selectedAspirations = collect(old('aspiration_ids', []))->map(fn ($id) => (string) $id)->all();
        $selectedGoals = collect(old('goal_ids', []))->map(fn ($id) => (string) $id)->all();
        $selectedFlagshipProjects = collect(old('flagship_project_ids', []))->map(fn ($id) => (string) $id)->all();

        $currencyOptions = ['USD', 'EUR', 'GBP', 'GHS', 'NGN', 'XOF', 'XAF', 'ZAR', 'KES', 'ETB', 'RWF'];
        $documentTypeOptions = ['MoU', 'Grant Agreement', 'Approval Letter', 'Budget Approval', 'Supporting Document'];
        $oldDocumentCount = max(
            1,
            count((array) old('document_types', [])),
            count((array) old('document_names', [])),
            count((array) old('document_descriptions', []))
        );
    @endphp

    <div class="nxl-container funding-create-workspace">
        <div class="funding-create-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="funding-create-kicker mb-2">Finance Funding Control</div>
                    <h4 class="fw-bold mb-2">Create Program Funding</h4>
                    <p class="mb-0">
                        Register a funding source, define its governance ownership, link AU strategic alignment, and attach supporting records before budget execution.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="funding-create-chip"><i class="feather-users"></i> {{ number_format($funders->count()) }} funders available</span>
                        <span class="funding-create-chip"><i class="feather-shield"></i> {{ number_format($nodes->count()) }} governance nodes</span>
                        <span class="funding-create-chip"><i class="feather-globe"></i> {{ number_format($memberStates->count()) }} member states</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    <a href="{{ route('finance.funders.index') }}" class="btn btn-light funding-create-btn">
                        <i class="feather-users me-1"></i> Funders
                    </a>
                    <a href="{{ route('finance.program-funding.index') }}" class="btn btn-success funding-create-btn">
                        <i class="feather-arrow-left me-1"></i> Back to Funding
                    </a>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mt-3">
                <div class="d-flex gap-2">
                    <i class="feather-alert-triangle mt-1"></i>
                    <div>
                        <strong>Please correct the following:</strong>
                        <ul class="mt-2 mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.program-funding.store') }}" enctype="multipart/form-data" class="mt-3">
            @csrf

            <div class="funding-create-grid">
                <div class="d-grid gap-3">
                    <section class="funding-create-panel">
                        <div class="funding-create-panel-header">
                            <div class="d-flex gap-3">
                                <span class="funding-create-section-icon"><i class="feather-file-text"></i></span>
                                <div>
                                    <h6 class="funding-create-title">Funding Identity</h6>
                                    <p class="funding-create-muted mb-0">Name the program, responsible node, funder, amount, and active funding period.</p>
                                </div>
                            </div>
                            <span class="badge bg-warning-subtle text-warning">Draft on save</span>
                        </div>
                        <div class="funding-create-panel-body">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <label class="form-label">Program Name *</label>
                                    <input type="text" name="program_name" class="form-control"
                                        value="{{ old('program_name') }}" placeholder="Enter program name" required>
                                </div>

                                <div class="col-lg-6">
                                    <label class="form-label">Funder *</label>
                                    <select name="funder_id" class="form-select" required>
                                        <option value="">Select funder</option>
                                        @foreach ($funders as $funder)
                                            <option value="{{ $funder->id }}" @selected(old('funder_id') == $funder->id)>
                                                {{ $funder->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6">
                                    <label class="form-label">Governance Node *</label>
                                    <select name="governance_node_id" class="form-select" required @disabled(! $canChooseGovernanceNode)>
                                        <option value="">Select governance node</option>
                                        @foreach ($nodes as $node)
                                            <option value="{{ $node->id }}" @selected((string) $selectedGovernanceNodeId === (string) $node->id)>
                                                {{ $node->name }}{{ $node->level?->name ? ' - ' . $node->level->name : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @unless ($canChooseGovernanceNode)
                                        <input type="hidden" name="governance_node_id" value="{{ $selectedGovernanceNodeId }}">
                                    @endunless
                                </div>

                                <div class="col-lg-6">
                                    <label class="form-label">Funding Type *</label>
                                    <select name="funding_type" class="form-select" required>
                                        <option value="">Select type</option>
                                        <option value="grant" @selected(old('funding_type') === 'grant')>Grant</option>
                                        <option value="allocation" @selected(old('funding_type') === 'allocation')>Government Allocation</option>
                                        <option value="capital" @selected(old('funding_type') === 'capital')>Capital Injection</option>
                                    </select>
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label">Approved Amount *</label>
                                    <input type="number" step="0.01" name="approved_amount" value="{{ old('approved_amount') }}"
                                        class="form-control" placeholder="0.00" required>
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label">Currency *</label>
                                    <input type="text" class="form-control currency-search mb-2" placeholder="Search currency">
                                    <select name="currency" class="form-select currency-select" required>
                                        <option value="">Select currency</option>
                                        @foreach ($currencyOptions as $currency)
                                            <option value="{{ $currency }}" @selected(old('currency', 'USD') === $currency)>
                                                {{ $currency }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label">Funding Period *</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="number" name="start_year" value="{{ old('start_year') }}"
                                                class="form-control" placeholder="Start" min="2000" required>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" name="end_year" value="{{ old('end_year') }}"
                                                class="form-control" placeholder="End" min="2000" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control"
                                        placeholder="Describe the purpose, scope, restrictions, or conditions attached to this funding.">{{ old('description') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="funding-create-panel">
                        <div class="funding-create-panel-header">
                            <div class="d-flex gap-3">
                                <span class="funding-create-section-icon blue"><i class="feather-globe"></i></span>
                                <div>
                                    <h6 class="funding-create-title">AU Strategic Alignment</h6>
                                    <p class="funding-create-muted mb-0">Connect the funding record to member states, RECs, aspirations, goals, and flagship priorities.</p>
                                </div>
                            </div>
                        </div>
                        <div class="funding-create-panel-body">
                            <div class="funding-create-choice mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="is_continental_initiative" value="1"
                                        class="form-check-input" id="is_continental_initiative"
                                        @checked(old('is_continental_initiative'))>
                                    <label class="form-check-label" for="is_continental_initiative">
                                        <strong>Continental Initiative</strong>
                                        <small class="text-muted d-block">Use this when the funding applies to all AU member states.</small>
                                    </label>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-lg-6" id="memberStatesWrapper">
                                    <label class="form-label">Beneficiary Member States</label>
                                    <select name="member_state_ids[]" class="form-select checkbox-multiselect-target" multiple
                                        id="memberStatesSelect" data-type="member-states"
                                        data-placeholder="Select member states">
                                        @foreach ($memberStates as $state)
                                            <option value="{{ $state->id }}" @selected(in_array((string) $state->id, $selectedMemberStates, true))>
                                                {{ $state->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6">
                                    <label class="form-label">Regional Blocks (RECs)</label>
                                    <select name="regional_block_ids[]" class="form-select checkbox-multiselect-target" multiple
                                        id="regionalBlocksSelect" data-type="regional-blocks"
                                        data-placeholder="Select regional blocks">
                                        @foreach ($regionalBlocks as $block)
                                            <option value="{{ $block->id }}" @selected(in_array((string) $block->id, $selectedRegionalBlocks, true))>
                                                {{ $block->display_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6">
                                    <label class="form-label">Agenda 2063 Aspirations</label>
                                    <select name="aspiration_ids[]" class="form-select checkbox-multiselect-target" multiple
                                        id="aspirationsSelect" data-type="aspirations"
                                        data-placeholder="Select aspirations">
                                        @foreach ($aspirations as $aspiration)
                                            <option value="{{ $aspiration->id }}" @selected(in_array((string) $aspiration->id, $selectedAspirations, true))>
                                                Aspiration {{ $aspiration->number }}: {{ $aspiration->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6">
                                    <label class="form-label">Agenda 2063 Goals</label>
                                    <select name="goal_ids[]" class="form-select checkbox-multiselect-target" multiple
                                        id="goalsSelect" data-type="goals" data-placeholder="Select goals">
                                        @foreach ($goals as $goal)
                                            <option value="{{ $goal->id }}" data-aspiration="{{ $goal->aspiration_id }}"
                                                @selected(in_array((string) $goal->id, $selectedGoals, true))>
                                                Goal {{ $goal->number }}: {{ $goal->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">AU Flagship Projects</label>
                                    <select name="flagship_project_ids[]" class="form-select checkbox-multiselect-target" multiple
                                        id="flagshipProjectsSelect" data-type="flagship-projects"
                                        data-placeholder="Select flagship projects">
                                        @foreach ($flagshipProjects as $project)
                                            <option value="{{ $project->id }}" @selected(in_array((string) $project->id, $selectedFlagshipProjects, true))>
                                                #{{ $project->number }}: {{ $project->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="funding-create-panel">
                        <div class="funding-create-panel-header">
                            <div class="d-flex gap-3">
                                <span class="funding-create-section-icon amber"><i class="feather-paperclip"></i></span>
                                <div>
                                    <h6 class="funding-create-title">Supporting Documents</h6>
                                    <p class="funding-create-muted mb-0">Attach agreements, approval letters, budgets, or supporting records. Add more rows when needed.</p>
                                </div>
                            </div>
                            <button type="button" id="add-document" class="btn btn-outline-success btn-sm funding-create-btn">
                                <i class="feather-plus me-1"></i> Add Document
                            </button>
                        </div>
                        <div class="funding-create-panel-body">
                            <div id="documents-wrapper">
                                @foreach (range(0, $oldDocumentCount - 1) as $documentIndex)
                                    <div class="funding-document-row document-row">
                                        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                            <span class="funding-document-badge">
                                                <i class="feather-file"></i>
                                                <span data-document-number>Document {{ $documentIndex + 1 }}</span>
                                            </span>
                                            <button type="button" class="btn btn-outline-danger btn-sm funding-remove-document remove-document">
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-lg-3">
                                                <label class="form-label">Document Type</label>
                                                <select name="document_types[]" class="form-select">
                                                    <option value="">Select type</option>
                                                    @foreach ($documentTypeOptions as $type)
                                                        <option value="{{ $type }}" @selected(old("document_types.$documentIndex") === $type)>
                                                            {{ $type }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-lg-4">
                                                <label class="form-label">Document Name</label>
                                                <input type="text" name="document_names[]" class="form-control"
                                                    value="{{ old("document_names.$documentIndex") }}"
                                                    placeholder="e.g. Signed Grant Agreement">
                                            </div>

                                            <div class="col-lg-3">
                                                <label class="form-label">Description</label>
                                                <input type="text" name="document_descriptions[]" class="form-control"
                                                    value="{{ old("document_descriptions.$documentIndex") }}"
                                                    placeholder="Optional summary">
                                            </div>

                                            <div class="col-lg-2">
                                                <label class="form-label">Upload File</label>
                                                <input type="file" name="documents[]" class="form-control"
                                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="alert alert-info d-flex gap-2 mt-3 mb-0">
                                <i class="feather-info mt-1"></i>
                                <div class="small">
                                    Accepted files: PDF, Word, Excel, JPG, and PNG. When a file is selected, its type and name must also be provided.
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="d-grid gap-3">
                    <section class="funding-create-panel">
                        <div class="funding-create-panel-header">
                            <div class="d-flex gap-3">
                                <span class="funding-create-section-icon wine"><i class="feather-check-circle"></i></span>
                                <div>
                                    <h6 class="funding-create-title">Record Checks</h6>
                                    <p class="funding-create-muted mb-0">Quick reminders before saving.</p>
                                </div>
                            </div>
                        </div>
                        <div class="funding-create-panel-body">
                            <div class="funding-create-help-list">
                                <div class="funding-create-help-item">
                                    <strong>Governance ownership</strong>
                                    <span>The selected node controls who can view and manage this funding record.</span>
                                </div>
                                <div class="funding-create-help-item">
                                    <strong>Funding period</strong>
                                    <span>The end year must be the same as, or later than, the start year.</span>
                                </div>
                                <div class="funding-create-help-item">
                                    <strong>Draft status</strong>
                                    <span>New records are saved as draft until they are reviewed and approved.</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="funding-create-panel">
                        <div class="funding-create-panel-header">
                            <div class="d-flex gap-3">
                                <span class="funding-create-section-icon blue"><i class="feather-lock"></i></span>
                                <div>
                                    <h6 class="funding-create-title">Access Scope</h6>
                                    <p class="funding-create-muted mb-0">Governance visibility is applied automatically.</p>
                                </div>
                            </div>
                        </div>
                        <div class="funding-create-panel-body">
                            <p class="funding-create-muted mb-0">
                                Global administrators can choose any available governance node. Other users save funding records against their assigned node.
                            </p>
                        </div>
                    </section>
                </aside>
            </div>

            <div class="funding-create-actions d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3">
                <div class="small text-muted">
                    <i class="feather-info me-1"></i>
                    Funding will be saved as <strong>DRAFT</strong> and must be approved before use.
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                    <a href="{{ route('finance.program-funding.index') }}" class="btn btn-light funding-create-btn">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-success funding-create-btn">
                        <i class="feather-save me-1"></i> Save Funding
                    </button>
                </div>
            </div>
        </form>
    </div>

    <template id="document-row-template">
        <div class="funding-document-row document-row">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <span class="funding-document-badge">
                    <i class="feather-file"></i>
                    <span data-document-number>Document</span>
                </span>
                <button type="button" class="btn btn-outline-danger btn-sm funding-remove-document remove-document">
                    <i class="feather-trash-2"></i>
                </button>
            </div>

            <div class="row g-3">
                <div class="col-lg-3">
                    <label class="form-label">Document Type</label>
                    <select name="document_types[]" class="form-select">
                        <option value="">Select type</option>
                        @foreach ($documentTypeOptions as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Document Name</label>
                    <input type="text" name="document_names[]" class="form-control" placeholder="e.g. Signed Grant Agreement">
                </div>

                <div class="col-lg-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="document_descriptions[]" class="form-control" placeholder="Optional summary">
                </div>

                <div class="col-lg-2">
                    <label class="form-label">Upload File</label>
                    <input type="file" name="documents[]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png">
                </div>
            </div>
        </div>
    </template>
@endsection

@push('scripts')
    <script src="{{ asset('admin/assets/js/checkbox-multiselect.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const wrapper = document.getElementById('documents-wrapper');
            const addBtn = document.getElementById('add-document');
            const template = document.getElementById('document-row-template');

            function documentRows() {
                return Array.from(wrapper.querySelectorAll('.document-row'));
            }

            function syncDocumentRequiredState(row) {
                const fileInput = row.querySelector('input[type="file"]');
                const typeInput = row.querySelector('select[name="document_types[]"]');
                const nameInput = row.querySelector('input[name="document_names[]"]');
                const hasFile = Boolean(fileInput && fileInput.value);

                if (typeInput) {
                    typeInput.required = hasFile;
                }

                if (nameInput) {
                    nameInput.required = hasFile;
                }
            }

            function updateDocumentRows() {
                const rows = documentRows();

                rows.forEach((row, index) => {
                    const numberLabel = row.querySelector('[data-document-number]');
                    const removeButton = row.querySelector('.remove-document');

                    if (numberLabel) {
                        numberLabel.textContent = `Document ${index + 1}`;
                    }

                    if (removeButton) {
                        removeButton.style.display = rows.length > 1 ? 'inline-flex' : 'none';
                    }

                    syncDocumentRequiredState(row);
                });
            }

            if (wrapper && addBtn && template) {
                addBtn.addEventListener('click', () => {
                    const fragment = template.content.cloneNode(true);
                    wrapper.appendChild(fragment);
                    updateDocumentRows();
                });

                wrapper.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('.remove-document');
                    if (!removeButton) {
                        return;
                    }

                    removeButton.closest('.document-row')?.remove();
                    updateDocumentRows();
                });

                wrapper.addEventListener('change', (event) => {
                    if (event.target.matches('input[type="file"]')) {
                        syncDocumentRequiredState(event.target.closest('.document-row'));
                    }
                });

                updateDocumentRows();
            }

            document.querySelectorAll('.currency-search').forEach((input) => {
                const select = input.parentElement.querySelector('.currency-select');
                if (!select) {
                    return;
                }

                input.addEventListener('input', () => {
                    const term = input.value.toLowerCase();

                    Array.from(select.options).forEach((option) => {
                        option.hidden = option.value !== '' && term && !option.value.toLowerCase().includes(term);
                    });
                });
            });

            const multiSelectInstances = {};

            if (window.CheckboxMultiSelect) {
                document.querySelectorAll('.checkbox-multiselect-target').forEach((select) => {
                    const id = select.id;

                    multiSelectInstances[id] = new CheckboxMultiSelect(select, {
                        type: select.dataset.type || 'default',
                        placeholder: select.dataset.placeholder || 'Select options',
                        searchPlaceholder: 'Type to search...',
                        showTags: true,
                        maxTagsVisible: 4
                    });
                });
            }

            const continentalCheckbox = document.getElementById('is_continental_initiative');
            const memberStatesWrapper = document.getElementById('memberStatesWrapper');
            const memberStatesSelect = document.getElementById('memberStatesSelect');

            function toggleMemberStates() {
                if (!continentalCheckbox || !memberStatesWrapper) {
                    return;
                }

                const memberStatesInstance = multiSelectInstances.memberStatesSelect;

                if (continentalCheckbox.checked) {
                    memberStatesWrapper.classList.add('is-disabled');

                    if (memberStatesSelect) {
                        memberStatesSelect.disabled = true;
                        Array.from(memberStatesSelect.options).forEach((option) => {
                            option.selected = false;
                        });
                    }

                    if (memberStatesInstance) {
                        memberStatesInstance.setDisabled(true);
                        memberStatesInstance.clearAll();
                    }
                } else {
                    memberStatesWrapper.classList.remove('is-disabled');

                    if (memberStatesSelect) {
                        memberStatesSelect.disabled = false;
                    }

                    if (memberStatesInstance) {
                        memberStatesInstance.setDisabled(false);
                    }
                }
            }

            if (continentalCheckbox) {
                continentalCheckbox.addEventListener('change', toggleMemberStates);
                toggleMemberStates();
            }
        });
    </script>
@endpush
