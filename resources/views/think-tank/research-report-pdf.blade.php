<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $member->name }} Research Outputs</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 11px; line-height: 1.45; }
        .header { background: #0f172a; color: #fff; padding: 18px 20px; border-radius: 10px; }
        .kicker { color: #fde68a; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .title { font-size: 22px; font-weight: 700; margin: 5px 0; }
        .muted { color: #64748b; }
        .section { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-top: 12px; }
        .section-title { font-size: 11px; text-transform: uppercase; color: #475569; font-weight: 700; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #475569; font-size: 10px; text-transform: uppercase; text-align: left; padding: 6px; border-bottom: 1px solid #cbd5e1; }
        td { padding: 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .grid td { width: 25%; border: 0; padding: 5px; }
        .metric { border: 1px solid #e2e8f0; border-radius: 7px; padding: 10px; background: #f8fafc; }
        .metric-label { color: #64748b; font-size: 10px; text-transform: uppercase; font-weight: 700; }
        .metric-value { color: #0f172a; font-size: 15px; font-weight: 700; margin-top: 4px; }
        .bar { height: 10px; border-radius: 99px; background: #e2e8f0; overflow: hidden; margin-top: 5px; }
        .bar span { display: block; height: 10px; background: #0f766e; border-radius: 99px; }
        .pill { display: inline-block; border-radius: 99px; padding: 3px 7px; background: #e0f2fe; color: #075985; font-size: 10px; font-weight: 700; }
        .two-col td { width: 50%; vertical-align: top; border: 0; padding: 6px; }
    </style>
</head>
<body>
<div class="header">
    <div class="kicker">Research Output Profile / {{ $dashboardFilter['label'] }}</div>
    <div class="title">{{ $member->name }}</div>
    <div>{{ $member->consortium?->name ?? 'Consortium not linked' }}{{ $member->country ? ' / ' . $member->country : '' }}</div>
    <div>Generated {{ now()->format('M d, Y H:i') }}</div>
</div>

<table class="grid" style="margin-top: 12px;">
    <tr>
        <td><div class="metric"><div class="metric-label">Outputs</div><div class="metric-value">{{ number_format($researchStats['total']) }}</div></div></td>
        <td><div class="metric"><div class="metric-label">Approved</div><div class="metric-value">{{ number_format($researchStats['approved']) }}</div></div></td>
        <td><div class="metric"><div class="metric-label">Attached Files</div><div class="metric-value">{{ number_format($researchStats['with_files']) }}</div></div></td>
        <td><div class="metric"><div class="metric-label">Publication Dates</div><div class="metric-value">{{ number_format($researchStats['published']) }}</div></div></td>
    </tr>
</table>

<table class="two-col">
    <tr>
        <td>
            <div class="section">
                <div class="section-title">Output Type Graph</div>
                @php $maxType = max($typeCounts->values()->all() ?: [1]); @endphp
                @forelse($typeCounts as $type => $count)
                    @php $width = $maxType > 0 ? min(100, ($count / $maxType) * 100) : 0; @endphp
                    <div><strong>{{ ucfirst(str_replace('_', ' ', $type)) }}</strong> - {{ number_format($count) }}</div>
                    <div class="bar"><span style="width: {{ number_format($width, 2, '.', '') }}%;"></span></div>
                @empty
                    <div class="muted">No output type data found.</div>
                @endforelse
            </div>
        </td>
        <td>
            <div class="section">
                <div class="section-title">Review Status Graph</div>
                @php $maxStatus = max($statusCounts->values()->all() ?: [1]); @endphp
                @forelse($statusCounts as $status => $count)
                    @php $width = $maxStatus > 0 ? min(100, ($count / $maxStatus) * 100) : 0; @endphp
                    <div><strong>{{ ucfirst(str_replace('_', ' ', $status)) }}</strong> - {{ number_format($count) }}</div>
                    <div class="bar"><span style="width: {{ number_format($width, 2, '.', '') }}%; background:#2563eb;"></span></div>
                @empty
                    <div class="muted">No status data found.</div>
                @endforelse
            </div>
        </td>
    </tr>
</table>

<table class="two-col">
    <tr>
        <td>
            <div class="section">
                <div class="section-title">Access Summary</div>
                <div>Attached files: <strong>{{ number_format($researchStats['with_files']) }}</strong></div>
                <div>External links: <strong>{{ number_format($researchStats['with_links']) }}</strong></div>
                <div>No file/link: <strong>{{ number_format(max(0, $researchStats['total'] - $researchStats['with_files'] - $researchStats['with_links'])) }}</strong></div>
            </div>
        </td>
        <td>
            <div class="section">
                <div class="section-title">Publication Readiness</div>
                <div>Publication date set: <strong>{{ number_format($researchStats['published']) }}</strong></div>
                <div>Publication date missing: <strong>{{ number_format($researchStats['draft_unpublished']) }}</strong></div>
            </div>
        </td>
    </tr>
</table>

<div class="section">
    <div class="section-title">Research Output Register</div>
    <table>
        <thead>
        <tr>
            <th>Output</th>
            <th>Type</th>
            <th>Access</th>
            <th>Status</th>
            <th>Published</th>
            <th>Submitted</th>
        </tr>
        </thead>
        <tbody>
        @forelse($outputRecords as $output)
            <tr>
                <td>{{ $output->title }}<br><span class="muted">{{ \Illuminate\Support\Str::limit(strip_tags($output->abstract ?? 'No abstract provided.'), 100) }}</span></td>
                <td>{{ str_replace('_', ' ', ucfirst($output->output_type)) }}</td>
                <td>
                    @if($output->file_path)
                        File attached
                    @elseif($output->external_url)
                        External link
                    @else
                        No file
                    @endif
                </td>
                <td><span class="pill">{{ ucfirst(str_replace('_', ' ', $output->status)) }}</span></td>
                <td>{{ $output->published_on?->format('d M Y') ?? 'N/A' }}</td>
                <td>{{ $output->submitted_at?->format('d M Y') ?? $output->created_at?->format('d M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No research outputs match the selected search.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
