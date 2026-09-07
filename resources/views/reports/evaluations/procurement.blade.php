@extends('layouts.app')

@section('title', 'Procurement Evaluation Report')

@section('content')
<main class="nxl-container evr-shell" aria-labelledby="procurementReportTitle">
    <header class="evr-hero">
        <div class="evr-hero__copy">
            <span class="evr-eyebrow">Procurement evaluation management report</span>
            <h1 id="procurementReportTitle">{{ $procurement->title ?: 'Untitled procurement' }}</h1>
            <p>Review applicant results, evaluator and section scores, panel consistency, and the evidence supporting the next decision.</p>
            <div class="evr-hero__meta"><span><i class="feather-hash" aria-hidden="true"></i>{{ $procurement->reference_no ?: 'No reference number' }}</span></div>
        </div>
        <div class="evr-hero__actions evr-no-print" aria-label="Report actions">
            <a href="{{ route('reports.evaluations.index') }}" class="evr-btn evr-btn--ghost"><i class="feather-arrow-left" aria-hidden="true"></i> Back to reports</a>
            <a href="{{ route('reports.evaluations.procurement.pdf', $procurement) }}" class="evr-btn evr-btn--light"><i class="feather-download" aria-hidden="true"></i> Download PDF</a>
            <button type="button" class="evr-btn evr-btn--ghost" onclick="window.print()"><i class="feather-printer" aria-hidden="true"></i> Print</button>
        </div>
    </header>
    @include('reports.evaluations.partials.management-report', ['management' => $management])
</main>
@endsection

@push('styles')
    @include('reports.evaluations.partials.report-suite-styles')
@endpush
