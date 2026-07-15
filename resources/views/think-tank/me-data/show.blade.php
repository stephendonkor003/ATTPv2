@push('styles')
    <style>
        .tt-me-form {
            --me-ink: #172033;
            --me-muted: #64748b;
            --me-line: #dfe7e3;
            --me-primary: #176b55;
            --me-soft: #edf8f3;
        }

        .think-tank-workspace > .card.shadow-sm.border-0.overflow-hidden.mb-4 {
            display: none;
        }

        .tt-me-form .me-back-link {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: .8rem;
            color: #475569;
            font-size: .8rem;
            font-weight: 700;
            text-decoration: none;
        }

        .tt-me-form .me-back-link:hover,
        .tt-me-form .me-back-link:focus {
            color: var(--me-primary);
        }

        .tt-me-form .me-form-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1.25rem;
            padding: 1.3rem;
            color: #fff;
            border-radius: 10px;
            background: #1d5b47;
            box-shadow: none;
        }

        .tt-me-form .me-form-code {
            display: inline-flex;
            margin-bottom: .4rem;
            color: #baf1dd;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .tt-me-form .me-form-hero h4 {
            margin: 0;
            color: #fff;
            font-weight: 800;
        }

        .tt-me-form .me-form-hero p {
            max-width: 760px;
            margin: .5rem 0 0;
            color: rgba(255, 255, 255, .82);
            font-size: .84rem;
            line-height: 1.55;
        }

        .tt-me-form .me-template-label {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .45rem;
            margin-top: .55rem;
            color: rgba(255, 255, 255, .8);
            font-size: .75rem;
        }

        .tt-me-form .me-template-label span {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .28rem .48rem;
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 6px;
            background: rgba(255, 255, 255, .08);
        }

        .tt-me-form .me-status-box {
            min-width: 205px;
            padding: .85rem;
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 8px;
            background: rgba(9, 38, 31, .24);
        }

        .tt-me-form .me-status-box .status-label {
            color: #baf1dd;
            font-size: .65rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .tt-me-form .me-status-box strong {
            display: block;
            margin-top: .25rem;
            font-size: .9rem;
        }

        .tt-me-form .me-overview-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .75rem;
            margin: .9rem 0;
        }

        .tt-me-form .me-overview-item {
            padding: .85rem .95rem;
            border: 1px solid var(--me-line);
            border-radius: 8px;
            background: #fff;
        }

        .tt-me-form .me-overview-item span {
            display: block;
            color: var(--me-muted);
            font-size: .66rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .tt-me-form .me-overview-item strong {
            display: block;
            margin-top: .32rem;
            color: var(--me-ink);
            font-size: .82rem;
        }

        .tt-me-form .me-progress-shell {
            padding: .85rem 1rem;
            border: 1px solid var(--me-line);
            border-radius: 8px;
            background: #fff;
        }

        .tt-me-form .me-progress-head {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            color: #475569;
            font-size: .75rem;
            font-weight: 700;
        }

        .tt-me-form .me-progress-track {
            height: 7px;
            margin-top: .55rem;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .tt-me-form .me-progress-track span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--me-primary);
        }

        .tt-me-form .me-guidance {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            margin-top: .85rem;
            padding: .9rem 1rem;
            color: #334155;
            border: 1px solid #cfe7dc;
            border-radius: 8px;
            background: #f4fbf7;
            font-size: .8rem;
            line-height: 1.55;
        }

        .tt-me-form .me-guidance i {
            margin-top: .12rem;
            color: var(--me-primary);
        }

        .tt-me-form .me-returned {
            margin-top: .85rem;
            padding: .9rem 1rem;
            color: #78350f;
            border: 1px solid #f4cf80;
            border-radius: 8px;
            background: #fffbeb;
            font-size: .8rem;
        }

        .tt-me-form .me-section-card {
            --section-bg: #eff6ff;
            --section-header: #e7eef8;
            --section-border: #a8b3c2;
            --section-accent: #172033;
            --section-text: #172033;
            margin-top: .9rem;
            overflow: hidden;
            border: 1px solid var(--section-border);
            border-inline-start-width: 4px;
            border-radius: 10px;
            background: var(--section-bg);
            box-shadow: none;
        }

        .tt-me-form .me-section-heading {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: 1rem;
            color: var(--section-text);
            border-bottom: 1px solid var(--section-border);
            background: var(--section-header);
        }

        .tt-me-form .me-section-number {
            display: inline-flex;
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            align-items: center;
            justify-content: center;
            color: #fff;
            border-radius: 7px;
            background: var(--section-accent);
            box-shadow: none;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .03em;
        }

        .tt-me-form .me-section-heading-copy {
            min-width: 0;
        }

        .tt-me-form .me-section-kicker {
            margin-bottom: .18rem;
            color: var(--section-accent);
            font-size: .62rem;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .tt-me-form .me-section-title {
            margin: 0;
            color: var(--section-text);
            font-size: .88rem;
            font-weight: 800;
        }

        .tt-me-form .me-section-guidance {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            margin: .85rem 1rem 0;
            padding: .8rem .9rem;
            color: var(--section-text);
            border: 1px solid var(--section-border);
            border-radius: 11px;
            background: rgba(255, 255, 255, .82);
            box-shadow: none;
        }

        .tt-me-form .me-section-guidance-icon {
            display: inline-flex;
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            align-items: center;
            justify-content: center;
            color: #fff;
            border-radius: 9px;
            background: var(--section-accent);
            font-size: .78rem;
        }

        .tt-me-form .me-section-guidance strong {
            display: block;
            margin-bottom: .16rem;
            color: var(--section-text);
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .025em;
        }

        .tt-me-form .me-section-guidance p {
            max-width: 880px;
            margin: 0;
            color: #475569;
            font-size: .74rem;
            line-height: 1.55;
        }

        .tt-me-form .me-fields-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            padding: 1rem;
            background: var(--section-bg);
        }

        .tt-me-form .me-field {
            min-width: 0;
            padding: .85rem;
            border: 1px solid rgba(148, 163, 184, .3);
            border-radius: 11px;
            background: rgba(255, 255, 255, .84);
        }

        .tt-me-form .me-field.field-wide,
        .tt-me-form .me-field.field-checkbox,
        .tt-me-form .me-field.field-upload {
            grid-column: 1 / -1;
        }

        .tt-me-form .me-field label,
        .tt-me-form .me-field-label {
            display: flex;
            align-items: center;
            gap: .35rem;
            margin-bottom: .38rem;
            color: #243244;
            font-size: .78rem;
            font-weight: 800;
        }

        .tt-me-form .required-mark {
            color: #dc2626;
        }

        .tt-me-form .unit-label {
            color: var(--me-muted);
            font-size: .68rem;
            font-weight: 600;
        }

        .tt-me-form .me-help {
            margin: -.15rem 0 .45rem;
            color: var(--me-muted);
            font-size: .7rem;
            line-height: 1.45;
        }

        .tt-me-form .me-field input,
        .tt-me-form .me-field select,
        .tt-me-form .me-field textarea,
        .tt-me-form .me-notes textarea {
            border-color: #cdd9d3;
            border-radius: 9px;
            padding: .65rem .72rem;
            font-size: .82rem;
        }

        .tt-me-form .me-field input:focus,
        .tt-me-form .me-field select:focus,
        .tt-me-form .me-field textarea:focus,
        .tt-me-form .me-notes textarea:focus {
            border-color: #47a98b;
            box-shadow: 0 0 0 .18rem rgba(23, 107, 85, .12);
        }

        .tt-me-form .me-field textarea {
            min-height: 112px;
        }

        .tt-me-form .me-choice-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .5rem;
        }

        .tt-me-form .me-choice {
            display: flex;
            align-items: center;
            gap: .55rem;
            margin: 0;
            padding: .65rem .7rem;
            color: #334155;
            border: 1px solid #d8e2dd;
            border-radius: 9px;
            background: #fbfdfc;
            font-size: .76rem;
            font-weight: 600;
        }

        .tt-me-form .me-choice input {
            width: auto;
            margin: 0;
            padding: 0;
        }

        .tt-me-form .me-choice:has(input:checked) {
            color: #14532d;
            border-color: #75bda4;
            background: #effaf5;
        }

        .tt-me-form .me-multiselect {
            min-height: 120px;
        }

        .tt-me-form .me-rating-grid {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
        }

        .tt-me-form .me-rating-option {
            position: relative;
            margin: 0;
        }

        .tt-me-form .me-rating-option input {
            position: absolute;
            inline-size: 1px;
            block-size: 1px;
            opacity: 0;
        }

        .tt-me-form .me-rating-option span {
            display: inline-flex;
            min-width: 44px;
            align-items: center;
            justify-content: center;
            gap: .28rem;
            padding: .58rem .62rem;
            color: #64748b;
            border: 1px solid #d8e2dd;
            border-radius: 9px;
            background: #fbfdfc;
            cursor: pointer;
            font-size: .76rem;
            font-weight: 800;
        }

        .tt-me-form .me-rating-option input:checked + span {
            color: #92400e;
            border-color: #f2bd58;
            background: #fffbeb;
        }

        .tt-me-form .me-rating-option input:focus-visible + span {
            outline: 3px solid rgba(23, 107, 85, .18);
            outline-offset: 2px;
        }

        .tt-me-form .me-scale-wrap {
            display: grid;
            grid-template-columns: auto minmax(180px, 1fr) auto;
            align-items: center;
            gap: .7rem;
            padding: .72rem;
            border: 1px solid #d8e2dd;
            border-radius: 9px;
            background: #fbfdfc;
        }

        .tt-me-form .me-scale-wrap input[type="range"] {
            padding: 0;
        }

        .tt-me-form .me-scale-wrap output {
            min-width: 74px;
            padding: .28rem .5rem;
            color: #14532d;
            border-radius: 7px;
            background: #e7f7ef;
            text-align: center;
            font-size: .72rem;
            font-weight: 800;
        }

        .tt-me-form .me-upload-box {
            padding: .8rem;
            border: 1px dashed #9dbdb1;
            border-radius: 11px;
            background: #f8fcfa;
        }

        .tt-me-form .me-upload-box input[type="file"] {
            background: #fff;
        }

        .tt-me-form .me-upload-rules {
            margin-top: .42rem;
            color: var(--me-muted);
            font-size: .68rem;
        }

        .tt-me-form .me-file-list {
            display: grid;
            gap: .45rem;
            margin-top: .55rem;
        }

        .tt-me-form .me-file-item {
            display: flex;
            min-width: 0;
            align-items: center;
            justify-content: space-between;
            gap: .7rem;
            padding: .58rem .65rem;
            color: #334155;
            border: 1px solid #dce7e2;
            border-radius: 9px;
            background: #fff;
            font-size: .72rem;
        }

        .tt-me-form .me-file-name {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tt-me-form .me-file-item a {
            flex: 0 0 auto;
            color: var(--me-primary);
            font-weight: 800;
            text-decoration: none;
        }

        .tt-me-form .me-read-value {
            min-height: 42px;
            padding: .68rem .75rem;
            color: #243244;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: #f8fafc;
            font-size: .8rem;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .tt-me-form .me-read-value.empty {
            color: #94a3b8;
            font-style: italic;
        }

        .tt-me-form .me-notes {
            margin-top: .9rem;
            padding: 1rem;
            border: 1px solid var(--me-line);
            border-radius: 10px;
            background: #fff;
        }

        .tt-me-form .me-notes textarea {
            min-height: 90px;
        }

        .tt-me-form .me-actions {
            position: sticky;
            bottom: 0;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem;
            padding: .85rem 1rem;
            border: 1px solid #cfe0d8;
            border-radius: 10px;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 -2px 10px rgba(15, 23, 42, .04);
            backdrop-filter: blur(8px);
        }

        .tt-me-form .me-actions p {
            margin: 0;
            color: var(--me-muted);
            font-size: .72rem;
        }

        .me-confirm-dialog {
            width: min(92vw, 540px);
            max-height: min(86vh, 680px);
            margin: auto;
            overflow: auto;
            border: 0;
            border-radius: 11px;
            background: #fff;
            color: #1b2821;
            box-shadow: 0 22px 64px rgba(17, 39, 27, .2);
            padding: 0;
        }

        .me-confirm-dialog::backdrop {
            background: rgba(20, 31, 25, .48);
            backdrop-filter: blur(2px);
        }

        .me-confirm-dialog .me-confirm-head {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: 1rem 1.1rem;
            border-bottom: 1px solid #e1e8e4;
        }

        .me-confirm-dialog .me-confirm-icon {
            width: 36px;
            height: 36px;
            display: inline-grid;
            place-items: center;
            flex: 0 0 36px;
            border-radius: 50%;
            background: #e9f4ee;
            color: #176b4b;
        }

        .me-confirm-dialog .me-confirm-head h2 {
            margin: 0;
            color: #1b2821;
            font-size: 1rem;
            font-weight: 780;
        }

        .me-confirm-dialog .me-confirm-head p {
            margin: .2rem 0 0;
            color: #657269;
            font-size: .75rem;
            line-height: 1.5;
        }

        .me-confirm-dialog .me-confirm-close {
            width: 32px;
            height: 32px;
            display: inline-grid;
            place-items: center;
            flex: 0 0 32px;
            margin-inline-start: auto;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: #6b776f;
        }

        .me-confirm-dialog .me-confirm-close:hover {
            background: #f2f5f3;
        }

        .me-confirm-dialog .me-confirm-body {
            padding: 1rem 1.1rem;
        }

        .me-confirm-dialog .me-confirm-record {
            margin: 0;
            padding: .8rem;
            border: 1px solid #e1e8e4;
            border-radius: 8px;
            background: #f8faf9;
        }

        .me-confirm-dialog .me-confirm-record div + div {
            margin-top: .55rem;
            padding-top: .55rem;
            border-top: 1px solid #e4eae6;
        }

        .me-confirm-dialog .me-confirm-record dt {
            color: #718078;
            font-size: .66rem;
            font-weight: 750;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .me-confirm-dialog .me-confirm-record dd {
            margin: .15rem 0 0;
            color: #243129;
            font-size: .8rem;
            font-weight: 700;
        }

        .me-confirm-dialog .me-confirm-warning {
            display: flex;
            align-items: flex-start;
            gap: .55rem;
            margin: .8rem 0 0;
            color: #765b1b;
            font-size: .73rem;
            line-height: 1.5;
        }

        .me-confirm-dialog .me-confirm-warning i {
            margin-top: .12rem;
        }

        .me-confirm-dialog .me-confirm-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .55rem;
            padding: .85rem 1.1rem;
            border-top: 1px solid #e1e8e4;
            background: #fbfcfb;
        }

        .me-confirm-dialog .me-confirm-actions button {
            min-height: 38px;
            border-radius: 7px;
            padding: .48rem .8rem;
            font-size: .76rem;
            font-weight: 750;
        }

        .me-confirm-dialog .me-confirm-cancel {
            border: 1px solid #ccd7d1;
            background: #fff;
            color: #425048;
        }

        .me-confirm-dialog .me-confirm-submit {
            border: 1px solid #176b4b;
            background: #176b4b;
            color: #fff;
        }

        .me-confirm-dialog .me-confirm-submit:disabled {
            cursor: wait;
            opacity: .7;
        }

        .tt-me-form .invalid-feedback {
            display: block;
            font-size: .7rem;
        }

        @media (max-width: 900px) {
            .tt-me-form .me-overview-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 680px) {
            .tt-me-form .me-form-hero {
                grid-template-columns: 1fr;
            }

            .tt-me-form .me-status-box {
                min-width: 0;
            }

            .tt-me-form .me-fields-grid,
            .tt-me-form .me-choice-grid {
                grid-template-columns: 1fr;
            }

            .tt-me-form .me-field.field-wide,
            .tt-me-form .me-field.field-checkbox,
            .tt-me-form .me-field.field-upload {
                grid-column: auto;
            }

            .tt-me-form .me-scale-wrap {
                grid-template-columns: 1fr;
            }

            .tt-me-form .me-actions {
                position: static;
                align-items: stretch;
                flex-direction: column;
            }

            .tt-me-form .me-actions .d-flex {
                display: grid !important;
            }
        }
    </style>
@endpush

<x-think-tank.partials.shell :member="$member" title="M&E Indicator Reporting">
    @php
        $showRouteParams = array_merge(['assignment' => $assignment->id], $portalRouteParams);
        $draftAction = route('think-tank.me-data.save-draft', $showRouteParams);
        $submitAction = route('think-tank.me-data.submit', $showRouteParams);
        $submissionStatus = $submission?->status;
        $statusLabel = match ($submissionStatus) {
            'draft' => 'Draft saved',
            'submitted' => 'Submitted for review',
            'returned' => 'Correction required',
            'validated' => 'Validated',
            'approved' => 'Approved',
            default => match ($assignmentState) {
                'upcoming' => 'Upcoming',
                'closed' => 'Closed',
                default => 'Not started',
            },
        };
        $linkedIndicatorField = $fields->first(fn ($field): bool => filled($field->indicator_id));
        $linkedIndicator = method_exists($form, 'indicator')
            ? $form->indicator
            : ($linkedIndicatorField?->relationLoaded('indicator') ? $linkedIndicatorField->indicator : null);
        $indicatorCode = trim((string) ($linkedIndicator?->indicator_code ?: $form->code));
        $indicatorName = trim((string) ($linkedIndicator?->name ?: $linkedIndicatorField?->label ?: $form->title));
        $indicatorUnit = trim((string) (
            $linkedIndicator?->unit?->symbol
            ?: $linkedIndicator?->unit?->name
            ?: $linkedIndicatorField?->unit_label
            ?: ''
        ));
    @endphp

    <div class="tt-me-form">
        <a class="me-back-link" href="{{ route('think-tank.me-data.index', $portalRouteParams) }}">
            <i class="feather-arrow-left"></i> Back to assigned indicators
        </a>

        <section class="me-form-hero" aria-labelledby="collection-form-heading">
            <div>
                <span class="me-form-code">
                    {{ $indicatorCode }} &middot; Assigned indicator
                    @if ($indicatorUnit !== '')
                        &middot; Unit: {{ $indicatorUnit }}
                    @endif
                </span>
                <h4 id="collection-form-heading">{{ $indicatorName }}</h4>
                <div class="me-template-label">
                    <span><i class="feather-file-text" aria-hidden="true"></i> Template: {{ $form->title }}</span>
                    <span><i class="feather-layers" aria-hidden="true"></i> Version {{ $form->version }}</span>
                </div>
                @if ($form->description)
                    <p>{{ $form->description }}</p>
                @endif
            </div>
            <div class="me-status-box">
                <span class="status-label">Current status</span>
                <strong>{{ $statusLabel }}</strong>
                @if ($submission?->submitted_at)
                    <small class="d-block mt-2 text-white-50">
                        Sent {{ $submission->submitted_at->format('d M Y, H:i') }}
                    </small>
                @endif
            </div>
        </section>

        <section class="me-overview-grid" aria-label="Collection details">
            <article class="me-overview-item">
                <span>Reporting period</span>
                <strong>{{ $period?->label ?? 'Not specified' }}</strong>
            </article>
            <article class="me-overview-item">
                <span>Period dates</span>
                <strong>
                    {{ $period?->period_start?->format('d M Y') ?? '—' }}
                    - {{ $period?->period_end?->format('d M Y') ?? '—' }}
                </strong>
            </article>
            <article class="me-overview-item">
                <span>Due date</span>
                <strong>{{ $collection->due_at?->format('d M Y, H:i') ?? 'No fixed due date' }}</strong>
            </article>
            <article class="me-overview-item">
                <span>Collection closes</span>
                <strong>{{ $collection->closes_at?->format('d M Y, H:i') ?? 'No fixed closing date' }}</strong>
            </article>
        </section>

        <section class="me-progress-shell" aria-label="Form completion">
            <div class="me-progress-head">
                <span>{{ $progress['answered'] }} of {{ $progress['total'] }} fields completed</span>
                <span>{{ $progress['percent'] }}%</span>
            </div>
            <div class="me-progress-track" aria-hidden="true">
                <span style="width: {{ $progress['percent'] }}%"></span>
            </div>
        </section>

        @if ($collection->instructions || $form->instructions)
            <div class="me-guidance">
                <i class="feather-info"></i>
                <div>
                    <strong class="d-block mb-1">Before you begin</strong>
                    {!! nl2br(e($collection->instructions ?: $form->instructions)) !!}
                </div>
            </div>
        @endif

        @if ($submissionStatus === 'returned')
            <div class="me-returned" role="alert">
                <strong class="d-block mb-1"><i class="feather-alert-triangle me-1"></i> The M&E team requested a correction</strong>
                {{ $submission->review_notes ?: 'Please review the responses and submit the corrected information.' }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3" role="alert">
                <strong class="d-block mb-1">Please review the highlighted information.</strong>
                {{ $errors->first() }}
            </div>
        @endif

        @if (! $editable && ! in_array($submissionStatus, ['submitted', 'validated', 'approved'], true))
            <div class="alert alert-secondary mt-3 mb-0">
                <i class="feather-lock me-1"></i>
                This form is available for reference, but responses cannot be changed while the collection is {{ $assignmentState }}.
            </div>
        @endif

        @if ($editable)
            <form method="POST" action="{{ $draftAction }}" enctype="multipart/form-data" novalidate data-me-reporting-form>
                @csrf
        @endif

        @forelse ($formSections as $sectionIndex => $formSection)
            @php
                $sectionHeadingId = 'form-section-' . ($sectionIndex + 1) . '-' . (\Illuminate\Support\Str::slug($formSection['key']) ?: 'information');
                $sectionGuidanceId = $sectionHeadingId . '-guidance';
                $sectionQuestionCount = $formSection['fields']->count();
                $sectionPalette = $formSection['palette'];
            @endphp
            <section class="me-section-card"
                aria-labelledby="{{ $sectionHeadingId }}"
                aria-describedby="{{ $sectionGuidanceId }}"
                style="--section-bg: {{ $sectionPalette['background'] }}; --section-header: {{ $sectionPalette['header'] }}; --section-border: {{ $sectionPalette['border'] }}; --section-accent: {{ $sectionPalette['accent'] }}; --section-text: {{ $sectionPalette['text'] }};">
                <header class="me-section-heading">
                    <span class="me-section-number" aria-hidden="true">
                        {{ str_pad((string) ($sectionIndex + 1), 2, '0', STR_PAD_LEFT) }}
                    </span>
                    <div class="me-section-heading-copy">
                        <div class="me-section-kicker">
                            Section {{ $sectionIndex + 1 }} &middot;
                            {{ $sectionQuestionCount }} {{ \Illuminate\Support\Str::plural('question', $sectionQuestionCount) }}
                        </div>
                        <h5 class="me-section-title" id="{{ $sectionHeadingId }}">{{ $formSection['name'] }}</h5>
                    </div>
                </header>
                <div class="me-section-guidance" id="{{ $sectionGuidanceId }}" role="note">
                    <span class="me-section-guidance-icon" aria-hidden="true">
                        <i class="feather-info"></i>
                    </span>
                    <div>
                        <strong>What to do in this section</strong>
                        <p>{!! nl2br(e($formSection['guidance'])) !!}</p>
                    </div>
                </div>
                <div class="me-fields-grid">
                    @foreach ($formSection['fields'] as $field)
                        @php
                            $fieldId = (string) $field->id;
                            $inputId = 'answer-' . $fieldId;
                            $value = old("answers.{$fieldId}", $answerValues->get($fieldId));
                            $options = $fieldOptions->get($fieldId, collect());
                            $validation = is_array($field->validation) ? $field->validation : [];
                            $isUpload = in_array($field->field_type, ['file', 'image'], true);
                            $wide = in_array($field->field_type, [
                                'textarea', 'radio', 'multiselect', 'checkbox', 'yes_no',
                                'rating', 'scale', 'file', 'image',
                            ], true);
                            $selected = collect(\Illuminate\Support\Arr::flatten(is_array($value) ? $value : [$value]))
                                ->filter(fn ($item) => is_scalar($item))
                                ->map(fn ($item) => (string) $item)
                                ->values();
                            $scalarValue = is_scalar($value)
                                ? (string) $value
                                : (string) ($selected->first() ?? '');
                            $optionLabels = $options->mapWithKeys(fn ($option) => [
                                (string) $option['value'] => (string) $option['label'],
                            ]);
                            $displayValue = $isUpload
                                ? null
                                : (is_array($value)
                                    ? $selected->map(fn ($item) => $optionLabels->get($item, $item))->implode(', ')
                                    : $scalarValue);
                            if (in_array($field->field_type, ['select', 'radio'], true)
                                && $displayValue !== null && $displayValue !== '') {
                                $displayValue = $optionLabels->get((string) $displayValue, $displayValue);
                            } elseif ($field->field_type === 'yes_no' && $displayValue !== null && $displayValue !== '') {
                                $displayValue = ucfirst((string) $displayValue);
                            }
                            if ($field->field_type === 'percentage' && $displayValue !== null && $displayValue !== '') {
                                $displayValue .= '%';
                            } elseif ($field->unit_label && $displayValue !== null && $displayValue !== '') {
                                $displayValue .= ' ' . $field->unit_label;
                            }
                            $existingAttachments = $attachments->get($fieldId, collect());
                            $uploadSettings = $fieldUploadSettings->get($fieldId, [
                                'allowed_extensions' => [],
                                'max_file_size_mb' => 10,
                                'multiple' => false,
                                'max_files' => 1,
                            ]);
                            $minLength = max(0, (int) ($validation['min_length'] ?? 0));
                            $ratingMin = max(1, min(10, (int) ($validation['min'] ?? 1)));
                            $ratingMax = max(1, min(10, (int) ($validation['max'] ?? 5)));
                            if ($ratingMax < $ratingMin) {
                                [$ratingMin, $ratingMax] = [$ratingMax, $ratingMin];
                            }
                            $ratingStep = is_numeric($validation['step'] ?? null)
                                ? min(max(1, (int) $validation['step']), max(1, $ratingMax - $ratingMin))
                                : 1;
                            $scaleMin = is_numeric($validation['min'] ?? data_get($validation, 'scale.min'))
                                ? (float) ($validation['min'] ?? data_get($validation, 'scale.min'))
                                : 1;
                            $scaleMax = is_numeric($validation['max'] ?? data_get($validation, 'scale.max'))
                                ? (float) ($validation['max'] ?? data_get($validation, 'scale.max'))
                                : 10;
                            $scaleStep = is_numeric($validation['step'] ?? data_get($validation, 'scale.step'))
                                ? max(0.000001, (float) ($validation['step'] ?? data_get($validation, 'scale.step')))
                                : 1;
                            if ($scaleMax < $scaleMin) {
                                [$scaleMin, $scaleMax] = [$scaleMax, $scaleMin];
                            }
                        @endphp
                        <div class="me-field {{ $wide ? 'field-wide' : '' }} {{ in_array($field->field_type, ['checkbox', 'multiselect'], true) ? 'field-checkbox' : '' }} {{ $isUpload ? 'field-upload' : '' }}">
                            <div class="me-field-label">
                                {{ $field->label }}
                                @if ($field->is_required)
                                    <span class="required-mark" title="Required">*</span>
                                @endif
                                @if ($field->unit_label)
                                    <span class="unit-label">({{ $field->unit_label }})</span>
                                @endif
                            </div>
                            @if ($field->help_text)
                                <p class="me-help" id="{{ $inputId }}-help">{{ $field->help_text }}</p>
                            @endif

                            @if ($editable)
                                @switch($field->field_type)
                                    @case('textarea')
                                        <textarea id="{{ $inputId }}" name="answers[{{ $fieldId }}]"
                                            aria-describedby="{{ $field->help_text ? $inputId . '-help' : '' }}"
                                            class="form-control {{ $errors->has("answers.{$fieldId}") ? 'is-invalid' : '' }}"
                                            minlength="{{ $minLength }}"
                                            maxlength="{{ min(20000, max(1, (int) ($validation['max_length'] ?? 5000))) }}">{{ is_scalar($value) ? $value : '' }}</textarea>
                                        @break

                                    @case('integer')
                                    @case('number')
                                    @case('percentage')
                                    @case('currency')
                                        <input id="{{ $inputId }}" type="number" name="answers[{{ $fieldId }}]"
                                            value="{{ is_scalar($value) ? $value : '' }}"
                                            step="{{ $validation['step'] ?? ($field->field_type === 'integer' ? 1 : 'any') }}"
                                            @if (isset($validation['min'])) min="{{ $validation['min'] }}" @elseif ($field->field_type === 'percentage') min="0" @endif
                                            @if (isset($validation['max'])) max="{{ $validation['max'] }}" @elseif ($field->field_type === 'percentage') max="100" @endif
                                            inputmode="{{ $field->field_type === 'integer' ? 'numeric' : 'decimal' }}"
                                            aria-describedby="{{ $field->help_text ? $inputId . '-help' : '' }}"
                                            class="form-control {{ $errors->has("answers.{$fieldId}") ? 'is-invalid' : '' }}">
                                        @break

                                    @case('date')
                                    @case('time')
                                    @case('datetime')
                                    @case('month')
                                        <input id="{{ $inputId }}"
                                            type="{{ $field->field_type === 'datetime' ? 'datetime-local' : $field->field_type }}"
                                            name="answers[{{ $fieldId }}]"
                                            value="{{ is_scalar($value) ? $value : '' }}"
                                            aria-describedby="{{ $field->help_text ? $inputId . '-help' : '' }}"
                                            class="form-control {{ $errors->has("answers.{$fieldId}") ? 'is-invalid' : '' }}">
                                        @break

                                    @case('year')
                                        <input id="{{ $inputId }}" type="number" name="answers[{{ $fieldId }}]"
                                            value="{{ is_scalar($value) ? $value : '' }}" step="1"
                                            min="{{ (int) ($validation['min'] ?? 1900) }}"
                                            max="{{ (int) ($validation['max'] ?? 2100) }}"
                                            inputmode="numeric" placeholder="YYYY"
                                            aria-describedby="{{ $field->help_text ? $inputId . '-help' : '' }}"
                                            class="form-control {{ $errors->has("answers.{$fieldId}") ? 'is-invalid' : '' }}">
                                        @break

                                    @case('email')
                                    @case('phone')
                                    @case('url')
                                        @php
                                            $htmlType = ['email' => 'email', 'phone' => 'tel', 'url' => 'url'][$field->field_type];
                                            $hardMax = ['email' => 255, 'phone' => 30, 'url' => 2048][$field->field_type];
                                            $maxLength = min($hardMax, max(1, (int) ($validation['max_length'] ?? $hardMax)));
                                        @endphp
                                        <input id="{{ $inputId }}" type="{{ $htmlType }}" name="answers[{{ $fieldId }}]"
                                            value="{{ is_scalar($value) ? $value : '' }}"
                                            minlength="{{ $minLength }}" maxlength="{{ $maxLength }}"
                                            @if ($field->field_type === 'phone') inputmode="tel" autocomplete="tel" @endif
                                            @if ($field->field_type === 'email') autocomplete="email" @endif
                                            @if ($field->field_type === 'url') placeholder="https://example.org" @endif
                                            aria-describedby="{{ $field->help_text ? $inputId . '-help' : '' }}"
                                            class="form-control {{ $errors->has("answers.{$fieldId}") ? 'is-invalid' : '' }}">
                                        @if ($minLength > 0 || isset($validation['max_length']))
                                            <p class="me-upload-rules mb-0">
                                                {{ $minLength > 0 ? 'Minimum ' . $minLength . ' characters.' : '' }}
                                                Maximum {{ $maxLength }} characters.
                                            </p>
                                        @endif
                                        @break

                                    @case('select')
                                        <select id="{{ $inputId }}" name="answers[{{ $fieldId }}]"
                                            aria-describedby="{{ $field->help_text ? $inputId . '-help' : '' }}"
                                            class="form-select {{ $errors->has("answers.{$fieldId}") ? 'is-invalid' : '' }}">
                                            <option value="">Select an option</option>
                                            @foreach ($options as $option)
                                                <option value="{{ $option['value'] }}" @selected($scalarValue === (string) $option['value'])>
                                                    {{ $option['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @break

                                    @case('multiselect')
                                        <select id="{{ $inputId }}" name="answers[{{ $fieldId }}][]" multiple
                                            size="{{ min(8, max(3, $options->count())) }}"
                                            aria-describedby="{{ $field->help_text ? $inputId . '-help' : '' }}"
                                            class="form-select me-multiselect {{ $errors->has("answers.{$fieldId}") ? 'is-invalid' : '' }}">
                                            @foreach ($options as $option)
                                                <option value="{{ $option['value'] }}" @selected($selected->contains((string) $option['value']))>
                                                    {{ $option['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="me-upload-rules mb-0">Hold Ctrl (Windows) or Command (Mac) to choose more than one option.</p>
                                        @break

                                    @case('checkbox')
                                        <div class="me-choice-grid" id="{{ $inputId }}">
                                            @foreach ($options as $option)
                                                <label class="me-choice">
                                                    <input type="checkbox" name="answers[{{ $fieldId }}][]"
                                                        value="{{ $option['value'] }}"
                                                        @checked($selected->contains((string) $option['value']))>
                                                    <span>{{ $option['label'] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @break

                                    @case('radio')
                                    @case('yes_no')
                                        @php
                                            $radioOptions = $field->field_type === 'yes_no'
                                                ? collect([['value' => 'yes', 'label' => 'Yes'], ['value' => 'no', 'label' => 'No']])
                                                : $options;
                                        @endphp
                                        <div class="me-choice-grid" id="{{ $inputId }}">
                                            @foreach ($radioOptions as $option)
                                                <label class="me-choice" for="{{ $inputId }}-{{ $loop->index }}">
                                                    <input id="{{ $inputId }}-{{ $loop->index }}" type="radio"
                                                        name="answers[{{ $fieldId }}]" value="{{ $option['value'] }}"
                                                        @checked($scalarValue === (string) $option['value'])>
                                                    <span>{{ $option['label'] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @break

                                    @case('rating')
                                        <div class="me-rating-grid" id="{{ $inputId }}">
                                            @foreach (range($ratingMin, $ratingMax, $ratingStep) as $rating)
                                                <label class="me-rating-option" for="{{ $inputId }}-{{ $rating }}">
                                                    <input id="{{ $inputId }}-{{ $rating }}" type="radio"
                                                        name="answers[{{ $fieldId }}]" value="{{ $rating }}"
                                                        @checked($scalarValue === (string) $rating)>
                                                    <span><i class="feather-star"></i> {{ $rating }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @break

                                    @case('scale')
                                        <div class="me-scale-wrap">
                                            <span>{{ $scaleMin }}</span>
                                            <input id="{{ $inputId }}-range" type="range"
                                                min="{{ $scaleMin }}" max="{{ $scaleMax }}" step="{{ $scaleStep }}"
                                                value="{{ is_scalar($value) && $value !== '' ? $value : $scaleMin }}"
                                                oninput="document.getElementById('{{ $inputId }}').value=this.value; document.getElementById('{{ $inputId }}-output').textContent=this.value;">
                                            <output id="{{ $inputId }}-output" for="{{ $inputId }}-range">
                                                {{ is_scalar($value) && $value !== '' ? $value : 'Not selected' }}
                                            </output>
                                            <input id="{{ $inputId }}" type="hidden" name="answers[{{ $fieldId }}]"
                                                value="{{ is_scalar($value) ? $value : '' }}">
                                        </div>
                                        @break

                                    @case('file')
                                    @case('image')
                                        <div class="me-upload-box">
                                            <input id="{{ $inputId }}" type="file"
                                                name="answers[{{ $fieldId }}]{{ $uploadSettings['multiple'] ? '[]' : '' }}"
                                                accept="{{ collect($uploadSettings['allowed_extensions'])->map(fn ($extension) => '.' . $extension)->implode(',') }}"
                                                @if ($uploadSettings['multiple']) multiple @endif
                                                aria-describedby="{{ $inputId }}-rules"
                                                class="form-control {{ $errors->has("answers.{$fieldId}") ? 'is-invalid' : '' }}">
                                            <p class="me-upload-rules mb-0" id="{{ $inputId }}-rules">
                                                {{ $uploadSettings['multiple'] ? 'Up to ' . $uploadSettings['max_files'] . ' files' : 'One file' }},
                                                {{ $uploadSettings['max_file_size_mb'] }} MB maximum each.
                                                Allowed: {{ collect($uploadSettings['allowed_extensions'])->map(fn ($extension) => strtoupper($extension))->implode(', ') ?: 'No formats configured' }}.
                                                @if (! $uploadSettings['multiple'] && $existingAttachments->isNotEmpty())
                                                    Selecting a new file replaces the current one after the draft is saved.
                                                @endif
                                            </p>
                                            @if ($existingAttachments->isNotEmpty())
                                                <div class="me-file-list" aria-label="Existing attachments">
                                                    @foreach ($existingAttachments as $attachment)
                                                        <div class="me-file-item">
                                                            <span class="me-file-name">
                                                                <i class="{{ $field->field_type === 'image' ? 'feather-image' : 'feather-paperclip' }} me-1"></i>
                                                                {{ $attachment['original_name'] }}
                                                                @if (isset($attachment['size']))
                                                                    <small class="text-muted">({{ number_format($attachment['size'] / 1024, 1) }} KB)</small>
                                                                @endif
                                                            </span>
                                                            <a href="{{ $attachment['download_url'] }}">Download</a>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        @break

                                    @default
                                        <input id="{{ $inputId }}" type="text" name="answers[{{ $fieldId }}]"
                                            value="{{ is_scalar($value) ? $value : '' }}"
                                            minlength="{{ $minLength }}"
                                            maxlength="{{ min(20000, max(1, (int) ($validation['max_length'] ?? 5000))) }}"
                                            aria-describedby="{{ $field->help_text ? $inputId . '-help' : '' }}"
                                            class="form-control {{ $errors->has("answers.{$fieldId}") ? 'is-invalid' : '' }}">
                                @endswitch

                                @error("answers.{$fieldId}")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @elseif ($isUpload)
                                @if ($existingAttachments->isEmpty())
                                    <div class="me-read-value empty">Not provided</div>
                                @else
                                    <div class="me-file-list mt-0" aria-label="Submitted attachments">
                                        @foreach ($existingAttachments as $attachment)
                                            <div class="me-file-item">
                                                <span class="me-file-name">
                                                    <i class="{{ $field->field_type === 'image' ? 'feather-image' : 'feather-paperclip' }} me-1"></i>
                                                    {{ $attachment['original_name'] }}
                                                    @if (isset($attachment['size']))
                                                        <small class="text-muted">({{ number_format($attachment['size'] / 1024, 1) }} KB)</small>
                                                    @endif
                                                </span>
                                                <a href="{{ $attachment['download_url'] }}">Download</a>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <div class="me-read-value {{ $displayValue === null || $displayValue === '' ? 'empty' : '' }}">
                                    {{ $displayValue === null || $displayValue === '' ? 'Not provided' : $displayValue }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="alert alert-secondary mt-3">This form does not contain any fields.</div>
        @endforelse

        <section class="me-notes">
            <label class="fw-bold small mb-2" for="submission-notes">Additional notes</label>
            @if ($editable)
                <textarea id="submission-notes" name="notes" class="form-control @error('notes') is-invalid @enderror"
                    maxlength="2000" placeholder="Add context that will help the M&E reviewer understand this submission.">{{ old('notes', $submission?->notes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @else
                <div class="me-read-value {{ blank($submission?->notes) ? 'empty' : '' }}">
                    {{ $submission?->notes ?: 'No additional notes were provided.' }}
                </div>
            @endif
        </section>

        @if ($editable)
                <div class="me-actions">
                    <p>
                        <i class="feather-save me-1"></i>
                        Save a draft at any time. Required fields are checked when you submit.
                    </p>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-light border" formnovalidate>
                            <i class="feather-save me-1"></i> Save draft
                        </button>
                        <button type="button" class="btn btn-primary" data-me-submit-trigger data-me-submit-action="{{ $submitAction }}">
                            <i class="feather-send me-1"></i> Submit data
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>

    @if ($editable)
        <dialog class="me-confirm-dialog"
                aria-labelledby="me-submit-confirm-title"
                aria-describedby="me-submit-confirm-description"
                data-me-confirm-dialog>
            <div class="me-confirm-head">
                <span class="me-confirm-icon" aria-hidden="true"><i class="feather-send"></i></span>
                <div>
                    <h2 id="me-submit-confirm-title">Confirm indicator submission</h2>
                    <p id="me-submit-confirm-description">Review this reporting record before sending it to the M&amp;E team.</p>
                </div>
                <button class="me-confirm-close" type="button" aria-label="Close confirmation" data-me-close-confirm>
                    <i class="feather-x" aria-hidden="true"></i>
                </button>
            </div>
            <div class="me-confirm-body">
                <dl class="me-confirm-record">
                    <div>
                        <dt>Indicator</dt>
                        <dd>{{ $indicatorCode }} &mdash; {{ $indicatorName }}</dd>
                    </div>
                    <div>
                        <dt>Reporting period</dt>
                        <dd>{{ $period?->label ?? 'Not specified' }}</dd>
                    </div>
                    <div>
                        <dt>Reporting template</dt>
                        <dd>{{ $form->title }}</dd>
                    </div>
                </dl>
                <p class="me-confirm-warning">
                    <i class="feather-alert-circle" aria-hidden="true"></i>
                    <span>After submission, this record cannot be edited unless the M&amp;E team returns it for correction.</span>
                </p>
            </div>
            <div class="me-confirm-actions">
                <button class="me-confirm-cancel" type="button" data-me-cancel-submit autofocus>Go back</button>
                <button class="me-confirm-submit" type="button" data-me-confirm-submit>
                    <i class="feather-send me-1" aria-hidden="true"></i> Confirm submission
                </button>
            </div>
        </dialog>
    @endif

    <script>
        (() => {
            const startSubmissionConfirmation = () => {
                const form = document.querySelector('[data-me-reporting-form]');
                const trigger = form?.querySelector('[data-me-submit-trigger]');
                const dialog = document.querySelector('[data-me-confirm-dialog]');
                const confirmButton = dialog?.querySelector('[data-me-confirm-submit]');
                const closeButtons = dialog?.querySelectorAll('[data-me-cancel-submit], [data-me-close-confirm]');

                if (!form || !trigger || !dialog || !confirmButton) return;

                const closeDialog = () => {
                    if (typeof dialog.close === 'function') dialog.close();
                    else dialog.removeAttribute('open');
                    trigger.focus();
                };

                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (typeof form.reportValidity === 'function' && !form.reportValidity()) return;
                    if (typeof dialog.showModal === 'function') dialog.showModal();
                    else dialog.setAttribute('open', '');
                });

                closeButtons?.forEach((button) => button.addEventListener('click', closeDialog));

                dialog.addEventListener('cancel', (event) => {
                    event.preventDefault();
                    closeDialog();
                });

                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) closeDialog();
                });

                confirmButton.addEventListener('click', () => {
                    confirmButton.disabled = true;
                    confirmButton.setAttribute('aria-busy', 'true');
                    confirmButton.textContent = 'Submitting…';

                    form.action = trigger.dataset.meSubmitAction || form.action;

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                        return;
                    }

                    HTMLFormElement.prototype.submit.call(form);
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startSubmissionConfirmation, { once: true });
            } else {
                startSubmissionConfirmation();
            }
        })();
    </script>
</x-think-tank.partials.shell>
