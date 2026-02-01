@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Edit Approval Process</h4>
                <p class="text-muted mb-0">
                    Update step approval process information
                </p>
            </div>
            <a href="{{ route('procurement.settings.step-approvals.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        {{-- ================= VALIDATION ERRORS ================= --}}
        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                <strong>Please fix the following issues:</strong>
                <ul class="mb-0 mt-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ================= FORM CARD ================= --}}
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <form method="POST" action="{{ route('procurement.settings.step-approvals.update', $stepApproval) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" class="form-control"
                            value="{{ old('name', $stepApproval->name) }}" required>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Step Stage</label>
                            <select name="step_stage_id" class="form-select">
                                <option value="">-- Select Step Stage --</option>
                                @foreach($stepStages as $stepStage)
                                    <option value="{{ $stepStage->id }}"
                                        {{ old('step_stage_id', $stepApproval->step_stage_id) == $stepStage->id ? 'selected' : '' }}>
                                        {{ $stepStage->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">The step stage this approval belongs to</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Governance Node</label>
                            <select name="governance_node_id" class="form-select">
                                <option value="">-- Select Governance Node --</option>
                                @foreach($governanceNodes as $node)
                                    <option value="{{ $node->id }}"
                                        {{ old('governance_node_id', $stepApproval->governance_node_id) == $node->id ? 'selected' : '' }}>
                                        {{ $node->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">The governance unit responsible for approval</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" rows="3" class="form-control"
                            placeholder="Brief description of this approval process">{{ old('description', $stepApproval->description) }}</textarea>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Approval Order</label>
                            <input type="number" name="approval_order" class="form-control"
                                value="{{ old('approval_order', $stepApproval->approval_order) }}" min="0">
                            <small class="text-muted">Order in which approvals are processed</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold d-block">&nbsp;</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_required" class="form-check-input" id="is_required"
                                    {{ old('is_required', $stepApproval->is_required) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_required">Required Approval</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold d-block">&nbsp;</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                    {{ old('is_active', $stepApproval->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="{{ route('procurement.settings.step-approvals.index') }}" class="btn btn-light">Cancel</a>
                        <button class="btn btn-primary">
                            <i class="feather-save me-1"></i> Update Approval Process
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
