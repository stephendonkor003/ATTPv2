@php
    $scope = $report->reporting_scope ?? [];
    $scopeLabels = [
        'geographic_scope' => ['label' => 'Geographic scope', 'options' => $achievementTaxonomy['geographic_scopes']],
        'country' => ['label' => 'Country', 'options' => []],
        'rec' => ['label' => 'REC', 'options' => $achievementTaxonomy['recs']],
        'priority_theme' => ['label' => 'Priority theme', 'options' => $achievementTaxonomy['priority_themes']],
        'gender' => ['label' => 'Gender focus', 'options' => $achievementTaxonomy['genders']],
        'age_group' => ['label' => 'Age group', 'options' => $achievementTaxonomy['age_groups']],
        'stakeholder_category' => ['label' => 'Stakeholder', 'options' => $achievementTaxonomy['stakeholder_categories']],
    ];
    $disaggregationPanels = [
        'gender' => 'Beneficiaries by gender',
        'age_group' => 'Beneficiaries by age group',
        'stakeholder_category' => 'Beneficiaries by stakeholder',
        'priority_theme' => 'Beneficiaries by priority theme',
        'country' => 'Beneficiaries by country',
    ];
@endphp

<style>
    .report-insights{margin-top:1rem;border:1px solid var(--report-border);border-radius:1rem;background:#fff;box-shadow:0 8px 22px rgba(25,64,52,.045);overflow:hidden}
    .report-insights__head{display:flex;justify-content:space-between;gap:1rem;padding:1rem 1.15rem;border-bottom:1px solid var(--report-border);background:linear-gradient(120deg,#f2faf6,#f7fbf9)}
    .report-insights__head h5{margin:0;color:var(--report-ink);font-size:.95rem;font-weight:850}.report-insights__head p{margin:.2rem 0 0;color:var(--report-muted);font-size:.72rem}
    .report-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.7rem;padding:1rem 1.15rem}
    .report-kpi{min-width:0;padding:.8rem;border:1px solid #deebe5;border-radius:.75rem;background:#fbfdfc}.report-kpi small{display:block;color:var(--report-muted);font-size:.6rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.report-kpi strong{display:block;margin-top:.22rem;color:var(--report-green);font-size:1.25rem;font-weight:850}
    .report-scope{display:flex;flex-wrap:wrap;gap:.45rem;padding:0 1.15rem 1rem}.report-scope__label{align-self:center;margin-right:.2rem;color:var(--report-ink);font-size:.68rem;font-weight:850}.report-scope__chip{display:inline-flex;gap:.3rem;padding:.3rem .5rem;border-radius:999px;color:#245f49;background:#eaf6f0;font-size:.64rem;font-weight:750}.report-scope__empty{color:var(--report-muted);font-size:.68rem}
    .report-insight-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:.85rem;padding:0 1.15rem 1rem}.report-insight-panel{min-width:0;padding:.9rem;border:1px solid #deebe5;border-radius:.8rem}.report-insight-panel h6{margin:0 0 .75rem;color:var(--report-ink);font-size:.78rem;font-weight:850}
    .report-progress-row{display:grid;grid-template-columns:minmax(100px,.35fr) minmax(160px,1fr) auto;gap:.65rem;align-items:center;margin-top:.65rem}.report-progress-row:first-of-type{margin-top:0}.report-progress-label strong,.report-progress-label small{display:block;overflow-wrap:anywhere}.report-progress-label strong{color:var(--report-ink);font-size:.68rem}.report-progress-label small{color:var(--report-muted);font-size:.6rem}.report-progress-track{height:.58rem;border-radius:999px;background:#e7efeb;overflow:hidden}.report-progress-fill{height:100%;border-radius:inherit;background:linear-gradient(90deg,#0b6b4d,#32a276)}.report-progress-value{min-width:48px;color:var(--report-ink);font-size:.66rem;font-weight:850;text-align:right}
    .report-disagg-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.report-disagg-card{padding:.75rem;border:1px solid #e0ebe6;border-radius:.7rem;background:#fbfdfc}.report-disagg-card h6{margin:0 0 .6rem;font-size:.7rem}.report-disagg-row{display:grid;grid-template-columns:minmax(80px,.6fr) minmax(90px,1fr) auto;gap:.45rem;align-items:center;margin-top:.45rem}.report-disagg-row span{overflow-wrap:anywhere;color:#52675f;font-size:.61rem}.report-disagg-row b{color:var(--report-ink);font-size:.62rem}.report-disagg-empty{color:var(--report-muted);font-size:.64rem}
    .report-reference{padding:0 1.15rem 1.15rem}.report-reference h6{margin:0 0 .7rem;color:var(--report-ink);font-size:.8rem;font-weight:850}.report-reference table{width:100%;table-layout:fixed;font-size:.67rem}.report-reference th{color:#52685f;background:#f3f8f5;font-size:.59rem;letter-spacing:.035em;text-transform:uppercase}.report-reference th,.report-reference td{padding:.6rem;border:1px solid #e0eae5;vertical-align:top;overflow-wrap:anywhere;white-space:normal}.report-reference .code{display:block;color:var(--report-green);font-size:.61rem;font-weight:850}.report-reference .name{display:block;margin-top:.15rem;color:var(--report-ink);font-weight:800}
    @media(max-width:1050px){.report-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.report-insight-grid{grid-template-columns:1fr}}
    @media(max-width:700px){.report-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.report-disagg-grid{grid-template-columns:1fr}.report-progress-row{grid-template-columns:1fr}.report-progress-value{text-align:left}.report-reference table{min-width:920px}}
</style>

<section class="report-insights" id="report-indicator-dashboard">
    <div class="report-insights__head">
        <div><h5><i class="feather-activity me-2" aria-hidden="true"></i>Indicator reporting dashboard</h5><p>Live summary of indicator coverage, target progress, achievements, beneficiary disaggregation and evidence.</p></div>
        <span class="badge bg-light text-dark border align-self-start">{{ $report->periodLabel() }}</span>
    </div>

    <div class="report-kpis">
        <div class="report-kpi"><small>Indicators due</small><strong>{{ number_format($reportAnalytics['summary']['indicators_due']) }}</strong></div>
        <div class="report-kpi"><small>Results reported</small><strong>{{ number_format($reportAnalytics['summary']['results_reported']) }}</strong></div>
        <div class="report-kpi"><small>Achievement records</small><strong>{{ number_format($reportAnalytics['summary']['achievements']) }}</strong></div>
        <div class="report-kpi"><small>Beneficiaries</small><strong>{{ number_format($reportAnalytics['summary']['beneficiaries']) }}</strong></div>
        <div class="report-kpi"><small>Evidence items</small><strong>{{ number_format($reportAnalytics['summary']['evidence_items']) }}</strong></div>
    </div>

    <div class="report-scope" aria-label="Reporter-selected disaggregation scope">
        <span class="report-scope__label">Reporter scope:</span>
        @forelse($scope as $key => $value)
            @if(isset($scopeLabels[$key]))
                <span class="report-scope__chip"><strong>{{ $scopeLabels[$key]['label'] }}:</strong> {{ $scopeLabels[$key]['options'][$value] ?? $value }}</span>
            @endif
        @empty
            <span class="report-scope__empty">No starting scope selected. Detailed achievement rows still enforce each indicator's required disaggregation.</span>
        @endforelse
    </div>

    <div class="report-insight-grid">
        <div class="report-insight-panel">
            <h6>Period target-progress graph</h6>
            @foreach($reportAnalytics['progress'] as $progress)
                <div class="report-progress-row">
                    <div class="report-progress-label"><strong>{{ $progress['code'] }}</strong><small title="{{ $progress['name'] }}">{{ str($progress['name'])->limit(55) }}</small></div>
                    <div class="report-progress-track" role="progressbar" aria-label="{{ $progress['code'] }} target progress" aria-valuenow="{{ $progress['progress'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100"><div class="report-progress-fill" style="width:{{ $progress['bar_width'] }}%"></div></div>
                    <div class="report-progress-value">{{ $progress['progress'] !== null ? number_format($progress['progress'],1).'%' : 'Pending' }}</div>
                </div>
            @endforeach
        </div>

        <div class="report-insight-panel">
            <h6>Disaggregation graphs</h6>
            <div class="report-disagg-grid">
                @foreach($disaggregationPanels as $key => $title)
                    @php
                        $rows = collect($reportAnalytics['disaggregation'][$key]);
                        $maximum = max(1, (int) $rows->max('count'));
                    @endphp
                    <div class="report-disagg-card">
                        <h6>{{ $title }}</h6>
                        @forelse($rows as $row)
                            <div class="report-disagg-row"><span>{{ $row['label'] }}</span><div class="report-progress-track"><div class="report-progress-fill" style="width:{{ min(100, ($row['count'] / $maximum) * 100) }}%"></div></div><b>{{ number_format($row['count']) }}</b></div>
                        @empty
                            <div class="report-disagg-empty">No beneficiary rows reported yet.</div>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="report-reference">
        <h6>Indicator reference and result table</h6>
        <div class="table-responsive">
            <table>
                <thead><tr><th style="width:18%">Indicator</th><th style="width:20%">Definition</th><th style="width:12%">Measurement</th><th style="width:15%">Targets &amp; result</th><th style="width:18%">Required disaggregation</th><th style="width:17%">Collection &amp; evidence</th></tr></thead>
                <tbody>
                    @foreach($report->indicatorResults as $result)
                        @php
                            $indicator = $result->indicator;
                            $dimensions = $indicator?->disaggregationRequirements?->map(fn($requirement) => ($requirement->dimension?->name ?: 'Category').($requirement->is_required ? ' (required)' : ''))->join(', ');
                        @endphp
                        <tr>
                            <td><span class="code">{{ $indicator?->indicator_code }}</span><span class="name">{{ $indicator?->name }}</span><div class="text-muted mt-1">{{ str($indicator?->results_level)->headline() }}</div></td>
                            <td>{{ $indicator?->definitions ?: 'Not configured' }}</td>
                            <td>Type: {{ str($indicator?->value_type ?: 'number')->headline() }}<br>Unit: {{ $indicator?->unit?->symbol ?: $indicator?->unit?->name ?: 'Not set' }}<br>Baseline: {{ $indicator?->baseline_value ?? 'Not set' }}</td>
                            <td>Period: {{ $result->target_value ?? 'Not set' }}<br>Annual: {{ $result->annual_target ?? 'Not set' }}<br>Actual: {{ $indicator?->value_type === 'milestone' ? ($result->actual_text ?: 'Pending') : ($result->actual_value ?? 'Pending') }}</td>
                            <td>{{ $dimensions ?: 'Standard reporting scope' }}</td>
                            <td>{{ $indicator?->data_collection_method ?: 'Not configured' }}<br><strong>MOV:</strong> {{ $indicator?->meansOfVerificationFolder?->name ?: 'Not linked' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
