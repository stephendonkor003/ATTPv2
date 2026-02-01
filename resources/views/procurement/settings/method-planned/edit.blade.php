@extends('layouts.app')

@section('content')
    @php
        $existingMilestones = $methodPlanned->milestones->map(function ($milestone) {
            return [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'description' => $milestone->description,
                'target_days' => $milestone->target_days,
                'sort_order' => $milestone->sort_order,
                'is_active' => $milestone->is_active,
            ];
        })->toArray();

        $milestoneRows = old('milestones', $existingMilestones ?: [
            [
                'title' => '',
                'description' => '',
                'target_days' => '',
                'sort_order' => '',
                'is_active' => true,
            ],
        ]);
    @endphp

    <div class="nxl-container">

        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Edit Method Planned</h4>
                <p class="text-muted mb-0">Update the method details and milestones.</p>
            </div>
            <a href="{{ route('procurement.settings.method-planned.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                <strong>Fix the highlighted issues below.</strong>
                <ul class="mb-0 mt-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <form method="POST" action="{{ route('procurement.settings.method-planned.update', $methodPlanned) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Method Name <span class="text-danger">*</span></label>
                            <input type="text" name="method_name" class="form-control"
                                value="{{ old('method_name', $methodPlanned->method_name) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Active</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                    {{ old('is_active', $methodPlanned->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Keep method active</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Method Description</label>
                            <textarea name="description" rows="3" class="form-control" placeholder="Optional description">{{ old('description', $methodPlanned->description) }}</textarea>
                        </div>
                    </div>

                    @include('procurement.settings.method-planned._milestones-form', ['milestoneRows' => $milestoneRows])

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="{{ route('procurement.settings.method-planned.index') }}" class="btn btn-light">Cancel</a>
                        <button class="btn btn-primary">
                            <i class="feather-save me-1"></i> Update Method
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
