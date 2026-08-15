<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Evaluation Form Template - {{ $evaluation->name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 108px 24px 58px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #243746;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            line-height: 1.34;
        }

        .page-header {
            position: fixed;
            top: -90px;
            right: 0;
            left: 0;
            height: 72px;
            padding: 9px 11px;
            border-bottom: 4px solid #f4b942;
            background: #102a43;
            color: #fff;
        }

        .header-table,
        .context-table,
        .summary-table,
        .section-heading,
        .criteria-table,
        .subtotal-table,
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .brand-cell {
            width: 92px;
        }

        .logo-frame {
            display: block;
            width: 82px;
            height: 45px;
            overflow: hidden;
            border: 2px solid #7d9bb5;
            background: #fff;
        }

        .logo-frame img {
            width: 82px;
            height: 47px;
        }

        .report-title {
            margin: 0 0 2px;
            color: #fff;
            font-size: 16px;
            font-weight: 900;
            line-height: 1.05;
        }

        .report-subtitle {
            color: #f8d77a;
            font-size: 8px;
            font-weight: 800;
        }

        .header-side {
            width: 132px;
            color: #dbeafe;
            font-size: 6.2px;
            line-height: 1.45;
            text-align: right;
        }

        .header-side strong {
            color: #fff;
            font-size: 6.8px;
        }

        .document-section {
            margin-top: 8px;
        }

        .document-section:first-of-type {
            margin-top: 0;
        }

        .document-section-title {
            padding: 5px 7px;
            background: #176b87;
            color: #fff;
            font-size: 7px;
            font-weight: 900;
            letter-spacing: .45px;
            text-transform: uppercase;
        }

        .document-section-note {
            padding: 4px 7px;
            border: 1px solid #d7e0ea;
            border-top: 0;
            background: #f8fafc;
            color: #64748b;
            font-size: 6px;
        }

        .context-table td,
        .summary-table td {
            border: 1px solid #d7e0ea;
            padding: 5px 6px;
            vertical-align: top;
        }

        .context-table td {
            width: 25%;
        }

        .field-label,
        .metric-label {
            display: block;
            color: #64748b;
            font-size: 5.5px;
            font-weight: 900;
            letter-spacing: .2px;
            text-transform: uppercase;
        }

        .field-value {
            display: block;
            margin-top: 2px;
            color: #102a43;
            font-size: 6.8px;
            font-weight: 800;
        }

        .field-detail {
            display: block;
            margin-top: 1px;
            color: #718096;
            font-size: 5.4px;
        }

        .description-box,
        .method-note {
            margin-top: 6px;
            padding: 6px 7px;
            border: 1px solid #cbd8e5;
            background: #f8fafc;
            color: #41566a;
        }

        .description-box strong,
        .method-note strong {
            color: #102a43;
        }

        .method-note {
            border-color: #b8e2d2;
            background: #edf9f4;
            color: #176348;
        }

        .method-note.categorical {
            border-color: #b9dbe8;
            background: #eef8fc;
            color: #17667e;
        }

        .summary-table td {
            width: 33.333%;
        }

        .metric-value {
            display: block;
            margin-top: 2px;
            color: #102a43;
            font-size: 10px;
            font-weight: 900;
        }

        .metric-meta {
            display: block;
            margin-top: 1px;
            color: #64748b;
            font-size: 5.3px;
        }

        .outline-title {
            margin-top: 10px;
            padding: 6px 8px;
            border-bottom: 3px solid #f4b942;
            background: #102a43;
            color: #fff;
            font-size: 8px;
            font-weight: 900;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .outline-note {
            padding: 4px 7px;
            border: 1px solid #d7e0ea;
            border-top: 0;
            background: #f8fafc;
            color: #64748b;
            font-size: 5.8px;
        }

        .section-block {
            margin-top: 7px;
        }

        .section-heading {
            page-break-after: avoid;
        }

        .section-heading td {
            padding: 6px 7px;
            border: 1px solid;
            vertical-align: middle;
        }

        .section-accent {
            width: 7px;
            padding: 0 !important;
        }

        .section-identity {
            font-size: 7px;
            font-weight: 900;
        }

        .section-level {
            display: block;
            margin-bottom: 1px;
            font-size: 5.2px;
            font-weight: 900;
            letter-spacing: .25px;
            text-transform: uppercase;
        }

        .section-counts {
            width: 142px;
            font-size: 5.5px;
            font-weight: 800;
            line-height: 1.45;
            text-align: right;
        }

        .section-description {
            padding: 5px 7px;
            border: 1px solid #d7e0ea;
            border-top: 0;
            background: #fff;
            color: #64748b;
            font-size: 6px;
            page-break-after: avoid;
        }

        .criteria-table {
            table-layout: fixed;
            page-break-inside: auto;
        }

        .criteria-table thead {
            display: table-header-group;
        }

        .criteria-table tr {
            page-break-inside: avoid;
        }

        .criteria-table th {
            padding: 4px 5px;
            border: 1px solid #b8c8d8;
            background: #e8f0f5;
            color: #102a43;
            font-size: 5.5px;
            font-weight: 900;
            text-align: left;
            text-transform: uppercase;
        }

        .criteria-table td {
            padding: 5px;
            border: 1px solid #d7e0ea;
            color: #334e68;
            font-size: 6.2px;
            vertical-align: top;
        }

        .criteria-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .criterion-number {
            width: 9%;
            color: #64748b !important;
            font-weight: 800;
            white-space: nowrap;
        }

        .criterion-name {
            width: 29%;
            color: #102a43 !important;
            font-weight: 900;
        }

        .criterion-guidance {
            width: 44%;
        }

        .criterion-value {
            width: 18%;
            font-weight: 900;
            text-align: right;
        }

        .response-cell {
            width: 30%;
        }

        .choice {
            display: block;
            margin-bottom: 2px;
            white-space: nowrap;
        }

        .choice-box {
            display: inline-block;
            width: 7px;
            height: 7px;
            margin-right: 3px;
            border: 1px solid #829ab1;
            background: #fff;
            vertical-align: -1px;
        }

        .grouping-note {
            padding: 7px;
            border: 1px solid #d7e0ea;
            border-top: 0;
            background: #f8fafc;
            color: #64748b;
            font-size: 6px;
            font-style: italic;
        }

        .subtotal-table {
            page-break-inside: avoid;
        }

        .subtotal-table td {
            padding: 5px 7px;
            border: 1px solid #b8c8d8;
            background: #edf4f8;
            color: #294e63;
            font-size: 6px;
            font-weight: 800;
        }

        .subtotal-value {
            width: 47%;
            color: #102a43 !important;
            text-align: right;
        }

        .empty-state {
            margin-top: 8px;
            padding: 18px 12px;
            border: 1px solid #d7e0ea;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
        }

        .page-footer {
            position: fixed;
            right: 0;
            bottom: -42px;
            left: 0;
            padding: 6px 10px 5px;
            border-top: 3px solid #f4b942;
            background: #102a43;
            color: #dbeafe;
            font-size: 5.7px;
        }

        .footer-table td {
            width: 33.333%;
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .footer-brand {
            color: #fff;
            font-weight: 900;
        }

        .footer-brand span,
        .footer-context span,
        .footer-page span {
            display: block;
            margin-top: 1px;
            color: #a9c5d7;
            font-size: 5px;
        }

        .footer-context {
            color: #f8d77a;
            font-weight: 800;
            text-align: center;
        }

        .footer-page {
            color: #fff;
            font-weight: 900;
            text-align: right;
        }

        .page-number:after {
            content: "Page " counter(page) " of " counter(pages);
        }
    </style>
</head>
<body>
@php
    $sectionOutline = \App\Support\EvaluationSectionHierarchy::flattened($evaluation);
    $totalCriteria = $sectionOutline->sum(fn (array $node): int => $node['section']->criteria->count());
    $totalSections = $sectionOutline->count();
    $rootSectionCount = $sectionOutline->where('depth', 0)->count();
    $tierCount = $sectionOutline->isEmpty() ? 0 : $sectionOutline->max('depth') + 1;
    $subtotalCount = $sectionOutline->filter(fn (array $node): bool => (bool) $node['section']->show_subtotal)->count();
    $generatedAt = $reportGeneratedAt ?? now(config('app.timezone', 'UTC'));
    $generator = $generatedBy ?? auth()->user();
    $reference = $documentReference ?? 'ATTP-EVAL-'.strtoupper(substr((string) $evaluation->getKey(), 0, 8));
    $statusLabel = $evaluation->status === 'close' ? 'Closed' : ucfirst((string) $evaluation->status);
    $palette = [
        ['accent' => '#3157d5', 'soft' => '#eef2ff', 'deep' => '#203b98'],
        ['accent' => '#0f8a72', 'soft' => '#ecfdf7', 'deep' => '#08604f'],
        ['accent' => '#7a4bd0', 'soft' => '#f5f0ff', 'deep' => '#56319d'],
        ['accent' => '#cf6a16', 'soft' => '#fff5e9', 'deep' => '#91440b'],
        ['accent' => '#087f9c', 'soft' => '#eafafd', 'deep' => '#075a70'],
        ['accent' => '#c33d67', 'soft' => '#fff0f5', 'deep' => '#8c2949'],
        ['accent' => '#52731d', 'soft' => '#f3f9e9', 'deep' => '#38510f'],
        ['accent' => '#5b6474', 'soft' => '#f2f4f7', 'deep' => '#353c48'],
    ];
@endphp

<header class="page-header">
    <table class="header-table">
        <tr>
            <td class="brand-cell">
                <span class="logo-frame">
                    <img src="{{ public_path('assets/images/attp-logo.jpeg') }}" alt="Africa Think Tank Platform">
                </span>
            </td>
            <td>
                <h1 class="report-title">Evaluation Form Template</h1>
                <div class="report-subtitle">{{ $evaluation->name }}</div>
            </td>
            <td class="header-side">
                <strong>{{ strtoupper($evaluation->typeLabel()) }}</strong><br>
                {{ strtoupper($statusLabel) }} TEMPLATE<br>
                Generated {{ $generatedAt->format('d M Y, H:i') }}<br>
                {{ $generatedAt->timezoneName }}
            </td>
        </tr>
    </table>
</header>

<section class="document-section">
    <div class="document-section-title">Template context and control</div>
    <div class="document-section-note">Controlled evaluation-form specification for configuration, review and authorised use.</div>
    <table class="context-table">
        <tr>
            <td>
                <span class="field-label">Portfolio</span>
                <span class="field-value">{{ $evaluation->portfolio?->name ?: 'Not assigned' }}</span>
            </td>
            <td>
                <span class="field-label">Evaluation type</span>
                <span class="field-value">{{ $evaluation->typeLabel() }}</span>
                <span class="field-detail">{{ $evaluation->usesNumericScoring() ? 'Numeric scoring' : 'Categorical assessment' }}</span>
            </td>
            <td>
                <span class="field-label">Template owner</span>
                <span class="field-value">{{ $evaluation->creator?->name ?: 'ATTP Evaluation Team' }}</span>
                @if($evaluation->creator?->email)
                    <span class="field-detail">{{ $evaluation->creator->email }}</span>
                @endif
            </td>
            <td>
                <span class="field-label">Document reference</span>
                <span class="field-value">{{ $reference }}</span>
                <span class="field-detail">Status: {{ $statusLabel }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="field-label">Generated by</span>
                <span class="field-value">{{ $generator?->name ?: 'Authorised platform user' }}</span>
                @if($generator?->email)
                    <span class="field-detail">{{ $generator->email }}</span>
                @endif
            </td>
            <td colspan="2">
                <span class="field-label">Generated at</span>
                <span class="field-value">{{ $generatedAt->format('d F Y, H:i:s T') }}</span>
                <span class="field-detail">Exported from the ATTP evaluation configuration register</span>
            </td>
        </tr>
    </table>

    @if($evaluation->description)
        <div class="description-box"><strong>Purpose and instructions:</strong> {{ $evaluation->description }}</div>
    @endif
</section>

<section class="document-section">
    <div class="document-section-title">Form architecture summary</div>
    <div class="document-section-note">The outline below supports up to four tiers: Section, Sub-Section, Sub-Sub Section and Sub-Sub-Sub Section.</div>
    <table class="summary-table">
        <tr>
            <td>
                <span class="metric-label">Main sections</span>
                <span class="metric-value">{{ number_format($rootSectionCount) }}</span>
                <span class="metric-meta">Each main section carries its own colour family</span>
            </td>
            <td>
                <span class="metric-label">Total sections</span>
                <span class="metric-value">{{ number_format($totalSections) }}</span>
                <span class="metric-meta">Across {{ number_format($tierCount) }} {{ Str::plural('tier', $tierCount) }} in use</span>
            </td>
            <td>
                <span class="metric-label">Questions / criteria</span>
                <span class="metric-value">{{ number_format($totalCriteria) }}</span>
                <span class="metric-meta">Each criterion belongs to one section only</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="metric-label">{{ $evaluation->usesNumericScoring() ? 'Subtotal displays' : 'Category summaries' }}</span>
                <span class="metric-value">{{ number_format($subtotalCount) }}</span>
                <span class="metric-meta">Enabled section and descendant roll-ups</span>
            </td>
            <td>
                <span class="metric-label">Assessment method</span>
                <span class="metric-value" style="font-size: 8px;">{{ $evaluation->usesNumericScoring() ? 'Numeric score' : 'Category count' }}</span>
                <span class="metric-meta">Type-aware totals; criteria are never counted twice overall</span>
            </td>
            <td>
                @if($evaluation->usesNumericScoring())
                    <span class="metric-label">Overall maximum</span>
                    <span class="metric-value">{{ number_format($overallTotal, 2) }}</span>
                    <span class="metric-meta">Configured maximum across all criteria</span>
                @else
                    <span class="metric-label">Available responses</span>
                    <span class="metric-value" style="font-size: 7px;">{{ implode(' / ', $evaluation->decisionOptions()) }}</span>
                    <span class="metric-meta">Reported as a distribution, not a synthetic score</span>
                @endif
            </td>
        </tr>
    </table>

    @if($evaluation->usesNumericScoring())
        <div class="method-note"><strong>Subtotal rule:</strong> an enabled subtotal adds the submitted numeric scores within that section and all of its descendants, and displays the corresponding configured maximum.</div>
    @else
        <div class="method-note categorical"><strong>Subtotal rule:</strong> an enabled subtotal counts {{ implode(', ', $evaluation->decisionOptions()) }} responses within that section and all descendants. It does not convert categories into a numeric score or ranking.</div>
    @endif
</section>

<div class="outline-title">Evaluation form outline</div>
<div class="outline-note">Numbering and indentation show the exact four-tier relationship. Question counts distinguish criteria attached directly to a section from the full descendant roll-up.</div>

@forelse($sectionOutline as $node)
    @php
        $section = $node['section'];
        $depth = (int) $node['depth'];
        $colors = $palette[((int) ($node['root_index'] ?? 0)) % count($palette)];
        $directCriteriaCount = $section->criteria->count();
        $subtreeCriteriaCount = $section->subtreeCriteria()->count();
        $headingBackground = $depth === 0 ? $colors['accent'] : $colors['soft'];
        $headingColor = $depth === 0 ? '#ffffff' : $colors['deep'];
        $indent = min($depth * 11, 33);
    @endphp

    <div class="section-block" style="margin-left: {{ $indent }}px;">
        <table class="section-heading" style="background: {{ $headingBackground }}; color: {{ $headingColor }};">
            <tr>
                <td class="section-accent" style="border-color: {{ $colors['accent'] }}; background: {{ $colors['accent'] }};"></td>
                <td class="section-identity" style="border-color: {{ $colors['accent'] }};">
                    <span class="section-level" style="color: {{ $depth === 0 ? '#dcecf3' : $colors['deep'] }};">{{ $node['label'] }}</span>
                    {{ $node['number'] }}. {{ $section->name }}
                </td>
                <td class="section-counts" style="border-color: {{ $colors['accent'] }};">
                    {{ number_format($directCriteriaCount) }} direct {{ Str::plural('question', $directCriteriaCount) }}<br>
                    {{ number_format($subtreeCriteriaCount) }} including descendants
                </td>
            </tr>
        </table>

        @if($section->description)
            <div class="section-description">{{ $section->description }}</div>
        @endif

        @if($section->criteria->isNotEmpty())
            <table class="criteria-table">
                <thead>
                    <tr>
                        <th style="width: 9%;">Ref.</th>
                        <th style="width: {{ $evaluation->usesNumericScoring() ? '29%' : '25%' }};">Evaluation question / criterion</th>
                        <th style="width: {{ $evaluation->usesNumericScoring() ? '44%' : '36%' }};">Description / guidance</th>
                        @if($evaluation->usesNumericScoring())
                            <th style="width: 18%; text-align: right;">Maximum score</th>
                        @else
                            <th style="width: 30%;">Permitted response</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($section->criteria as $criterionIndex => $criterion)
                        <tr>
                            <td class="criterion-number">{{ $node['number'] }}.{{ $criterionIndex + 1 }}</td>
                            <td class="criterion-name">{{ $criterion->name }}</td>
                            <td class="criterion-guidance">{{ $criterion->description ?: 'No additional guidance supplied.' }}</td>
                            @if($evaluation->usesNumericScoring())
                                <td class="criterion-value">{{ number_format((float) $criterion->max_score, 2) }}</td>
                            @else
                                <td class="response-cell">
                                    @foreach($evaluation->decisionOptions() as $option)
                                        <span class="choice"><span class="choice-box"></span>{{ $option }}</span>
                                    @endforeach
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="grouping-note">Structural grouping only. Its {{ number_format($subtreeCriteriaCount) }} {{ Str::plural('question', $subtreeCriteriaCount) }} {{ $subtreeCriteriaCount === 1 ? 'is' : 'are' }} organised in the child sections shown below.</div>
        @endif

        @if($section->show_subtotal)
            <table class="subtotal-table">
                <tr>
                    <td>
                        {{ $node['number'] }} {{ $evaluation->usesNumericScoring() ? 'subtotal' : 'category summary' }} enabled<br>
                        Includes {{ number_format($subtreeCriteriaCount) }} {{ Str::plural('question', $subtreeCriteriaCount) }} in this section and its descendants
                    </td>
                    <td class="subtotal-value">
                        @if($evaluation->usesNumericScoring())
                            Configured maximum: {{ number_format($section->subtotalMaxScore(), 2) }}
                        @else
                            Response counts: {{ implode(' / ', $evaluation->decisionOptions()) }}
                        @endif
                    </td>
                </tr>
            </table>
        @endif
    </div>
@empty
    <div class="empty-state"><strong>No form sections have been configured.</strong><br>This template is not ready for assignment until its section hierarchy and evaluation criteria are added.</div>
@endforelse

<footer class="page-footer">
    <table class="footer-table">
        <tr>
            <td class="footer-brand">
                AFRICA THINK TANK PLATFORM
                <span>Evaluation management and quality assurance</span>
            </td>
            <td class="footer-context">
                {{ $reference }}
                <span>{{ $evaluation->typeLabel() }} evaluation form | Controlled template</span>
            </td>
            <td class="footer-page">
                <span class="page-number"></span>
                <span>Generated {{ $generatedAt->format('d M Y') }}</span>
            </td>
        </tr>
    </table>
</footer>
</body>
</html>
