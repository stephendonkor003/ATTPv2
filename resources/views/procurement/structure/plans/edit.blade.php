@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">Edit Procurement Plan</h4>
                <p class="text-muted mb-0">
                    Update procurement plan: <strong>{{ $programPlan->name }}</strong>
                </p>
            </div>

            <a href="{{ route('procurement.structure.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back to Structure
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <div class="fw-semibold mb-1">The procurement plan could not be updated.</div>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('procurement.structure.update', $programPlan) }}" method="POST" class="row g-3">
                    @csrf
                    @method('PUT')

                    @if ($canChoosePortfolio)
                        <div class="col-md-6">
                            <label class="form-label" for="governance_node_id">Portfolio <span
                                    class="text-danger">*</span></label>
                            <select name="governance_node_id" id="governance_node_id"
                                class="form-control @error('governance_node_id') is-invalid @enderror" required>
                                <option value="">Select portfolio</option>
                                @foreach ($governanceNodes as $node)
                                    <option value="{{ $node->id }}"
                                        {{ old('governance_node_id', $programPlan->governance_node_id) == $node->id ? 'selected' : '' }}>
                                        {{ $node->name }} @if ($node->code)
                                            ({{ $node->code }})
                                        @endif
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
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $programPlan->name) }}" placeholder="e.g. My 2063 Procurement Plan"
                            required>
                        @error('name')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="start_date">Start Date</label>
                        <input type="date" name="start_date" id="start_date"
                            class="form-control @error('start_date') is-invalid @enderror"
                            value="{{ old('start_date', $programPlan->start_date?->format('Y-m-d')) }}">
                        @error('start_date')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="end_date">End Date</label>
                        <input type="date" name="end_date" id="end_date"
                            class="form-control @error('end_date') is-invalid @enderror"
                            value="{{ old('end_date', $programPlan->end_date?->format('Y-m-d')) }}">
                        @error('end_date')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-md-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror"
                            rows="2">{{ old('description', $programPlan->description) }}</textarea>
                        @error('description')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="is_active">Status <span class="text-danger">*</span></label>
                        <select name="is_active" id="is_active"
                            class="form-select @error('is_active') is-invalid @enderror" required>
                            <option value="1" {{ old('is_active', $programPlan->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>
                                Active
                            </option>
                            <option value="0" {{ old('is_active', $programPlan->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>
                                Archived
                            </option>
                        </select>
                        <small class="text-muted">Archived plans keep their existing items but cannot receive new ones.</small>
                        @error('is_active')
                            <small class="text-danger d-block">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-12 text-muted small">
                        Created by: <span class="fw-semibold">{{ $programPlan->creator->name ?? '—' }}</span>
                        @if ($programPlan->created_at)
                            | Created: {{ $programPlan->created_at->format('M d, Y') }}
                        @endif
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary btn-sm" type="submit">
                            <i class="feather-save me-1"></i> Update Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
