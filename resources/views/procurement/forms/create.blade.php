@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-dark">Create Procurement Form</h4>

            <a href="{{ route('forms.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        {{-- ================= FORM CARD ================= --}}
        <div class="card mt-3 shadow-sm">
            <div class="card-body">

                {{-- ERRORS --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- FORM --}}
                <form method="POST" action="{{ route('forms.store') }}">
                    @csrf

                    <div class="row">

                        {{-- PROCUREMENT CATEGORY --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">
                                Procurement Category
                            </label>
                            <select name="resource_id" class="form-control" required>
                                <option value="">-- Select Category --</option>
                                @foreach ($resources as $resource)
                                    <option value="{{ $resource->id }}">
                                        {{ $resource->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- FORM STAGE --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">
                                Form Stage
                            </label>
                            <select name="applies_to" class="form-control" required>
                                <option value="">-- Select Stage --</option>
                                <option value="submission">Bid Submission</option>
                                <option value="prescreening">Prescreening</option>
                                <option value="technical">Technical Evaluation</option>
                                <option value="financial">Financial Evaluation</option>
                            </select>
                            <small class="text-muted">
                                Default fields are added automatically:
                                <strong>Name</strong> and <strong>Email</strong>.
                            </small>
                        </div>

                        {{-- FORM NAME --}}
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-semibold">
                                Form Name
                            </label>
                            <input type="text" name="name" class="form-control"
                                placeholder="e.g. Technical Evaluation Form" required>
                        </div>

                        {{-- STATUS --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">
                                Status
                            </label>
                            <select name="is_active" class="form-control">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                    </div>

                    {{-- ACTIONS --}}
                    <div class="d-flex justify-content-end mt-3">
                        <button class="btn btn-primary">
                            <i class="feather-save me-1"></i> Save Form
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>
@endsection
