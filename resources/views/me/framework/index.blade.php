@extends('layouts.app')

@section('title', 'ATTP MEL Framework Administration')

@php
    $formContext = old('form_context', 'configuration');
    $initialTab = in_array($formContext, ['configuration', 'irs', 'targets', 'calculation'], true)
        ? $formContext
        : 'configuration';
    $approvedIrsCount = $indicators->filter(fn ($indicator) => $indicator->approvedReferenceSheet)->count();
    $approvedTargetCount = $indicators->sum(
        fn ($indicator) => $indicator->targets->where('approval_status', 'approved')->count()
    );
    $systemCalculatedCount = $indicators->where('reporting_source', 'system_calculated')->count();
    $irs = $selected?->approvedReferenceSheet;
    $nextIrsVersion = $selected ? ((int) $selected->referenceSheets->max('version') + 1) : 1;
    $latestRule = $selected?->calculationRules->sortByDesc('version')->first();
    $targetTypeLabels = [
        'cumulative' => 'Cumulative target',
        'period' => 'Period-specific target',
        'end_target' => 'End target only',
        'milestone' => 'Qualitative milestone',
    ];
@endphp

@push('styles')
<style>
    .mel-framework {
        --primary: #075c7a;
        --primary-dark: #06465e;
        --primary-soft: #edf5f7;
        --ink: #17313a;
        --muted: #64777e;
        --line: #dbe5e8;
        --surface: #ffffff;
        --surface-soft: #f7f9fa;
        max-width: 1540px;
        margin: 0 auto;
        color: var(--ink);
    }

    .mel-framework .page-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.5rem;
        padding: 1.5rem;
        border-radius: 1rem;
        background: linear-gradient(120deg, var(--primary-dark), #08708e);
        box-shadow: 0 16px 38px rgba(7, 92, 122, .15);
        color: #fff;
    }

    .mel-framework .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        margin-bottom: .35rem;
        font-size: .74rem;
        font-weight: 800;
        letter-spacing: .07em;
        text-transform: uppercase;
        opacity: .82;
    }

    .mel-framework .page-hero h1 {
        margin: 0 0 .35rem;
        color: #fff;
        font-size: clamp(1.35rem, 2vw, 1.75rem);
        font-weight: 750;
    }

    .mel-framework .page-hero p {
        max-width: 820px;
        margin: 0;
        color: rgba(255, 255, 255, .82);
        font-size: .86rem;
        line-height: 1.55;
    }

    .mel-framework .hero-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .55rem;
    }

    .mel-framework .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .8rem;
        margin: 1rem 0;
    }

    .mel-framework .summary-card,
    .mel-framework .panel,
    .mel-framework .framework-settings {
        border: 1px solid var(--line);
        border-radius: .9rem;
        background: var(--surface);
        box-shadow: 0 8px 25px rgba(19, 49, 59, .035);
    }

    .mel-framework .summary-card {
        display: flex;
        align-items: center;
        gap: .8rem;
        padding: .95rem 1rem;
    }

    .mel-framework .summary-icon {
        display: grid;
        flex: 0 0 2.45rem;
        width: 2.45rem;
        height: 2.45rem;
        place-items: center;
        border-radius: .7rem;
        background: var(--primary-soft);
        color: var(--primary);
        font-size: 1rem;
    }

    .mel-framework .summary-card small,
    .mel-framework .summary-card strong {
        display: block;
    }

    .mel-framework .summary-card small {
        margin-bottom: .12rem;
        color: var(--muted);
        font-size: .7rem;
        font-weight: 700;
    }

    .mel-framework .summary-card strong {
        color: var(--ink);
        font-size: 1.12rem;
        line-height: 1.2;
    }

    .mel-framework .status-badge,
    .mel-framework .meta-badge {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .28rem .55rem;
        border: 1px solid #cddde2;
        border-radius: 999px;
        background: #f5f9fa;
        color: #36545e;
        font-size: .68rem;
        font-weight: 750;
        white-space: nowrap;
    }

    .mel-framework .status-badge.active,
    .mel-framework .status-badge.approved {
        border-color: #b8d5de;
        background: #eaf4f7;
        color: var(--primary);
    }

    .mel-framework .status-badge.draft {
        border-color: #dfd7c4;
        background: #faf7ef;
        color: #725d2f;
    }

    .mel-framework .education-strip {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: .75rem;
        margin-bottom: 1rem;
        padding: .9rem 1rem;
        border: 1px solid #cfe0e5;
        border-radius: .8rem;
        background: #f1f7f9;
        color: #38555f;
    }

    .mel-framework .education-strip > i {
        margin-top: .08rem;
        color: var(--primary);
        font-size: 1.05rem;
    }

    .mel-framework .education-strip strong,
    .mel-framework .education-strip span {
        display: block;
    }

    .mel-framework .education-strip strong {
        margin-bottom: .15rem;
        color: var(--ink);
        font-size: .8rem;
    }

    .mel-framework .education-strip span {
        font-size: .75rem;
        line-height: 1.5;
    }

    .mel-framework .framework-settings {
        margin-bottom: 1rem;
        overflow: hidden;
    }

    .mel-framework .framework-settings > summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .9rem 1rem;
        cursor: pointer;
        list-style: none;
        background: var(--surface);
    }

    .mel-framework .framework-settings > summary::-webkit-details-marker {
        display: none;
    }

    .mel-framework .framework-settings > summary strong,
    .mel-framework .framework-settings > summary small {
        display: block;
    }

    .mel-framework .framework-settings > summary strong {
        font-size: .86rem;
    }

    .mel-framework .framework-settings > summary small {
        margin-top: .12rem;
        color: var(--muted);
        font-size: .7rem;
    }

    .mel-framework .settings-chevron {
        color: var(--primary);
        transition: transform .2s ease;
    }

    .mel-framework .framework-settings[open] .settings-chevron {
        transform: rotate(180deg);
    }

    .mel-framework .settings-body {
        padding: 1rem;
        border-top: 1px solid var(--line);
        background: var(--surface-soft);
    }

    .mel-framework .workspace {
        display: grid;
        grid-template-columns: minmax(280px, 335px) minmax(0, 1fr);
        align-items: start;
        gap: 1rem;
    }

    .mel-framework .indicator-explorer {
        position: sticky;
        top: 1rem;
        max-height: calc(100vh - 2rem);
        overflow: hidden;
    }

    .mel-framework .panel-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: .95rem 1rem;
        border-bottom: 1px solid var(--line);
        background: var(--surface-soft);
    }

    .mel-framework .panel-title {
        margin: 0;
        color: var(--ink);
        font-size: .9rem;
        font-weight: 750;
    }

    .mel-framework .panel-subtitle {
        margin: .18rem 0 0;
        color: var(--muted);
        font-size: .71rem;
        line-height: 1.45;
    }

    .mel-framework .explorer-controls {
        display: grid;
        gap: .55rem;
        padding: .8rem;
        border-bottom: 1px solid var(--line);
    }

    .mel-framework .search-control {
        position: relative;
    }

    .mel-framework .search-control i {
        position: absolute;
        top: 50%;
        left: .8rem;
        color: #829399;
        transform: translateY(-50%);
    }

    .mel-framework .search-control input {
        padding-left: 2.25rem;
    }

    .mel-framework .indicator-list {
        max-height: calc(100vh - 280px);
        overflow-y: auto;
        scrollbar-width: thin;
    }

    .mel-framework .indicator-link {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .65rem;
        padding: .82rem .9rem;
        border-bottom: 1px solid #edf1f2;
        color: var(--ink);
        text-decoration: none;
        transition: background .16s ease, box-shadow .16s ease;
    }

    .mel-framework .indicator-link:hover {
        background: #f7fafb;
        color: var(--ink);
    }

    .mel-framework .indicator-link.active {
        background: var(--primary-soft);
        box-shadow: inset 3px 0 0 var(--primary);
    }

    .mel-framework .indicator-code {
        display: inline-flex;
        align-items: center;
        align-self: start;
        justify-content: center;
        min-width: 3.7rem;
        padding: .27rem .4rem;
        border-radius: .4rem;
        background: #e8f1f4;
        color: var(--primary);
        font-size: .67rem;
        font-weight: 850;
    }

    .mel-framework .indicator-name {
        display: block;
        margin-bottom: .28rem;
        font-size: .76rem;
        font-weight: 700;
        line-height: 1.4;
    }

    .mel-framework .indicator-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .3rem .45rem;
        color: var(--muted);
        font-size: .64rem;
    }

    .mel-framework .explorer-empty {
        display: none;
        padding: 1.5rem 1rem;
        color: var(--muted);
        font-size: .75rem;
        text-align: center;
    }

    .mel-framework .indicator-summary {
        padding: 1.1rem;
        border-bottom: 1px solid var(--line);
    }

    .mel-framework .indicator-summary-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .mel-framework .indicator-summary h2 {
        margin: .25rem 0 .35rem;
        color: var(--ink);
        font-size: 1.1rem;
        line-height: 1.4;
    }

    .mel-framework .indicator-summary p {
        margin: 0;
        color: var(--muted);
        font-size: .75rem;
    }

    .mel-framework .indicator-facts {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        margin-top: .8rem;
    }

    .mel-framework .workspace-tabs {
        display: flex;
        gap: .25rem;
        overflow-x: auto;
        padding: .6rem .7rem 0;
        border-bottom: 1px solid var(--line);
        background: var(--surface-soft);
    }

    .mel-framework .workspace-tab {
        display: inline-flex;
        align-items: center;
        gap: .42rem;
        padding: .68rem .78rem;
        border: 0;
        border-bottom: 2px solid transparent;
        background: transparent;
        color: var(--muted);
        font-size: .73rem;
        font-weight: 750;
        white-space: nowrap;
    }

    .mel-framework .workspace-tab:hover,
    .mel-framework .workspace-tab.active {
        color: var(--primary);
    }

    .mel-framework .workspace-tab.active {
        border-bottom-color: var(--primary);
        background: #fff;
    }

    .mel-framework .tab-panel {
        padding: 1rem;
    }

    .mel-framework .section-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .85rem;
    }

    .mel-framework .section-heading h3 {
        margin: 0;
        color: var(--ink);
        font-size: .9rem;
    }

    .mel-framework .section-heading p {
        max-width: 760px;
        margin: .18rem 0 0;
        color: var(--muted);
        font-size: .72rem;
        line-height: 1.5;
    }

    .mel-framework .form-section {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #e6edef;
    }

    .mel-framework .form-section:first-of-type {
        margin-top: 0;
        padding-top: 0;
        border-top: 0;
    }

    .mel-framework .form-section-title {
        margin: 0 0 .75rem;
        color: #36525c;
        font-size: .77rem;
        font-weight: 800;
    }

    .mel-framework .field-label {
        display: block;
        margin-bottom: .32rem;
        color: #3d5962;
        font-size: .72rem;
        font-weight: 750;
    }

    .mel-framework .field-label .required {
        color: #a63b3b;
    }

    .mel-framework .field-help {
        display: block;
        margin-top: .3rem;
        color: #75878d;
        font-size: .65rem;
        line-height: 1.4;
    }

    .mel-framework .form-control,
    .mel-framework .form-select {
        min-height: 42px;
        border-color: #cfdcdf;
        color: var(--ink);
        font-size: .78rem;
    }

    .mel-framework textarea.form-control {
        min-height: auto;
        line-height: 1.5;
    }

    .mel-framework .form-control:focus,
    .mel-framework .form-select:focus {
        border-color: #7aaebe;
        box-shadow: 0 0 0 .18rem rgba(7, 92, 122, .11);
    }

    .mel-framework .option-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .6rem;
    }

    .mel-framework .check-card {
        display: flex;
        align-items: flex-start;
        gap: .6rem;
        padding: .75rem;
        border: 1px solid var(--line);
        border-radius: .65rem;
        background: var(--surface-soft);
        cursor: pointer;
    }

    .mel-framework .check-card input {
        margin-top: .14rem;
    }

    .mel-framework .check-card strong,
    .mel-framework .check-card small {
        display: block;
    }

    .mel-framework .check-card strong {
        color: var(--ink);
        font-size: .72rem;
    }

    .mel-framework .check-card small {
        margin-top: .12rem;
        color: var(--muted);
        font-size: .64rem;
        line-height: 1.4;
    }

    .mel-framework .form-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #e6edef;
    }

    .mel-framework .form-actions small {
        max-width: 650px;
        color: var(--muted);
        font-size: .67rem;
        line-height: 1.45;
    }

    .mel-framework .btn-primary {
        border-color: var(--primary);
        background: var(--primary);
    }

    .mel-framework .btn-primary:hover,
    .mel-framework .btn-primary:focus {
        border-color: var(--primary-dark);
        background: var(--primary-dark);
    }

    .mel-framework .current-record {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: .75rem;
        margin-bottom: 1rem;
        padding: .8rem;
        border: 1px solid #cfe0e5;
        border-radius: .7rem;
        background: #f3f8fa;
    }

    .mel-framework .current-record-icon {
        display: grid;
        width: 2.2rem;
        height: 2.2rem;
        place-items: center;
        border-radius: .6rem;
        background: #dfeef2;
        color: var(--primary);
    }

    .mel-framework .current-record strong,
    .mel-framework .current-record small {
        display: block;
    }

    .mel-framework .current-record strong {
        font-size: .76rem;
    }

    .mel-framework .current-record small {
        margin-top: .1rem;
        color: var(--muted);
        font-size: .65rem;
    }

    .mel-framework .table-shell {
        overflow: hidden;
        border: 1px solid var(--line);
        border-radius: .7rem;
    }

    .mel-framework .table-shell .table {
        margin-bottom: 0;
        font-size: .72rem;
    }

    .mel-framework .table-shell th {
        border-bottom-width: 1px;
        background: var(--surface-soft);
        color: #536a72;
        font-size: .65rem;
        letter-spacing: .025em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .mel-framework .table-shell td {
        vertical-align: middle;
    }

    .mel-framework .empty-state {
        padding: 2rem 1rem;
        color: var(--muted);
        text-align: center;
    }

    .mel-framework .empty-state i {
        display: block;
        margin-bottom: .5rem;
        color: #8ea3aa;
        font-size: 1.5rem;
    }

    .mel-framework .empty-state strong,
    .mel-framework .empty-state span {
        display: block;
    }

    .mel-framework .empty-state strong {
        color: var(--ink);
        font-size: .8rem;
    }

    .mel-framework .empty-state span {
        margin-top: .2rem;
        font-size: .7rem;
    }

    .mel-framework .subform {
        margin-top: 1rem;
        border: 1px solid var(--line);
        border-radius: .75rem;
        overflow: hidden;
    }

    .mel-framework .subform > summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .8rem .9rem;
        cursor: pointer;
        list-style: none;
        background: var(--surface-soft);
        color: var(--ink);
        font-size: .75rem;
        font-weight: 750;
    }

    .mel-framework .subform > summary::-webkit-details-marker {
        display: none;
    }

    .mel-framework .subform-body {
        padding: 1rem;
        border-top: 1px solid var(--line);
    }

    .mel-framework [hidden] {
        display: none !important;
    }

    @media (max-width: 1100px) {
        .mel-framework .summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .mel-framework .workspace {
            grid-template-columns: minmax(250px, 290px) minmax(0, 1fr);
        }

        .mel-framework .option-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 860px) {
        .mel-framework .page-hero,
        .mel-framework .indicator-summary-top,
        .mel-framework .form-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .mel-framework .hero-actions {
            justify-content: flex-start;
        }

        .mel-framework .workspace {
            grid-template-columns: 1fr;
        }

        .mel-framework .indicator-explorer {
            position: static;
            max-height: none;
        }

        .mel-framework .indicator-list {
            max-height: 340px;
        }
    }

    @media (max-width: 560px) {
        .mel-framework .summary-grid {
            grid-template-columns: 1fr;
        }

        .mel-framework .page-hero,
        .mel-framework .tab-panel,
        .mel-framework .indicator-summary {
            padding: 1rem;
        }

        .mel-framework .current-record {
            grid-template-columns: auto 1fr;
        }

        .mel-framework .current-record .status-badge {
            grid-column: 1 / -1;
            justify-self: start;
        }
    }
</style>
@endpush

@section('content')
<div class="mel-framework" data-framework-workspace data-initial-tab="{{ $initialTab }}">
    <header class="page-hero">
        <div>
            <span class="eyebrow">
                <i class="feather-layers" aria-hidden="true"></i>
                {{ $framework->code }} &middot; Version {{ $framework->version }}
            </span>
            <h1>Results Framework Administration</h1>
            <p>
                Maintain indicator ownership, reference guidance, targets and system calculations without
                overwriting historical definitions or approved results.
            </p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-sm btn-light" href="{{ route('budget.me.results-dashboard.index') }}">
                <i class="feather-bar-chart-2 me-1" aria-hidden="true"></i> View official results
            </a>
            <a class="btn btn-sm btn-outline-light" href="{{ route('budget.me.submission-reviews.index') }}">
                <i class="feather-check-square me-1" aria-hidden="true"></i> Review submissions
            </a>
        </div>
    </header>

    @if (session('success'))
        <div class="alert alert-success mt-3" role="status">
            <i class="feather-check-circle me-2" aria-hidden="true"></i>{{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mt-3" role="alert" data-error-summary>
            <div class="fw-bold mb-1">Please review the highlighted information.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="summary-grid" aria-label="Framework summary">
        <article class="summary-card">
            <span class="summary-icon"><i class="feather-shield" aria-hidden="true"></i></span>
            <div>
                <small>Framework status</small>
                <strong>{{ str($framework->status)->headline() }}</strong>
            </div>
        </article>
        <article class="summary-card">
            <span class="summary-icon"><i class="feather-target" aria-hidden="true"></i></span>
            <div>
                <small>Official indicators</small>
                <strong>{{ number_format($indicators->count()) }}</strong>
            </div>
        </article>
        <article class="summary-card">
            <span class="summary-icon"><i class="feather-book-open" aria-hidden="true"></i></span>
            <div>
                <small>Approved reference sheets</small>
                <strong>{{ $approvedIrsCount }} of {{ $indicators->count() }}</strong>
            </div>
        </article>
        <article class="summary-card">
            <span class="summary-icon"><i class="feather-git-branch" aria-hidden="true"></i></span>
            <div>
                <small>Approved targets</small>
                <strong>{{ number_format($approvedTargetCount) }}</strong>
            </div>
        </article>
    </section>

    <aside class="education-strip" aria-label="Framework governance guidance">
        <i class="feather-info" aria-hidden="true"></i>
        <div>
            <strong>How to use this workspace</strong>
            <span>
                Select an indicator on the left, then use the focused tabs to configure it. Create a new IRS,
                target or calculation-rule version when official guidance changes; do not edit historical records.
                Only approved targets and final Secretariat-approved results appear in official reporting.
            </span>
        </div>
    </aside>

    <details class="framework-settings" @if ($formContext === 'framework' && $errors->any()) open @endif>
        <summary>
            <span>
                <strong><i class="feather-settings me-2" aria-hidden="true"></i>Framework profile and governance</strong>
                <small>Update the framework title, PDO statement, effective dates and formal status.</small>
            </span>
            <span class="d-flex align-items-center gap-2">
                <span class="status-badge {{ $framework->status }}">{{ str($framework->status)->headline() }}</span>
                <i class="feather-chevron-down settings-chevron" aria-hidden="true"></i>
            </span>
        </summary>
        <div class="settings-body">
            <form method="POST" action="{{ route('budget.me.framework.update', $framework) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="form_context" value="framework">
                <input type="hidden" name="is_current" value="1">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <label class="field-label" for="framework-title">Framework title <span class="required">*</span></label>
                        <input id="framework-title" name="title" class="form-control" value="{{ old('title', $framework->title) }}" required>
                    </div>
                    <div class="col-lg-4">
                        <label class="field-label" for="framework-status">Formal status <span class="required">*</span></label>
                        <select id="framework-status" name="status" class="form-select" required>
                            @foreach (['draft' => 'Draft — still under review', 'active' => 'Active — approved for use', 'retired' => 'Retired — historical only'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $framework->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="field-label" for="framework-pdo">Project Development Objective <span class="required">*</span></label>
                        <textarea id="framework-pdo" name="project_development_objective" class="form-control" rows="3" required>{{ old('project_development_objective', $framework->project_development_objective) }}</textarea>
                        <span class="field-help">Use the exact approved PDO wording. This statement appears on official results reports.</span>
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="framework-effective-from">Effective from</label>
                        <input id="framework-effective-from" type="date" name="effective_from" class="form-control" value="{{ old('effective_from', $framework->effective_from?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="framework-effective-to">Effective to</label>
                        <input id="framework-effective-to" type="date" name="effective_to" class="form-control" value="{{ old('effective_to', $framework->effective_to?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="field-label" for="framework-notes">Version notes</label>
                        <textarea id="framework-notes" name="notes" class="form-control" rows="2">{{ old('notes', $framework->notes) }}</textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <small>
                        This remains the current framework version. Activating or retiring it records governance metadata;
                        historical results and earlier IRS/target versions are not rewritten.
                    </small>
                    <button class="btn btn-primary" type="submit">
                        <i class="feather-save me-1" aria-hidden="true"></i> Save framework profile
                    </button>
                </div>
            </form>
        </div>
    </details>

    <div class="workspace">
        <aside class="panel indicator-explorer" aria-label="Official indicator explorer">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Indicator explorer</h2>
                    <p class="panel-subtitle">Choose one indicator to administer.</p>
                </div>
                <span class="meta-badge">{{ $indicators->count() }}</span>
            </div>
            <div class="explorer-controls">
                <div class="search-control">
                    <i class="feather-search" aria-hidden="true"></i>
                    <input class="form-control" type="search" placeholder="Search code or indicator..." aria-label="Search indicators" data-indicator-search>
                </div>
                <select class="form-select" aria-label="Filter indicators by component" data-indicator-group>
                    <option value="all">All PDOs and components</option>
                    <option value="pdo">PDO indicators</option>
                    @foreach ($components as $component)
                        <option value="{{ $component->id }}">{{ $component->project_id }} &middot; {{ $component->name }}</option>
                    @endforeach
                </select>
            </div>
            <nav class="indicator-list" data-indicator-list>
                @foreach ($indicators as $item)
                    <a
                        class="indicator-link {{ $selected?->id === $item->id ? 'active' : '' }}"
                        href="{{ route('budget.me.framework.index', ['indicator' => $item->id]) }}"
                        data-indicator-item
                        data-search="{{ str($item->indicator_code.' '.$item->name.' '.$item->projectComponent?->name)->lower() }}"
                        data-group="{{ $item->results_level === 'pdo' ? 'pdo' : $item->project_component_id }}"
                        @if ($selected?->id === $item->id) aria-current="page" @endif
                    >
                        <span class="indicator-code">{{ $item->indicator_code }}</span>
                        <span>
                            <span class="indicator-name">{{ $item->name }}</span>
                            <span class="indicator-meta">
                                <span>{{ $item->projectComponent?->name ?: 'Project Development Objective' }}</span>
                                <span>&middot;</span>
                                <span>{{ str($item->reporting_source)->headline() }}</span>
                                @if ($item->approvedReferenceSheet)
                                    <span>&middot; IRS v{{ $item->approvedReferenceSheet->version }}</span>
                                @endif
                            </span>
                        </span>
                    </a>
                @endforeach
                <div class="explorer-empty" data-indicator-empty>
                    <i class="feather-search mb-2" aria-hidden="true"></i>
                    No indicators match the current search and component filter.
                </div>
            </nav>
        </aside>

        <main class="panel">
            @if ($selected)
                <section class="indicator-summary">
                    <div class="indicator-summary-top">
                        <div>
                            <span class="indicator-code">{{ $selected->indicator_code }}</span>
                            <h2>{{ $selected->name }}</h2>
                            <p>{{ $selected->result_area ?: 'No result area has been recorded for this indicator.' }}</p>
                        </div>
                        <span class="status-badge {{ $selected->is_active ? 'active' : 'draft' }}">
                            <i class="feather-{{ $selected->is_active ? 'check-circle' : 'pause-circle' }}" aria-hidden="true"></i>
                            {{ $selected->is_active ? 'Active indicator' : 'Inactive indicator' }}
                        </span>
                    </div>
                    <div class="indicator-facts">
                        <span class="meta-badge"><i class="feather-folder" aria-hidden="true"></i>{{ $selected->projectComponent?->name ?: 'PDO level' }}</span>
                        <span class="meta-badge"><i class="feather-database" aria-hidden="true"></i>{{ str($selected->reporting_source)->headline() }}</span>
                        <span class="meta-badge"><i class="feather-hash" aria-hidden="true"></i>{{ $valueTypes[$selected->value_type] ?? str($selected->value_type)->headline() }}</span>
                        <span class="meta-badge"><i class="feather-book-open" aria-hidden="true"></i>{{ $irs ? 'IRS v'.$irs->version : 'No approved IRS' }}</span>
                        <span class="meta-badge"><i class="feather-target" aria-hidden="true"></i>{{ $selected->targets->count() }} target records</span>
                    </div>
                </section>

                <nav class="workspace-tabs" role="tablist" aria-label="Indicator administration areas">
                    <button class="workspace-tab" type="button" role="tab" data-tab-button="configuration" aria-controls="framework-tab-configuration">
                        <i class="feather-sliders" aria-hidden="true"></i> Configuration
                    </button>
                    <button class="workspace-tab" type="button" role="tab" data-tab-button="irs" aria-controls="framework-tab-irs">
                        <i class="feather-book-open" aria-hidden="true"></i> Reference Sheet
                    </button>
                    <button class="workspace-tab" type="button" role="tab" data-tab-button="targets" aria-controls="framework-tab-targets">
                        <i class="feather-target" aria-hidden="true"></i> Targets
                    </button>
                    <button class="workspace-tab" type="button" role="tab" data-tab-button="calculation" aria-controls="framework-tab-calculation">
                        <i class="feather-git-branch" aria-hidden="true"></i> Calculation
                    </button>
                </nav>

                <section id="framework-tab-configuration" class="tab-panel" role="tabpanel" data-tab-panel="configuration">
                    <div class="section-heading">
                        <div>
                            <h3>Indicator configuration</h3>
                            <p>Define who reports this indicator and how approved records are consolidated across periods and organizations.</p>
                        </div>
                    </div>
                    <aside class="education-strip">
                        <i class="feather-help-circle" aria-hidden="true"></i>
                        <div>
                            <strong>Before changing aggregation</strong>
                            <span>Confirm the method against the approved IRS. Percentages normally require weighted numerator/denominator consolidation, while milestones and non-additive indicators should not be summed.</span>
                        </div>
                    </aside>
                    <form method="POST" action="{{ route('budget.me.framework.indicators.update', $selected) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="form_context" value="configuration">

                        <div class="form-section">
                            <h4 class="form-section-title">Purpose and measurement</h4>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="field-label" for="indicator-result-area">Result area</label>
                                    <textarea id="indicator-result-area" name="result_area" class="form-control" rows="3">{{ old('result_area', $selected->result_area) }}</textarea>
                                    <span class="field-help">State the outcome or component result this indicator helps measure.</span>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="indicator-value-type">Value type <span class="required">*</span></label>
                                    <select id="indicator-value-type" name="value_type" class="form-select" required>
                                        @foreach ($valueTypes as $key => $label)
                                            <option value="{{ $key }}" @selected(old('value_type', $selected->value_type) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <span class="field-help">Controls how actual results are entered and displayed.</span>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="indicator-target-type">Target behavior <span class="required">*</span></label>
                                    <select id="indicator-target-type" name="target_type" class="form-select" required>
                                        @foreach ($targetTypeLabels as $key => $label)
                                            <option value="{{ $key }}" @selected(old('target_type', $selected->target_type) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <span class="field-help">Determines whether targets apply by period, cumulatively or as milestones.</span>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="indicator-reporting-source">Reporting responsibility <span class="required">*</span></label>
                                    <select id="indicator-reporting-source" name="reporting_source" class="form-select" required>
                                        @foreach ($reportingSources as $key => $label)
                                            <option value="{{ $key }}" @selected(old('reporting_source', $selected->reporting_source) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <span class="field-help">Choose Secretariat, Think Tank, both, or system-calculated.</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="form-section-title">Consolidation controls</h4>
                            <div class="row g-3">
                                <div class="col-lg-5">
                                    <label class="field-label" for="indicator-period-aggregation">Across reporting periods <span class="required">*</span></label>
                                    <select id="indicator-period-aggregation" name="aggregation_method" class="form-select" required>
                                        @foreach ($aggregationMethods as $key => $label)
                                            <option value="{{ $key }}" @selected(old('aggregation_method', $selected->aggregation_method) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <span class="field-help">How approved period values combine for the indicator.</span>
                                </div>
                                <div class="col-lg-5">
                                    <label class="field-label" for="indicator-org-rollup">Across Think Tanks <span class="required">*</span></label>
                                    <select id="indicator-org-rollup" name="organization_rollup_method" class="form-select" required>
                                        @foreach ($organizationRollupMethods as $key => $label)
                                            <option value="{{ $key }}" @selected(old('organization_rollup_method', $selected->organization_rollup_method) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <span class="field-help">How approved organization contributions consolidate project-wide.</span>
                                </div>
                                <div class="col-lg-2">
                                    <label class="field-label" for="indicator-display-order">Display order <span class="required">*</span></label>
                                    <input id="indicator-display-order" type="number" name="display_order" class="form-control" min="0" max="10000" value="{{ old('display_order', $selected->display_order) }}" required>
                                    <span class="field-help">Lower numbers appear first.</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="form-section-title">Operational requirements</h4>
                            <input type="hidden" name="is_cumulative" value="0">
                            <input type="hidden" name="requires_evidence" value="0">
                            <input type="hidden" name="is_active" value="0">
                            <div class="option-grid">
                                <label class="check-card">
                                    <input class="form-check-input" type="checkbox" name="is_cumulative" value="1" @checked(old('is_cumulative', $selected->is_cumulative))>
                                    <span><strong>Cumulative indicator</strong><small>Official actual includes approved results up to the selected period.</small></span>
                                </label>
                                <label class="check-card">
                                    <input class="form-check-input" type="checkbox" name="requires_evidence" value="1" @checked(old('requires_evidence', $selected->requires_evidence))>
                                    <span><strong>Evidence is required</strong><small>Submission approval is blocked when required evidence is missing.</small></span>
                                </label>
                                <label class="check-card">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $selected->is_active))>
                                    <span><strong>Indicator is active</strong><small>Active indicators remain available for current reporting and dashboards.</small></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <small>Changes affect future configuration and reporting behavior; approved historical results remain in the audit trail.</small>
                            <button class="btn btn-primary" type="submit">
                                <i class="feather-save me-1" aria-hidden="true"></i> Save indicator configuration
                            </button>
                        </div>
                    </form>
                </section>

                <section id="framework-tab-irs" class="tab-panel" role="tabpanel" data-tab-panel="irs" hidden>
                    <div class="section-heading">
                        <div>
                            <h3>Indicator Reference Sheet</h3>
                            <p>The IRS explains exactly what qualifies, how it is measured, which evidence is acceptable and who is responsible.</p>
                        </div>
                        <span class="meta-badge">Next version: {{ $nextIrsVersion }}</span>
                    </div>

                    @if ($irs)
                        <div class="current-record">
                            <span class="current-record-icon"><i class="feather-book-open" aria-hidden="true"></i></span>
                            <span>
                                <strong>Current approved reference sheet: Version {{ $irs->version }}</strong>
                                <small>Approved {{ $irs->approved_at?->format('d M Y') ?: 'date not recorded' }} &middot; Reporting frequency: {{ $irs->reporting_frequency ?: 'not specified' }}</small>
                            </span>
                            <span class="status-badge approved">Approved</span>
                        </div>
                    @else
                        <div class="current-record">
                            <span class="current-record-icon"><i class="feather-alert-circle" aria-hidden="true"></i></span>
                            <span><strong>No approved IRS is available</strong><small>Create a complete draft below, review it, then approve a controlled version.</small></span>
                            <span class="status-badge draft">Action needed</span>
                        </div>
                    @endif

                    <aside class="education-strip">
                        <i class="feather-info" aria-hidden="true"></i>
                        <div>
                            <strong>A new version never overwrites the previous one</strong>
                            <span>Use a draft while guidance is being prepared. Select Approved only after the definition, calculation, responsibilities and Means of Verification have been formally validated.</span>
                        </div>
                    </aside>

                    <form method="POST" action="{{ route('budget.me.framework.irs.store', $selected) }}">
                        @csrf
                        <input type="hidden" name="form_context" value="irs">

                        <div class="form-section">
                            <h4 class="form-section-title">1. Definition and eligibility</h4>
                            <div class="row g-3">
                                <div class="col-lg-8">
                                    <label class="field-label" for="irs-definition">Indicator definition <span class="required">*</span></label>
                                    <textarea id="irs-definition" name="definition" class="form-control" rows="4" required>{{ old('definition', $irs?->definition) }}</textarea>
                                    <span class="field-help">Give an unambiguous definition that a reporting officer can apply consistently.</span>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="irs-rationale">Rationale</label>
                                    <textarea id="irs-rationale" name="rationale" class="form-control" rows="4">{{ old('rationale', $irs?->rationale) }}</textarea>
                                    <span class="field-help">Explain why this measure matters to ATTP performance.</span>
                                </div>
                                <div class="col-lg-6">
                                    <label class="field-label" for="irs-inclusion">Inclusion criteria <span class="required">*</span></label>
                                    <textarea id="irs-inclusion" name="inclusion_criteria" class="form-control" rows="4" required>{{ old('inclusion_criteria', $irs?->inclusion_criteria) }}</textarea>
                                    <span class="field-help">List the conditions a record must satisfy before it can be counted.</span>
                                </div>
                                <div class="col-lg-6">
                                    <label class="field-label" for="irs-exclusion">Exclusion criteria <span class="required">*</span></label>
                                    <textarea id="irs-exclusion" name="exclusion_criteria" class="form-control" rows="4" required>{{ old('exclusion_criteria', $irs?->exclusion_criteria) }}</textarea>
                                    <span class="field-help">List duplicates, incomplete activities or other records that must not be counted.</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="form-section-title">2. Measurement and collection</h4>
                            <div class="row g-3">
                                <div class="col-lg-4">
                                    <label class="field-label" for="irs-unit">Unit of measurement <span class="required">*</span></label>
                                    <input id="irs-unit" name="unit_of_measurement" class="form-control" value="{{ old('unit_of_measurement', $irs?->unit_of_measurement ?? $selected->unit?->name) }}" required>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="irs-collection-frequency">Collection frequency</label>
                                    <input id="irs-collection-frequency" name="collection_frequency" class="form-control" value="{{ old('collection_frequency', $irs?->collection_frequency) }}" placeholder="Example: Continuous">
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="irs-reporting-frequency">Reporting frequency <span class="required">*</span></label>
                                    <input id="irs-reporting-frequency" name="reporting_frequency" class="form-control" value="{{ old('reporting_frequency', $irs?->reporting_frequency) }}" placeholder="Example: Semi-annual" required>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="irs-collection-method">Data collection method <span class="required">*</span></label>
                                    <textarea id="irs-collection-method" name="data_collection_method" class="form-control" rows="4" required>{{ old('data_collection_method', $irs?->data_collection_method) }}</textarea>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="irs-data-sources">Data sources <span class="required">*</span></label>
                                    <textarea id="irs-data-sources" name="data_sources" class="form-control" rows="4" required>{{ old('data_sources', $irs?->data_sources) }}</textarea>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="irs-calculation">Calculation method <span class="required">*</span></label>
                                    <textarea id="irs-calculation" name="calculation_method" class="form-control" rows="4" required>{{ old('calculation_method', $irs?->calculation_method) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="form-section-title">3. Evidence and accountability</h4>
                            <div class="row g-3">
                                <div class="col-lg-4">
                                    <label class="field-label" for="irs-mov">Means of Verification <span class="required">*</span></label>
                                    <textarea id="irs-mov" name="means_of_verification" class="form-control" rows="4" required>{{ old('means_of_verification', $irs?->means_of_verification) }}</textarea>
                                    <span class="field-help">Name the documents, records or systems that can substantiate the result.</span>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="irs-generation-responsibility">Data generation responsibility <span class="required">*</span></label>
                                    <textarea id="irs-generation-responsibility" name="data_generation_responsibility" class="form-control" rows="4" required>{{ old('data_generation_responsibility', $irs?->data_generation_responsibility) }}</textarea>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="irs-verification-responsibility">Verification responsibility <span class="required">*</span></label>
                                    <textarea id="irs-verification-responsibility" name="verification_responsibility" class="form-control" rows="4" required>{{ old('verification_responsibility', $irs?->verification_responsibility) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="form-section-title">4. Disaggregation, guidance and approval</h4>
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <label class="field-label" for="irs-disaggregation">Required disaggregation</label>
                                    <input id="irs-disaggregation" name="disaggregation" class="form-control" value="{{ old('disaggregation', collect($irs?->disaggregation)->join(', ')) }}" placeholder="Gender, country, thematic area">
                                    <span class="field-help">Separate dimensions with commas. Only include dimensions required for this indicator.</span>
                                </div>
                                <div class="col-lg-3">
                                    <label class="field-label" for="irs-approval-status">Version status <span class="required">*</span></label>
                                    <select id="irs-approval-status" name="approval_status" class="form-select" required>
                                        <option value="draft" @selected(old('approval_status') === 'draft')>Draft — continue review</option>
                                        <option value="approved" @selected(old('approval_status') === 'approved')>Approved — official guidance</option>
                                        <option value="retired" @selected(old('approval_status') === 'retired')>Retired — historical record</option>
                                    </select>
                                </div>
                                <div class="col-lg-3">
                                    <label class="field-label" for="irs-effective-from">Effective from</label>
                                    <input id="irs-effective-from" type="date" name="effective_from" class="form-control" value="{{ old('effective_from') }}">
                                </div>
                                <div class="col-12">
                                    <label class="field-label" for="irs-guidance">Additional guidance</label>
                                    <textarea id="irs-guidance" name="additional_guidance" class="form-control" rows="3">{{ old('additional_guidance', $irs?->additional_guidance) }}</textarea>
                                    <span class="field-help">Add examples, edge cases or instructions that will help Think Tank and Secretariat users report consistently.</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <small>Saving creates IRS version {{ $nextIrsVersion }}. The current and earlier versions remain available for audit and historical interpretation.</small>
                            <button class="btn btn-primary" type="submit">
                                <i class="feather-plus-circle me-1" aria-hidden="true"></i> Create IRS version {{ $nextIrsVersion }}
                            </button>
                        </div>
                    </form>
                </section>

                <section id="framework-tab-targets" class="tab-panel" role="tabpanel" data-tab-panel="targets" hidden>
                    <div class="section-heading">
                        <div>
                            <h3>Targets and allocation history</h3>
                            <p>Review project-wide targets and Think Tank allocations before creating a controlled revision.</p>
                        </div>
                        <span class="meta-badge">{{ $selected->targets->count() }} records</span>
                    </div>

                    <aside class="education-strip">
                        <i class="feather-info" aria-hidden="true"></i>
                        <div>
                            <strong>Project targets and Think Tank allocations are different</strong>
                            <span>A project target describes the overall ATTP commitment. A Think Tank allocation assigns part of that commitment to one organization. Create a new revision with a clear reason instead of replacing a previous target.</span>
                        </div>
                    </aside>

                    <div class="table-shell">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Scope</th>
                                        <th>Period</th>
                                        <th>Target</th>
                                        <th>Revision</th>
                                        <th>Effective</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($selected->targets->sortByDesc('created_at') as $target)
                                        <tr>
                                            <td>
                                                <strong>{{ str($target->target_scope)->headline() }}</strong>
                                                @if ($target->thinkTank)
                                                    <div class="text-muted small">{{ $target->thinkTank->name }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <strong>{{ $target->period_label }}</strong>
                                                <div class="text-muted small">
                                                    {{ $target->project_year ? 'Project Y'.$target->project_year : ($target->reporting_year ?: 'Year not set') }}
                                                </div>
                                            </td>
                                            <td>{{ $target->target_text ?? ($target->target_value !== null ? number_format((float) $target->target_value, 2) : '—') }}</td>
                                            <td>v{{ $target->revision }}</td>
                                            <td>{{ $target->effective_from?->format('d M Y') ?: 'Not set' }}</td>
                                            <td><span class="status-badge {{ $target->approval_status }}">{{ str($target->approval_status)->headline() }}</span></td>
                                            <td>{{ $target->revision_reason ?? $target->notes ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">
                                                <div class="empty-state">
                                                    <i class="feather-target" aria-hidden="true"></i>
                                                    <strong>No target records yet</strong>
                                                    <span>Create the first controlled target revision below.</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <details class="subform" @if ($formContext === 'targets' && $errors->any()) open @endif>
                        <summary>
                            <span><i class="feather-plus-circle me-2" aria-hidden="true"></i>Create a target allocation or revision</span>
                            <i class="feather-chevron-down" aria-hidden="true"></i>
                        </summary>
                        <div class="subform-body">
                            <form method="POST" action="{{ route('budget.me.framework.targets.store', $selected) }}" data-target-form>
                                @csrf
                                <input type="hidden" name="form_context" value="targets">
                                <div class="row g-3">
                                    <div class="col-lg-3">
                                        <label class="field-label" for="target-scope">Target scope <span class="required">*</span></label>
                                        <select id="target-scope" name="target_scope" class="form-select" required data-target-scope>
                                            <option value="project" @selected(old('target_scope', 'project') === 'project')>Project-wide target</option>
                                            <option value="think_tank" @selected(old('target_scope') === 'think_tank')>Think Tank allocation</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-5" data-think-tank-field>
                                        <label class="field-label" for="target-think-tank">Think Tank <span class="required">*</span></label>
                                        <select id="target-think-tank" name="think_tank_member_id" class="form-select" data-think-tank-select>
                                            <option value="">Select the receiving Think Tank</option>
                                            @foreach ($thinkTanks as $tank)
                                                <option value="{{ $tank->id }}" @selected(old('think_tank_member_id') === $tank->id)>{{ $tank->name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="field-help">Required only when allocating a target to one Think Tank.</span>
                                    </div>
                                    <div class="col-lg-2">
                                        <label class="field-label" for="target-project-year">Project year</label>
                                        <input id="target-project-year" type="number" name="project_year" class="form-control" min="1" max="99" value="{{ old('project_year') }}" placeholder="1">
                                    </div>
                                    <div class="col-lg-2">
                                        <label class="field-label" for="target-reporting-year">Reporting year</label>
                                        <input id="target-reporting-year" type="number" name="reporting_year" class="form-control" min="2000" max="2100" value="{{ old('reporting_year') }}" placeholder="2027">
                                    </div>
                                    <div class="col-lg-3">
                                        <label class="field-label" for="target-period-label">Period label <span class="required">*</span></label>
                                        <input id="target-period-label" name="period_label" class="form-control" value="{{ old('period_label') }}" placeholder="Y2, H1 or END" required>
                                        <span class="field-help">Use the approved framework label for this target point.</span>
                                    </div>
                                    <div class="col-lg-3">
                                        <label class="field-label" for="target-value">Numeric target</label>
                                        <input id="target-value" type="number" step="any" name="target_value" class="form-control" value="{{ old('target_value') }}">
                                    </div>
                                    <div class="col-lg-3">
                                        <label class="field-label" for="target-text">Qualitative target</label>
                                        <input id="target-text" name="target_text" class="form-control" value="{{ old('target_text') }}" placeholder="Yes or milestone wording">
                                        <span class="field-help">Enter either a numeric or qualitative target.</span>
                                    </div>
                                    <div class="col-lg-3">
                                        <label class="field-label" for="target-baseline">Baseline</label>
                                        <input id="target-baseline" name="baseline_value" class="form-control" value="{{ old('baseline_value', $selected->baseline_value) }}">
                                    </div>
                                    <div class="col-lg-3">
                                        <label class="field-label" for="target-status">Approval status <span class="required">*</span></label>
                                        <select id="target-status" name="approval_status" class="form-select" required>
                                            <option value="draft" @selected(old('approval_status') === 'draft')>Draft — under review</option>
                                            <option value="approved" @selected(old('approval_status') === 'approved')>Approved — official target</option>
                                            <option value="retired" @selected(old('approval_status') === 'retired')>Retired — historical only</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-3">
                                        <label class="field-label" for="target-effective-from">Effective from</label>
                                        <input id="target-effective-from" type="date" name="effective_from" class="form-control" value="{{ old('effective_from') }}">
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="field-label" for="target-reason">Revision reason <span class="required">*</span></label>
                                        <textarea id="target-reason" name="revision_reason" class="form-control" rows="3" required>{{ old('revision_reason') }}</textarea>
                                        <span class="field-help">Explain the approval, allocation or amendment decision clearly for the audit trail.</span>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <small>The system calculates the next revision number for the selected scope, organization and period.</small>
                                    <button class="btn btn-primary" type="submit">
                                        <i class="feather-plus-circle me-1" aria-hidden="true"></i> Create target revision
                                    </button>
                                </div>
                            </form>
                        </div>
                    </details>
                </section>

                <section id="framework-tab-calculation" class="tab-panel" role="tabpanel" data-tab-panel="calculation" hidden>
                    <div class="section-heading">
                        <div>
                            <h3>System calculation rules</h3>
                            <p>Configure which approved source indicators contribute and how qualifying records are filtered and deduplicated.</p>
                        </div>
                        <span class="meta-badge">{{ $systemCalculatedCount }} system-calculated indicators</span>
                    </div>

                    @if ($selected->reporting_source === 'system_calculated')
                        @if ($latestRule)
                            <div class="current-record">
                                <span class="current-record-icon"><i class="feather-git-branch" aria-hidden="true"></i></span>
                                <span>
                                    <strong>Current rule: {{ $latestRule->calculation_key }} &middot; Version {{ $latestRule->version }}</strong>
                                    <small>Sources: {{ collect(data_get($latestRule->configuration, 'source_indicator_codes'))->join(', ') ?: 'not specified' }} &middot; {{ $latestRule->source_type }}</small>
                                </span>
                                <span class="status-badge {{ $latestRule->is_active ? 'active' : 'draft' }}">{{ $latestRule->is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                        @endif

                        <aside class="education-strip">
                            <i class="feather-alert-circle" aria-hidden="true"></i>
                            <div>
                                <strong>Calculation rules use approved records only</strong>
                                <span>Source codes must identify existing framework indicators. Qualification filters use JSON and should be changed only when the approved IRS defines the corresponding data field and qualifying value.</span>
                            </div>
                        </aside>

                        <form method="POST" action="{{ route('budget.me.framework.calculations.store', $selected) }}">
                            @csrf
                            <input type="hidden" name="form_context" value="calculation">
                            <div class="row g-3">
                                <div class="col-lg-4">
                                    <label class="field-label" for="calculation-key">Calculation key <span class="required">*</span></label>
                                    <input id="calculation-key" name="calculation_key" class="form-control" value="{{ old('calculation_key', $latestRule?->calculation_key) }}" placeholder="pdo_policy_engagements" required>
                                    <span class="field-help">Use lowercase letters, numbers and underscores only.</span>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="calculation-source-type">Approved source type <span class="required">*</span></label>
                                    <input id="calculation-source-type" name="source_type" class="form-control" value="{{ old('source_type', $latestRule?->source_type ?? 'approved_indicator_results') }}" required>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="calculation-source-codes">Source indicator codes</label>
                                    <input id="calculation-source-codes" name="source_indicator_codes" class="form-control" value="{{ old('source_indicator_codes', collect(data_get($latestRule?->configuration, 'source_indicator_codes'))->join(', ')) }}" placeholder="INTC2.3, INTC2.5">
                                    <span class="field-help">Separate multiple codes with commas.</span>
                                </div>
                                <div class="col-lg-4">
                                    <label class="field-label" for="calculation-deduplication">Deduplication key</label>
                                    <input id="calculation-deduplication" name="deduplication_key" class="form-control" value="{{ old('deduplication_key', $latestRule?->deduplication_key) }}" placeholder="source_record_id">
                                    <span class="field-help">Prevents one operational record from being counted more than once.</span>
                                </div>
                                <div class="col-lg-5">
                                    <label class="field-label" for="calculation-filter">Qualification filter (JSON)</label>
                                    <textarea id="calculation-filter" name="qualification_filter" class="form-control" rows="3" placeholder='{"citizen_engagement":true}'>{{ old('qualification_filter', data_get($latestRule?->configuration, 'achievement_filter') ? json_encode(data_get($latestRule?->configuration, 'achievement_filter'), JSON_UNESCAPED_SLASHES) : '') }}</textarea>
                                    <span class="field-help">Leave blank when every approved source record qualifies.</span>
                                </div>
                                <div class="col-lg-3">
                                    <label class="field-label" for="calculation-effective-from">Effective from</label>
                                    <input id="calculation-effective-from" type="date" name="effective_from" class="form-control" value="{{ old('effective_from') }}">
                                </div>
                            </div>
                            <div class="form-actions">
                                <small>Saving creates a new rule version. Existing approved result records and earlier calculation versions are retained.</small>
                                <button class="btn btn-primary" type="submit">
                                    <i class="feather-plus-circle me-1" aria-hidden="true"></i> Create calculation version
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="empty-state">
                            <i class="feather-git-branch" aria-hidden="true"></i>
                            <strong>This indicator is reported directly</strong>
                            <span>
                                Calculation rules are available only when Reporting responsibility is set to
                                <b>System Calculated</b> in the Configuration tab.
                            </span>
                        </div>
                    @endif
                </section>
            @else
                <div class="empty-state py-5">
                    <i class="feather-target" aria-hidden="true"></i>
                    <strong>No official indicator is available</strong>
                    <span>Install or activate a controlled framework before administering indicators.</span>
                </div>
            @endif
        </main>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-framework-workspace]');
    if (!workspace) return;

    const tabButtons = [...workspace.querySelectorAll('[data-tab-button]')];
    const tabPanels = [...workspace.querySelectorAll('[data-tab-panel]')];
    const validTabs = tabButtons.map(button => button.dataset.tabButton);

    const activateTab = (tabName, updateHash = false) => {
        const nextTab = validTabs.includes(tabName) ? tabName : 'configuration';
        tabButtons.forEach(button => {
            const active = button.dataset.tabButton === nextTab;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.setAttribute('tabindex', active ? '0' : '-1');
        });
        tabPanels.forEach(panel => {
            panel.hidden = panel.dataset.tabPanel !== nextTab;
        });
        if (updateHash) history.replaceState(null, '', `#${nextTab}`);
    };

    tabButtons.forEach(button => {
        button.addEventListener('click', () => activateTab(button.dataset.tabButton, true));
        button.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            const currentIndex = tabButtons.indexOf(button);
            const direction = event.key === 'ArrowRight' ? 1 : -1;
            const nextButton = tabButtons[(currentIndex + direction + tabButtons.length) % tabButtons.length];
            activateTab(nextButton.dataset.tabButton, true);
            nextButton.focus();
        });
    });

    const hashTab = window.location.hash.replace('#', '');
    activateTab(validTabs.includes(hashTab) ? hashTab : workspace.dataset.initialTab);

    const search = workspace.querySelector('[data-indicator-search]');
    const group = workspace.querySelector('[data-indicator-group]');
    const indicatorItems = [...workspace.querySelectorAll('[data-indicator-item]')];
    const emptyMessage = workspace.querySelector('[data-indicator-empty]');

    const filterIndicators = () => {
        const term = (search?.value || '').trim().toLowerCase();
        const selectedGroup = group?.value || 'all';
        let visibleCount = 0;
        indicatorItems.forEach(item => {
            const matchesSearch = !term || item.dataset.search.includes(term);
            const matchesGroup = selectedGroup === 'all' || item.dataset.group === selectedGroup;
            const visible = matchesSearch && matchesGroup;
            item.hidden = !visible;
            if (visible) visibleCount += 1;
        });
        if (emptyMessage) emptyMessage.style.display = visibleCount ? 'none' : 'block';
    };

    search?.addEventListener('input', filterIndicators);
    group?.addEventListener('change', filterIndicators);

    const targetForm = workspace.querySelector('[data-target-form]');
    if (targetForm) {
        const scope = targetForm.querySelector('[data-target-scope]');
        const thinkTankField = targetForm.querySelector('[data-think-tank-field]');
        const thinkTankSelect = targetForm.querySelector('[data-think-tank-select]');
        const syncTargetScope = () => {
            const requiresThinkTank = scope.value === 'think_tank';
            thinkTankField.hidden = !requiresThinkTank;
            thinkTankSelect.disabled = !requiresThinkTank;
            thinkTankSelect.required = requiresThinkTank;
            if (!requiresThinkTank) thinkTankSelect.value = '';
        };
        scope.addEventListener('change', syncTargetScope);
        syncTargetScope();
    }
});
</script>
@endpush
