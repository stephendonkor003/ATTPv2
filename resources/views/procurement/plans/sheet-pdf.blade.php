<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Procurement Plan Sheet</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; font-size: 12px; }
        th { background: #f5f5f5; }
        ul { padding-left: 16px; margin: 0; }
    </style>
</head>
<body>
    <h3>Procurement Plan Sheet</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Code</th>
                <th>Title</th>
                <th>Method</th>
                <th>Milestones</th>
                <th>Stage</th>
                <th>Status</th>
                <th>Start</th>
                <th>End</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plans as $index => $plan)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $plan->procurement_code }}</td>
                    <td>{{ $plan->title }}</td>
                    <td>{{ $plan->methodPlanned->method_name ?? '—' }}</td>
                    <td>
                        @if ($plan->methodPlanned && $plan->methodPlanned->milestones->isNotEmpty())
                            <ul>
                                @foreach ($plan->methodPlanned->milestones as $milestone)
                                    <li>{{ $milestone->title }} ({{ $milestone->target_days }}d)</li>
                                @endforeach
                            </ul>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $plan->stage->stage_name ?? '—' }}</td>
                    <td>{{ $plan->status->name ?? '—' }}</td>
                    <td>{{ $plan->estimated_start_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $plan->estimated_end_date?->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
