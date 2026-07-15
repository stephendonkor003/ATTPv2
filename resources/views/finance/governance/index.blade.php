@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <style>
            .organogram-tree ul {
                list-style: none;
                padding-left: 1.5rem;
                margin: 0;
                position: relative;
            }
            .organogram-tree ul:before {
                content: '';
                position: absolute;
                left: 0.55rem;
                top: 0;
                bottom: 0;
                border-left: 1px solid #d0d7de;
            }
            .organogram-tree li {
                position: relative;
                padding-left: 1.5rem;
                margin: 0.5rem 0;
            }
            .organogram-tree li:before {
                content: '';
                position: absolute;
                left: 0.55rem;
                top: 0.95rem;
                width: 1.1rem;
                border-top: 1px solid #d0d7de;
            }
            .organogram-tree li:after {
                content: '';
                position: absolute;
                left: 1.6rem;
                top: 0.78rem;
                width: 0;
                height: 0;
                border-top: 6px solid transparent;
                border-bottom: 6px solid transparent;
                border-left: 7px solid #9aa4b5;
            }

            /* Prevent Bootstrap modal backdrop from blurring entire page */
            .modal-backdrop {
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                filter: none !important;
            }

            body.modal-open .main-wrapper,
            body.modal-open .content-wrapper,
            body.modal-open {
                filter: none !important;
            }
            .organogram-root > li:first-child:before,
            .organogram-root > li:first-child:after {
                top: 1.05rem;
            }
            .organogram-tree ul.organogram-root {
                padding-left: 0;
            }
            .organogram-tree ul.organogram-root:before {
                content: none;
            }
            .organogram-node {
                display: inline-block;
                padding: 0.45rem 0.75rem;
                background: #fdfefe;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                min-width: 240px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.06);
            }
            .organogram-name {
                font-weight: 700;
                color: #1f2937;
            }
            .organogram-level {
                font-size: 12px;
                color: #6b7280;
            }
            .organogram-dotted {
                font-size: 11px;
                color: #0d6efd;
                margin-top: 2px;
            }

            .governance-hero {
                background: #063f36;
                border-radius: 8px;
                padding: 18px 20px;
                color: #ffffff;
                box-shadow: 0 14px 32px rgba(6, 63, 54, 0.18);
            }

            .governance-hero h4,
            .governance-hero p {
                color: #ffffff;
            }

            .governance-chip {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                border: 1px solid rgba(255, 255, 255, 0.26);
                border-radius: 999px;
                padding: 0.28rem 0.62rem;
                color: #d9fff4;
                background: rgba(255, 255, 255, 0.08);
                font-size: 0.78rem;
                font-weight: 700;
            }

            .governance-stat-grid,
            .governance-node-grid {
                display: grid;
                gap: 12px;
            }

            .governance-stat-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .governance-stat-card,
            .governance-node-card {
                border: 1px solid #dbe3ea;
                border-radius: 8px;
                background: #ffffff;
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
            }

            .governance-stat-card {
                padding: 14px 16px;
            }

            .governance-stat-icon {
                width: 38px;
                height: 38px;
                border-radius: 8px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #065f46;
                background: #d1fae5;
                font-size: 1.05rem;
            }

            .governance-stat-label {
                color: #64748b;
                font-size: 0.78rem;
                font-weight: 700;
                text-transform: uppercase;
            }

            .governance-stat-value {
                color: #0f172a;
                font-size: 1.55rem;
                font-weight: 800;
                line-height: 1;
            }

            .governance-node-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .governance-node-card {
                padding: 14px;
                min-height: 184px;
                transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
            }

            .governance-node-card:hover {
                transform: translateY(-2px);
                border-color: #10b981;
                box-shadow: 0 14px 28px rgba(15, 23, 42, 0.11);
            }

            .governance-node-title {
                color: #0f172a;
                font-size: 0.98rem;
                font-weight: 800;
                line-height: 1.25;
            }

            .governance-node-meta {
                color: #64748b;
                font-size: 0.78rem;
            }

            .governance-node-count {
                display: flex;
                align-items: center;
                justify-content: space-between;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 8px 10px;
                background: #f8fafc;
            }

            .governance-node-count strong {
                color: #0f172a;
                font-size: 1.1rem;
            }

            @media (max-width: 1199.98px) {
                .governance-stat-grid,
                .governance-node-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 767.98px) {
                .governance-stat-grid,
                .governance-node-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <div class="governance-hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="governance-chip"><i class="feather-git-branch"></i> Governance Structure</span>
                        <span class="governance-chip"><i class="feather-users"></i> {{ number_format($governanceStats['direct_users']) }} users</span>
                        <span class="governance-chip"><i class="feather-briefcase"></i> {{ number_format($governanceStats['total_portfolios']) }} portfolios</span>
                    </div>
                    <h4 class="fw-bold mb-1">Governance Structure</h4>
                    <p class="mb-0">
                        Configure levels, nodes, reporting lines, and assignments across ATTP governance.
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="#nodesTab" class="btn btn-light btn-sm" data-bs-toggle="tab" data-bs-target="#nodesTab">
                        <i class="feather-layers me-1"></i> Nodes
                    </a>
                    <a href="#assignmentsTab" class="btn btn-outline-light btn-sm" data-bs-toggle="tab" data-bs-target="#assignmentsTab">
                        <i class="feather-user-check me-1"></i> Assignments
                    </a>
                </div>
            </div>
        </div>

        <div class="governance-stat-grid mt-3">
            <div class="governance-stat-card">
                <div class="d-flex align-items-center gap-3">
                    <span class="governance-stat-icon"><i class="feather-layers"></i></span>
                    <div>
                        <div class="governance-stat-label">Users</div>
                        <div class="governance-stat-value">{{ number_format($governanceStats['direct_users']) }}</div>
                        <small class="text-muted">Scoped to governance nodes</small>
                    </div>
                </div>
            </div>
            <div class="governance-stat-card">
                <div class="d-flex align-items-center gap-3">
                    <span class="governance-stat-icon"><i class="feather-briefcase"></i></span>
                    <div>
                        <div class="governance-stat-label">Total Portfolios</div>
                        <div class="governance-stat-value">{{ number_format($governanceStats['total_portfolios']) }}</div>
                        <small class="text-muted">All portfolios in the system</small>
                    </div>
                </div>
            </div>
            <div class="governance-stat-card">
                <div class="d-flex align-items-center gap-3">
                    <span class="governance-stat-icon"><i class="feather-check-circle"></i></span>
                    <div>
                        <div class="governance-stat-label">Active Portfolios</div>
                        <div class="governance-stat-value">{{ number_format($governanceStats['active_portfolios']) }}</div>
                        <small class="text-muted">Currently active</small>
                    </div>
                </div>
            </div>
            <div class="governance-stat-card">
                <div class="d-flex align-items-center gap-3">
                    <span class="governance-stat-icon"><i class="feather-archive"></i></span>
                    <div>
                        <div class="governance-stat-label">Ended Portfolios</div>
                        <div class="governance-stat-value">{{ number_format($governanceStats['ended_portfolios']) }}</div>
                        <small class="text-muted">Marked as ended</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Governance Portfolio Cards</h6>
                        <p class="text-muted small mb-0">Each card shows users and portfolio status totals for that governance node.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">{{ number_format($governanceCards->count()) }} nodes</span>
                </div>

                <div class="governance-node-grid">
                    @forelse ($governanceCards as $card)
                        <div class="governance-node-card">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <div class="governance-node-title">{{ $card['name'] }}</div>
                                    <div class="governance-node-meta">
                                        {{ $card['level'] }}
                                        @if ($card['code'])
                                            · {{ $card['code'] }}
                                        @endif
                                    </div>
                                </div>
                                <span class="badge {{ $card['status'] === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ ucfirst($card['status']) }}
                                </span>
                            </div>

                            <p class="text-muted small mb-3">
                                {{ Str::limit($card['description'] ?: 'No description added for this governance node.', 96) }}
                            </p>

                            <div class="d-grid gap-2">
                                <div class="governance-node-count">
                                    <span class="small text-muted"><i class="feather-users me-1"></i> Direct users</span>
                                    <strong>{{ number_format($card['direct_users_count']) }}</strong>
                                </div>
                                <div class="governance-node-count">
                                    <span class="small text-muted"><i class="feather-briefcase me-1"></i> Total portfolios</span>
                                    <strong>{{ number_format($card['total_portfolios_count']) }}</strong>
                                </div>
                                <div class="governance-node-count">
                                    <span class="small text-muted"><i class="feather-check-circle me-1"></i> Active portfolios</span>
                                    <strong>{{ number_format($card['active_portfolios_count']) }}</strong>
                                </div>
                                <div class="governance-node-count">
                                    <span class="small text-muted"><i class="feather-archive me-1"></i> Ended portfolios</span>
                                    <strong>{{ number_format($card['ended_portfolios_count']) }}</strong>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small">No governance nodes created yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success mt-3">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger mt-3">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                {{ $errors->first() }}
            </div>
        @endif

        <ul class="nav nav-tabs mt-3" id="governanceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="levels-tab" data-bs-toggle="tab" data-bs-target="#levelsTab"
                    type="button" role="tab" aria-controls="levelsTab" aria-selected="true">
                    Levels
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="nodes-tab" data-bs-toggle="tab" data-bs-target="#nodesTab"
                    type="button" role="tab" aria-controls="nodesTab" aria-selected="false">
                    Structure Nodes
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="lines-tab" data-bs-toggle="tab" data-bs-target="#linesTab" type="button"
                    role="tab" aria-controls="linesTab" aria-selected="false">
                    Reporting Lines
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#assignmentsTab"
                    type="button" role="tab" aria-controls="assignmentsTab" aria-selected="false">
                    Assignments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="organogram-tab" data-bs-toggle="tab" data-bs-target="#organogramTab"
                    type="button" role="tab" aria-controls="organogramTab" aria-selected="false">
                    Organogram
                </button>
            </li>
        </ul>

        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="levelsTab" role="tabpanel" aria-labelledby="levels-tab">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h6 class="fw-bold mb-1">Governance Levels</h6>
                                <p class="text-muted small mb-0">Define the hierarchy levels used across the governance structure.</p>
                            </div>
                        </div>

                        @canany(['finance.governance_structure.create', 'finance.governance_structure.manage'])
                            <hr>
                            <form method="POST" action="{{ route('finance.governance.levels.store') }}" class="row g-3">
                                @csrf
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Key</label>
                                    <input type="text" name="key" id="createLevelKey" class="form-control" required placeholder="e.g. organ" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Name</label>
                                    <input type="text" name="name" id="createLevelName" class="form-control" required placeholder="e.g. Organ">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Sort Order</label>
                                    <input type="number" name="sort_order" class="form-control" min="0" step="1" value="{{ ($levels->max('sort_order') ?? -1) + 1 }}" readonly>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Description</label>
                                    <input type="text" name="description" class="form-control" placeholder="Optional description">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary">
                                        <i class="feather-plus-circle me-1"></i> Add Level
                                    </button>
                                </div>
                            </form>
                        @endcanany
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-3">
                    <div class="card-body">
                        <table id="governanceLevelsTable"
                            class="table table-striped table-hover data-table"
                            style="width: 100%;"
                            data-config='@json(["language" => ["emptyTable" => "No levels created yet."]])'>
                            <thead>
                                <tr>
                                    <th style="width: 140px;">Key</th>
                                    <th>Name</th>
                                    <th style="width: 110px;">Sort Order</th>
                                    <th>Description</th>
                                    <th style="width: 140px;" class="text-center no-sort no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($levels as $level)
                                    <tr>
                                        <td><code>{{ $level->key }}</code></td>
                                        <td>{{ $level->name }}</td>
                                        <td>{{ $level->sort_order }}</td>
                                        <td>{{ $level->description ?? '-' }}</td>
                                        <td class="text-center no-export">
                                            <div class="d-flex justify-content-center gap-2">
                                                @canany(['finance.governance_structure.edit', 'finance.governance_structure.manage'])
                                                    <a class="btn btn-sm btn-outline-warning"
                                                        href="{{ route('finance.governance.levels.edit', $level) }}">
                                                        <i class="feather-edit"></i>
                                                    </a>
                                                @endcanany
                                                @canany(['finance.governance_structure.delete', 'finance.governance_structure.manage'])
                                                    <form method="POST"
                                                        action="{{ route('finance.governance.levels.destroy', $level) }}" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Delete this level?')">
                                                            <i class="feather-trash-2"></i>
                                                        </button>
                                                    </form>
                                                @endcanany
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="nodesTab" role="tabpanel" aria-labelledby="nodes-tab">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h6 class="fw-bold mb-1">Structure Nodes</h6>
                                <p class="text-muted small mb-0">Define Organ &rarr; Commission &rarr; Department &rarr; Directorate &rarr;
                                    Division/Unit.</p>
                            </div>
                        </div>

                        @canany(['finance.governance_structure.create', 'finance.governance_structure.manage'])
                            <hr>
                            <form method="POST" action="{{ route('finance.governance.nodes.store') }}" class="row g-3">
                                @csrf
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Level</label>
                                    <select name="level_id" class="form-select" required>
                                        <option value="">-- Select Level --</option>
                                        @foreach ($levels as $level)
                                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Name</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Code</label>
                                    <input type="text" name="code" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Effective Start</label>
                                    <input type="date" name="effective_start" class="form-control">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Description</label>
                                    <input type="text" name="description" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary">
                                        <i class="feather-plus-circle me-1"></i> Add Node
                                    </button>
                                </div>
                            </form>
                        @endcanany
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-3">
                    <div class="card-body">
                        <table id="governanceNodesTable"
                            class="table table-striped table-hover data-table"
                            style="width: 100%;"
                            data-config='@json(["language" => ["emptyTable" => "No nodes created yet."]])'>
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Level</th>
                                    <th>Name</th>
                                    <th style="width: 100px;">Code</th>
                                    <th style="width: 150px;">Users</th>
                                    <th style="width: 100px;" class="text-center">Status</th>
                                    <th style="width: 130px;">Effective Start</th>
                                    <th style="width: 140px;" class="text-center no-sort no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($nodes as $node)
                                    <tr>
                                        <td><span class="badge bg-primary">{{ $node->level->name ?? '-' }}</span></td>
                                        <td>
                                            <div class="fw-semibold">{{ $node->name }}</div>
                                            <div class="text-muted small">
                                                {{ $node->description ?? '-' }}
                                            </div>
                                        </td>
                                        <td><code>{{ $node->code ?? '-' }}</code></td>
                                        <td>
                                            <div class="fw-semibold">{{ number_format($node->direct_users_count) }} direct</div>
                                            <small class="text-muted">{{ number_format($node->assignment_count) }} assignment{{ $node->assignment_count === 1 ? '' : 's' }}</small>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge {{ $node->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                                {{ ucfirst($node->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                {{ $node->effective_start?->format('d M Y') ?? '-' }}
                                            </div>
                                        </td>
                                        <td class="text-center no-export">
                                            <div class="d-flex justify-content-center gap-2">
                                                @canany(['finance.governance_structure.edit', 'finance.governance_structure.manage'])
                                                    <a class="btn btn-sm btn-outline-warning"
                                                        href="{{ route('finance.governance.nodes.edit', $node) }}">
                                                        <i class="feather-edit"></i>
                                                    </a>
                                                @endcanany
                                                @canany(['finance.governance_structure.delete', 'finance.governance_structure.manage'])
                                                    <form method="POST"
                                                        action="{{ route('finance.governance.nodes.destroy', $node) }}" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Delete this node?')">
                                                            <i class="feather-trash-2"></i>
                                                        </button>
                                                    </form>
                                                @endcanany
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="linesTab" role="tabpanel" aria-labelledby="lines-tab">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h6 class="fw-bold mb-1">Reporting Lines</h6>
                                <p class="text-muted small mb-0">Use primary lines for hierarchy, dotted/advisory for
                                    matrix structures.</p>
                                <div class="small text-muted mt-2">
                                    <div><strong>Primary:</strong> formal hierarchy (one active primary per node).</div>
                                    <div><strong>Dotted:</strong> matrix reporting for cross-functional work.</div>
                                    <div><strong>Advisory:</strong> guidance relationship without line authority.</div>
                                    <div><strong>Effective dates:</strong> define when each line is valid.</div>
                                </div>
                            </div>
                        </div>

                        @canany(['finance.governance_structure.create', 'finance.governance_structure.manage'])
                            <hr>
                            <form method="POST" action="{{ route('finance.governance.lines.store') }}" class="row g-3">
                                @csrf
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Child Node</label>
                                    <select name="child_node_id" class="form-select" required>
                                        <option value="">-- Select Node --</option>
                                        @foreach ($nodes as $node)
                                            <option value="{{ $node->id }}">
                                                {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Parent Node</label>
                                    <select name="parent_node_id" class="form-select" required>
                                        <option value="">-- Select Node --</option>
                                        @foreach ($nodes as $node)
                                            <option value="{{ $node->id }}">
                                                {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Line Type</label>
                                    <select name="line_type" class="form-select" required>
                                        <option value="primary">Primary (Hierarchy)</option>
                                        <option value="dotted">Dotted (Matrix)</option>
                                        <option value="advisory">Advisory</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Effective Start</label>
                                    <input type="date" name="effective_start" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Effective End</label>
                                    <input type="date" name="effective_end" class="form-control">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary">
                                        <i class="feather-plus-circle me-1"></i> Add Reporting Line
                                    </button>
                                </div>
                            </form>
                        @endcanany
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-3">
                    <div class="card-body">
                        <table id="governanceLinesTable"
                            class="table table-striped table-hover data-table"
                            style="width: 100%;"
                            data-config='@json(["language" => ["emptyTable" => "No reporting lines created yet."]])'>
                            <thead>
                                <tr>
                                    <th>Child Node</th>
                                    <th>Parent Node</th>
                                    <th style="width: 130px;" class="text-center">Type</th>
                                    <th style="width: 180px;">Effective</th>
                                    <th style="width: 140px;" class="text-center no-sort no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $line)
                                    <tr>
                                        <td>
                                            <strong>{{ $line->child->name ?? '-' }}</strong>
                                            <div class="text-muted small"><i class="feather-tag me-1"></i>{{ $line->child->level->name ?? '' }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ $line->parent->name ?? '-' }}</strong>
                                            <div class="text-muted small"><i class="feather-tag me-1"></i>{{ $line->parent->level->name ?? '' }}</div>
                                        </td>
                                        <td class="text-center">
                                            @if($line->line_type === 'primary')
                                                <span class="badge bg-success">{{ ucfirst($line->line_type) }}</span>
                                            @elseif($line->line_type === 'dotted')
                                                <span class="badge bg-info">{{ ucfirst($line->line_type) }}</span>
                                            @else
                                                <span class="badge bg-warning">{{ ucfirst($line->line_type) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="small">
                                                <i class="feather-calendar me-1"></i>{{ $line->effective_start?->format('d M Y') ?? '-' }}
                                                &rarr;
                                                {{ $line->effective_end?->format('d M Y') ?? 'Open' }}
                                            </div>
                                        </td>
                                        <td class="text-center no-export">
                                            <div class="d-flex justify-content-center gap-2">
                                                @canany(['finance.governance_structure.edit', 'finance.governance_structure.manage'])
                                                    <a class="btn btn-sm btn-outline-warning"
                                                        href="{{ route('finance.governance.lines.edit', $line) }}">
                                                        <i class="feather-edit"></i>
                                                    </a>
                                                @endcanany
                                                @canany(['finance.governance_structure.delete', 'finance.governance_structure.manage'])
                                                    <form method="POST"
                                                        action="{{ route('finance.governance.lines.destroy', $line) }}" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Delete this reporting line?')">
                                                            <i class="feather-trash-2"></i>
                                                        </button>
                                                    </form>
                                                @endcanany
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="assignmentsTab" role="tabpanel" aria-labelledby="assignments-tab">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h6 class="fw-bold mb-1">Assignments</h6>
                                <p class="text-muted small mb-0">Assign employees and roles to governance nodes with
                                    effective dates.</p>
                            </div>
                        </div>

                        @canany(['finance.governance_structure.create', 'finance.governance_structure.manage'])
                            <hr>
                            <form method="POST" action="{{ route('finance.governance.assignments.store') }}"
                                class="row g-3">
                                @csrf
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Node</label>
                                    <select name="node_id" class="form-select" required>
                                        <option value="">-- Select Node --</option>
                                        @foreach ($nodes as $node)
                                            <option value="{{ $node->id }}">
                                                {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Employee</label>
                                    <input type="text" class="form-control user-search mb-2"
                                        placeholder="Search employee name or email">
                                    <select name="user_id" class="form-select" required>
                                        <option value="">-- Select Employee --</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->email }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Role Title</label>
                                    <input type="text" name="role_title" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Effective Start</label>
                                    <input type="date" name="effective_start" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Effective End</label>
                                    <input type="date" name="effective_end" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" value="1" name="is_primary"
                                            id="assignmentPrimary">
                                        <label class="form-check-label fw-semibold" for="assignmentPrimary">
                                            Primary Assignment
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" value="1" name="notify_user"
                                            id="assignmentNotify">
                                        <label class="form-check-label fw-semibold" for="assignmentNotify">
                                            Email notification to user
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary">
                                        <i class="feather-plus-circle me-1"></i> Add Assignment
                                    </button>
                                </div>
                            </form>
                        @endcanany
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-3">
                    <div class="card-body">
                        <table id="governanceAssignmentsTable"
                            class="table table-striped table-hover data-table"
                            style="width: 100%;"
                            data-config='@json(["language" => ["emptyTable" => "No assignments created yet."]])'>
                            <thead>
                                <tr>
                                    <th>Node</th>
                                    <th>Employee</th>
                                    <th style="width: 150px;">Role</th>
                                    <th style="width: 100px;" class="text-center">Primary</th>
                                    <th style="width: 180px;">Effective</th>
                                    <th style="width: 140px;" class="text-center no-sort no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assignments as $assignment)
                                    <tr>
                                        <td>
                                            <strong>{{ $assignment->node->name ?? '-' }}</strong>
                                            <div class="text-muted small"><i class="feather-tag me-1"></i>{{ $assignment->node->level->name ?? '' }}</div>
                                        </td>
                                        <td>
                                            <div><strong>{{ $assignment->user->name ?? '-' }}</strong></div>
                                            <div class="text-muted small"><i class="feather-mail me-1"></i>{{ $assignment->user->email ?? '' }}</div>
                                        </td>
                                        <td><span class="badge bg-info">{{ $assignment->role_title ?? '-' }}</span></td>
                                        <td class="text-center">
                                            <span
                                                class="badge {{ $assignment->is_primary ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $assignment->is_primary ? 'Yes' : 'No' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <i class="feather-calendar me-1"></i>{{ $assignment->effective_start?->format('d M Y') ?? '-' }}
                                                &rarr;
                                                {{ $assignment->effective_end?->format('d M Y') ?? 'Open' }}
                                            </div>
                                        </td>
                                        <td class="text-center no-export">
                                            <div class="d-flex justify-content-center gap-2">
                                                @canany(['finance.governance_structure.edit', 'finance.governance_structure.manage'])
                                                    <a class="btn btn-sm btn-outline-warning"
                                                        href="{{ route('finance.governance.assignments.edit', $assignment) }}">
                                                        <i class="feather-edit"></i>
                                                    </a>
                                                @endcanany
                                                @canany(['finance.governance_structure.delete', 'finance.governance_structure.manage'])
                                                    <form method="POST"
                                                        action="{{ route('finance.governance.assignments.destroy', $assignment) }}" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Delete this assignment?')">
                                                            <i class="feather-trash-2"></i>
                                                        </button>
                                                    </form>
                                                @endcanany
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="organogramTab" role="tabpanel" aria-labelledby="organogram-tab">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h6 class="fw-bold mb-1">Organogram</h6>
                                <p class="text-muted small mb-0">Derived from primary reporting lines. Dotted/advisory links are shown as side notes.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-3">
                    <div class="card-body">
                        <div id="organogramTree" class="organogram-tree"></div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Initialize DataTables for governance tables with special handling for collapsed edit rows
            $(document).ready(function() {
                // Configuration for all governance tables
                const governanceTableConfig = $.extend(true, {}, window.dataTableConfig, {
                    drawCallback: function() {
                        // Remove DataTables classes from collapsed rows
                        $('.child-row.collapse').removeClass('odd even');
                    }
                });

                // Initialize each table
                $('#governanceLevelsTable').DataTable(governanceTableConfig);
                $('#governanceNodesTable').DataTable(governanceTableConfig);
                $('#governanceLinesTable').DataTable(governanceTableConfig);
                $('#governanceAssignmentsTable').DataTable(governanceTableConfig);
            });
        </script>

        <script>
            document.querySelectorAll('.user-search').forEach(input => {
                const form = input.closest('form');
                const select = form ? form.querySelector('select[name="user_id"]') : null;
                if (!select) return;

                input.addEventListener('input', () => {
                    const term = input.value.toLowerCase();
                    Array.from(select.options).forEach(option => {
                        if (option.value === '') {
                            option.hidden = false;
                            return;
                        }
                        const text = option.text.toLowerCase();
                        option.hidden = term && !text.includes(term);
                    });
                });
            });

            // Auto-generate level key from name (slug) and keep sort order readonly
            const nameInput = document.getElementById('createLevelName');
            const keyInput = document.getElementById('createLevelKey');
            if (nameInput && keyInput) {
                nameInput.addEventListener('input', () => {
                    const slug = nameInput.value
                        .toLowerCase()
                        .trim()
                        .replace(/[^a-z0-9]+/g, '_')
                        .replace(/^_+|_+$/g, '');
                    keyInput.value = slug;
                });
            }

            // Organogram rendering (built strictly from reporting lines)
            const orgContainer = document.getElementById('organogramTree');
            if (orgContainer) {
                const nodes = @json($orgNodes);   // [{id, name, level}]
                const lines = @json($orgLines);   // [{child, parent, type}]

                const nodeMap = Object.fromEntries(nodes.map(n => [n.id, n]));

                const primaryLines = lines.filter(l => l.type === 'primary');
                const childrenMap = {};
                const dottedMap = {};

                lines.forEach(l => {
                    if (l.type === 'primary') {
                        childrenMap[l.parent] = childrenMap[l.parent] || [];
                        childrenMap[l.parent].push(l.child);
                    } else {
                        dottedMap[l.child] = dottedMap[l.child] || [];
                        dottedMap[l.child].push({ parent: l.parent, type: l.type });
                    }
                });

                // Roots: parents that are never listed as a child in PRIMARY lines.
                const childIds = new Set(primaryLines.map(l => l.child));
                let rootIds = [...new Set(primaryLines.map(l => l.parent))].filter(id => !childIds.has(id));

                // Fallbacks: if no primary hierarchy, show every node that appears in any line.
                if (!rootIds.length) {
                    const anyLineIds = new Set(lines.flatMap(l => [l.parent, l.child]));
                    rootIds = [...anyLineIds];
                }

                const renderNode = (id, visited = new Set()) => {
                    if (visited.has(id)) {
                        return `<li><div class="organogram-node">
                            <div class="organogram-name">${nodeMap[id]?.name ?? 'Loop detected'}</div>
                            <div class="organogram-level text-muted">Cycle detected</div>
                        </div></li>`;
                    }

                    const n = nodeMap[id];
                    if (!n) return '';

                    const nextVisited = new Set(visited);
                    nextVisited.add(id);

                    const children = childrenMap[id] || [];
                    const dotted = dottedMap[id] || [];

                    const dottedHtml = dotted.length
                        ? `<div class="organogram-dotted">(${
                            dotted.map(function(d) {
                                const parentName = nodeMap[d.parent] ? nodeMap[d.parent].name : 'N/A';
                                return `${d.type} to ${parentName}`;
                            }).join(', ')
                        })</div>`
                        : '';

                    const childrenHtml = children.length
                        ? `<ul class="organogram-children">${children.map(childId => renderNode(childId, nextVisited)).join('')}</ul>`
                        : '';

                    return `<li>
                        <div class="organogram-node">
                            <div class="organogram-name">${n.name}</div>
                            <div class="organogram-level">${n.level}</div>
                            ${dottedHtml}
                        </div>
                        ${childrenHtml}
                    </li>`;
                };

                const rootsHtml = rootIds.length
                    ? rootIds.map(id => renderNode(id)).join('')
                    : '<li><div class="text-muted">No reporting lines yet.</div></li>';

                orgContainer.innerHTML = `<ul class="organogram-root">${rootsHtml}</ul>`;
            }
        </script>
    </div>
@endsection
