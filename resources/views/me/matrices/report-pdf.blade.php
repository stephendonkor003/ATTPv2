<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 13px 13px 42px; }
        body { margin: 0; color: #17343e; font-family: DejaVu Sans, Arial, sans-serif; font-size: 7px; line-height: 1.32; }
        .header { padding: 9px 11px; border-bottom: 4px solid #77adba; background: #075c7a; color: #fff; }
        .header-table,.meta-table,.summary-table,.visual-table,.register-table,.sheet-table,.footer-table { width: 100%; border-collapse: collapse; }
        .brand-cell { width: 118px; }
        .logo-wrap { display: block; width: 106px; height: 55px; overflow: hidden; border: 2px solid rgba(255,255,255,.45); background: #fff; }
        .logo-wrap img { width: 106px; height: 60px; }
        .title { margin: 0 0 2px; color: #fff; font-size: 17px; font-weight: 900; }
        .subtitle { color: #cce8ee; font-size: 7.5px; font-weight: 700; }
        .header-side { color: #d9edf1; font-size: 6.5px; text-align: right; }
        .section { margin-top: 8px; }
        .section-title { padding: 5px 7px; background: #075c7a; color: #fff; font-size: 7px; font-weight: 900; letter-spacing: .4px; text-transform: uppercase; }
        .section-note { padding: 4px 7px; border: 1px solid #d7e3e6; border-top: 0; background: #f7fafb; color: #647980; font-size: 5.8px; }
        .meta-table td,.summary-table td { padding: 5px 6px; border: 1px solid #d7e3e6; vertical-align: top; }
        .meta-table td { width: 25%; }
        .meta-label,.summary-label { display: block; color: #647980; font-size: 5.5px; font-weight: 900; text-transform: uppercase; }
        .meta-value { display: block; margin-top: 2px; color: #17343e; font-size: 6.6px; font-weight: 800; }
        .summary-table td { width: 16.666%; }
        .summary-value { display: block; margin-top: 2px; color: #075c7a; font-size: 10px; font-weight: 900; }
        .summary-meta { display: block; margin-top: 1px; color: #647980; font-size: 5.2px; }
        .control-note { margin-top: 6px; padding: 5px 7px; border: 1px solid #b8decf; background: #edf8f3; color: #176348; }
        .visual-table td { width: 33.333%; padding: 6px 7px; border: 1px solid #d7e3e6; vertical-align: top; }
        .visual-title { margin-bottom: 5px; color: #294850; font-size: 6.2px; font-weight: 900; text-transform: uppercase; }
        .bar-row { margin-bottom: 4px; }
        .bar-label { display: inline-block; width: 62px; color: #647980; font-size: 5.4px; }
        .bar-track { display: inline-block; width: 128px; height: 7px; background: #e8eff1; vertical-align: middle; }
        .bar-fill { display: block; height: 7px; background: #075c7a; }
        .bar-value { display: inline-block; width: 22px; color: #294850; font-size: 5.5px; font-weight: 900; text-align: right; }
        .register-table { table-layout: fixed; page-break-inside: auto; }
        .register-table thead { display: table-header-group; }
        .register-table tr { page-break-inside: avoid; }
        .register-table th { padding: 4px 3px; border: 1px solid #075c7a; background: #075c7a; color: #fff; font-size: 5.2px; font-weight: 900; text-align: left; text-transform: uppercase; vertical-align: middle; }
        .register-table td { padding: 4px 3px; border: 1px solid #d7e3e6; color: #294750; font-size: 5.5px; overflow-wrap: anywhere; vertical-align: top; }
        .register-table tbody tr:nth-child(even) td { background: #f8fafb; }
        .matrix-col { width: 21%; }
        .portfolio-col { width: 13%; }
        .inspection-col { width: 15%; }
        .change-col { width: 18%; }
        .code { color: #075c7a; font-weight: 900; }
        .muted { display: block; margin-top: 1px; color: #6b7e85; font-size: 5px; }
        .status { font-weight: 900; text-transform: uppercase; }
        .status-active { color: #176348; }
        .status-draft { color: #815613; }
        .status-retired { color: #61757c; }
        .page-break { page-break-before: always; }
        .matrix-card { margin-top: 7px; padding: 6px 7px; border: 1px solid #cbdcdf; border-left: 4px solid #075c7a; background: #f7fafb; page-break-inside: avoid; }
        .matrix-card-title { color: #17343e; font-size: 7px; font-weight: 900; }
        .matrix-card-meta { margin-top: 2px; color: #61777f; font-size: 5.5px; }
        .matrix-card-summary { margin-top: 4px; color: #294750; font-size: 5.7px; }
        .sheet-table { margin-top: 4px; table-layout: fixed; }
        .sheet-table th { padding: 3px; border: 1px solid #cbdcdf; background: #e8f2f5; color: #294850; font-size: 5.2px; text-align: left; }
        .sheet-table td { padding: 3px; border: 1px solid #d7e3e6; font-size: 5.2px; }
        .num { text-align: right; white-space: nowrap; }
        .empty { padding: 18px !important; color: #647980 !important; text-align: center; }
        .footer { position: fixed; right: 0; bottom: -32px; left: 0; padding: 6px 10px 5px; border-top: 3px solid #77adba; background: #05465d; color: #d7ebef; font-size: 5.8px; }
        .footer-table td { width: 33.333%; border: 0; padding: 0; vertical-align: middle; }
        .footer-brand { color: #fff; font-weight: 900; }
        .footer-context { color: #cde9ef; text-align: center; }
        .footer-page { color: #fff; font-weight: 800; text-align: right; }
        .page-number:after { content: "Page " counter(page) " of " counter(pages); }
    </style>
</head>
<body>
@php
    $formatBytes = static function ($bytes): string {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 1).' KB';
        return number_format($bytes).' B';
    };
    $statusRows = collect([
        ['label' => 'Active', 'value' => $metrics['active'], 'color' => '#187459'],
        ['label' => 'Draft', 'value' => $metrics['draft'], 'color' => '#b8791f'],
        ['label' => 'Retired', 'value' => $metrics['retired'], 'color' => '#73838a'],
    ]);
    $formatRows = collect(['XLSX', 'XLS', 'CSV', 'PDF'])->map(fn ($format) => [
        'label' => $format,
        'value' => $matrices->filter(fn ($matrix) => $matrix->formatLabel() === $format)->count(),
    ]);
    $portfolioRows = $matrices->groupBy(fn ($matrix) => $matrix->portfolio?->name ?: 'Portfolio unavailable')
        ->map(fn ($rows, $label) => ['label' => $label, 'value' => $rows->count()])
        ->sortByDesc('value')->take(5)->values();
    $barWidth = static fn ($value, $maximum): float => $maximum > 0 ? max(2, min(100, ($value / $maximum) * 100)) : 0;
@endphp

<div class="header">
    <table class="header-table"><tr>
        <td class="brand-cell"><span class="logo-wrap"><img src="{{ public_path('assets/images/attp-logo.jpeg') }}" alt="ATTP"></span></td>
        <td><div class="title">{{ $isIndividual ? 'M&E Matrix Version Control Sheet' : 'M&E Matrix Control Register' }}</div><div class="subtitle">Africa Think Tank Platform · Monitoring, Evaluation and Learning</div></td>
        <td class="header-side">Generated {{ $generatedAt->format('d M Y, H:i') }}<br>By <strong>{{ $generatedBy?->name ?: 'ATTP Secretariat' }}</strong></td>
    </tr></table>
</div>

<div class="section">
    <div class="section-title">Report context and document control</div>
    <div class="section-note">This report is generated directly from the authorized matrix register. Original files remain protected in the Knowledge and Evidence Repository.</div>
    <table class="meta-table"><tr>
        <td><span class="meta-label">Report scope</span><span class="meta-value">{{ $scopeLabel }}</span></td>
        <td><span class="meta-label">Register records</span><span class="meta-value">{{ number_format($metrics['total']) }} controlled versions</span></td>
        <td><span class="meta-label">Governance state</span><span class="meta-value">{{ number_format($metrics['active']) }} active · {{ number_format($metrics['draft']) }} draft · {{ number_format($metrics['retired']) }} retired</span></td>
        <td><span class="meta-label">Report reference</span><span class="meta-value">MATRIX-{{ $generatedAt->format('Ymd-His') }}</span></td>
    </tr></table>
    <div class="control-note"><strong>Control statement:</strong> activation identifies the operational source of truth for a matrix code. Earlier active versions are retired but retained, and every uploaded file is synchronized with the evidence repository.</div>
</div>

<div class="section">
    <div class="section-title">Executive control summary</div>
    <table class="summary-table"><tr>
        <td><span class="summary-label">Matrix codes</span><span class="summary-value">{{ number_format($metrics['codes']) }}</span><span class="summary-meta">Distinct controlled series</span></td>
        <td><span class="summary-label">Activation coverage</span><span class="summary-value">{{ number_format($metrics['active_coverage'], 1) }}%</span><span class="summary-meta">Codes with active versions</span></td>
        <td><span class="summary-label">Portfolios</span><span class="summary-value">{{ number_format($metrics['portfolios']) }}</span><span class="summary-meta">Represented in scope</span></td>
        <td><span class="summary-label">Workbook sheets</span><span class="summary-value">{{ number_format($metrics['sheets']) }}</span><span class="summary-meta">{{ number_format($metrics['rows']) }} inspected rows</span></td>
        <td><span class="summary-label">Workbook controls</span><span class="summary-value">{{ number_format($metrics['validations']) }}</span><span class="summary-meta">{{ number_format($metrics['formulas']) }} formulas</span></td>
        <td><span class="summary-label">Controlled storage</span><span class="summary-value">{{ $formatBytes($metrics['storage_bytes']) }}</span><span class="summary-meta">Repository-backed files</span></td>
    </tr></table>
</div>

<div class="section">
    <div class="section-title">Graphical register profile</div>
    <table class="visual-table"><tr>
        <td><div class="visual-title">Lifecycle distribution</div>@php($max = max(1, (int) $statusRows->max('value')))@foreach($statusRows as $row)<div class="bar-row"><span class="bar-label">{{ $row['label'] }}</span><span class="bar-track"><i class="bar-fill" style="width:{{ $barWidth($row['value'], $max) }}%;background:{{ $row['color'] }}"></i></span><span class="bar-value">{{ $row['value'] }}</span></div>@endforeach</td>
        <td><div class="visual-title">File format mix</div>@php($max = max(1, (int) $formatRows->max('value')))@foreach($formatRows as $row)<div class="bar-row"><span class="bar-label">{{ $row['label'] }}</span><span class="bar-track"><i class="bar-fill" style="width:{{ $barWidth($row['value'], $max) }}%"></i></span><span class="bar-value">{{ $row['value'] }}</span></div>@endforeach</td>
        <td><div class="visual-title">Leading portfolios</div>@php($max = max(1, (int) $portfolioRows->max('value')))@forelse($portfolioRows as $row)<div class="bar-row"><span class="bar-label">{{ str($row['label'])->limit(18) }}</span><span class="bar-track"><i class="bar-fill" style="width:{{ $barWidth($row['value'], $max) }}%"></i></span><span class="bar-value">{{ $row['value'] }}</span></div>@empty<span class="muted">No portfolio data in scope.</span>@endforelse</td>
    </tr></table>
</div>

<div class="section">
    <div class="section-title">Detailed matrix version register</div>
    <div class="section-note">The register distinguishes lifecycle, effective dates, structural inspection and repository availability. Sheet columns are summed across worksheets.</div>
    <table class="register-table">
        <thead><tr><th class="matrix-col">Matrix / file</th><th class="portfolio-col">Portfolio</th><th>Version</th><th>Status</th><th class="inspection-col">Workbook inspection</th><th>Effective period</th><th>Uploaded / approved</th><th class="change-col">Change summary</th></tr></thead>
        <tbody>
        @forelse($matrices as $matrix)
            @php($inspection = $matrix->inspectionTotals())
            <tr>
                <td><span class="code">{{ $matrix->matrix_code }}</span><br>{{ $matrix->title }}<span class="muted">{{ $matrix->repositoryItem?->original_filename ?: 'Filename unavailable' }} · {{ $matrix->formatLabel() }} · {{ $formatBytes($matrix->repositoryItem?->file_size) }}</span></td>
                <td>{{ $matrix->portfolio?->name ?: 'Portfolio unavailable' }}</td>
                <td class="num">v{{ $matrix->version_number }}</td>
                <td class="status status-{{ $matrix->status }}">{{ $statuses[$matrix->status] ?? str($matrix->status)->headline() }}</td>
                <td>{{ number_format($inspection['sheet_count']) }} sheets · {{ number_format($inspection['data_rows']) }} rows<span class="muted">{{ number_format($inspection['formula_cells']) }} formulas · {{ number_format($inspection['validated_cells']) }} validations</span></td>
                <td>{{ $matrix->effective_from?->format('d M Y') ?: 'Not dated' }}<span class="muted">to {{ $matrix->effective_to?->format('d M Y') ?: 'open ended' }}</span></td>
                <td>{{ $matrix->created_at?->format('d M Y') }} · {{ $matrix->createdBy?->name ?: 'System migration' }}<span class="muted">{{ $matrix->approved_at ? 'Approved '.$matrix->approved_at->format('d M Y').' by '.($matrix->approvedBy?->name ?: 'authorized officer') : 'Not approved' }}</span></td>
                <td>{{ $matrix->change_summary ?: 'No change summary recorded.' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No matrix versions match the selected report scope.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($matrices->isNotEmpty())
    <div class="page-break"></div>
    <div class="section">
        <div class="section-title">Workbook inspection appendix</div>
        <div class="section-note">Sheet-level statistics are captured at upload. Large sheets may use limited deep-cell inspection to protect platform availability.</div>
        @foreach($matrices as $matrix)
            <div class="matrix-card">
                <div class="matrix-card-title">{{ $matrix->matrix_code }} · Version {{ $matrix->version_number }} · {{ $matrix->title }}</div>
                <div class="matrix-card-meta">{{ $matrix->portfolio?->name ?: 'Portfolio unavailable' }} · {{ $matrix->formatLabel() }} · {{ $statuses[$matrix->status] ?? str($matrix->status)->headline() }}</div>
                <div class="matrix-card-summary"><strong>Change:</strong> {{ $matrix->change_summary ?: 'No change summary recorded.' }}</div>
                @if(!empty(data_get($matrix->import_summary, 'sheets')))
                    <table class="sheet-table"><thead><tr><th>Worksheet</th><th class="num">Rows</th><th class="num">Columns</th><th class="num">Formula cells</th><th class="num">Validated cells</th><th>Inspection note</th></tr></thead><tbody>
                    @foreach(data_get($matrix->import_summary, 'sheets', []) as $sheet)
                        <tr><td>{{ data_get($sheet, 'name', 'Worksheet') }}</td><td class="num">{{ number_format((int) data_get($sheet, 'data_rows', 0)) }}</td><td class="num">{{ number_format((int) data_get($sheet, 'data_columns', 0)) }}</td><td class="num">{{ number_format((int) data_get($sheet, 'formula_cells', 0)) }}</td><td class="num">{{ number_format((int) data_get($sheet, 'validated_cells', 0)) }}</td><td>{{ data_get($sheet, 'inspection_limited') ? 'Deep-cell inspection limited because the sheet exceeds 20,000 dimension cells.' : 'Complete cell-control inspection performed.' }}</td></tr>
                    @endforeach
                    </tbody></table>
                @else
                    <div class="matrix-card-summary">{{ data_get($matrix->import_summary, 'message', 'No sheet-level workbook structure is available.') }}</div>
                @endif
            </div>
        @endforeach
    </div>
@endif

<div class="footer">
    <table class="footer-table"><tr><td class="footer-brand">Africa Think Tank Platform</td><td class="footer-context">M&amp;E Matrix Control · {{ $scopeLabel }}</td><td class="footer-page"><span class="page-number"></span></td></tr></table>
</div>
</body>
</html>
