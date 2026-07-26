@php
    $documentPortfolio = filled($portfolioName ?? null)
        ? $portfolioName
        : (data_get($visit->settings, 'portfolio.name') ?: 'ATTP Portfolio');
    $documentStatus = ucfirst(str_replace('_', ' ', $visit->siteVisit?->status ?? 'draft'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $visit->reference_number }} — Bi-Annual Site Visit</title>
    <style>
        @page {
            margin: 104px 34px 68px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #20332f;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.3px;
            line-height: 1.43;
        }

        .document-header {
            position: fixed;
            top: -91px;
            right: -34px;
            left: -34px;
            height: 76px;
            padding: 13px 34px 10px;
            border-bottom: 4px solid #d7a528;
            background: #075446;
            color: #fff;
        }

        .header-table,
        .footer-table,
        .meta-table,
        .summary-table,
        .team-table,
        .findings,
        .review-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-logo-cell {
            width: 104px;
            padding-right: 12px;
            vertical-align: middle;
        }

        .header-logo {
            max-width: 94px;
            max-height: 36px;
        }

        .header-brand {
            color: #cce9df;
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .header-title {
            margin-top: 3px;
            color: #fff;
            font-size: 13px;
            font-weight: bold;
            line-height: 1.2;
        }

        .header-meta-cell {
            width: 142px;
            color: #dff3ed;
            font-size: 6.7px;
            line-height: 1.55;
            text-align: right;
            vertical-align: middle;
        }

        .document-footer {
            position: fixed;
            right: -34px;
            bottom: -54px;
            left: -34px;
            height: 40px;
            padding: 7px 34px 0;
            border-top: 1px solid #cfdcd8;
            color: #687a75;
            font-size: 6.3px;
        }

        .footer-left {
            width: 43%;
            vertical-align: top;
        }

        .footer-centre {
            width: 37%;
            color: #075446;
            font-weight: bold;
            text-align: center;
            vertical-align: top;
        }

        .footer-right {
            width: 20%;
            text-align: right;
            vertical-align: top;
        }

        .watermark {
            position: fixed;
            z-index: -1000;
            top: 43%;
            left: -56px;
            width: 790px;
            color: #075446;
            font-size: 43px;
            font-weight: bold;
            letter-spacing: 1.5px;
            line-height: 1.1;
            opacity: .045;
            text-align: center;
            text-transform: uppercase;
            transform: rotate(-31deg);
            transform-origin: 50% 50%;
        }

        .cover {
            margin-bottom: 11px;
            padding: 15px 17px 13px;
            border: 1px solid #cddfda;
            border-left: 6px solid #d7a528;
            background: #f7fbf9;
        }

        .eyebrow {
            color: #08765f;
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 1.05px;
            text-transform: uppercase;
        }

        .cover h1 {
            margin: 5px 0 4px;
            color: #075446;
            font-size: 19px;
            line-height: 1.2;
        }

        .cover p {
            margin: 0;
            color: #536963;
            font-size: 7.6px;
            line-height: 1.5;
        }

        .status-badge {
            display: inline-block;
            margin-left: 6px;
            padding: 2px 6px;
            border: 1px solid #b9d8ce;
            background: #e8f5f1;
            color: #075446;
            font-size: 5.7px;
            font-weight: bold;
            letter-spacing: .35px;
            text-transform: uppercase;
        }

        .meta-table {
            margin: 9px 0 10px;
            table-layout: fixed;
        }

        .meta-table td {
            padding: 7px 8px;
            border: 1px solid #dce7e3;
            background: rgba(255, 255, 255, .92);
            vertical-align: top;
        }

        .label {
            display: block;
            margin-bottom: 2px;
            color: #73847f;
            font-size: 5.8px;
            font-weight: bold;
            letter-spacing: .65px;
            text-transform: uppercase;
        }

        .value {
            color: #19332d;
            font-size: 7.3px;
            font-weight: bold;
        }

        .muted {
            color: #71817d;
        }

        .summary-table {
            margin: 0 0 10px;
            table-layout: fixed;
        }

        .summary-table td {
            width: 25%;
            padding: 8px;
            border: 1px solid #dce7e3;
            background: #f8fbfa;
            text-align: center;
        }

        .summary-number {
            display: block;
            color: #075446;
            font-size: 15px;
            font-weight: bold;
            line-height: 1.1;
        }

        .summary-label {
            display: block;
            margin-top: 3px;
            color: #6f807b;
            font-size: 5.8px;
            font-weight: bold;
            letter-spacing: .45px;
            text-transform: uppercase;
        }

        .team-panel,
        .notes-panel {
            margin: 0 0 10px;
            padding: 7px 9px;
            border: 1px solid #e4e5d5;
            border-left: 4px solid #d7a528;
            background: #fffdf6;
        }

        .team-table {
            table-layout: fixed;
        }

        .team-table td {
            padding: 2px 5px 2px 0;
            color: #40554f;
            font-size: 6.6px;
            vertical-align: top;
        }

        .lead {
            color: #075446;
            font-size: 5.7px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .section {
            margin-top: 14px;
        }

        .section-heading {
            padding: 8px 10px;
            background: #075446;
            color: #fff;
            page-break-after: avoid;
        }

        .section-heading-table {
            width: 100%;
            border-collapse: collapse;
        }

        .section-number {
            width: 62px;
            color: #cce9df;
            font-size: 6px;
            font-weight: bold;
            letter-spacing: .65px;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .section-title {
            color: #fff;
            font-size: 11px;
            font-weight: bold;
            vertical-align: middle;
        }

        .section-score {
            width: 76px;
            color: #dff3ed;
            font-size: 6px;
            text-align: right;
            vertical-align: middle;
        }

        .section-description {
            padding: 6px 9px;
            border: 1px solid #d9e5e1;
            border-top: 0;
            background: #f6faf8;
            color: #5e706a;
        }

        .topic {
            margin: 10px 0 5px;
            padding: 6px 8px;
            border-left: 4px solid #d7a528;
            background: #eef6f3;
            color: #173f36;
            font-size: 9px;
            font-weight: bold;
            page-break-after: avoid;
        }

        .topic-description {
            display: block;
            margin-top: 2px;
            color: #657671;
            font-size: 6.5px;
            font-weight: normal;
        }

        .question {
            margin-bottom: 7px;
            padding: 7px 8px;
            border: 1px solid #dce6e3;
            background: rgba(255, 255, 255, .94);
            page-break-inside: avoid;
        }

        .question-title {
            margin-bottom: 5px;
            color: #20332f;
            font-size: 7.5px;
            font-weight: bold;
        }

        .question-key {
            display: inline-block;
            margin-right: 4px;
            padding: 1px 4px;
            background: #e6f3ef;
            color: #075446;
            font-size: 5.8px;
            font-weight: bold;
        }

        .rating {
            float: right;
            margin-left: 5px;
            padding: 2px 6px;
            border: 1px solid #b9d8ce;
            background: #e8f5f1;
            color: #075446;
            font-size: 5.8px;
            font-weight: bold;
        }

        .rating.unanswered {
            border-color: #d9dfdd;
            background: #f3f5f4;
            color: #75827e;
        }

        .rating-guidance {
            margin: 4px 0 5px;
            padding: 4px 6px;
            border-left: 2px solid #9dcfc0;
            background: #f4f9f7;
            color: #596e68;
            font-size: 6.3px;
        }

        .findings {
            margin-top: 4px;
            table-layout: fixed;
        }

        .findings td {
            width: 50%;
            padding: 5px 6px;
            border: 1px solid #e0e8e5;
            vertical-align: top;
        }

        .findings td + td {
            border-left: 0;
        }

        .response {
            margin-top: 4px;
            padding: 5px 6px;
            border: 1px solid #e0e8e5;
            background: #fbfcfc;
            white-space: pre-line;
        }

        .empty-value {
            color: #9aa6a2;
            font-style: italic;
        }

        .review-section {
            margin-top: 15px;
            page-break-inside: avoid;
        }

        .review-heading {
            padding: 7px 9px;
            background: #edf5f2;
            color: #075446;
            font-size: 9px;
            font-weight: bold;
        }

        .review-table td {
            padding: 6px 7px;
            border: 1px solid #dce6e3;
            vertical-align: top;
        }
    </style>
</head>
<body>
    <header class="document-header">
        <table class="header-table">
            <tr>
                @if (filled($logoDataUri ?? null))
                    <td class="header-logo-cell">
                        <img class="header-logo" src="{{ $logoDataUri }}" alt="ATTP">
                    </td>
                @endif
                <td>
                    <div class="header-brand">Africa Think Tank Platform · Monitoring &amp; Evaluation</div>
                    <div class="header-title">{{ $visit->title }}</div>
                </td>
                <td class="header-meta-cell">
                    {{ $visit->reference_number }}<br>
                    {{ $visit->cycleLabel() }} · {{ $documentStatus }}
                </td>
            </tr>
        </table>
    </header>

    <footer class="document-footer">
        <table class="footer-table">
            <tr>
                <td class="footer-left">ATTP · Generated {{ now()->format('d M Y, H:i') }}</td>
                <td class="footer-centre">{{ $documentPortfolio }}</td>
                <td class="footer-right"></td>
            </tr>
        </table>
    </footer>

    <div class="watermark">{{ $documentPortfolio }}</div>

    <section class="cover">
        <div class="eyebrow">Bi-Annual Site Visit · Assessment Report</div>
        <h1>{{ $visit->title }}</h1>
        <p>
            {{ $visit->reference_number }} · {{ $visit->cycleLabel() }}
            <span class="status-badge">{{ $documentStatus }}</span>
        </p>
    </section>

    <table class="meta-table">
        <tr>
            <td>
                <span class="label">Portfolio</span>
                <span class="value">{{ $documentPortfolio }}</span>
            </td>
            <td>
                <span class="label">Think Tank</span>
                <span class="value">{{ $visit->thinkTank?->name ?? 'Not recorded' }}</span><br>
                <span class="muted">{{ $visit->thinkTank?->country }}</span>
            </td>
            <td>
                <span class="label">Visit period</span>
                <span class="value">
                    {{ optional($visit->starts_on)->format('d M Y') ?: '—' }}
                    –
                    {{ optional($visit->ends_on)->format('d M Y') ?: '—' }}
                </span>
            </td>
            <td>
                <span class="label">Questionnaire</span>
                <span class="value">{{ $visit->template?->name ?? ($snapshot['name'] ?? 'Monitoring Tool') }}</span><br>
                <span class="muted">Version {{ $visit->template_version }}</span>
            </td>
        </tr>
        @if ($visit->location || $visit->submitted_at)
            <tr>
                <td colspan="2">
                    <span class="label">Location</span>
                    <span class="value">{{ $visit->location ?: 'Not recorded' }}</span>
                </td>
                <td colspan="2">
                    <span class="label">Submission</span>
                    <span class="value">
                        {{ optional($visit->submitted_at)->format('d M Y, H:i') ?: 'Not yet submitted' }}
                    </span>
                    @if ($visit->submittedBy?->name)
                        <br><span class="muted">By {{ $visit->submittedBy->name }}</span>
                    @endif
                </td>
            </tr>
        @endif
    </table>

    <table class="summary-table">
        <tr>
            <td>
                <span class="summary-number">{{ isset($scores['overall']) ? number_format($scores['overall'], 1).'%' : '—' }}</span>
                <span class="summary-label">Weighted score</span>
            </td>
            <td>
                <span class="summary-number">{{ number_format((float) ($completion['percentage'] ?? 0), 0) }}%</span>
                <span class="summary-label">Completion</span>
            </td>
            <td>
                <span class="summary-number">{{ $completion['answered'] ?? 0 }}/{{ $completion['total'] ?? 0 }}</span>
                <span class="summary-label">Answered</span>
            </td>
            <td>
                <span class="summary-number">{{ $scores['rated'] ?? 0 }}</span>
                <span class="summary-label">Applicable ratings</span>
            </td>
        </tr>
    </table>

    <div class="team-panel">
        <span class="label">Monitoring team</span>
        <table class="team-table">
            <tr>
                @forelse ($visit->siteVisit?->group?->members ?? [] as $member)
                    <td>
                        <strong>{{ $member->user?->name ?? 'Unknown member' }}</strong>
                        @if ((string) $member->user_id === (string) $visit->siteVisit?->group?->leader_id)
                            <span class="lead"> · Team lead</span>
                        @endif
                        @if (filled(data_get($visit->settings, 'team_specialisms.'.$member->user_id)))
                            <br><span class="muted">{{ data_get($visit->settings, 'team_specialisms.'.$member->user_id) }}</span>
                        @endif
                    </td>
                    @if ($loop->iteration % 3 === 0 && ! $loop->last)
                        </tr><tr>
                    @endif
                @empty
                    <td class="muted">No team members recorded.</td>
                @endforelse
            </tr>
        </table>
    </div>

    @if ($visit->objectives)
        <div class="notes-panel">
            <span class="label">Visit objectives and preparation notes</span>
            <div style="white-space: pre-line">{{ $visit->objectives }}</div>
        </div>
    @endif

    @forelse ($snapshot['sections'] ?? [] as $sectionIndex => $section)
        @php
            $sectionKey = $section['key'] ?? null;
            $sectionPercentage = $sectionKey
                ? data_get($scores, 'details.sections.'.$sectionKey.'.percentage')
                : null;
        @endphp
        <section class="section">
            <div class="section-heading">
                <table class="section-heading-table">
                    <tr>
                        <td class="section-number">Part {{ $sectionIndex + 1 }}</td>
                        <td class="section-title">{{ $section['title'] ?? $section['name'] ?? 'Section' }}</td>
                        <td class="section-score">
                            {{ $sectionPercentage !== null ? number_format((float) $sectionPercentage, 1).'%' : 'Assessment section' }}
                        </td>
                    </tr>
                </table>
            </div>
            @if (filled($section['description'] ?? null))
                <div class="section-description">{{ $section['description'] }}</div>
            @endif

            @forelse ($section['topics'] ?? [] as $topicIndex => $topic)
                <div class="topic">
                    {{ $sectionIndex + 1 }}.{{ $topicIndex + 1 }} {{ $topic['title'] ?? $topic['name'] ?? 'Topic' }}
                    @if (filled($topic['description'] ?? null))
                        <span class="topic-description">{{ $topic['description'] }}</span>
                    @endif
                </div>

                @foreach ($topic['questions'] ?? [] as $questionIndex => $question)
                    @php
                        $key = $question['key']
                            ?? $question['stable_key']
                            ?? 'Q-'.($sectionIndex + 1).'.'.($topicIndex + 1).'.'.($questionIndex + 1);
                    @endphp
                    @continue(!in_array($key, $visibleQuestionKeys ?? [], true))
                    @php
                        $answer = $answerMap[$key] ?? [];
                        $score = $answer['score'] ?? null;
                        $isNotApplicable = (bool) ($answer['is_not_applicable'] ?? false);
                        $type = (string) ($question['response_type'] ?? $question['type'] ?? 'scored_assessment');
                        $configuredRating = collect($question['options'] ?? [])->first(
                            static function (mixed $option) use ($score, $isNotApplicable): bool {
                                if (! is_array($option)) {
                                    return false;
                                }

                                $aliases = ['na', 'n/a', 'not applicable', 'not_applicable'];
                                $optionIsNotApplicable = (bool) (
                                    $option['is_not_applicable']
                                        ?? $option['is_na']
                                        ?? (
                                            in_array(strtolower(trim((string) ($option['value'] ?? ''))), $aliases, true)
                                            || in_array(strtolower(trim((string) ($option['label'] ?? ''))), $aliases, true)
                                        )
                                );

                                return $optionIsNotApplicable === $isNotApplicable
                                    && (
                                        $isNotApplicable
                                        || (string) ($option['score'] ?? '') === (string) $score
                                        || (string) ($option['value'] ?? '') === (string) $score
                                    );
                            }
                        );
                        $ratingLabel = $answer['rating_label']
                            ?? (is_array($configuredRating) ? ($configuredRating['label'] ?? null) : null)
                            ?? 'Not answered';
                        $ratingDescription = is_array($configuredRating)
                            ? ($configuredRating['description'] ?? $configuredRating['help_text'] ?? null)
                            : null;
                        $response = $answer['value'] ?? null;

                        if ($type === 'evidence') {
                            $evidenceItems = is_array($response)
                                ? (array_is_list($response) ? $response : [$response])
                                : [$response];
                            $response = collect($evidenceItems)->map(
                                static function (mixed $item): ?string {
                                    if (is_scalar($item)) {
                                        return trim((string) $item) ?: null;
                                    }

                                    if (! is_array($item)) {
                                        return null;
                                    }

                                    return $item['reference']
                                        ?? $item['original_name']
                                        ?? $item['name']
                                        ?? $item['url']
                                        ?? $item['path']
                                        ?? $item['stored_path']
                                        ?? null;
                                }
                            )->filter()->implode(', ');
                        } elseif (is_array($response)) {
                            $response = collect($response)
                                ->filter(static fn (mixed $item): bool => is_scalar($item))
                                ->implode(', ');
                        }
                    @endphp

                    <article class="question">
                        <div class="question-title">
                            <span class="question-key">{{ $key }}</span>
                            @if ($type === 'scored_assessment')
                                <span class="rating {{ $score === null && ! $isNotApplicable ? 'unanswered' : '' }}">
                                    @if ($isNotApplicable)
                                        N/A · {{ $ratingLabel }}
                                    @elseif ($score !== null)
                                        {{ number_format((float) $score, 1) }} · {{ $ratingLabel }}
                                    @else
                                        Not answered
                                    @endif
                                </span>
                            @endif
                            {{ $question['prompt'] ?? $question['question'] ?? $question['label'] ?? 'Question' }}
                        </div>

                        @if (filled($question['help_text'] ?? null))
                            <div class="rating-guidance"><strong>Assessor guidance:</strong> {{ $question['help_text'] }}</div>
                        @endif

                        @if ($type === 'scored_assessment')
                            @if (filled($ratingDescription))
                                <div class="rating-guidance">
                                    <span class="label">Selected rating guide</span>
                                    {{ $ratingDescription }}
                                </div>
                            @endif

                            <table class="findings">
                                <tr>
                                    <td>
                                        <span class="label">Strength observed</span>
                                        @if (filled($answer['strength'] ?? null))
                                            <div style="white-space: pre-line">{{ $answer['strength'] }}</div>
                                        @else
                                            <span class="empty-value">No strength recorded</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="label">Weakness or gap</span>
                                        @if (filled($answer['weakness'] ?? null))
                                            <div style="white-space: pre-line">{{ $answer['weakness'] }}</div>
                                        @else
                                            <span class="empty-value">No gap recorded</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            @if ($isNotApplicable || filled($answer['evidence_notes'] ?? null))
                                <div class="response">
                                    <span class="label">{{ $isNotApplicable ? 'N/A justification' : 'Evidence and assessor notes' }}</span>
                                    @if ($isNotApplicable)
                                        {{ ($answer['not_applicable_reason'] ?? null) ?: 'No justification recorded' }}
                                    @else
                                        {{ $answer['evidence_notes'] }}
                                    @endif
                                </div>
                            @endif
                        @elseif ($type === 'information')
                            <div class="response muted">Information and guidance item — no response required.</div>
                        @else
                            <div class="response">
                                <span class="label">Response</span>
                                @if (filled($response))
                                    {{ $response }}
                                @else
                                    <span class="empty-value">No response recorded</span>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            @empty
                <div class="response muted">No topics were configured for this section.</div>
            @endforelse
        </section>
    @empty
        <div class="notes-panel">No questionnaire sections were found in the locked visit snapshot.</div>
    @endforelse

    @if (($visit->siteVisit?->approvals?->count() ?? 0) > 0)
        <section class="review-section">
            <div class="review-heading">Review and approval history</div>
            <table class="review-table">
                <tbody>
                    @foreach ($visit->siteVisit->approvals as $approval)
                        <tr>
                            <td style="width: 20%">
                                <span class="label">Decision</span>
                                <strong>{{ ucfirst(str_replace('_', ' ', $approval->status ?? $approval->decision ?? 'Reviewed')) }}</strong>
                            </td>
                            <td style="width: 23%">
                                <span class="label">Reviewer</span>
                                {{ $approval->reviewer?->name ?? 'Authorized reviewer' }}
                            </td>
                            <td style="width: 20%">
                                <span class="label">Date</span>
                                {{ optional($approval->reviewed_at ?? $approval->created_at)->format('d M Y, H:i') }}
                            </td>
                            <td>
                                <span class="label">Remarks</span>
                                {{ $approval->remarks ?: '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</body>
</html>
