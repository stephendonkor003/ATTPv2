@php
    $statusLabels = [
        'ready' => 'Report ready',
        'in_progress' => 'Panel in progress',
        'awaiting' => 'Awaiting reports',
        'setup_required' => 'Assignment required',
    ];
@endphp

<article class="pev-method-card pev-method-card--{{ $methodCard['type'] }}">
    <header class="pev-method-card__head">
        <span class="pev-method-card__icon"><i class="{{ $methodCard['icon'] }}" aria-hidden="true"></i></span>
        <span class="pev-status pev-status--{{ $methodCard['status'] }}">{{ $statusLabels[$methodCard['status']] }}</span>
    </header>
    <div class="pev-method-card__body">
        <span class="pev-eyebrow">{{ $methodCard['mode'] }}</span>
        <h3>{{ $methodCard['label'] }}</h3>
        <p>{{ $methodCard['description'] }}</p>

        @if ($methodCard['templates']->isNotEmpty())
            <div class="pev-template-list" aria-label="Evaluation templates">
                @foreach ($methodCard['templates']->take(3) as $template)
                    <span><i class="feather-file-text" aria-hidden="true"></i>{{ $template }}</span>
                @endforeach
                @if ($methodCard['templates']->count() > 3)
                    <span>+{{ $methodCard['templates']->count() - 3 }} more</span>
                @endif
            </div>
        @endif

        <div class="pev-method-stats">
            <div><span>Applications evaluated</span><strong>{{ number_format($methodCard['applicant_count']) }}</strong></div>
            <div><span>Active reports</span><strong>{{ number_format($methodCard['report_count']) }}</strong></div>
            <div><span>Panel members</span><strong>{{ number_format($methodCard['evaluator_count']) }}</strong></div>
        </div>

        <div class="pev-progress">
            <div class="pev-progress__label"><span>Assignment completion</span><strong>{{ $methodCard['completion_percent'] }}%</strong></div>
            <span class="pev-progress__track"><span style="width: {{ max(0, min(100, $methodCard['completion_percent'])) }}%"></span></span>
        </div>
    </div>
    <footer class="pev-method-card__foot">
        <small>{{ $methodCard['latest_at'] ? 'Last activity '.$methodCard['latest_at']->format('d M Y') : 'No submitted reports yet' }}</small>
        @if ($methodCard['method_url'])
            <a href="{{ $methodCard['method_url'] }}" class="pev-btn pev-btn--primary">
                Open {{ $methodCard['label'] }} <i class="feather-arrow-right" aria-hidden="true"></i>
            </a>
        @else
            <span class="pev-btn pev-btn--disabled" aria-disabled="true">Report access required</span>
        @endif
    </footer>
</article>
