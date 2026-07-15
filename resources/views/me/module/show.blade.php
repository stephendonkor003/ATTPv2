@extends('layouts.app')

@section('title', $section['title'])

@section('content')
    <div class="container-fluid">
        <div class="page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <div class="text-muted small fw-semibold text-uppercase mb-1">Monitoring & Evaluation</div>
                <h4 class="mb-1">{{ $section['title'] }}</h4>
                @if (!empty($section['subtitle']))
                    <p class="text-muted mb-0">{{ $section['subtitle'] }}</p>
                @endif
            </div>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">
                Rebuild Scaffold
            </span>
        </div>

        <div class="row g-3 mb-4">
            @foreach ($sections as $sectionKey => $item)
                @php
                    $routes = [
                        'results-framework' => 'budget.me.rebuild.results-framework',
                        'data-entry-performance-tracking' => 'budget.me.rebuild.data-entry',
                        'data-quality-approval-workflow' => 'budget.me.rebuild.data-quality',
                        'reporting-dashboard' => 'budget.me.rebuild.reporting-dashboard',
                        'management-dashboard' => 'budget.me.rebuild.management-dashboard',
                        'knowledge-evidence-repository' => 'budget.me.rebuild.knowledge-repository',
                        'data-governance-framework' => 'budget.me.rebuild.data-governance',
                    ];
                    $isActive = $sectionKey === $key;
                @endphp
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <a href="{{ route($routes[$sectionKey]) }}"
                        class="text-decoration-none d-block h-100">
                        <div class="card h-100 shadow-sm {{ $isActive ? 'border-primary' : 'border-0' }}">
                            <div class="card-body d-flex gap-3 align-items-start">
                                <span class="d-inline-flex align-items-center justify-content-center rounded bg-light text-primary"
                                    style="width: 38px; height: 38px;">
                                    <i class="{{ $item['icon'] }}"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold text-dark">{{ $item['title'] }}</div>
                                    @if (!empty($item['subtitle']))
                                        <div class="text-muted small mt-1">{{ $item['subtitle'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary"
                        style="width: 44px; height: 44px;">
                        <i class="{{ $section['icon'] }}"></i>
                    </span>
                    <div>
                        <h5 class="mb-1">{{ $section['title'] }}</h5>
                        <div class="text-muted">Ready for rebuild.</div>
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    This section has been reset and prepared for the new M&E module build.
                </div>
            </div>
        </div>
    </div>
@endsection
