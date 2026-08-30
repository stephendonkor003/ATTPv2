@php
    $totalCriteria = $evaluation->sections->sum(fn ($section) => $section->criteria->count());
    $sectionOutline = \App\Support\EvaluationSectionHierarchy::flattened($evaluation);
    $tierCount = $sectionOutline->isEmpty() ? 0 : $sectionOutline->max('depth') + 1;
@endphp

<div class="mb-3">
    <div class="d-flex flex-wrap gap-2">
        <span class="badge bg-primary-subtle text-primary">Type: {{ $evaluation->typeLabel() }}</span>
        <span class="badge bg-info-subtle text-info">Sections: {{ $evaluation->sections->count() }}</span>
        <span class="badge bg-success-subtle text-success">Tiers used: {{ $tierCount }}</span>
        <span class="badge bg-secondary-subtle text-secondary">Criteria: {{ $totalCriteria }}</span>
        <span class="badge bg-dark-subtle text-dark">Status: {{ ucfirst($evaluation->status) }}</span>
    </div>
    @if ($evaluation->description)
        <p class="text-muted mt-2 mb-0">{{ $evaluation->description }}</p>
    @endif
    @if ($evaluation->usesCategoricalDecisions())
        <div class="small text-muted mt-2">
            <strong>Evaluator choices:</strong>
            {{ implode(' / ', $evaluation->decisionOptions()) }}
        </div>
    @endif
    <div class="alert alert-info py-2 px-3 small mt-2 mb-0">
        <strong>Required per question:</strong>
        {{ $evaluation->usesNumericScoring()
            ? 'a numeric score within the configured maximum and an evidence response.'
            : 'one permitted decision and an evidence comment.' }}
    </div>
</div>

@forelse ($sectionOutline as $node)
    @php($section = $node['section'])
    <div class="card border-0 shadow-sm mb-3" style="margin-left: {{ min($node['depth'] * 18, 54) }}px">
        <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
            <span>
                <small class="text-uppercase text-muted me-2">{{ $node['label'] }}</small>
                {{ $node['number'] }}. {{ $section->name }}
            </span>
            <span class="d-flex gap-1">
                <span class="badge bg-dark">{{ $section->criteria->count() }} direct criteria</span>
                @if ($section->show_subtotal)
                    @if ($evaluation->usesNumericScoring())
                        <span class="badge bg-primary">Sub-total max {{ number_format($section->subtotalMaxScore(), 2) }}</span>
                    @else
                        <span class="badge bg-info text-dark">Category distribution sub-total</span>
                    @endif
                @endif
            </span>
        </div>
        <div class="card-body">
            @if ($section->description)
                <p class="text-muted mb-3">{{ $section->description }}</p>
            @endif

            @if ($section->criteria->isEmpty())
                <div class="text-muted">Grouping section; criteria are organised in its child sections.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40">#</th>
                                <th>Criteria</th>
                                <th>Description</th>
                                @if ($evaluation->usesNumericScoring())
                                    <th width="210">Maximum / required response</th>
                                @else
                                    <th width="240">Response</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section->criteria as $criterionIndex => $criterion)
                                <tr>
                                    <td>{{ $criterionIndex + 1 }}</td>
                                    <td class="fw-semibold">{{ $criterion->name }}</td>
                                    <td>{{ $criterion->description ?: '—' }}</td>
                                    @if ($evaluation->usesNumericScoring())
                                        <td>
                                            <strong>{{ number_format((float) $criterion->max_score, 2) }} points</strong><br>
                                            <small class="text-muted">Score + evidence response</small>
                                        </td>
                                    @else
                                        <td>{{ implode(' / ', $evaluation->decisionOptions()) }} + evidence comment</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="alert alert-warning mb-0">
        This evaluation has no sections yet. Configure sections and criteria first.
    </div>
@endforelse
