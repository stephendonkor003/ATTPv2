<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Survey Responses' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #0f172a; }
        h3 { margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px; }
        th { background: #e5e7eb; text-align: left; }
        .small { font-size: 11px; }
    </style>
</head>
<body>
    <h3>{{ $title ?? 'Survey Responses' }}</h3>
    <table>
        <thead>
            <tr>
                <th>Indicator</th>
                <th>Methodology</th>
                <th>Survey Token</th>
                <th>Respondent</th>
                <th>Email</th>
                <th>Org</th>
                <th>Submitted</th>
                <th>Answers (JSON)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($responses as $resp)
                <tr>
                    <td class="small">{{ $resp->indicator->name ?? '' }}</td>
                    <td class="small">{{ $resp->methodology->name ?? '' }}</td>
                    <td class="small">{{ optional($resp->surveyLink)->public_token ?? '' }}</td>
                    <td class="small">{{ $resp->respondent_name }}</td>
                    <td class="small">{{ $resp->respondent_email }}</td>
                    <td class="small">{{ $resp->respondent_organization }}</td>
                    <td class="small">{{ optional($resp->submitted_at)->format('Y-m-d H:i') }}</td>
                    <td class="small">{{ json_encode($resp->answers) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
