@php
    $portalUser = auth()->user();
    $canSubmitIndicatorData = (bool) $portalUser?->can('think_tank.me.submit');
    $canViewPerformanceReports = (bool) ($portalUser?->can('think_tank.me.reports.view')
        || $portalUser?->can('think_tank.me.reports.manage'));
    $canViewNotifications = (bool) $portalUser?->can('think_tank.me.notifications.view');
    $assignmentSummary = array_merge([
        'total' => 0,
        'open' => 0,
        'upcoming' => 0,
        'submitted' => 0,
        'closed' => 0,
        'action_required' => 0,
    ], (array) data_get($assignmentOverview, 'summary', []));
    $priorityAssignments = collect(data_get($assignmentOverview, 'priority', []));
    $nextAction = $priorityAssignments->first(
        fn (array $item): bool => ($item['state'] ?? null) === 'open' && (bool) ($item['can_edit'] ?? false)
    );
    $performanceRows = collect($rows)->filter(
        fn (array $row): bool => in_array(
            $row['indicator']->reporting_source,
            ['think_tank', 'both', 'system_calculated'],
            true
        )
    )->values();
    $formatResult = static function (mixed $value): string {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_float($value)) {
            return number_format($value, 2);
        }

        return (string) $value;
    };
@endphp

<x-think-tank.partials.shell :member="$member" title="M&E Dashboard">
    <style>
        .tt-mel-dashboard {
            --mel-primary: #075f78;
            --mel-primary-dark: #063f52;
            --mel-primary-soft: #eaf6f8;
            --mel-success: #157357;
            --mel-success-soft: #e8f6f0;
            --mel-warning: #a76709;
            --mel-warning-soft: #fff5df;
            --mel-danger: #b33a3a;
            --mel-danger-soft: #fff0f0;
            --mel-ink: #18323b;
            --mel-muted: #667b83;
            --mel-line: #dbe7ea;
            --mel-surface: #f7fafb;
            color: var(--mel-ink);
        }

        .tt-mel-dashboard *,
        .tt-mel-dashboard *::before,
        .tt-mel-dashboard *::after {
            box-sizing: border-box;
        }

        .tt-mel-dashboard .mel-hero {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1.5rem;
            align-items: center;
            padding: clamp(1.35rem, 3vw, 2.2rem);
            border-radius: 1.15rem;
            color: #fff;
            background:
                radial-gradient(circle at 92% 12%, rgba(255, 204, 91, .24), transparent 27%),
                linear-gradient(125deg, var(--mel-primary-dark), var(--mel-primary));
            box-shadow: 0 18px 42px rgba(6, 63, 82, .18);
        }

        .tt-mel-dashboard .mel-hero::after {
            position: absolute;
            right: -4rem;
            bottom: -6rem;
            width: 15rem;
            height: 15rem;
            border: 1px solid rgba(255, 255, 255, .13);
            border-radius: 50%;
            content: '';
        }

        .tt-mel-dashboard .mel-hero-copy,
        .tt-mel-dashboard .mel-hero-actions {
            position: relative;
            z-index: 1;
            min-width: 0;
        }

        .tt-mel-dashboard .mel-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: .6rem;
            color: rgba(255, 255, 255, .78);
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .tt-mel-dashboard .mel-hero h1 {
            max-width: 720px;
            margin: 0 0 .55rem;
            color: #fff;
            font-size: clamp(1.55rem, 3vw, 2.25rem);
            font-weight: 850;
            letter-spacing: -.025em;
        }

        .tt-mel-dashboard .mel-hero p {
            max-width: 740px;
            margin: 0;
            color: rgba(255, 255, 255, .82);
            font-size: .88rem;
            line-height: 1.65;
        }

        .tt-mel-dashboard .mel-hero-actions {
            display: flex;
            flex-direction: column;
            gap: .55rem;
            width: min(100%, 245px);
        }

        .tt-mel-dashboard .mel-hero-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            min-height: 44px;
            padding: .7rem 1rem;
            border: 1px solid transparent;
            border-radius: .7rem;
            font-size: .78rem;
            font-weight: 800;
            text-align: center;
            text-decoration: none;
        }

        .tt-mel-dashboard .mel-hero-action.is-primary {
            background: #fff;
            color: var(--mel-primary-dark);
        }

        .tt-mel-dashboard .mel-hero-action.is-secondary {
            border-color: rgba(255, 255, 255, .4);
            background: rgba(255, 255, 255, .08);
            color: #fff;
        }

        .tt-mel-dashboard .mel-hero-action:hover,
        .tt-mel-dashboard .mel-hero-action:focus {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, .12);
        }

        .tt-mel-dashboard .mel-guide {
            margin: 1rem 0;
            padding: 1rem;
            border: 1px solid #cfe1e6;
            border-radius: 1rem;
            background: #fff;
        }

        .tt-mel-dashboard .mel-section-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            min-width: 0;
        }

        .tt-mel-dashboard .mel-section-heading > div {
            min-width: 0;
        }

        .tt-mel-dashboard .mel-section-kicker {
            display: block;
            margin-bottom: .2rem;
            color: var(--mel-primary);
            font-size: .65rem;
            font-weight: 850;
            letter-spacing: .075em;
            text-transform: uppercase;
        }

        .tt-mel-dashboard .mel-section-heading h2 {
            margin: 0;
            color: var(--mel-ink);
            font-size: 1.05rem;
            font-weight: 850;
        }

        .tt-mel-dashboard .mel-section-heading p {
            max-width: 760px;
            margin: .3rem 0 0;
            color: var(--mel-muted);
            font-size: .76rem;
            line-height: 1.55;
        }

        .tt-mel-dashboard .mel-steps {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
            margin-top: .9rem;
        }

        .tt-mel-dashboard .mel-step {
            position: relative;
            min-width: 0;
            padding: .85rem;
            border: 1px solid var(--mel-line);
            border-radius: .8rem;
            background: var(--mel-surface);
        }

        .tt-mel-dashboard .mel-step-number {
            display: grid;
            width: 1.8rem;
            height: 1.8rem;
            margin-bottom: .55rem;
            place-items: center;
            border-radius: .55rem;
            background: var(--mel-primary);
            color: #fff;
            font-size: .68rem;
            font-weight: 850;
        }

        .tt-mel-dashboard .mel-step strong,
        .tt-mel-dashboard .mel-step small {
            display: block;
            overflow-wrap: anywhere;
        }

        .tt-mel-dashboard .mel-step strong {
            margin-bottom: .25rem;
            color: var(--mel-ink);
            font-size: .76rem;
        }

        .tt-mel-dashboard .mel-step small {
            color: var(--mel-muted);
            font-size: .68rem;
            line-height: 1.5;
        }

        .tt-mel-dashboard .mel-metrics {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: .65rem;
            margin: 1rem 0;
        }

        .tt-mel-dashboard .mel-metric {
            min-width: 0;
            padding: .85rem;
            border: 1px solid var(--mel-line);
            border-radius: .85rem;
            background: #fff;
        }

        .tt-mel-dashboard .mel-metric-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }

        .tt-mel-dashboard .mel-metric-icon {
            display: grid;
            width: 2rem;
            height: 2rem;
            flex: 0 0 auto;
            place-items: center;
            border-radius: .6rem;
            background: var(--mel-primary-soft);
            color: var(--mel-primary);
        }

        .tt-mel-dashboard .mel-metric strong,
        .tt-mel-dashboard .mel-metric small {
            display: block;
            overflow-wrap: anywhere;
        }

        .tt-mel-dashboard .mel-metric strong {
            color: var(--mel-ink);
            font-size: 1.25rem;
            font-weight: 850;
        }

        .tt-mel-dashboard .mel-metric small {
            margin-top: .45rem;
            color: var(--mel-muted);
            font-size: .65rem;
            font-weight: 750;
            line-height: 1.4;
        }

        .tt-mel-dashboard .mel-main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(280px, .75fr);
            gap: 1rem;
            align-items: start;
        }

        .tt-mel-dashboard .mel-panel {
            min-width: 0;
            overflow: hidden;
            margin-bottom: 1rem;
            border: 1px solid var(--mel-line);
            border-radius: 1rem;
            background: #fff;
        }

        .tt-mel-dashboard .mel-panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--mel-line);
            background: #fbfdfd;
        }

        .tt-mel-dashboard .mel-panel-head > div {
            min-width: 0;
        }

        .tt-mel-dashboard .mel-panel-head h2 {
            margin: 0;
            color: var(--mel-ink);
            font-size: .98rem;
            font-weight: 850;
        }

        .tt-mel-dashboard .mel-panel-head p {
            margin: .25rem 0 0;
            color: var(--mel-muted);
            font-size: .72rem;
            line-height: 1.5;
        }

        .tt-mel-dashboard .mel-panel-link {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            flex: 0 0 auto;
            color: var(--mel-primary);
            font-size: .72rem;
            font-weight: 800;
            text-decoration: none;
        }

        .tt-mel-dashboard .mel-assignment-list {
            display: grid;
            gap: .7rem;
            padding: .85rem;
        }

        .tt-mel-dashboard .mel-assignment {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(170px, .35fr);
            gap: 1rem;
            align-items: center;
            min-width: 0;
            padding: .9rem;
            border: 1px solid var(--mel-line);
            border-radius: .8rem;
            background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .tt-mel-dashboard .mel-assignment:hover {
            border-color: #afd1da;
            box-shadow: 0 8px 20px rgba(16, 71, 86, .07);
        }

        .tt-mel-dashboard .mel-assignment.is-overdue {
            border-left: 4px solid var(--mel-danger);
        }

        .tt-mel-dashboard .mel-assignment-main,
        .tt-mel-dashboard .mel-assignment-action {
            min-width: 0;
        }

        .tt-mel-dashboard .mel-code {
            display: inline-flex;
            max-width: 100%;
            margin-bottom: .35rem;
            padding: .2rem .45rem;
            border-radius: .4rem;
            background: var(--mel-primary-soft);
            color: var(--mel-primary);
            font-size: .62rem;
            font-weight: 850;
            overflow-wrap: anywhere;
        }

        .tt-mel-dashboard .mel-assignment h3 {
            margin: 0;
            color: var(--mel-ink);
            font-size: .85rem;
            font-weight: 850;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .tt-mel-dashboard .mel-form-name {
            margin: .22rem 0 0;
            color: var(--mel-muted);
            font-size: .69rem;
            overflow-wrap: anywhere;
        }

        .tt-mel-dashboard .mel-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .38rem .75rem;
            margin-top: .55rem;
            color: #536a72;
            font-size: .67rem;
        }

        .tt-mel-dashboard .mel-meta span {
            display: inline-flex;
            align-items: center;
            gap: .28rem;
            min-width: 0;
        }

        .tt-mel-dashboard .mel-progress-row {
            display: flex;
            align-items: center;
            gap: .65rem;
            margin-top: .65rem;
        }

        .tt-mel-dashboard .mel-progress {
            overflow: hidden;
            height: .42rem;
            flex: 1 1 auto;
            border-radius: 999px;
            background: #e7eef0;
        }

        .tt-mel-dashboard .mel-progress > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--mel-primary), #18a17c);
        }

        .tt-mel-dashboard .mel-progress-row small {
            flex: 0 0 auto;
            color: var(--mel-muted);
            font-size: .64rem;
            font-weight: 800;
        }

        .tt-mel-dashboard .mel-assignment-action {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: .55rem;
        }

        .tt-mel-dashboard .mel-status {
            display: inline-flex;
            align-self: flex-end;
            max-width: 100%;
            padding: .28rem .55rem;
            border-radius: 999px;
            background: #eef3f4;
            color: #546a72;
            font-size: .61rem;
            font-weight: 850;
            text-align: center;
            overflow-wrap: anywhere;
        }

        .tt-mel-dashboard .mel-status.is-action {
            background: var(--mel-warning-soft);
            color: var(--mel-warning);
        }

        .tt-mel-dashboard .mel-status.is-submitted {
            background: var(--mel-success-soft);
            color: var(--mel-success);
        }

        .tt-mel-dashboard .mel-status.is-overdue {
            background: var(--mel-danger-soft);
            color: var(--mel-danger);
        }

        .tt-mel-dashboard .mel-assignment-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            min-height: 38px;
            padding: .55rem .7rem;
            border: 1px solid var(--mel-primary);
            border-radius: .6rem;
            background: var(--mel-primary);
            color: #fff;
            font-size: .7rem;
            font-weight: 850;
            text-align: center;
            text-decoration: none;
        }

        .tt-mel-dashboard .mel-assignment-button.is-view {
            background: #fff;
            color: var(--mel-primary);
        }

        .tt-mel-dashboard .mel-tip-list,
        .tt-mel-dashboard .mel-status-list,
        .tt-mel-dashboard .mel-quick-links {
            display: grid;
            gap: .65rem;
            padding: .85rem;
        }

        .tt-mel-dashboard .mel-tip {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .6rem;
            align-items: start;
        }

        .tt-mel-dashboard .mel-tip-icon {
            display: grid;
            width: 1.9rem;
            height: 1.9rem;
            place-items: center;
            border-radius: .55rem;
            background: var(--mel-primary-soft);
            color: var(--mel-primary);
            font-size: .76rem;
        }

        .tt-mel-dashboard .mel-tip strong,
        .tt-mel-dashboard .mel-tip small {
            display: block;
        }

        .tt-mel-dashboard .mel-tip strong {
            color: var(--mel-ink);
            font-size: .72rem;
        }

        .tt-mel-dashboard .mel-tip small {
            margin-top: .15rem;
            color: var(--mel-muted);
            font-size: .66rem;
            line-height: 1.5;
        }

        .tt-mel-dashboard .mel-status-item {
            display: grid;
            grid-template-columns: 7rem minmax(0, 1fr);
            gap: .55rem;
            align-items: start;
            color: var(--mel-muted);
            font-size: .66rem;
            line-height: 1.45;
        }

        .tt-mel-dashboard .mel-status-item strong {
            color: var(--mel-ink);
            font-size: .67rem;
        }

        .tt-mel-dashboard .mel-quick-link {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .65rem;
            align-items: center;
            min-width: 0;
            padding: .7rem;
            border: 1px solid var(--mel-line);
            border-radius: .7rem;
            color: inherit;
            text-decoration: none;
        }

        .tt-mel-dashboard .mel-quick-link:hover {
            border-color: #afd1da;
            background: var(--mel-surface);
        }

        .tt-mel-dashboard .mel-quick-link > i:first-child {
            color: var(--mel-primary);
        }

        .tt-mel-dashboard .mel-quick-link strong,
        .tt-mel-dashboard .mel-quick-link small {
            display: block;
            overflow-wrap: anywhere;
        }

        .tt-mel-dashboard .mel-quick-link strong {
            color: var(--mel-ink);
            font-size: .71rem;
        }

        .tt-mel-dashboard .mel-quick-link small {
            margin-top: .12rem;
            color: var(--mel-muted);
            font-size: .63rem;
        }

        .tt-mel-dashboard .mel-empty {
            padding: 2rem 1rem;
            color: var(--mel-muted);
            text-align: center;
        }

        .tt-mel-dashboard .mel-empty i {
            display: block;
            margin-bottom: .55rem;
            color: var(--mel-primary);
            font-size: 1.6rem;
        }

        .tt-mel-dashboard .mel-empty strong {
            display: block;
            margin-bottom: .25rem;
            color: var(--mel-ink);
            font-size: .82rem;
        }

        .tt-mel-dashboard .mel-performance-table-wrap {
            overflow-x: auto;
            max-width: 100%;
        }

        .tt-mel-dashboard .mel-performance-table {
            width: 100%;
            min-width: 960px;
            margin: 0;
            table-layout: fixed;
            font-size: .7rem;
        }

        .tt-mel-dashboard .mel-performance-table th,
        .tt-mel-dashboard .mel-performance-table td,
        .tt-mel-dashboard .mel-performance-table th *,
        .tt-mel-dashboard .mel-performance-table td * {
            max-width: 100%;
            white-space: normal !important;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .tt-mel-dashboard .mel-performance-table th {
            padding: .7rem;
            border-bottom: 1px solid var(--mel-line);
            background: #f4f8f9;
            color: #536b73;
            font-size: .59rem;
            font-weight: 850;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .tt-mel-dashboard .mel-performance-table td {
            padding: .75rem .7rem;
            border-color: #edf2f3;
            vertical-align: top;
        }

        .tt-mel-dashboard .mel-performance-table th:first-child {
            width: 29%;
        }

        .tt-mel-dashboard .mel-indicator-name {
            color: var(--mel-ink);
            font-size: .71rem;
            font-weight: 750;
            line-height: 1.4;
        }

        .tt-mel-dashboard .mel-classification {
            display: inline-flex;
            max-width: 100%;
            padding: .28rem .5rem;
            border-radius: 999px;
            color: #fff;
            font-size: .6rem;
            font-weight: 850;
            text-align: center;
        }

        .tt-mel-dashboard .mel-performance-note {
            display: flex;
            align-items: flex-start;
            gap: .5rem;
            padding: .75rem 1rem;
            border-top: 1px solid var(--mel-line);
            background: #fbfdfd;
            color: var(--mel-muted);
            font-size: .67rem;
            line-height: 1.45;
        }

        @media (max-width: 1200px) {
            .tt-mel-dashboard .mel-metrics {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .tt-mel-dashboard .mel-steps {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 920px) {
            .tt-mel-dashboard .mel-hero,
            .tt-mel-dashboard .mel-main-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .tt-mel-dashboard .mel-hero-actions {
                width: 100%;
                flex-direction: row;
                flex-wrap: wrap;
            }

            .tt-mel-dashboard .mel-hero-action {
                flex: 1 1 210px;
            }
        }

        @media (max-width: 700px) {
            .tt-mel-dashboard .mel-steps,
            .tt-mel-dashboard .mel-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .tt-mel-dashboard .mel-section-heading,
            .tt-mel-dashboard .mel-panel-head,
            .tt-mel-dashboard .mel-assignment {
                grid-template-columns: minmax(0, 1fr);
                flex-direction: column;
            }

            .tt-mel-dashboard .mel-assignment {
                display: grid;
            }

            .tt-mel-dashboard .mel-assignment-action {
                align-items: stretch;
            }

            .tt-mel-dashboard .mel-status {
                align-self: flex-start;
            }

            .tt-mel-dashboard .mel-panel-link {
                align-self: flex-start;
            }

            .tt-mel-dashboard .mel-performance-table {
                min-width: 0;
            }

            .tt-mel-dashboard .mel-performance-table thead {
                display: none;
            }

            .tt-mel-dashboard .mel-performance-table,
            .tt-mel-dashboard .mel-performance-table tbody,
            .tt-mel-dashboard .mel-performance-table tr,
            .tt-mel-dashboard .mel-performance-table td {
                display: block;
                width: 100%;
            }

            .tt-mel-dashboard .mel-performance-table tr {
                padding: .65rem .8rem;
                border-bottom: 1px solid var(--mel-line);
            }

            .tt-mel-dashboard .mel-performance-table td {
                display: grid;
                grid-template-columns: minmax(7rem, .7fr) minmax(0, 1.3fr);
                gap: .65rem;
                padding: .38rem 0;
                border: 0;
            }

            .tt-mel-dashboard .mel-performance-table td::before {
                color: var(--mel-muted);
                content: attr(data-label);
                font-size: .6rem;
                font-weight: 850;
                letter-spacing: .035em;
                text-transform: uppercase;
            }
        }

        @media (max-width: 480px) {
            .tt-mel-dashboard .mel-steps,
            .tt-mel-dashboard .mel-metrics {
                grid-template-columns: minmax(0, 1fr);
            }

            .tt-mel-dashboard .mel-status-item,
            .tt-mel-dashboard .mel-performance-table td {
                grid-template-columns: minmax(0, 1fr);
                gap: .2rem;
            }
        }
    </style>

    <div class="tt-mel-dashboard">
        <header class="mel-hero">
            <div class="mel-hero-copy">
                <span class="mel-eyebrow"><i class="feather-activity" aria-hidden="true"></i> {{ $framework?->version ?: 'ATTP' }} M&amp;E reporting</span>
                <h1>Submit indicator data with confidence</h1>
                <p>
                    Welcome, {{ $member->name }}. Start with an assigned indicator, enter the result for the reporting period,
                    attach the requested evidence, save your draft, and submit it to the ATTP M&amp;E team for review.
                </p>
            </div>
            <div class="mel-hero-actions">
                @if ($nextAction && $canSubmitIndicatorData)
                    <a class="mel-hero-action is-primary" href="{{ $nextAction['url'] }}">
                        <i class="feather-edit-3" aria-hidden="true"></i>Start next submission
                    </a>
                @else
                    <a class="mel-hero-action is-primary" href="{{ route('think-tank.me-data.index') }}">
                        <i class="feather-list" aria-hidden="true"></i>View assigned indicators
                    </a>
                @endif
                <a class="mel-hero-action is-secondary" href="#how-to-submit">
                    <i class="feather-help-circle" aria-hidden="true"></i>Show me how
                </a>
            </div>
        </header>

        <section class="mel-guide" id="how-to-submit" aria-labelledby="mel-guide-title">
            <div class="mel-section-heading">
                <div>
                    <span class="mel-section-kicker">Beginner guide</span>
                    <h2 id="mel-guide-title">How to submit indicator data</h2>
                    <p>Follow these four steps for each indicator and reporting period assigned to your think tank.</p>
                </div>
            </div>
            <div class="mel-steps">
                <article class="mel-step">
                    <span class="mel-step-number">1</span>
                    <strong>Open the assigned indicator</strong>
                    <small>Check the indicator name, reporting period, unit of measurement, and submission deadline.</small>
                </article>
                <article class="mel-step">
                    <span class="mel-step-number">2</span>
                    <strong>Gather data and evidence</strong>
                    <small>Use your official source records. Prepare totals, required disaggregation, and supporting files.</small>
                </article>
                <article class="mel-step">
                    <span class="mel-step-number">3</span>
                    <strong>Complete and save the form</strong>
                    <small>Answer every required question. Save a draft whenever you need to continue later.</small>
                </article>
                <article class="mel-step">
                    <span class="mel-step-number">4</span>
                    <strong>Review and submit</strong>
                    <small>Check values and attachments before submitting. Submitted data goes to the ATTP M&amp;E team for review.</small>
                </article>
            </div>
        </section>

        <section class="mel-metrics" aria-label="M&E reporting summary">
            <article class="mel-metric"><div class="mel-metric-top"><span class="mel-metric-icon"><i class="feather-list" aria-hidden="true"></i></span><strong>{{ number_format((int) $assignmentSummary['total']) }}</strong></div><small>Assigned indicator forms</small></article>
            <article class="mel-metric"><div class="mel-metric-top"><span class="mel-metric-icon"><i class="feather-edit-3" aria-hidden="true"></i></span><strong>{{ number_format((int) $assignmentSummary['action_required']) }}</strong></div><small>Require your action</small></article>
            <article class="mel-metric"><div class="mel-metric-top"><span class="mel-metric-icon"><i class="feather-send" aria-hidden="true"></i></span><strong>{{ number_format((int) $assignmentSummary['submitted']) }}</strong></div><small>Submitted for review</small></article>
            <article class="mel-metric"><div class="mel-metric-top"><span class="mel-metric-icon"><i class="feather-check-circle" aria-hidden="true"></i></span><strong>{{ number_format((int) $summary['approved_result_count']) }}</strong></div><small>Approved results</small></article>
            <article class="mel-metric"><div class="mel-metric-top"><span class="mel-metric-icon"><i class="feather-trending-up" aria-hidden="true"></i></span><strong>{{ $summary['average_achievement'] === null ? '—' : number_format($summary['average_achievement'], 1).'%' }}</strong></div><small>Average achievement</small></article>
            <article class="mel-metric"><div class="mel-metric-top"><span class="mel-metric-icon"><i class="feather-pie-chart" aria-hidden="true"></i></span><strong>{{ number_format($summary['average_completeness'], 1) }}%</strong></div><small>Reporting completeness</small></article>
        </section>

        <div class="mel-main-grid">
            <div>
                <section class="mel-panel" aria-labelledby="mel-actions-title">
                    <div class="mel-panel-head">
                        <div>
                            <h2 id="mel-actions-title">Your reporting actions</h2>
                            <p>Work from the nearest deadline. Draft progress is saved per indicator and reporting period.</p>
                        </div>
                        <a class="mel-panel-link" href="{{ route('think-tank.me-data.index') }}">All indicators <i class="feather-arrow-right" aria-hidden="true"></i></a>
                    </div>

                    @if ($priorityAssignments->isNotEmpty())
                        <div class="mel-assignment-list">
                            @foreach ($priorityAssignments as $item)
                                @php
                                    $progress = array_merge(['answered' => 0, 'total' => 0, 'percent' => 0], (array) ($item['progress'] ?? []));
                                    $progressPercent = min(100, max(0, (int) $progress['percent']));
                                    $state = (string) ($item['state'] ?? 'closed');
                                    $isOverdue = (bool) ($item['is_overdue'] ?? false);
                                    $isEditable = $canSubmitIndicatorData && (bool) ($item['can_edit'] ?? false);
                                    $statusLabel = match (true) {
                                        $isOverdue => 'Overdue — action required',
                                        $state === 'open' && ($item['submission_status'] ?? null) === 'returned' => 'Correction required',
                                        $state === 'open' && ($item['submission_status'] ?? null) === 'draft' => 'Draft — continue',
                                        $state === 'open' => 'Ready to start',
                                        $state === 'upcoming' => 'Upcoming',
                                        $state === 'submitted' => (string) ($item['submission_status_label'] ?? 'Submitted'),
                                        default => 'Closed',
                                    };
                                    $statusClass = match (true) {
                                        $isOverdue => 'is-overdue',
                                        $state === 'open' => 'is-action',
                                        $state === 'submitted' => 'is-submitted',
                                        default => '',
                                    };
                                    $actionLabel = match (true) {
                                        $isEditable && ($item['submission_status'] ?? null) === 'returned' => 'Correct and resubmit',
                                        $isEditable && ($item['submission_status'] ?? null) === 'draft' => 'Continue submission',
                                        $isEditable => 'Start indicator submission',
                                        default => 'View details',
                                    };
                                @endphp
                                <article class="mel-assignment {{ $isOverdue ? 'is-overdue' : '' }}">
                                    <div class="mel-assignment-main">
                                        <span class="mel-code">{{ $item['indicator_code'] ?: $item['form_code'] }}</span>
                                        <h3>{{ $item['indicator_name'] ?: $item['form_title'] }}</h3>
                                        <p class="mel-form-name">Linked form: {{ $item['form_title'] }}</p>
                                        <div class="mel-meta">
                                            <span><i class="feather-calendar" aria-hidden="true"></i>{{ $item['period_label'] }}</span>
                                            @if ($item['indicator_unit'])<span><i class="feather-hash" aria-hidden="true"></i>Unit: {{ $item['indicator_unit'] }}</span>@endif
                                            <span class="{{ $isOverdue ? 'text-danger fw-bold' : '' }}"><i class="feather-clock" aria-hidden="true"></i>{{ $item['due_at'] ? ($isOverdue ? 'Was due ' : 'Due ').$item['due_at']->format('d M Y') : 'Deadline not set' }}</span>
                                        </div>
                                        <div class="mel-progress-row">
                                            <div class="mel-progress" role="progressbar" aria-label="Form progress" aria-valuenow="{{ $progressPercent }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $progressPercent }}%"></span></div>
                                            <small>{{ $progress['answered'] }}/{{ $progress['total'] }} answered</small>
                                        </div>
                                    </div>
                                    <div class="mel-assignment-action">
                                        <span class="mel-status {{ $statusClass }}">{{ $statusLabel }}</span>
                                        <a class="mel-assignment-button {{ $isEditable ? '' : 'is-view' }}" href="{{ $item['url'] }}">
                                            {{ $actionLabel }} <i class="feather-arrow-right" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="mel-empty">
                            <i class="feather-inbox" aria-hidden="true"></i>
                            <strong>No indicator submission is assigned yet</strong>
                            The ATTP M&amp;E team will publish assigned forms here. You will also receive a portal notification.
                        </div>
                    @endif
                </section>
            </div>

            <aside>
                <section class="mel-panel" aria-labelledby="mel-tips-title">
                    <div class="mel-panel-head"><div><h2 id="mel-tips-title">Before you submit</h2><p>Small checks that prevent data from being returned.</p></div></div>
                    <div class="mel-tip-list">
                        <div class="mel-tip"><span class="mel-tip-icon"><i class="feather-book-open" aria-hidden="true"></i></span><div><strong>Read the indicator carefully</strong><small>Report only results that meet its definition, period, and unit of measurement.</small></div></div>
                        <div class="mel-tip"><span class="mel-tip-icon"><i class="feather-users" aria-hidden="true"></i></span><div><strong>Complete disaggregation</strong><small>Break results down by the categories requested in the form; do not invent categories.</small></div></div>
                        <div class="mel-tip"><span class="mel-tip-icon"><i class="feather-paperclip" aria-hidden="true"></i></span><div><strong>Attach verifiable evidence</strong><small>Use final source documents, clear filenames, and the correct reporting period.</small></div></div>
                        <div class="mel-tip"><span class="mel-tip-icon"><i class="feather-copy" aria-hidden="true"></i></span><div><strong>Avoid double counting</strong><small>Count one eligible result once unless the indicator instructions explicitly say otherwise.</small></div></div>
                    </div>
                </section>

                <section class="mel-panel" aria-labelledby="mel-status-title">
                    <div class="mel-panel-head"><div><h2 id="mel-status-title">What each status means</h2><p>Know what happens after you start.</p></div></div>
                    <div class="mel-status-list">
                        <div class="mel-status-item"><strong>Ready to start</strong><span>The form is open and has no saved answers.</span></div>
                        <div class="mel-status-item"><strong>Draft</strong><span>Your answers are saved but not yet sent to ATTP.</span></div>
                        <div class="mel-status-item"><strong>Submitted</strong><span>The M&amp;E team can review the submission.</span></div>
                        <div class="mel-status-item"><strong>Correction required</strong><span>Read the review comments, update the form, and resubmit.</span></div>
                        <div class="mel-status-item"><strong>Approved</strong><span>The result is accepted for official reporting and consolidation.</span></div>
                    </div>
                </section>

                <section class="mel-panel" aria-labelledby="mel-links-title">
                    <div class="mel-panel-head"><div><h2 id="mel-links-title">M&amp;E shortcuts</h2><p>Open the workspace you need.</p></div></div>
                    <div class="mel-quick-links">
                        <a class="mel-quick-link" href="{{ route('think-tank.me-data.index') }}"><i class="feather-activity" aria-hidden="true"></i><span><strong>Indicator data</strong><small>All assigned forms and deadlines</small></span><i class="feather-chevron-right" aria-hidden="true"></i></a>
                        @if ($canViewPerformanceReports)
                            <a class="mel-quick-link" href="{{ route('think-tank.performance-reports.index') }}"><i class="feather-trending-up" aria-hidden="true"></i><span><strong>Performance reports</strong><small>Prepare and track periodic reports</small></span><i class="feather-chevron-right" aria-hidden="true"></i></a>
                        @endif
                        @if ($canViewNotifications)
                            <a class="mel-quick-link" href="{{ route('think-tank.reporting-notifications.index') }}"><i class="feather-bell" aria-hidden="true"></i><span><strong>Notifications</strong><small>Assignments, deadlines, and decisions</small></span><i class="feather-chevron-right" aria-hidden="true"></i></a>
                        @endif
                    </div>
                </section>
            </aside>
        </div>

        <section class="mel-panel" aria-labelledby="mel-performance-title">
            <div class="mel-panel-head">
                <div>
                    <h2 id="mel-performance-title">Target vs approved actual</h2>
                    <p>This section shows official approved performance. Draft, submitted, and returned values are not included yet.</p>
                </div>
                @if ($canViewPerformanceReports)
                    <a class="mel-panel-link" href="{{ route('think-tank.performance-reports.index') }}">Performance reports <i class="feather-arrow-right" aria-hidden="true"></i></a>
                @endif
            </div>

            @if ($performanceRows->isNotEmpty())
                <div class="mel-performance-table-wrap" role="region" aria-label="Target and approved indicator performance" tabindex="0">
                    <table class="table mel-performance-table align-middle">
                        <thead><tr><th>Indicator</th><th>Target</th><th>Period actual</th><th>Cumulative</th><th>Trend</th><th>Achievement</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($performanceRows as $row)
                                @php
                                    $target = $row['target_text'] ?? $row['target_value'] ?? null;
                                    $trendIcon = match ($row['trend']['direction']) {
                                        'up' => 'feather-trending-up',
                                        'down' => 'feather-trending-down',
                                        default => 'feather-minus',
                                    };
                                @endphp
                                <tr>
                                    <td data-label="Indicator"><span class="mel-code">{{ $row['indicator']->indicator_code }}</span><div class="mel-indicator-name">{{ $row['indicator']->name }}</div></td>
                                    <td data-label="Target">{{ $formatResult($target) }}</td>
                                    <td data-label="Period actual">{{ $formatResult($row['period_actual']) }}</td>
                                    <td data-label="Cumulative">{{ $formatResult($row['cumulative_actual']) }}</td>
                                    <td data-label="Trend"><i class="{{ $trendIcon }} me-1" aria-hidden="true"></i>{{ $row['trend']['label'] }}</td>
                                    <td data-label="Achievement">{{ $row['achievement_percent'] !== null ? number_format($row['achievement_percent'], 1).'%' : '—' }}</td>
                                    <td data-label="Status"><span class="mel-classification" style="background: {{ $row['classification']['color'] }}">{{ $row['classification']['label'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="mel-empty"><i class="feather-bar-chart-2" aria-hidden="true"></i><strong>No approved indicator results yet</strong>Submit assigned indicator data first. Approved results will appear here after ATTP review.</div>
            @endif
            <div class="mel-performance-note"><i class="feather-info" aria-hidden="true"></i><span>A dash means that no approved value is available for the selected reporting scope. Your saved drafts remain available in Indicator Data.</span></div>
        </section>
    </div>
</x-think-tank.partials.shell>
