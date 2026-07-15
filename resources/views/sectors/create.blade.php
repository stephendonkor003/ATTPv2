@extends('layouts.app')

@section('title', 'Create Portfolio')

@push('styles')
    <style>
        .portfolio-create {
            color: #0f172a;
        }

        .portfolio-create-hero {
            border-radius: 8px;
            padding: 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 58%, #522b39 100%);
            box-shadow: 0 16px 32px rgba(6, 63, 54, 0.14);
        }

        .portfolio-create-hero h4,
        .portfolio-create-hero p {
            color: #ffffff;
        }

        .portfolio-create-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .portfolio-create-chip {
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

        .portfolio-create-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.65fr);
            gap: 14px;
            align-items: start;
        }

        .portfolio-create-panel {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .portfolio-create-panel + .portfolio-create-panel {
            margin-top: 14px;
        }

        .portfolio-create-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 8px 8px 0 0;
            background: #f8fafc;
        }

        .portfolio-create-panel-body {
            padding: 18px;
        }

        .portfolio-create-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            color: #065f46;
            background: #d1fae5;
        }

        .portfolio-create-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .portfolio-create-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .portfolio-create-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .portfolio-create-title {
            margin-bottom: 0.15rem;
            color: #0f172a;
            font-weight: 800;
        }

        .portfolio-create-muted {
            color: #64748b;
            font-size: 0.84rem;
        }

        .portfolio-create .form-label {
            color: #334155;
            font-size: 0.82rem;
            font-weight: 800;
        }

        .portfolio-create .form-control,
        .portfolio-create .form-select {
            border-color: #cbd5e1;
            border-radius: 8px;
            min-height: 42px;
        }

        .portfolio-create .form-control:focus,
        .portfolio-create .form-select:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 0.18rem rgba(15, 118, 110, 0.14);
        }

        .portfolio-create textarea.form-control {
            min-height: 112px;
        }

        .portfolio-create-leader {
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 14px;
            background: #f0fdf4;
        }

        .portfolio-create-ttl {
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 14px;
            background: #fffbeb;
        }

        .portfolio-create-side {
            position: sticky;
            top: calc(var(--attp-backoffice-header-offset, 104px) + 12px);
        }

        .portfolio-create-check-list {
            display: grid;
            gap: 10px;
        }

        .portfolio-create-check {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            background: #f8fafc;
        }

        .portfolio-create-check strong {
            display: block;
            color: #0f172a;
            font-size: 0.86rem;
        }

        .portfolio-create-check span {
            color: #64748b;
            font-size: 0.8rem;
        }

        .portfolio-create-actions {
            position: sticky;
            bottom: 0;
            z-index: 5;
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 -10px 24px rgba(15, 23, 42, 0.08);
        }

        .portfolio-create-actions .btn-success,
        .portfolio-create-hero .btn-success {
            background: #0f766e;
            border-color: #0f766e;
        }

        .portfolio-create-actions .btn-success:hover,
        .portfolio-create-hero .btn-success:hover {
            background: #0b5f59;
            border-color: #0b5f59;
        }

        .portfolio-create-prompt {
            border: 1px solid #f59e0b;
            border-radius: 8px;
            background: #fffbeb;
        }

        @media (max-width: 1199.98px) {
            .portfolio-create-layout {
                grid-template-columns: 1fr;
            }

            .portfolio-create-side {
                position: static;
            }
        }

        @media (max-width: 767.98px) {
            .portfolio-create-hero,
            .portfolio-create-panel-header,
            .portfolio-create-panel-body {
                padding: 14px;
            }

            .portfolio-create-actions {
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
        $currencyOptions = [
            'USD' => 'USD - US Dollar',
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - Pound Sterling',
            'XOF' => 'XOF - West African CFA Franc',
            'XAF' => 'XAF - Central African CFA Franc',
            'GHS' => 'GHS - Ghana Cedi',
            'NGN' => 'NGN - Nigerian Naira',
            'KES' => 'KES - Kenyan Shilling',
            'ZAR' => 'ZAR - South African Rand',
            'ETB' => 'ETB - Ethiopian Birr',
        ];
    @endphp

    <div class="nxl-container portfolio-create">
        <div class="portfolio-create-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="portfolio-create-kicker mb-2">ATTP Portfolio Setup</div>
                    <h4 class="fw-bold mb-2">Create Portfolio</h4>
                    <p class="mb-0">
                        Register the portfolio, assign governance ownership, and appoint the manager or coordinator who will oversee delivery across the full ATTP workflow.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="portfolio-create-chip"><i class="feather-shield"></i> {{ number_format($nodes->count()) }} governance nodes</span>
                        <span class="portfolio-create-chip"><i class="feather-user-check"></i> Manager or coordinator required</span>
                        <span class="portfolio-create-chip"><i class="feather-briefcase"></i> Portfolio to sub-activity oversight</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    <a href="{{ route('budget.portfolios.index') }}" class="btn btn-light">
                        <i class="feather-arrow-left me-1"></i> Portfolio Register
                    </a>
                    <a href="{{ route('budget.programs.index') }}" class="btn btn-success">
                        <i class="feather-layers me-1"></i> Programs
                    </a>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="feather-check-circle me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
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
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('portfolio_leader_conversion_prompt'))
            @php
                $prompt = session('portfolio_leader_conversion_prompt');
            @endphp
            <div class="portfolio-create-prompt mt-3 p-3">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div class="d-flex gap-3">
                        <span class="portfolio-create-icon amber"><i class="feather-user-plus"></i></span>
                        <div>
                            <h6 class="fw-bold mb-1">Existing user found for this portfolio leader email</h6>
                            <div class="small text-muted">
                                {{ $prompt['name'] }} ({{ $prompt['email'] }}) currently has role
                                <strong>{{ $prompt['current_role'] }}</strong>. Convert this account to
                                <strong>{{ $prompt['target_role'] }}</strong> and assign it to this portfolio?
                            </div>
                        </div>
                    </div>
                    <button type="submit" form="portfolioCreateForm" name="convert_existing_portfolio_leader" value="1" class="btn btn-warning fw-bold">
                        Yes, Convert Account
                    </button>
                </div>
            </div>
        @endif

        <form id="portfolioCreateForm" action="{{ route('budget.portfolios.store') }}" method="POST" class="mt-3">
            @csrf

            <div class="portfolio-create-layout">
                <div>
                    <section class="portfolio-create-panel">
                        <div class="portfolio-create-panel-header">
                            <div class="d-flex gap-3">
                                <span class="portfolio-create-icon"><i class="feather-briefcase"></i></span>
                                <div>
                                    <h6 class="portfolio-create-title">Portfolio Identity</h6>
                                    <p class="portfolio-create-muted mb-0">Name the portfolio, set its governance owner, and define its current operating status.</p>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success">Required</span>
                        </div>

                        <div class="portfolio-create-panel-body">
                            <div class="row g-3">
                                <div class="col-lg-4">
                                    <label class="form-label">Portfolio Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Enter portfolio name" required>
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label">Portfolio Currency <span class="text-danger">*</span></label>
                                    <select name="currency" class="form-select" required>
                                        @foreach ($currencyOptions as $code => $label)
                                            <option value="{{ $code }}" @selected(old('currency', 'USD') === $code)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label">Governance Node <span class="text-danger">*</span></label>
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

                                <div class="col-lg-4">
                                    <label class="form-label">Portfolio Status</label>
                                    <select name="status" class="form-select">
                                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                        <option value="ended" @selected(old('status') === 'ended')>Ended</option>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" placeholder="Describe the mandate, scope, delivery focus, or governance context for this portfolio.">{{ old('description') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="portfolio-create-panel">
                        <div class="portfolio-create-panel-header">
                            <div class="d-flex gap-3">
                                <span class="portfolio-create-icon blue"><i class="feather-user-check"></i></span>
                                <div>
                                    <h6 class="portfolio-create-title">Portfolio Leadership</h6>
                                    <p class="portfolio-create-muted mb-0">Assign the manager or coordinator responsible for delivery oversight across the portfolio structure.</p>
                                </div>
                            </div>
                            <span class="badge bg-primary-subtle text-primary">Account linked</span>
                        </div>

                        <div class="portfolio-create-panel-body">
                            <div class="portfolio-create-leader">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Leader Name <span class="text-danger">*</span></label>
                                        <input type="text" name="portfolio_manager_name" class="form-control"
                                            value="{{ old('portfolio_manager_name') }}" placeholder="Portfolio leader name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Leader Email <span class="text-danger">*</span></label>
                                        <input type="email" name="portfolio_manager_email" class="form-control"
                                            value="{{ old('portfolio_manager_email') }}" placeholder="leader@example.org" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Leadership Role <span class="text-danger">*</span></label>
                                        <select name="portfolio_manager_role" class="form-select" required>
                                            <option value="Portfolio Manager" @selected(old('portfolio_manager_role', 'Portfolio Manager') === 'Portfolio Manager')>
                                                Portfolio Manager
                                            </option>
                                            <option value="Portfolio Coordinator" @selected(old('portfolio_manager_role') === 'Portfolio Coordinator')>
                                                Portfolio Coordinator
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="portfolio-create-panel">
                        <div class="portfolio-create-panel-header">
                            <div class="d-flex gap-3">
                                <span class="portfolio-create-icon amber"><i class="feather-users"></i></span>
                                <div>
                                    <h6 class="portfolio-create-title">Task Team Leader</h6>
                                    <p class="portfolio-create-muted mb-0">Capture the TTL responsible for technical direction and institutional coordination.</p>
                                </div>
                            </div>
                            <span class="badge bg-warning-subtle text-warning">Required</span>
                        </div>

                        <div class="portfolio-create-panel-body">
                            <div class="portfolio-create-ttl">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">TTL Name <span class="text-danger">*</span></label>
                                        <input type="text" name="ttl_name" class="form-control" value="{{ old('ttl_name') }}" placeholder="Task Team Leader name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">TTL Email <span class="text-danger">*</span></label>
                                        <input type="email" name="ttl_email" class="form-control" value="{{ old('ttl_email') }}" placeholder="ttl@example.org" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="portfolio-create-side">
                    <section class="portfolio-create-panel">
                        <div class="portfolio-create-panel-header">
                            <div class="d-flex gap-3">
                                <span class="portfolio-create-icon wine"><i class="feather-check-circle"></i></span>
                                <div>
                                    <h6 class="portfolio-create-title">Setup Checks</h6>
                                    <p class="portfolio-create-muted mb-0">The saved portfolio will become the parent for programs, activities, budgets, procurement, and M&E.</p>
                                </div>
                            </div>
                        </div>

                        <div class="portfolio-create-panel-body">
                            <div class="portfolio-create-check-list">
                                <div class="portfolio-create-check">
                                    <strong>Governance scope</strong>
                                    <span>Users outside the assigned governance node will not see this portfolio unless they have global access.</span>
                                </div>
                                <div class="portfolio-create-check">
                                    <strong>Leader account</strong>
                                    <span>A new account is created when the leader email does not exist. Existing emails require confirmation.</span>
                                </div>
                                <div class="portfolio-create-check">
                                    <strong>Portfolio coverage</strong>
                                    <span>The leader oversees linked programs, projects, activities, sub-activities, budget records, procurement, and M&E.</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>

            <div class="portfolio-create-actions d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3">
                <div class="small text-muted">
                    <i class="feather-info me-1"></i>
                    Save only after confirming the governance node, portfolio leader, and TTL details.
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                    <a href="{{ route('budget.portfolios.index') }}" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-success">
                        <i class="feather-save me-1"></i> Save Portfolio
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
