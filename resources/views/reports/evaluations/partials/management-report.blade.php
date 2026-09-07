@once
    @push('styles')
        @include('reports.evaluations.partials.management-report-styles')
    @endpush
@endonce

@php
    $managementAnchor = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($managementAnchorPrefix ?? 'evaluation')) ?: 'evaluation';
    $managementOverview = $management['overview'] ?? [];
    $managementNumber = static fn ($value, $decimals = 2, $suffix = '') => $value !== null && $value !== '' && is_numeric($value) ? number_format((float) $value, $decimals).$suffix : 'Not recorded';
    $managementText = static fn ($value) => $value === null || $value === '' ? 'Not recorded' : (string) $value;
    $managementColor = static fn ($color) => is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#0f766e';
@endphp

<div class="eval-management">
    <nav class="eval-nav" aria-label="Evaluation report sections">
        <a href="#{{ $managementAnchor }}-summary"><span class="eval-nav-number">1</span>Summary overview</a>
        <a href="#{{ $managementAnchor }}-results"><span class="eval-nav-number">2</span>Results and rankings</a>
        <a href="#{{ $managementAnchor }}-details"><span class="eval-nav-number">3</span>Evaluator and section scores</a>
        <a href="#{{ $managementAnchor }}-consistency"><span class="eval-nav-number">4</span>Panel insights</a>
        <a href="#{{ $managementAnchor }}-governance"><span class="eval-nav-number">5</span>Governance and next steps</a>
    </nav>

    <section id="{{ $managementAnchor }}-summary" class="eval-report-section" aria-labelledby="{{ $managementAnchor }}-summary-title">
        <header class="eval-section-head"><span class="eval-section-icon"><i class="feather-pie-chart" aria-hidden="true"></i></span><div><h2 id="{{ $managementAnchor }}-summary-title">1 Summary overview</h2><p>Review applicant coverage, panel progress, and the recorded evaluation timeline before comparing results.</p></div></header>
        <div class="eval-section-body">
            <div class="eval-stat-grid">
                @foreach (['total_applicants' => 'Total applicants', 'evaluated_applicants' => 'Applicants with evaluations', 'evaluator_count' => 'Evaluators', 'submitted_reports' => 'Submitted evaluation reports', 'template_count' => 'Evaluation templates'] as $metric => $label)
                    <div class="eval-stat"><strong>{{ $managementNumber($managementOverview[$metric] ?? null, 0) }}</strong><span>{{ $label }}</span></div>
                @endforeach
            </div>
            <div class="eval-time-grid">
                @foreach (['start' => 'First recorded assignment', 'end' => 'Latest finalized submission', 'elapsed' => 'Elapsed evaluation window', 'active_time' => 'Active evaluation time'] as $metric => $label)
                    <div><span class="eval-label">{{ $label }}</span><strong class="eval-value">{{ $managementText($managementOverview[$metric] ?? null) }}</strong></div>
                @endforeach
            </div>
            @if(!empty($managementOverview['timing_note']))<p class="eval-note"><i class="feather-info" aria-hidden="true"></i> {{ $managementOverview['timing_note'] }}</p>@endif

            <h3 class="eval-subtitle">Evaluator participation and timing</h3>
            <div class="eval-table-scroll" tabindex="0" role="region" aria-label="Evaluator participation and timing table">
                <table class="eval-table">
                    <thead><tr><th scope="col">Evaluator</th><th scope="col" class="eval-number">Assigned</th><th scope="col" class="eval-number">Submitted reports</th><th scope="col">First submission</th><th scope="col">Last submission</th><th scope="col">Elapsed window</th><th scope="col">Active time</th></tr></thead>
                    <tbody>
                        @forelse($management['evaluators'] ?? [] as $evaluator)
                            <tr>
                                <td class="eval-person"><div class="eval-evaluator-name"><span class="eval-color-dot" style="background:{{ $managementColor($evaluator['color'] ?? null) }}" aria-hidden="true"></span><strong>{{ $managementText($evaluator['name'] ?? null) }}</strong></div>@if(!empty($evaluator['email']))<small>{{ $evaluator['email'] }}</small>@endif</td>
                                <td class="eval-number">{{ $managementNumber($evaluator['assigned'] ?? null, 0) }}</td>
                                <td class="eval-number">{{ $managementNumber($evaluator['completed'] ?? null, 0) }}</td>
                                <td>{{ $managementText($evaluator['first_submission'] ?? null) }}</td>
                                <td>{{ $managementText($evaluator['last_submission'] ?? null) }}</td>
                                <td>{{ $managementText($evaluator['elapsed'] ?? null) }}</td>
                                <td>{{ $managementText($evaluator['active_time'] ?? null) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No evaluator assignments or submissions are recorded for this report.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @foreach($management['overview_charts'] ?? [] as $chart)
                <figure class="eval-chart-card"><h4>{{ $chart['title'] }}</h4><div class="eval-chart-scroll" tabindex="0" role="region" aria-label="{{ $chart['title'] }}"><img class="eval-chart" src="{{ $chart['src'] }}" alt="{{ $chart['alt'] }}"></div>@if(!empty($chart['note']))<figcaption>{{ $chart['note'] }}</figcaption>@endif</figure>
            @endforeach
        </div>
    </section>

    <section id="{{ $managementAnchor }}-results" class="eval-report-section" aria-labelledby="{{ $managementAnchor }}-results-title">
        <header class="eval-section-head"><span class="eval-section-icon"><i class="feather-bar-chart-2" aria-hidden="true"></i></span><div><h2 id="{{ $managementAnchor }}-results-title">2 Evaluation results and rankings</h2><p>Compare applicants within each evaluation and phase. Panel completion and the applicable evaluation method determine whether a numeric rank can be reported.</p></div></header>
        <div class="eval-section-body">
            @forelse($management['groups'] ?? [] as $group)
                @php($numericGroup = (bool) ($group['numeric'] ?? false))
                @php($qualificationGroup = (bool) ($group['qualification_ranking'] ?? false))
                <article class="eval-group">
                    <header class="eval-group-head"><div><span class="eval-kicker">{{ $group['phase'] ?? 'Evaluation results' }}</span><h3>{{ $group['title'] }}</h3></div><span class="eval-tag">{{ $numericGroup ? 'Numeric evaluation' : ($qualificationGroup ? 'Qualification order' : 'Categorical outcomes') }}</span></header>
                    <div class="eval-table-scroll" tabindex="0" role="region" aria-label="{{ $group['title'] }} applicant results">
                        <table class="eval-table">
                            <thead><tr>@if($numericGroup || $qualificationGroup)<th scope="col">{{ $qualificationGroup ? 'Qualification position' : 'Rank' }}</th>@endif<th scope="col">Applicant</th>@if($numericGroup)<th scope="col" class="eval-number">Panel score</th><th scope="col" class="eval-number">Raw score / maximum</th>@endif<th scope="col">Panel submissions</th><th scope="col">Outcome / status</th>@if($numericGroup)<th scope="col" class="eval-number">Score spread</th>@endif</tr></thead>
                            <tbody>
                                @forelse($group['rankings'] ?? [] as $row)
                                    <tr>
                                        @if($numericGroup || $qualificationGroup)<td>@if(($row['rank'] ?? null) !== null)<span class="eval-rank">{{ $row['rank'] }}</span>@else<span class="eval-label">Pending / unranked</span>@endif</td>@endif
                                        <td class="eval-person"><strong>{{ $managementText($row['name'] ?? null) }}</strong><small>{{ $managementText($row['code'] ?? null) }}</small></td>
                                        @if($numericGroup)<td class="eval-number eval-nowrap"><strong>{{ $managementNumber($row['score'] ?? null, 2, '%') }}</strong></td><td class="eval-number">{{ $managementNumber($row['raw_score'] ?? null) }} / {{ $managementNumber($row['max_score'] ?? null) }}</td>@endif
                                        <td>{{ $managementNumber($row['completed'] ?? null, 0) }} / {{ $managementNumber($row['expected'] ?? null, 0) }}</td>
                                        <td><span class="eval-status">{{ $managementText($row['status'] ?? null) }}</span></td>
                                        @if($numericGroup)<td class="eval-number">{{ $managementNumber($row['spread'] ?? null, 2) }}</td>@endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $numericGroup ? 7 : ($qualificationGroup ? 4 : 3) }}">No applicant results are available for this evaluation.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($qualificationGroup)<p class="eval-note">Qualification positions follow the authoritative EOI qualification precedence and shortlist rules. These positions represent categorical qualification order; numeric merit scores do not apply.</p>@elseif(!$numericGroup)<p class="eval-note">This evaluation records categorical decisions. Numeric scores and numeric ranks do not apply.</p>@endif
                    @foreach($group['charts'] ?? [] as $chart)
                        <figure class="eval-chart-card"><h4>{{ $chart['title'] }}</h4><div class="eval-chart-scroll" tabindex="0" role="region" aria-label="{{ $chart['title'] }}"><img class="eval-chart" src="{{ $chart['src'] }}" alt="{{ $chart['alt'] }}"></div>@if(!empty($chart['note']))<figcaption>{{ $chart['note'] }}</figcaption>@endif</figure>
                    @endforeach
                </article>
            @empty
                <div class="eval-empty"><strong>No evaluation results recorded</strong>Applicant results will appear here when evaluation records are available.</div>
            @endforelse
        </div>
    </section>

    <section id="{{ $managementAnchor }}-details" class="eval-report-section" aria-labelledby="{{ $managementAnchor }}-details-title">
        <header class="eval-section-head"><span class="eval-section-icon"><i class="feather-list" aria-hidden="true"></i></span><div><h2 id="{{ $managementAnchor }}-details-title">3 Detailed evaluator and section scores</h2><p>Trace the results to each evaluation section and each evaluator’s submitted criteria, scores, decisions, and comments.</p></div></header>
        <div class="eval-section-body">
            @foreach($management['sections'] ?? [] as $section)
                @php($numericSection = is_numeric($section['max_score'] ?? null))
                <article class="eval-group">
                    <header class="eval-group-head"><div><span class="eval-kicker">{{ $section['evaluation'] }}</span><h3>{{ $section['title'] }}</h3></div>@if($numericSection)<span class="eval-tag">Maximum score: {{ $managementNumber($section['max_score'] ?? null) }}</span>@else<span class="eval-tag">Categorical section outcomes</span>@endif</header>
                    <div class="eval-table-scroll" tabindex="0" role="region" aria-label="{{ $section['title'] }} section scores">
                        <table class="eval-table">
                            <thead><tr>@if($numericSection)<th scope="col">Rank</th>@endif<th scope="col">Applicant</th>@if($numericSection)<th scope="col" class="eval-number">Average section score</th><th scope="col" class="eval-number">Percentage</th>@endif<th scope="col">Panel submissions</th><th scope="col">Status</th></tr></thead>
                            <tbody>
                                @forelse($section['rankings'] ?? [] as $row)
                                    <tr>
                                        @if($numericSection)<td>@if(($row['rank'] ?? null) !== null)<span class="eval-rank">{{ $row['rank'] }}</span>@else<span class="eval-label">Pending / unranked</span>@endif</td>@endif
                                        <td class="eval-person"><strong>{{ $managementText($row['name'] ?? null) }}</strong><small>{{ $managementText($row['code'] ?? null) }}</small></td>
                                        @if($numericSection)<td class="eval-number">{{ $managementNumber($row['score'] ?? null) }}</td><td class="eval-number">{{ $managementNumber($row['percentage'] ?? null, 2, '%') }}</td>@endif
                                        <td>{{ $managementNumber($row['count'] ?? null, 0) }} / {{ $managementNumber($row['expected'] ?? null, 0) }}</td>
                                        <td><span class="eval-status">{{ $managementText($row['status'] ?? null) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $numericSection ? 6 : 3 }}">No recorded applicant results for this section.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @foreach($section['charts'] ?? [] as $chart)
                        <figure class="eval-chart-card"><h4>{{ $chart['title'] }}</h4><div class="eval-chart-scroll" tabindex="0" role="region" aria-label="{{ $chart['title'] }}"><img class="eval-chart" src="{{ $chart['src'] }}" alt="{{ $chart['alt'] }}"></div>@if(!empty($chart['note']))<figcaption>{{ $chart['note'] }}</figcaption>@endif</figure>
                    @endforeach
                </article>
            @endforeach

            <h3 class="eval-subtitle">Complete evaluator submission records</h3>
            <p class="eval-note">Each record below preserves the submitted result and the available criterion-level evidence. Missing scores remain marked as not recorded.</p>
            @forelse($management['details'] ?? [] as $detail)
                <article class="eval-record">
                    <header class="eval-record-head">
                        <div><span class="eval-kicker">{{ $detail['code'] }}</span><h4>{{ $detail['applicant'] }}</h4><span class="eval-label">{{ $detail['evaluation'] }} · {{ $detail['phase'] }}</span></div>
                        <div><span class="eval-label">Evaluator</span><strong class="eval-value">{{ $detail['evaluator'] }}</strong><span class="eval-label" style="margin-top:8px">Submitted {{ $managementText($detail['submitted_at'] ?? null) }}</span></div>
                        <div><span class="eval-label">Recorded result</span><strong class="eval-value">{{ $managementText($detail['result'] ?? null) }}</strong></div>
                    </header>
                    <div class="eval-table-scroll" tabindex="0" role="region" aria-label="{{ $detail['applicant'] }} criteria submitted by {{ $detail['evaluator'] }}">
                        <table class="eval-table eval-criteria-table">
                            <thead><tr><th scope="col">Section</th><th scope="col">Criterion</th><th scope="col">Recorded score / decision</th><th scope="col" class="eval-number">Maximum</th><th scope="col">Evaluator comment</th></tr></thead>
                            <tbody>
                                @forelse($detail['criteria'] ?? [] as $criterion)
                                    <tr><td>{{ $managementText($criterion['section'] ?? null) }}</td><td>{{ $managementText($criterion['criterion'] ?? null) }}</td><td>{{ $managementText($criterion['value'] ?? null) }}</td><td class="eval-number">{{ $managementNumber($criterion['max'] ?? null) }}</td><td class="eval-comment">{{ $managementText($criterion['comment'] ?? null) }}</td></tr>
                                @empty
                                    <tr><td colspan="5">No criterion-level details were recorded in this submission.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="eval-record-comments"><span class="eval-label">Overall evaluator comments</span><p class="eval-comment">{{ $managementText($detail['comments'] ?? null) }}</p></div>
                    @foreach($detail['section_feedback'] ?? [] as $feedback)
                        <div class="eval-record-comments">
                            <h4 style="font-size:.85rem;margin:0 0 12px">{{ $feedback['section'] }} · Section feedback</h4>
                            <div class="eval-governance-grid">
                                <div><span class="eval-label">Strengths</span><p class="eval-comment">{{ $managementText($feedback['strengths'] ?? null) }}</p></div>
                                <div><span class="eval-label">Weaknesses</span><p class="eval-comment">{{ $managementText($feedback['weaknesses'] ?? null) }}</p></div>
                            </div>
                        </div>
                    @endforeach
                </article>
            @empty
                <div class="eval-empty" style="margin-top:18px"><strong>No evaluator submissions recorded</strong>Detailed criterion scores, decisions, and comments will appear when evaluators submit their reports.</div>
            @endforelse
        </div>
    </section>

    <section id="{{ $managementAnchor }}-consistency" class="eval-report-section" aria-labelledby="{{ $managementAnchor }}-consistency-title">
        <header class="eval-section-head"><span class="eval-section-icon"><i class="feather-activity" aria-hidden="true"></i></span><div><h2 id="{{ $managementAnchor }}-consistency-title">4 Panel consistency and management insights</h2><p>Identify incomplete panels and differences between recorded evaluator results that may need review before the next decision.</p></div></header>
        <div class="eval-section-body">
            <div class="eval-insight-grid">
                @forelse($management['insights'] ?? [] as $insight)
                    @php($insightTone = in_array($insight['tone'] ?? '', ['success', 'warning', 'danger', 'info'], true) ? $insight['tone'] : 'info')
                    <article class="eval-insight eval-insight--{{ $insightTone }}"><h3>{{ $insight['title'] }}</h3><p>{{ $insight['body'] }}</p></article>
                @empty
                    <div class="eval-empty">No additional management insights are available from the recorded evaluations.</div>
                @endforelse
            </div>
            <h3 class="eval-subtitle">Variation across evaluators</h3>
            <div class="eval-table-scroll" tabindex="0" role="region" aria-label="Panel score consistency">
                <table class="eval-table">
                    <thead><tr><th scope="col">Applicant</th><th scope="col">Evaluation</th><th scope="col" class="eval-number">Evaluators</th><th scope="col" class="eval-number">Minimum</th><th scope="col" class="eval-number">Maximum</th><th scope="col" class="eval-number">Spread</th><th scope="col" class="eval-number">Standard deviation</th></tr></thead>
                    <tbody>
                        @forelse($management['consistency'] ?? [] as $row)
                            <tr><td class="eval-person"><strong>{{ $row['applicant'] }}</strong><small>{{ $row['code'] }}</small></td><td>{{ $row['evaluation'] }}</td><td class="eval-number">{{ $managementNumber($row['evaluators'] ?? null, 0) }}</td><td class="eval-number">{{ $managementNumber($row['minimum'] ?? null) }}</td><td class="eval-number">{{ $managementNumber($row['maximum'] ?? null) }}</td><td class="eval-number">{{ $managementNumber($row['spread'] ?? null) }}</td><td class="eval-number">{{ $managementNumber($row['std_dev'] ?? null) }}</td></tr>
                        @empty
                            <tr><td colspan="7">No comparable numeric panel scores are available. Categorical decisions are recorded in the evaluator details above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="eval-note">Spread shows the gap between the lowest and highest recorded evaluator scores. Standard deviation describes how closely those scores cluster. Differences are prompts for review and do not change an evaluator’s submitted result.</p>
        </div>
    </section>

    <section id="{{ $managementAnchor }}-governance" class="eval-report-section" aria-labelledby="{{ $managementAnchor }}-governance-title">
        <header class="eval-section-head"><span class="eval-section-icon"><i class="feather-shield" aria-hidden="true"></i></span><div><h2 id="{{ $managementAnchor }}-governance-title">5 Governance, audit trail and next steps</h2><p>Use the calculation rules, source records, and recommended follow-up actions to support an accountable procurement decision.</p></div></header>
        <div class="eval-section-body">
            <div class="eval-governance-grid">
                <section class="eval-governance-card"><h3>Methodology and reporting rules</h3><ul>@forelse($management['methodology'] ?? [] as $rule)<li>{{ $rule }}</li>@empty<li>No additional calculation rules were supplied for this report.</li>@endforelse</ul></section>
                <section class="eval-governance-card"><h3>Recommended next steps</h3><ol>@forelse($management['actions'] ?? [] as $action)<li>{{ $action }}</li>@empty<li>Review the available evaluation records and confirm the applicable approval process before proceeding.</li>@endforelse</ol></section>
            </div>
            <h3 class="eval-subtitle">Evaluation submission audit trail</h3>
            <div class="eval-table-scroll" tabindex="0" role="region" aria-label="Evaluation submission audit trail">
                <table class="eval-table">
                    <thead><tr><th scope="col">Applicant</th><th scope="col">Evaluator</th><th scope="col">Evaluation</th><th scope="col">Submitted at</th><th scope="col">Revision</th><th scope="col">Record ID</th></tr></thead>
                    <tbody>
                        @forelse($management['audit'] ?? [] as $entry)
                            <tr><td class="eval-person"><strong>{{ $entry['applicant'] }}</strong><small>{{ $entry['code'] }}</small></td><td>{{ $entry['evaluator'] }}</td><td>{{ $entry['evaluation'] }}</td><td>{{ $managementText($entry['submitted_at'] ?? null) }}</td><td>{{ $managementText($entry['revision'] ?? null) }}</td><td style="overflow-wrap:anywhere;max-width:210px">{{ $managementText($entry['id'] ?? null) }}</td></tr>
                        @empty
                            <tr><td colspan="6">No evaluation submissions are available in the audit trail.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="eval-report-footer">Report generated {{ $managementText($management['generated_at'] ?? null) }}</p>
        </div>
    </section>
</div>
