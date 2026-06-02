<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Annex B QASC - {{ $output->title }}</title>
    <style>
        @page { margin: 28px 28px 32px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 10px;
            line-height: 1.42;
        }
        .header {
            border-bottom: 3px solid #1d4ed8;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .kicker {
            color: #1d4ed8;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 3px;
        }
        .intro {
            color: #475569;
            font-size: 9px;
            margin-top: 6px;
        }
        .section {
            border: 1px solid #cbd5e1;
            margin-top: 10px;
        }
        .section-title {
            background: #eff6ff;
            border-bottom: 1px solid #cbd5e1;
            color: #1d4ed8;
            font-size: 10px;
            font-weight: bold;
            padding: 6px 8px;
            text-transform: uppercase;
        }
        .section-body { padding: 8px; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 5px;
            vertical-align: top;
            word-wrap: break-word;
        }
        th {
            background: #0f172a;
            color: #ffffff;
            font-size: 8px;
            text-align: left;
            text-transform: uppercase;
        }
        .meta td {
            width: 25%;
            background: #f8fafc;
        }
        .label {
            color: #64748b;
            display: block;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .value {
            color: #0f172a;
            display: block;
            font-size: 10px;
            font-weight: bold;
            margin-top: 2px;
        }
        .status {
            color: #166534;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status.na { color: #64748b; }
        .signature-grid td {
            width: 50%;
            min-height: 120px;
        }
        .signature-img {
            max-height: 60px;
            max-width: 220px;
            margin: 6px 0;
        }
        .muted {
            color: #64748b;
            font-size: 8px;
        }
        .footer {
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 8px;
            margin-top: 12px;
            padding-top: 7px;
        }
    </style>
</head>
<body>
    @php
        $leadAuthor = (array) ($qasc['lead_author'] ?? []);
        $leadThinkTankRepresentative = (array) ($qasc['lead_think_tank_representative'] ?? []);
    @endphp

    <div class="header">
        <div class="kicker">Annex B / ATTP Quality Assurance Self-Certification</div>
        <div class="title">{{ $qasc['output_title'] ?? $output->title }}</div>
        <div class="intro">
            This self-certification applies to ATTP-supported outputs that the Platform proposes to take through AU channels,
            including AU specialised technical committees, AU organs, and Platform flagship publication routes.
        </div>
    </div>

    <div class="section">
        <div class="section-title">Output Information</div>
        <div class="section-body">
            <table class="meta">
                <tr>
                    <td><span class="label">Output title</span><span class="value">{{ $qasc['output_title'] ?? $output->title }}</span></td>
                    <td><span class="label">Lead author(s)</span><span class="value">{{ $qasc['lead_authors'] ?? 'N/A' }}</span></td>
                    <td><span class="label">Lead think tank</span><span class="value">{{ $qasc['lead_think_tank'] ?? $member->name }}</span></td>
                    <td><span class="label">Consortium</span><span class="value">{{ $qasc['consortium'] ?? 'N/A' }}</span></td>
                </tr>
                <tr>
                    <td><span class="label">Track classification</span><span class="value">{{ $qasc['track_classification'] ?? 'N/A' }}</span></td>
                    <td><span class="label">Original language</span><span class="value">{{ $qasc['original_language'] ?? 'N/A' }}</span></td>
                    <td><span class="label">Intended publication date</span><span class="value">{{ !empty($qasc['intended_publication_date']) ? \Illuminate\Support\Carbon::parse($qasc['intended_publication_date'])->format('d M Y') : 'N/A' }}</span></td>
                    <td><span class="label">Submitted</span><span class="value">{{ $output->submitted_at?->format('d M Y H:i') ?? now()->format('d M Y H:i') }}</span></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Checklist</div>
        <div class="section-body">
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 43%;">Checklist Item</th>
                        <th style="width: 14%;">Confirmed / N/A</th>
                        <th style="width: 22%;">Signed By</th>
                        <th style="width: 16%;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($checklist as $item)
                        @php $status = (string) ($item['status'] ?? 'confirmed'); @endphp
                        <tr>
                            <td>{{ $item['number'] ?? $loop->iteration }}</td>
                            <td>
                                <strong>{{ $item['title'] ?? 'Checklist item' }}</strong>
                                <div class="muted">{{ $item['description'] ?? '' }}</div>
                                <div class="muted">{{ $item['applies_to'] ?? 'Applies to all tracks' }}</div>
                            </td>
                            <td>
                                <span class="status {{ $status === 'not_applicable' ? 'na' : '' }}">
                                    {{ $status === 'not_applicable' ? 'N/A' : 'Confirmed' }}
                                </span>
                            </td>
                            <td>{{ $item['signed_by'] ?? 'N/A' }}</td>
                            <td>{{ !empty($item['signed_date']) ? \Illuminate\Support\Carbon::parse($item['signed_date'])->format('d M Y') : 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Certification Signatures</div>
        <div class="section-body">
            <table class="signature-grid">
                <tr>
                    <td>
                        <strong>Lead Author</strong>
                        <div style="margin-top: 6px;">Name: {{ $leadAuthor['name'] ?? 'N/A' }}</div>
                        <div>
                            Signature:
                            @if($authorSignatureDataUri)
                                <br><img class="signature-img" src="{{ $authorSignatureDataUri }}" alt="Lead author signature">
                            @else
                                <span class="muted">Signature uploaded.</span>
                            @endif
                        </div>
                        <div>Date: {{ !empty($leadAuthor['date']) ? \Illuminate\Support\Carbon::parse($leadAuthor['date'])->format('d M Y') : 'N/A' }}</div>
                    </td>
                    <td>
                        <strong>Lead Think Tank</strong>
                        <div style="margin-top: 6px;">Name: {{ $leadThinkTankRepresentative['name'] ?? 'N/A' }}</div>
                        <div>
                            Signature:
                            @if($thinkTankSignatureDataUri)
                                <br><img class="signature-img" src="{{ $thinkTankSignatureDataUri }}" alt="Lead think tank signature">
                            @else
                                <span class="muted">Signature uploaded.</span>
                            @endif
                        </div>
                        <div>Date: {{ !empty($leadThinkTankRepresentative['date']) ? \Illuminate\Support\Carbon::parse($leadThinkTankRepresentative['date'])->format('d M Y') : 'N/A' }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="footer">
        {{ config('app.name', 'ATTP') }} | {{ $member->name }} | Annex B QASC generated {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
