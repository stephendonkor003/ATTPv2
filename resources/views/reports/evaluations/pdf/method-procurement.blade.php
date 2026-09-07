<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $methodDefinition['label'] ?? 'Procurement' }} Evaluation Management Report</title>
    <style>
        @page { size: A4 landscape; margin: 29mm 12mm 18mm; }
        body { margin: 0; color: #233b45; font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; line-height: 1.45; }
        .management-document-header { position: fixed; top: -29mm; left: -12mm; right: -12mm; padding: 12px 12mm 11px; background: #123a48; border-bottom: 4px solid #c5a45d; color: #ffffff; }
        .management-document-brand { display: table; width: 100%; }
        .management-document-copy, .management-document-logo { display: table-cell; vertical-align: middle; }
        .management-document-logo { width: 100px; text-align: right; }
        .management-document-logo img { max-height: 39px; max-width: 88px; padding: 3px; background: #ffffff; }
        .management-document-eyebrow { color: #a4e0d4; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .management-document-header h1 { margin: 3px 0; color: #ffffff; font-size: 18px; line-height: 1.25; }
        .management-document-header p { margin: 3px 0 0; color: #e3edf0; font-size: 8px; }
        .management-document-footer { position: fixed; bottom: -12mm; left: 0; right: 0; padding-top: 6px; border-top: 1px solid #afc6ce; color: #607983; font-size: 6.8px; }
        .management-document-footer .right { margin-left: 16px; }
    </style>
    @include('reports.evaluations.pdf.management-styles')
</head>
<body>
    <header class="management-document-header">
        <div class="management-document-brand">
            <div class="management-document-copy">
                <div class="management-document-eyebrow">{{ $platformName ?? 'Africa Think Tank Platform' }} / {{ $methodDefinition['label'] ?? 'Procurement' }}</div>
                <h1>Evaluation Management Report</h1>
                <p>{{ $procurement->title ?: 'Untitled procurement' }}</p>
                <p>{{ $procurement->reference_no ?: 'Reference not recorded' }} &middot; {{ $methodDefinition['mode'] ?? 'Evaluation' }}</p>
            </div>
            @if (!empty($logoDataUri))
                <div class="management-document-logo"><img src="{{ $logoDataUri }}" alt="{{ $platformName ?? 'Africa Think Tank Platform' }}"></div>
            @endif
        </div>
    </header>
    <footer class="management-document-footer">
        {{ $platformName ?? 'Africa Think Tank Platform' }} &middot; {{ $procurement->reference_no ?: 'Evaluation report' }}
        <span class="right">Generated {{ data_get($management ?? [], 'generated_at', now()->format('d M Y, H:i T')) }}</span>
    </footer>
    @include('reports.evaluations.pdf.management-report', ['management' => $management ?? []])
</body>
</html>
