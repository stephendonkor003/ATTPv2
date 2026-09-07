@php
    $mgOverview = data_get($management, 'overview', []);
    $mgDisplay = static function ($value, string $fallback = 'Not recorded'): string {
        if ($value === null || $value === '') return $fallback;
        if ($value instanceof \DateTimeInterface) return $value->format('d M Y, H:i T');
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        return (string) $value;
    };
    $mgNumber = static fn ($value, int $precision = 2, string $suffix = '') => is_numeric($value) ? number_format((float) $value, $precision).$suffix : 'Not recorded';
    $mgHasSubmitted = (int) data_get($mgOverview, 'submitted_reports', 0) > 0;
    $mgSections = array_map('intval', $managementSections ?? [1, 2, 3, 4, 5]);
    $mgSection3Mode = $managementSection3Mode ?? 'all';
@endphp
<div class="management-report">
    @if (in_array(1, $mgSections, true))
    <section class="mg-section">
        <div class="mg-section-heading">
            <div class="mg-section-number">Section 1 of 5</div>
            <h2>Summary overview</h2>
            <p>Applicant coverage, panel participation and the recorded evaluation timeline.</p>
        </div>
        <table class="mg-metrics"><tbody><tr>
            @foreach (['total_applicants' => 'Total applicants', 'evaluated_applicants' => 'Applicants evaluated', 'evaluator_count' => 'Evaluators', 'submitted_reports' => 'Submitted reports', 'template_count' => 'Evaluation templates'] as $key => $label)
                <td><span class="mg-metric-label">{{ $label }}</span><strong class="mg-metric-value">{{ $mgNumber(data_get($mgOverview, $key), 0) }}</strong></td>
            @endforeach
        </tr></tbody></table>
        @unless ($mgHasSubmitted)
            <div class="mg-note mg-pending"><strong>Evaluation results are pending.</strong> No submitted evaluator reports are recorded for this report scope. Draft work is excluded from results, scores and rankings. Applicant and assignment coverage remains visible below.</div>
        @endunless
        <table><thead><tr><th>First recorded assignment</th><th>Latest finalized submission</th><th>Elapsed evaluation window</th><th>Active evaluation time</th></tr></thead><tbody><tr>
            <td>{{ $mgDisplay(data_get($mgOverview, 'start')) }}</td><td>{{ $mgDisplay(data_get($mgOverview, 'end')) }}</td><td>{{ $mgDisplay(data_get($mgOverview, 'elapsed')) }}</td><td>{{ $mgDisplay(data_get($mgOverview, 'active_time')) }}</td>
        </tr></tbody></table>
        @if (filled(data_get($mgOverview, 'timing_note')))<div class="mg-note">{{ data_get($mgOverview, 'timing_note') }}</div>@endif
        <h3 class="mg-heading">Evaluator participation and submission timing</h3>
        <table><thead><tr><th style="width:24%;">Evaluator</th><th style="width:7%;">Assigned</th><th style="width:11%;">Submitted reports</th><th>First submission</th><th>Last submission</th><th>Elapsed period</th><th>Active time</th></tr></thead><tbody>
            @forelse (data_get($management, 'evaluators', []) as $evaluator)
                <tr><td><strong>{{ $mgDisplay(data_get($evaluator, 'name')) }}</strong><span class="mg-subline">{{ $mgDisplay(data_get($evaluator, 'email')) }}</span></td><td class="mg-center">{{ $mgNumber(data_get($evaluator, 'assigned'), 0) }}</td><td class="mg-center">{{ $mgNumber(data_get($evaluator, 'completed'), 0) }}</td><td>{{ $mgDisplay(data_get($evaluator, 'first_submission')) }}</td><td>{{ $mgDisplay(data_get($evaluator, 'last_submission')) }}</td><td>{{ $mgDisplay(data_get($evaluator, 'elapsed')) }}</td><td>{{ $mgDisplay(data_get($evaluator, 'active_time')) }}</td></tr>
            @empty
                <tr><td colspan="7" class="mg-small">No evaluator assignments or submissions are recorded for this report scope.</td></tr>
            @endforelse
        </tbody></table>
        @foreach (collect(data_get($management, 'overview_charts', []))->chunk(2) as $chartPair)
            <table class="mg-chart-grid"><tbody><tr>
                @foreach ($chartPair as $chart)
                    <td><h4 class="mg-chart-title">{{ $mgDisplay(data_get($chart, 'title'), 'Coverage chart') }}</h4>
                        @if (str_starts_with((string) data_get($chart, 'src'), 'data:image/'))<img class="mg-chart-image" src="{{ data_get($chart, 'src') }}" alt="{{ data_get($chart, 'alt', data_get($chart, 'title', 'Coverage chart')) }}">@else<div class="mg-empty">Chart image is not available.</div>@endif
                        @if (filled(data_get($chart, 'note')))<p class="mg-chart-note">{{ data_get($chart, 'note') }}</p>@endif
                    </td>
                @endforeach
                @if ($chartPair->count() === 1)<td style="border:0;"></td>@endif
            </tr></tbody></table>
        @endforeach
    </section>

    @endif
    @if (in_array(2, $mgSections, true))
    <section class="mg-section">
        <div class="mg-section-heading">
            <div class="mg-section-number">Section 2 of 5</div>
            <h2>Evaluation results and rankings</h2>
            <p>Results remain separated by evaluation and phase. Coverage accompanies each applicant result.</p>
        </div>
        @forelse (data_get($management, 'groups', []) as $group)
            @php
                $mgNumeric = (bool) data_get($group, 'numeric', false);
                $mgQualification = (bool) data_get($group, 'qualification_ranking', false);
            @endphp
            <h3 class="mg-heading">{{ $mgDisplay(data_get($group, 'title'), 'Evaluation') }} &middot; {{ $mgDisplay(data_get($group, 'phase'), 'Phase not recorded') }}</h3>
            <div class="mg-note">{{ $mgQualification ? 'Qualification positions and shortlist status come from the authoritative EOI qualification resolver. These are categorical qualification positions; numeric merit scores do not apply.' : ($mgNumeric ? 'Numeric ranks use submitted results within this evaluation and phase. Review panel coverage before interpreting a position as final.' : 'This evaluation records categorical decisions. Numeric scores and ranks are not applicable; the recorded outcome is shown without an invented ranking.') }}</div>
            <table><thead><tr><th style="width:6%;">Rank</th><th style="width:25%;">Applicant</th><th style="width:10%;">Average score</th><th style="width:13%;">Raw score / maximum</th><th style="width:12%;">Panel coverage</th><th style="width:10%;">Spread (pp)</th><th>Result / status</th></tr></thead><tbody>
                @forelse (data_get($group, 'rankings', []) as $row)
                    <tr><td class="mg-center">{{ $mgNumeric || $mgQualification ? $mgDisplay(data_get($row, 'rank')) : 'Not applicable' }}</td><td><strong>{{ $mgDisplay(data_get($row, 'name')) }}</strong><span class="mg-subline">{{ $mgDisplay(data_get($row, 'code')) }}</span></td><td class="mg-numeric mg-score">{{ $mgNumeric ? $mgNumber(data_get($row, 'score'), 2, '%') : 'Not applicable' }}</td><td class="mg-numeric">{{ $mgNumeric ? $mgNumber(data_get($row, 'raw_score')).' / '.$mgNumber(data_get($row, 'max_score')) : 'Not applicable' }}</td><td class="mg-center">{{ $mgNumber(data_get($row, 'completed'), 0) }} / {{ $mgNumber(data_get($row, 'expected'), 0) }}</td><td class="mg-numeric">{{ $mgNumeric ? $mgNumber(data_get($row, 'spread')) : 'Not applicable' }}</td><td class="mg-status">{{ $mgDisplay(data_get($row, 'status')) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="mg-small">No applicant results are recorded for this evaluation.</td></tr>
                @endforelse
            </tbody></table>
            @foreach (collect(data_get($group, 'charts', []))->chunk(1) as $chartPair)
                <table class="mg-chart-grid"><tbody><tr>
                    @foreach ($chartPair as $chart)
                        <td><h4 class="mg-chart-title">{{ $mgDisplay(data_get($chart, 'title'), 'Evaluation results chart') }}</h4>
                            @if (str_starts_with((string) data_get($chart, 'src'), 'data:image/'))<img class="mg-chart-image" src="{{ data_get($chart, 'src') }}" alt="{{ data_get($chart, 'alt', data_get($chart, 'title', 'Evaluation results chart')) }}">@else<div class="mg-empty">No chart image is available.</div>@endif
                            @if (filled(data_get($chart, 'note')))<p class="mg-chart-note">{{ data_get($chart, 'note') }}</p>@endif
                        </td>
                    @endforeach
                </tr></tbody></table>
            @endforeach
        @empty
            <div class="mg-empty">Evaluation results and rankings are not recorded. They become available when evaluator reports are submitted.</div>
        @endforelse
    </section>

    @endif
    @if (in_array(3, $mgSections, true))
    <section class="mg-section">
        <div class="mg-section-heading">
            <div class="mg-section-number">Section 3 of 5</div>
            <h2>Detailed evaluator and section scores</h2>
            <p>Section comparisons, individual submitted assessments and the complete recorded comments.</p>
            @if ($managementDetailContinuation ?? false)<p>Continued &mdash; additional evaluator records from the same report scope.</p>@endif
        </div>
        @if (in_array($mgSection3Mode, ['all', 'comparisons'], true))
        <h3 class="mg-heading">Section results</h3>
        @forelse (data_get($management, 'sections', []) as $section)
            @php $mgNumericSection = is_numeric(data_get($section, 'max_score')); @endphp
            <h3 class="mg-heading">{{ $mgDisplay(data_get($section, 'title'), 'Section') }} &middot; {{ $mgDisplay(data_get($section, 'evaluation'), 'Evaluation') }}</h3>
            <p class="mg-small">{{ $mgNumericSection ? 'Section maximum: '.$mgNumber(data_get($section, 'max_score')).'. Missing scores are shown as not recorded.' : 'Categorical section decisions. Numeric scores and ranks do not apply.' }}</p>
            <table><thead><tr>@if ($mgNumericSection)<th style="width:7%;">Rank</th>@endif<th style="width:29%;">Applicant</th>@if ($mgNumericSection)<th>Average raw score</th><th>Percentage</th>@endif<th>Submitted / expected</th><th>Status / decision</th></tr></thead><tbody>
                @forelse (data_get($section, 'rankings', []) as $row)
                    <tr>@if ($mgNumericSection)<td class="mg-center">{{ $mgDisplay(data_get($row, 'rank')) }}</td>@endif<td><strong>{{ $mgDisplay(data_get($row, 'name')) }}</strong><span class="mg-subline">{{ $mgDisplay(data_get($row, 'code')) }}</span></td>@if ($mgNumericSection)<td class="mg-numeric">{{ $mgNumber(data_get($row, 'score')) }}</td><td class="mg-numeric mg-score">{{ $mgNumber(data_get($row, 'percentage'), 2, '%') }}</td>@endif<td class="mg-center">{{ $mgNumber(data_get($row, 'count'), 0) }} / {{ $mgNumber(data_get($row, 'expected'), 0) }}</td><td class="mg-status">{{ $mgDisplay(data_get($row, 'status')) }}</td></tr>
                @empty
                    <tr><td colspan="{{ $mgNumericSection ? 6 : 3 }}" class="mg-small">No submitted section assessments are recorded.</td></tr>
                @endforelse
            </tbody></table>
            @foreach (collect(data_get($section, 'charts', []))->chunk(1) as $chartPair)
                <table class="mg-chart-grid"><tbody><tr>
                    @foreach ($chartPair as $chart)
                        <td><h4 class="mg-chart-title">{{ $mgDisplay(data_get($chart, 'title'), 'Section scores chart') }}</h4>
                            @if (str_starts_with((string) data_get($chart, 'src'), 'data:image/'))<img class="mg-chart-image" src="{{ data_get($chart, 'src') }}" alt="{{ data_get($chart, 'alt', data_get($chart, 'title', 'Section scores chart')) }}">@else<div class="mg-empty">No chart image is available.</div>@endif
                            @if (filled(data_get($chart, 'note')))<p class="mg-chart-note">{{ data_get($chart, 'note') }}</p>@endif
                        </td>
                    @endforeach
                </tr></tbody></table>
            @endforeach
        @empty
            <div class="mg-empty">No submitted numeric section scores are recorded for this report scope.</div>
        @endforelse
        @endif
        @if (in_array($mgSection3Mode, ['all', 'details'], true))
        <h3 class="mg-heading">Individual evaluator submissions</h3>
        @forelse (data_get($management, 'details', []) as $detail)
            <div class="mg-detail-heading"><h4>{{ $mgDisplay(data_get($detail, 'applicant')) }} &middot; {{ $mgDisplay(data_get($detail, 'code')) }}</h4><p>{{ $mgDisplay(data_get($detail, 'evaluation')) }} &middot; {{ $mgDisplay(data_get($detail, 'phase')) }}</p></div>
            <table><thead><tr><th>Evaluator</th><th>Recorded result</th><th>Submitted at</th></tr></thead><tbody><tr><td>{{ $mgDisplay(data_get($detail, 'evaluator')) }}</td><td class="mg-score">{{ $mgDisplay(data_get($detail, 'result')) }}</td><td>{{ $mgDisplay(data_get($detail, 'submitted_at')) }}</td></tr></tbody></table>
            <p class="mg-comment-label">Evaluator comments</p><div class="mg-long-text">{{ $mgDisplay(data_get($detail, 'comments'), 'No overall comment recorded.') }}</div>
            @foreach (data_get($detail, 'section_feedback', []) as $feedback)
                <h4 class="mg-heading">{{ $mgDisplay(data_get($feedback, 'section'), 'Section') }} &mdash; Evaluator feedback</h4>
                <p class="mg-comment-label">Strengths</p><div class="mg-long-text">{{ $mgDisplay(data_get($feedback, 'strengths'), 'No strengths recorded.') }}</div>
                <p class="mg-comment-label">Weaknesses</p><div class="mg-long-text">{{ $mgDisplay(data_get($feedback, 'weaknesses'), 'No weaknesses recorded.') }}</div>
            @endforeach
            <table><thead><tr><th style="width:23%;">Section</th><th style="width:49%;">Criterion</th><th style="width:16%;">Recorded score / decision</th><th style="width:12%;">Maximum</th></tr></thead><tbody>
                @forelse (data_get($detail, 'criteria', []) as $criterion)
                    <tr><td>{{ $mgDisplay(data_get($criterion, 'section')) }}</td><td>{{ $mgDisplay(data_get($criterion, 'criterion')) }}</td><td>{{ $mgDisplay(data_get($criterion, 'value')) }}</td><td>{{ $mgDisplay(data_get($criterion, 'max')) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="mg-small">No criterion-level records were submitted.</td></tr>
                @endforelse
            </tbody></table>
            @foreach (data_get($detail, 'criteria', []) as $criterion)
                @if (filled(data_get($criterion, 'comment')))
                    <p class="mg-comment-label">{{ $mgDisplay(data_get($criterion, 'section')) }} / {{ $mgDisplay(data_get($criterion, 'criterion')) }} &mdash; Comment</p><div class="mg-long-text">{{ data_get($criterion, 'comment') }}</div>
                @endif
            @endforeach
        @empty
            <div class="mg-empty">No submitted evaluator assessments are available. Draft assessments do not appear as completed evaluations.</div>
        @endforelse
        @endif
    </section>

    @endif
    @if (in_array(4, $mgSections, true))
    <section class="mg-section">
        <div class="mg-section-heading">
            <div class="mg-section-number">Section 4 of 5</div>
            <h2>Panel consistency and management insights</h2>
            <p>Evidence from submitted assessments to support panel review and follow-up.</p>
        </div>
        <h3 class="mg-heading">Management observations</h3>
        @forelse (data_get($management, 'insights', []) as $insight)
            @php $mgTone = in_array(data_get($insight, 'tone'), ['warning', 'success'], true) ? data_get($insight, 'tone') : 'neutral'; @endphp
            <div class="mg-insight mg-insight-{{ $mgTone }}"><h4>{{ $mgDisplay(data_get($insight, 'title'), 'Management observation') }}</h4><div class="mg-long-text">{{ $mgDisplay(data_get($insight, 'body')) }}</div></div>
        @empty
            <div class="mg-empty">No management insights can be derived from submitted results in this report scope.</div>
        @endforelse
        <h3 class="mg-heading">Evaluator score dispersion</h3>
        <div class="mg-note">Numeric dispersion compares recorded evaluator percentages within the same evaluation. Spread is the maximum minus minimum score, measured in percentage points (pp). A difference in scoring alone does not establish evaluator bias.</div>
        <table><thead><tr><th style="width:25%;">Applicant</th><th style="width:22%;">Evaluation</th><th>Evaluators</th><th>Minimum %</th><th>Maximum %</th><th>Spread (pp)</th><th>Std. dev. (pp)</th></tr></thead><tbody>
            @forelse (data_get($management, 'consistency', []) as $row)
                <tr><td><strong>{{ $mgDisplay(data_get($row, 'applicant')) }}</strong><span class="mg-subline">{{ $mgDisplay(data_get($row, 'code')) }}</span></td><td>{{ $mgDisplay(data_get($row, 'evaluation')) }}</td><td class="mg-center">{{ $mgNumber(data_get($row, 'evaluators'), 0) }}</td><td class="mg-numeric">{{ $mgNumber(data_get($row, 'minimum')) }}</td><td class="mg-numeric">{{ $mgNumber(data_get($row, 'maximum')) }}</td><td class="mg-numeric">{{ $mgNumber(data_get($row, 'spread')) }}</td><td class="mg-numeric">{{ $mgNumber(data_get($row, 'std_dev')) }}</td></tr>
            @empty
                <tr><td colspan="7" class="mg-small">Comparable submitted numeric scores are not available for a panel consistency assessment.</td></tr>
            @endforelse
        </tbody></table>
    </section>

    @endif
    @if (in_array(5, $mgSections, true))
    <section class="mg-section">
        <div class="mg-section-heading">
            <div class="mg-section-number">Section 5 of 5</div>
            <h2>Governance, audit trail and next steps</h2>
            <p>Calculation rules, source submission history and recorded management actions.</p>
        </div>
        <h3 class="mg-heading">Methodology and reporting rules</h3>
        <ol class="mg-list">
            @forelse (data_get($management, 'methodology', []) as $rule)<li>{{ $mgDisplay($rule) }}</li>@empty<li>Detailed methodology has not been recorded for this report.</li>@endforelse
        </ol>
        <h3 class="mg-heading">Next steps</h3>
        <ol class="mg-list">
            @forelse (data_get($management, 'actions', []) as $action)<li>{{ $mgDisplay($action) }}</li>@empty<li>No management actions are recorded.</li>@endforelse
        </ol>
        <h3 class="mg-heading">Submission audit trail</h3>
        <table class="mg-audit"><thead><tr><th style="width:23%;">Applicant</th><th style="width:18%;">Evaluator</th><th style="width:20%;">Evaluation</th><th style="width:16%;">Submitted at</th><th style="width:7%;">Revision</th><th style="width:16%;">Record ID</th></tr></thead><tbody>
            @forelse (data_get($management, 'audit', []) as $row)
                <tr><td>{{ $mgDisplay(data_get($row, 'applicant')) }}<span class="mg-subline">{{ $mgDisplay(data_get($row, 'code')) }}</span></td><td>{{ $mgDisplay(data_get($row, 'evaluator')) }}</td><td>{{ $mgDisplay(data_get($row, 'evaluation')) }}</td><td>{{ $mgDisplay(data_get($row, 'submitted_at')) }}</td><td class="mg-center">{{ $mgDisplay(data_get($row, 'revision')) }}</td><td class="mg-audit-id">{{ $mgDisplay(data_get($row, 'id')) }}</td></tr>
            @empty
                <tr><td colspan="6" class="mg-small">No submitted evaluator records are available in this report scope.</td></tr>
            @endforelse
        </tbody></table>
        <p class="mg-small">Report generated: {{ $mgDisplay(data_get($management, 'generated_at')) }}. This report reflects the recorded data at generation time.</p>
    </section>
    @endif
</div>
