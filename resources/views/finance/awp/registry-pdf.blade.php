<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Work Plans Registry</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111827; }
        .header { background: #102a43; color: #fff; padding: 14px 18px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 4px 0 0; color: #dbeafe; }
        .summary { width: 100%; margin: 14px 0; border-collapse: collapse; }
        .summary td { border: 1px solid #d1d5db; padding: 8px; }
        .summary .label { background: #f3f4f6; font-weight: bold; }
        table.registry { width: 100%; border-collapse: collapse; }
        table.registry th, table.registry td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        table.registry th { background: #e5e7eb; text-align: left; }
        .right { text-align: right; }
        .small { color: #4b5563; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Work Plans Registry</h1>
        <p>Generated {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    <table class="summary">
        <tr>
            <td class="label">Folders</td>
            <td>{{ number_format($summary['folders'] ?? 0) }}</td>
            <td class="label">Programs</td>
            <td>{{ number_format($summary['programs'] ?? 0) }}</td>
            <td class="label">Items Saved</td>
            <td>{{ number_format($summary['items'] ?? 0) }}</td>
            <td class="label">Work Plan Amount</td>
            <td>USD {{ number_format($summary['amount'] ?? 0, 2) }}</td>
        </tr>
    </table>

    <table class="registry">
        <thead>
            <tr>
                <th>Folder</th>
                <th>Program</th>
                <th>Year</th>
                <th>Items</th>
                <th>Planned</th>
                <th>Committed</th>
                <th>Approved</th>
                <th>Latest Update</th>
                <th>Preview Items</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($folders as $folder)
                <tr>
                    <td>{{ $folder['folder_name'] }}</td>
                    <td>{{ $folder['program']?->name ?? 'Program not linked' }}</td>
                    <td>{{ $folder['year'] ?: '' }}</td>
                    <td class="right">{{ number_format($folder['items_count']) }}</td>
                    <td class="right">{{ $folder['currency'] }} {{ number_format($folder['planned_amount'], 2) }}</td>
                    <td class="right">{{ $folder['currency'] }} {{ number_format($folder['committed_amount'], 2) }}</td>
                    <td class="right">{{ number_format($folder['approved_count']) }}</td>
                    <td>{{ $folder['latest_update'] ? \Illuminate\Support\Carbon::parse($folder['latest_update'])->format('Y-m-d H:i') : '' }}</td>
                    <td class="small">{{ collect($folder['items_preview'])->implode(', ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;">No saved work plan folders found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
