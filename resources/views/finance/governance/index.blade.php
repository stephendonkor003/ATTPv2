@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Governance Structure</h4>
                <p class="text-muted mb-0">
                    Configure organizational levels, reporting lines, and assignments with effective dates.
                </p>
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
                                    <input type="text" name="key" class="form-control" required placeholder="e.g. organ">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Name</label>
                                    <input type="text" name="name" class="form-control" required placeholder="e.g. Organ">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Sort Order</label>
                                    <input type="number" name="sort_order" class="form-control" min="0" step="1" value="{{ $levels->max('sort_order') + 1 }}">
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
                                                    <button class="btn btn-sm btn-outline-warning" type="button"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editLevelModal{{ $level->id }}">
                                                        <i class="feather-edit"></i>
                                                    </button>
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

                <!-- Edit Level Modals -->
                @foreach ($levels as $level)
                    @canany(['finance.governance_structure.edit', 'finance.governance_structure.manage'])
                        <div class="modal fade" id="editLevelModal{{ $level->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Level: {{ $level->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="{{ route('finance.governance.levels.update', $level) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Key</label>
                                                    <input type="text" name="key" class="form-control" value="{{ $level->key }}" required>
                                                </div>
                                                <div class="col-md-8">
                                                    <label class="form-label fw-semibold">Name</label>
                                                    <input type="text" name="name" class="form-control" value="{{ $level->name }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Sort Order</label>
                                                    <input type="number" name="sort_order" class="form-control" min="0" step="1" value="{{ $level->sort_order }}">
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label fw-semibold">Description</label>
                                                    <input type="text" name="description" class="form-control" value="{{ $level->description }}">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endcanany
                @endforeach
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
                                                    <button class="btn btn-sm btn-outline-warning" type="button"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editNodeModal{{ $node->id }}">
                                                        <i class="feather-edit"></i>
                                                    </button>
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

                <!-- Edit Node Modals -->
                @foreach ($nodes as $node)
                    @canany(['finance.governance_structure.edit', 'finance.governance_structure.manage'])
                        <div class="modal fade" id="editNodeModal{{ $node->id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Node: {{ $node->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="{{ route('finance.governance.nodes.update', $node) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Level</label>
                                                    <select name="level_id" class="form-select" required>
                                                        @foreach ($levels as $level)
                                                            <option value="{{ $level->id }}" @selected($node->level_id == $level->id)>
                                                                {{ $level->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Status</label>
                                                    <select name="status" class="form-select" required>
                                                        <option value="active" @selected($node->status === 'active')>Active</option>
                                                        <option value="inactive" @selected($node->status === 'inactive')>Inactive</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Name</label>
                                                    <input type="text" name="name" class="form-control" value="{{ $node->name }}" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Code</label>
                                                    <input type="text" name="code" class="form-control" value="{{ $node->code }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Effective Start</label>
                                                    <input type="date" name="effective_start" class="form-control"
                                                        value="{{ optional($node->effective_start)->format('Y-m-d') }}">
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label fw-semibold">Description</label>
                                                    <textarea name="description" class="form-control" rows="3">{{ $node->description }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endcanany
                @endforeach
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
                                                    <button class="btn btn-sm btn-outline-warning" type="button"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editLineModal{{ $line->id }}">
                                                        <i class="feather-edit"></i>
                                                    </button>
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

                <!-- Edit Line Modals -->
                @foreach ($lines as $line)
                    @canany(['finance.governance_structure.edit', 'finance.governance_structure.manage'])
                        <div class="modal fade" id="editLineModal{{ $line->id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Reporting Line</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="{{ route('finance.governance.lines.update', $line) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Child Node</label>
                                                    <select name="child_node_id" class="form-select" required>
                                                        @foreach ($nodes as $node)
                                                            <option value="{{ $node->id }}" @selected($line->child_node_id == $node->id)>
                                                                {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Parent Node</label>
                                                    <select name="parent_node_id" class="form-select" required>
                                                        @foreach ($nodes as $node)
                                                            <option value="{{ $node->id }}" @selected($line->parent_node_id == $node->id)>
                                                                {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Line Type</label>
                                                    <select name="line_type" class="form-select" required>
                                                        <option value="primary" @selected($line->line_type === 'primary')>Primary (Hierarchy)</option>
                                                        <option value="dotted" @selected($line->line_type === 'dotted')>Dotted (Matrix)</option>
                                                        <option value="advisory" @selected($line->line_type === 'advisory')>Advisory</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Effective Start</label>
                                                    <input type="date" name="effective_start" class="form-control"
                                                        value="{{ optional($line->effective_start)->format('Y-m-d') }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Effective End</label>
                                                    <input type="date" name="effective_end" class="form-control"
                                                        value="{{ optional($line->effective_end)->format('Y-m-d') }}">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endcanany
                @endforeach
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
                                                    <button class="btn btn-sm btn-outline-warning" type="button"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editAssignmentModal{{ $assignment->id }}">
                                                        <i class="feather-edit"></i>
                                                    </button>
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

                <!-- Edit Assignment Modals -->
                @foreach ($assignments as $assignment)
                    @canany(['finance.governance_structure.edit', 'finance.governance_structure.manage'])
                        <div class="modal fade" id="editAssignmentModal{{ $assignment->id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Assignment</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="{{ route('finance.governance.assignments.update', $assignment) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Node</label>
                                                    <select name="node_id" class="form-select" required>
                                                        @foreach ($nodes as $node)
                                                            <option value="{{ $node->id }}" @selected($assignment->node_id == $node->id)>
                                                                {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Employee</label>
                                                    <select name="user_id" class="form-select" required>
                                                        @foreach ($users as $user)
                                                            <option value="{{ $user->id }}" @selected($assignment->user_id == $user->id)>
                                                                {{ $user->name }} - {{ $user->email }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Role Title</label>
                                                    <input type="text" name="role_title" class="form-control" value="{{ $assignment->role_title }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check mt-4">
                                                        <input class="form-check-input" type="checkbox" value="1" name="is_primary"
                                                            id="editPrimary{{ $assignment->id }}" @checked($assignment->is_primary)>
                                                        <label class="form-check-label fw-semibold" for="editPrimary{{ $assignment->id }}">
                                                            Primary Assignment
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Effective Start</label>
                                                    <input type="date" name="effective_start" class="form-control"
                                                        value="{{ optional($assignment->effective_start)->format('Y-m-d') }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Effective End</label>
                                                    <input type="date" name="effective_end" class="form-control"
                                                        value="{{ optional($assignment->effective_end)->format('Y-m-d') }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check mt-4">
                                                        <input class="form-check-input" type="checkbox" value="1" name="notify_user"
                                                            id="editNotify{{ $assignment->id }}">
                                                        <label class="form-check-label fw-semibold" for="editNotify{{ $assignment->id }}">
                                                            Email notification
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endcanany
                @endforeach
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
        </script>
    </div>
@endsection
