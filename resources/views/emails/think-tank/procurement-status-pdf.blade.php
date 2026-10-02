<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $notification->heading }}</title>
    <style>
        @page { margin: 24mm 18mm 20mm; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.5; }
        .header { padding: 18px 20px; background: #0b3b2e; color: #fff; border-radius: 8px; }
        .eyebrow { color: #b9e4d2; font-size: 8px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        h1 { margin: 5px 0 0; font-size: 19px; line-height: 1.25; }
        .meta { margin-top: 7px; color: #d8eee5; font-size: 8px; }
        .section { margin-top: 17px; }
        .section-title { margin-bottom: 6px; color: #0f5f4b; font-size: 9px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px 10px; border: 1px solid #dce5e1; vertical-align: top; }
        td.label { width: 31%; background: #f3f7f5; color: #587067; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .transition { padding: 13px 15px; border: 1px solid #b9d8cc; border-radius: 7px; background: #edf8f3; color: #0b4c3b; font-size: 13px; font-weight: 700; }
        .note { padding: 11px 13px; border-left: 4px solid #dc8b18; background: #fff7e8; }
        .footer { position: fixed; right: 0; bottom: -12mm; left: 0; padding-top: 7px; border-top: 1px solid #dce5e1; color: #75847e; font-size: 7px; }
    </style>
</head>
<body>
    <div class="header">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="" style="float:right;width:58px;height:auto;border-radius:5px;">
        @endif
        <div class="eyebrow">{{ $platformName }} &middot; Procurement oversight</div>
        <h1>{{ $notification->heading }}</h1>
        <div class="meta">Workflow event {{ $event->id }} &middot; {{ $event->created_at?->format('d M Y, H:i T') }}</div>
    </div>

    <div class="section">
        <div class="section-title">Recorded transition</div>
        <div class="transition">{{ $fromStatusLabel }} &rarr; {{ $toStatusLabel }}</div>
    </div>

    <div class="section">
        <div class="section-title">Procurement record</div>
        <table>
            <tr><td class="label">Think Tank</td><td>{{ $metadata['think_tank_name'] ?? $event->plan?->member?->name ?? 'Not recorded' }}</td></tr>
            <tr><td class="label">Annual plan</td><td>{{ $metadata['plan_title'] ?? $event->plan?->title }} ({{ $metadata['plan_code'] ?? $event->plan?->plan_code }})</td></tr>
            <tr><td class="label">Fiscal year</td><td>{{ $metadata['fiscal_year'] ?? $event->plan?->fiscal_year }}</td></tr>
            @if($event->item_id)
                <tr><td class="label">Procurement item</td><td>{{ $metadata['item_title'] ?? $event->item?->title }} ({{ $metadata['item_code'] ?? $event->item?->item_code }})</td></tr>
                <tr><td class="label">Estimated amount</td><td>{{ $metadata['currency'] ?? 'USD' }} {{ number_format((float) ($metadata['estimated_amount'] ?? 0), 2) }}</td></tr>
                <tr><td class="label">Supporting record count</td><td>{{ number_format((int) ($metadata['document_count'] ?? 0)) }} private document(s) recorded at transition time</td></tr>
            @endif
            <tr><td class="label">Recorded by</td><td>{{ $metadata['actor_name'] ?? $event->actor?->name ?? 'System workflow' }}</td></tr>
            <tr><td class="label">Action</td><td>{{ \Illuminate\Support\Str::headline($event->action) }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Status message</div>
        <p>{{ $notification->message }}</p>
    </div>

    @if($event->reason)
        <div class="section">
            <div class="section-title">Action note</div>
            <div class="note">{{ $event->reason }}</div>
        </div>
    @endif

    <div class="footer">
        Generated automatically from the recorded AUC-ATTP procurement workflow event. No document paths, credentials or confidential authentication data are included.
    </div>
</body>
</html>
