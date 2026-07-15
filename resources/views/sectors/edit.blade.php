@extends('layouts.app')

@section('title', 'Edit Portfolio')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="fw-bold text-dark mb-1">Edit Portfolio</h4>
                <p class="text-muted mb-0">Update portfolio details, governance scope, portfolio leadership, and TTL ownership.</p>
            </div>
            <a href="{{ route('budget.portfolios.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('portfolio_leader_conversion_prompt'))
            @php
                $prompt = session('portfolio_leader_conversion_prompt');
            @endphp
            <div class="alert alert-warning mt-3">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <h6 class="fw-bold mb-1">Existing user found for this portfolio leader email</h6>
                        <div class="small">
                            {{ $prompt['name'] }} ({{ $prompt['email'] }}) currently has role
                            <strong>{{ $prompt['current_role'] }}</strong>. Convert this account to
                            <strong>{{ $prompt['target_role'] }}</strong> and assign it to this portfolio?
                        </div>
                    </div>
                    <button type="submit" form="portfolioEditForm" name="convert_existing_portfolio_leader" value="1" class="btn btn-warning">
                        Yes, Convert Account
                    </button>
                </div>
            </div>
        @endif

        <div class="card mt-3 shadow-sm border-0">
            <div class="card-body">
                <form id="portfolioEditForm" action="{{ route('budget.portfolios.update', $sector->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @php
                        $currentUser = auth()->user();
                        $canChooseGovernanceNode = (bool) ($currentUser?->isAdmin() || $currentUser?->isSuperAdmin());
                        $selectedGovernanceNodeId = old('governance_node_id', $sector->governance_node_id);
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

                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label class="form-label">Portfolio Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                value="{{ old('name', $sector->name) }}" required>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label">Portfolio Currency <span class="text-danger">*</span></label>
                            <select name="currency" class="form-select" required>
                                @foreach ($currencyOptions as $code => $label)
                                    <option value="{{ $code }}" @selected(old('currency', $sector->currency ?: 'USD') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label">Governance Node <span class="text-danger">*</span></label>
                            <select name="governance_node_id" class="form-select" required @disabled(! $canChooseGovernanceNode)>
                                <option value="">-- Select Node --</option>
                                @foreach ($nodes as $node)
                                    <option value="{{ $node->id }}" @selected($selectedGovernanceNodeId == $node->id)>
                                        {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                    </option>
                                @endforeach
                            </select>
                            @unless($canChooseGovernanceNode)
                                <input type="hidden" name="governance_node_id" value="{{ $selectedGovernanceNodeId }}">
                            @endunless
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label">Portfolio Status</label>
                            <select name="status" class="form-select">
                                <option value="active" @selected(old('status', $sector->status ?? 'active') === 'active')>Active</option>
                                <option value="ended" @selected(old('status', $sector->status ?? 'active') === 'ended')>Ended</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description', $sector->description) }}</textarea>
                        </div>

                        <div class="col-12">
                            <div class="border rounded-3 p-3 bg-success-subtle">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
                                    <div>
                                        <h6 class="fw-semibold mb-1">Portfolio Manager / Coordinator</h6>
                                        <p class="text-muted small mb-0">
                                            This account is assigned to oversee the portfolio across budget, finance, procurement, M&E, evaluation, and site visits.
                                        </p>
                                    </div>
                                    <span class="badge bg-success align-self-start">Required</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Leader Name <span class="text-danger">*</span></label>
                                        <input type="text" name="portfolio_manager_name" class="form-control"
                                            value="{{ old('portfolio_manager_name', $sector->portfolio_manager_name ?: optional($sector->portfolioManager)->name) }}"
                                            placeholder="Portfolio leader name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Leader Email <span class="text-danger">*</span></label>
                                        <input type="email" name="portfolio_manager_email" class="form-control"
                                            value="{{ old('portfolio_manager_email', $sector->portfolio_manager_email ?: optional($sector->portfolioManager)->email) }}"
                                            placeholder="leader@example.org" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Leadership Role <span class="text-danger">*</span></label>
                                        <select name="portfolio_manager_role" class="form-select" required>
                                            <option value="Portfolio Manager" @selected(old('portfolio_manager_role', $sector->portfolio_manager_role ?: 'Portfolio Manager') === 'Portfolio Manager')>
                                                Portfolio Manager
                                            </option>
                                            <option value="Portfolio Coordinator" @selected(old('portfolio_manager_role', $sector->portfolio_manager_role) === 'Portfolio Coordinator')>
                                                Portfolio Coordinator
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded-3 p-3 bg-light-subtle">
                                <h6 class="fw-semibold mb-3">Task Team Leader (TTL)</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">TTL Name <span class="text-danger">*</span></label>
                                        <input type="text" name="ttl_name" class="form-control"
                                            value="{{ old('ttl_name', $sector->ttl_name) }}" placeholder="Task Team Leader name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">TTL Email <span class="text-danger">*</span></label>
                                        <input type="email" name="ttl_email" class="form-control"
                                            value="{{ old('ttl_email', $sector->ttl_email) }}" placeholder="ttl@example.org" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button class="btn btn-primary">
                            <i class="feather-save me-1"></i> Update Portfolio
                        </button>
                        <a href="{{ route('budget.portfolios.index') }}" class="btn btn-light border">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
