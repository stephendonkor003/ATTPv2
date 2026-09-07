<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $submission->reference_no }} - Approval submission</title>
    <style>
        @page { margin: 35px 38px 55px; }
        body { font-family: DejaVu Sans, sans-serif; color: #233b31; font-size: 10px; line-height: 1.55; }
        .header { background: #07553d; color: #fff; padding: 22px 25px; border-bottom: 4px solid #c9ab68; }
        .eyebrow { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #cfebdc; }
        h1 { font-size: 24px; margin: 9px 0 4px; }
        h2 { font-size: 13px; color: #07553d; border-bottom: 1px solid #d9e5dd; padding-bottom: 7px; margin-top: 24px; }
        .reference { color: #e0f0e6; font-size: 11px; }
        .notice { background: #fff7e4; border-left: 4px solid #c9ab68; padding: 12px 15px; margin: 20px 0; color: #79531b; }
        .approved { background: #eef8f0; border-color: #07553d; color: #07553d; }
        .muted { color: #697e72; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 8px 10px; vertical-align: top; border-bottom: 1px solid #e0e8e2; overflow-wrap: break-word; word-wrap: break-word; }
        th { background: #edf4ef; text-align: left; font-size: 9px; color: #315840; }
        .label { width: 30%; color: #5e7367; background: #f5f8f6; font-weight: bold; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
        .value { white-space: pre-wrap; }
        .meta { margin-top: 16px; }
        .meta td { border: 0; padding: 3px 0; }
        .footer { position: fixed; bottom: -32px; left: 0; right: 0; border-top: 1px solid #dbe5df; padding-top: 8px; font-size: 8px; color: #718176; }
        .page-number:after { content: counter(page); }
        .document-name { word-break: break-all; }
    </style>
</head>
<body>
    @php
        $requestType = $submission->kind === 'purchase_request' ? 'Purchase request' : 'Disbursement';
        $summary = collect($submission->summary ?? []);
        $formatValue = function ($value) use (&$formatValue): string {
            if ($value === null || $value === '') return 'Not provided';
            if (is_bool($value)) return $value ? 'Yes' : 'No';
            if (is_array($value)) {
                $parts = [];
                foreach ($value as $key => $entry) {
                    $parts[] = (is_string($key) ? $key . ': ' : '') . $formatValue($entry);
                }
                return implode('; ', $parts);
            }
            return (string) $value;
        };
        $lineItems = collect($summary->get('Line items', []));
        $columns = $lineItems->flatMap(fn ($row) => is_array($row) ? array_keys($row) : [])->unique()->values();
    @endphp
    <div class="footer">{{ config('app.name', 'ATTP') }} &nbsp; | &nbsp; {{ $submission->reference_no }} &nbsp; | &nbsp; {{ ucfirst($submission->status) }} <span style="float:right;">Page <span class="page-number"></span></span></div>
    <div class="header">
        <div class="eyebrow">{{ config('app.name', 'ATTP') }} / Administrative office</div>
        <h1>{{ $requestType }}</h1>
        <div class="reference">{{ $submission->reference_no }} &nbsp; | &nbsp; Approval submission</div>
    </div>
    @if ($submission->status === 'approved')
        <div class="notice approved"><strong>Approved.</strong> This submission has been approved and applied to the relevant records.</div>
    @elseif ($submission->status === 'rejected')
        <div class="notice"><strong>Rejected - not effective in reports.</strong> This submission was not approved and has not been applied to financial or procurement reporting.</div>
    @else
        <div class="notice"><strong>Pending approval - not effective in reports.</strong><br>This document records the administrative assistant's submission. It requires Project Coordinator or administrator approval before it can affect financial or procurement reporting. It is not a payment authorization or an approved purchase request.</div>
    @endif
    <table class="meta">
        <tr><td><strong>Submitted by:</strong> {{ $submission->creator?->name ?: 'Administrative assistant' }}</td><td><strong>Submitted:</strong> {{ $submission->created_at?->format('d M Y, H:i T') }}</td></tr>
        <tr><td><strong>Request type:</strong> {{ $requestType }}</td><td><strong>Status:</strong> {{ ucfirst($submission->status) }}</td></tr>
    </table>
    <h2>Submission details</h2>
    <table>
        @forelse ($summary->except('Line items') as $label => $value)
            <tr><td class="label">{{ $label }}</td><td class="value">{{ $formatValue($value) }}</td></tr>
        @empty
            <tr><td class="muted">No additional details provided.</td></tr>
        @endforelse
    </table>
    @if ($lineItems->isNotEmpty())
        <h2>Line items</h2>
        <table>
            <thead><tr><th style="width:18px;">#</th>@foreach ($columns as $column)<th>{{ \Illuminate\Support\Str::headline($column) }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($lineItems as $item)
                    <tr><td>{{ $loop->iteration }}</td>@foreach ($columns as $column)<td>{{ $formatValue($item[$column] ?? null) }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <h2>Supporting documents ({{ count($submission->documents ?? []) }})</h2>
    <table>
        <thead><tr><th style="width:18px;">#</th><th>Filename</th></tr></thead>
        <tbody>
            @forelse ($submission->documents ?? [] as $document)
                <tr><td>{{ $loop->iteration }}</td><td class="document-name">{{ $document['name'] ?? 'Supporting document' }}</td></tr>
            @empty
                <tr><td colspan="2" class="muted">No supporting documents were uploaded.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="muted">Supporting files remain available on the protected review page. This PDF lists their filenames; their contents are not merged into this document.</p>
    @if ($submission->reviewed_at)
        <h2>Review decision</h2>
        <table>
            <tr><td class="label">Reviewed by</td><td>{{ $submission->reviewer?->name ?: 'Authorized reviewer' }}</td></tr>
            <tr><td class="label">Reviewed at</td><td>{{ $submission->reviewed_at->format('d M Y, H:i T') }}</td></tr>
            <tr><td class="label">Decision</td><td>{{ ucfirst($submission->status) }}</td></tr>
            <tr><td class="label">Review note</td><td class="value">{{ $submission->review_note ?: 'No note provided.' }}</td></tr>
        </table>
    @endif
</body>
</html>
