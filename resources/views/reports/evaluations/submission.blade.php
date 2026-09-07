@extends('layouts.app')

@section('title', 'Evaluation Submission Report')

@section('content')
@php
    $applicantName = $submission->applicant?->display_name ?? 'Applicant';
    $submissionCode = $submission->applicant?->procurement_submission_code ?? 'Not recorded';
@endphp
<main class="nxl-container evr-shell" aria-labelledby="evaluationSubmissionReportTitle">
    <header class="evr-hero">
        <div class="evr-hero__copy">
            <span class="evr-eyebrow">Individual evaluation submission report</span>
            <h1 id="evaluationSubmissionReportTitle">{{ $applicantName }}</h1>
            <p>{{ $submission->evaluation?->name ?? 'Evaluation' }} ? {{ $submission->procurement?->title ?? 'Procurement not recorded' }}</p>
            <div class="evr-hero__meta">
                <span><i class="feather-hash" aria-hidden="true"></i>{{ $submissionCode }}</span>
                <span><i class="feather-user" aria-hidden="true"></i>{{ $submission->evaluator?->name ?? 'Evaluator not recorded' }}</span>
                <span><i class="feather-clock" aria-hidden="true"></i>{{ $submission->submitted_at?->format('d M Y, H:i') ?? 'Submission time not recorded' }}</span>
            </div>
        </div>
        <div class="evr-hero__actions evr-no-print" aria-label="Report actions">
            <a href="{{ route('reports.evaluations.index') }}" class="evr-btn evr-btn--ghost"><i class="feather-arrow-left" aria-hidden="true"></i> Reports</a>
            <a href="{{ route('reports.evaluations.submission.pdf', $submission) }}" class="evr-btn evr-btn--light"><i class="feather-download" aria-hidden="true"></i> Download PDF</a>
            <a href="{{ route('reports.evaluations.submission.anonymised-pdf', $submission) }}" class="evr-btn evr-btn--ghost"><i class="feather-shield" aria-hidden="true"></i> Anonymised PDF</a>
            <button type="button" class="evr-btn evr-btn--ghost" onclick="window.print()"><i class="feather-printer" aria-hidden="true"></i> Print</button>
        </div>
    </header>
    @include('reports.evaluations.partials.management-report', ['management' => $management])
</main>
@endsection

@push('styles')
    @include('reports.evaluations.partials.report-suite-styles')
@endpush
