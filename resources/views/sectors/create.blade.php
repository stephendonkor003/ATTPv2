@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-dark">Create Sector</h4>
        </div>

        <div class="card mt-3 shadow-sm">
            <div class="card-body">

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <form action="{{ route('budget.sectors.store') }}" method="POST">
                    @csrf

                    @php
                        $currentUser = auth()->user();
                        $currentNodeId = optional($currentUser)->governance_node_id;
                        $canChooseGovernanceNode = (bool) ($currentUser?->isAdmin() || $currentUser?->isSuperAdmin());
                        $selectedGovernanceNodeId = old('governance_node_id', $canChooseGovernanceNode ? null : $currentNodeId);
                    @endphp

                    <div class="mb-3">
                        <label class="form-label">Sector Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea name="description" class="form-control"></textarea>
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

                    <button class="btn btn-primary">Save Sector</button>
                </form>
            </div>
        </div>

    </div>
@endsection
