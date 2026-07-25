@php
    $completedSections = collect($sectionCompletion)
        ->where('status', 'complete')
        ->count();
    $completionPercent = (int) round(($completedSections / max(count($sectionCompletion), 1)) * 100);
@endphp

<section class="report-completion" aria-labelledby="report-completion-title">
    <div class="completion-heading">
        <div>
            <span class="completion-eyebrow">Mandatory section check</span>
            <h5 id="report-completion-title">
                {{ $submissionReady ? 'Report ready for submission' : $completedSections.' of '.count($sectionCompletion).' sections complete' }}
            </h5>
            <p>
                @if ($submissionReady)
                    Every mandatory section and supporting evidence requirement has been completed.
                @else
                    Complete all red and amber sections, then save the draft before submitting it for review.
                @endif
            </p>
        </div>
        <div class="completion-score {{ $submissionReady ? 'is-ready' : '' }}">
            <strong>{{ $completionPercent }}%</strong>
            <span>complete</span>
        </div>
    </div>
    <div class="completion-progress" role="progressbar" aria-valuenow="{{ $completionPercent }}" aria-valuemin="0" aria-valuemax="100">
        <span style="width: {{ $completionPercent }}%"></span>
    </div>
    <div class="completion-grid">
        @foreach ($sectionCompletion as $section)
            <a href="#report-section-{{ $section['number'] }}" class="completion-item completion-item--{{ str_replace('_', '-', $section['status']) }}">
                <span>{{ $section['number'] }}</span>
                <div>
                    <strong>{{ $section['title'] }}</strong>
                    <small>
                        {{ $section['status_label'] }}
                        @if ($section['missing'] !== [])
                            · {{ implode(', ', $section['missing']) }}
                        @endif
                    </small>
                </div>
            </a>
        @endforeach
    </div>
</section>
