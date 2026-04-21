<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ data_get($surveyConfig, 'title', 'Public Survey') }}</title>
    <style>
        :root {
            --bg: #eef4fb;
            --panel: rgba(255, 255, 255, 0.94);
            --panel-strong: #ffffff;
            --ink: #0f172a;
            --muted: #475569;
            --line: #d9e3f0;
            --primary: #0f4c81;
            --accent: #14b8a6;
            --soft: #e8f4fb;
            --warn: #b91c1c;
            --success: #166534;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(20, 184, 166, 0.12), transparent 28%),
                radial-gradient(circle at top right, rgba(15, 76, 129, 0.12), transparent 32%),
                linear-gradient(180deg, #f7fbff 0%, var(--bg) 100%);
            color: var(--ink);
            font: 15px/1.55 "Segoe UI", Tahoma, Arial, sans-serif;
            padding: 18px 14px 36px;
        }

        .shell {
            max-width: 980px;
            margin: 0 auto;
        }

        .hero {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            padding: 24px;
            color: #f8fafc;
            background:
                linear-gradient(135deg, rgba(10, 36, 64, 0.98), rgba(15, 76, 129, 0.94)),
                linear-gradient(180deg, #0f4c81, #14b8a6);
            box-shadow: 0 26px 54px rgba(15, 23, 42, 0.18);
            margin-bottom: 16px;
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: auto -10% -25% auto;
            width: 280px;
            height: 280px;
            background: radial-gradient(circle, rgba(20, 184, 166, 0.45), transparent 65%);
            pointer-events: none;
        }

        .hero-grid {
            position: relative;
            display: grid;
            gap: 18px;
            grid-template-columns: minmax(0, 1.8fr) minmax(240px, 0.9fr);
            align-items: end;
        }

        .hero h1 {
            margin: 0 0 10px;
            font-size: clamp(1.85rem, 2.4vw, 2.6rem);
            line-height: 1.1;
        }

        .hero p {
            margin: 0;
            max-width: 62ch;
            color: rgba(248, 250, 252, 0.92);
        }

        .hero-stats {
            display: grid;
            gap: 10px;
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.08);
            padding: 7px 12px;
            font-size: 0.83rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .hero-meta {
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 16px;
            backdrop-filter: blur(8px);
        }

        .hero-meta strong,
        .hero-meta span {
            display: block;
        }

        .hero-meta strong {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(226, 232, 240, 0.82);
            margin-bottom: 4px;
        }

        .hero-meta span {
            color: #f8fafc;
            font-weight: 600;
        }

        .panel {
            border: 1px solid rgba(217, 227, 240, 0.9);
            background: var(--panel);
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .panel-head {
            padding: 16px 18px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.75);
        }

        .panel-body {
            padding: 18px;
        }

        .progress-wrap {
            display: grid;
            gap: 8px;
        }

        .progress-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .progress-bar {
            height: 10px;
            border-radius: 999px;
            background: #dfeaf5;
            overflow: hidden;
        }

        .progress-bar > span {
            display: block;
            height: 100%;
            width: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transition: width 0.28s ease;
        }

        .step {
            display: none;
            animation: fadeStep 0.28s ease;
        }

        .step.is-active {
            display: block;
        }

        @keyframes fadeStep {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .step-header {
            display: grid;
            gap: 6px;
            margin-bottom: 18px;
        }

        .step-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            border-radius: 999px;
            background: var(--soft);
            color: var(--primary);
            padding: 7px 11px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .step-header h2,
        .step-header h3 {
            margin: 0;
            line-height: 1.15;
        }

        .step-header p {
            margin: 0;
            color: var(--muted);
        }

        .intro-grid,
        .question-grid {
            display: grid;
            gap: 12px;
        }

        .intro-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 18px;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field label,
        .question-label {
            font-weight: 700;
            color: var(--ink);
        }

        .required {
            color: #dc2626;
            margin-left: 3px;
        }

        input[type="text"],
        input[type="email"],
        input[type="url"],
        input[type="date"],
        input[type="datetime-local"],
        input[type="file"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            border: 1px solid #a8bdd4;
            border-radius: 14px;
            padding: 12px 14px;
            font: inherit;
            color: var(--ink);
            background: rgba(255, 255, 255, 0.96);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        select[multiple] {
            min-height: 132px;
        }

        .question-block {
            border: 1px solid #d9e6f2;
            border-radius: 18px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.92);
            display: grid;
            gap: 12px;
        }

        .question-hint {
            color: var(--muted);
            font-size: 0.88rem;
            margin-top: -4px;
        }

        .choice-list {
            display: grid;
            gap: 10px;
        }

        .choice-list label,
        .scale-grid label {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            border: 1px solid #d9e6f2;
            border-radius: 14px;
            padding: 10px 12px;
            background: rgba(248, 250, 252, 0.9);
            font-weight: 500;
        }

        .scale-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(78px, 1fr));
        }

        .scale-grid label {
            justify-content: center;
            text-align: center;
            flex-direction: column;
            align-items: center;
            min-height: 78px;
        }

        .scale-labels {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: var(--muted);
            font-size: 0.86rem;
        }

        .slider-wrap {
            display: grid;
            gap: 12px;
        }

        .slider-output {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            border-radius: 999px;
            background: var(--soft);
            color: var(--primary);
            padding: 8px 14px;
            font-weight: 700;
        }

        input[type="range"] {
            width: 100%;
            accent-color: var(--primary);
        }

        .matrix-wrap {
            overflow-x: auto;
            border: 1px solid #d9e6f2;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.96);
        }

        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 580px;
        }

        .matrix-table th,
        .matrix-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 10px;
            text-align: center;
            vertical-align: middle;
        }

        .matrix-table th:first-child,
        .matrix-table td:first-child {
            text-align: left;
            min-width: 230px;
        }

        .matrix-table tr:last-child th,
        .matrix-table tr:last-child td {
            border-bottom: 0;
        }

        .step-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 12px 18px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.16s ease, opacity 0.16s ease, background 0.16s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            color: #ffffff;
            background: linear-gradient(90deg, var(--primary), #1367a8);
        }

        .btn-secondary {
            color: var(--ink);
            background: #eef4fb;
            border: 1px solid #d4e0ec;
        }

        .btn-success {
            color: #ffffff;
            background: linear-gradient(90deg, #166534, #22c55e);
        }

        .alert {
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 16px;
            font-size: 0.95rem;
            border: 1px solid;
        }

        .alert-success {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: var(--success);
        }

        .alert-danger {
            background: #fef2f2;
            border-color: #fecaca;
            color: var(--warn);
        }

        .question-error {
            color: var(--warn);
            font-size: 0.86rem;
            font-weight: 600;
        }

        .success-card {
            display: grid;
            gap: 12px;
            text-align: left;
        }

        .success-icon {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            color: #ffffff;
            background: linear-gradient(135deg, #166534, #22c55e);
            box-shadow: 0 12px 24px rgba(34, 197, 94, 0.24);
        }

        .muted-note {
            color: var(--muted);
            font-size: 0.9rem;
        }

        [hidden] {
            display: none !important;
        }

        @media (max-width: 860px) {
            .hero-grid {
                grid-template-columns: 1fr;
            }

            .intro-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            body {
                padding-inline: 10px;
            }

            .hero,
            .panel-head,
            .panel-body {
                padding-inline: 14px;
            }

            .step-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="shell">
        <section class="hero">
            <div class="hero-grid">
                <div>
                    <span class="hero-chip">ATTP Monitoring, Evaluation and Learning</span>
                    <h1>{{ data_get($surveyConfig, 'title', 'Public Survey') }}</h1>
                    <p>{{ data_get($surveyConfig, 'intro', 'Please complete the survey carefully. Use next and back to move through each section before submitting.') }}</p>
                </div>
                <div class="hero-stats">
                    <div class="hero-meta">
                        <strong>Indicator</strong>
                        <span>{{ $link->indicator->name }}</span>
                    </div>
                    <div class="hero-meta">
                        <strong>Methodology</strong>
                        <span>{{ $methodology->name }}</span>
                    </div>
                    @if (data_get($surveyConfig, 'estimated_minutes'))
                        <div class="hero-meta">
                            <strong>Estimated Time</strong>
                            <span>{{ data_get($surveyConfig, 'estimated_minutes') }} minute{{ (int) data_get($surveyConfig, 'estimated_minutes') === 1 ? '' : 's' }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div class="progress-wrap">
                    <div class="progress-row">
                        <strong id="progressLabel">Introduction</strong>
                        <span id="progressMeta">Step 0 of {{ count($sections) }}</span>
                    </div>
                    <div class="progress-bar">
                        <span id="progressBarFill"></span>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                @if (session('success'))
                    <div class="success-card">
                        <div class="success-icon">✓</div>
                        <div>
                            <h2 class="mb-2">Response submitted</h2>
                            <p class="muted-note mb-0">{{ session('success') }}</p>
                        </div>
                        <div>
                            <a class="btn btn-secondary" href="{{ route('public.me.indicators.surveys.show', ['token' => $link->public_token]) }}">
                                Submit another response
                            </a>
                        </div>
                    </div>
                @else
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Please review the highlighted survey items.</strong>
                            <ul style="margin: 10px 0 0; padding-left: 18px;">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" enctype="multipart/form-data"
                        action="{{ route('public.me.indicators.surveys.submit', ['token' => $link->public_token]) }}"
                        id="publicSurveyForm" novalidate>
                        @csrf

                        <section class="step is-active" data-step-kind="intro" data-step-label="Introduction">
                            <div class="step-header">
                                <span class="step-kicker">Welcome</span>
                                <h2>Before you begin</h2>
                                <p>
                                    Share your respondent details below, then move through the survey one section at a time.
                                    You can always use <strong>Back</strong> and <strong>Next</strong> to review your responses before submitting.
                                </p>
                            </div>

                            <div class="intro-grid">
                                <div class="field">
                                    <label for="respondent_name">Your Name</label>
                                    <input id="respondent_name" type="text" name="respondent_name" value="{{ old('respondent_name') }}">
                                </div>
                                <div class="field">
                                    <label for="respondent_email">Your Email</label>
                                    <input id="respondent_email" type="email" name="respondent_email" value="{{ old('respondent_email') }}">
                                </div>
                                <div class="field">
                                    <label for="respondent_phone">Phone</label>
                                    <input id="respondent_phone" type="text" name="respondent_phone" value="{{ old('respondent_phone') }}">
                                </div>
                                <div class="field">
                                    <label for="respondent_organization">Organization / Agency</label>
                                    <input id="respondent_organization" type="text" name="respondent_organization" value="{{ old('respondent_organization') }}">
                                </div>
                            </div>

                            <div class="step-actions">
                                <div class="muted-note">
                                    {{ count($sections) }} section{{ count($sections) === 1 ? '' : 's' }} in this survey.
                                </div>
                                <button type="button" class="btn btn-primary" data-step-action="next">Start Survey</button>
                            </div>
                        </section>

                        @foreach ($sections as $sectionIndex => $section)
                            <section class="step"
                                data-step-kind="section"
                                data-step-label="{{ $section['title'] }}"
                                data-section-key="{{ $section['key'] }}"
                                data-section-visibility='@json($section['visibility'] ?? [])'>
                                <div class="step-header">
                                    <span class="step-kicker">Section {{ $sectionIndex + 1 }}</span>
                                    <h3>{{ $section['title'] }}</h3>
                                    @if (!empty($section['description']))
                                        <p>{{ $section['description'] }}</p>
                                    @endif
                                </div>

                                <div class="question-grid">
                                    @foreach ($section['questions'] as $questionIndex => $question)
                                        @php
                                            $type = strtolower((string) ($question['type'] ?? 'text'));
                                            $questionKey = (string) ($question['key'] ?? ('question_' . $sectionIndex . '_' . $questionIndex));
                                            $required = (bool) ($question['required'] ?? false);
                                            $oldValue = old('answers.' . $questionKey);
                                            $options = collect($question['options'] ?? [])->filter()->values()->all();
                                            $matrixRows = collect($question['rows'] ?? [])->values();
                                            $matrixColumns = collect($question['columns'] ?? [])->values();
                                            $scaleMin = (int) data_get($question, 'scale.min', 1);
                                            $scaleMax = (int) data_get($question, 'scale.max', 5);
                                            $scaleStep = max((int) data_get($question, 'scale.step', 1), 1);
                                            $sliderValue = is_scalar($oldValue) && trim((string) $oldValue) !== ''
                                                ? (string) $oldValue
                                                : (string) $scaleMin;
                                        @endphp

                                        <div class="question-block"
                                            data-question-key="{{ $questionKey }}"
                                            data-question-type="{{ $type }}"
                                            data-question-visibility='@json($question['visibility'] ?? [])'
                                            data-question-required="{{ $required ? '1' : '0' }}"
                                            data-question-max-selections="{{ data_get($question, 'max_selections') }}"
                                            data-question-min-selections="{{ data_get($question, 'min_selections') }}">
                                            <div>
                                                <div class="question-label">
                                                    {{ $question['label'] ?? ('Question ' . ($questionIndex + 1)) }}
                                                    @if ($required)
                                                        <span class="required">*</span>
                                                    @endif
                                                </div>
                                                @if (!empty($question['hint']))
                                                    <div class="question-hint">{{ $question['hint'] }}</div>
                                                @endif
                                            </div>

                                            @if ($type === 'textarea')
                                                <textarea name="answers[{{ $questionKey }}]" {{ $required ? 'required' : '' }}>{{ is_scalar($oldValue) ? $oldValue : '' }}</textarea>
                                            @elseif ($type === 'select')
                                                <select name="answers[{{ $questionKey }}]" {{ $required ? 'required' : '' }}>
                                                    <option value="">Select an option</option>
                                                    @foreach ($options as $option)
                                                        <option value="{{ $option }}" @selected((string) $oldValue === (string) $option)>{{ $option }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif ($type === 'multiselect')
                                                @php
                                                    $oldChoices = is_array($oldValue) ? $oldValue : [];
                                                @endphp
                                                <select name="answers[{{ $questionKey }}][]" multiple {{ $required ? 'required' : '' }}>
                                                    @foreach ($options as $option)
                                                        <option value="{{ $option }}" @selected(in_array($option, $oldChoices, true))>{{ $option }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif ($type === 'radio')
                                                <div class="choice-list">
                                                    @foreach ($options as $option)
                                                        <label>
                                                            <input type="radio" name="answers[{{ $questionKey }}]" value="{{ $option }}"
                                                                {{ $required ? 'required' : '' }}
                                                                @checked((string) $oldValue === (string) $option)>
                                                            <span>{{ $option }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @elseif ($type === 'checkbox')
                                                @php
                                                    $oldChoices = is_array($oldValue) ? $oldValue : [];
                                                @endphp
                                                <div class="choice-list" data-checkbox-group="{{ $questionKey }}">
                                                    @foreach ($options as $option)
                                                        <label>
                                                            <input type="checkbox" name="answers[{{ $questionKey }}][]" value="{{ $option }}"
                                                                @checked(in_array($option, $oldChoices, true))>
                                                            <span>{{ $option }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @elseif ($type === 'scale')
                                                <div class="scale-grid">
                                                    @for ($value = $scaleMin; $value <= $scaleMax; $value++)
                                                        <label>
                                                            <input type="radio" name="answers[{{ $questionKey }}]" value="{{ $value }}"
                                                                {{ $required ? 'required' : '' }}
                                                                @checked((string) $oldValue === (string) $value)>
                                                            <span>{{ $value }}</span>
                                                        </label>
                                                    @endfor
                                                </div>
                                                @if (data_get($question, 'scale.min_label') || data_get($question, 'scale.max_label'))
                                                    <div class="scale-labels">
                                                        <span>{{ data_get($question, 'scale.min_label') }}</span>
                                                        <span>{{ data_get($question, 'scale.max_label') }}</span>
                                                    </div>
                                                @endif
                                            @elseif ($type === 'slider')
                                                <div class="slider-wrap">
                                                    <div class="slider-output">
                                                        <span>Selected</span>
                                                        <strong data-slider-value="{{ $questionKey }}">{{ $sliderValue }}</strong>
                                                    </div>
                                                    <input type="range"
                                                        min="{{ $scaleMin }}"
                                                        max="{{ $scaleMax }}"
                                                        step="{{ $scaleStep }}"
                                                        name="answers[{{ $questionKey }}]"
                                                        value="{{ $sliderValue }}"
                                                        data-slider-input="{{ $questionKey }}">
                                                </div>
                                                <div class="scale-labels">
                                                    <span>{{ data_get($question, 'scale.min_label') ?: $scaleMin }}</span>
                                                    <span>{{ data_get($question, 'scale.max_label') ?: $scaleMax }}</span>
                                                </div>
                                            @elseif ($type === 'file')
                                                <input type="file" name="answers[{{ $questionKey }}]" {{ $required ? 'required' : '' }}>
                                            @elseif ($type === 'url')
                                                <input type="url"
                                                    name="answers[{{ $questionKey }}]"
                                                    value="{{ is_scalar($oldValue) ? $oldValue : '' }}"
                                                    placeholder="https://example.org/reference"
                                                    {{ $required ? 'required' : '' }}>
                                            @elseif ($type === 'matrix')
                                                @php
                                                    $oldMatrix = is_array($oldValue) ? $oldValue : [];
                                                @endphp
                                                <div class="matrix-wrap" data-matrix-group="{{ $questionKey }}">
                                                    <table class="matrix-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Item</th>
                                                                @foreach ($matrixColumns as $column)
                                                                    <th>{{ data_get($column, 'label', $column) }}</th>
                                                                @endforeach
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($matrixRows as $row)
                                                                @php
                                                                    $rowKey = (string) data_get($row, 'key', 'row_' . $loop->index);
                                                                    $selectedColumn = $oldMatrix[$rowKey] ?? null;
                                                                @endphp
                                                                <tr>
                                                                    <td>{{ data_get($row, 'label', $row) }}</td>
                                                                    @foreach ($matrixColumns as $column)
                                                                        @php
                                                                            $columnKey = (string) data_get($column, 'key', 'column_' . $loop->index);
                                                                            $columnValue = (string) data_get($column, 'label', $columnKey);
                                                                        @endphp
                                                                        <td>
                                                                            <input type="radio"
                                                                                name="answers[{{ $questionKey }}][{{ $rowKey }}]"
                                                                                value="{{ $columnValue }}"
                                                                                @checked((string) $selectedColumn === (string) $columnValue)>
                                                                        </td>
                                                                    @endforeach
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <input type="{{ match ($type) {
                                                    'number', 'email', 'date' => $type,
                                                    'datetime' => 'datetime-local',
                                                    default => 'text',
                                                } }}"
                                                    name="answers[{{ $questionKey }}]"
                                                    value="{{ is_scalar($oldValue) ? $oldValue : '' }}"
                                                    {{ $required ? 'required' : '' }}>
                                            @endif

                                            @error('answers.' . $questionKey)
                                                <div class="question-error">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @endforeach
                                </div>

                                <div class="step-actions">
                                    <button type="button" class="btn btn-secondary" data-step-action="back">Back</button>
                                    @if ($loop->last)
                                        <button type="button" class="btn btn-success" data-step-action="submit">Submit Survey</button>
                                    @else
                                        <button type="button" class="btn btn-primary" data-step-action="next">Next Section</button>
                                    @endif
                                </div>
                            </section>
                        @endforeach
                    </form>
                @endif
            </div>
        </section>
    </div>

    @if (!session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('publicSurveyForm');
                if (!form) {
                    return;
                }

                const progressLabel = document.getElementById('progressLabel');
                const progressMeta = document.getElementById('progressMeta');
                const progressFill = document.getElementById('progressBarFill');
                const allSteps = Array.from(form.querySelectorAll('.step'));
                let currentVisibleStepIndex = 0;

                function parseJsonAttribute(element, attribute) {
                    try {
                        return JSON.parse(element.getAttribute(attribute) || '{}');
                    } catch (error) {
                        return {};
                    }
                }

                function comparableValues(value) {
                    if (Array.isArray(value)) {
                        return value.flat(Infinity)
                            .filter((item) => item !== null && item !== undefined && String(item).trim() !== '')
                            .map((item) => String(item).trim().toLowerCase());
                    }

                    if (value && typeof value === 'object') {
                        return Object.values(value)
                            .filter((item) => item !== null && item !== undefined && String(item).trim() !== '')
                            .map((item) => String(item).trim().toLowerCase());
                    }

                    if (value !== null && value !== undefined && String(value).trim() !== '') {
                        return [String(value).trim().toLowerCase()];
                    }

                    return [];
                }

                function answerForQuestion(questionKey) {
                    const questionBlock = form.querySelector(`[data-question-key="${questionKey}"]`);
                    if (!questionBlock) {
                        return null;
                    }

                    const questionType = questionBlock.getAttribute('data-question-type');

                    if (questionType === 'checkbox') {
                        return Array.from(questionBlock.querySelectorAll('input[type="checkbox"]:checked'))
                            .map((input) => input.value);
                    }

                    if (questionType === 'multiselect') {
                        return Array.from(questionBlock.querySelectorAll('select option:checked'))
                            .map((option) => option.value);
                    }

                    if (questionType === 'matrix') {
                        const matrix = {};
                        Array.from(questionBlock.querySelectorAll('tbody tr')).forEach((row) => {
                            const checked = row.querySelector('input[type="radio"]:checked');
                            if (checked) {
                                const name = checked.name || '';
                                const match = name.match(/\[([^\]]+)\]$/);
                                if (match) {
                                    matrix[match[1]] = checked.value;
                                }
                            }
                        });
                        return matrix;
                    }

                    if (questionType === 'file') {
                        return Array.from(questionBlock.querySelectorAll('input[type="file"]'))
                            .flatMap((input) => Array.from(input.files || []).map((file) => file.name));
                    }

                    const checkedRadio = questionBlock.querySelector('input[type="radio"]:checked');
                    if (checkedRadio) {
                        return checkedRadio.value;
                    }

                    const field = questionBlock.querySelector('input:not([type="radio"]):not([type="checkbox"]), textarea, select');
                    return field ? field.value : null;
                }

                function matchesVisibility(visibility) {
                    const questionKey = (visibility?.question_key || '').toString().trim();
                    const values = Array.isArray(visibility?.values)
                        ? visibility.values.map((item) => String(item).trim().toLowerCase()).filter(Boolean)
                        : [];

                    if (!questionKey || values.length === 0) {
                        return true;
                    }

                    const currentValues = comparableValues(answerForQuestion(questionKey));
                    if (currentValues.length === 0) {
                        return false;
                    }

                    return currentValues.some((item) => values.includes(item));
                }

                function setInputsDisabled(container, disabled) {
                    container.querySelectorAll('input, textarea, select').forEach((field) => {
                        field.disabled = disabled;
                    });
                }

                function visibleSteps() {
                    return allSteps.filter((step) => !step.hidden);
                }

                function updateProgress() {
                    const activeStep = visibleSteps()[currentVisibleStepIndex] || visibleSteps()[0];
                    const totalSections = allSteps.filter((step) => step.getAttribute('data-step-kind') === 'section' && !step.hidden).length;

                    if (!activeStep) {
                        return;
                    }

                    const kind = activeStep.getAttribute('data-step-kind');
                    const activeSectionIndex = visibleSteps()
                        .slice(0, currentVisibleStepIndex + 1)
                        .filter((step) => step.getAttribute('data-step-kind') === 'section')
                        .length;

                    progressLabel.textContent = activeStep.getAttribute('data-step-label') || 'Survey';
                    progressMeta.textContent = kind === 'intro'
                        ? `Step 0 of ${totalSections}`
                        : `Step ${activeSectionIndex} of ${totalSections}`;

                    const denominator = Math.max(totalSections, 1);
                    const numerator = kind === 'intro' ? 0 : activeSectionIndex;
                    progressFill.style.width = `${(numerator / denominator) * 100}%`;
                }

                function showCurrentStep() {
                    const steps = visibleSteps();
                    const safeIndex = Math.max(0, Math.min(currentVisibleStepIndex, steps.length - 1));
                    currentVisibleStepIndex = safeIndex;

                    allSteps.forEach((step) => step.classList.remove('is-active'));
                    if (steps[safeIndex]) {
                        steps[safeIndex].classList.add('is-active');
                    }

                    updateProgress();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }

                function refreshVisibility() {
                    allSteps.forEach((step) => {
                        if (step.getAttribute('data-step-kind') !== 'section') {
                            return;
                        }

                        const sectionVisible = matchesVisibility(parseJsonAttribute(step, 'data-section-visibility'));
                        let visibleQuestionCount = 0;

                        step.querySelectorAll('.question-block').forEach((questionBlock) => {
                            const questionVisible = sectionVisible && matchesVisibility(parseJsonAttribute(questionBlock, 'data-question-visibility'));
                            questionBlock.hidden = !questionVisible;
                            setInputsDisabled(questionBlock, !questionVisible);

                            if (questionVisible) {
                                visibleQuestionCount += 1;
                            }
                        });

                        step.hidden = !sectionVisible || visibleQuestionCount === 0;
                    });

                    const steps = visibleSteps();
                    if (!steps[currentVisibleStepIndex]) {
                        currentVisibleStepIndex = Math.max(0, steps.length - 1);
                    }

                    showCurrentStep();
                }

                function clearQuestionErrors(step) {
                    step.querySelectorAll('.question-error[data-client-error="1"]').forEach((item) => item.remove());
                }

                function updateSliderDisplays() {
                    form.querySelectorAll('[data-slider-input]').forEach((input) => {
                        const questionKey = input.getAttribute('data-slider-input');
                        const output = form.querySelector(`[data-slider-value="${questionKey}"]`);

                        if (output) {
                            output.textContent = input.value;
                        }
                    });
                }

                function appendClientError(questionBlock, message) {
                    const error = document.createElement('div');
                    error.className = 'question-error';
                    error.dataset.clientError = '1';
                    error.textContent = message;
                    questionBlock.appendChild(error);
                }

                function validateCurrentStep() {
                    const step = visibleSteps()[currentVisibleStepIndex];
                    if (!step || step.getAttribute('data-step-kind') === 'intro') {
                        return true;
                    }

                    clearQuestionErrors(step);

                    for (const field of step.querySelectorAll('.question-block:not([hidden]) input:not([type="checkbox"]):not([type="radio"]), .question-block:not([hidden]) textarea, .question-block:not([hidden]) select')) {
                        if (!field.reportValidity()) {
                            return false;
                        }
                    }

                    for (const questionBlock of step.querySelectorAll('.question-block:not([hidden])')) {
                        const required = questionBlock.getAttribute('data-question-required') === '1';
                        const questionType = questionBlock.getAttribute('data-question-type');
                        const maxSelections = Number.parseInt(questionBlock.getAttribute('data-question-max-selections') || '', 10);
                        const minSelections = Number.parseInt(questionBlock.getAttribute('data-question-min-selections') || '', 10);

                        if (questionType === 'checkbox') {
                            const selected = questionBlock.querySelectorAll('input[type="checkbox"]:checked').length;

                            if (required && selected === 0) {
                                appendClientError(questionBlock, 'Select at least one option.');
                                return false;
                            }

                            if (!Number.isNaN(minSelections) && selected < minSelections) {
                                appendClientError(questionBlock, `Select at least ${minSelections} option(s).`);
                                return false;
                            }

                            if (!Number.isNaN(maxSelections) && selected > maxSelections) {
                                appendClientError(questionBlock, `Select no more than ${maxSelections} option(s).`);
                                return false;
                            }
                        }

                        if (questionType === 'multiselect') {
                            const selected = questionBlock.querySelectorAll('select option:checked').length;

                            if (required && selected === 0) {
                                appendClientError(questionBlock, 'Select at least one option.');
                                return false;
                            }

                            if (!Number.isNaN(minSelections) && selected < minSelections) {
                                appendClientError(questionBlock, `Select at least ${minSelections} option(s).`);
                                return false;
                            }

                            if (!Number.isNaN(maxSelections) && selected > maxSelections) {
                                appendClientError(questionBlock, `Select no more than ${maxSelections} option(s).`);
                                return false;
                            }
                        }

                        if (questionType === 'file' && required) {
                            const hasFile = questionBlock.querySelector('input[type="file"]')?.files?.length > 0;
                            if (!hasFile) {
                                appendClientError(questionBlock, 'Please upload a file before continuing.');
                                return false;
                            }
                        }

                        if ((questionType === 'radio' || questionType === 'scale') && required) {
                            const selected = questionBlock.querySelector('input[type="radio"]:checked');
                            if (!selected) {
                                appendClientError(questionBlock, 'Please choose one option before continuing.');
                                return false;
                            }
                        }

                        if (questionType === 'matrix' && required) {
                            const missingRow = Array.from(questionBlock.querySelectorAll('tbody tr')).find((row) => !row.querySelector('input[type="radio"]:checked'));
                            if (missingRow) {
                                appendClientError(questionBlock, 'Please answer every row in this grid.');
                                return false;
                            }
                        }
                    }

                    return true;
                }

                form.addEventListener('input', refreshVisibility);
                form.addEventListener('input', updateSliderDisplays);
                form.addEventListener('change', refreshVisibility);
                form.addEventListener('change', updateSliderDisplays);

                form.querySelectorAll('[data-step-action="next"]').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (!validateCurrentStep()) {
                            return;
                        }

                        const steps = visibleSteps();
                        currentVisibleStepIndex = Math.min(currentVisibleStepIndex + 1, steps.length - 1);
                        showCurrentStep();
                    });
                });

                form.querySelectorAll('[data-step-action="back"]').forEach((button) => {
                    button.addEventListener('click', () => {
                        currentVisibleStepIndex = Math.max(currentVisibleStepIndex - 1, 0);
                        showCurrentStep();
                    });
                });

                form.querySelectorAll('[data-step-action="submit"]').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (!validateCurrentStep()) {
                            return;
                        }

                        form.submit();
                    });
                });

                refreshVisibility();
                updateSliderDisplays();

                const firstServerError = form.querySelector('.question-error');
                if (firstServerError) {
                    const parentStep = firstServerError.closest('.step');
                    if (parentStep) {
                        const steps = visibleSteps();
                        const targetIndex = steps.indexOf(parentStep);
                        if (targetIndex >= 0) {
                            currentVisibleStepIndex = targetIndex;
                            showCurrentStep();
                        }
                    }
                }
            });
        </script>
    @endif
</body>

</html>
