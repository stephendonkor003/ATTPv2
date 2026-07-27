<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $template->name }} — {{ $portfolioName }}</title>
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
            line-height: 1.42;
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
        .rubric,
        .field-table {
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
            width: 130px;
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
            width: 45%;
            vertical-align: top;
        }

        .footer-centre {
            width: 35%;
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
            margin-bottom: 14px;
            padding: 17px 18px 15px;
            border: 1px solid #cddfda;
            border-left: 6px solid #d7a528;
            background: #f7fbf9;
        }

        .eyebrow {
            color: #08765f;
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 1.1px;
            text-transform: uppercase;
        }

        .cover h1 {
            margin: 5px 0 4px;
            color: #075446;
            font-size: 20px;
            line-height: 1.2;
        }

        .cover p {
            margin: 0;
            color: #536963;
            font-size: 8px;
            line-height: 1.55;
        }

        .meta-table {
            margin: 10px 0 13px;
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

        .instructions {
            margin: 0 0 14px;
            padding: 9px 11px;
            border: 1px solid #e2e9d7;
            border-left: 4px solid #d7a528;
            background: #fffdf6;
            color: #596a64;
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

        .section-weight {
            width: 64px;
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
            padding: 7px 8px 8px;
            border: 1px solid #dce6e3;
            background: rgba(255, 255, 255, .94);
            page-break-inside: avoid;
        }

        .question-heading {
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

        .question-type {
            float: right;
            color: #71817d;
            font-size: 5.6px;
            font-weight: bold;
            letter-spacing: .3px;
            text-transform: uppercase;
        }

        .help {
            margin: 4px 0 6px;
            padding: 4px 6px;
            border-left: 2px solid #9dcfc0;
            background: #f4f9f7;
            color: #60736d;
            font-size: 6.3px;
        }

        .rubric {
            margin: 4px 0 6px;
            table-layout: fixed;
        }

        .rubric th {
            padding: 3px 4px;
            border: 1px solid #d7e3df;
            background: #edf5f2;
            color: #47615a;
            font-size: 5.6px;
            text-align: left;
            text-transform: uppercase;
        }

        .rubric td {
            padding: 3px 4px;
            border: 1px solid #dfe8e5;
            color: #52665f;
            font-size: 6px;
            vertical-align: top;
        }

        .rubric-score {
            width: 32px;
            color: #075446;
            font-weight: bold;
            text-align: center;
        }

        .rubric-select {
            width: 22px;
            text-align: center;
        }

        .field-table {
            margin-top: 4px;
            table-layout: fixed;
        }

        .field-table td {
            width: 50%;
            padding: 4px 6px 1px;
            border: 1px solid #e0e8e5;
            vertical-align: top;
        }

        .field-table td + td {
            border-left: 0;
        }

        .response-block {
            margin-top: 4px;
            padding: 4px 6px 1px;
            border: 1px solid #e0e8e5;
        }

        .write-line {
            height: 13px;
            border-bottom: 1px dotted #aebdb8;
        }

        .choice-list {
            margin-top: 4px;
            color: #4e625d;
        }

        .choice {
            display: inline-block;
            margin: 0 12px 4px 0;
            white-space: nowrap;
        }

        .checkbox {
            display: inline-block;
            width: 8px;
            height: 8px;
            margin-right: 3px;
            border: 1px solid #73847f;
            vertical-align: -1px;
        }

        .required {
            color: #a94343;
        }

        .muted {
            color: #71817d;
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
                    <div class="header-title">{{ $template->name }}</div>
                </td>
                <td class="header-meta-cell">
                    {{ $portfolioName }}<br>
                    Questionnaire v{{ $template->version }}
                </td>
            </tr>
        </table>
    </header>

    <footer class="document-footer">
        <table class="footer-table">
            <tr>
                <td class="footer-left">ATTP · Bi-Annual Site Visit Questionnaire</td>
                <td class="footer-centre">{{ $portfolioName }}</td>
                <td class="footer-right"></td>
            </tr>
        </table>
    </footer>

    <div class="watermark">{{ $portfolioName }}</div>

    <section class="cover">
        <div class="eyebrow">Bi-Annual Site Visit · Field Questionnaire</div>
        <h1>{{ $template->name }}</h1>
        <p>
            @if ($thinkTank)
                This working copy is prepared for <strong>{{ $thinkTank->name }}</strong> and is branded to the
                <strong>{{ $portfolioName }}</strong> portfolio.
            @else
                This reusable library copy is issued by the <strong>Africa Think Tank Platform</strong>.
            @endif
            Record clear, evidence-based responses in the spaces provided.
        </p>
    </section>

    <table class="meta-table">
        <tr>
            <td><span class="label">Portfolio</span><span class="value">{{ $portfolioName }}</span></td>
            <td><span class="label">Think Tank</span><span class="value">{{ $thinkTank?->name ?: 'Not assigned' }}</span></td>
            <td><span class="label">Questionnaire</span><span class="value">{{ $template->code }} · v{{ $template->version }}</span></td>
            <td><span class="label">Generated</span><span class="value">{{ now()->format('d M Y, H:i') }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Visit date</span><div class="write-line"></div></td>
            <td><span class="label">Location</span><div class="write-line"></div></td>
            <td><span class="label">Team lead</span><div class="write-line"></div></td>
            <td><span class="label">Respondent</span><div class="write-line"></div></td>
        </tr>
    </table>

    @if (filled($definition['description'] ?? null) || filled($definition['instructions'] ?? null))
        <div class="instructions">
            @if (filled($definition['description'] ?? null))
                <span class="label">Purpose</span>
                <div>{{ $definition['description'] }}</div>
            @endif
            @if (filled($definition['instructions'] ?? null))
                <span class="label" style="margin-top: 6px">Completion instructions</span>
                <div style="white-space: pre-line">{{ $definition['instructions'] }}</div>
            @endif
        </div>
    @endif

    @php
        $documentRatingOptions = collect(data_get($definition, 'rating_scale.options', []))->filter();
    @endphp
    @if ($documentRatingOptions->isNotEmpty())
        <div class="instructions">
            <span class="label">Assessment rating guide</span>
            <table class="rubric">
                <thead>
                    <tr>
                        <th class="rubric-score">Score</th>
                        <th style="width: 28%">Rating</th>
                        <th>Assessment guide</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documentRatingOptions as $option)
                        <tr>
                            <td class="rubric-score">{{ $option['score'] ?? $option['value'] ?? '—' }}</td>
                            <td>{{ $option['label'] ?? $option['value'] ?? 'Option' }}</td>
                            <td>{{ $option['description'] ?? $option['help_text'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <span class="muted">
                Use the configured choice shown under each scored question. Question-specific options take precedence over this general guide.
            </span>
        </div>
    @endif

    @forelse ($definition['sections'] ?? [] as $sectionIndex => $section)
        <section class="section">
            <div class="section-heading">
                <table class="section-heading-table">
                    <tr>
                        <td class="section-number">Part {{ $sectionIndex + 1 }}</td>
                        <td class="section-title">{{ $section['title'] ?? 'Section' }}</td>
                        <td class="section-weight">Weight {{ number_format((float) ($section['weight'] ?? 1), 2) }}</td>
                    </tr>
                </table>
            </div>
            @if (filled($section['description'] ?? null))
                <div class="section-description">{{ $section['description'] }}</div>
            @endif

            @forelse ($section['topics'] ?? [] as $topicIndex => $topic)
                <div class="topic">
                    {{ $sectionIndex + 1 }}.{{ $topicIndex + 1 }} {{ $topic['title'] ?? 'Topic' }}
                    @if (filled($topic['description'] ?? null))
                        <span class="topic-description">{{ $topic['description'] }}</span>
                    @endif
                </div>

                @foreach ($topic['questions'] ?? [] as $questionIndex => $question)
                    @php
                        $type = (string) ($question['response_type'] ?? $question['type'] ?? 'long_text');
                        $options = collect($question['options'] ?? [])->filter();
                        $key = $question['key'] ?? 'Q-'.($sectionIndex + 1).'.'.($topicIndex + 1).'.'.($questionIndex + 1);
                        $lineCount = in_array($type, ['long_text', 'narrative', 'evidence'], true) ? 5 : 2;
                    @endphp
                    <article class="question">
                        <div class="question-heading">
                            <span class="question-key">{{ $key }}</span>
                            <span class="question-type">{{ str_replace('_', ' ', $type) }}</span>
                            {{ $question['prompt'] ?? $question['label'] ?? 'Question' }}
                            @if ($question['required'] ?? false)
                                <span class="required">*</span>
                            @endif
                        </div>

                        @if (filled($question['help_text'] ?? null))
                            <div class="help"><strong>Guidance:</strong> {{ $question['help_text'] }}</div>
                        @endif

                        @if ($type === 'information')
                            <div class="muted">Information and guidance item — no response required.</div>
                        @elseif ($type === 'scored_assessment')
                            @if ($options->isNotEmpty())
                                <div class="choice-list">
                                    @foreach ($options as $option)
                                        @php
                                            $optionScore = is_array($option) ? ($option['score'] ?? $option['value'] ?? null) : null;
                                            $optionLabel = is_array($option) ? ($option['label'] ?? $option['value'] ?? 'Option') : $option;
                                        @endphp
                                        <span class="choice">
                                            <span class="checkbox"></span>
                                            @if ($optionScore !== null && $optionScore !== '')
                                                <strong>{{ $optionScore }}</strong> ·
                                            @endif
                                            {{ $optionLabel }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <div class="choice-list">
                                    <span class="choice"><span class="checkbox"></span>0</span>
                                    <span class="choice"><span class="checkbox"></span>1</span>
                                    <span class="choice"><span class="checkbox"></span>2</span>
                                    <span class="choice"><span class="checkbox"></span>3</span>
                                    @if ($question['allows_na'] ?? false)
                                        <span class="choice"><span class="checkbox"></span>Not applicable</span>
                                    @endif
                                </div>
                            @endif

                            <table class="field-table">
                                <tr>
                                    <td>
                                        <span class="label">Strength observed</span>
                                        <div class="write-line"></div>
                                        <div class="write-line"></div>
                                    </td>
                                    <td>
                                        <span class="label">Weakness or gap</span>
                                        <div class="write-line"></div>
                                        <div class="write-line"></div>
                                    </td>
                                </tr>
                            </table>
                            <div class="response-block">
                                <span class="label">Evidence and assessor notes</span>
                                <div class="write-line"></div>
                                <div class="write-line"></div>
                                <div class="write-line"></div>
                            </div>
                        @elseif (in_array($type, ['single_choice', 'multiple_choice', 'yes_no_na'], true) && $options->isNotEmpty())
                            <div class="choice-list">
                                @foreach ($options as $option)
                                    @php
                                        $optionLabel = is_array($option)
                                            ? ($option['label'] ?? $option['value'] ?? 'Option')
                                            : $option;
                                    @endphp
                                    <span class="choice"><span class="checkbox"></span>{{ $optionLabel }}</span>
                                @endforeach
                            </div>
                            @if ($type === 'multiple_choice')
                                <div class="response-block">
                                    <span class="label">Additional notes</span>
                                    <div class="write-line"></div>
                                    <div class="write-line"></div>
                                </div>
                            @endif
                        @else
                            <div class="response-block">
                                <span class="label">
                                    {{ $type === 'evidence' ? 'Evidence reference' : 'Response' }}
                                </span>
                                @for ($line = 0; $line < $lineCount; $line++)
                                    <div class="write-line"></div>
                                @endfor
                            </div>
                        @endif
                    </article>
                @endforeach
            @empty
                <div class="question muted">No topics have been configured in this section.</div>
            @endforelse
        </section>
    @empty
        <div class="instructions">This questionnaire template does not contain any sections yet.</div>
    @endforelse
</body>
</html>
