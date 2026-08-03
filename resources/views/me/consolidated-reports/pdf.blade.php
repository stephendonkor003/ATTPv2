<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#1f2937}h1{font-size:18px;color:#0b5c45;margin-bottom:4px}.meta{color:#64748b;margin-bottom:14px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:5px;vertical-align:top}th{background:#eaf5f0;text-align:left}.num{text-align:right}.small{font-size:8px;color:#64748b}</style></head><body>
<h1>ATTP Consolidated M&amp;E Performance Report</h1><div class="meta">{{ strtoupper($filters['period_type']) }} &middot; {{ $filters['period_label'] }} {{ $filters['year'] }} &middot; Generated {{ now()->format('d M Y H:i') }} by {{ $generatedBy?->name }}</div>
@php
    $activeFilters = collect([
        'Think tank' => $selectedThinkTank?->name,
        'Geographic scope' => $filters['geographic_scope'] ?? null,
        'Country' => $filters['country'] ?? null,
        'REC' => $filters['rec'] ?? null,
        'Implementing institution type' => $filters['implementing_institution_type'] ?? null,
        'Implementing institution' => $filters['implementing_institution'] ?? null,
        'ATTP priority thematic area' => $filters['priority_theme'] ?? null,
        'Gender' => $filters['gender'] ?? null,
        'Age group' => $filters['age_group'] ?? null,
        'Stakeholder category' => $filters['stakeholder_category'] ?? null,
    ])->filter(fn ($value) => filled($value));
@endphp
@if($activeFilters->isNotEmpty())
    <div class="meta"><strong>Applied filters:</strong> {{ $activeFilters->map(fn ($value, $label) => $label.': '.str($value)->headline())->join(' | ') }}</div>
@endif
<table><thead><tr><th>Indicator</th><th>Roll-up</th><th class="num">Result</th><th class="num">Organizations</th><th class="num">Achievements</th><th class="num">Beneficiaries</th><th>Disaggregation</th></tr></thead><tbody>@forelse($consolidated as $row)<tr><td><strong>{{ $row['indicator']?->indicator_code }}</strong><br>{{ $row['indicator']?->name }}</td><td>{{ $row['rollup_label'] }}@if($row['duplicate_result_count']>0)<br><span class="small">{{ $row['duplicate_result_count'] }} duplicate source result(s) suppressed</span>@endif</td><td class="num">{{ $row['value']!==null?number_format($row['value'],2):'N/A' }}</td><td class="num">{{ $row['organization_count'] }}</td><td class="num">{{ $row['achievement_count'] }}</td><td class="num">{{ number_format($row['beneficiary_count']) }}</td><td>Female {{ number_format($row['gender']->get('female',0)) }}; Male {{ number_format($row['gender']->get('male',0)) }}<br>Youth {{ number_format($row['age_groups']->get('youth_below_35',0)) }}; Adult {{ number_format($row['age_groups']->get('adult_35_plus',0)) }}<br>Stakeholders: {{ $row['stakeholders']->keys()->map(fn($value)=>\Illuminate\Support\Str::headline($value))->join(', ') ?: 'Not recorded' }}<br>Themes: {{ $row['themes']->keys()->map(fn($value)=>\Illuminate\Support\Str::headline($value))->join(', ') ?: 'Not recorded' }}<br><span class="small">{{ $row['countries']->keys()->join(', ') }}@if($row['recs']->isNotEmpty()) &middot; REC: {{ $row['recs']->keys()->map(fn($value)=>strtoupper($value))->join(', ') }}@endif</span></td></tr>@empty<tr><td colspan="7">No approved data for this period.</td></tr>@endforelse</tbody></table>
</body></html>
