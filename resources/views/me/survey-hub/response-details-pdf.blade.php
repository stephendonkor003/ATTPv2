<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Survey Response Details</title>
    <style>
        @page { margin: 24px 22px 28px; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 9px;
            line-height: 1.42;
        }

        .header {
            border-bottom: 3px solid #0f766e;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .brand {
            color: #0f766e;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 3px;
        }

        .subtitle {
            color: #475569;
            font-size: 9px;
            margin-top: 3px;
        }

        .footer {
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 8px;
            margin-top: 14px;
            padding-top: 8px;
        }

        .section {
            border: 1px solid #cbd5e1;
            margin-bottom: 10px;
        }

        .section-title {
            background: #ecfeff;
            color: #0f766e;
            border-bottom: 1px solid #cbd5e1;
            font-size: 10px;
            font-weight: bold;
            padding: 6px 8px;
        }

        .section-body {
            padding: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 5px;
            vertical-align: top;
            word-wrap: break-word;
        }

        th {
            background: #0f766e;
            color: #ffffff;
            font-size: 7.5px;
            text-align: left;
            text-transform: uppercase;
        }

        .summary td {
            background: #f8fafc;
        }

        .label {
            color: #64748b;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .value {
            color: #0f172a;
            font-size: 11px;
            font-weight: bold;
            margin-top: 2px;
        }

        .question {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .chip {
            display: inline-block;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 3px 6px;
            margin: 2px 3px 2px 0;
            font-size: 7.5px;
            font-weight: bold;
            color: #334155;
        }

        .status {
            font-weight: bold;
            font-size: 8px;
        }

        .answered {
            color: #166534;
        }

        .missing {
            color: #64748b;
        }

        .muted {
            color: #64748b;
            font-size: 7.5px;
        }

        .response-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
    </style>
</head>
<body>
    @php
        $completionRate = (float) ($selectedQuestionStats['completion_rate'] ?? 0);
        $breakdownRows = collect($answerBreakdown['rows'] ?? []);
        $generated = $generatedAt ?? now();
        $isAllExport = !empty($isAllQuestions);
    @endphp

    <div class="header">
        <div class="brand">{{ config('app.name', 'ATTP') }} | Monitoring and Evaluation</div>
        <div class="title">Survey Response Details</div>
        <div class="subtitle">
            {{ $surveyLink->indicator->name ?? 'Unassigned indicator' }}
            | {{ $methodology->name ?? $surveyLink->methodology->name ?? 'Questionnaire' }}
            | Generated {{ \Illuminate\Support\Carbon::parse($generated)->format('Y-m-d H:i:s') }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">Summary</div>
        <div class="section-body">
            <table class="summary">
                <tr>
                    <td>
                        <div class="label">Total Submissions</div>
                        <div class="value">{{ $stats['responses'] ?? 0 }}</div>
                    </td>
                    <td>
                        <div class="label">Answered</div>
                        <div class="value">{{ $stats['answered'] ?? 0 }}</div>
                    </td>
                    <td>
                        <div class="label">Missing</div>
                        <div class="value">{{ $stats['missing'] ?? 0 }}</div>
                    </td>
                    <td>
                        <div class="label">Completion</div>
                        <div class="value">{{ number_format($completionRate, 1) }}%</div>
                    </td>
                    <td>
                        <div class="label">Latest Response</div>
                        <div class="value">
                            {{ !empty($stats['last_response']) ? \Illuminate\Support\Carbon::parse($stats['last_response'])->format('Y-m-d H:i') : 'No data' }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">{{ $isAllExport ? 'Question Selection' : 'Selected Question' }}</div>
        <div class="section-body">
            <div class="question">{{ $selectedQuestion['label'] ?? 'Question' }}</div>
            <span class="chip">Section: {{ trim((string) ($selectedQuestion['section_title'] ?? '')) ?: 'General section' }}</span>
            <span class="chip">Type: {{ \Illuminate\Support\Str::headline($selectedQuestion['type'] ?? 'Question') }}</span>
            <span class="chip">{{ $selectedQuestionStats['headline'] ?? 'No responses yet.' }}</span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">{{ $answerBreakdown['title'] ?? 'Answer Pattern' }}</div>
        <div class="section-body">
            <div class="muted" style="margin-bottom: 6px;">{{ $answerBreakdown['subtitle'] ?? 'Selected answer summary.' }}</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 70%;">Answer / Pattern</th>
                        <th style="width: 15%;">Count</th>
                        <th style="width: 15%;">Share</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($breakdownRows as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $row['count'] }}</td>
                            <td>{{ number_format((float) $row['percent'], 1) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">No answer pattern is available for this question yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Real Response Data</div>
        <div class="section-body">
            <table class="response-table">
                @if ($isAllExport)
                    <thead>
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th style="width: 18%;">Respondent</th>
                            <th style="width: 28%;">Question</th>
                            <th style="width: 29%;">Answer</th>
                            <th style="width: 8%;">Status</th>
                            <th style="width: 13%;">Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($answerRows as $answerRow)
                            @if (empty($answerRow['answers']))
                                <tr>
                                    <td>{{ $answerRow['response_number'] }}</td>
                                    <td>
                                        <strong>{{ $answerRow['respondent_name'] }}</strong>
                                        <div class="muted">{{ $answerRow['respondent_organization'] ?: 'No organization captured' }}</div>
                                    </td>
                                    <td colspan="3">No question answers were captured in this submission.</td>
                                    <td>{{ $answerRow['submitted_at'] }}</td>
                                </tr>
                            @else
                                @foreach (($answerRow['answers'] ?? []) as $answerItem)
                                    <tr>
                                        <td>{{ $answerRow['response_number'] }}</td>
                                        <td>
                                            <strong>{{ $answerRow['respondent_name'] }}</strong>
                                            <div class="muted">{{ $answerRow['respondent_organization'] ?: 'No organization captured' }}</div>
                                            @if ($answerRow['respondent_email'])
                                                <div class="muted">{{ $answerRow['respondent_email'] }}</div>
                                            @endif
                                            @if ($answerRow['respondent_phone'])
                                                <div class="muted">{{ $answerRow['respondent_phone'] }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $answerItem['question'] ?? 'Question' }}</strong>
                                            <div class="muted">{{ $answerItem['section_title'] ?? 'General section' }} | {{ $answerItem['type'] ?? 'Question' }}</div>
                                        </td>
                                        <td>{{ $answerItem['answer_value'] ?? 'No answer captured.' }}</td>
                                        <td>
                                            <span class="status {{ !empty($answerItem['has_answer']) ? 'answered' : 'missing' }}">
                                                {{ !empty($answerItem['has_answer']) ? 'Answered' : 'Missing' }}
                                            </span>
                                        </td>
                                        <td>{{ $answerRow['submitted_at'] }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        @empty
                            <tr>
                                <td colspan="6">No responses were submitted for this survey link.</td>
                            </tr>
                        @endforelse
                    </tbody>
                @else
                    <thead>
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th style="width: 18%;">Respondent</th>
                            <th style="width: 18%;">Organization</th>
                            <th style="width: 9%;">Status</th>
                            <th style="width: 36%;">Selected Question Answer</th>
                            <th style="width: 15%;">Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($answerRows as $answerRow)
                            <tr>
                                <td>{{ $answerRow['response_number'] }}</td>
                                <td>
                                    <strong>{{ $answerRow['respondent_name'] }}</strong>
                                    @if ($answerRow['respondent_email'])
                                        <div class="muted">{{ $answerRow['respondent_email'] }}</div>
                                    @endif
                                    @if ($answerRow['respondent_phone'])
                                        <div class="muted">{{ $answerRow['respondent_phone'] }}</div>
                                    @endif
                                </td>
                                <td>{{ $answerRow['respondent_organization'] ?: 'No organization captured' }}</td>
                                <td>
                                    <span class="status {{ $answerRow['has_answer'] ? 'answered' : 'missing' }}">
                                        {{ $answerRow['has_answer'] ? 'Answered' : 'Missing' }}
                                    </span>
                                </td>
                                <td>{{ $answerRow['answer_value'] }}</td>
                                <td>{{ $answerRow['submitted_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No responses were submitted for this survey link.</td>
                            </tr>
                        @endforelse
                    </tbody>
                @endif
            </table>
        </div>
    </div>

    <div class="footer">
        Survey response export | Token: {{ $surveyLink->public_token }} | {{ config('app.name', 'ATTP') }}
    </div>
</body>
</html>
