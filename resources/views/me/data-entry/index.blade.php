@extends(($isDataEntryFragment ?? false) ? 'layouts.fragment' : 'layouts.app')

@section('title', 'M&E Data Entry and Performance Tracking')
@section('lean_admin_scripts', '1')

@unless ($isDataEntryFragment ?? false)
@push('styles')
    @include('me.indicators.partials.styles')

    <style>
        .me-data-entry .me-workflow-guide {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            margin: 1rem 0;
        }

        .me-data-entry .me-workflow-guide.me-report-lifecycle {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .me-data-entry .me-workflow-step {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: .9rem;
            border: 1px solid var(--me-border);
            border-radius: .8rem;
            background: #fff;
        }

        .me-data-entry .me-workflow-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background: var(--me-green-800);
            color: #fff;
            font-size: .78rem;
            font-weight: 800;
        }

        .me-data-entry .me-workflow-step strong {
            display: block;
            color: var(--me-green-950);
            font-size: .82rem;
        }

        .me-data-entry .me-workflow-step small {
            display: block;
            margin-top: .2rem;
            color: var(--me-muted);
            line-height: 1.45;
        }

        .me-data-entry .me-tabs {
            display: flex;
            gap: .35rem;
            overflow-x: auto;
            margin-bottom: 1rem;
            padding: .35rem;
            border: 1px solid var(--me-border);
            border-radius: .8rem;
            background: #fff;
            scrollbar-width: thin;
        }

        .me-data-entry .me-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 1 0 auto;
            gap: .4rem;
            min-height: 40px;
            padding: .55rem .8rem;
            border-radius: .55rem;
            color: #4f635b;
            font-size: .78rem;
            font-weight: 750;
            text-decoration: none;
            white-space: nowrap;
        }

        .me-data-entry .me-tab:hover,
        .me-data-entry .me-tab:focus {
            background: var(--me-green-100);
            color: var(--me-green-950);
        }

        .me-data-entry .me-tab.active {
            background: var(--me-green-800);
            color: #fff;
            box-shadow: 0 5px 12px rgba(11, 92, 69, .18);
        }

        .me-data-entry .me-filter-grid {
            display: grid;
            grid-template-columns: minmax(190px, 1.25fr) minmax(150px, .8fr) minmax(130px, .6fr) auto;
            gap: .65rem;
            width: min(100%, 820px);
        }

        .me-data-entry .me-filter-grid .form-control,
        .me-data-entry .me-filter-grid .form-select,
        .me-data-entry .me-filter-grid .btn {
            min-height: 40px;
        }

        .me-data-entry .me-filter-grid.me-submission-filter-grid {
            grid-template-columns: minmax(260px, 1.35fr) minmax(170px, .8fr) minmax(150px, .65fr) auto;
            align-items: end;
            width: 100%;
            max-width: 980px;
        }

        .me-data-entry .me-filter-label {
            display: block;
            margin-bottom: .35rem;
            color: #43584f;
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .025em;
        }

        .me-data-entry .me-submission-results-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .72rem 1rem;
            border-bottom: 1px solid var(--me-border);
            background: #fbfdfc;
            color: #52665d;
            font-size: .72rem;
        }

        .me-data-entry .me-submission-results-summary strong {
            color: var(--me-green-950);
            font-size: .76rem;
        }

        .me-data-entry .me-submission-filter-cue {
            display: inline-flex;
            align-items: center;
            gap: .32rem;
            color: var(--me-green-700);
            font-weight: 750;
        }

        .me-data-entry .me-submission-table {
            min-width: 1060px;
        }

        .me-data-entry .me-submission-table tbody tr:hover {
            background: #fbfdfc;
        }

        .me-data-entry .me-submission-review {
            display: flex;
            align-items: flex-start;
            gap: .45rem;
        }

        .me-data-entry .me-submission-review i {
            margin-top: .12rem;
            color: var(--me-green-700);
        }

        .me-data-entry .me-status {
            display: inline-flex;
            align-items: center;
            gap: .28rem;
            padding: .26rem .55rem;
            border-radius: 999px;
            background: #edf2ef;
            color: #4e635a;
            font-size: .66rem;
            font-weight: 800;
            letter-spacing: .025em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .me-data-entry .me-status.open,
        .me-data-entry .me-status.active,
        .me-data-entry .me-status.published,
        .me-data-entry .me-status.reviewed,
        .me-data-entry .me-status.approved,
        .me-data-entry .me-status.validated {
            background: #dff3e9;
            color: #0b6a4c;
        }

        .me-data-entry .me-status.draft,
        .me-data-entry .me-status.not_started {
            background: #eef2f7;
            color: #556477;
        }

        .me-data-entry .me-status.submitted {
            background: #e7efff;
            color: #255ab5;
        }

        .me-data-entry .me-status.returned {
            background: #fff2dc;
            color: #9a5c00;
        }

        .me-data-entry .me-status.closed,
        .me-data-entry .me-status.archived {
            background: #f4e8e8;
            color: #8c4141;
        }

        .me-data-entry .me-record-title {
            color: var(--me-ink);
            font-size: .84rem;
            font-weight: 750;
            line-height: 1.35;
        }

        .me-data-entry .me-record-meta {
            margin-top: .25rem;
            color: var(--me-muted);
            font-size: .71rem;
            line-height: 1.45;
        }

        .me-data-entry .me-code {
            display: inline-block;
            margin-bottom: .28rem;
        }

        .me-data-entry .me-section-builder {
            --section-color: #EFF6FF;
            overflow: hidden;
            margin-bottom: 1rem;
            border: 1px solid color-mix(in srgb, var(--section-color) 64%, #cad7d1);
            border-top: 5px solid var(--section-color);
            border-radius: .9rem;
            background: #fff;
            background: color-mix(in srgb, var(--section-color) 35%, #fff);
            box-shadow: 0 5px 16px rgba(24, 62, 48, .05);
        }

        .me-data-entry .me-section-builder-header {
            padding: 1rem;
            border-bottom: 1px solid color-mix(in srgb, var(--section-color) 58%, #dce5e1);
        }

        .me-data-entry .me-section-builder-body {
            padding: 1rem;
        }

        .me-data-entry .me-section-builder .me-builder-row {
            background: rgba(255, 255, 255, .92);
        }

        .me-data-entry .me-section-number {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            margin-bottom: .65rem;
            color: var(--me-green-950);
            font-size: .72rem;
            font-weight: 850;
            letter-spacing: .035em;
            text-transform: uppercase;
        }

        .me-data-entry .me-section-color-control {
            display: flex;
            align-items: center;
            gap: .55rem;
        }

        .me-data-entry .me-section-color-control input[type="color"] {
            flex: 0 0 auto;
            width: 48px;
            height: 40px;
            padding: .2rem;
            border: 1px solid var(--me-border);
            border-radius: .5rem;
            background: #fff;
            cursor: pointer;
        }

        .me-data-entry .me-color-presets {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }

        .me-data-entry .me-color-preset {
            width: 27px;
            height: 27px;
            padding: 0;
            border: 2px solid #fff;
            border-radius: 50%;
            background: var(--preset-color);
            box-shadow: 0 0 0 1px #aebdb6;
        }

        .me-data-entry .me-color-preset.is-selected {
            box-shadow: 0 0 0 2px var(--me-green-800);
        }

        .me-data-entry .me-add-section {
            width: 100%;
            min-height: 46px;
            border: 1px dashed #78a895;
            border-radius: .75rem;
            background: #f8fcfa;
            color: var(--me-green-800);
            font-size: .78rem;
            font-weight: 800;
        }

        .me-data-entry .me-locked-section {
            overflow: hidden;
            margin-bottom: .8rem;
            border: 1px solid color-mix(in srgb, var(--section-color) 60%, #d4dfda);
            border-left: 7px solid var(--section-color);
            border-radius: .75rem;
            background: #fff;
            background: color-mix(in srgb, var(--section-color) 30%, #fff);
        }

        .me-data-entry .me-locked-section-header {
            padding: .85rem 1rem;
            border-bottom: 1px solid color-mix(in srgb, var(--section-color) 55%, #dce5e1);
        }

        .me-data-entry .me-builder-row {
            position: relative;
            margin-bottom: .8rem;
            padding: 1rem;
            border: 1px solid var(--me-border);
            border-radius: .75rem;
            background: var(--me-surface);
        }

        .me-data-entry .me-builder-row.is-dragging {
            opacity: .65;
        }

        .me-data-entry .me-builder-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .9rem;
        }

        .me-data-entry .me-builder-title {
            margin: 0;
            color: var(--me-green-950);
            font-size: .78rem;
            font-weight: 800;
        }

        .me-data-entry .me-builder-actions {
            display: flex;
            gap: .3rem;
        }

        .me-data-entry .me-builder-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            padding: 0;
        }

        .me-data-entry .me-field-settings {
            height: 100%;
            padding: .8rem;
            border: 1px solid #dce7e1;
            border-radius: .65rem;
            background: #fff;
        }

        .me-data-entry .me-field-settings-title {
            display: flex;
            align-items: center;
            gap: .35rem;
            margin-bottom: .7rem;
            color: var(--me-green-950);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .015em;
        }

        .me-data-entry .me-field-settings[hidden] {
            display: none !important;
        }

        .me-data-entry .me-template-indicator-field {
            height: 100%;
            padding: .75rem .85rem;
            border: 1px solid #d6e5de;
            border-radius: .65rem;
            background: #f8fbf9;
        }

        .me-data-entry .me-template-indicator-label {
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .me-data-entry .me-template-indicator-label i {
            color: var(--me-green-800);
        }

        .me-data-entry .me-lock-note {
            display: flex;
            gap: .65rem;
            margin-bottom: 1rem;
            padding: .8rem .9rem;
            border: 1px solid #eed9a5;
            border-radius: .65rem;
            background: #fff9e9;
            color: #765a16;
            font-size: .76rem;
            line-height: 1.5;
        }

        .me-data-entry .me-locked-field {
            padding: .8rem;
            border-bottom: 1px solid var(--me-border);
        }

        .me-data-entry .me-locked-field:last-child {
            border-bottom: 0;
        }

        .me-data-entry .me-member-picker {
            max-height: 330px;
            overflow-y: auto;
            border: 1px solid var(--me-border);
            border-radius: .65rem;
            background: #fff;
        }

        .me-data-entry .me-member-option {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            padding: .72rem .8rem;
            border-bottom: 1px solid #edf2ef;
            cursor: pointer;
        }

        .me-data-entry .me-member-option:last-child {
            border-bottom: 0;
        }

        .me-data-entry .me-member-option:hover {
            background: #f8fbf9;
        }

        .me-data-entry .me-member-option.is-hidden {
            display: none;
        }

        .me-data-entry .me-member-option .form-check-input {
            flex: 0 0 auto;
            margin-top: .2rem;
        }

        .me-data-entry .me-member-name {
            color: var(--me-ink);
            font-size: .79rem;
            font-weight: 700;
        }

        .me-data-entry .me-member-meta {
            margin-top: .12rem;
            color: var(--me-muted);
            font-size: .68rem;
        }

        .me-data-entry .me-form-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .55rem;
            margin-top: 1.1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--me-border);
        }

        .me-data-entry .me-pagination-wrap {
            padding: .85rem 1rem;
            border-top: 1px solid var(--me-border);
        }

        .me-data-entry .me-row-actions {
            flex-wrap: wrap;
        }

        .me-data-entry .me-mobile-card .me-row-actions .btn,
        .me-data-entry .me-mobile-card .me-row-actions form {
            flex: 1 1 auto;
        }

        .me-data-entry .me-mobile-card .me-row-actions form .btn {
            width: 100%;
        }

        /* Page-specific visual system: deliberately isolated from the wider admin theme. */
        .me-data-entry {
            --me-green-950: #073b4c;
            --me-green-800: #075c7a;
            --me-green-700: #08708e;
            --me-green-100: #eaf4f7;
            --me-ink: #172b35;
            --me-muted: #627680;
            --me-border: #d9e4e8;
            --me-surface: #f7f9fa;
            padding-bottom: 2.5rem;
            color: var(--me-ink);
        }

        .me-data-entry .me-hero {
            padding: clamp(1.35rem, 2.5vw, 2rem);
            border: 1px solid #cfdde2;
            border-left: 5px solid var(--me-green-800);
            border-radius: .9rem;
            background: #fff;
            box-shadow: 0 10px 28px rgba(18, 49, 63, .06);
        }

        .me-data-entry .me-hero::after {
            position: absolute;
            top: 0;
            right: 0;
            width: 210px;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(7, 92, 122, .045));
            content: '';
            pointer-events: none;
        }

        .me-data-entry .me-hero > * {
            position: relative;
            z-index: 1;
        }

        .me-data-entry .me-eyebrow {
            color: var(--me-green-700);
        }

        .me-data-entry .me-hero h1 {
            color: var(--me-green-950);
            font-size: clamp(1.45rem, 2.6vw, 2.05rem);
            letter-spacing: -.02em;
        }

        .me-data-entry .me-hero p {
            max-width: 820px;
            color: #5c707a;
            font-size: .9rem;
            line-height: 1.65;
        }

        .me-data-entry .me-primary-action {
            min-height: 42px;
            border-color: var(--me-green-800);
            background: var(--me-green-800);
            box-shadow: none;
        }

        .me-data-entry .me-report-dashboard-callout {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .85rem;
            padding: .9rem 1rem;
            border: 1px solid #d5e2e7;
            border-radius: .72rem;
            background: #f7fafb;
        }

        .me-data-entry .me-report-dashboard-callout strong,
        .me-data-entry .me-report-dashboard-callout small {
            display: block;
        }

        .me-data-entry .me-report-dashboard-callout strong {
            color: var(--me-green-950);
            font-size: .8rem;
        }

        .me-data-entry .me-report-dashboard-callout small {
            margin-top: .2rem;
            color: var(--me-muted);
            font-size: .69rem;
        }

        .me-data-entry .me-primary-action:hover,
        .me-data-entry .me-primary-action:focus-visible {
            border-color: #06475e;
            background: #06475e;
        }

        .me-data-entry .btn-success,
        .me-data-entry .btn-outline-success:hover,
        .me-data-entry .btn-outline-success:focus-visible,
        .me-data-entry .btn-outline-primary:hover,
        .me-data-entry .btn-outline-primary:focus-visible {
            border-color: var(--me-green-800);
            background: var(--me-green-800);
            color: #fff;
        }

        .me-data-entry .btn-outline-success,
        .me-data-entry .btn-outline-primary {
            border-color: #8ab2c0;
            color: var(--me-green-800);
        }

        .me-data-entry .progress-bar.bg-success {
            background-color: var(--me-green-700) !important;
        }

        .me-data-entry .me-summary-grid {
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: .75rem;
            margin: .9rem 0;
        }

        .me-data-entry .me-summary-card {
            min-height: 82px;
            padding: .9rem 1rem;
            border-color: #d9e4e8;
            border-radius: .75rem;
            box-shadow: none;
        }

        .me-data-entry .me-summary-card:hover {
            border-color: #b9cdd5;
        }

        .me-data-entry .me-summary-icon {
            width: 2.35rem;
            height: 2.35rem;
            border-radius: .6rem;
            background: var(--me-green-100);
            color: var(--me-green-800);
        }

        .me-data-entry .me-summary-value {
            color: var(--me-green-950);
            font-size: 1.2rem;
        }

        .me-data-entry .me-readiness {
            overflow: hidden;
            margin-bottom: 1rem;
            border: 1px solid #d7e3e7;
            border-radius: .8rem;
            background: #fff;
        }

        .me-data-entry .me-readiness > summary {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto auto;
            align-items: center;
            gap: .75rem;
            padding: .85rem 1rem;
            cursor: pointer;
            list-style: none;
        }

        .me-data-entry .me-readiness > summary::-webkit-details-marker {
            display: none;
        }

        .me-data-entry .me-readiness > summary:focus-visible {
            outline: 3px solid rgba(8, 112, 142, .16);
            outline-offset: -3px;
        }

        .me-data-entry .me-readiness-summary-icon,
        .me-data-entry .me-readiness-gate-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 2.1rem;
            height: 2.1rem;
            border-radius: .55rem;
            background: var(--me-green-100);
            color: var(--me-green-800);
        }

        .me-data-entry .me-readiness-summary-copy {
            min-width: 0;
        }

        .me-data-entry .me-readiness-summary-copy strong,
        .me-data-entry .me-readiness-summary-copy small {
            display: block;
        }

        .me-data-entry .me-readiness-summary-copy strong {
            color: var(--me-green-950);
            font-size: .86rem;
        }

        .me-data-entry .me-readiness-summary-copy small {
            margin-top: .15rem;
            color: var(--me-muted);
            font-size: .7rem;
        }

        .me-data-entry .me-readiness-state {
            padding: .28rem .55rem;
            border-radius: 999px;
            background: #fff1d6;
            color: #855600;
            font-size: .65rem;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .me-data-entry .me-readiness-state.is-ready {
            background: #e3f4eb;
            color: #176441;
        }

        .me-data-entry .me-readiness-chevron {
            color: #73858d;
            transition: transform .2s ease;
        }

        .me-data-entry .me-readiness[open] .me-readiness-chevron {
            transform: rotate(180deg);
        }

        .me-data-entry .me-readiness-body {
            padding: 1rem;
            border-top: 1px solid #e1eaed;
            background: #f8fafb;
        }

        .me-data-entry .me-readiness-progress {
            display: grid;
            grid-template-columns: minmax(240px, .8fr) minmax(260px, 1.2fr);
            align-items: center;
            gap: 1rem;
            margin-bottom: .9rem;
        }

        .me-data-entry .me-readiness-progress strong,
        .me-data-entry .me-readiness-progress small {
            display: block;
        }

        .me-data-entry .me-readiness-progress strong {
            color: #253d48;
            font-size: .79rem;
        }

        .me-data-entry .me-readiness-progress small {
            margin-top: .18rem;
            color: var(--me-muted);
            font-size: .68rem;
        }

        .me-data-entry .me-readiness-progress .progress {
            height: 7px;
            background: #e3eaed;
        }

        .me-data-entry .me-readiness-progress .progress-bar {
            background: var(--me-green-700);
        }

        .me-data-entry .me-readiness-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .65rem;
        }

        .me-data-entry .me-readiness-gate {
            min-width: 0;
            padding: .8rem;
            border: 1px solid #dce6e9;
            border-radius: .65rem;
            background: #fff;
        }

        .me-data-entry .me-readiness-gate.needs-action {
            border-color: #ecd9af;
        }

        .me-data-entry .me-readiness-gate-head {
            display: flex;
            align-items: flex-start;
            gap: .6rem;
        }

        .me-data-entry .me-readiness-gate-head strong,
        .me-data-entry .me-readiness-gate-head small {
            display: block;
            overflow-wrap: anywhere;
        }

        .me-data-entry .me-readiness-gate-head strong {
            color: #263e49;
            font-size: .75rem;
        }

        .me-data-entry .me-readiness-gate-head small {
            margin-top: .15rem;
            color: var(--me-green-700);
            font-size: .67rem;
            font-weight: 750;
        }

        .me-data-entry .me-readiness-gate p {
            margin: .65rem 0;
            color: var(--me-muted);
            font-size: .68rem;
            line-height: 1.45;
        }

        .me-data-entry .me-readiness-gate a {
            color: var(--me-green-800);
            font-size: .68rem;
            font-weight: 800;
            text-decoration: none;
        }

        .me-data-entry .me-readiness-gate a:hover {
            text-decoration: underline;
        }

        .me-data-entry .me-tabs {
            gap: .25rem;
            margin-bottom: .85rem;
            padding: .28rem;
            border-color: #d4e1e5;
            border-radius: .72rem;
            box-shadow: none;
        }

        .me-data-entry .me-tab {
            min-height: 42px;
            border: 1px solid transparent;
            border-radius: .5rem;
            color: #526873;
            font-size: .74rem;
        }

        .me-data-entry .me-tab:hover,
        .me-data-entry .me-tab:focus-visible {
            border-color: #c9dce3;
            background: #f2f7f9;
            color: var(--me-green-950);
        }

        .me-data-entry .me-tab.active {
            border-color: var(--me-green-800);
            background: var(--me-green-800);
            box-shadow: none;
        }

        .me-data-entry .me-workflow-guide,
        .me-data-entry .me-workflow-guide.me-report-lifecycle {
            grid-template-columns: repeat(auto-fit, minmax(205px, 1fr));
            gap: 0;
            overflow: hidden;
            margin: .85rem 0;
            border: 1px solid #d9e4e8;
            border-radius: .75rem;
            background: #fff;
        }

        .me-data-entry .me-workflow-step {
            min-width: 0;
            border: 0;
            border-right: 1px solid #e0e8eb;
            border-radius: 0;
            background: #fff;
        }

        .me-data-entry .me-workflow-step:last-child {
            border-right: 0;
        }

        .me-data-entry .me-page-guide {
            margin: .85rem 0 1rem;
            padding: 1rem;
            border: 1px solid #cfe1e7;
            border-left: 5px solid var(--me-green-800);
            border-radius: .8rem;
            background: #fff;
        }

        .me-data-entry .me-page-guide-intro {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .85rem;
            align-items: start;
        }

        .me-data-entry .me-page-guide-icon {
            display: inline-grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: .7rem;
            background: var(--me-green-100);
            color: var(--me-green-800);
            font-size: 1.05rem;
        }

        .me-data-entry .me-page-guide h2 {
            margin: .1rem 0 .3rem;
            color: var(--me-green-950);
            font-size: 1rem;
            font-weight: 800;
        }

        .me-data-entry .me-page-guide p {
            max-width: 920px;
            margin: 0;
            color: var(--me-muted);
            font-size: .78rem;
            line-height: 1.55;
        }

        .me-data-entry .me-page-guide .me-workflow-guide {
            margin-bottom: 0;
        }

        .me-data-entry .me-schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: .7rem;
        }

        .me-data-entry .me-schedule-card {
            min-width: 0;
            padding: .85rem;
            border: 1px solid var(--me-border);
            border-radius: .7rem;
            background: #fbfdfc;
        }

        .me-data-entry .me-progress-breakdown {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            margin-top: .55rem;
        }

        .me-data-entry .me-progress-chip {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            padding: .24rem .48rem;
            border-radius: 999px;
            background: #edf2ef;
            color: #4e635a;
            font-size: .65rem;
            font-weight: 800;
        }

        .me-data-entry .me-progress-chip.is-complete {
            background: #dff3e9;
            color: #0b6a4c;
        }

        .me-data-entry .me-progress-chip.is-pending {
            background: #fff2dc;
            color: #8d5807;
        }

        .me-data-entry .me-period-fix-modal .modal-content {
            overflow: hidden;
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 24px 70px rgba(7, 56, 43, .24);
        }

        body.me-period-fix-open {
            overflow: hidden;
        }

        .me-data-entry .me-period-fix-modal.is-open {
            display: block !important;
            overflow-x: hidden;
            overflow-y: auto;
            background: rgba(4, 31, 39, .58);
        }

        .me-data-entry .me-period-fix-modal.is-open .modal-dialog {
            pointer-events: auto;
        }

        .me-data-entry .me-period-fix-modal .modal-header {
            align-items: flex-start;
            padding: 1rem 1.1rem;
            border-bottom: 1px solid var(--me-border);
            background: linear-gradient(135deg, #f3fbf7, #eef7fa);
        }

        .me-data-entry .me-period-fix-title {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .7rem;
            align-items: start;
            min-width: 0;
        }

        .me-data-entry .me-period-fix-title > span {
            display: grid;
            width: 2.35rem;
            height: 2.35rem;
            place-items: center;
            border-radius: .65rem;
            background: #fff2dc;
            color: #94600c;
        }

        .me-data-entry .me-period-fix-title h2 {
            margin: 0;
            color: var(--me-green-950);
            font-size: 1rem;
            font-weight: 850;
        }

        .me-data-entry .me-period-fix-title p {
            margin: .2rem 0 0;
            color: var(--me-muted);
            font-size: .72rem;
            line-height: 1.5;
        }

        .me-data-entry .me-period-fix-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .65rem;
            margin-bottom: 1rem;
        }

        .me-data-entry .me-period-fix-fact {
            min-width: 0;
            padding: .7rem;
            border: 1px solid var(--me-border);
            border-radius: .7rem;
            background: #f8fbfa;
        }

        .me-data-entry .me-period-fix-fact small,
        .me-data-entry .me-period-fix-fact strong {
            display: block;
            overflow-wrap: anywhere;
        }

        .me-data-entry .me-period-fix-fact small {
            margin-bottom: .2rem;
            color: var(--me-muted);
            font-size: .61rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .me-data-entry .me-period-fix-fact strong {
            color: var(--me-ink);
            font-size: .73rem;
        }

        .me-data-entry .me-period-fix-note {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .6rem;
            align-items: start;
            margin-top: 1rem;
            padding: .75rem;
            border: 1px solid #cfe3d8;
            border-radius: .7rem;
            background: var(--me-green-100);
            color: #245c48;
            font-size: .7rem;
            line-height: 1.5;
        }

        .me-data-entry .me-period-fix-modal .modal-footer {
            padding: .8rem 1.1rem;
            border-top: 1px solid var(--me-border);
            background: #fbfdfc;
        }

        body.me-form-preview-open {
            overflow: hidden;
        }

        .me-data-entry .me-form-preview-modal {
            position: fixed;
            inset: 0;
            z-index: 1090;
            display: none;
            place-items: center;
            padding: 1rem;
            background: rgba(4, 31, 39, .66);
        }

        .me-data-entry .me-form-preview-modal.is-open {
            display: grid;
        }

        .me-data-entry .me-form-preview-dialog {
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            width: min(1180px, 96vw);
            max-height: 94vh;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, .35);
            border-radius: 1.15rem;
            background: #f3f7f5;
            box-shadow: 0 32px 90px rgba(3, 28, 35, .38);
        }

        .me-data-entry .me-form-preview-header {
            position: relative;
            overflow: hidden;
            padding: 1.15rem 1.25rem;
            color: #fff;
            background: linear-gradient(125deg, #063f31, #0b7656);
        }

        .me-data-entry .me-form-preview-header::after {
            position: absolute;
            top: -70px;
            right: -35px;
            width: 190px;
            height: 190px;
            border: 28px solid rgba(255, 255, 255, .075);
            border-radius: 50%;
            content: "";
        }

        .me-data-entry .me-form-preview-header-main {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .me-data-entry .me-form-preview-eyebrow {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            align-items: center;
            margin-bottom: .35rem;
            color: rgba(255, 255, 255, .72);
            font-size: .65rem;
            font-weight: 800;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .me-data-entry .me-form-preview-header h2 {
            margin: 0;
            color: #fff;
            font-size: 1.15rem;
            font-weight: 850;
        }

        .me-data-entry .me-form-preview-header p {
            max-width: 760px;
            margin: .35rem 0 0;
            color: rgba(255, 255, 255, .76);
            font-size: .74rem;
            line-height: 1.5;
        }

        .me-data-entry .me-form-preview-close {
            position: relative;
            z-index: 2;
            display: grid;
            flex: 0 0 auto;
            width: 2.25rem;
            height: 2.25rem;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: .65rem;
            color: #fff;
            background: rgba(255, 255, 255, .12);
        }

        .me-data-entry .me-form-preview-meta {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .5rem;
            margin-top: .9rem;
        }

        .me-data-entry .me-form-preview-meta div {
            min-width: 0;
            padding: .55rem .65rem;
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: .6rem;
            background: rgba(0, 0, 0, .08);
        }

        .me-data-entry .me-form-preview-meta small,
        .me-data-entry .me-form-preview-meta strong {
            display: block;
            overflow-wrap: anywhere;
        }

        .me-data-entry .me-form-preview-meta small {
            color: rgba(255, 255, 255, .58);
            font-size: .55rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .me-data-entry .me-form-preview-meta strong {
            margin-top: .12rem;
            color: #fff;
            font-size: .68rem;
        }

        .me-data-entry .me-form-preview-body {
            overflow-y: auto;
            padding: 1rem;
        }

        .me-data-entry .me-form-preview-canvas {
            width: min(900px, 100%);
            margin: 0 auto;
            overflow: hidden;
            border: 1px solid #dce8e3;
            border-radius: .9rem;
            background: #fff;
            box-shadow: 0 12px 30px rgba(18, 58, 46, .08);
        }

        .me-data-entry .me-form-preview-intro {
            padding: 1rem 1.1rem;
            border-bottom: 1px solid #e0ebe6;
            background: #fbfdfc;
        }

        .me-data-entry .me-form-preview-intro h3 {
            margin: 0;
            color: var(--me-green-950);
            font-size: .9rem;
            font-weight: 850;
        }

        .me-data-entry .me-form-preview-intro p {
            margin: .3rem 0 0;
            color: var(--me-muted);
            font-size: .7rem;
            line-height: 1.55;
        }

        .me-data-entry .me-form-preview-sections {
            display: grid;
            gap: .85rem;
            padding: 1rem;
        }

        .me-data-entry .me-preview-section {
            overflow: hidden;
            border: 1px solid #dce7e2;
            border-radius: .8rem;
            background: #fff;
        }

        .me-data-entry .me-preview-section-head {
            padding: .8rem .9rem;
            border-bottom: 1px solid #dce7e2;
            background: var(--preview-section-color, #eff6ff);
        }

        .me-data-entry .me-preview-section-head h4 {
            margin: 0;
            color: var(--me-ink);
            font-size: .8rem;
            font-weight: 850;
        }

        .me-data-entry .me-preview-section-head p {
            margin: .2rem 0 0;
            color: #566c63;
            font-size: .65rem;
            line-height: 1.45;
        }

        .me-data-entry .me-preview-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
            padding: .9rem;
        }

        .me-data-entry .me-preview-field {
            min-width: 0;
        }

        .me-data-entry .me-preview-field.is-wide {
            grid-column: 1 / -1;
        }

        .me-data-entry .me-preview-label {
            display: block;
            margin-bottom: .32rem;
            color: var(--me-ink);
            font-size: .68rem;
            font-weight: 800;
        }

        .me-data-entry .me-preview-label em {
            color: #c0392b;
            font-style: normal;
        }

        .me-data-entry .me-preview-control {
            min-height: 2.4rem;
            padding: .58rem .65rem;
            border: 1px solid #d4e0da;
            border-radius: .58rem;
            color: #819089;
            background: #fbfdfc;
            font-size: .68rem;
        }

        .me-data-entry .me-preview-control.is-textarea {
            min-height: 5rem;
        }

        .me-data-entry .me-preview-choice-list {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
        }

        .me-data-entry .me-preview-choice {
            display: inline-flex;
            gap: .35rem;
            align-items: center;
            padding: .4rem .5rem;
            border: 1px solid #dde7e2;
            border-radius: .5rem;
            color: #65776f;
            background: #fbfdfc;
            font-size: .64rem;
        }

        .me-data-entry .me-preview-choice::before {
            width: .72rem;
            height: .72rem;
            border: 1px solid #aebdb6;
            border-radius: .18rem;
            content: "";
        }

        .me-data-entry .me-preview-help {
            display: block;
            margin-top: .28rem;
            color: var(--me-muted);
            font-size: .6rem;
            line-height: 1.4;
        }

        .me-data-entry .me-form-preview-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .8rem 1rem;
            border-top: 1px solid #dce7e2;
            background: #fff;
        }

        .me-data-entry .me-form-preview-footer-note {
            color: var(--me-muted);
            font-size: .65rem;
        }

        .me-data-entry .me-form-preview-footer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        @media (max-width: 760px) {
            .me-data-entry .me-form-preview-modal {
                padding: .4rem;
            }

            .me-data-entry .me-form-preview-dialog {
                width: 100%;
                max-height: 98vh;
                border-radius: .8rem;
            }

            .me-data-entry .me-form-preview-meta,
            .me-data-entry .me-preview-fields {
                grid-template-columns: minmax(0, 1fr);
            }

            .me-data-entry .me-preview-field.is-wide {
                grid-column: auto;
            }

            .me-data-entry .me-form-preview-footer {
                align-items: stretch;
                flex-direction: column;
            }
        }

        @media (max-width: 700px) {
            .me-data-entry .me-period-fix-summary {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        .me-data-entry .me-workflow-number {
            width: 1.85rem;
            height: 1.85rem;
            background: var(--me-green-100);
            color: var(--me-green-800);
        }

        .me-data-entry .me-panel {
            overflow: hidden;
            border-color: #d7e3e7;
            border-radius: .8rem;
            box-shadow: 0 7px 22px rgba(18, 49, 63, .045);
        }

        .me-data-entry .me-panel-header {
            padding: 1rem;
            background: #fff;
        }

        .me-data-entry .me-panel-body {
            padding: 1rem;
        }

        .me-data-entry .me-form-section {
            min-width: 0;
            padding: 1rem;
            border: 1px solid #e0e8eb;
            border-radius: .7rem;
            background: #fafcfc;
        }

        .me-data-entry .me-form-section + .me-form-section {
            margin-top: .9rem;
        }

        .me-data-entry .row,
        .me-data-entry .row > *,
        .me-data-entry form,
        .me-data-entry .me-filter-grid > * {
            min-width: 0;
        }

        .me-data-entry .form-control,
        .me-data-entry .form-select {
            width: 100%;
            max-width: 100%;
            border-color: #cbd9de;
            background-color: #fff;
        }

        .me-data-entry .form-control:focus,
        .me-data-entry .form-select:focus {
            border-color: var(--me-green-700);
            box-shadow: 0 0 0 .18rem rgba(8, 112, 142, .12);
        }

        .me-data-entry .me-section-builder {
            border-color: #d9e4e8;
            border-top-width: 4px;
            background: #f9fbfb;
            box-shadow: none;
        }

        .me-data-entry .me-section-builder-header {
            background: rgba(255, 255, 255, .72);
        }

        .me-data-entry .me-builder-row {
            border-color: #dce6e9;
            background: #fff;
            box-shadow: 0 3px 10px rgba(18, 49, 63, .035);
        }

        .me-data-entry .me-builder-heading {
            padding-bottom: .7rem;
            border-bottom: 1px solid #e5ecef;
        }

        .me-data-entry .me-builder-actions .btn:disabled {
            opacity: .38;
        }

        .me-data-entry .me-member-picker {
            scrollbar-color: #8aaab6 #edf3f5;
            scrollbar-width: thin;
        }

        .me-data-entry .me-member-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .55rem;
            margin-top: .55rem;
        }

        .me-data-entry .me-member-count {
            color: var(--me-muted);
            font-size: .7rem;
            font-weight: 700;
        }

        .me-data-entry .me-member-bulk-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }

        .me-data-entry .me-register-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .65rem;
            padding: .62rem 1rem;
            border-bottom: 1px solid #dfe8eb;
            background: #f8fafb;
            color: var(--me-muted);
            font-size: .69rem;
        }

        .me-data-entry .me-register-toolbar strong {
            color: var(--me-green-950);
        }

        .me-data-entry .me-data-table-region {
            width: 100%;
            max-height: min(68vh, 720px);
            overflow: auto;
            overscroll-behavior: contain;
            scrollbar-color: #88a8b4 #edf3f5;
            scrollbar-gutter: stable;
        }

        .me-data-entry .me-data-table-region:focus-visible {
            outline: 3px solid rgba(8, 112, 142, .16);
            outline-offset: -3px;
        }

        .me-data-entry .me-data-table-region::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }

        .me-data-entry .me-data-table-region::-webkit-scrollbar-track {
            background: #edf3f5;
        }

        .me-data-entry .me-data-table-region::-webkit-scrollbar-thumb {
            border: 3px solid #edf3f5;
            border-radius: 999px;
            background: #88a8b4;
        }

        .me-data-entry .me-data-table-region .me-register-table {
            width: 100%;
            min-width: 1160px;
            table-layout: auto;
        }

        .me-data-entry .me-data-table-region .me-form-template-table {
            width: 1500px !important;
            min-width: 1500px;
            table-layout: fixed !important;
        }

        .me-data-entry .me-data-table-region .me-performance-report-table,
        .me-data-entry .me-data-table-region .me-submission-table {
            width: 1540px !important;
            min-width: 1540px;
            table-layout: fixed !important;
        }

        .me-data-entry .me-data-table-region .me-collection-table {
            width: 1500px !important;
            min-width: 1500px;
            table-layout: fixed !important;
        }

        .me-data-entry .me-form-template-table .me-form-col-template {
            width: 285px;
        }

        .me-data-entry .me-form-template-table .me-form-col-indicator {
            width: 310px;
        }

        .me-data-entry .me-form-template-table .me-form-col-portfolio {
            width: 230px;
        }

        .me-data-entry .me-form-template-table .me-form-col-ownership {
            width: 210px;
        }

        .me-data-entry .me-form-template-table .me-form-col-usage {
            width: 175px;
        }

        .me-data-entry .me-form-template-table .me-form-col-actions {
            width: 290px;
        }

        .me-data-entry .me-linked-indicator {
            min-width: 0;
            padding: .7rem .75rem;
            border: 1px solid #d6e5de;
            border-radius: .65rem;
            background: #f7fbf9;
        }

        .me-data-entry .me-linked-indicator .me-code {
            margin-bottom: .4rem;
        }

        .me-data-entry .me-linked-indicator-name {
            color: var(--me-ink);
            font-size: .76rem;
            font-weight: 750;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .me-data-entry .me-linked-indicator.is-missing {
            border-color: #efd4d4;
            background: #fff8f8;
            color: #8c4141;
        }

        .me-data-entry .me-data-table-region .me-register-table th {
            min-width: 0;
            top: 0;
            z-index: 3;
            padding: .7rem .8rem;
            background: #f4f7f8;
            color: #4e6570;
        }

        .me-data-entry .me-data-table-region .me-register-table td {
            min-width: 0;
            padding: .8rem;
            background: #fff;
            overflow-wrap: anywhere;
            word-break: normal;
            vertical-align: top;
        }

        .me-data-entry .me-data-table-region .me-register-table td > * {
            min-width: 0;
            max-width: 100%;
        }

        .me-data-entry .me-data-table-region .me-register-table th,
        .me-data-entry .me-data-table-region .me-register-table th *,
        .me-data-entry .me-data-table-region .me-register-table td,
        .me-data-entry .me-data-table-region .me-register-table td * {
            max-width: 100%;
            white-space: normal !important;
            overflow-wrap: anywhere !important;
            word-break: break-word !important;
            hyphens: auto;
        }

        .me-data-entry .me-data-table-region .me-register-table .me-code {
            display: inline-block;
            white-space: normal !important;
        }

        .me-data-entry .me-data-table-region .me-register-table tbody tr:hover td {
            background: #f8fbfc;
        }

        .me-data-entry .me-data-table-region .me-register-table th:last-child,
        .me-data-entry .me-data-table-region .me-register-table td:last-child {
            position: static;
            right: auto;
            z-index: auto;
            background: #fff;
            box-shadow: none;
        }

        .me-data-entry .me-data-table-region .me-register-table th:last-child {
            background: #f4f7f8;
        }

        .me-data-entry .me-data-table-region .me-row-actions {
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .me-data-entry .me-data-table-region .me-row-actions > * {
            flex: 1 1 110px;
            min-width: 0;
            max-width: 100%;
        }

        .me-data-entry .me-data-table-region .me-row-actions .btn {
            width: 100%;
            max-width: 100%;
            line-height: 1.25;
            white-space: normal !important;
            overflow-wrap: anywhere;
        }

        .me-data-entry .me-data-table-region .me-register-table .me-status {
            max-width: 100%;
            text-align: center;
            white-space: normal;
        }

        .me-data-entry [data-me-data-entry-fragment] {
            transition: opacity .16s ease, transform .16s ease;
        }

        .me-data-entry [data-me-data-entry-fragment].is-loading {
            opacity: .52;
            pointer-events: none;
            transform: translateY(2px);
        }

        body.me-data-entry-ajax-loading::before {
            position: fixed;
            top: 0;
            right: 0;
            z-index: 20000;
            width: 35%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #0a7b99, #42b883, transparent);
            content: '';
            animation: me-data-entry-progress 1s ease-in-out infinite;
        }

        @keyframes me-data-entry-progress {
            from { transform: translateX(-285%); }
            to { transform: translateX(285%); }
        }

        .me-data-entry .me-status.open,
        .me-data-entry .me-status.active,
        .me-data-entry .me-status.published,
        .me-data-entry .me-status.reviewed,
        .me-data-entry .me-status.approved,
        .me-data-entry .me-status.validated,
        .me-data-entry .me-status.verified,
        .me-data-entry .me-status.completed {
            background: #e1f2eb;
            color: #166344;
        }

        .me-data-entry .me-status.planned,
        .me-data-entry .me-status.under_review,
        .me-data-entry .me-status.resubmitted {
            background: #e8f1f8;
            color: #315f7d;
        }

        .me-data-entry .me-status.rejected {
            background: #f4e8e8;
            color: #8c4141;
        }

        .me-data-entry .me-form-footer {
            position: sticky;
            bottom: 0;
            z-index: 6;
            margin: 1rem -1rem -1rem;
            padding: .85rem 1rem;
            border-top-color: #d7e3e7;
            background: rgba(255, 255, 255, .96);
            backdrop-filter: blur(8px);
        }

        @media (max-width: 991.98px) {
            .me-data-entry .me-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                width: 100%;
            }

            .me-data-entry .me-filter-grid.me-submission-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .me-data-entry .me-readiness-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .me-data-entry .me-workflow-guide,
            .me-data-entry .me-workflow-guide.me-report-lifecycle {
                grid-template-columns: 1fr;
            }

            .me-data-entry .me-workflow-step {
                border-right: 0;
                border-bottom: 1px solid #e0e8eb;
            }

            .me-data-entry .me-workflow-step:last-child {
                border-bottom: 0;
            }

            .me-data-entry .me-readiness-progress,
            .me-data-entry .me-readiness-grid {
                grid-template-columns: 1fr;
            }

            .me-data-entry .me-panel-header {
                align-items: stretch;
            }

            .me-data-entry .me-report-dashboard-callout {
                align-items: stretch;
                flex-direction: column;
            }

            .me-data-entry .me-filter-grid {
                grid-template-columns: 1fr;
            }

            .me-data-entry .me-filter-grid.me-submission-filter-grid {
                grid-template-columns: 1fr;
            }

            .me-data-entry .me-submission-results-summary {
                align-items: flex-start;
                flex-direction: column;
            }

            .me-data-entry .me-form-footer {
                align-items: stretch;
                flex-direction: column-reverse;
            }

            .me-data-entry .me-form-footer .btn,
            .me-data-entry .me-form-footer .me-primary-action {
                width: 100%;
            }
        }

        @media (max-width: 575.98px) {
            .me-data-entry .me-readiness > summary {
                grid-template-columns: auto minmax(0, 1fr) auto;
            }

            .me-data-entry .me-readiness-state {
                grid-column: 2;
                justify-self: start;
            }

            .me-data-entry .me-readiness-chevron {
                grid-column: 3;
                grid-row: 1 / span 2;
            }

            .me-data-entry .me-panel-body,
            .me-data-entry .me-form-section {
                padding: .85rem;
            }

            .me-data-entry .me-form-footer {
                margin-right: -.85rem;
                margin-bottom: -.85rem;
                margin-left: -.85rem;
                padding: .8rem .85rem;
            }
        }
    </style>
@endpush
@endunless

@section('content')
    @php
        $canManage = auth()->user()->can('me.data_entry.manage') || auth()->user()->can('me.configuration.manage');
        $tabLabels = [
            'collections' => ['label' => 'Think Tanks Data Collections', 'icon' => 'feather-users'],
            'forms' => ['label' => 'Forms Generator', 'icon' => 'feather-file-plus'],
            'reports' => ['label' => 'Performance Reports', 'icon' => 'feather-bar-chart-2'],
            'submissions' => ['label' => 'Submissions', 'icon' => 'feather-send'],
        ];
        $pageGuides = [
            'collections' => [
                'title' => 'Plan and monitor what every think tank must submit',
                'summary' => 'Each row joins one indicator, its collection form, reporting deadline and assigned think tanks. Use it to see who is expected to report, who has submitted and who still needs follow-up.',
                'tips' => [
                    ['title' => 'Set the schedule', 'text' => 'Create a reporting period before opening a collection.'],
                    ['title' => 'Assign and publish', 'text' => 'Choose every think tank expected to supply data, then use Publish / Send to Think Tanks.'],
                    ['title' => 'Follow progress', 'text' => 'Compare submitted, draft and not-started counts before the due date.'],
                ],
            ],
            'forms' => [
                'title' => 'Generate the form used to collect indicator evidence',
                'summary' => 'A form defines the questions a think tank answers. Every form must have one primary performance indicator and may map numeric questions to additional indicators.',
                'tips' => [
                    ['title' => 'Choose the indicator', 'text' => 'Select the project component and the exact indicator the form will collect.'],
                    ['title' => 'Build clear questions', 'text' => 'Group questions into sections and explain what evidence respondents should provide.'],
                    ['title' => 'Publish when ready', 'text' => 'Draft forms can be edited; published forms can be used in a data collection.'],
                ],
            ],
            'reports' => [
                'title' => 'Turn approved indicator data into a performance report',
                'summary' => 'Use this page to prepare, review and approve periodic performance reports after the underlying indicator submissions and evidence are ready.',
                'tips' => [
                    ['title' => 'Prepare', 'text' => 'Select the reporting period and complete every required result section.'],
                    ['title' => 'Review', 'text' => 'Check indicator coverage, explanations and supporting documents.'],
                    ['title' => 'Approve', 'text' => 'Only approved results should be treated as official performance evidence.'],
                ],
            ],
            'submissions' => [
                'title' => 'Review every think tank assignment by indicator',
                'summary' => 'This register includes all assigned think tanks, even when they have not started. Use the indicator, period and status columns to identify missing, draft, submitted and approved returns.',
                'tips' => [
                    ['title' => 'Find the think tank', 'text' => 'Search by organization, country, indicator, form or reporting period.'],
                    ['title' => 'Check completeness', 'text' => 'Review the answer count, submission date and current workflow status.'],
                    ['title' => 'Take action', 'text' => 'Open submitted work for review and follow up on assignments that have not started.'],
                ],
            ],
        ];
        $pageGuide = $pageGuides[$tab];
        $statusChoices = match ($tab) {
            'forms' => ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'],
            'reports' => ['draft' => 'Draft', 'submitted' => 'Submitted', 'verified' => 'Verified', 'approved' => 'Approved', 'reviewed' => 'Legacy approved', 'archived' => 'Archived'],
            'submissions' => [
                'not_started' => 'Not started',
                'draft' => 'Draft',
                'submitted' => 'Submitted',
                'resubmitted' => 'Resubmitted',
                'under_review' => 'Under review',
                'returned' => 'Returned',
                'validated' => 'Validated',
                'verified' => 'Verified',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
            ],
            default => ['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed'],
        };
        $createTarget = match ($tab) {
            'forms' => ['query' => ['tab' => 'forms', 'create' => 'form'], 'label' => 'Generate a form'],
            'collections' => ['query' => ['tab' => 'collections', 'create' => 'collection'], 'label' => 'Create collection'],
            'reports' => ['href' => route('budget.me.performance-reports.create'), 'label' => 'Create report'],
            default => null,
        };
        $createHref = $createTarget
            ? ($createTarget['href'] ?? route('budget.me.rebuild.data-entry', $createTarget['query']).'#data-entry-workspace')
            : null;
    @endphp

    @unless ($isDataEntryFragment ?? false)
    <main class="me-results-framework me-data-entry nxl-container">
        <header class="me-hero">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                <div>
                    <div class="me-eyebrow"><i class="feather-edit-3" aria-hidden="true"></i> Monitoring &amp; Evaluation</div>
                    <h1>Data Entry and Performance Tracking</h1>
                    <p>
                        Use four clear work areas to generate indicator forms, collect think-tank data, monitor submissions, and prepare performance reports.
                    </p>
                </div>

                <div data-me-data-entry-hero-action>
                    @include('me.data-entry.partials.hero-actions')
                </div>
            </div>
        </header>

        <section class="me-summary-grid" aria-label="Data entry summary">
            <article class="me-summary-card">
                <span class="me-summary-icon"><i class="feather-unlock" aria-hidden="true"></i></span>
                <div>
                    <div class="me-summary-value">{{ number_format((int) ($summary['open'] ?? 0)) }}</div>
                    <div class="me-summary-label">Open collections</div>
                </div>
            </article>
            <article class="me-summary-card">
                <span class="me-summary-icon"><i class="feather-clock" aria-hidden="true"></i></span>
                <div>
                    <div class="me-summary-value">{{ number_format((int) ($summary['due_soon'] ?? 0)) }}</div>
                    <div class="me-summary-label">Due in the next 7 days</div>
                </div>
            </article>
            <article class="me-summary-card">
                <span class="me-summary-icon"><i class="feather-send" aria-hidden="true"></i></span>
                <div>
                    <div class="me-summary-value">{{ number_format((int) ($summary['submitted'] ?? 0)) }}</div>
                    <div class="me-summary-label">Submitted or reviewed</div>
                </div>
            </article>
            @if ($canManage)
                <article class="me-summary-card">
                    <span class="me-summary-icon"><i class="feather-check-circle" aria-hidden="true"></i></span>
                    <div>
                        <div class="me-summary-value">{{ (int) ($reportingReadiness['percentage'] ?? 0) }}%</div>
                        <div class="me-summary-label">Reporting readiness</div>
                    </div>
                </article>
            @endif
        </section>

        @if ($canManage)
            <details class="me-readiness">
                <summary>
                    <span class="me-readiness-summary-icon"><i class="feather-shield" aria-hidden="true"></i></span>
                    <span class="me-readiness-summary-copy">
                        <strong id="reporting-readiness-title">Think-tank reporting readiness</strong>
                        <small>{{ number_format((int) ($reportingReadiness['completed'] ?? 0)) }} of {{ number_format((int) ($reportingReadiness['total'] ?? 0)) }} setup controls complete</small>
                    </span>
                    <span class="me-readiness-state {{ ($reportingReadiness['ready'] ?? false) ? 'is-ready' : 'needs-action' }}">
                        {{ ($reportingReadiness['ready'] ?? false) ? 'Ready' : 'Action required' }}
                    </span>
                    <i class="feather-chevron-down me-readiness-chevron" aria-hidden="true"></i>
                </summary>
                <div class="me-readiness-body" aria-labelledby="reporting-readiness-title">
                    <div class="me-readiness-progress">
                        <div>
                            <strong>Commissioning checklist</strong>
                            <small>Live controls from the current database. Complete every gate before reporting begins.</small>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Reporting readiness" aria-valuenow="{{ (int) ($reportingReadiness['percentage'] ?? 0) }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ (int) ($reportingReadiness['percentage'] ?? 0) }}%"></div>
                        </div>
                    </div>
                    <div class="me-readiness-grid">
                        @foreach (($reportingReadiness['gates'] ?? []) as $readinessGate)
                            <article class="me-readiness-gate {{ $readinessGate['complete'] ? 'is-complete' : 'needs-action' }}">
                                <div class="me-readiness-gate-head">
                                    <span class="me-readiness-gate-icon"><i class="{{ $readinessGate['complete'] ? 'feather-check' : 'feather-alert-circle' }}" aria-hidden="true"></i></span>
                                    <div>
                                        <strong>{{ $readinessGate['label'] }}</strong>
                                        <small>{{ $readinessGate['value'] }}</small>
                                    </div>
                                </div>
                                <p>{{ $readinessGate['detail'] }}</p>
                                <a href="{{ route($readinessGate['route'], $readinessGate['query']) }}">
                                    {{ $readinessGate['action'] }} <i class="feather-arrow-right" aria-hidden="true"></i>
                                </a>
                            </article>
                        @endforeach
                    </div>
                </div>
            </details>
        @endif

    @endunless

        <div
            data-me-data-entry-fragment
            data-page-title="{{ $tabLabels[$tab]['label'] }} | M&amp;E Data Entry and Performance Tracking"
        >
        <template data-me-data-entry-hero-action-template>
            @include('me.data-entry.partials.hero-actions')
        </template>
        @if ($tab === 'reports')
            <div class="me-report-dashboard-callout">
                <div>
                    <strong>Reporting performance dashboard</strong>
                    <small>Analyze workflow distribution, deadlines, review time and indicator completeness, then drill into report records.</small>
                </div>
                <a href="{{ route('budget.me.rebuild.reporting-dashboard') }}" class="btn btn-success flex-shrink-0">
                    <i class="feather-bar-chart-2 me-1" aria-hidden="true"></i>Open dashboard
                </a>
            </div>
        @endif

        <section class="me-page-guide" aria-labelledby="me-page-guide-title">
            <div class="me-page-guide-intro">
                <span class="me-page-guide-icon"><i class="feather-help-circle" aria-hidden="true"></i></span>
                <div>
                    <div class="me-eyebrow">How to use this page</div>
                    <h2 id="me-page-guide-title">{{ $pageGuide['title'] }}</h2>
                    <p>{{ $pageGuide['summary'] }}</p>
                </div>
            </div>
            <div class="me-workflow-guide" aria-label="Page guidance">
                @foreach ($pageGuide['tips'] as $tipIndex => $tip)
                    <article class="me-workflow-step">
                        <span class="me-workflow-number">{{ $tipIndex + 1 }}</span>
                        <div><strong>{{ $tip['title'] }}</strong><small>{{ $tip['text'] }}</small></div>
                    </article>
                @endforeach
            </div>
        </section>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm" role="status">
                <i class="feather-check-circle me-2" aria-hidden="true"></i>{{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger border-0 shadow-sm" role="alert">
                <i class="feather-alert-triangle me-2" aria-hidden="true"></i>{{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm" role="alert" tabindex="-1" id="data-entry-validation-summary">
                <div class="fw-bold mb-2"><i class="feather-alert-triangle me-1" aria-hidden="true"></i> Please correct the information below.</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <nav class="me-tabs" aria-label="Data entry sections">
            @foreach ($tabLabels as $tabKey => $tabItem)
                <a
                    href="{{ route('budget.me.rebuild.data-entry', ['tab' => $tabKey]) }}"
                    class="me-tab {{ $tab === $tabKey ? 'active' : '' }}"
                    @if ($tab === $tabKey) aria-current="page" @endif
                >
                    <i class="{{ $tabItem['icon'] }}" aria-hidden="true"></i>{{ $tabItem['label'] }}
                </a>
            @endforeach
        </nav>

        <div id="data-entry-workspace">
            @if ($canManage && $showFormBuilder)
                @php
                    $fieldTypeGroups = [
                        'Number' => [
                            'integer' => 'Integer',
                            'number' => 'Number',
                            'percentage' => 'Percentage',
                            'currency' => 'Currency',
                        ],
                        'Text / contact' => [
                            'text' => 'Short text',
                            'textarea' => 'Long text',
                            'email' => 'Email',
                            'phone' => 'Phone',
                            'url' => 'URL',
                        ],
                        'Date / time' => [
                            'date' => 'Date',
                            'time' => 'Time',
                            'datetime' => 'Date and time',
                            'month' => 'Month',
                            'year' => 'Year',
                        ],
                        'Choice' => [
                            'select' => 'Dropdown (single choice)',
                            'radio' => 'Radio',
                            'multiselect' => 'Multi-select (multiple choices)',
                            'checkbox' => 'Checkbox group',
                            'yes_no' => 'Yes / No',
                            'rating' => 'Rating',
                            'scale' => 'Scale',
                        ],
                        'Upload' => [
                            'file' => 'File upload',
                            'image' => 'Image upload',
                        ],
                    ];
                    $fieldTypeLabels = collect($fieldTypeGroups)->collapse();
                    $numericSettingTypes = ['integer', 'number', 'percentage', 'currency', 'rating', 'scale'];
                    $mappableFieldTypes = ['integer', 'number', 'percentage', 'currency'];
                    $textSettingTypes = ['text', 'textarea', 'email', 'phone', 'url'];
                    $choiceFieldTypes = ['select', 'radio', 'multiselect', 'checkbox'];
                    $uploadFieldTypes = ['file', 'image'];
                    $defaultFileExtensions = 'pdf, doc, docx, xls, xlsx, csv, txt';
                    $defaultImageExtensions = 'jpg, jpeg, png, webp, gif';
                    $sectionPalette = \App\Models\MeDataEntryFormSection::SOFT_BACKGROUND_COLORS;
                    $defaultSectionGuidance = 'Complete the questions in this section using the most accurate information available. Review your answers before continuing to the next section.';
                    $formLocked = (bool) ($editingForm
                        && $editingForm->status === \App\Models\MeDataEntryForm::STATUS_PUBLISHED
                        && $editingFormHasSubmissions);
                    $databaseSections = $editingForm
                        ? $editingForm->sections->values()->map(fn ($section) => [
                            'id' => (string) $section->id,
                            'section_key' => $section->section_key,
                            'name' => $section->name,
                            'description' => trim((string) $section->description) ?: $defaultSectionGuidance,
                            'background_color' => $section->background_color,
                            'sort_order' => $section->sort_order,
                        ])->all()
                        : [];
                    $databaseRows = $editingForm
                        ? $editingForm->sections->values()->flatMap(fn ($section) => $section->fields->values()->map(fn ($field) => [
                            'id' => (string) $field->id,
                            'field_key' => $field->field_key,
                            'section_key' => $section->section_key,
                            'section' => $section->name,
                            'label' => $field->label,
                            'field_type' => $field->field_type,
                            'is_required' => (bool) $field->is_required,
                            'help_text' => $field->help_text,
                            'options' => implode(PHP_EOL, $field->options ?? []),
                            'unit_label' => $field->unit_label,
                            'indicator_id' => $field->indicator_id ? (string) $field->indicator_id : null,
                            'validation' => is_array($field->validation) ? $field->validation : [],
                            'sort_order' => $field->sort_order,
                        ]))->values()->all()
                        : [];
                    $formSections = old('sections');
                    $formRows = old('fields');
                    if ($formLocked || !is_array($formSections)) {
                        $formSections = $editingForm ? $databaseSections : [[
                            'id' => null,
                            'section_key' => 'general_information',
                            'name' => \App\Models\MeDataEntryFormSection::DEFAULT_NAME,
                            'description' => $defaultSectionGuidance,
                            'background_color' => \App\Models\MeDataEntryFormSection::DEFAULT_COLOR,
                            'sort_order' => 10,
                        ]];
                    }
                    if ($formLocked || !is_array($formRows)) {
                        $formRows = $editingForm ? $databaseRows : [[
                            'id' => null,
                            'field_key' => null,
                            'section_key' => 'general_information',
                            'label' => '',
                            'field_type' => 'text',
                            'is_required' => true,
                            'help_text' => '',
                            'options' => '',
                            'unit_label' => '',
                            'indicator_id' => null,
                            'validation' => [],
                            'sort_order' => 10,
                        ]];
                    }
                    $formRowsBySection = collect($formRows)->groupBy(fn ($row) => (string) ($row['section_key'] ?? ''), true);
                    $knownSectionKeys = collect($formSections)->pluck('section_key')->map(fn ($key) => (string) $key);
                    $orphanRows = collect($formRows)->filter(fn ($row) => !$knownSectionKeys->contains((string) ($row['section_key'] ?? '')));
                    if ($orphanRows->isNotEmpty() && $knownSectionKeys->isNotEmpty()) {
                        $firstSectionKey = (string) $knownSectionKeys->first();
                        $formRowsBySection->put(
                            $firstSectionKey,
                            $formRowsBySection->get($firstSectionKey, collect())->merge($orphanRows)
                        );
                    }
                    $formPortfolioValue = (string) ($formLocked ? $editingForm->portfolio_id : old('portfolio_id', $editingForm?->portfolio_id));
                    $formComponentValue = (string) ($formLocked ? $editingForm->project_component_id : old('project_component_id', $editingForm?->project_component_id));
                    $formIndicatorValue = (string) ($formLocked ? $editingForm->indicator_id : old('indicator_id', $editingForm?->indicator_id));
                    $formCodeDisplay = (string) ($editingForm?->code ?? '');
                @endphp

                <section class="me-panel" aria-labelledby="form-builder-title">
                    <div class="me-panel-header">
                        <div>
                            <h2 class="me-panel-title" id="form-builder-title">{{ $editingForm ? 'Edit form template' : 'Create form template' }}</h2>
                            <p class="me-panel-subtitle">Design the exact data fields participants will complete. Publish only when the structure is ready.</p>
                        </div>
                        @if ($editingForm)
                            <span class="me-status {{ $editingForm->status }}">{{ $editingForm->status }}</span>
                        @endif
                    </div>
                    <div class="me-panel-body">
                        @if ($formLocked)
                            <div class="me-lock-note">
                                <i class="feather-lock flex-shrink-0 mt-1" aria-hidden="true"></i>
                                <div><strong>Structure locked.</strong> This published form has submissions, so its linked indicator, portfolio, code, sections and questions are preserved. You can still update its title, description, instructions and responsible person.</div>
                            </div>
                        @else
                            <div class="me-required-note">
                                <i class="feather-info flex-shrink-0 mt-1" aria-hidden="true"></i>
                                <div>The linked indicator identifies this template in the think tank portal. Optional question-level result mapping is available only for integer, number, percentage and currency fields, using indicators from the selected portfolio.</div>
                            </div>
                        @endif

                        <form
                            method="POST"
                            action="{{ $editingForm ? route('budget.me.data-entry.forms.update', $editingForm) : route('budget.me.data-entry.forms.store') }}"
                            data-form-builder
                            data-section-palette='@json($sectionPalette)'
                        >
                            @csrf
                            @if ($editingForm)
                                @method('PUT')
                            @endif

                            <div class="me-form-section">
                                <div class="me-form-section-title">Template details</div>
                                <div class="row g-3">
                                    <div class="col-lg-4">
                                        <label class="form-label" for="form-portfolio">Portfolio <span class="text-danger">*</span></label>
                                        @if ($formLocked)
                                            <input type="hidden" name="portfolio_id" value="{{ $formPortfolioValue }}">
                                            <select id="form-portfolio" class="form-select" disabled data-form-portfolio>
                                                @foreach ($portfolios as $portfolio)
                                                    <option value="{{ $portfolio->id }}" @selected((string) $portfolio->id === $formPortfolioValue)>{{ $portfolio->name }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <select id="form-portfolio" name="portfolio_id" class="form-select @error('portfolio_id') is-invalid @enderror" required data-form-portfolio>
                                                <option value="">Choose portfolio</option>
                                                @foreach ($portfolios as $portfolio)
                                                    <option value="{{ $portfolio->id }}" @selected((string) $portfolio->id === $formPortfolioValue)>{{ $portfolio->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('portfolio_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        @endif
                                    </div>
                                    <div class="col-lg-4">
                                        <label class="form-label" for="form-project-component">Project Component <span class="text-danger">*</span></label>
                                        @if ($formLocked)
                                            <input type="hidden" name="project_component_id" value="{{ $formComponentValue }}">
                                        @endif
                                        <select
                                            id="form-project-component"
                                            @unless ($formLocked) name="project_component_id" required @endunless
                                            class="form-select @error('project_component_id') is-invalid @enderror"
                                            data-form-component
                                            data-locked="{{ $formLocked ? 'true' : 'false' }}"
                                            @disabled($formLocked)
                                        >
                                            <option value="">Choose project component</option>
                                            @foreach ($projectComponents as $componentOption)
                                                <option
                                                    value="{{ $componentOption['id'] }}"
                                                    data-portfolio="{{ $componentOption['portfolio_id'] }}"
                                                    data-directorate="{{ $componentOption['directorate'] }}"
                                                    @selected($formComponentValue === (string) $componentOption['id'])
                                                >{{ $componentOption['label'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('project_component_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="me-field-help" data-component-directorate>
                                            Select a component to identify its responsible Directorate.
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="me-template-indicator-field">
                                            <label class="form-label me-template-indicator-label" for="form-indicator">
                                                <i class="feather-target" aria-hidden="true"></i>
                                                Linked performance indicator <span class="text-danger">*</span>
                                            </label>
                                            @if ($formLocked)
                                                <input type="hidden" name="indicator_id" value="{{ $formIndicatorValue }}">
                                            @endif
                                            <select
                                                id="form-indicator"
                                                @unless ($formLocked) name="indicator_id" required @endunless
                                                class="form-select @error('indicator_id') is-invalid @enderror"
                                                aria-describedby="form-indicator-help"
                                                data-template-indicator
                                                data-locked="{{ $formLocked ? 'true' : 'false' }}"
                                                @disabled($formLocked)
                                            >
                                                <option value="">Choose an indicator</option>
                                                @foreach ($indicatorOptions as $indicatorOption)
                                                    <option
                                                        value="{{ $indicatorOption['id'] }}"
                                                        data-portfolio="{{ $indicatorOption['portfolio_id'] }}"
                                                        data-component="{{ $indicatorOption['project_component_id'] }}"
                                                        @selected($formIndicatorValue === (string) $indicatorOption['id'])
                                                    >{{ $indicatorOption['label'] }}{{ $indicatorOption['unit'] ? ' · '.$indicatorOption['unit'] : '' }}</option>
                                                @endforeach
                                            </select>
                                            @error('indicator_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <div id="form-indicator-help" class="me-field-help" data-template-indicator-help>
                                                Choose the indicator this template will collect evidence for. Think tanks will open this template from that indicator in their M&amp;E workspace.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <label class="form-label" for="form-code">Template code</label>
                                        <input
                                            type="text"
                                            id="form-code"
                                            class="form-control text-uppercase"
                                            value="{{ $formCodeDisplay }}"
                                            placeholder="Assigned automatically when saved"
                                            aria-describedby="form-code-help"
                                            aria-readonly="true"
                                            readonly
                                        >
                                        <div id="form-code-help" class="me-field-help">
                                            {{ $editingForm ? 'This system-generated code cannot be changed.' : 'A unique code will be generated when you save this template.' }}
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <label class="form-label" for="form-responsible">Responsible person <span class="text-danger">*</span></label>
                                        <select id="form-responsible" name="responsible_user_id" class="form-select @error('responsible_user_id') is-invalid @enderror" required>
                                            <option value="">Choose responsible person</option>
                                            @foreach ($responsibleUsers as $responsibleUser)
                                                <option value="{{ $responsibleUser->id }}" @selected((string) old('responsible_user_id', $editingForm?->responsible_user_id) === (string) $responsibleUser->id)>
                                                    {{ $responsibleUser->name }}{{ $responsibleUser->email ? ' · '.$responsibleUser->email : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('responsible_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="form-title">Form title <span class="text-danger">*</span></label>
                                        <input type="text" id="form-title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $editingForm?->title) }}" maxlength="255" required>
                                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label" for="form-description">Description</label>
                                        <textarea id="form-description" name="description" class="form-control @error('description') is-invalid @enderror" maxlength="2000" placeholder="What this form measures and when it should be used">{{ old('description', $editingForm?->description) }}</textarea>
                                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label" for="form-instructions">Participant instructions</label>
                                        <textarea id="form-instructions" name="instructions" class="form-control @error('instructions') is-invalid @enderror" maxlength="5000" placeholder="Guidance shown to assigned think tanks">{{ old('instructions', $editingForm?->instructions) }}</textarea>
                                        @error('instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="me-form-section">
                                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3">
                                    <div>
                                        <div class="me-form-section-title mb-1">Form sections and questions</div>
                                        <div class="me-field-help mt-0">Group related questions into clearly coloured sections, then arrange them in the order participants should complete them.</div>
                                    </div>
                                    @unless ($formLocked)
                                        <span class="me-status planned" data-builder-counts aria-live="polite">1 section · 1 question</span>
                                    @endunless
                                </div>

                                @if ($formLocked)
                                    <div data-section-list>
                                        @foreach ($formSections as $sectionIndex => $sectionRow)
                                            @php
                                                $sectionKey = (string) ($sectionRow['section_key'] ?? 'section_'.($sectionIndex + 1));
                                                $sectionColor = (string) ($sectionRow['background_color'] ?? \App\Models\MeDataEntryFormSection::DEFAULT_COLOR);
                                                $sectionFields = $formRowsBySection->get($sectionKey, collect());
                                            @endphp
                                            <article class="me-locked-section" style="--section-color: {{ $sectionColor }}">
                                                <input type="hidden" name="sections[{{ $sectionIndex }}][id]" value="{{ $sectionRow['id'] ?? '' }}">
                                                <input type="hidden" name="sections[{{ $sectionIndex }}][section_key]" value="{{ $sectionKey }}">
                                                <input type="hidden" name="sections[{{ $sectionIndex }}][name]" value="{{ $sectionRow['name'] ?? '' }}">
                                                <input type="hidden" name="sections[{{ $sectionIndex }}][description]" value="{{ $sectionRow['description'] ?? '' }}">
                                                <input type="hidden" name="sections[{{ $sectionIndex }}][background_color]" value="{{ $sectionColor }}">
                                                <input type="hidden" name="sections[{{ $sectionIndex }}][sort_order]" value="{{ ($sectionIndex + 1) * 10 }}">
                                                <div class="me-locked-section-header">
                                                    <div class="me-section-number"><i class="feather-layers" aria-hidden="true"></i>Section {{ $sectionIndex + 1 }}</div>
                                                    <div class="me-record-title">{{ $sectionRow['name'] ?: 'Untitled section' }}</div>
                                                    @if (!empty($sectionRow['description']))<div class="me-record-meta">{{ $sectionRow['description'] }}</div>@endif
                                                </div>
                                        @foreach ($sectionFields as $index => $row)
                                            @php
                                                $rowOptions = is_array($row['options'] ?? null) ? implode(PHP_EOL, $row['options']) : (string) ($row['options'] ?? '');
                                                $rowValidation = is_array($row['validation'] ?? null) ? $row['validation'] : [];
                                                $rowExtensions = is_array($rowValidation['allowed_extensions'] ?? null)
                                                    ? implode(', ', $rowValidation['allowed_extensions'])
                                                    : (string) ($rowValidation['allowed_extensions'] ?? '');
                                            @endphp
                                            <div class="me-locked-field">
                                                <input type="hidden" name="fields[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][field_key]" value="{{ $row['field_key'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][section_key]" value="{{ $sectionKey }}">
                                                <input type="hidden" name="fields[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][field_type]" value="{{ $row['field_type'] ?? 'text' }}">
                                                <input type="hidden" name="fields[{{ $index }}][is_required]" value="{{ !empty($row['is_required']) ? '1' : '0' }}">
                                                <input type="hidden" name="fields[{{ $index }}][help_text]" value="{{ $row['help_text'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][options]" value="{{ $rowOptions }}">
                                                <input type="hidden" name="fields[{{ $index }}][unit_label]" value="{{ $row['unit_label'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][indicator_id]" value="{{ $row['indicator_id'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][sort_order]" value="{{ ($index + 1) * 10 }}">
                                                <input type="hidden" name="fields[{{ $index }}][validation][min]" value="{{ $rowValidation['min'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][validation][max]" value="{{ $rowValidation['max'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][validation][step]" value="{{ $rowValidation['step'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][validation][min_length]" value="{{ $rowValidation['min_length'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][validation][max_length]" value="{{ $rowValidation['max_length'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][validation][allowed_extensions]" value="{{ $rowExtensions }}">
                                                <input type="hidden" name="fields[{{ $index }}][validation][max_file_size_mb]" value="{{ $rowValidation['max_file_size_mb'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][validation][multiple]" value="{{ !empty($rowValidation['multiple']) ? '1' : '0' }}">

                                                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                                    <div>
                                                        <div class="me-record-title">{{ $row['label'] ?: 'Untitled question' }}</div>
                                                        <div class="me-record-meta">
                                                            {{ $fieldTypeLabels[$row['field_type'] ?? 'text'] ?? ucfirst(str_replace('_', ' ', (string) ($row['field_type'] ?? 'text'))) }}{{ !empty($row['is_required']) ? ' · Required' : '' }}
                                                        </div>
                                                        @if ($rowExtensions !== '')
                                                            <div class="me-record-meta">Allowed: {{ $rowExtensions }} · Up to {{ $rowValidation['max_file_size_mb'] ?? 10 }} MB{{ !empty($rowValidation['multiple']) ? ' · Multiple files' : '' }}</div>
                                                        @endif
                                                    </div>
                                                    @if (!empty($row['indicator_id']))
                                                        @php $mappedIndicator = $indicatorOptions->firstWhere('id', (string) $row['indicator_id']); @endphp
                                                        <span class="me-chip"><i class="feather-link" aria-hidden="true"></i>{{ $mappedIndicator['label'] ?? 'Mapped indicator' }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                            </article>
                                        @endforeach
                                    </div>
                                @else
                                    <div data-section-list>
                                        @foreach ($formSections as $sectionIndex => $sectionRow)
                                            @php
                                                $sectionKey = (string) ($sectionRow['section_key'] ?? 'section_'.($sectionIndex + 1));
                                                $sectionColor = (string) ($sectionRow['background_color'] ?? $sectionPalette[$sectionIndex % count($sectionPalette)]);
                                                $sectionFields = $formRowsBySection->get($sectionKey, collect());
                                            @endphp
                                            <article class="me-section-builder" data-section-card style="--section-color: {{ $sectionColor }}">
                                                <div class="me-section-builder-header">
                                                    <div class="d-flex align-items-start justify-content-between gap-3">
                                                        <div class="me-section-number"><i class="feather-layers" aria-hidden="true"></i>Section <span data-section-number>{{ $sectionIndex + 1 }}</span></div>
                                                        <div class="me-builder-actions">
                                                            <button type="button" class="btn btn-sm btn-light border" title="Move section up" aria-label="Move section up" data-move-section="up"><i class="feather-arrow-up" aria-hidden="true"></i></button>
                                                            <button type="button" class="btn btn-sm btn-light border" title="Move section down" aria-label="Move section down" data-move-section="down"><i class="feather-arrow-down" aria-hidden="true"></i></button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Remove section" aria-label="Remove section" data-remove-section><i class="feather-trash-2" aria-hidden="true"></i></button>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="sections[{{ $sectionIndex }}][id]" value="{{ $sectionRow['id'] ?? '' }}">
                                                    <input type="hidden" name="sections[{{ $sectionIndex }}][section_key]" value="{{ $sectionKey }}" data-section-key>
                                                    <input type="hidden" name="sections[{{ $sectionIndex }}][sort_order]" value="{{ ($sectionIndex + 1) * 10 }}" data-section-sort-order>
                                                    <div class="row g-3">
                                                        <div class="col-lg-6">
                                                            <label class="form-label">Section name <span class="text-danger">*</span></label>
                                                            <input type="text" name="sections[{{ $sectionIndex }}][name]" class="form-control @error('sections.'.$sectionIndex.'.name') is-invalid @enderror" value="{{ $sectionRow['name'] ?? '' }}" maxlength="255" placeholder="e.g. Organisation profile" required data-section-name>
                                                            @error('sections.'.$sectionIndex.'.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <label class="form-label">Background colour</label>
                                                            <div class="me-section-color-control">
                                                                <input type="color" name="sections[{{ $sectionIndex }}][background_color]" value="{{ $sectionColor }}" aria-label="Section background colour" data-section-color>
                                                                <div class="me-color-presets" aria-label="Soft colour presets">
                                                                    @foreach ($sectionPalette as $presetColor)
                                                                        <button type="button" class="me-color-preset {{ strtoupper($presetColor) === strtoupper($sectionColor) ? 'is-selected' : '' }}" style="--preset-color: {{ $presetColor }}" title="Use {{ $presetColor }}" aria-label="Use colour {{ $presetColor }}" data-color-preset="{{ $presetColor }}"></button>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                            @error('sections.'.$sectionIndex.'.background_color')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="section-description-{{ $sectionIndex }}">Instructions / explanation <span class="text-danger">*</span></label>
                                                            <textarea id="section-description-{{ $sectionIndex }}" name="sections[{{ $sectionIndex }}][description]" class="form-control @error('sections.'.$sectionIndex.'.description') is-invalid @enderror" rows="3" maxlength="2000" placeholder="Explain what respondents should provide, which records to consult, and any definitions they need." required aria-describedby="section-description-help-{{ $sectionIndex }}">{{ $sectionRow['description'] ?? $defaultSectionGuidance }}</textarea>
                                                            @error('sections.'.$sectionIndex.'.description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                            <div id="section-description-help-{{ $sectionIndex }}" class="me-field-help">This text appears above the section for think-tank respondents. Give them enough guidance to answer the questions correctly.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="me-section-builder-body">
                                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                                        <div><strong class="small text-dark">Questions</strong><div class="me-field-help mt-0">Each section needs at least one question.</div></div>
                                                        <button type="button" class="btn btn-sm btn-outline-success" data-add-field><i class="feather-plus me-1" aria-hidden="true"></i>Add question</button>
                                                    </div>
                                                    <div data-section-fields>
                                        @foreach ($sectionFields as $index => $row)
                                            @php
                                                $rowType = (string) ($row['field_type'] ?? 'text');
                                                $rowOptions = is_array($row['options'] ?? null) ? implode(PHP_EOL, $row['options']) : (string) ($row['options'] ?? '');
                                                $rowValidation = is_array($row['validation'] ?? null) ? $row['validation'] : [];
                                                $rowExtensions = is_array($rowValidation['allowed_extensions'] ?? null)
                                                    ? implode(', ', $rowValidation['allowed_extensions'])
                                                    : (string) ($rowValidation['allowed_extensions'] ?? '');
                                                $rowLengthCap = match ($rowType) {
                                                    'email' => 255,
                                                    'phone' => 30,
                                                    'url' => 2048,
                                                    default => 20000,
                                                };
                                            @endphp
                                            <article class="me-builder-row" data-field-row>
                                                <div class="me-builder-heading">
                                                    <h3 class="me-builder-title">Question <span data-field-number>{{ $loop->iteration }}</span></h3>
                                                    <div class="me-builder-actions">
                                                        <button type="button" class="btn btn-sm btn-light border" title="Move question up" aria-label="Move question up" data-move-field="up"><i class="feather-arrow-up" aria-hidden="true"></i></button>
                                                        <button type="button" class="btn btn-sm btn-light border" title="Move question down" aria-label="Move question down" data-move-field="down"><i class="feather-arrow-down" aria-hidden="true"></i></button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Remove question" aria-label="Remove question" data-remove-field><i class="feather-trash-2" aria-hidden="true"></i></button>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="fields[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][field_key]" value="{{ $row['field_key'] ?? '' }}">
                                                <input type="hidden" name="fields[{{ $index }}][section_key]" value="{{ $sectionKey }}" data-field-section-key>
                                                <input type="hidden" name="fields[{{ $index }}][sort_order]" value="{{ ($loop->iteration) * 10 }}" data-sort-order>
                                                <div class="row g-3">
                                                    <div class="col-lg-8">
                                                        <label class="form-label">Question <span class="text-danger">*</span></label>
                                                        <input type="text" name="fields[{{ $index }}][label]" class="form-control @error('fields.'.$index.'.label') is-invalid @enderror" value="{{ $row['label'] ?? '' }}" maxlength="255" placeholder="Question shown to participants" required>
                                                        @error('fields.'.$index.'.label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                    </div>
                                                    <div class="col-lg-4">
                                                        <label class="form-label">Answer type <span class="text-danger">*</span></label>
                                                        <select name="fields[{{ $index }}][field_type]" class="form-select @error('fields.'.$index.'.field_type') is-invalid @enderror" required data-field-type>
                                                            @foreach ($fieldTypeGroups as $groupLabel => $groupTypes)
                                                                <optgroup label="{{ $groupLabel }}">
                                                                    @foreach ($groupTypes as $typeValue => $typeLabel)
                                                                        <option value="{{ $typeValue }}" @selected($rowType === $typeValue)>{{ $typeLabel }}</option>
                                                                    @endforeach
                                                                </optgroup>
                                                            @endforeach
                                                        </select>
                                                        @error('fields.'.$index.'.field_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <label class="form-label">Help text</label>
                                                        <input type="text" name="fields[{{ $index }}][help_text]" class="form-control @error('fields.'.$index.'.help_text') is-invalid @enderror" value="{{ $row['help_text'] ?? '' }}" maxlength="1000" placeholder="Optional explanation or example">
                                                        @error('fields.'.$index.'.help_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                    </div>
                                                    <div class="col-lg-3" data-unit-wrap @if (!in_array($rowType, $mappableFieldTypes, true)) hidden @endif>
                                                        <label class="form-label">Unit label</label>
                                                        <input type="text" name="fields[{{ $index }}][unit_label]" class="form-control @error('fields.'.$index.'.unit_label') is-invalid @enderror" value="{{ $row['unit_label'] ?? '' }}" maxlength="80" placeholder="%, people, USD">
                                                        @error('fields.'.$index.'.unit_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                    </div>
                                                    <div class="col-lg-3 d-flex align-items-end">
                                                        <div class="form-check mb-2">
                                                            <input type="hidden" name="fields[{{ $index }}][is_required]" value="0">
                                                            <input class="form-check-input" type="checkbox" name="fields[{{ $index }}][is_required]" value="1" id="field-required-{{ $index }}" @checked(!empty($row['is_required']))>
                                                            <label class="form-check-label fw-semibold small" for="field-required-{{ $index }}">Required response</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6" data-numeric-settings @if (!in_array($rowType, $numericSettingTypes, true)) hidden @endif>
                                                        <div class="me-field-settings">
                                                            <div class="me-field-settings-title"><i class="feather-sliders" aria-hidden="true"></i>Numeric range and step</div>
                                                            <div class="row g-2">
                                                                <div class="col-sm-4">
                                                                    <label class="form-label">Minimum</label>
                                                                    <input type="number" @if ($rowType === 'rating') min="1" max="10" step="1" @else step="any" @endif name="fields[{{ $index }}][validation][min]" class="form-control @error('fields.'.$index.'.validation.min') is-invalid @enderror" value="{{ $rowValidation['min'] ?? '' }}" placeholder="No minimum" data-numeric-min-input>
                                                                    @error('fields.'.$index.'.validation.min')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <label class="form-label">Maximum</label>
                                                                    <input type="number" @if ($rowType === 'rating') min="1" max="10" step="1" @else step="any" @endif name="fields[{{ $index }}][validation][max]" class="form-control @error('fields.'.$index.'.validation.max') is-invalid @enderror" value="{{ $rowValidation['max'] ?? '' }}" placeholder="No maximum" data-numeric-max-input>
                                                                    @error('fields.'.$index.'.validation.max')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <label class="form-label">Step</label>
                                                                    <input type="number" @if ($rowType === 'rating') min="1" max="10" step="1" @else min="0" step="any" @endif name="fields[{{ $index }}][validation][step]" class="form-control @error('fields.'.$index.'.validation.step') is-invalid @enderror" value="{{ $rowValidation['step'] ?? '' }}" placeholder="Any" data-numeric-step-input>
                                                                    @error('fields.'.$index.'.validation.step')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-6" data-text-settings @if (!in_array($rowType, $textSettingTypes, true)) hidden @endif>
                                                        <div class="me-field-settings">
                                                            <div class="me-field-settings-title"><i class="feather-type" aria-hidden="true"></i>Text length</div>
                                                            <div class="row g-2">
                                                                <div class="col-sm-6">
                                                                    <label class="form-label">Minimum characters</label>
                                                                    <input type="number" min="0" max="{{ $rowLengthCap }}" step="1" name="fields[{{ $index }}][validation][min_length]" class="form-control @error('fields.'.$index.'.validation.min_length') is-invalid @enderror" value="{{ $rowValidation['min_length'] ?? '' }}" placeholder="No minimum" data-min-length-input>
                                                                    @error('fields.'.$index.'.validation.min_length')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                                </div>
                                                                <div class="col-sm-6">
                                                                    <label class="form-label">Maximum characters</label>
                                                                    <input type="number" min="0" max="{{ $rowLengthCap }}" step="1" name="fields[{{ $index }}][validation][max_length]" class="form-control @error('fields.'.$index.'.validation.max_length') is-invalid @enderror" value="{{ $rowValidation['max_length'] ?? '' }}" placeholder="Use portal limit" data-max-length-input>
                                                                    @error('fields.'.$index.'.validation.max_length')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                                </div>
                                                            </div>
                                                            <span class="me-field-help" data-text-limit-help>Portal hard limit: {{ number_format($rowLengthCap) }} characters. Leave maximum blank to use this limit.</span>
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-6" data-options-wrap @if (!in_array($rowType, $choiceFieldTypes, true)) hidden @endif>
                                                        <div class="me-field-settings">
                                                            <div class="me-field-settings-title"><i class="feather-list" aria-hidden="true"></i>Choice options</div>
                                                            <textarea name="fields[{{ $index }}][options]" class="form-control @error('fields.'.$index.'.options') is-invalid @enderror" rows="4" placeholder="Enter one option per line">{{ $rowOptions }}</textarea>
                                                            <span class="me-field-help" data-options-help>Select and radio require at least two options; multi-select and checkbox require at least one.</span>
                                                            @error('fields.'.$index.'.options')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                        </div>
                                                    </div>

                                                    <div class="col-12" data-upload-settings @if (!in_array($rowType, $uploadFieldTypes, true)) hidden @endif>
                                                        <div class="me-field-settings">
                                                            <div class="me-field-settings-title"><i class="feather-upload-cloud" aria-hidden="true"></i>Upload rules</div>
                                                            <div class="row g-3 align-items-end">
                                                                <div class="col-lg-6">
                                                                    <label class="form-label">Allowed file extensions</label>
                                                                    <input type="text" name="fields[{{ $index }}][validation][allowed_extensions]" class="form-control @error('fields.'.$index.'.validation.allowed_extensions') is-invalid @enderror" value="{{ $rowExtensions }}" placeholder="{{ $rowType === 'image' ? $defaultImageExtensions : $defaultFileExtensions }}" data-extension-input>
                                                                    <span class="me-field-help">Separate extensions with commas. Dots and capital letters are cleaned automatically.</span>
                                                                    @error('fields.'.$index.'.validation.allowed_extensions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                                </div>
                                                                <div class="col-lg-3 col-sm-6">
                                                                    <label class="form-label">Maximum size (MB)</label>
                                                                    <input type="number" min="1" max="50" step="1" name="fields[{{ $index }}][validation][max_file_size_mb]" class="form-control @error('fields.'.$index.'.validation.max_file_size_mb') is-invalid @enderror" value="{{ $rowValidation['max_file_size_mb'] ?? '' }}" placeholder="10" data-file-size-input>
                                                                    @error('fields.'.$index.'.validation.max_file_size_mb')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                                </div>
                                                                <div class="col-lg-3 col-sm-6">
                                                                    <div class="form-check mb-2">
                                                                        <input type="hidden" name="fields[{{ $index }}][validation][multiple]" value="0">
                                                                        <input class="form-check-input" type="checkbox" name="fields[{{ $index }}][validation][multiple]" value="1" id="field-multiple-{{ $index }}" @checked(!empty($rowValidation['multiple']))>
                                                                        <label class="form-check-label fw-semibold small" for="field-multiple-{{ $index }}">Allow multiple uploads</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-6" data-indicator-wrap @if (!in_array($rowType, $mappableFieldTypes, true)) hidden @endif>
                                                        <div class="me-field-settings">
                                                            <div class="me-field-settings-title"><i class="feather-link" aria-hidden="true"></i>Performance indicator mapping</div>
                                                            <label class="form-label">Mapped indicator</label>
                                                            <select name="fields[{{ $index }}][indicator_id]" class="form-select @error('fields.'.$index.'.indicator_id') is-invalid @enderror" data-indicator-select>
                                                                <option value="">No indicator mapping</option>
                                                                @foreach ($indicatorOptions as $indicatorOption)
                                                                    <option
                                                                        value="{{ $indicatorOption['id'] }}"
                                                                        data-portfolio="{{ $indicatorOption['portfolio_id'] }}"
                                                                        data-component="{{ $indicatorOption['project_component_id'] }}"
                                                                        @selected((string) ($row['indicator_id'] ?? '') === (string) $indicatorOption['id'])
                                                                    >{{ $indicatorOption['label'] }}{{ $indicatorOption['unit'] ? ' · '.$indicatorOption['unit'] : '' }}</option>
                                                                @endforeach
                                                            </select>
                                                            <span class="me-field-help">Available for integer, number, percentage and currency. One indicator can be mapped only once.</span>
                                                            @error('fields.'.$index.'.indicator_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </article>
                                        @endforeach
                                                    </div>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                    <button type="button" class="me-add-section" data-add-section><i class="feather-plus-circle me-1" aria-hidden="true"></i>Add another section</button>
                                @endif
                            </div>

                            <div class="me-form-footer">
                                <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'forms']) }}" class="btn btn-light border">Cancel</a>
                                <button type="submit" class="me-primary-action border-0">
                                    <i class="feather-save" aria-hidden="true"></i>{{ $editingForm ? 'Save changes' : 'Save draft form' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            @elseif ($canManage && $showPeriodForm)
                <section class="me-panel" aria-labelledby="period-form-title">
                    <div class="me-panel-header">
                        <div>
                            <h2 class="me-panel-title" id="period-form-title">{{ $editingPeriod ? 'Edit reporting period' : 'Create reporting period' }}</h2>
                            <p class="me-panel-subtitle">Periods are portfolio-specific windows used to organise one or more data collections.</p>
                        </div>
                        @if ($editingPeriod)<span class="me-status {{ $editingPeriod->lifecycle_status }}">{{ str($editingPeriod->lifecycle_status)->replace('_', ' ')->title() }}</span>@endif
                    </div>
                    <div class="me-panel-body">
                        <form method="POST" action="{{ $editingPeriod ? route('budget.me.data-entry.periods.update', $editingPeriod) : route('budget.me.data-entry.periods.store') }}" data-period-form>
                            @csrf
                            @if ($editingPeriod) @method('PUT') @endif

                            <div class="row g-3 me-form-section">
                                <div class="col-lg-6">
                                    <label class="form-label" for="period-portfolio">Portfolio <span class="text-danger">*</span></label>
                                    <select id="period-portfolio" name="portfolio_id" class="form-select @error('portfolio_id') is-invalid @enderror" required>
                                        <option value="">Choose portfolio</option>
                                        @foreach ($portfolios as $portfolio)
                                            <option value="{{ $portfolio->id }}" @selected((string) old('portfolio_id', $editingPeriod?->portfolio_id) === (string) $portfolio->id)>{{ $portfolio->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('portfolio_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label" for="period-code">Period code <span class="text-danger">*</span></label>
                                    <input type="text" id="period-code" name="code" class="form-control text-uppercase @error('code') is-invalid @enderror" value="{{ old('code', $editingPeriod?->code) }}" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" placeholder="2026-Q3" required>
                                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label" for="period-type">Period type <span class="text-danger">*</span></label>
                                    <select id="period-type" name="period_type" class="form-select @error('period_type') is-invalid @enderror" required>
                                        @foreach (['month' => 'Monthly', 'quarter' => 'Quarterly', 'semi_annual' => 'Semi-Annual', 'year' => 'Year', 'annual' => 'Annual', 'custom' => 'Custom'] as $typeValue => $typeLabel)
                                            <option value="{{ $typeValue }}" @selected(old('period_type', $editingPeriod?->period_type ?? 'quarter') === $typeValue)>{{ $typeLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('period_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-6">
                                    <label class="form-label" for="period-label">Display label <span class="text-danger">*</span></label>
                                    <input type="text" id="period-label" name="label" class="form-control @error('label') is-invalid @enderror" value="{{ old('label', $editingPeriod?->label) }}" maxlength="150" placeholder="Quarter 3, 2026" required>
                                    @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-2 col-md-4">
                                    <label class="form-label" for="period-start">Start date <span class="text-danger">*</span></label>
                                    <input type="date" id="period-start" name="period_start" class="form-control @error('period_start') is-invalid @enderror" value="{{ old('period_start', $editingPeriod?->period_start?->format('Y-m-d')) }}" required>
                                    @error('period_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-2 col-md-4">
                                    <label class="form-label" for="period-end">End date <span class="text-danger">*</span></label>
                                    <input type="date" id="period-end" name="period_end" class="form-control @error('period_end') is-invalid @enderror" value="{{ old('period_end', $editingPeriod?->period_end?->format('Y-m-d')) }}" required>
                                    @error('period_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-2 col-md-4">
                                    <label class="form-label" for="period-status">Workflow status <span class="text-danger">*</span></label>
                                    <select id="period-status" name="lifecycle_status" class="form-select @error('lifecycle_status') is-invalid @enderror" required>
                                        @foreach (['planned' => 'Planned', 'open' => 'Open', 'closed' => 'Closed', 'under_review' => 'Under review', 'completed' => 'Completed'] as $statusValue => $statusLabel)
                                            <option value="{{ $statusValue }}" @selected(old('lifecycle_status', $editingPeriod?->lifecycle_status ?? 'planned') === $statusValue)>{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('lifecycle_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-2 col-md-4">
                                    <label class="form-label" for="period-year">Reporting year <span class="text-danger">*</span></label>
                                    <input type="number" min="2000" max="2100" id="period-year" name="reporting_year" class="form-control @error('reporting_year') is-invalid @enderror" value="{{ old('reporting_year', $editingPeriod?->reporting_year ?? now()->year) }}" required>
                                    @error('reporting_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label" for="submission-opens">Submission opens</label>
                                    <input type="datetime-local" id="submission-opens" name="submission_opens_at" class="form-control @error('submission_opens_at') is-invalid @enderror" value="{{ old('submission_opens_at', $editingPeriod?->submission_opens_at?->format('Y-m-d\\TH:i')) }}">
                                    @error('submission_opens_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label" for="submission-deadline">Submission deadline</label>
                                    <input type="datetime-local" id="submission-deadline" name="submission_deadline" class="form-control @error('submission_deadline') is-invalid @enderror" value="{{ old('submission_deadline', $editingPeriod?->submission_deadline?->format('Y-m-d\\TH:i')) }}">
                                    @error('submission_deadline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label" for="review-deadline">Review deadline</label>
                                    <input type="datetime-local" id="review-deadline" name="review_deadline" class="form-control @error('review_deadline') is-invalid @enderror" value="{{ old('review_deadline', $editingPeriod?->review_deadline?->format('Y-m-d\\TH:i')) }}">
                                    @error('review_deadline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-9">
                                    <label class="form-label" for="period-instructions">Instructions</label>
                                    <textarea id="period-instructions" name="instructions" class="form-control @error('instructions') is-invalid @enderror" rows="2" maxlength="5000" placeholder="Explain submission expectations, evidence requirements, and review timing.">{{ old('instructions', $editingPeriod?->instructions) }}</textarea>
                                    @error('instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="me-form-footer">
                                <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'collections']) }}" class="btn btn-light border">Cancel</a>
                                <button type="submit" class="me-primary-action border-0"><i class="feather-save" aria-hidden="true"></i>{{ $editingPeriod ? 'Save period' : 'Create period' }}</button>
                            </div>
                        </form>
                    </div>
                </section>
            @elseif ($canManage && $showCollectionForm)
                @php
                    $collectionFormValue = (string) old('form_id', $editingCollection?->form_id);
                    $collectionPeriodValue = (string) old('reporting_period_id', $editingCollection?->reporting_period_id);
                    $selectedMemberIds = collect(old(
                        'member_ids',
                        $editingCollection ? $editingCollection->assignments->pluck('think_tank_member_id')->all() : []
                    ))->map(fn ($id) => (string) $id);
                @endphp
                <section class="me-panel" aria-labelledby="collection-form-title">
                    <div class="me-panel-header">
                        <div>
                            <h2 class="me-panel-title" id="collection-form-title">{{ $editingCollection ? 'Edit data collection' : 'Create data collection' }}</h2>
                            <p class="me-panel-subtitle">Combine one published form and one active period from the same portfolio, then assign participating think tanks.</p>
                        </div>
                        @if ($editingCollection)<span class="me-status {{ $editingCollection->status }}">{{ $editingCollection->status }}</span>@endif
                    </div>
                    <div class="me-panel-body">
                        <form method="POST" action="{{ $editingCollection ? route('budget.me.data-entry.collections.update', $editingCollection) : route('budget.me.data-entry.collections.store') }}" data-collection-form>
                            @csrf
                            @if ($editingCollection) @method('PUT') @endif

                            <div class="me-required-note">
                                <i class="feather-info flex-shrink-0 mt-1" aria-hidden="true"></i>
                                <div>Assignments with a submission cannot be removed later. Opening the collection makes it visible to assigned participants when the opening date arrives.</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <label class="form-label" for="collection-form">Published form <span class="text-danger">*</span></label>
                                    <select id="collection-form" name="form_id" class="form-select @error('form_id') is-invalid @enderror" required data-collection-template>
                                        <option value="">Choose form template</option>
                                        @foreach ($publishedForms as $publishedForm)
                                            <option value="{{ $publishedForm->id }}" data-portfolio="{{ $publishedForm->portfolio_id }}" @selected($collectionFormValue === (string) $publishedForm->id)>
                                                {{ $publishedForm->portfolio?->name }} · {{ $publishedForm->code }} — {{ $publishedForm->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('form_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-6">
                                    <label class="form-label" for="collection-period">Active reporting period <span class="text-danger">*</span></label>
                                    <select id="collection-period" name="reporting_period_id" class="form-select @error('reporting_period_id') is-invalid @enderror" required data-collection-period>
                                        <option value="">Choose reporting period</option>
                                        @foreach ($activePeriods as $activePeriod)
                                            <option value="{{ $activePeriod->id }}" data-portfolio="{{ $activePeriod->portfolio_id }}" @selected($collectionPeriodValue === (string) $activePeriod->id)>
                                                {{ $activePeriod->portfolio?->name }} · {{ $activePeriod->code }} — {{ $activePeriod->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="me-field-help" data-period-help>Select a form first to show periods from the same portfolio.</span>
                                    @error('reporting_period_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label" for="collection-opens">Opens at <span class="text-danger">*</span></label>
                                    <input type="datetime-local" id="collection-opens" name="opens_at" class="form-control @error('opens_at') is-invalid @enderror" value="{{ old('opens_at', $editingCollection?->opens_at?->format('Y-m-d\TH:i')) }}" required>
                                    @error('opens_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label" for="collection-due">Due at <span class="text-danger">*</span></label>
                                    <input type="datetime-local" id="collection-due" name="due_at" class="form-control @error('due_at') is-invalid @enderror" value="{{ old('due_at', $editingCollection?->due_at?->format('Y-m-d\TH:i')) }}" required>
                                    @error('due_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label" for="collection-closes">Closes at <span class="text-danger">*</span></label>
                                    <input type="datetime-local" id="collection-closes" name="closes_at" class="form-control @error('closes_at') is-invalid @enderror" value="{{ old('closes_at', $editingCollection?->closes_at?->format('Y-m-d\TH:i')) }}" required>
                                    @error('closes_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label" for="collection-status">Status <span class="text-danger">*</span></label>
                                    <select id="collection-status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                        <option value="draft" @selected(old('status', $editingCollection?->status ?? 'draft') === 'draft')>Draft</option>
                                        <option value="open" @selected(old('status', $editingCollection?->status ?? 'draft') === 'open')>Open</option>
                                    </select>
                                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="collection-instructions">Collection-specific instructions</label>
                                    <textarea id="collection-instructions" name="instructions" class="form-control @error('instructions') is-invalid @enderror" maxlength="5000" placeholder="Deadlines, evidence requirements or notes specific to this collection">{{ old('instructions', $editingCollection?->instructions) }}</textarea>
                                    @error('instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="me-form-section">
                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-2 mb-2">
                                    <div>
                                        <div class="me-form-section-title mb-1">Participating think tanks <span class="text-danger">*</span></div>
                                        <div class="me-field-help mt-0">Assign at least one active think tank.</div>
                                    </div>
                                    <div class="w-100" style="max-width: 330px">
                                        <label class="visually-hidden" for="member-search">Search think tanks</label>
                                        <input type="search" id="member-search" class="form-control" placeholder="Search think tanks" data-member-search>
                                    </div>
                                </div>
                                <div class="me-member-picker" data-member-list>
                                    @forelse ($availableThinkTanks as $thinkTank)
                                        <label class="me-member-option" data-member-option data-search="{{ \Illuminate\Support\Str::lower($thinkTank->name.' '.$thinkTank->country.' '.$thinkTank->consortium?->name) }}">
                                            <input class="form-check-input" type="checkbox" name="member_ids[]" value="{{ $thinkTank->id }}" @checked($selectedMemberIds->contains((string) $thinkTank->id))>
                                            <span>
                                                <span class="me-member-name">{{ $thinkTank->name }}</span>
                                                <span class="me-member-meta">{{ collect([$thinkTank->country, $thinkTank->consortium?->name])->filter()->join(' · ') ?: 'Think tank member' }}@if ($thinkTank->status !== 'active') · Existing inactive assignment @endif</span>
                                            </span>
                                        </label>
                                    @empty
                                        <div class="p-4 text-center me-muted small">No active think tanks are available for assignment.</div>
                                    @endforelse
                                </div>
                                <div class="me-member-tools">
                                    <span class="me-member-count" data-member-count aria-live="polite">0 think tanks selected</span>
                                    <div class="me-member-bulk-actions">
                                        <button type="button" class="btn btn-sm btn-light border" data-member-select-visible>Select visible</button>
                                        <button type="button" class="btn btn-sm btn-light border" data-member-clear-visible>Clear visible</button>
                                    </div>
                                </div>
                                @error('member_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                @error('member_ids.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            </div>

                            <div class="me-form-footer">
                                <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'collections']) }}" class="btn btn-light border">Cancel</a>
                                <button type="submit" class="me-primary-action border-0"><i class="feather-save" aria-hidden="true"></i>{{ $editingCollection ? 'Save collection' : 'Create collection' }}</button>
                            </div>
                        </form>
                    </div>
                </section>
            @endif

            @if ($tab === 'collections' && ! $showPeriodForm && ! $showCollectionForm)
                <section class="me-panel mb-3" aria-labelledby="reporting-schedule-title">
                    <div class="me-panel-header">
                        <div>
                            <h2 class="me-panel-title" id="reporting-schedule-title">Reporting schedule</h2>
                            <p class="me-panel-subtitle">A collection needs an open reporting period. Manage the schedule here before assigning a form to think tanks.</p>
                        </div>
                        @if ($canManage)
                            <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'collections', 'create' => 'period']) }}#data-entry-workspace" class="btn btn-sm btn-outline-success">
                                <i class="feather-calendar me-1" aria-hidden="true"></i>Add reporting period
                            </a>
                        @endif
                    </div>
                    <div class="me-panel-body">
                        <div class="me-schedule-grid">
                            @forelse ($periods as $period)
                                <article class="me-schedule-card">
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <div>
                                            <span class="me-code">{{ $period->code }}</span>
                                            <div class="me-record-title">{{ $period->label }}</div>
                                        </div>
                                        <span class="me-status {{ $period->lifecycle_status }}">{{ str($period->lifecycle_status)->replace('_', ' ')->title() }}</span>
                                    </div>
                                    <div class="me-record-meta">{{ $period->portfolio?->name ?: 'Portfolio unavailable' }}</div>
                                    <div class="me-record-meta">{{ $period->period_start?->format('d M Y') }} &mdash; {{ $period->period_end?->format('d M Y') }}</div>
                                    <div class="me-record-meta">{{ number_format((int) $period->collections_count) }} {{ \Illuminate\Support\Str::plural('collection', (int) $period->collections_count) }}</div>
                                    @if ($canManage)
                                        <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'collections', 'edit_period' => $period->id]) }}#data-entry-workspace" class="btn btn-sm btn-light border mt-2">
                                            <i class="feather-edit-2 me-1" aria-hidden="true"></i>Edit schedule
                                        </a>
                                    @endif
                                </article>
                            @empty
                                <div class="me-empty-state py-4">
                                    <h3 class="h6 fw-bold mb-1">No reporting period yet</h3>
                                    <p class="me-muted small mb-0">Create the first reporting period before opening a think-tank data collection.</p>
                                </div>
                            @endforelse
                        </div>
                        @if ($periods->hasPages())
                            <div class="me-pagination-wrap mt-3">{{ $periods->links() }}</div>
                        @endif
                    </div>
                </section>
            @endif

            @php
                $registerTitle = match ($tab) {
                    'forms' => 'Generated forms',
                    'reports' => 'Quarterly performance reports',
                    'submissions' => 'Think-tank submissions by indicator',
                    default => 'Indicator collection tracker',
                };
                $registerSubtitle = match ($tab) {
                    'forms' => 'Create, link, publish and retire reusable indicator collection forms.',
                    'reports' => 'Create, complete and review quarterly indicator performance reports.',
                    'submissions' => 'See every assigned think tank, its indicator and how far the submission has progressed.',
                    default => 'See the indicator, linked form, reporting deadline, assigned think tanks and submission progress together.',
                };
                $currentPaginator = match ($tab) {
                    'forms' => $forms,
                    'reports' => $reports,
                    'submissions' => $submissionAssignments,
                    default => $collections,
                };
                $registerSearch = $tab === 'submissions'
                    ? (string) ($submissionSearch ?? $search)
                    : (string) $search;
                $registerStatusFilter = $tab === 'submissions'
                    ? (string) ($submissionStatusFilter ?? $statusFilter)
                    : (string) $statusFilter;
                $registerHasFilters = $registerSearch !== '' || $portfolioId || $registerStatusFilter !== '';
            @endphp

            <section class="me-panel" aria-labelledby="data-entry-register-title">
                <div class="me-panel-header flex-column">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 w-100">
                        <div>
                            <h2 class="me-panel-title" id="data-entry-register-title">{{ $registerTitle }}</h2>
                            <p class="me-panel-subtitle">{{ $registerSubtitle }}</p>
                        </div>

                        @if ($canManage && $tab === 'submissions')
                            <a href="{{ route('budget.me.submission-reviews.index') }}" class="btn btn-sm btn-outline-primary flex-shrink-0">
                                <i class="feather-check-square me-1" aria-hidden="true"></i>Open full review queue
                            </a>
                        @elseif ($canManage && $createTarget && ! $showFormBuilder && ! $showPeriodForm && ! $showCollectionForm)
                            <a href="{{ $createHref }}" class="btn btn-sm btn-outline-success flex-shrink-0">
                                <i class="feather-plus me-1" aria-hidden="true"></i>{{ $createTarget['label'] }}
                            </a>
                        @endif
                    </div>

                    <form method="GET" action="{{ route('budget.me.rebuild.data-entry') }}" class="me-filter-grid {{ $tab === 'submissions' ? 'me-submission-filter-grid' : '' }}" role="search" aria-label="Filter {{ strtolower($registerTitle) }}">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <div>
                            <label class="{{ $tab === 'submissions' ? 'me-filter-label' : 'visually-hidden' }}" for="data-entry-search">{{ $tab === 'submissions' ? 'Search submissions' : 'Search '.strtolower($registerTitle) }}</label>
                            <input type="search" id="data-entry-search" name="q" class="form-control" value="{{ $registerSearch }}" placeholder="{{ $tab === 'submissions' ? 'Participant, indicator, template or period' : 'Search code, title or participant' }}">
                        </div>
                        <div>
                            <label class="{{ $tab === 'submissions' ? 'me-filter-label' : 'visually-hidden' }}" for="data-entry-portfolio-filter">{{ $tab === 'submissions' ? 'Portfolio' : 'Filter by portfolio' }}</label>
                            <select id="data-entry-portfolio-filter" name="portfolio_id" class="form-select">
                                <option value="">All portfolios</option>
                                @foreach ($portfolios as $portfolio)
                                    <option value="{{ $portfolio->id }}" @selected((string) $portfolioId === (string) $portfolio->id)>{{ $portfolio->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $tab === 'submissions' ? 'me-filter-label' : 'visually-hidden' }}" for="data-entry-status-filter">{{ $tab === 'submissions' ? 'Status' : 'Filter by status' }}</label>
                            <select id="data-entry-status-filter" name="status" class="form-select">
                                <option value="">All statuses</option>
                                @foreach ($statusChoices as $filterStatus => $filterLabel)
                                    <option value="{{ $filterStatus }}" @selected($registerStatusFilter === $filterStatus)>{{ $filterLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-outline-success flex-grow-1"><i class="feather-search me-1" aria-hidden="true"></i>{{ $tab === 'submissions' ? 'Search submissions' : 'Filter' }}</button>
                            @if ($registerHasFilters)
                                <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => $tab]) }}" class="btn btn-light border" aria-label="Clear filters"><i class="feather-x" aria-hidden="true"></i></a>
                            @endif
                        </div>
                    </form>
                </div>

                @if (! $currentPaginator || $currentPaginator->isEmpty())
                    <div class="me-empty-state">
                        <span class="me-empty-icon">
                            <i class="{{ $tabLabels[$tab]['icon'] }}" aria-hidden="true"></i>
                        </span>
                        <h3 class="h6 fw-bold mb-2">
                            @if ($tab === 'submissions')
                                {{ $registerHasFilters ? 'No assignments match these filters' : 'No think-tank indicator assignments yet' }}
                            @else
                                {{ $registerHasFilters ? 'No matching records' : 'Nothing here yet' }}
                            @endif
                        </h3>
                        <p class="me-muted small mb-3">
                            @if ($registerHasFilters)
                                {{ $tab === 'submissions' ? 'Try a different participant, indicator, template, period, portfolio or status.' : 'Try a different search term, portfolio or status.' }}
                            @elseif ($tab === 'forms')
                                Create the first form template to define what participants will report.
                            @elseif ($tab === 'reports')
                                Choose a published form, quarter and year to create the first performance report.
                            @elseif ($tab === 'submissions')
                                Assigned think tanks will appear here, including those that have not started.
                            @else
                                Publish a form and activate a period, then create the first collection.
                            @endif
                        </p>
                        @if ($canManage && $createTarget && $tab !== 'submissions' && ! $registerHasFilters)
                            <a href="{{ $createHref }}" class="me-primary-action">
                                <i class="feather-plus" aria-hidden="true"></i>{{ $createTarget['label'] }}
                            </a>
                        @endif
                    </div>
                @else
                    @if ($tab !== 'submissions')
                        <div class="me-register-toolbar" aria-live="polite">
                            <span>
                                Showing <strong>{{ number_format((int) $currentPaginator->firstItem()) }}&ndash;{{ number_format((int) $currentPaginator->lastItem()) }}</strong>
                                of <strong>{{ number_format((int) $currentPaginator->total()) }}</strong> {{ \Illuminate\Support\Str::plural('record', $currentPaginator->total()) }}
                            </span>
                            <span class="me-register-desktop"><i class="feather-move me-1" aria-hidden="true"></i>Scroll to view every column</span>
                        </div>
                    @endif

                    @if ($tab === 'forms')
                    @php
                        $formPreviewProfiles = $forms->getCollection()->mapWithKeys(function ($form) use ($canManage): array {
                            $fieldProfile = function ($field): array {
                                $options = collect($field->options ?? [])->map(function ($option): string {
                                    if (is_array($option)) {
                                        return (string) ($option['label'] ?? $option['value'] ?? '');
                                    }

                                    return trim((string) $option);
                                })->filter()->values()->all();

                                return [
                                    'id' => (string) $field->id,
                                    'label' => $field->label,
                                    'help' => $field->help_text,
                                    'type' => $field->field_type,
                                    'type_label' => str($field->field_type)->replace('_', ' ')->headline()->toString(),
                                    'unit' => $field->unit_label,
                                    'required' => (bool) $field->is_required,
                                    'options' => $options,
                                ];
                            };
                            $sections = $form->sections->map(function ($section) use ($fieldProfile): array {
                                return [
                                    'name' => $section->name,
                                    'description' => $section->description,
                                    'color' => $section->background_color ?: '#EFF6FF',
                                    'fields' => $section->fields->map($fieldProfile)->values()->all(),
                                ];
                            })->values();
                            $assignedFieldIds = $form->sections->flatMap->fields->pluck('id');
                            $orphanFields = $form->fields
                                ->reject(fn ($field) => $assignedFieldIds->contains($field->id))
                                ->map($fieldProfile)
                                ->values();
                            if ($orphanFields->isNotEmpty()) {
                                $sections->push([
                                    'name' => $sections->isEmpty() ? 'General information' : 'Additional questions',
                                    'description' => 'Complete the following questions using the most accurate information available.',
                                    'color' => '#F8FAFC',
                                    'fields' => $orphanFields->all(),
                                ]);
                            }

                            return [(string) $form->id => [
                                'code' => $form->code,
                                'title' => $form->title,
                                'description' => $form->description,
                                'instructions' => $form->instructions,
                                'status' => str($form->status)->headline()->toString(),
                                'version' => $form->version,
                                'portfolio' => $form->portfolio?->name ?: 'Portfolio unavailable',
                                'component' => $form->projectComponent?->name ?: 'Component not linked',
                                'indicator' => $form->indicator
                                    ? trim(($form->indicator->indicator_code ?: 'No code').' — '.$form->indicator->name)
                                    : 'No indicator linked',
                                'responsible' => $form->responsiblePerson?->name ?: 'Not assigned',
                                'sections' => $sections->all(),
                                'field_count' => (int) $form->fields_count,
                                'edit_url' => $canManage && $form->status !== \App\Models\MeDataEntryForm::STATUS_ARCHIVED
                                    ? route('budget.me.rebuild.data-entry', ['tab' => 'forms', 'edit_form' => $form->id]).'#data-entry-workspace'
                                    : null,
                            ]];
                        });
                    @endphp
                    <div class="table-responsive me-register-desktop me-data-table-region" role="region" aria-label="Scrollable form template register" tabindex="0">
                        <table class="table me-register-table me-form-template-table align-middle">
                            <caption class="visually-hidden">Collection form templates</caption>
                            <colgroup>
                                <col class="me-form-col-template">
                                <col class="me-form-col-indicator">
                                <col class="me-form-col-portfolio">
                                <col class="me-form-col-ownership">
                                <col class="me-form-col-usage">
                                <col class="me-form-col-actions">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Template</th>
                                    <th>Linked indicator</th>
                                    <th>Portfolio</th>
                                    <th>Ownership</th>
                                    <th>Usage</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($forms as $form)
                                    <tr>
                                        <td>
                                            <span class="me-code">{{ $form->code }}</span>
                                            <div class="me-record-title">{{ $form->title }}</div>
                                            <div class="me-record-meta">Version {{ $form->version }} · {{ \Illuminate\Support\Str::limit($form->description ?: 'No description provided', 90) }}</div>
                                        </td>
                                        <td>
                                            @if ($form->indicator)
                                                <div class="me-linked-indicator">
                                                    <span class="me-code">{{ $form->indicator->indicator_code ?: 'NO CODE' }}</span>
                                                    <div class="me-linked-indicator-name">{{ $form->indicator->name }}</div>
                                                    <div class="me-record-meta mt-1">
                                                        Unit: {{ $form->indicator->unit?->symbol ?: ($form->indicator->unit?->name ?: 'Not configured') }}
                                                        @if ((int) $form->indicators_count > 1)
                                                            &middot; {{ number_format((int) $form->indicators_count - 1) }} additional question {{ \Illuminate\Support\Str::plural('mapping', (int) $form->indicators_count - 1) }}
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                <div class="me-linked-indicator is-missing">
                                                    <div class="fw-semibold small">No indicator linked</div>
                                                    <div class="me-record-meta">Edit this template and select its performance indicator.</div>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold small text-dark">{{ $form->portfolio?->name ?: 'Portfolio unavailable' }}</div>
                                            <div class="me-record-meta mt-1">
                                                {{ $form->projectComponent?->project_id ? $form->projectComponent->project_id.' · ' : '' }}{{ $form->projectComponent?->name ?: 'Component not linked' }}
                                            </div>
                                            <span class="me-status {{ $form->status }} mt-2">{{ $form->status }}</span>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-dark">{{ $form->responsiblePerson?->name ?: 'Not assigned' }}</div>
                                            <div class="me-record-meta">{{ $form->projectComponent?->governanceNode?->name ?: 'Directorate not assigned' }}</div>
                                            <div class="me-record-meta">{{ number_format((int) $form->fields_count) }} {{ \Illuminate\Support\Str::plural('field', (int) $form->fields_count) }}</div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-dark">{{ number_format((int) $form->collections_count) }} collections</div>
                                            <div class="me-record-meta">{{ number_format((int) $form->performance_reports_count) }} performance reports</div>
                                            <div class="me-record-meta">{{ number_format((int) $form->submitted_collections_count) }} collections with submissions</div>
                                        </td>
                                        <td>
                                            <div class="me-row-actions justify-content-end mb-1">
                                                <button type="button" class="btn btn-sm btn-outline-info" data-preview-form="{{ $form->id }}" aria-haspopup="dialog" aria-controls="me-form-preview-modal" aria-label="Preview {{ $form->title }}"><i class="feather-eye" aria-hidden="true"></i> Preview form</button>
                                            </div>
                                            @if ($canManage)
                                                <div class="me-row-actions justify-content-end">
                                                    @if ($form->status !== \App\Models\MeDataEntryForm::STATUS_ARCHIVED)
                                                        <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'forms', 'edit_form' => $form->id]) }}#data-entry-workspace" class="btn btn-sm btn-light border" aria-label="Edit {{ $form->title }}"><i class="feather-edit-2" aria-hidden="true"></i> Edit</a>
                                                    @endif
                                                    @if ($form->status === \App\Models\MeDataEntryForm::STATUS_DRAFT)
                                                        <form method="POST" action="{{ route('budget.me.data-entry.forms.publish', $form) }}" data-confirm="Publish this form? It will become available for new collections.">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-success"><i class="feather-upload-cloud" aria-hidden="true"></i> Publish</button>
                                                        </form>
                                                    @elseif ($form->status !== \App\Models\MeDataEntryForm::STATUS_ARCHIVED)
                                                        <a href="{{ route('budget.me.performance-reports.create', ['form_id' => $form->id]) }}" class="btn btn-sm btn-outline-primary">
                                                            <i class="feather-bar-chart-2" aria-hidden="true"></i> Report
                                                        </a>
                                                        <form method="POST" action="{{ route('budget.me.data-entry.forms.archive', $form) }}" data-confirm="Archive this form? It cannot be used for new collections.">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Archive {{ $form->title }}"><i class="feather-archive" aria-hidden="true"></i></button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="text-end me-muted small">View only</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="me-mobile-register">
                        @foreach ($forms as $form)
                            <article class="me-mobile-card">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div><span class="me-code">{{ $form->code }}</span><h3 class="me-record-title mb-0">{{ $form->title }}</h3></div>
                                    <span class="me-status {{ $form->status }}">{{ $form->status }}</span>
                                </div>
                                <div class="me-mobile-facts">
                                    <div class="me-mobile-fact"><small>Portfolio</small><strong>{{ $form->portfolio?->name ?: 'Unavailable' }}</strong></div>
                                    <div class="me-mobile-fact"><small>Component</small><strong>{{ $form->projectComponent?->name ?: 'Not linked' }}</strong></div>
                                    <div class="me-mobile-fact">
                                        <small>Linked indicator</small>
                                        <strong>
                                            @if ($form->indicator)
                                                {{ $form->indicator->indicator_code ?: 'No code' }} &mdash; {{ $form->indicator->name }}
                                            @else
                                                No indicator linked
                                            @endif
                                        </strong>
                                    </div>
                                    <div class="me-mobile-fact"><small>Responsible</small><strong>{{ $form->responsiblePerson?->name ?: 'Not assigned' }}</strong></div>
                                    <div class="me-mobile-fact"><small>Structure</small><strong>{{ $form->fields_count }} fields · v{{ $form->version }}</strong></div>
                                    <div class="me-mobile-fact"><small>Usage</small><strong>{{ $form->collections_count }} collections · {{ $form->performance_reports_count }} reports</strong></div>
                                </div>
                                <div class="me-row-actions justify-content-start mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-info" data-preview-form="{{ $form->id }}" aria-haspopup="dialog" aria-controls="me-form-preview-modal"><i class="feather-eye me-1" aria-hidden="true"></i>Preview form</button>
                                </div>
                                @if ($canManage && $form->status !== \App\Models\MeDataEntryForm::STATUS_ARCHIVED)
                                    <div class="me-row-actions justify-content-start">
                                        <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'forms', 'edit_form' => $form->id]) }}#data-entry-workspace" class="btn btn-sm btn-light border"><i class="feather-edit-2 me-1" aria-hidden="true"></i>Edit</a>
                                        @if ($form->status === \App\Models\MeDataEntryForm::STATUS_DRAFT)
                                            <form method="POST" action="{{ route('budget.me.data-entry.forms.publish', $form) }}" data-confirm="Publish this form? It will become available for new collections.">@csrf<button type="submit" class="btn btn-sm btn-outline-success"><i class="feather-upload-cloud me-1" aria-hidden="true"></i>Publish</button></form>
                                        @else
                                            <a href="{{ route('budget.me.performance-reports.create', ['form_id' => $form->id]) }}" class="btn btn-sm btn-outline-primary"><i class="feather-bar-chart-2 me-1" aria-hidden="true"></i>Report</a>
                                            <form method="POST" action="{{ route('budget.me.data-entry.forms.archive', $form) }}" data-confirm="Archive this form? It cannot be used for new collections.">@csrf<button type="submit" class="btn btn-sm btn-outline-danger"><i class="feather-archive me-1" aria-hidden="true"></i>Archive</button></form>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    <script type="application/json" data-form-preview-data>@json($formPreviewProfiles)</script>
                    <div
                        class="me-form-preview-modal"
                        id="me-form-preview-modal"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="me-form-preview-title"
                        aria-hidden="true"
                        tabindex="-1"
                        data-form-preview-modal
                    >
                        <div class="me-form-preview-dialog">
                            <header class="me-form-preview-header">
                                <div class="me-form-preview-header-main">
                                    <div>
                                        <div class="me-form-preview-eyebrow"><span data-form-preview-code>FORM</span><span>Â·</span><span data-form-preview-status>Preview</span><span>Â·</span><span>Version <span data-form-preview-version>1</span></span></div>
                                        <h2 id="me-form-preview-title" data-form-preview-title>Form preview</h2>
                                        <p data-form-preview-description>Review how the reporting form will appear to respondents.</p>
                                    </div>
                                    <button type="button" class="me-form-preview-close" data-form-preview-close aria-label="Close form preview"><i class="feather-x" aria-hidden="true"></i></button>
                                </div>
                                <div class="me-form-preview-meta">
                                    <div><small>Linked indicator</small><strong data-form-preview-indicator>Not linked</strong></div>
                                    <div><small>Portfolio</small><strong data-form-preview-portfolio>Unavailable</strong></div>
                                    <div><small>Project component</small><strong data-form-preview-component>Unavailable</strong></div>
                                    <div><small>Responsible person</small><strong data-form-preview-responsible>Not assigned</strong></div>
                                </div>
                            </header>
                            <div class="me-form-preview-body">
                                <div class="me-form-preview-canvas">
                                    <div class="me-form-preview-intro">
                                        <h3>Instructions for the respondent</h3>
                                        <p data-form-preview-instructions>Complete every required question and review the information before submitting.</p>
                                    </div>
                                    <div class="me-form-preview-sections" data-form-preview-sections></div>
                                </div>
                            </div>
                            <footer class="me-form-preview-footer">
                                <div class="me-form-preview-footer-note"><i class="feather-eye me-1" aria-hidden="true"></i>Preview only. No information entered here will be saved.</div>
                                <div class="me-form-preview-footer-actions">
                                    <a href="#" class="btn btn-outline-primary d-none" data-form-preview-edit><i class="feather-edit-2 me-1" aria-hidden="true"></i>Edit template</a>
                                    <button type="button" class="btn btn-success" data-form-preview-close><i class="feather-check me-1" aria-hidden="true"></i>Close preview</button>
                                </div>
                            </footer>
                        </div>
                    </div>
                @elseif ($tab === 'reports')
                    <div class="table-responsive me-register-desktop me-data-table-region" role="region" aria-label="Scrollable performance report register" tabindex="0">
                        <table class="table me-register-table me-performance-report-table align-middle">
                            <caption class="visually-hidden">Quarterly performance reports</caption>
                            <colgroup>
                                <col style="width: 260px">
                                <col style="width: 220px">
                                <col style="width: 260px">
                                <col style="width: 240px">
                                <col style="width: 190px">
                                <col style="width: 160px">
                                <col style="width: 210px">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Report</th>
                                    <th>Author / owner</th>
                                    <th>Project Component</th>
                                    <th>Responsible Directorate</th>
                                    <th>Coverage</th>
                                    <th>Stage</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reports as $report)
                                    <tr>
                                        <td>
                                            <span class="me-code">{{ $report->reporting_quarter }} {{ $report->reporting_year }}</span>
                                            <div class="me-record-title">{{ $report->form?->title ?: 'Form unavailable' }}</div>
                                            <div class="me-record-meta">{{ $report->form?->code }}</div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-dark">{{ $report->thinkTank?->name ?: ($report->createdBy?->name ?: 'Secretariat') }}</div>
                                            <div class="me-record-meta">{{ $report->thinkTank ? \Illuminate\Support\Str::headline($report->thinkTank->role ?: 'think tank') : 'Internal report' }}</div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-dark">{{ $report->projectComponent?->name ?: 'Component unavailable' }}</div>
                                            <div class="me-record-meta">{{ $report->projectComponent?->project_id }}</div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-dark">{{ $report->responsibleDirectorate?->name ?: 'Not assigned' }}</div>
                                            <div class="me-record-meta">{{ $report->responsibleDirectorate?->code }}</div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-dark">{{ $report->indicator_results_count }} due indicators</div>
                                            <div class="me-record-meta">{{ $report->documents_count }} supporting documents</div>
                                        </td>
                                        <td><span class="me-status {{ $report->status }}">{{ $report->lifecycleLabel() }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ route('budget.me.performance-reports.edit', $report) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="{{ $report->isEditable() ? 'feather-edit-2' : 'feather-eye' }}" aria-hidden="true"></i>
                                                {{ $report->isEditable() ? 'Complete' : ($report->isArchived() ? 'History' : 'Review') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="me-mobile-register">
                        @foreach ($reports as $report)
                            <article class="me-mobile-card">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div>
                                        <span class="me-code">{{ $report->reporting_quarter }} {{ $report->reporting_year }}</span>
                                        <h3 class="me-record-title mb-0">{{ $report->form?->title ?: 'Form unavailable' }}</h3>
                                    </div>
                                    <span class="me-status {{ $report->status }}">{{ $report->lifecycleLabel() }}</span>
                                </div>
                                <div class="me-mobile-facts">
                                    <div class="me-mobile-fact"><small>Component</small><strong>{{ $report->projectComponent?->name ?: 'Unavailable' }}</strong></div>
                                    <div class="me-mobile-fact"><small>Owner</small><strong>{{ $report->thinkTank?->name ?: ($report->createdBy?->name ?: 'Secretariat') }}</strong></div>
                                    <div class="me-mobile-fact"><small>Directorate</small><strong>{{ $report->responsibleDirectorate?->name ?: 'Not assigned' }}</strong></div>
                                    <div class="me-mobile-fact"><small>Indicators</small><strong>{{ $report->indicator_results_count }}</strong></div>
                                    <div class="me-mobile-fact"><small>Evidence</small><strong>{{ $report->documents_count }} files</strong></div>
                                </div>
                                <a href="{{ route('budget.me.performance-reports.edit', $report) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="{{ $report->isEditable() ? 'feather-edit-2' : 'feather-eye' }} me-1" aria-hidden="true"></i>
                                    {{ $report->isEditable() ? 'Complete report' : 'Open review' }}
                                </a>
                            </article>
                        @endforeach
                    </div>
                @elseif ($tab === 'submissions')
                    <div class="me-submission-results-summary" aria-live="polite">
                        <strong>
                            Showing {{ number_format((int) $submissionAssignments->firstItem()) }}&ndash;{{ number_format((int) $submissionAssignments->lastItem()) }}
                            of {{ number_format((int) $submissionAssignments->total()) }} think-tank indicator {{ \Illuminate\Support\Str::plural('assignment', $submissionAssignments->total()) }}
                        </strong>
                        @if ($registerHasFilters)
                            <span class="me-submission-filter-cue"><i class="feather-filter" aria-hidden="true"></i>Filters applied</span>
                        @else
                            <span><i class="feather-clock me-1" aria-hidden="true"></i>Newest activity first</span>
                        @endif
                    </div>
                    <div class="table-responsive me-register-desktop me-data-table-region" role="region" aria-label="Scrollable participant submission register" tabindex="0">
                        <table class="table me-register-table me-submission-table align-middle">
                            <caption class="visually-hidden">Participant data submissions</caption>
                            <colgroup>
                                <col style="width: 270px">
                                <col style="width: 300px">
                                <col style="width: 310px">
                                <col style="width: 230px">
                                <col style="width: 240px">
                                <col style="width: 220px">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Think tank</th>
                                    <th>Indicator</th>
                                    <th>Linked form / period</th>
                                    <th>Data required by</th>
                                    <th>Submission so far</th>
                                    <th>Review / action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($submissionAssignments as $assignment)
                                    @php
                                        $submission = $assignment->submission;
                                        $submissionCollection = $assignment->collection;
                                        $submissionForm = $submissionCollection?->form;
                                        $submissionIndicator = $submissionForm?->indicator;
                                        $submissionPortfolio = $submissionForm?->portfolio;
                                        $submissionParticipant = $assignment->thinkTank;
                                        $submissionPeriod = $submissionCollection?->reportingPeriod;
                                        $indicatorUnit = $submissionIndicator?->unit?->symbol ?: $submissionIndicator?->unit?->name;
                                        $submissionStatus = $submission?->effectiveStatus() ?? 'not_started';
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="me-record-title">{{ $submissionParticipant?->name ?: 'Think tank unavailable' }}</div>
                                            <div class="me-record-meta">{{ $submissionParticipant?->country ?: 'Country not set' }}</div>
                                            @if ($submission?->submittedBy?->name)<div class="me-record-meta">Submitted by {{ $submission->submittedBy->name }}</div>@endif
                                        </td>
                                        <td>
                                            @if ($submissionIndicator?->indicator_code)<span class="me-code">{{ $submissionIndicator->indicator_code }}</span>@endif
                                            <div class="me-record-title">{{ $submissionIndicator?->name ?: 'Indicator unavailable' }}</div>
                                            @if ($indicatorUnit)<div class="me-record-meta">Measured in {{ $indicatorUnit }}</div>@endif
                                            <div class="me-record-meta">{{ $submissionPortfolio?->name ?: 'Portfolio unavailable' }}</div>
                                        </td>
                                        <td>
                                            @if ($submissionForm?->code)<span class="me-code">{{ $submissionForm->code }}</span>@endif
                                            <div class="me-record-title">{{ $submissionForm?->title ?: 'Template unavailable' }}</div>
                                            <div class="me-record-meta">
                                                {{ $submissionPeriod?->label ?: 'Reporting period unavailable' }}
                                                @if ($submissionPeriod?->code)&middot; {{ $submissionPeriod->code }}@endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-dark">{{ $submissionCollection?->due_at?->format('d M Y, H:i') ?: 'Deadline not set' }}</div>
                                            <div class="me-record-meta">Assigned {{ $assignment->assigned_at?->format('d M Y') ?: 'date unavailable' }}</div>
                                            <div class="me-record-meta">Window: {{ $submissionCollection?->opens_at?->format('d M') ?: 'not set' }} &mdash; {{ $submissionCollection?->closes_at?->format('d M Y') ?: 'not set' }}</div>
                                        </td>
                                        <td>
                                            <span class="me-status {{ $submissionStatus }}">{{ str($submissionStatus)->replace('_', ' ')->title() }}</span>
                                            <div class="me-record-meta">
                                                @if ($submission?->submitted_at)
                                                    Submitted {{ $submission->submitted_at->format('d M Y, H:i') }}
                                                @elseif ($submission)
                                                    Last saved {{ $submission->updated_at?->format('d M Y, H:i') ?: 'date unavailable' }}
                                                @else
                                                    No response has been started
                                                @endif
                                                <br>{{ number_format((int) ($submission?->answers_count ?? 0)) }} {{ \Illuminate\Support\Str::plural('answer', (int) ($submission?->answers_count ?? 0)) }}@if ($submission) &middot; Revision {{ $submission->revision }}@endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="me-submission-review">
                                                <i class="{{ $submission?->reviewed_at ? 'feather-check-circle' : 'feather-clock' }}" aria-hidden="true"></i>
                                                <div>
                                                    <div class="small fw-semibold text-dark">{{ $submission?->reviewedBy?->name ?: ($submission ? ($submissionStatus === 'draft' ? 'Not ready for review' : 'Awaiting review') : 'Waiting for think tank') }}</div>
                                                    <div class="me-record-meta">{{ $submission?->reviewed_at?->format('d M Y, H:i') ?: 'No review recorded' }}</div>
                                                </div>
                                            </div>
                                            @if ($canManage && $submission)
                                                <a href="{{ route('budget.me.submission-reviews.show', $submission) }}" class="btn btn-sm btn-outline-primary mt-2">
                                                    <i class="feather-eye me-1" aria-hidden="true"></i>{{ $submissionStatus === 'draft' ? 'View submission' : 'Open review' }}
                                                </a>
                                            @elseif (! $submission)
                                                <div class="me-record-meta mt-2">Follow up with the assigned think tank.</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="me-mobile-register">
                        @foreach ($submissionAssignments as $assignment)
                            @php
                                $submission = $assignment->submission;
                                $submissionCollection = $assignment->collection;
                                $submissionForm = $submissionCollection?->form;
                                $submissionIndicator = $submissionForm?->indicator;
                                $submissionPortfolio = $submissionForm?->portfolio;
                                $submissionParticipant = $assignment->thinkTank;
                                $submissionPeriod = $submissionCollection?->reportingPeriod;
                                $submissionStatus = $submission?->effectiveStatus() ?? 'not_started';
                            @endphp
                            <article class="me-mobile-card">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div>
                                        @if ($submissionForm?->code)<span class="me-code">{{ $submissionForm->code }}</span>@endif
                                        <h3 class="me-record-title mb-0">{{ $submissionParticipant?->name ?: 'Participant unavailable' }}</h3>
                                        <div class="me-record-meta">{{ $submissionParticipant?->country ?: 'Country not set' }}</div>
                                        @if ($submission?->submittedBy?->name)
                                            <div class="me-record-meta">Submitted by {{ $submission->submittedBy->name }}</div>
                                        @endif
                                    </div>
                                    <span class="me-status {{ $submissionStatus }}">{{ str($submissionStatus)->replace('_', ' ')->title() }}</span>
                                </div>
                                <div class="me-mobile-facts">
                                    <div class="me-mobile-fact"><small>Portfolio</small><strong>{{ $submissionPortfolio?->name ?: 'Unavailable' }}</strong></div>
                                    <div class="me-mobile-fact"><small>Indicator</small><strong>{{ $submissionIndicator?->name ?: 'Unavailable' }}</strong></div>
                                    <div class="me-mobile-fact"><small>Template</small><strong>{{ $submissionForm?->title ?: 'Unavailable' }}</strong></div>
                                    <div class="me-mobile-fact"><small>Reporting period</small><strong>{{ $submissionPeriod?->label ?: 'Unavailable' }}</strong></div>
                                    <div class="me-mobile-fact"><small>Due</small><strong>{{ $submissionCollection?->due_at?->format('d M Y, H:i') ?: 'Not set' }}</strong></div>
                                    <div class="me-mobile-fact"><small>Submitted</small><strong>{{ $submission?->submitted_at?->format('d M Y, H:i') ?: ($submission ? 'Draft not submitted' : 'Not started') }}</strong></div>
                                    <div class="me-mobile-fact"><small>Response</small><strong>{{ number_format((int) ($submission?->answers_count ?? 0)) }} {{ \Illuminate\Support\Str::plural('answer', (int) ($submission?->answers_count ?? 0)) }}</strong></div>
                                </div>
                                <div class="me-submission-review">
                                    <i class="{{ $submission?->reviewed_at ? 'feather-check-circle' : 'feather-clock' }}" aria-hidden="true"></i>
                                    <div class="me-record-meta mt-0">
                                        Reviewer: {{ $submission?->reviewedBy?->name ?: ($submission ? ($submissionStatus === 'draft' ? 'Not ready for review' : 'Awaiting review') : 'Waiting for think tank') }}
                                        @if ($submission?->reviewed_at)<br>{{ $submission->reviewed_at->format('d M Y, H:i') }}@endif
                                    </div>
                                </div>
                                @if ($canManage && $submission)
                                    <a href="{{ route('budget.me.submission-reviews.show', $submission) }}" class="btn btn-sm btn-outline-primary mt-3">
                                        <i class="feather-eye me-1" aria-hidden="true"></i>{{ $submissionStatus === 'draft' ? 'View submission' : 'Open review' }}
                                    </a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="table-responsive me-register-desktop me-data-table-region" role="region" aria-label="Scrollable data collection register" tabindex="0">
                        <table class="table me-register-table me-collection-table align-middle">
                            <caption class="visually-hidden">Data collections and progress</caption>
                            <colgroup>
                                <col style="width: 260px">
                                <col style="width: 250px">
                                <col style="width: 250px">
                                <col style="width: 260px">
                                <col style="width: 230px">
                                <col style="width: 250px">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Indicator</th>
                                    <th>Linked form</th>
                                    <th>When data is needed</th>
                                    <th>Assigned think tanks</th>
                                    <th>Submission progress</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($collections as $collection)
                                    @php
                                        $submittedAssignments = $collection->assignments->filter(fn ($assignment) => (bool) $assignment->submission?->submitted_at);
                                        $draftAssignments = $collection->assignments->filter(fn ($assignment) => $assignment->submission && ! $assignment->submission->submitted_at);
                                        $notStartedAssignments = $collection->assignments->filter(fn ($assignment) => ! $assignment->submission);
                                        $submittedCount = $submittedAssignments->count();
                                        $completion = $collection->assignments_count > 0 ? min(100, round(($submittedCount / $collection->assignments_count) * 100)) : 0;
                                        $isPastDue = $collection->due_at && now()->isAfter($collection->due_at) && $collection->status !== \App\Models\MeDataCollection::STATUS_CLOSED;
                                        $collectionIndicator = $collection->form?->indicator;
                                    @endphp
                                    <tr>
                                        <td>
                                            @if ($collectionIndicator?->indicator_code)<span class="me-code">{{ $collectionIndicator->indicator_code }}</span>@endif
                                            <div class="me-record-title">{{ $collectionIndicator?->name ?: 'Indicator unavailable' }}</div>
                                            <div class="me-record-meta">Unit: {{ $collectionIndicator?->unit?->symbol ?: ($collectionIndicator?->unit?->name ?: 'Not configured') }}</div>
                                        </td>
                                        <td>
                                            <span class="me-code">{{ $collection->form?->code ?: 'No code' }}</span>
                                            <div class="me-record-title">{{ $collection->form?->title ?: 'Form unavailable' }}</div>
                                            <div class="me-record-meta">{{ $collection->form?->portfolio?->name ?: 'Portfolio unavailable' }}</div>
                                            <span class="me-status {{ $collection->status }} mt-2">{{ $collection->status }}</span>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-dark">Due {{ $collection->due_at?->format('d M Y, H:i') ?: 'Not set' }}</div>
                                            <div class="me-record-meta">{{ $collection->reportingPeriod?->label ?: 'Period unavailable' }}@if ($collection->reportingPeriod?->code) &middot; {{ $collection->reportingPeriod->code }}@endif</div>
                                            <div class="me-record-meta">Open {{ $collection->opens_at?->format('d M Y') ?: 'not set' }} &middot; Close {{ $collection->closes_at?->format('d M Y') ?: 'not set' }}</div>
                                            @if ($isPastDue)<span class="text-danger small fw-semibold"><i class="feather-alert-circle me-1" aria-hidden="true"></i>Past due</span>@endif
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-dark">{{ number_format((int) $collection->assignments_count) }} {{ \Illuminate\Support\Str::plural('think tank', (int) $collection->assignments_count) }}</div>
                                            <div class="me-record-meta">{{ $collection->assignments->take(3)->pluck('thinkTank.name')->filter()->join(', ') ?: 'No think tank assigned' }}@if ($collection->assignments_count > 3) +{{ $collection->assignments_count - 3 }} more @endif</div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-dark">{{ $submittedCount }} / {{ $collection->assignments_count }} submitted</div>
                                            <div class="progress mt-2" style="height: 6px" role="progressbar" aria-label="Submission progress" aria-valuenow="{{ $completion }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: {{ $completion }}%"></div></div>
                                            <div class="me-progress-breakdown">
                                                <span class="me-progress-chip is-complete">{{ $submittedCount }} submitted</span>
                                                <span class="me-progress-chip">{{ $draftAssignments->count() }} draft</span>
                                                <span class="me-progress-chip is-pending">{{ $notStartedAssignments->count() }} not started</span>
                                            </div>
                                            @if ($submittedAssignments->isNotEmpty())
                                                <div class="me-record-meta">Submitted by: {{ $submittedAssignments->take(3)->pluck('thinkTank.name')->filter()->join(', ') }}@if ($submittedCount > 3) +{{ $submittedCount - 3 }} more @endif</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($canManage)
                                                <div class="me-row-actions justify-content-end">
                                                    @if ($collection->assignments_count > 0)
                                                        <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'submissions', 'q' => $collection->form?->code]) }}" class="btn btn-sm btn-outline-primary"><i class="feather-file-text" aria-hidden="true"></i> Submissions</a>
                                                    @endif
                                                    @if ($collection->status !== \App\Models\MeDataCollection::STATUS_CLOSED)
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm {{ $collection->reportingPeriod?->isActive() ? 'btn-outline-warning' : 'btn-warning' }}"
                                                            data-fix-reporting-period
                                                            data-action="{{ route('budget.me.data-entry.collections.reporting-period.fix', $collection) }}"
                                                            data-collection-id="{{ $collection->id }}"
                                                            data-period-code="{{ $collection->reportingPeriod?->code }}"
                                                            data-period-label="{{ $collection->reportingPeriod?->label }}"
                                                            data-period-coverage="{{ $collection->reportingPeriod?->period_start?->format('d M Y') }} — {{ $collection->reportingPeriod?->period_end?->format('d M Y') }}"
                                                            data-period-status="{{ str($collection->reportingPeriod?->status ?: 'Unavailable')->headline() }}"
                                                            data-period-lifecycle="{{ str($collection->reportingPeriod?->lifecycle_status ?: 'Unavailable')->headline() }}"
                                                            data-submission-opens="{{ $collection->reportingPeriod?->submission_opens_at?->format('Y-m-d\TH:i') }}"
                                                            data-submission-deadline="{{ $collection->reportingPeriod?->submission_deadline?->format('Y-m-d\TH:i') }}"
                                                            data-review-deadline="{{ $collection->reportingPeriod?->review_deadline?->format('Y-m-d\TH:i') }}"
                                                        ><i class="feather-calendar" aria-hidden="true"></i> Fix reporting period</button>
                                                        <form method="POST" action="{{ route('budget.me.data-entry.collections.publish', $collection) }}" data-confirm="{{ $collection->status === \App\Models\MeDataCollection::STATUS_DRAFT ? 'Publish this collection and notify every assigned think tank? The linked form will appear in their M&E portal.' : 'Send this open collection to assigned think tanks again? Accounts already notified today will not receive a duplicate.' }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-success"><i class="feather-send" aria-hidden="true"></i> Publish / Send to Think Tanks</button>
                                                        </form>
                                                        <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'collections', 'edit_collection' => $collection->id]) }}#data-entry-workspace" class="btn btn-sm btn-light border"><i class="feather-edit-2" aria-hidden="true"></i> Edit</a>
                                                        <form method="POST" action="{{ route('budget.me.data-entry.collections.close', $collection) }}" data-confirm="Close this collection? Participants will no longer be able to submit.">@csrf<button type="submit" class="btn btn-sm btn-outline-danger"><i class="feather-lock" aria-hidden="true"></i> Close</button></form>
                                                    @endif
                                                </div>
                                            @elseif (! $canManage)
                                                <div class="text-end me-muted small">View only</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="me-mobile-register">
                        @foreach ($collections as $collection)
                            @php
                                $submittedCount = $collection->assignments->filter(fn ($assignment) => (bool) $assignment->submission?->submitted_at)->count();
                                $draftCount = $collection->assignments->filter(fn ($assignment) => $assignment->submission && ! $assignment->submission->submitted_at)->count();
                                $notStartedCount = $collection->assignments->filter(fn ($assignment) => ! $assignment->submission)->count();
                                $completion = $collection->assignments_count > 0 ? min(100, round(($submittedCount / $collection->assignments_count) * 100)) : 0;
                                $collectionIndicator = $collection->form?->indicator;
                            @endphp
                            <article class="me-mobile-card">
                                <div class="d-flex align-items-start justify-content-between gap-2"><div><span class="me-code">{{ $collectionIndicator?->indicator_code ?: 'No indicator code' }}</span><h3 class="me-record-title mb-0">{{ $collectionIndicator?->name ?: 'Indicator unavailable' }}</h3></div><span class="me-status {{ $collection->status }}">{{ $collection->status }}</span></div>
                                <div class="me-mobile-facts">
                                    <div class="me-mobile-fact"><small>Linked form</small><strong>{{ $collection->form?->title ?: 'Unavailable' }}</strong></div>
                                    <div class="me-mobile-fact"><small>Period</small><strong>{{ $collection->reportingPeriod?->label ?: 'Unavailable' }}</strong></div>
                                    <div class="me-mobile-fact"><small>Due</small><strong>{{ $collection->due_at?->format('d M Y, H:i') }}</strong></div>
                                    <div class="me-mobile-fact"><small>Assigned</small><strong>{{ $collection->assignments_count }} think tanks</strong></div>
                                    <div class="me-mobile-fact"><small>Submitted</small><strong>{{ $submittedCount }} / {{ $collection->assignments_count }}</strong></div>
                                    <div class="me-mobile-fact"><small>Still pending</small><strong>{{ $draftCount }} draft &middot; {{ $notStartedCount }} not started</strong></div>
                                </div>
                                <div class="progress mb-3" style="height: 6px" role="progressbar" aria-label="Submission progress" aria-valuenow="{{ $completion }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: {{ $completion }}%"></div></div>
                                @if ($canManage)
                                    <div class="me-row-actions justify-content-start">
                                        @if ($collection->assignments_count > 0)
                                            <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'submissions', 'q' => $collection->form?->code]) }}" class="btn btn-sm btn-outline-primary"><i class="feather-file-text me-1" aria-hidden="true"></i>Submissions</a>
                                        @endif
                                        @if ($collection->status !== \App\Models\MeDataCollection::STATUS_CLOSED)
                                            <button
                                                type="button"
                                                class="btn btn-sm {{ $collection->reportingPeriod?->isActive() ? 'btn-outline-warning' : 'btn-warning' }}"
                                                data-fix-reporting-period
                                                data-action="{{ route('budget.me.data-entry.collections.reporting-period.fix', $collection) }}"
                                                data-collection-id="{{ $collection->id }}"
                                                data-period-code="{{ $collection->reportingPeriod?->code }}"
                                                data-period-label="{{ $collection->reportingPeriod?->label }}"
                                                data-period-coverage="{{ $collection->reportingPeriod?->period_start?->format('d M Y') }} — {{ $collection->reportingPeriod?->period_end?->format('d M Y') }}"
                                                data-period-status="{{ str($collection->reportingPeriod?->status ?: 'Unavailable')->headline() }}"
                                                data-period-lifecycle="{{ str($collection->reportingPeriod?->lifecycle_status ?: 'Unavailable')->headline() }}"
                                                data-submission-opens="{{ $collection->reportingPeriod?->submission_opens_at?->format('Y-m-d\TH:i') }}"
                                                data-submission-deadline="{{ $collection->reportingPeriod?->submission_deadline?->format('Y-m-d\TH:i') }}"
                                                data-review-deadline="{{ $collection->reportingPeriod?->review_deadline?->format('Y-m-d\TH:i') }}"
                                            ><i class="feather-calendar me-1" aria-hidden="true"></i>Fix reporting period</button>
                                            <form method="POST" action="{{ route('budget.me.data-entry.collections.publish', $collection) }}" data-confirm="{{ $collection->status === \App\Models\MeDataCollection::STATUS_DRAFT ? 'Publish this collection and notify every assigned think tank? The linked form will appear in their M&E portal.' : 'Send this open collection to assigned think tanks again? Accounts already notified today will not receive a duplicate.' }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success"><i class="feather-send me-1" aria-hidden="true"></i>Publish / Send to Think Tanks</button>
                                            </form>
                                            <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'collections', 'edit_collection' => $collection->id]) }}#data-entry-workspace" class="btn btn-sm btn-light border"><i class="feather-edit-2 me-1" aria-hidden="true"></i>Edit</a>
                                            <form method="POST" action="{{ route('budget.me.data-entry.collections.close', $collection) }}" data-confirm="Close this collection? Participants will no longer be able to submit.">@csrf<button type="submit" class="btn btn-sm btn-outline-danger"><i class="feather-lock me-1" aria-hidden="true"></i>Close</button></form>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif

                @if ($currentPaginator && $currentPaginator->hasPages())
                    <div class="me-pagination-wrap">{{ $currentPaginator->links() }}</div>
                @endif
                @endif
            </section>
        </div>

        @if ($canManage && $tab === 'collections' && ! $showPeriodForm && ! $showCollectionForm)
            @php
                $fixPeriodCollectionId = (string) old('fix_reporting_period_collection_id', '');
                $fixPeriodHasErrors = collect([
                    'fix_reporting_period_collection_id',
                    'fix_submission_opens_at',
                    'fix_submission_deadline',
                    'fix_review_deadline',
                ])->contains(fn (string $field): bool => $errors->has($field));
            @endphp
            <div
                class="modal fade me-period-fix-modal"
                id="me-fix-reporting-period-modal"
                tabindex="-1"
                role="dialog"
                aria-labelledby="me-fix-reporting-period-title"
                aria-hidden="true"
                data-fix-period-modal
                data-auto-open="{{ $fixPeriodCollectionId !== '' && $fixPeriodHasErrors ? 'true' : 'false' }}"
            >
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <form
                            method="POST"
                            action="{{ $fixPeriodCollectionId !== '' ? route('budget.me.data-entry.collections.reporting-period.fix', $fixPeriodCollectionId) : '#' }}"
                            data-fix-period-form
                        >
                            @csrf
                            <input type="hidden" name="fix_reporting_period_collection_id" value="{{ $fixPeriodCollectionId }}" data-fix-period-collection-id>
                            <input type="hidden" name="fix_period_code" value="{{ old('fix_period_code') }}" data-fix-period-code-input>
                            <input type="hidden" name="fix_period_label" value="{{ old('fix_period_label') }}" data-fix-period-label-input>
                            <input type="hidden" name="fix_period_coverage" value="{{ old('fix_period_coverage') }}" data-fix-period-coverage-input>

                            <div class="modal-header">
                                <div class="me-period-fix-title">
                                    <span><i class="feather-calendar" aria-hidden="true"></i></span>
                                    <div>
                                        <h2 class="modal-title" id="me-fix-reporting-period-title">Fix reporting period</h2>
                                        <p>Correct the submission window and open the linked period without leaving Data Collections.</p>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-fix-period-close aria-label="Close"></button>
                            </div>

                            <div class="modal-body p-3 p-md-4">
                                <div class="me-period-fix-summary" aria-label="Linked reporting period">
                                    <div class="me-period-fix-fact"><small>Reporting period</small><strong data-fix-period-identity>{{ trim(old('fix_period_code').' — '.old('fix_period_label'), ' —') ?: 'Select a collection' }}</strong></div>
                                    <div class="me-period-fix-fact"><small>Coverage dates</small><strong data-fix-period-coverage>{{ old('fix_period_coverage') ?: 'Not available' }}</strong></div>
                                    <div class="me-period-fix-fact"><small>Current state</small><strong data-fix-period-state>Will be changed to Active / Open</strong></div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label" for="fix-submission-opens">Submission opens</label>
                                        <input type="datetime-local" id="fix-submission-opens" name="fix_submission_opens_at" class="form-control @error('fix_submission_opens_at') is-invalid @enderror" value="{{ old('fix_submission_opens_at') }}" data-fix-submission-opens>
                                        <span class="me-field-help">Leave blank to allow submissions immediately after publishing.</span>
                                        @error('fix_submission_opens_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="fix-submission-deadline">Submission deadline</label>
                                        <input type="datetime-local" id="fix-submission-deadline" name="fix_submission_deadline" class="form-control @error('fix_submission_deadline') is-invalid @enderror" value="{{ old('fix_submission_deadline') }}" data-fix-submission-deadline>
                                        <span class="me-field-help">Use a future deadline, or leave blank if the collection dates control access.</span>
                                        @error('fix_submission_deadline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="fix-review-deadline">Review deadline</label>
                                        <input type="datetime-local" id="fix-review-deadline" name="fix_review_deadline" class="form-control @error('fix_review_deadline') is-invalid @enderror" value="{{ old('fix_review_deadline') }}" data-fix-review-deadline>
                                        <span class="me-field-help">Optional deadline for the ATTP review team.</span>
                                        @error('fix_review_deadline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="me-period-fix-note">
                                    <i class="feather-info" aria-hidden="true"></i>
                                    <div><strong class="d-block mb-1">What saving will do</strong>The system will set this reporting period to <strong>Active / Open</strong>. It will not send the collection automatically; use <strong>Publish / Send to Think Tanks</strong> after this modal closes.</div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-light border" data-fix-period-close>Cancel</button>
                                <button type="submit" class="btn btn-success"><i class="feather-unlock me-1" aria-hidden="true"></i>Save and open period</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        @if ($canManage && $showFormBuilder && !($formLocked ?? false))
            <template id="data-entry-field-template">
                <article class="me-builder-row" data-field-row>
                    <div class="me-builder-heading">
                        <h3 class="me-builder-title">Question <span data-field-number></span></h3>
                        <div class="me-builder-actions">
                            <button type="button" class="btn btn-sm btn-light border" title="Move question up" aria-label="Move question up" data-move-field="up"><i class="feather-arrow-up" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-light border" title="Move question down" aria-label="Move question down" data-move-field="down"><i class="feather-arrow-down" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Remove question" aria-label="Remove question" data-remove-field><i class="feather-trash-2" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <input type="hidden" name="fields[__INDEX__][id]" value="">
                    <input type="hidden" name="fields[__INDEX__][field_key]" value="">
                    <input type="hidden" name="fields[__INDEX__][section_key]" value="__SECTION_KEY__" data-field-section-key>
                    <input type="hidden" name="fields[__INDEX__][sort_order]" value="" data-sort-order>
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <label class="form-label">Question <span class="text-danger">*</span></label>
                            <input type="text" name="fields[__INDEX__][label]" class="form-control" maxlength="255" placeholder="Question shown to participants" required>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Answer type <span class="text-danger">*</span></label>
                            <select name="fields[__INDEX__][field_type]" class="form-select" required data-field-type>
                                @foreach ($fieldTypeGroups as $groupLabel => $groupTypes)
                                    <optgroup label="{{ $groupLabel }}">
                                        @foreach ($groupTypes as $typeValue => $typeLabel)
                                            <option value="{{ $typeValue }}" @selected($typeValue === 'text')>{{ $typeLabel }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Help text</label>
                            <input type="text" name="fields[__INDEX__][help_text]" class="form-control" maxlength="1000" placeholder="Optional explanation or example">
                        </div>
                        <div class="col-lg-3" data-unit-wrap>
                            <label class="form-label">Unit label</label>
                            <input type="text" name="fields[__INDEX__][unit_label]" class="form-control" maxlength="80" placeholder="%, people, USD">
                        </div>
                        <div class="col-lg-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input type="hidden" name="fields[__INDEX__][is_required]" value="0">
                                <input class="form-check-input" type="checkbox" name="fields[__INDEX__][is_required]" value="1" id="field-required-__INDEX__" checked>
                                <label class="form-check-label fw-semibold small" for="field-required-__INDEX__">Required response</label>
                            </div>
                        </div>
                        <div class="col-lg-6" data-numeric-settings>
                            <div class="me-field-settings">
                                <div class="me-field-settings-title"><i class="feather-sliders" aria-hidden="true"></i>Numeric range and step</div>
                                <div class="row g-2">
                                    <div class="col-sm-4"><label class="form-label">Minimum</label><input type="number" step="any" name="fields[__INDEX__][validation][min]" class="form-control" placeholder="No minimum" data-numeric-min-input></div>
                                    <div class="col-sm-4"><label class="form-label">Maximum</label><input type="number" step="any" name="fields[__INDEX__][validation][max]" class="form-control" placeholder="No maximum" data-numeric-max-input></div>
                                    <div class="col-sm-4"><label class="form-label">Step</label><input type="number" min="0" step="any" name="fields[__INDEX__][validation][step]" class="form-control" placeholder="Any" data-numeric-step-input></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6" data-text-settings hidden>
                            <div class="me-field-settings">
                                <div class="me-field-settings-title"><i class="feather-type" aria-hidden="true"></i>Text length</div>
                                <div class="row g-2">
                                    <div class="col-sm-6"><label class="form-label">Minimum characters</label><input type="number" min="0" max="20000" step="1" name="fields[__INDEX__][validation][min_length]" class="form-control" placeholder="No minimum" data-min-length-input></div>
                                    <div class="col-sm-6"><label class="form-label">Maximum characters</label><input type="number" min="0" max="20000" step="1" name="fields[__INDEX__][validation][max_length]" class="form-control" placeholder="Use portal limit" data-max-length-input></div>
                                </div>
                                <span class="me-field-help" data-text-limit-help>Portal hard limit: 20,000 characters. Leave maximum blank to use this limit.</span>
                            </div>
                        </div>

                        <div class="col-lg-6" data-options-wrap hidden>
                            <div class="me-field-settings">
                                <div class="me-field-settings-title"><i class="feather-list" aria-hidden="true"></i>Choice options</div>
                                <textarea name="fields[__INDEX__][options]" class="form-control" rows="4" placeholder="Enter one option per line"></textarea>
                                <span class="me-field-help" data-options-help>Select and radio require at least two options; multi-select and checkbox require at least one.</span>
                            </div>
                        </div>

                        <div class="col-12" data-upload-settings hidden>
                            <div class="me-field-settings">
                                <div class="me-field-settings-title"><i class="feather-upload-cloud" aria-hidden="true"></i>Upload rules</div>
                                <div class="row g-3 align-items-end">
                                    <div class="col-lg-6">
                                        <label class="form-label">Allowed file extensions</label>
                                        <input type="text" name="fields[__INDEX__][validation][allowed_extensions]" class="form-control" placeholder="{{ $defaultFileExtensions }}" data-extension-input>
                                        <span class="me-field-help">Separate extensions with commas. Dots and capital letters are cleaned automatically.</span>
                                    </div>
                                    <div class="col-lg-3 col-sm-6"><label class="form-label">Maximum size (MB)</label><input type="number" min="1" max="50" step="1" name="fields[__INDEX__][validation][max_file_size_mb]" class="form-control" placeholder="10" data-file-size-input></div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="form-check mb-2">
                                            <input type="hidden" name="fields[__INDEX__][validation][multiple]" value="0">
                                            <input class="form-check-input" type="checkbox" name="fields[__INDEX__][validation][multiple]" value="1" id="field-multiple-__INDEX__">
                                            <label class="form-check-label fw-semibold small" for="field-multiple-__INDEX__">Allow multiple uploads</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6" data-indicator-wrap>
                            <div class="me-field-settings">
                                <div class="me-field-settings-title"><i class="feather-link" aria-hidden="true"></i>Performance indicator mapping</div>
                                <label class="form-label">Mapped indicator</label>
                                <select name="fields[__INDEX__][indicator_id]" class="form-select" data-indicator-select>
                                    <option value="">No indicator mapping</option>
                                    @foreach ($indicatorOptions as $indicatorOption)
                                        <option value="{{ $indicatorOption['id'] }}" data-portfolio="{{ $indicatorOption['portfolio_id'] }}" data-component="{{ $indicatorOption['project_component_id'] }}">{{ $indicatorOption['label'] }}{{ $indicatorOption['unit'] ? ' · '.$indicatorOption['unit'] : '' }}</option>
                                    @endforeach
                                </select>
                                <span class="me-field-help">Available for integer, number, percentage and currency. One indicator can be mapped only once.</span>
                            </div>
                        </div>
                    </div>
                </article>
            </template>
            <template id="data-entry-section-template">
                <article class="me-section-builder" data-section-card style="--section-color: __SECTION_COLOR__">
                    <div class="me-section-builder-header">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div class="me-section-number"><i class="feather-layers" aria-hidden="true"></i>Section <span data-section-number></span></div>
                            <div class="me-builder-actions">
                                <button type="button" class="btn btn-sm btn-light border" title="Move section up" aria-label="Move section up" data-move-section="up"><i class="feather-arrow-up" aria-hidden="true"></i></button>
                                <button type="button" class="btn btn-sm btn-light border" title="Move section down" aria-label="Move section down" data-move-section="down"><i class="feather-arrow-down" aria-hidden="true"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Remove section" aria-label="Remove section" data-remove-section><i class="feather-trash-2" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <input type="hidden" name="sections[__SECTION_INDEX__][id]" value="">
                        <input type="hidden" name="sections[__SECTION_INDEX__][section_key]" value="__SECTION_KEY__" data-section-key>
                        <input type="hidden" name="sections[__SECTION_INDEX__][sort_order]" value="" data-section-sort-order>
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label">Section name <span class="text-danger">*</span></label>
                                <input type="text" name="sections[__SECTION_INDEX__][name]" class="form-control" maxlength="255" placeholder="e.g. Performance results" required data-section-name>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label">Background colour</label>
                                <div class="me-section-color-control">
                                    <input type="color" name="sections[__SECTION_INDEX__][background_color]" value="__SECTION_COLOR__" aria-label="Section background colour" data-section-color>
                                    <div class="me-color-presets" aria-label="Soft colour presets">
                                        @foreach ($sectionPalette as $presetColor)
                                            <button type="button" class="me-color-preset" style="--preset-color: {{ $presetColor }}" title="Use {{ $presetColor }}" aria-label="Use colour {{ $presetColor }}" data-color-preset="{{ $presetColor }}"></button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="section-description-__SECTION_INDEX__">Instructions / explanation <span class="text-danger">*</span></label>
                                <textarea id="section-description-__SECTION_INDEX__" name="sections[__SECTION_INDEX__][description]" class="form-control" rows="3" maxlength="2000" placeholder="Explain what respondents should provide, which records to consult, and any definitions they need." required aria-describedby="section-description-help-__SECTION_INDEX__">{{ $defaultSectionGuidance }}</textarea>
                                <div id="section-description-help-__SECTION_INDEX__" class="me-field-help">This text appears above the section for think-tank respondents. Give them enough guidance to answer the questions correctly.</div>
                            </div>
                        </div>
                    </div>
                    <div class="me-section-builder-body">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <div><strong class="small text-dark">Questions</strong><div class="me-field-help mt-0">Each section needs at least one question.</div></div>
                            <button type="button" class="btn btn-sm btn-outline-success" data-add-field><i class="feather-plus me-1" aria-hidden="true"></i>Add question</button>
                        </div>
                        <div data-section-fields></div>
                    </div>
                </article>
            </template>
        @endif
        </div>
    @unless ($isDataEntryFragment ?? false)
    </main>
    @endunless
@endsection

@unless ($isDataEntryFragment ?? false)
@push('scripts')
    <script>
        (() => {
            const initializeWorkspace = () => {
            document.querySelectorAll('.me-data-entry form[data-confirm]').forEach((form) => {
                if (form.dataset.confirmInitialized === 'true') return;
                form.dataset.confirmInitialized = 'true';
                form.addEventListener('submit', (event) => {
                    if (!window.confirm(form.dataset.confirm || 'Continue with this action?')) {
                        event.preventDefault();
                    }
                });
            });

            const fixPeriodModal = document.querySelector('[data-fix-period-modal]');
            if (fixPeriodModal && fixPeriodModal.dataset.initialized !== 'true') {
                fixPeriodModal.dataset.initialized = 'true';
                const fixPeriodForm = fixPeriodModal.querySelector('[data-fix-period-form]');
                let fixPeriodTrigger = null;
                const setText = (selector, value, fallback) => {
                    const target = fixPeriodModal.querySelector(selector);
                    if (target) target.textContent = value || fallback;
                };
                const setValue = (selector, value) => {
                    const target = fixPeriodModal.querySelector(selector);
                    if (target) target.value = value || '';
                };

                const populateFixPeriodModal = (trigger) => {
                    if (!trigger || !fixPeriodForm) return;

                    fixPeriodForm.action = trigger.dataset.action || '#';
                    setValue('[data-fix-period-collection-id]', trigger.dataset.collectionId);
                    setValue('[data-fix-period-code-input]', trigger.dataset.periodCode);
                    setValue('[data-fix-period-label-input]', trigger.dataset.periodLabel);
                    setValue('[data-fix-period-coverage-input]', trigger.dataset.periodCoverage);
                    setValue('[data-fix-submission-opens]', trigger.dataset.submissionOpens);
                    setValue('[data-fix-submission-deadline]', trigger.dataset.submissionDeadline);
                    setValue('[data-fix-review-deadline]', trigger.dataset.reviewDeadline);
                    setText('[data-fix-period-identity]', [trigger.dataset.periodCode, trigger.dataset.periodLabel].filter(Boolean).join(' — '), 'Reporting period unavailable');
                    setText('[data-fix-period-coverage]', trigger.dataset.periodCoverage, 'Coverage dates unavailable');
                    setText('[data-fix-period-state]', [trigger.dataset.periodStatus, trigger.dataset.periodLifecycle].filter(Boolean).join(' / '), 'State unavailable');
                    fixPeriodForm.dataset.meFormDirty = 'false';
                };

                const openFixPeriodModal = (trigger = null) => {
                    if (trigger) {
                        fixPeriodTrigger = trigger;
                        populateFixPeriodModal(trigger);
                    }

                    fixPeriodModal.classList.add('show', 'is-open');
                    fixPeriodModal.style.display = 'block';
                    fixPeriodModal.setAttribute('aria-hidden', 'false');
                    fixPeriodModal.setAttribute('aria-modal', 'true');
                    document.body.classList.add('me-period-fix-open');

                    window.requestAnimationFrame(() => {
                        const focusTarget = fixPeriodModal.querySelector('.is-invalid')
                            || fixPeriodModal.querySelector('[data-fix-submission-opens]')
                            || fixPeriodModal.querySelector('[data-fix-period-close]');
                        focusTarget?.focus({ preventScroll: true });
                    });
                };

                const closeFixPeriodModal = () => {
                    fixPeriodModal.classList.remove('show', 'is-open');
                    fixPeriodModal.style.removeProperty('display');
                    fixPeriodModal.setAttribute('aria-hidden', 'true');
                    fixPeriodModal.removeAttribute('aria-modal');
                    document.body.classList.remove('me-period-fix-open');
                    if (fixPeriodForm) fixPeriodForm.dataset.meFormDirty = 'false';
                    fixPeriodTrigger?.focus({ preventScroll: true });
                };

                document.querySelectorAll('[data-fix-reporting-period]').forEach((trigger) => {
                    if (trigger.dataset.fixPeriodInitialized === 'true') return;
                    trigger.dataset.fixPeriodInitialized = 'true';
                    trigger.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        openFixPeriodModal(trigger);
                    });
                });

                fixPeriodModal.querySelectorAll('[data-fix-period-close]').forEach((button) => {
                    button.addEventListener('click', closeFixPeriodModal);
                });

                fixPeriodModal.addEventListener('mousedown', (event) => {
                    if (event.target === fixPeriodModal) closeFixPeriodModal();
                });

                fixPeriodModal.addEventListener('keydown', (event) => {
                    if (event.key !== 'Escape') return;
                    event.preventDefault();
                    closeFixPeriodModal();
                });

                if (fixPeriodModal.dataset.autoOpen === 'true') {
                    window.requestAnimationFrame(() => openFixPeriodModal());
                }
            }

            const formPreviewModal = document.querySelector('[data-form-preview-modal]');
            if (formPreviewModal && formPreviewModal.dataset.initialized !== 'true') {
                formPreviewModal.dataset.initialized = 'true';
                const previewDataNode = document.querySelector('[data-form-preview-data]');
                const previewSections = formPreviewModal.querySelector('[data-form-preview-sections]');
                const previewEditLink = formPreviewModal.querySelector('[data-form-preview-edit]');
                let previewProfiles = {};
                let previewTrigger = null;
                try {
                    previewProfiles = JSON.parse(previewDataNode?.textContent || '{}');
                } catch (error) {
                    previewProfiles = {};
                }

                const setPreviewText = (selector, value, fallback = 'Not available') => {
                    formPreviewModal.querySelectorAll(selector).forEach((element) => {
                        element.textContent = value || fallback;
                    });
                };

                const createPreviewChoices = (options) => {
                    const list = document.createElement('div');
                    list.className = 'me-preview-choice-list';
                    (options.length ? options : ['Option 1', 'Option 2']).forEach((option) => {
                        const choice = document.createElement('span');
                        choice.className = 'me-preview-choice';
                        choice.textContent = option;
                        list.appendChild(choice);
                    });
                    return list;
                };

                const createPreviewControl = (field) => {
                    const type = field.type || 'text';
                    const options = Array.isArray(field.options) ? field.options : [];
                    if (['radio', 'checkbox', 'yes_no'].includes(type)) {
                        return createPreviewChoices(type === 'yes_no' ? ['Yes', 'No'] : options);
                    }
                    if (['rating', 'scale'].includes(type)) {
                        return createPreviewChoices(options.length ? options : ['1', '2', '3', '4', '5']);
                    }

                    const control = document.createElement('div');
                    control.className = 'me-preview-control';
                    if (type === 'textarea') {
                        control.classList.add('is-textarea');
                        control.textContent = 'Enter a detailed response...';
                    } else if (['select', 'multiselect'].includes(type)) {
                        control.textContent = options.length
                            ? `Select ${type === 'multiselect' ? 'one or more' : 'an option'}: ${options.join(', ')}`
                            : 'Select an option';
                    } else if (['file', 'image'].includes(type)) {
                        control.textContent = type === 'image' ? 'Choose image file' : 'Choose supporting file';
                    } else {
                        const placeholders = {
                            integer: 'Enter a whole number',
                            number: 'Enter a number',
                            percentage: 'Enter percentage',
                            currency: 'Enter amount',
                            email: 'name@example.org',
                            phone: 'Enter telephone number',
                            url: 'https://',
                            date: 'Select date',
                            time: 'Select time',
                            datetime: 'Select date and time',
                            month: 'Select month',
                            year: 'Enter year',
                        };
                        control.textContent = placeholders[type] || 'Enter response';
                    }
                    return control;
                };

                const renderPreviewSections = (sections) => {
                    if (!previewSections) return;
                    previewSections.replaceChildren();
                    (sections || []).forEach((section, sectionIndex) => {
                        const article = document.createElement('section');
                        article.className = 'me-preview-section';
                        const color = /^#[0-9a-f]{6}$/i.test(section.color || '') ? section.color : '#EFF6FF';
                        article.style.setProperty('--preview-section-color', color);

                        const heading = document.createElement('header');
                        heading.className = 'me-preview-section-head';
                        const title = document.createElement('h4');
                        title.textContent = `${sectionIndex + 1}. ${section.name || 'Untitled section'}`;
                        const description = document.createElement('p');
                        description.textContent = section.description || 'Complete the questions in this section.';
                        heading.append(title, description);

                        const fields = document.createElement('div');
                        fields.className = 'me-preview-fields';
                        (section.fields || []).forEach((field, fieldIndex) => {
                            const wrapper = document.createElement('div');
                            wrapper.className = 'me-preview-field';
                            if (['textarea', 'radio', 'checkbox', 'multiselect', 'file', 'image'].includes(field.type)) {
                                wrapper.classList.add('is-wide');
                            }
                            const label = document.createElement('label');
                            label.className = 'me-preview-label';
                            label.append(`${fieldIndex + 1}. ${field.label || 'Untitled question'}`);
                            if (field.required) {
                                const required = document.createElement('em');
                                required.textContent = ' *';
                                label.appendChild(required);
                            }
                            if (field.unit) {
                                const unit = document.createElement('span');
                                unit.className = 'text-muted fw-normal';
                                unit.textContent = ` (${field.unit})`;
                                label.appendChild(unit);
                            }
                            wrapper.append(label, createPreviewControl(field));
                            if (field.help) {
                                const help = document.createElement('small');
                                help.className = 'me-preview-help';
                                help.textContent = field.help;
                                wrapper.appendChild(help);
                            }
                            fields.appendChild(wrapper);
                        });

                        if (!(section.fields || []).length) {
                            const empty = document.createElement('div');
                            empty.className = 'text-muted small p-3';
                            empty.textContent = 'No questions have been added to this section.';
                            fields.appendChild(empty);
                        }
                        article.append(heading, fields);
                        previewSections.appendChild(article);
                    });

                    if (!(sections || []).length) {
                        const empty = document.createElement('div');
                        empty.className = 'alert alert-warning mb-0';
                        empty.textContent = 'This template has no sections or questions to preview yet.';
                        previewSections.appendChild(empty);
                    }
                };

                const populateFormPreview = (profile) => {
                    setPreviewText('[data-form-preview-code]', profile.code, 'FORM');
                    setPreviewText('[data-form-preview-status]', profile.status, 'Draft');
                    setPreviewText('[data-form-preview-version]', profile.version, '1');
                    setPreviewText('[data-form-preview-title]', profile.title, 'Form preview');
                    setPreviewText('[data-form-preview-description]', profile.description, 'Review how this reporting form will appear to respondents.');
                    setPreviewText('[data-form-preview-indicator]', profile.indicator, 'No indicator linked');
                    setPreviewText('[data-form-preview-portfolio]', profile.portfolio);
                    setPreviewText('[data-form-preview-component]', profile.component);
                    setPreviewText('[data-form-preview-responsible]', profile.responsible, 'Not assigned');
                    setPreviewText('[data-form-preview-instructions]', profile.instructions, 'Complete every required question and review the information before submitting.');
                    renderPreviewSections(profile.sections || []);

                    if (previewEditLink) {
                        previewEditLink.classList.toggle('d-none', !profile.edit_url);
                        previewEditLink.href = profile.edit_url || '#';
                    }
                };

                const openFormPreview = (trigger) => {
                    const profile = previewProfiles[trigger.dataset.previewForm];
                    if (!profile) return;
                    previewTrigger = trigger;
                    populateFormPreview(profile);
                    formPreviewModal.classList.add('is-open');
                    formPreviewModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('me-form-preview-open');
                    window.requestAnimationFrame(() => formPreviewModal.querySelector('[data-form-preview-close]')?.focus({ preventScroll: true }));
                };

                const closeFormPreview = () => {
                    formPreviewModal.classList.remove('is-open');
                    formPreviewModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('me-form-preview-open');
                    previewTrigger?.focus({ preventScroll: true });
                };

                document.querySelectorAll('[data-preview-form]').forEach((trigger) => {
                    if (trigger.dataset.previewInitialized === 'true') return;
                    trigger.dataset.previewInitialized = 'true';
                    trigger.addEventListener('click', () => openFormPreview(trigger));
                });
                formPreviewModal.querySelectorAll('[data-form-preview-close]').forEach((button) => {
                    button.addEventListener('click', closeFormPreview);
                });
                previewEditLink?.addEventListener('click', closeFormPreview);
                formPreviewModal.addEventListener('mousedown', (event) => {
                    if (event.target === formPreviewModal) closeFormPreview();
                });
                formPreviewModal.addEventListener('keydown', (event) => {
                    if (event.key !== 'Escape') return;
                    event.preventDefault();
                    closeFormPreview();
                });
            }

            const firstInvalid = document.querySelector('.me-data-entry .is-invalid');
            if (firstInvalid) {
                firstInvalid.focus({ preventScroll: true });
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            const builder = document.querySelector('[data-form-builder]');
            const sectionList = builder?.querySelector('[data-section-list]');
            const fieldTemplate = document.getElementById('data-entry-field-template');
            const sectionTemplate = document.getElementById('data-entry-section-template');
            const portfolioSelect = builder?.querySelector('[data-form-portfolio]');
            const componentSelect = builder?.querySelector('[data-form-component]');
            const componentDirectorate = builder?.querySelector('[data-component-directorate]');
            const templateIndicatorSelect = builder?.querySelector('[data-template-indicator]');
            const templateIndicatorHelp = builder?.querySelector('[data-template-indicator-help]');
            const builderCounts = builder?.querySelector('[data-builder-counts]');
            let sectionPalette = [];
            try {
                sectionPalette = JSON.parse(builder?.dataset.sectionPalette || '[]');
            } catch (error) {
                sectionPalette = [];
            }

            const filterTemplateComponents = () => {
                if (!componentSelect) return;

                const portfolioId = portfolioSelect?.value || '';
                const locked = componentSelect.dataset.locked === 'true';
                let selectedStillAllowed = !componentSelect.value;
                let availableCount = 0;

                Array.from(componentSelect.options).forEach((option) => {
                    if (!option.value) return;
                    const allowed = option.dataset.portfolio === portfolioId || (locked && option.selected);
                    option.hidden = !allowed;
                    option.disabled = !allowed;
                    if (allowed) availableCount++;
                    if (allowed && option.selected) selectedStillAllowed = true;
                });

                if (!locked && !selectedStillAllowed) componentSelect.value = '';
                componentSelect.disabled = locked || portfolioId === '';
                componentSelect.options[0].textContent = portfolioId === ''
                    ? 'Choose a portfolio first'
                    : (availableCount === 0 ? 'No components available for this portfolio' : 'Choose project component');

                const selected = componentSelect.selectedOptions[0];
                if (componentDirectorate) {
                    componentDirectorate.textContent = selected?.value
                        ? `Responsible Directorate: ${selected.dataset.directorate || 'Not assigned'}`
                        : 'Select a component to identify its responsible Directorate.';
                }
            };

            const filterTemplateIndicators = () => {
                if (!templateIndicatorSelect) return;

                const portfolioId = portfolioSelect?.value || '';
                const componentId = componentSelect?.value || '';
                const locked = templateIndicatorSelect.dataset.locked === 'true';
                const placeholder = templateIndicatorSelect.options[0];
                let availableCount = 0;
                let selectedStillAllowed = !templateIndicatorSelect.value;

                Array.from(templateIndicatorSelect.options).forEach((option) => {
                    if (!option.value) return;
                    const allowed = (
                        option.dataset.portfolio === portfolioId
                        && option.dataset.component === componentId
                    ) || (locked && option.selected);
                    option.hidden = !allowed;
                    option.disabled = !allowed;
                    if (allowed) availableCount++;
                    if (allowed && option.selected) selectedStillAllowed = true;
                });

                if (!locked && !selectedStillAllowed) templateIndicatorSelect.value = '';
                templateIndicatorSelect.disabled = locked || portfolioId === '' || componentId === '';

                if (placeholder) {
                    placeholder.textContent = portfolioId === ''
                        ? 'Choose a portfolio first'
                        : (componentId === ''
                            ? 'Choose a project component first'
                            : (availableCount === 0 ? 'No indicators available for this component' : 'Choose an indicator'));
                }

                if (templateIndicatorHelp) {
                    if (portfolioId === '') {
                        templateIndicatorHelp.textContent = 'Choose a portfolio first to see its available indicators.';
                    } else if (componentId === '') {
                        templateIndicatorHelp.textContent = 'Choose a project component to see its linked performance indicators.';
                    } else if (availableCount === 0) {
                        templateIndicatorHelp.textContent = 'No indicators are registered for this component. Link an indicator to the component in Results Framework and Indicator Management before saving this template.';
                    } else {
                        const countLabel = `${availableCount} ${availableCount === 1 ? 'indicator' : 'indicators'} available`;
                        templateIndicatorHelp.textContent = `${countLabel}. The linked template will open from this indicator in the think tank M&E workspace.`;
                    }
                }
            };

            const filterIndicatorOptions = (select) => {
                if (!select) return;
                const portfolioId = portfolioSelect?.value || '';
                const componentId = componentSelect?.value || '';
                let selectedStillAllowed = !select.value;

                Array.from(select.options).forEach((option) => {
                    if (!option.value) return;
                    const allowed = portfolioId !== ''
                        && componentId !== ''
                        && option.dataset.portfolio === portfolioId
                        && option.dataset.component === componentId;
                    option.hidden = !allowed;
                    option.disabled = !allowed;
                    if (allowed && option.selected) selectedStillAllowed = true;
                });

                if (!selectedStillAllowed) select.value = '';
            };

            const updateFieldRow = (row, resetDefaults = false) => {
                const type = row.querySelector('[data-field-type]')?.value || 'text';
                const numericTypes = ['integer', 'number', 'percentage', 'currency', 'rating', 'scale'];
                const mappableTypes = ['integer', 'number', 'percentage', 'currency'];
                const textTypes = ['text', 'textarea', 'email', 'phone', 'url'];
                const choiceTypes = ['select', 'radio', 'multiselect', 'checkbox'];
                const uploadTypes = ['file', 'image'];
                const numeric = numericTypes.includes(type);
                const mappable = mappableTypes.includes(type);
                const text = textTypes.includes(type);
                const choice = choiceTypes.includes(type);
                const upload = uploadTypes.includes(type);
                const unitWrap = row.querySelector('[data-unit-wrap]');
                const indicatorWrap = row.querySelector('[data-indicator-wrap]');
                const optionsWrap = row.querySelector('[data-options-wrap]');
                const numericWrap = row.querySelector('[data-numeric-settings]');
                const textWrap = row.querySelector('[data-text-settings]');
                const uploadWrap = row.querySelector('[data-upload-settings]');
                const indicatorSelect = row.querySelector('[data-indicator-select]');

                const setSettingState = (wrap, visible) => {
                    if (!wrap) return;
                    wrap.hidden = !visible;
                    wrap.querySelectorAll('input, select, textarea').forEach((control) => {
                        control.disabled = !visible;
                    });
                };

                setSettingState(unitWrap, mappable);
                setSettingState(indicatorWrap, mappable);
                setSettingState(optionsWrap, choice);
                setSettingState(numericWrap, numeric);
                setSettingState(textWrap, text);
                setSettingState(uploadWrap, upload);

                if (indicatorSelect) {
                    if (!mappable) indicatorSelect.value = '';
                    filterIndicatorOptions(indicatorSelect);
                }

                const numericDefaults = {
                    integer: { min: '', max: '', step: '1' },
                    number: { min: '', max: '', step: '' },
                    percentage: { min: '0', max: '100', step: '' },
                    currency: { min: '', max: '', step: '' },
                    rating: { min: '1', max: '5', step: '1' },
                    scale: { min: '1', max: '10', step: '1' },
                };
                if (numeric) {
                    Object.entries(numericDefaults[type]).forEach(([key, defaultValue]) => {
                        const input = numericWrap?.querySelector(`[name$="[validation][${key}]"]`);
                        if (input && (resetDefaults || input.value.trim() === '')) input.value = defaultValue;
                    });

                    const minimumInput = numericWrap?.querySelector('[data-numeric-min-input]');
                    const maximumInput = numericWrap?.querySelector('[data-numeric-max-input]');
                    const stepInput = numericWrap?.querySelector('[data-numeric-step-input]');
                    [minimumInput, maximumInput].forEach((input) => {
                        if (!input) return;
                        if (type === 'rating') {
                            input.min = '1';
                            input.max = '10';
                            input.step = '1';
                        } else {
                            input.removeAttribute('min');
                            input.removeAttribute('max');
                            input.step = 'any';
                        }
                    });
                    if (stepInput) {
                        stepInput.min = type === 'rating' ? '1' : '0';
                        stepInput.step = type === 'rating' ? '1' : 'any';
                        if (type === 'rating') stepInput.max = '10';
                        else stepInput.removeAttribute('max');
                    }
                }

                if (text) {
                    const textLengthCaps = { text: 20000, textarea: 20000, email: 255, phone: 30, url: 2048 };
                    const lengthCap = textLengthCaps[type];
                    const minLengthInput = textWrap?.querySelector('[data-min-length-input]');
                    const maxLengthInput = textWrap?.querySelector('[data-max-length-input]');
                    [minLengthInput, maxLengthInput].forEach((input) => {
                        if (!input) return;
                        input.max = String(lengthCap);
                        if (resetDefaults && input.value !== '' && Number(input.value) > lengthCap) {
                            input.value = String(lengthCap);
                        }
                    });
                    const textLimitHelp = textWrap?.querySelector('[data-text-limit-help]');
                    if (textLimitHelp) {
                        textLimitHelp.textContent = `Portal hard limit: ${lengthCap.toLocaleString()} characters. Leave maximum blank to use this limit.`;
                    }
                }

                const unitInput = unitWrap?.querySelector('input');
                if (type === 'percentage' && unitInput && (resetDefaults || unitInput.value.trim() === '')) {
                    unitInput.value = '%';
                }

                if (choice) {
                    const help = optionsWrap?.querySelector('[data-options-help]');
                    if (help) {
                        help.textContent = ['select', 'radio'].includes(type)
                            ? 'Add at least two options, one per line.'
                            : 'Add at least one option, one per line.';
                    }
                }

                if (upload) {
                    const extensionInput = uploadWrap?.querySelector('[data-extension-input]');
                    const sizeInput = uploadWrap?.querySelector('[data-file-size-input]');
                    const multipleInput = uploadWrap?.querySelector('input[type="checkbox"][name$="[validation][multiple]"]');
                    const extensions = type === 'image'
                        ? 'jpg, jpeg, png, webp, gif'
                        : 'pdf, doc, docx, xls, xlsx, csv, txt';
                    if (extensionInput) {
                        extensionInput.placeholder = extensions;
                        if (resetDefaults || extensionInput.value.trim() === '') extensionInput.value = extensions;
                    }
                    if (sizeInput && (resetDefaults || sizeInput.value.trim() === '')) sizeInput.value = '10';
                    if (multipleInput && resetDefaults) multipleInput.checked = false;
                }

                row.dataset.fieldType = type;
            };

            const newSectionKey = () => `section_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;

            const updateSectionColour = (section) => {
                const colourInput = section.querySelector('[data-section-color]');
                if (!colourInput) return;
                const colour = colourInput.value.toUpperCase();
                section.style.setProperty('--section-color', colour);
                section.querySelectorAll('[data-color-preset]').forEach((preset) => {
                    preset.classList.toggle('is-selected', preset.dataset.colorPreset.toUpperCase() === colour);
                });
            };

            const reindexBuilder = () => {
                if (!sectionList) return;
                const sections = Array.from(sectionList.querySelectorAll(':scope > [data-section-card]'));
                let fieldIndex = 0;

                sections.forEach((section, sectionIndex) => {
                    const sectionKeyInput = section.querySelector('[data-section-key]');
                    const sectionKey = sectionKeyInput?.value || newSectionKey();
                    if (sectionKeyInput && !sectionKeyInput.value) sectionKeyInput.value = sectionKey;
                    const number = section.querySelector('[data-section-number]');
                    if (number) number.textContent = String(sectionIndex + 1);
                    section.querySelectorAll('[name^="sections["]').forEach((input) => {
                        input.name = input.name.replace(/sections\[[^\]]+\]/, `sections[${sectionIndex}]`);
                    });
                    const sectionSort = section.querySelector('[data-section-sort-order]');
                    if (sectionSort) sectionSort.value = String((sectionIndex + 1) * 10);

                    const fields = Array.from(section.querySelectorAll('[data-section-fields] > [data-field-row]'));
                    fields.forEach((row, fieldPosition) => {
                        const fieldNumber = row.querySelector('[data-field-number]');
                        if (fieldNumber) fieldNumber.textContent = String(fieldPosition + 1);
                        row.querySelectorAll('[name]').forEach((input) => {
                            input.name = input.name.replace(/fields\[[^\]]+\]/, `fields[${fieldIndex}]`);
                        });
                        const fieldSectionKeyInput = row.querySelector('[data-field-section-key]');
                        if (fieldSectionKeyInput) fieldSectionKeyInput.value = sectionKey;
                        ['required', 'multiple'].forEach((controlName) => {
                            row.querySelectorAll(`[id^="field-${controlName}-"]`).forEach((input) => {
                                input.id = `field-${controlName}-${fieldIndex}`;
                            });
                            row.querySelectorAll(`label[for^="field-${controlName}-"]`).forEach((label) => {
                                label.htmlFor = `field-${controlName}-${fieldIndex}`;
                            });
                        });
                        const sortInput = row.querySelector('[data-sort-order]');
                        if (sortInput) sortInput.value = String((fieldPosition + 1) * 10);
                        const upButton = row.querySelector('[data-move-field="up"]');
                        const downButton = row.querySelector('[data-move-field="down"]');
                        if (upButton) upButton.disabled = fieldPosition === 0;
                        if (downButton) downButton.disabled = fieldPosition === fields.length - 1;
                        fieldIndex++;
                    });

                    const sectionUp = section.querySelector('[data-move-section="up"]');
                    const sectionDown = section.querySelector('[data-move-section="down"]');
                    const sectionRemove = section.querySelector('[data-remove-section]');
                    if (sectionUp) sectionUp.disabled = sectionIndex === 0;
                    if (sectionDown) sectionDown.disabled = sectionIndex === sections.length - 1;
                    if (sectionRemove) sectionRemove.disabled = sections.length === 1;
                    updateSectionColour(section);
                });

                if (builderCounts) {
                    const questionCount = sectionList.querySelectorAll('[data-field-row]').length;
                    builderCounts.textContent = `${sections.length} ${sections.length === 1 ? 'section' : 'sections'} · ${questionCount} ${questionCount === 1 ? 'question' : 'questions'}`;
                }
            };

            const addQuestion = (section, focus = true) => {
                const fieldContainer = section.querySelector('[data-section-fields]');
                const sectionKey = section.querySelector('[data-section-key]')?.value || newSectionKey();
                if (!fieldContainer || !fieldTemplate) return;
                if (builder.querySelectorAll('[data-field-row]').length >= 100) {
                    window.alert('A form can contain up to 100 questions.');
                    return;
                }
                const index = builder.querySelectorAll('[data-field-row]').length;
                const wrapper = document.createElement('div');
                wrapper.innerHTML = fieldTemplate.innerHTML
                    .replaceAll('__INDEX__', String(index))
                    .replaceAll('__SECTION_KEY__', sectionKey)
                    .trim();
                const row = wrapper.firstElementChild;
                fieldContainer.appendChild(row);
                updateFieldRow(row, true);
                reindexBuilder();
                if (focus) row.querySelector('input[name$="[label]"]')?.focus();
            };

            if (sectionList) {
                filterTemplateComponents();
                filterTemplateIndicators();
                sectionList.querySelectorAll('[data-field-row]').forEach(updateFieldRow);
                reindexBuilder();

                builder.querySelector('[data-add-section]')?.addEventListener('click', () => {
                    if (!sectionTemplate) return;
                    const sectionIndex = sectionList.querySelectorAll(':scope > [data-section-card]').length;
                    if (sectionIndex >= 30) {
                        window.alert('A form can contain up to 30 sections.');
                        return;
                    }
                    if (builder.querySelectorAll('[data-field-row]').length >= 100) {
                        window.alert('Every new section starts with a question, and this form already has the maximum of 100 questions.');
                        return;
                    }
                    const sectionKey = newSectionKey();
                    const colour = sectionPalette[sectionIndex % sectionPalette.length] || '#EFF6FF';
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = sectionTemplate.innerHTML
                        .replaceAll('__SECTION_INDEX__', String(sectionIndex))
                        .replaceAll('__SECTION_KEY__', sectionKey)
                        .replaceAll('__SECTION_COLOR__', colour)
                        .trim();
                    const section = wrapper.firstElementChild;
                    sectionList.appendChild(section);
                    addQuestion(section, false);
                    reindexBuilder();
                    section.querySelector('[data-section-name]')?.focus();
                });

                sectionList.addEventListener('change', (event) => {
                    if (event.target.matches('[data-field-type]')) {
                        updateFieldRow(event.target.closest('[data-field-row]'), true);
                    }
                    if (event.target.matches('[data-section-color]')) {
                        updateSectionColour(event.target.closest('[data-section-card]'));
                    }
                });

                sectionList.addEventListener('click', (event) => {
                    const section = event.target.closest('[data-section-card]');
                    if (!section) return;

                    const preset = event.target.closest('[data-color-preset]');
                    if (preset) {
                        const colourInput = section.querySelector('[data-section-color]');
                        if (colourInput) colourInput.value = preset.dataset.colorPreset;
                        updateSectionColour(section);
                        return;
                    }

                    if (event.target.closest('[data-add-field]')) {
                        addQuestion(section);
                        return;
                    }

                    const removeField = event.target.closest('[data-remove-field]');
                    const moveField = event.target.closest('[data-move-field]');
                    const row = event.target.closest('[data-field-row]');
                    const fieldContainer = section.querySelector('[data-section-fields]');
                    if (removeField && row) {
                        if (fieldContainer.querySelectorAll(':scope > [data-field-row]').length === 1) {
                            window.alert('Each section needs at least one question. Add another question before removing this one.');
                            return;
                        }
                        row.remove();
                        reindexBuilder();
                        return;
                    }
                    if (moveField && row) {
                        const sibling = moveField.dataset.moveField === 'up' ? row.previousElementSibling : row.nextElementSibling;
                        if (!sibling) return;
                        if (moveField.dataset.moveField === 'up') fieldContainer.insertBefore(row, sibling);
                        else fieldContainer.insertBefore(sibling, row);
                        reindexBuilder();
                        return;
                    }

                    const removeSection = event.target.closest('[data-remove-section]');
                    if (removeSection) {
                        if (sectionList.querySelectorAll(':scope > [data-section-card]').length === 1) {
                            window.alert('A form needs at least one section.');
                            return;
                        }
                        const sectionName = section.querySelector('[data-section-name]')?.value.trim() || 'this section';
                        if (!window.confirm(`Remove ${sectionName} and all of its questions?`)) return;
                        section.remove();
                        reindexBuilder();
                        return;
                    }

                    const moveSection = event.target.closest('[data-move-section]');
                    if (moveSection) {
                        const sibling = moveSection.dataset.moveSection === 'up' ? section.previousElementSibling : section.nextElementSibling;
                        if (!sibling) return;
                        if (moveSection.dataset.moveSection === 'up') sectionList.insertBefore(section, sibling);
                        else sectionList.insertBefore(sibling, section);
                        reindexBuilder();
                    }
                });

                portfolioSelect?.addEventListener('change', () => {
                    filterTemplateComponents();
                    filterTemplateIndicators();
                    sectionList.querySelectorAll('[data-indicator-select]').forEach(filterIndicatorOptions);
                });
                componentSelect?.addEventListener('change', () => {
                    filterTemplateComponents();
                    filterTemplateIndicators();
                    sectionList.querySelectorAll('[data-indicator-select]').forEach(filterIndicatorOptions);
                });
                builder.addEventListener('submit', reindexBuilder);
            }

            const periodForm = document.querySelector('[data-period-form]');
            if (periodForm) {
                const periodStart = periodForm.querySelector('[name="period_start"]');
                const periodEnd = periodForm.querySelector('[name="period_end"]');
                const submissionOpens = periodForm.querySelector('[name="submission_opens_at"]');
                const submissionDeadline = periodForm.querySelector('[name="submission_deadline"]');
                const reviewDeadline = periodForm.querySelector('[name="review_deadline"]');
                const updatePeriodDateLimits = () => {
                    if (periodEnd) periodEnd.min = periodStart?.value || '';
                    if (submissionOpens) submissionOpens.min = periodStart?.value ? `${periodStart.value}T00:00` : '';
                    if (submissionDeadline) submissionDeadline.min = submissionOpens?.value || (periodStart?.value ? `${periodStart.value}T00:00` : '');
                    if (reviewDeadline) reviewDeadline.min = submissionDeadline?.value || (periodEnd?.value ? `${periodEnd.value}T00:00` : '');
                };
                [periodStart, periodEnd, submissionOpens, submissionDeadline].forEach((control) => control?.addEventListener('change', updatePeriodDateLimits));
                updatePeriodDateLimits();
            }

            const collectionForm = document.querySelector('[data-collection-form]');
            const collectionTemplate = collectionForm?.querySelector('[data-collection-template]');
            const collectionPeriod = collectionForm?.querySelector('[data-collection-period]');
            const collectionPeriodHelp = collectionForm?.querySelector('[data-period-help]');
            const filterPeriods = () => {
                if (!collectionTemplate || !collectionPeriod) return;
                const selectedFormOption = collectionTemplate.options[collectionTemplate.selectedIndex];
                const portfolioId = selectedFormOption?.dataset.portfolio || '';
                let selectedStillAllowed = !collectionPeriod.value;
                let availableCount = 0;

                Array.from(collectionPeriod.options).forEach((option) => {
                    if (!option.value) return;
                    const allowed = portfolioId !== '' && option.dataset.portfolio === portfolioId;
                    option.hidden = !allowed;
                    option.disabled = !allowed;
                    if (allowed) availableCount++;
                    if (allowed && option.selected) selectedStillAllowed = true;
                });

                if (!selectedStillAllowed) collectionPeriod.value = '';
                collectionPeriod.disabled = portfolioId === '';
                collectionPeriod.options[0].textContent = portfolioId === ''
                    ? 'Choose a form template first'
                    : (availableCount === 0 ? 'No open periods for this portfolio' : 'Choose reporting period');
                if (collectionPeriodHelp) {
                    collectionPeriodHelp.textContent = portfolioId === ''
                        ? 'Select a form first to show periods from the same portfolio.'
                        : (availableCount === 0
                            ? 'This portfolio has no open reporting period. Create or open a period before saving the collection.'
                            : `${availableCount} ${availableCount === 1 ? 'open period is' : 'open periods are'} available for the selected form.`);
                }
            };
            if (collectionForm) {
                filterPeriods();
                collectionTemplate?.addEventListener('change', filterPeriods);

                const opensAt = collectionForm.querySelector('[name="opens_at"]');
                const dueAt = collectionForm.querySelector('[name="due_at"]');
                const closesAt = collectionForm.querySelector('[name="closes_at"]');
                const updateDateLimits = () => {
                    if (opensAt && dueAt) dueAt.min = opensAt.value;
                    if (closesAt) closesAt.min = dueAt?.value || opensAt?.value || '';
                };
                opensAt?.addEventListener('change', updateDateLimits);
                dueAt?.addEventListener('change', updateDateLimits);
                updateDateLimits();
            }

            const memberSearch = document.querySelector('[data-member-search]');
            const memberList = document.querySelector('[data-member-list]');
            const memberCount = document.querySelector('[data-member-count]');
            const memberOptions = () => Array.from(document.querySelectorAll('[data-member-option]'));
            const updateMemberCount = () => {
                if (!memberCount) return;
                const options = memberOptions();
                const selected = options.filter((option) => option.querySelector('input[type="checkbox"]')?.checked).length;
                const visible = options.filter((option) => !option.classList.contains('is-hidden')).length;
                memberCount.textContent = `${selected} ${selected === 1 ? 'think tank' : 'think tanks'} selected · ${visible} shown`;
            };
            const filterMembers = () => {
                if (!memberSearch) return;
                const term = memberSearch.value.trim().toLocaleLowerCase();
                memberOptions().forEach((option) => {
                    option.classList.toggle('is-hidden', term !== '' && !option.dataset.search.includes(term));
                });
                updateMemberCount();
            };
            memberSearch?.addEventListener('input', filterMembers);
            memberList?.addEventListener('change', updateMemberCount);
            document.querySelector('[data-member-select-visible]')?.addEventListener('click', () => {
                memberOptions().filter((option) => !option.classList.contains('is-hidden')).forEach((option) => {
                    const checkbox = option.querySelector('input[type="checkbox"]');
                    if (checkbox && !checkbox.disabled) checkbox.checked = true;
                });
                updateMemberCount();
            });
            document.querySelector('[data-member-clear-visible]')?.addEventListener('click', () => {
                memberOptions().filter((option) => !option.classList.contains('is-hidden')).forEach((option) => {
                    const checkbox = option.querySelector('input[type="checkbox"]');
                    if (checkbox && !checkbox.disabled) checkbox.checked = false;
                });
                updateMemberCount();
            });
            updateMemberCount();
            };

            const dataEntryUrl = new URL(@json(route('budget.me.rebuild.data-entry')), window.location.origin);
            const fragmentCache = new Map();
            const fragmentCacheLifetime = 30000;
            let activeRequest = null;
            let navigationSequence = 0;

            const navigationStatus = () => {
                let status = document.getElementById('me-data-entry-navigation-status');
                if (!status) {
                    status = document.createElement('div');
                    status.id = 'me-data-entry-navigation-status';
                    status.className = 'visually-hidden';
                    status.setAttribute('role', 'status');
                    status.setAttribute('aria-live', 'polite');
                    status.setAttribute('aria-atomic', 'true');
                    document.body.appendChild(status);
                }
                return status;
            };

            const announceNavigation = (message) => {
                const status = navigationStatus();
                status.textContent = '';
                window.requestAnimationFrame(() => {
                    status.textContent = message;
                });
            };

            const normalizedDataEntryUrl = (destination) => {
                const url = new URL(destination, window.location.href);
                url.searchParams.delete('fragment');
                return url;
            };

            const isDataEntryUrl = (url) => (
                url.origin === window.location.origin
                && url.pathname === dataEntryUrl.pathname
            );

            const fragmentFromHtml = (html) => {
                const parsed = new DOMParser().parseFromString(html, 'text/html');
                return parsed.querySelector('[data-me-data-entry-fragment]');
            };

            const cachedFragment = (key) => {
                const cached = fragmentCache.get(key);
                if (!cached || Date.now() - cached.storedAt > fragmentCacheLifetime) {
                    fragmentCache.delete(key);
                    return null;
                }
                return fragmentFromHtml(cached.html);
            };

            const requestFragment = async (url, signal) => {
                const key = `${url.pathname}${url.search}`;
                const cached = cachedFragment(key);
                if (cached) return cached;

                const requestUrl = new URL(url.href);
                requestUrl.hash = '';
                requestUrl.searchParams.set('fragment', '1');

                const response = await fetch(requestUrl.href, {
                    method: 'GET',
                    credentials: 'same-origin',
                    signal,
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-ME-Fragment': 'data-entry',
                    },
                });

                if (!response.ok) {
                    throw new Error(`The data-entry tab request failed with status ${response.status}.`);
                }

                const fragment = fragmentFromHtml(await response.text());
                if (!fragment) {
                    throw new Error('The data-entry tab response did not contain the expected workspace.');
                }

                fragmentCache.set(key, {
                    html: fragment.outerHTML,
                    storedAt: Date.now(),
                });

                return fragment;
            };

            const saveCurrentScrollPosition = () => {
                window.history.replaceState({
                    ...(window.history.state || {}),
                    meDataEntry: true,
                    scrollY: window.scrollY,
                }, '', window.location.href);
            };

            const scrollAfterNavigation = (url, scrollTarget, restoreScroll) => {
                window.requestAnimationFrame(() => {
                    if (Number.isFinite(restoreScroll)) {
                        window.scrollTo({ top: restoreScroll, left: 0, behavior: 'auto' });
                        return;
                    }

                    let target = null;
                    if (url.hash) {
                        try {
                            target = document.getElementById(decodeURIComponent(url.hash.slice(1)));
                        } catch (error) {
                            target = null;
                        }
                    }
                    target = target || (scrollTarget ? document.querySelector(scrollTarget) : null);
                    target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            };

            const navigateDataEntry = async (destination, options = {}) => {
                const url = normalizedDataEntryUrl(destination);
                if (!isDataEntryUrl(url)) {
                    window.location.assign(url.href);
                    return;
                }

                const sequence = ++navigationSequence;
                activeRequest?.abort();
                activeRequest = new AbortController();

                const currentFragment = document.querySelector('[data-me-data-entry-fragment]');
                currentFragment?.classList.add('is-loading');
                currentFragment?.setAttribute('aria-busy', 'true');
                document.body.classList.add('me-data-entry-ajax-loading');
                announceNavigation('Loading M&E data-entry workspace.');

                try {
                    const replacement = await requestFragment(url, activeRequest.signal);
                    if (sequence !== navigationSequence) return;

                    const heroTemplate = replacement.querySelector('[data-me-data-entry-hero-action-template]');
                    const heroSlot = document.querySelector('[data-me-data-entry-hero-action]');
                    if (heroTemplate && heroSlot) heroSlot.innerHTML = heroTemplate.innerHTML;
                    heroTemplate?.remove();

                    const existing = document.querySelector('[data-me-data-entry-fragment]');
                    if (!existing) throw new Error('The current data-entry workspace is unavailable.');
                    existing.replaceWith(replacement);

                    document.title = replacement.dataset.pageTitle || document.title;
                    if (options.history !== false) {
                        saveCurrentScrollPosition();
                        window.history.pushState({ meDataEntry: true, scrollY: 0 }, '', url.href);
                    }

                    initializeWorkspace();
                    initializeDirtyState();
                    scrollAfterNavigation(url, options.scrollTarget, options.restoreScroll);
                    if (options.focusActiveTab) {
                        replacement.querySelector('.me-tab.active')?.focus({ preventScroll: true });
                    }
                    announceNavigation(`${replacement.dataset.pageTitle || 'M&E data-entry workspace'} loaded.`);
                } catch (error) {
                    if (error.name === 'AbortError') return;
                    window.location.assign(url.href);
                } finally {
                    if (sequence === navigationSequence) {
                        activeRequest = null;
                        document.body.classList.remove('me-data-entry-ajax-loading');
                        document.querySelector('[data-me-data-entry-fragment]')?.classList.remove('is-loading');
                        document.querySelector('[data-me-data-entry-fragment]')?.removeAttribute('aria-busy');
                    }
                }
            };

            const hasUnsavedWorkspaceChanges = () => (
                document.querySelector('#data-entry-workspace form[data-me-form-dirty="true"]') !== null
            );

            const confirmWorkspaceNavigation = () => (
                !hasUnsavedWorkspaceChanges()
                || window.confirm('You have unsaved changes in this form. Leave this tab and discard them?')
            );

            const initializeDirtyState = () => {
                document.querySelectorAll('#data-entry-workspace form').forEach((form) => {
                    if (form.method.toUpperCase() === 'GET' || form.dataset.dirtyInitialized === 'true') return;
                    form.dataset.dirtyInitialized = 'true';
                    const markDirty = () => { form.dataset.meFormDirty = 'true'; };
                    form.addEventListener('input', markDirty);
                    form.addEventListener('change', markDirty);
                    form.addEventListener('submit', () => { form.dataset.meFormDirty = 'false'; });
                });
            };

            const initializeAsyncNavigation = () => {
                if (!window.fetch || !window.DOMParser || !window.history?.pushState) return;

                document.addEventListener('click', (event) => {
                    if (
                        event.defaultPrevented
                        || event.button !== 0
                        || event.metaKey
                        || event.ctrlKey
                        || event.shiftKey
                        || event.altKey
                    ) return;

                    const link = event.target.closest('a[href]');
                    if (
                        !link
                        || !link.closest('.me-data-entry')
                        || link.target
                        || link.hasAttribute('download')
                        || link.dataset.noAjax === 'true'
                    ) return;

                    const url = normalizedDataEntryUrl(link.href);
                    if (!isDataEntryUrl(url)) return;
                    if (!confirmWorkspaceNavigation()) {
                        event.preventDefault();
                        return;
                    }

                    event.preventDefault();
                    const isTab = link.closest('.me-tabs') !== null;
                    const isPagination = link.closest('.me-pagination-wrap') !== null;
                    navigateDataEntry(url, {
                        focusActiveTab: isTab,
                        scrollTarget: isTab ? '.me-tabs' : (isPagination ? '#data-entry-register-title' : '#data-entry-workspace'),
                    });
                });

                document.addEventListener('submit', (event) => {
                    const form = event.target.closest('form');
                    if (
                        !form
                        || !form.closest('[data-me-data-entry-fragment]')
                        || form.method.toUpperCase() !== 'GET'
                    ) return;

                    const url = normalizedDataEntryUrl(form.action || dataEntryUrl.href);
                    if (!isDataEntryUrl(url)) return;

                    event.preventDefault();
                    url.search = '';
                    new FormData(form).forEach((value, name) => {
                        if (typeof value === 'string') url.searchParams.append(name, value);
                    });
                    navigateDataEntry(url, { scrollTarget: '#data-entry-register-title' });
                });

                window.addEventListener('popstate', (event) => {
                    const url = normalizedDataEntryUrl(window.location.href);
                    if (!isDataEntryUrl(url)) return;
                    navigateDataEntry(url, {
                        history: false,
                        restoreScroll: Number.isFinite(event.state?.scrollY) ? event.state.scrollY : 0,
                    });
                });

                window.addEventListener('beforeunload', (event) => {
                    if (!hasUnsavedWorkspaceChanges()) return;
                    event.preventDefault();
                    event.returnValue = '';
                });

                saveCurrentScrollPosition();
            };

            const bootDataEntryPage = () => {
                initializeWorkspace();
                initializeDirtyState();
                initializeAsyncNavigation();
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootDataEntryPage, { once: true });
            } else {
                bootDataEntryPage();
            }
        })();
    </script>
@endpush
@endunless
