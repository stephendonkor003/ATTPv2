@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header mb-3">
            <h4 class="page-title">Create Evaluation</h4>
            <p class="text-muted">Evaluation configuration (admin)</p>
        </div>

        {{-- VALIDATION ERRORS --}}
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
                <form method="POST" action="{{ route('evals.cfg.store') }}">
                    @csrf

                    {{-- EVALUATION NAME --}}
                    <div class="mb-3">
                        <label class="form-label">Evaluation Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>

                    @include('evaluations.partials.portfolio-field')

                    {{-- DESCRIPTION --}}
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                    </div>

                    {{-- EVALUATION TYPE --}}
                    <div class="mb-3">
                        <label class="form-label">Evaluation Type</label>
                        <select name="type" class="form-select" required>
                            <option value="services" {{ old('type', 'services') === 'services' ? 'selected' : '' }}>
                                Services (Scored / Numeric)
                            </option>
                            <option value="goods" {{ old('type') === 'goods' ? 'selected' : '' }}>
                                Goods (Yes / No + Comments)
                            </option>
                        </select>

                        <small class="text-muted">
                            • Services: numeric scoring per criteria<br>
                            • Goods: compliance-based (YES / NO with comments)
                        </small>
                    </div>

                    <div class="text-end">
                        <button class="btn btn-primary">
                            Create Evaluation
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection
