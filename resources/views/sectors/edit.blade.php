@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold text-dark mb-1">Edit Sector</h4>
                <p class="text-muted mb-0">Update sector details within your governance node.</p>
            </div>
            <a href="{{ route('budget.sectors.index') }}" class="btn btn-outline-secondary">
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

        <div class="card mt-3 shadow-sm">
            <div class="card-body">
                <form action="{{ route('budget.sectors.update', $sector->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @php
                        $currentUser = auth()->user();
                        $canChooseGovernanceNode = (bool) ($currentUser?->isAdmin() || $currentUser?->isSuperAdmin());
                        $selectedGovernanceNodeId = old('governance_node_id', $sector->governance_node_id);
                    @endphp

                    <div class="mb-3">
                        <label class="form-label">Sector Name</label>
                        <input type="text" name="name" class="form-control"
                            value="{{ old('name', $sector->name) }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea name="description" class="form-control">{{ old('description', $sector->description) }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Governance Node</label>
                        <select name="governance_node_id" class="form-select" required @disabled(! $canChooseGovernanceNode)>
                            <option value="">-- Select Node --</option>
                            @foreach ($nodes as $node)
                                <option value="{{ $node->id }}"
                                    @selected($selectedGovernanceNodeId == $node->id)>
                                    {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                </option>
                            @endforeach
                        </select>
                        @unless($canChooseGovernanceNode)
                            <input type="hidden" name="governance_node_id" value="{{ $selectedGovernanceNodeId }}">
                        @endunless
                    </div>

                    <button class="btn btn-primary">Update Sector</button>
                </form>
            </div>
        </div>

    </div>
@endsection
