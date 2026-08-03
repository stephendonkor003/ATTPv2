@extends('layouts.app')

@section('title', 'Performance Report · '.$report->periodLabel())
@section('lean_admin_scripts', '1')

@push('styles')
    <style>
        .me-performance-report {
            --report-green: #0b5c45;
            --report-green-dark: #073f30;
            --report-green-soft: #edf8f3;
            --report-ink: #173c31;
            --report-muted: #687a73;
            --report-border: #dce8e3;
            max-width: 1380px;
            margin: 0 auto;
        }

        .me-performance-report .report-header {
            overflow: hidden;
            border-radius: 1rem;
            color: #fff;
            background: linear-gradient(120deg, var(--report-green-dark), #0d7456);
            box-shadow: 0 14px 34px rgba(7, 63, 48, .16);
        }

        .me-performance-report .report-header-main {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.4rem 1.5rem;
        }

        .me-performance-report .report-header p {
            margin: .35rem 0 0;
            color: rgba(255, 255, 255, .76);
        }

        .me-performance-report .report-status {
            display: inline-flex;
            align-items: center;
            padding: .38rem .7rem;
            border: 1px solid rgba(255, 255, 255, .26);
            border-radius: 999px;
            color: #fff;
            background: rgba(255, 255, 255, .12);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .me-performance-report .report-meta {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            border-top: 1px solid rgba(255, 255, 255, .15);
            background: rgba(0, 0, 0, .08);
        }

        .me-performance-report .meta-item {
            min-width: 0;
            padding: .85rem 1rem;
            border-right: 1px solid rgba(255, 255, 255, .12);
        }

        .me-performance-report .meta-item:last-child {
            border-right: 0;
        }

        .me-performance-report .meta-item small {
            display: block;
            margin-bottom: .18rem;
            color: rgba(255, 255, 255, .6);
            font-size: .63rem;
            font-weight: 800;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .me-performance-report .meta-item strong {
            display: block;
            overflow: hidden;
            color: #fff;
            font-size: .8rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .me-performance-report .report-section {
            margin-top: 1rem;
            border: 1px solid var(--report-border);
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 8px 22px rgba(25, 64, 52, .045);
        }

        .me-performance-report .section-head {
            display: flex;
            align-items: flex-start;
            gap: .8rem;
            padding: 1.05rem 1.15rem;
            border-bottom: 1px solid var(--report-border);
            background: #fbfdfc;
            border-radius: 1rem 1rem 0 0;
        }

        .me-performance-report .section-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: .65rem;
            color: var(--report-green);
            background: var(--report-green-soft);
        }

        .me-performance-report .section-head h5 {
            margin: 0;
            color: var(--report-ink);
            font-size: .94rem;
            font-weight: 800;
        }

        .me-performance-report .section-head p {
            margin: .2rem 0 0;
            color: var(--report-muted);
            font-size: .75rem;
        }

        .me-performance-report .section-body {
            padding: 1.1rem 1.15rem;
        }

        .me-performance-report .indicator-card {
            height: 100%;
            padding: 1rem;
            border: 1px solid var(--report-border);
            border-radius: .85rem;
            background: #fcfefd;
        }

        .me-performance-report .indicator-code {
            display: inline-block;
            margin-bottom: .35rem;
            color: var(--report-green);
            font-size: .7rem;
            font-weight: 850;
            letter-spacing: .035em;
        }

        .me-performance-report .indicator-card h6 {
            min-height: 2.7em;
            color: var(--report-ink);
            line-height: 1.4;
        }

        .me-performance-report .indicator-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            margin-bottom: .8rem;
        }

        .me-performance-report .indicator-meta span {
            padding: .25rem .48rem;
            border-radius: 999px;
            color: #456056;
            background: #eef4f1;
            font-size: .66rem;
            font-weight: 750;
        }

        .me-performance-report .result-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .55rem;
        }

        .me-performance-report .result-cell {
            min-width: 0;
            padding: .65rem;
            border: 1px solid #e2ebe7;
            border-radius: .65rem;
            background: #fff;
        }

        .me-performance-report .result-cell small {
            display: block;
            color: var(--report-muted);
            font-size: .62rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .me-performance-report .result-value {
            margin-top: .18rem;
            color: var(--report-ink);
            font-size: .94rem;
            font-weight: 800;
        }

        .me-performance-report .result-cell .form-control {
            min-height: 36px;
            margin-top: .35rem;
            padding: .35rem .5rem;
            border-radius: .5rem;
        }

        .me-performance-report .mov-link {
            display: flex;
            align-items: center;
            gap: .45rem;
            margin-top: .75rem;
            padding-top: .7rem;
            border-top: 1px dashed #d7e4df;
            color: var(--report-green);
            font-size: .72rem;
            font-weight: 700;
            text-decoration: none;
        }

        .me-performance-report .form-label {
            color: var(--report-ink);
            font-size: .78rem;
            font-weight: 750;
        }

        .me-performance-report textarea.form-control,
        .me-performance-report select.form-select {
            border-color: #cfddd7;
            border-radius: .68rem;
        }

        .me-performance-report textarea.form-control:focus,
        .me-performance-report select.form-select:focus,
        .me-performance-report input.form-control:focus {
            border-color: #2f8b6d;
            box-shadow: 0 0 0 .2rem rgba(47, 139, 109, .12);
        }

        .me-performance-report .document-row,
        .me-performance-report .existing-document {
            display: grid;
            grid-template-columns: minmax(180px, .8fr) minmax(240px, 1.2fr) auto;
            gap: .65rem;
            align-items: center;
            padding: .7rem;
            border: 1px solid var(--report-border);
            border-radius: .7rem;
            background: #fbfdfc;
        }

        .me-performance-report .existing-document {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .me-performance-report .document-file {
            min-width: 0;
        }

        .me-performance-report .document-file strong,
        .me-performance-report .document-file small {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .me-performance-report .report-actions {
            position: sticky;
            bottom: .75rem;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-top: 1rem;
            padding: .8rem;
            border: 1px solid var(--report-border);
            border-radius: .85rem;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 12px 30px rgba(18, 55, 43, .14);
            backdrop-filter: blur(8px);
        }

        .me-performance-report .section-head > div {
            min-width: 0;
        }

        .me-performance-report .section-state {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            flex: 0 0 auto;
            margin-left: auto;
            padding: .34rem .55rem;
            border: 1px solid;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 850;
            white-space: nowrap;
        }

        .me-performance-report .section-state small {
            padding-left: .3rem;
            border-left: 1px solid currentColor;
            font-size: .6rem;
            opacity: .75;
        }

        .me-performance-report .section-state--complete {
            border-color: #a7d7bf;
            color: #087443;
            background: #eaf8f0;
        }

        .me-performance-report .section-state--in-progress {
            border-color: #f1d28c;
            color: #8a5a00;
            background: #fff8e7;
        }

        .me-performance-report .section-state--not-started {
            border-color: #edb8b5;
            color: #a3312c;
            background: #fff0ef;
        }

        .me-performance-report .report-completion {
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid var(--report-border);
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 8px 22px rgba(25, 64, 52, .045);
        }

        .me-performance-report .completion-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .me-performance-report .completion-eyebrow,
        .me-performance-report .stage-action-kicker {
            color: var(--report-green);
            font-size: .63rem;
            font-weight: 850;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .me-performance-report .completion-heading h5,
        .me-performance-report .stage-action-copy h5 {
            margin: .18rem 0;
            color: var(--report-ink);
            font-size: .94rem;
            font-weight: 850;
        }

        .me-performance-report .completion-heading p,
        .me-performance-report .stage-action-copy p {
            margin: 0;
            color: var(--report-muted);
            font-size: .73rem;
        }

        .me-performance-report .completion-score {
            display: grid;
            min-width: 74px;
            padding: .55rem;
            border-radius: .75rem;
            color: #8a5a00;
            background: #fff8e7;
            text-align: center;
        }

        .me-performance-report .completion-score.is-ready {
            color: #087443;
            background: #eaf8f0;
        }

        .me-performance-report .completion-score strong,
        .me-performance-report .completion-score span {
            line-height: 1.1;
        }

        .me-performance-report .completion-score span {
            margin-top: .2rem;
            font-size: .58rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .me-performance-report .completion-progress {
            height: 7px;
            margin-top: .8rem;
            overflow: hidden;
            border-radius: 999px;
            background: #f1e0df;
        }

        .me-performance-report .completion-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #d8941d, #15935d);
        }

        .me-performance-report .completion-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .5rem;
            margin-top: .85rem;
        }

        .me-performance-report .completion-item {
            display: flex;
            align-items: flex-start;
            gap: .5rem;
            min-width: 0;
            padding: .55rem;
            border: 1px solid;
            border-radius: .65rem;
            color: inherit;
            text-decoration: none;
        }

        .me-performance-report .completion-item > span {
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: .45rem;
            color: #fff;
            background: currentColor;
            font-size: .65rem;
            font-weight: 850;
        }

        .me-performance-report .completion-item > span::first-line {
            color: #fff;
        }

        .me-performance-report .completion-item div {
            min-width: 0;
        }

        .me-performance-report .completion-item strong,
        .me-performance-report .completion-item small {
            display: block;
        }

        .me-performance-report .completion-item strong {
            overflow: hidden;
            font-size: .67rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .me-performance-report .completion-item small {
            margin-top: .12rem;
            overflow: hidden;
            font-size: .6rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .me-performance-report .completion-item--complete {
            border-color: #b9dfca;
            color: #087443;
            background: #f2fbf6;
        }

        .me-performance-report .completion-item--in-progress {
            border-color: #f1d28c;
            color: #8a5a00;
            background: #fffaf0;
        }

        .me-performance-report .completion-item--not-started {
            border-color: #edc3c0;
            color: #a3312c;
            background: #fff5f4;
        }

        .me-performance-report .report-stage-actions {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, .9fr);
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid #bad5e7;
            border-left: 5px solid #1676b8;
            border-radius: 1rem;
            background: linear-gradient(135deg, #f8fcff, #edf7fd);
        }

        .me-performance-report .report-stage-actions--submitted {
            border-color: #efd294;
            border-left-color: #d8941d;
            background: linear-gradient(135deg, #fffdf8, #fff7e5);
        }

        .me-performance-report .report-stage-actions--reviewed {
            border-color: #abd7bf;
            border-left-color: #15935d;
            background: linear-gradient(135deg, #f7fdf9, #eaf8f0);
        }

        .me-performance-report .report-stage-actions--archived {
            border-color: #cbd2d8;
            border-left-color: #3e4a53;
            background: linear-gradient(135deg, #fbfcfc, #eef1f3);
        }

        .me-performance-report .stage-review-form {
            display: grid;
            gap: .5rem;
        }

        .me-performance-report .stage-action-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .5rem;
        }

        .me-performance-report .lifecycle-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            min-height: 42px;
            padding-right: 1rem;
            padding-left: 1rem;
            font-weight: 800;
        }

        .me-performance-report .lifecycle-action:disabled {
            cursor: not-allowed;
            opacity: .48;
        }

        .me-performance-report .stage-locked {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            justify-self: end;
            padding: .65rem .8rem;
            border: 1px solid #ced7d3;
            border-radius: .65rem;
            color: #52635c;
            background: rgba(255, 255, 255, .7);
            font-size: .73rem;
            font-weight: 800;
        }

        .me-performance-report .review-panel {
            border-color: #c9dfd6;
            background: linear-gradient(135deg, #f7fcf9, #edf8f3);
        }

        .me-performance-report .readonly-note {
            padding: .8rem 1rem;
            border: 1px solid #c9ded5;
            border-radius: .75rem;
            color: #315a49;
            background: #f2faf6;
            font-size: .78rem;
        }

        @media (max-width: 991.98px) {
            .me-performance-report .report-meta {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .me-performance-report .completion-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .me-performance-report .meta-item {
                border-bottom: 1px solid rgba(255, 255, 255, .12);
            }
        }

        @media (max-width: 575.98px) {
            .me-performance-report .report-header-main,
            .me-performance-report .report-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .me-performance-report .section-head {
                flex-wrap: wrap;
            }

            .me-performance-report .section-state {
                width: 100%;
                margin-left: 3.05rem;
            }

            .me-performance-report .completion-heading {
                align-items: stretch;
                flex-direction: column;
            }

            .me-performance-report .completion-grid,
            .me-performance-report .report-stage-actions {
                grid-template-columns: 1fr;
            }

            .me-performance-report .stage-locked {
                justify-self: stretch;
            }

            .me-performance-report .report-meta,
            .me-performance-report .result-grid {
                grid-template-columns: 1fr;
            }

            .me-performance-report .document-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $editable = $canManage && $report->isEditable();
        $statusLabel = $report->lifecycleLabel();
    @endphp

    <div class="nxl-container">
        <div class="me-performance-report">
            <header class="report-header">
                <div class="report-header-main">
                    <div>
                        <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'reports']) }}" class="text-white-50 text-decoration-none small">
                            <i class="feather-arrow-left me-1" aria-hidden="true"></i>Performance reports
                        </a>
                        <h3 class="fw-bold mt-3 mb-0">{{ $report->form?->title }}</h3>
                        <p>{{ $report->periodLabel() }} performance tracking and report review</p>
                    </div>
                    <span class="report-status"><i class="feather-circle me-1" aria-hidden="true"></i>{{ $statusLabel }}</span>
                </div>
                <div class="report-meta">
                    <div class="meta-item"><small>Reporting form</small><strong>{{ $report->form?->code }}</strong></div>
                    <div class="meta-item"><small>Portfolio</small><strong title="{{ $report->portfolio?->name }}">{{ $report->portfolio?->name }}</strong></div>
                    <div class="meta-item"><small>Project Component</small><strong title="{{ $report->projectComponent?->name }}">{{ $report->projectComponent?->name }}</strong></div>
                    <div class="meta-item"><small>Responsible Directorate</small><strong title="{{ $report->responsibleDirectorate?->name }}">{{ $report->responsibleDirectorate?->name ?: 'Not assigned' }}</strong></div>
                    <div class="meta-item"><small>Report owner</small><strong title="{{ $report->thinkTank?->name }}">{{ $report->thinkTank?->name ?: ($report->createdBy?->name ?: 'Secretariat') }}</strong></div>
                    <div class="meta-item"><small>Reporting period</small><strong>{{ $report->reportingPeriod?->label }}</strong></div>
                </div>
            </header>

            @if (session('success'))
                <div class="alert alert-success mt-3 mb-0">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger mt-3 mb-0">
                    <div class="fw-bold mb-1">Please correct the report before continuing.</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @unless ($editable)
                <div class="readonly-note mt-3">
                    <i class="feather-lock me-1" aria-hidden="true"></i>
                    @if (!$canManage)
                        This report is available in read-only mode.
                    @else
                        This report is locked while it is in {{ strtolower($statusLabel) }} status.
                    @endif
                </div>
            @endunless

            @include('me.performance-reports.partials.completion-summary')

            <form method="POST" action="{{ route('budget.me.performance-reports.update', $report) }}" enctype="multipart/form-data" id="performance-report-form">
                @csrf
                @method('PUT')

                <section class="report-section" id="report-section-1">
                    <div class="section-head">
                        <span class="section-icon"><i class="feather-bar-chart-2" aria-hidden="true"></i></span>
                        <div>
                            <h5>1. Indicator results and progress against target</h5>
                            <p>Only indicators due for {{ $report->periodLabel() }} under the approved reporting frequency are included.</p>
                        </div>
                        @include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['indicator_results']])
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            @foreach ($report->indicatorResults as $result)
                                @php
                                    $indicator = $result->indicator;
                                    $unit = $indicator?->unit?->symbol ?: $indicator?->unit?->name;
                                    $actualValue = old('indicator_results.'.$result->id.'.actual_value', $result->actual_value);
                                    $rollupNumerator = old('indicator_results.'.$result->id.'.rollup_numerator', $result->rollup_numerator);
                                    $rollupDenominator = old('indicator_results.'.$result->id.'.rollup_denominator', $result->rollup_denominator);
                                @endphp
                                <div class="col-xl-6">
                                    <article class="indicator-card" data-indicator-card data-target="{{ $result->target_value }}">
                                        <span class="indicator-code">{{ $indicator?->indicator_code }}</span>
                                        <h6 class="fw-bold">{{ $indicator?->name }}</h6>
                                        <div class="indicator-meta">
                                            <span><i class="feather-calendar me-1" aria-hidden="true"></i>{{ $result->reporting_frequency }}</span>
                                            @if ($unit)<span><i class="feather-hash me-1" aria-hidden="true"></i>{{ $unit }}</span>@endif
                                        </div>
                                        <div class="result-grid">
                                            <div class="result-cell">
                                                <small>Result this period</small>
                                                <div class="result-value" data-period-value>
                                                    {{ $result->actual_value !== null ? number_format((float) $result->actual_value, 2) : 'Pending' }}
                                                </div>
                                            </div>
                                            <div class="result-cell">
                                                <small>Cumulative this year</small>
                                                <div class="result-value">
                                                    {{ $result->cumulative_year_result !== null ? number_format((float) $result->cumulative_year_result, 2) : 'Pending' }}
                                                </div>
                                            </div>
                                            <div class="result-cell">
                                                <small>Since programme baseline</small>
                                                <div class="result-value">
                                                    {{ $result->cumulative_programme_result !== null ? number_format((float) $result->cumulative_programme_result, 2) : 'Pending' }}
                                                </div>
                                            </div>
                                            <div class="result-cell">
                                                <small>Annual target</small>
                                                <div class="result-value">
                                                    {{ $result->annual_target !== null ? number_format((float) $result->annual_target, 2) : 'Not set' }}
                                                </div>
                                            </div>
                                            <div class="result-cell">
                                                <small>Life-of-programme target</small>
                                                <div class="result-value">
                                                    {{ $result->life_of_programme_target !== null ? number_format((float) $result->life_of_programme_target, 2) : 'Not set' }}
                                                </div>
                                            </div>
                                            <div class="result-cell">
                                                <small>Annual target achieved</small>
                                                <div class="result-value">
                                                    {{ $result->target_achievement_percent !== null ? number_format((float) $result->target_achievement_percent, 1).'%' : 'Pending' }}
                                                </div>
                                            </div>
                                            <div class="result-cell">
                                                <small>Period result</small>
                                                <input
                                                    type="number"
                                                    step="any"
                                                    name="indicator_results[{{ $result->id }}][actual_value]"
                                                    class="form-control @error('indicator_results.'.$result->id.'.actual_value') is-invalid @enderror"
                                                    value="{{ $actualValue }}"
                                                    aria-label="Actual result for {{ $indicator?->name }}"
                                                    data-actual
                                                    @disabled(!$editable)
                                                >
                                            </div>
                                            <div class="result-cell">
                                                <small>Period-target progress</small>
                                                <div class="result-value" data-progress>
                                                    {{ $result->progress_percent !== null ? number_format((float) $result->progress_percent, 1).'%' : 'Pending' }}
                                                </div>
                                            </div>
                                            @if($indicator?->organization_rollup_method === 'weighted_average')
                                                <div class="result-cell">
                                                    <small>Weighted numerator</small>
                                                    <input type="number" step="any" min="0" name="indicator_results[{{ $result->id }}][rollup_numerator]" class="form-control" value="{{ $rollupNumerator }}" @disabled(!$editable)>
                                                </div>
                                                <div class="result-cell">
                                                    <small>Weighted denominator</small>
                                                    <input type="number" step="any" min="0.0001" name="indicator_results[{{ $result->id }}][rollup_denominator]" class="form-control" value="{{ $rollupDenominator }}" @disabled(!$editable)>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="small text-muted mt-2">
                                            <i class="feather-layers me-1"></i>
                                            Time aggregation: {{ \App\Models\Indicator::AGGREGATION_METHODS[$result->aggregation_method] ?? 'Latest reported value' }}
                                            &middot; Organization roll-up: {{ \App\Models\Indicator::ORGANIZATION_ROLLUP_METHODS[$indicator?->organization_rollup_method] ?? 'Sum' }}
                                        </div>
                                        @if ($indicator?->meansOfVerificationFolder)
                                            <a class="mov-link" href="{{ route('budget.me.rebuild.knowledge-repository', ['folder_id' => $indicator->meansOfVerificationFolder->id]) }}" target="_blank" rel="noopener">
                                                <i class="feather-archive" aria-hidden="true"></i>
                                                MOV folder: {{ $indicator->meansOfVerificationFolder->name }} ({{ $indicator->meansOfVerificationFolder->documents->count() }} documents)
                                                <i class="feather-external-link ms-auto" aria-hidden="true"></i>
                                            </a>
                                        @else
                                            <div class="mov-link text-muted">
                                                <i class="feather-alert-circle" aria-hidden="true"></i>No repository MOV is linked to this indicator.
                                            </div>
                                        @endif
                                    </article>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="report-section" id="report-section-2">
                    <div class="section-head">
                        <span class="section-icon"><i class="feather-award" aria-hidden="true"></i></span>
                        <div><h5>2. Achievements and variance</h5><p>Summarise progress and explain any variance from the approved targets.</p></div>
                        @include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['achievements_variance']])
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label" for="key-achievements">Key achievements</label>
                                <textarea name="key_achievements" id="key-achievements" rows="6" class="form-control @error('key_achievements') is-invalid @enderror" @disabled(!$editable)>{{ old('key_achievements', $report->key_achievements) }}</textarea>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label" for="variance-explanation">Explanation of variance from targets</label>
                                <textarea name="variance_explanation" id="variance-explanation" rows="6" class="form-control @error('variance_explanation') is-invalid @enderror" @disabled(!$editable)>{{ old('variance_explanation', $report->variance_explanation) }}</textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="report-section" id="report-section-3">
                    <div class="section-head">
                        <span class="section-icon"><i class="feather-paperclip" aria-hidden="true"></i></span>
                        <div><h5>3. Means of Verification and supporting documents</h5><p>Record the evidence used and attach supporting files. Repository-linked MOVs appear with each indicator above.</p></div>
                        @include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['means_of_verification']])
                    </div>
                    <div class="section-body">
                        <label class="form-label" for="mov-notes">Means of Verification (MOV) notes</label>
                        <textarea name="means_of_verification_notes" id="mov-notes" rows="4" class="form-control @error('means_of_verification_notes') is-invalid @enderror" @disabled(!$editable)>{{ old('means_of_verification_notes', $report->means_of_verification_notes) }}</textarea>

                        @if ($report->documents->isNotEmpty())
                            <div class="mt-3">
                                <div class="form-label">Attached evidence</div>
                                <div class="d-grid gap-2">
                                    @foreach ($report->documents as $document)
                                        <div class="existing-document">
                                            <div class="document-file">
                                                <strong>{{ $document->document_name }}</strong>
                                                <small class="text-muted">Repository version {{ $document->repositoryItem?->version_number ?: 1 }}</small>
                                                @if($document->repositoryItem?->versions?->isNotEmpty())
                                                    <details class="small mt-1"><summary class="text-primary">Version history ({{ $document->repositoryItem->versions->count() }})</summary><ol class="mb-0 ps-3">@foreach($document->repositoryItem->versions->sortByDesc('version_number') as $version)<li><a href="{{ route('budget.me.knowledge-evidence.versions.download',[$document->repositoryItem,$version]) }}">v{{ $version->version_number }} - {{ $version->original_filename }}</a>@if($version->change_notes) - {{ $version->change_notes }}@endif</li>@endforeach</ol></details>
                                                @endif
                                                <small class="text-muted">{{ $document->original_filename }} · {{ $document->formattedSize() }}</small>
                                            </div>
                                            <a href="{{ route('budget.me.performance-reports.documents.download', [$report, $document]) }}" class="btn btn-sm btn-light border">
                                                <i class="feather-download me-1" aria-hidden="true"></i>Download
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($editable)
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                    <div class="form-label mb-0">Add attachments</div>
                                    <button type="button" class="btn btn-sm btn-light border" data-add-document>
                                        <i class="feather-plus me-1" aria-hidden="true"></i>Add document
                                    </button>
                                </div>
                                <div class="d-grid gap-2" data-document-list>
                                    <div class="document-row" data-document-row>
                                        <input type="text" name="document_names[]" class="form-control" placeholder="Document name">
                                        <input type="file" name="documents[]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.zip">
                                        <button type="button" class="btn btn-light border" data-remove-document aria-label="Remove attachment"><i class="feather-x" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                                <div class="text-muted small mt-2">Up to 10 files, 20 MB each. PDF, Office, CSV, text, image and ZIP files are accepted.</div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="report-section" id="report-section-4">
                    <div class="section-head">
                        <span class="section-icon"><i class="feather-check-circle" aria-hidden="true"></i></span>
                        <div><h5>4. Overall assessment, performance rating and conclusion</h5><p>Provide the management assessment and the overall evidence-based conclusion.</p></div>
                        @include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['overall_assessment']])
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-lg-8">
                                <label class="form-label" for="overall-assessment">Overall assessment</label>
                                <textarea name="overall_assessment" id="overall-assessment" rows="5" class="form-control @error('overall_assessment') is-invalid @enderror" @disabled(!$editable)>{{ old('overall_assessment', $report->overall_assessment) }}</textarea>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label" for="performance-rating">Performance rating</label>
                                <select name="performance_rating" id="performance-rating" class="form-select @error('performance_rating') is-invalid @enderror" @disabled(!$editable)>
                                    <option value="">Select rating</option>
                                    @foreach ($performanceRatings as $value => $label)
                                        <option value="{{ $value }}" @selected(old('performance_rating', $report->performance_rating) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="conclusion">Conclusion</label>
                                <textarea name="conclusion" id="conclusion" rows="4" class="form-control @error('conclusion') is-invalid @enderror" @disabled(!$editable)>{{ old('conclusion', $report->conclusion) }}</textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="report-section" id="report-section-5">
                    <div class="section-head">
                        <span class="section-icon"><i class="feather-shield" aria-hidden="true"></i></span>
                        <div><h5>5. Challenges and mitigation strategies</h5><p>Capture delivery constraints and the action taken or proposed to address them.</p></div>
                        @include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['challenges_mitigation']])
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label" for="challenges-faced">Challenges faced</label>
                                <textarea name="challenges_faced" id="challenges-faced" rows="5" class="form-control @error('challenges_faced') is-invalid @enderror" @disabled(!$editable)>{{ old('challenges_faced', $report->challenges_faced) }}</textarea>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label" for="mitigation-strategies">Mitigation strategies</label>
                                <textarea name="mitigation_strategies" id="mitigation-strategies" rows="5" class="form-control @error('mitigation_strategies') is-invalid @enderror" @disabled(!$editable)>{{ old('mitigation_strategies', $report->mitigation_strategies) }}</textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="report-section" id="report-section-6">
                    <div class="section-head">
                        <span class="section-icon"><i class="feather-refresh-cw" aria-hidden="true"></i></span>
                        <div><h5>6. Lessons learned and adaptive management</h5><p>Record what was learned and how implementation will be adjusted.</p></div>
                        @include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['lessons_adaptive_management']])
                    </div>
                    <div class="section-body">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label" for="lessons-learned">Lessons learned</label>
                                <textarea name="lessons_learned" id="lessons-learned" rows="5" class="form-control @error('lessons_learned') is-invalid @enderror" @disabled(!$editable)>{{ old('lessons_learned', $report->lessons_learned) }}</textarea>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label" for="adaptive-actions">Adaptive management actions</label>
                                <textarea name="adaptive_management_actions" id="adaptive-actions" rows="5" class="form-control @error('adaptive_management_actions') is-invalid @enderror" @disabled(!$editable)>{{ old('adaptive_management_actions', $report->adaptive_management_actions) }}</textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="report-section" id="report-section-7">
                    <div class="section-head">
                        <span class="section-icon"><i class="feather-compass" aria-hidden="true"></i></span>
                        <div><h5>7. Priorities or plans for the next reporting period</h5><p>Set out the next quarter’s priorities, planned outputs and management focus.</p></div>
                        @include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['next_period_priorities']])
                    </div>
                    <div class="section-body">
                        <label class="form-label" for="next-period-priorities">Next reporting-period priorities or plans</label>
                        <textarea name="next_period_priorities" id="next-period-priorities" rows="6" class="form-control @error('next_period_priorities') is-invalid @enderror" @disabled(!$editable)>{{ old('next_period_priorities', $report->next_period_priorities) }}</textarea>
                    </div>
                </section>

                @if ($editable)
                    <div class="report-actions">
                        <div class="text-muted small"><i class="feather-info me-1" aria-hidden="true"></i>Save the draft before submitting it for review.</div>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-save me-1" aria-hidden="true"></i>Save Report Draft
                        </button>
                    </div>
                @endif
            </form>

            @include('me.performance-reports.partials.achievement-tracker')

            @if ($editable)
                @foreach ($report->documents as $document)
                    <form id="delete-document-{{ $document->id }}" method="POST" action="{{ route('budget.me.performance-reports.documents.destroy', [$report, $document]) }}" class="d-none">
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
                @if ($report->documents->isNotEmpty())
                    <section class="report-section">
                        <div class="section-head"><span class="section-icon"><i class="feather-layers"></i></span><div><h5>Document revisions</h5><p>Upload a corrected version without deleting the earlier audit record.</p></div></div>
                        <div class="section-body d-grid gap-3">
                            @foreach ($report->documents as $document)
                                <div class="border rounded-3 p-3">
                                    <div class="fw-semibold mb-2">{{ $document->document_name }} - current version {{ $document->repositoryItem?->version_number ?: 1 }}</div>
                                    <form method="POST" action="{{ route('budget.me.performance-reports.documents.replace',[$report,$document]) }}" enctype="multipart/form-data" class="row g-2 align-items-end">@csrf<div class="col-lg-5"><label class="form-label small">Corrected file</label><input type="file" name="replacement_file" class="form-control" required></div><div class="col-lg-5"><label class="form-label small">What changed? *</label><input name="change_notes" class="form-control" required maxlength="5000" placeholder="Describe corrections made after review"></div><div class="col-lg-2"><button class="btn btn-outline-primary w-100">Upload v{{ ((int)($document->repositoryItem?->version_number ?: 1))+1 }}</button></div></form>
                                    <div class="text-end mt-2"><button type="submit" form="delete-document-{{ $document->id }}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Unlink {{ addslashes($document->document_name) }}? The repository version history will be retained.')"><i class="feather-trash-2 me-1"></i>Unlink document</button></div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

            @endif

            @include('me.performance-reports.partials.lifecycle-actions', ['isPortal' => false])

            @if ($report->review_notes && ! $report->isSubmitted())
                <section class="report-section">
                    <div class="section-head">
                        <span class="section-icon"><i class="feather-message-square" aria-hidden="true"></i></span>
                        <div><h5>Latest review decision</h5><p>{{ $report->reviewedBy?->name }} · {{ optional($report->reviewed_at)->format('d M Y, H:i') }}</p></div>
                    </div>
                    <div class="section-body">{!! nl2br(e($report->review_notes)) !!}</div>
                </section>
            @endif

            @if ($report->isArchived())
                <section class="report-section">
                    <div class="section-head">
                        <span class="section-icon"><i class="feather-lock" aria-hidden="true"></i></span>
                        <div>
                            <h5>Historical record</h5>
                            <p>Archived by {{ $report->archivedBy?->name ?: 'an authorized officer' }} on {{ optional($report->archived_at)->format('d M Y, H:i') }}.</p>
                        </div>
                    </div>
                    @if ($report->archive_notes)
                        <div class="section-body">{!! nl2br(e($report->archive_notes)) !!}</div>
                    @endif
                </section>
            @endif

            @if ($report->transitions->isNotEmpty())
                <section class="report-section">
                    <div class="section-head">
                        <span class="section-icon"><i class="feather-clock" aria-hidden="true"></i></span>
                        <div><h5>Lifecycle history</h5><p>Immutable record of report creation, submission, review, return and archival actions.</p></div>
                    </div>
                    <div class="section-body">
                        <div class="d-grid gap-2">
                            @foreach ($report->transitions as $transition)
                                <div class="existing-document">
                                    <div class="document-file">
                                        <strong>{{ \Illuminate\Support\Str::headline($transition->action) }}</strong>
                                        <small class="text-muted">
                                            {{ $transition->actor?->name ?: 'System' }} · {{ $transition->created_at?->format('d M Y, H:i') }}
                                            · {{ $transition->from_status ? \Illuminate\Support\Str::headline($transition->from_status).' → ' : '' }}{{ \Illuminate\Support\Str::headline($transition->to_status) }}
                                        </small>
                                        @if ($transition->notes)<small class="text-muted mt-1">{{ $transition->notes }}</small>@endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-indicator-card]').forEach((card) => {
                const actualInput = card.querySelector('[data-actual]');
                const progressOutput = card.querySelector('[data-progress]');
                const updateProgress = () => {
                    const target = Number(card.dataset.target);
                    const actual = Number(actualInput?.value);
                    const hasTarget = card.dataset.target !== '' && Number.isFinite(target) && target !== 0;
                    const hasActual = actualInput?.value !== '' && Number.isFinite(actual);
                    progressOutput.textContent = hasTarget && hasActual
                        ? `${((actual / target) * 100).toFixed(1)}%`
                        : 'Pending';
                };
                actualInput?.addEventListener('input', updateProgress);
            });

            const list = document.querySelector('[data-document-list]');
            const addButton = document.querySelector('[data-add-document]');
            const bindRemove = (button) => {
                button.addEventListener('click', () => {
                    const rows = list?.querySelectorAll('[data-document-row]') || [];
                    const row = button.closest('[data-document-row]');
                    if (rows.length === 1) {
                        row?.querySelectorAll('input').forEach((input) => {
                            input.value = '';
                        });
                        return;
                    }
                    row?.remove();
                });
            };

            list?.querySelectorAll('[data-remove-document]').forEach(bindRemove);
            addButton?.addEventListener('click', () => {
                const rows = list?.querySelectorAll('[data-document-row]') || [];
                if (!list || !rows.length || rows.length >= 10) {
                    return;
                }
                const clone = rows[0].cloneNode(true);
                clone.querySelectorAll('input').forEach((input) => {
                    input.value = '';
                });
                const remove = clone.querySelector('[data-remove-document]');
                if (remove) {
                    bindRemove(remove);
                }
                list.appendChild(clone);
            });
        });
    </script>
@endpush
