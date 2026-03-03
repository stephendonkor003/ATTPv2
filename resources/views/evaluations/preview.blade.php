@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-start mb-3">
            <div>
                <h4 class="fw-bold mb-1">{{ $evaluation->name }} - Template Preview</h4>
                <p class="text-muted mb-0">Sections and criteria structure preview.</p>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('evals.cfg.template.pdf', $evaluation) }}" class="btn btn-outline-danger btn-sm">
                    <i class="feather-download me-1"></i> Download PDF
                </a>
                <a href="{{ route('evals.cfg.index') }}" class="btn btn-outline-secondary btn-sm">
                    Back
                </a>
            </div>
        </div>

        @include('evaluations.partials.template-preview', ['evaluation' => $evaluation])
    </div>
@endsection
