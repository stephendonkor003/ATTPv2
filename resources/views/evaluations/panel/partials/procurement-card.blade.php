@php
    $procurement = $card['procurement'];
    $title = $procurement->title ?: 'Untitled procurement';
@endphp

<article
    class="pev-proc-card pev-proc-card--{{ $card['status'] }}"
    data-panel-procurement
    data-search="{{ $card['search'] }}"
    data-methods="{{ $card['method_types']->implode(' ') }}"
    data-status="{{ $card['status'] }}"
    data-title="{{ Str::lower($title) }}"
    data-progress="{{ $card['completion_percent'] }}"
    data-latest="{{ $card['latest_at']?->getTimestamp() ?? 0 }}"
>
    <div class="pev-proc-card__top">
        <span class="pev-reference"><i class="feather-hash" aria-hidden="true"></i>{{ $procurement->reference_no ?: 'No reference' }}</span>
        <span class="pev-status pev-status--{{ $card['status'] }}">{{ $statusLabel }}</span>
    </div>

    <div class="pev-proc-card__identity">
        <h3>{{ $title }}</h3>
        <div class="pev-card-meta">
            <span><i class="feather-activity" aria-hidden="true"></i>{{ Str::headline((string) ($procurement->status ?: 'Status not set')) }}</span>
            @if ($procurement->thinkTankPlanningItem?->procurement_category)
                <span><i class="feather-tag" aria-hidden="true"></i>{{ Str::headline($procurement->thinkTankPlanningItem->procurement_category) }}</span>
            @endif
        </div>
    </div>

    <div class="pev-method-pills" aria-label="Configured evaluation types">
        @forelse ($card['methods'] as $method)
            <span class="pev-method-pill pev-method-pill--{{ $method['type'] }}"><i class="{{ $method['icon'] }}" aria-hidden="true"></i>{{ $method['label'] }}</span>
        @empty
            <span class="pev-method-pill"><i class="feather-settings" aria-hidden="true"></i>Evaluation setup required</span>
        @endforelse
    </div>

    <div class="pev-card-metrics" aria-label="{{ $title }} panel summary">
        <div><span>Applications</span><strong>{{ number_format($card['application_count']) }}</strong></div>
        <div><span>Evaluated</span><strong>{{ number_format($card['evaluated_applicant_count']) }}</strong></div>
        <div><span>Reports</span><strong>{{ number_format($card['report_count']) }}</strong></div>
        <div><span>Panel</span><strong>{{ number_format($card['evaluator_count']) }}</strong></div>
    </div>

    <div class="pev-progress" aria-label="{{ $card['completion_percent'] }} percent assignment completion">
        <div class="pev-progress__label"><span>Active assignment completion</span><strong>{{ $card['completion_percent'] }}%</strong></div>
        <span class="pev-progress__track"><span style="width: {{ max(0, min(100, $card['completion_percent'])) }}%"></span></span>
    </div>

    <div class="pev-proc-card__foot">
        <small><i class="feather-clock" aria-hidden="true"></i>{{ $card['latest_at'] ? 'Updated '.$card['latest_at']->format('d M Y, H:i') : 'No panel activity yet' }}</small>
        @if ($card['show_url'] !== '#')
            <a href="{{ $card['show_url'] }}" class="pev-btn pev-btn--primary">
                View journey <i class="feather-arrow-right" aria-hidden="true"></i>
            </a>
        @else
            <span class="pev-btn pev-btn--disabled" aria-disabled="true">Journey unavailable</span>
        @endif
    </div>
</article>
