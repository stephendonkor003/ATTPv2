@extends('layouts.app')

@section('title', $methodDefinition['label'].' Report - '.($procurement->reference_no ?: $procurement->title))

@section('content')
<main class="nxl-container evr-shell" aria-labelledby="procurementReportTitle">
    <header class="evr-hero">
        <div class="evr-hero__copy">
            <span class="evr-eyebrow">{{ $methodDefinition['label'] }} · {{ $methodDefinition['mode'] }}</span>
            <h1 id="procurementReportTitle">{{ $procurement->title ?: 'Untitled procurement' }}</h1>
            <p>A complete management report covering progress, applicant results, evaluator and section scores, panel consistency, and the evidence behind the next decision.</p>
            <div class="evr-hero__meta">
                <span><i class="feather-hash" aria-hidden="true"></i>{{ $procurement->reference_no ?: 'No reference number' }}</span>
                <span><i class="feather-activity" aria-hidden="true"></i>{{ Str::headline($procurement->status ?: 'Status not specified') }}</span>
                @if ($summary['latest_at'])<span><i class="feather-clock" aria-hidden="true"></i>Updated {{ $summary['latest_at']->format('d M Y, H:i') }}</span>@endif
            </div>
        </div>
        <div class="evr-hero__actions evr-no-print" aria-label="Report export actions">
            <a href="{{ route('reports.evaluations.method', $method) }}" class="evr-btn evr-btn--ghost">
                <i class="feather-arrow-left" aria-hidden="true"></i> Procurement list
            </a>
            <a href="{{ route('reports.evaluations.method.procurement.excel', [$method, $procurement]) }}" class="evr-btn evr-btn--light">
                <i class="feather-grid" aria-hidden="true"></i> Excel
            </a>
            <a href="{{ route('reports.evaluations.method.procurement.csv', [$method, $procurement]) }}" class="evr-btn evr-btn--ghost">
                <i class="feather-file-text" aria-hidden="true"></i> CSV
            </a>
            <a href="{{ route('reports.evaluations.method.procurement.pdf', [$method, $procurement]) }}" class="evr-btn evr-btn--ghost">
                <i class="feather-download" aria-hidden="true"></i> PDF
            </a>
            <button type="button" class="evr-btn evr-btn--ghost" onclick="window.print()">
                <i class="feather-printer" aria-hidden="true"></i> Print
            </button>
        </div>
    </header>

    @include('reports.evaluations.partials.management-report', ['management' => $management])
</main>
@endsection

@push('styles')
    @include('reports.evaluations.partials.report-suite-styles')
@endpush

