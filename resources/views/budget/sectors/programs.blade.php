@extends('layouts.app')
@section('title', 'Portfolio Programs')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Programs under {{ $sector->name }}</h4>
                    <p class="text-muted mb-0">All programs and projects linked to this portfolio.</p>
                </div>
                <a href="{{ route('sectors.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back
                </a>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    @if ($sector->programs->count())
                        @foreach ($sector->programs as $program)
                            <div class="border-bottom mb-3 pb-3">
                                <h5 class="fw-bold text-primary mb-1">{{ $program->name }}</h5>
                                <p class="text-muted mb-2">{{ $program->description ?? 'No description provided.' }}</p>
                                <p><strong>Projects:</strong> {{ $program->projects->count() }}</p>
                                <a href="{{ route('programs.show', $program->id) }}" class="btn btn-sm btn-outline-primary">
                                    View Details
                                </a>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted mb-0">No programs found for this portfolio.</p>
                    @endif
                </div>
            </div>

        </div>
    </main>
@endsection
