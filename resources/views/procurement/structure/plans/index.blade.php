@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">My Procurement Plan</h4>
                <p class="text-muted mb-0">Create portfolio-owned procurement plan sheets and track their duration before
                    attaching procurements.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <div class="fw-semibold mb-1">The procurement plan could not be saved.</div>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($governanceNodes->isEmpty())
            <div class="alert alert-warning">
                <i class="feather-alert-triangle me-2"></i>
                No active portfolio is available in your governance scope. A portfolio assignment is required before
                a procurement plan can be created.
            </div>
        @endif

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <form action="{{ route('procurement.structure.store') }}" method="POST" class="row g-3">
                    @csrf
                    <input type="hidden" name="is_active" value="1">
                    @if ($canChoosePortfolio)
                        <div class="col-md-6">
                            <label class="form-label" for="governance_node_id">Portfolio <span class="text-danger">*</span></label>
                            <select name="governance_node_id" id="governance_node_id"
                                class="form-control @error('governance_node_id') is-invalid @enderror" required>
                                <option value="">Select portfolio</option>
                                @foreach ($governanceNodes as $node)
                                    <option value="{{ $node->id }}"
                                        {{ old('governance_node_id') == $node->id ? 'selected' : '' }}>
                                        {{ $node->name }} @if($node->code) ({{ $node->code }}) @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('governance_node_id')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                    @else
                        <div class="col-md-6">
                            <label class="form-label">Portfolio</label>
                            <input type="text" class="form-control" value="{{ $currentGovernanceNodeName }}" readonly>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label" for="name">Plan Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name"
                            class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}"
                            placeholder="e.g. My 2063 Procurement Plan" required>
                        @error('name')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="start_date">Start Date</label>
                        <input type="date" name="start_date" id="start_date"
                            class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date') }}">
                        @error('start_date')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="end_date">End Date</label>
                        <input type="date" name="end_date" id="end_date"
                            class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}">
                        @error('end_date')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="description">Description</label>
                        <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror"
                            rows="2">{{ old('description') }}</textarea>
                        @error('description')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-12 text-muted small">
                        Created by: <span class="fw-semibold">{{ auth()->user()->name }}</span>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary btn-sm" type="submit"
                            @disabled($governanceNodes->isEmpty())>
                            <i class="feather-save me-1"></i> Save Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Existing Plans</h5>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Plan Name</th>
                                <th>Portfolio</th>
                                <th>Duration</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Status</th>
                                <th class="text-center">Procurements</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($plans as $plan)
                                <tr>
                                    <td class="fw-semibold">{{ $plan->name }}</td>
                                    <td>{{ $plan->governanceNode?->name ?? '-' }}</td>
                                    <td>
                                        @if ($plan->duration_days !== null)
                                            {{ $plan->duration_days }} days
                                        @else
                                            <span class="text-muted">TBD</span>
                                        @endif
                                    </td>
                                    <td>{{ $plan->creator->name ?? '—' }}</td>
                                    <td>{{ $plan->created_at->format('M d, Y') }}</td>
                                    <td>
                                        <span class="badge {{ $plan->is_active ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $plan->is_active ? 'Active' : 'Archived' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info text-dark">{{ $plan->procurements_count }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('procurement.structure.edit', $plan) }}"
                                            class="btn btn-sm btn-outline-warning me-1">
                                            <i class="feather-edit-2 me-1"></i> Edit
                                        </a>
                                        @if ($plan->is_active)
                                            <a href="{{ route('procurement.plans.create', ['program_plan_id' => $plan->id]) }}"
                                                class="btn btn-sm btn-outline-success me-1">
                                                <i class="feather-plus me-1"></i> Add Item
                                            </a>
                                        @endif
                                        <a href="{{ route('procurement.plans.sheet', ['program_plan_id' => $plan->id]) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="feather-eye me-1"></i> View Sheet
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-3">
                                        No program plans yet. Create one to start populating procurements.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
