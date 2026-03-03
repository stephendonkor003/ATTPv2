<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Evaluation Template - {{ $evaluation->name }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
        }
        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }
        .meta {
            margin-top: 6px;
            color: #4b5563;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border: 1px solid #9ca3af;
            border-radius: 10px;
            margin-right: 6px;
            margin-bottom: 6px;
            font-size: 10px;
        }
        .section {
            margin-top: 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        .section-header {
            background: #f3f4f6;
            padding: 7px 10px;
            font-weight: 700;
        }
        .section-desc {
            color: #4b5563;
            padding: 8px 10px 0 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
            font-size: 11px;
        }
        th {
            background: #f9fafb;
            text-align: left;
        }
        .num {
            text-align: right;
            white-space: nowrap;
        }
        .empty {
            padding: 12px 10px;
            color: #6b7280;
        }
        .footer {
            margin-top: 14px;
            font-size: 10px;
            color: #6b7280;
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $totalCriteria = $evaluation->sections->sum(fn ($section) => $section->criteria->count());
    @endphp

    <div class="header">
        <p class="title">Evaluation Template: {{ $evaluation->name }}</p>
        <div class="meta">
            @if($evaluation->description)
                {{ $evaluation->description }}<br>
            @endif
            Generated on {{ now()->format('Y-m-d H:i') }}
        </div>
        <div style="margin-top: 8px;">
            <span class="badge">Type: {{ ucfirst($evaluation->type) }}</span>
            <span class="badge">Status: {{ ucfirst($evaluation->status) }}</span>
            <span class="badge">Sections: {{ $evaluation->sections->count() }}</span>
            <span class="badge">Criteria: {{ $totalCriteria }}</span>
            @if($evaluation->type === 'services')
                <span class="badge">Total Max Score: {{ number_format($overallTotal, 2) }}</span>
            @endif
        </div>
    </div>

    @forelse ($evaluation->sections as $index => $section)
        <div class="section">
            <div class="section-header">
                {{ $index + 1 }}. {{ $section->name }}
                @if($evaluation->type === 'services')
                    <span style="float: right;">Section Max: {{ number_format((float) ($sectionTotals[$section->id] ?? 0), 2) }}</span>
                @else
                    <span style="float: right;">{{ $section->criteria->count() }} criteria</span>
                @endif
            </div>

            @if($section->description)
                <div class="section-desc">{{ $section->description }}</div>
            @endif

            @if($section->criteria->isEmpty())
                <div class="empty">No criteria defined in this section.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 30%;">Criteria</th>
                            <th>Description</th>
                            @if ($evaluation->type === 'services')
                                <th style="width: 15%;" class="num">Max Score</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section->criteria as $criterionIndex => $criterion)
                            <tr>
                                <td>{{ $criterionIndex + 1 }}</td>
                                <td>{{ $criterion->name }}</td>
                                <td>{{ $criterion->description ?: '—' }}</td>
                                @if ($evaluation->type === 'services')
                                    <td class="num">{{ number_format((float) $criterion->max_score, 2) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <div class="empty">No sections defined for this evaluation.</div>
    @endforelse

    <div class="footer">
        {{ config('app.name') }} - Evaluation Template Export
    </div>
</body>
</html>
