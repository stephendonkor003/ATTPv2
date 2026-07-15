@extends('layouts.app')
@section('title', 'View Portfolio')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Portfolio Details</h4>
                    <p class="text-muted mb-0">Detailed overview of this portfolio and its programs.</p>
                </div>
                <a href="{{ route('sectors.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back
                </a>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-bold text-primary">{{ $sector->name }}</h5>
                    <p class="text-muted">{{ $sector->description ?? 'No description provided.' }}</p>
                    <hr>
                    <h6 class="fw-semibold mb-3">Programs under this Portfolio</h6>
                    @if ($sector->programs->count())
                        <ul class="list-group">
                            @foreach ($sector->programs as $program)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-grid-1x2 me-2"></i> {{ $program->name }}</span>
                                    <a href="{{ route('programs.show', $program->id) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        View Program
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted">No programs linked to this portfolio yet.</p>
                    @endif
                </div>
            </div>

        </div>
    </main>
@endsection
