<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ data_get($surveyConfig, 'title', 'Public Survey') }}</title>
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --line: #cbd5e1;
            --primary: #1d4ed8;
            --primary-soft: #eff6ff;
            --danger: #b91c1c;
            --success: #166534;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px 14px;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            color: var(--text);
            font: 14px/1.45 "Segoe UI", Tahoma, Arial, sans-serif;
        }

        .wrap {
            max-width: 880px;
            margin: 0 auto;
        }

        .hero {
            background: linear-gradient(120deg, #0f172a 0%, #1d4ed8 70%, #38bdf8 100%);
            color: #f8fafc;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 14px;
            box-shadow: 0 14px 26px rgba(15, 23, 42, 0.2);
        }

        .hero h1 {
            margin: 0 0 6px 0;
            font-size: 22px;
            line-height: 1.2;
        }

        .hero p {
            margin: 0;
            opacity: 0.96;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.07);
        }

        .meta {
            margin: 0 0 14px 0;
            color: var(--muted);
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #dbeafe;
            background: var(--primary-soft);
        }

        .alert {
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
            border: 1px solid;
            font-size: 13px;
        }

        .alert-success {
            border-color: #bbf7d0;
            background: #f0fdf4;
            color: var(--success);
        }

        .alert-danger {
            border-color: #fecaca;
            background: #fef2f2;
            color: var(--danger);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .field {
            margin-bottom: 12px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text);
        }

        .required {
            color: #dc2626;
            margin-left: 2px;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            border: 1px solid #94a3b8;
            border-radius: 8px;
            padding: 9px 10px;
            font: inherit;
            background: #ffffff;
        }

        textarea {
            min-height: 82px;
            resize: vertical;
        }

        .question {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
            background: #ffffff;
        }

        .question small {
            display: block;
            color: var(--muted);
            margin-top: 4px;
        }

        .choice-list {
            display: grid;
            gap: 6px;
        }

        .choice-list label {
            font-weight: 500;
            margin-bottom: 0;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #334155;
        }

        .actions {
            margin-top: 14px;
        }

        button {
            border: 0;
            background: var(--primary);
            color: #ffffff;
            border-radius: 8px;
            padding: 10px 14px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        button:hover {
            filter: brightness(0.95);
        }

        .footer {
            margin-top: 10px;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 740px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <h1>{{ data_get($surveyConfig, 'title', 'Public Survey') }}</h1>
            <p>{{ data_get($surveyConfig, 'intro', 'Please complete this survey form accurately.') }}</p>
        </div>

        <div class="card">
            <p class="meta">
                Indicator: <strong>{{ $link->indicator->name }}</strong><br>
                Methodology: <strong>{{ $methodology->name }}</strong>
            </p>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul style="margin: 0; padding-left: 18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('public.me.indicators.surveys.submit', ['token' => $link->public_token]) }}">
                @csrf

                <div class="grid">
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

                @foreach ($questions as $index => $question)
                    @php
                        $type = strtolower((string) ($question['type'] ?? 'text'));
                        $required = (bool) ($question['required'] ?? false);
                        $options = collect($question['options'] ?? [])
                            ->filter(fn ($option) => is_scalar($option) && trim((string) $option) !== '')
                            ->map(fn ($option) => trim((string) $option))
                            ->values()
                            ->all();
                    @endphp

                    <div class="question">
                        <label>
                            {{ $question['label'] ?? ('Question ' . ($index + 1)) }}
                            @if ($required)
                                <span class="required">*</span>
                            @endif
                        </label>

                        @if ($type === 'textarea')
                            <textarea name="answers[{{ $index }}]">{{ old('answers.' . $index) }}</textarea>
                        @elseif ($type === 'select')
                            <select name="answers[{{ $index }}]">
                                <option value="">Select an option</option>
                                @foreach ($options as $option)
                                    <option value="{{ $option }}" @selected(old('answers.' . $index) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @elseif ($type === 'radio')
                            <div class="choice-list">
                                @foreach ($options as $option)
                                    <label>
                                        <input type="radio" name="answers[{{ $index }}]" value="{{ $option }}"
                                            @checked(old('answers.' . $index) === $option)>
                                        {{ $option }}
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($type === 'checkbox')
                            @php
                                $selected = (array) old('answers.' . $index, []);
                            @endphp
                            <div class="choice-list">
                                @foreach ($options as $option)
                                    <label>
                                        <input type="checkbox" name="answers[{{ $index }}][]" value="{{ $option }}"
                                            @checked(in_array($option, $selected, true))>
                                        {{ $option }}
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <input type="{{ in_array($type, ['number', 'email', 'date'], true) ? $type : 'text' }}"
                                name="answers[{{ $index }}]" value="{{ old('answers.' . $index) }}">
                        @endif

                        @if (!empty($question['hint']))
                            <small>{{ $question['hint'] }}</small>
                        @endif
                    </div>
                @endforeach

                <div class="actions">
                    <button type="submit">Submit Survey</button>
                </div>
            </form>

            <p class="footer">
                Responses are securely stored and mapped to the responsible person/party attached to this indicator.
            </p>
        </div>
    </div>
</body>
</html>

