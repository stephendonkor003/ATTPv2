@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header mb-3 d-flex justify-content-between align-items-start">
            <div>
                <h4 class="page-title">Edit Evaluation</h4>
                <p class="text-muted mb-0">Update the draft evaluation template details.</p>
            </div>
            <a href="{{ route('evals.cfg.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('evals.cfg.update', $evaluation) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Evaluation Name</label>
                        <input type="text" name="name" class="form-control"
                            value="{{ old('name', $evaluation->name) }}" required>
                    </div>

                    @include('evaluations.partials.portfolio-field')

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description', $evaluation->description) }}</textarea>
                    </div>

                    <div class="text-end">
                        <button class="btn btn-primary">
                            Update Evaluation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
