@extends('layouts.app')

@section('title', 'Consolidated Evaluation Report')

@section('content')
<main class="nxl-container evr-shell" aria-labelledby="consolidatedReportTitle">
    <header class="evr-hero">
        <div class="evr-hero__copy">
            <span class="evr-eyebrow">Consolidated evaluation management report</span>
            <h1 id="consolidatedReportTitle">Evaluation portfolio overview</h1>
            <p>Review evaluation progress, applicant results, evaluator detail, panel consistency, and audit evidence across the procurements in your reporting scope.</p>
            <div class="evr-hero__meta"><span><i class="feather-folder" aria-hidden="true"></i>{{ number_format((int) ($summary['procurements'] ?? 0)) }} procurements</span><span><i class="feather-layers" aria-hidden="true"></i>Evaluation methods and phases remain separate</span></div>
        </div>
        <div class="evr-hero__actions evr-no-print" aria-label="Report actions">
            <a href="{{ route('reports.evaluations.index') }}" class="evr-btn evr-btn--ghost"><i class="feather-arrow-left" aria-hidden="true"></i> Back to reports</a>
            <a href="{{ route('reports.evaluations.consolidated.pdf') }}" class="evr-btn evr-btn--light"><i class="feather-download" aria-hidden="true"></i> Download PDF</a>
            <button type="button" class="evr-btn evr-btn--ghost" onclick="window.print()"><i class="feather-printer" aria-hidden="true"></i> Print</button>
        </div>
    </header>
    @include('reports.evaluations.partials.management-report', ['management' => $management])
</main>
@endsection

@push('styles')
    @include('reports.evaluations.partials.report-suite-styles')
@endpush
