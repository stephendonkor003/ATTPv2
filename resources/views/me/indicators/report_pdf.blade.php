<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>M&E Management Report Sheet</title>
    <style>
        @page { margin: 14px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            color: #111827;
        }
        .header {
            border-bottom: 2px solid #334155;
            margin-bottom: 8px;
            padding-bottom: 6px;
            text-align: center;
        }
        .app-name {
            font-size: 12px;
            font-weight: 700;
            margin: 0 0 2px 0;
            color: #0f172a;
        }
        .title {
            font-size: 13px;
            font-weight: bold;
            margin: 0;
            color: #0f172a;
        }
        .meta {
            margin-top: 4px;
            font-size: 8px;
            color: #334155;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 3px 4px;
            vertical-align: top;
            word-break: break-word;
        }
        th {
            background: #e2e8f0;
            font-size: 7.5px;
            text-align: left;
        }
        .small { font-size: 7px; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
    <div class="header">
        <p class="app-name">{{ config('app.name', 'Application') }}</p>
        <p class="title">M&E Management Report Sheet</p>
        <div class="meta">
            Generated: {{ $generatedAt?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s') }}
            @if (!empty($searchTerm))
                | Filter: "{{ $searchTerm }}"
            @endif
            | Total Rows: {{ $rows->count() }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Program</th>
                <th>Project</th>
                <th>Activity</th>
                <th>Sub-Activity</th>
                <th>Indicator</th>
                <th>Owner Type</th>
                <th>Level</th>
                <th>Frequency</th>
                <th>Baseline Type</th>
                <th>Baseline Period</th>
                <th>Baseline Value</th>
                <th>Responsible Party/Person</th>
                <th>Methodology</th>
                <th>Primary Source Type</th>
                <th>Primary Source Value</th>
                <th>Definition</th>
                <th>Target</th>
                <th>Actual</th>
                <th>Achievement</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['program'] ?? '—' }}</td>
                    <td>{{ $row['project'] ?? '—' }}</td>
                    <td>{{ $row['activity'] ?? '—' }}</td>
                    <td>{{ $row['sub_activity'] ?? '—' }}</td>
                    <td>{{ $row['indicator_name'] ?? '—' }}</td>
                    <td>{{ $row['owner_type'] ?? '—' }}</td>
                    <td>{{ $row['indicator_level'] ?? '—' }}</td>
                    <td>{{ $row['frequency'] ?? '—' }}</td>
                    <td>{{ $row['baseline_type'] ?? '—' }}</td>
                    <td>{{ $row['baseline_period'] ?? '—' }}</td>
                    <td>{{ $row['baseline_value'] ?? '—' }}</td>
                    <td>{{ $row['responsible'] ?? '—' }}</td>
                    <td>{{ $row['methodology'] ?? '—' }}</td>
                    <td>{{ $row['primary_source_type'] ?? '—' }}</td>
                    <td>{{ $row['primary_source_value'] ?? '—' }}</td>
                    <td>{{ $row['definition'] ?? '—' }}</td>
                    <td class="nowrap">{{ $row['target'] ?? '—' }}</td>
                    <td class="nowrap">{{ $row['actual'] ?? '—' }}</td>
                    <td class="nowrap">{{ $row['achievement'] ?? '—' }}</td>
                    <td class="nowrap">{{ $row['status'] ?? '—' }}</td>
                    <td>{{ $row['notes'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="21" class="small">No indicators available for export.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
