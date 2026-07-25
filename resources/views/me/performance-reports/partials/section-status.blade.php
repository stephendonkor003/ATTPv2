@php
    $stateClasses = [
        'complete' => 'section-state--complete',
        'in_progress' => 'section-state--in-progress',
        'not_started' => 'section-state--not-started',
    ];
    $stateIcons = [
        'complete' => 'feather-check-circle',
        'in_progress' => 'feather-alert-triangle',
        'not_started' => 'feather-x-circle',
    ];
    $missingText = $section['missing'] === []
        ? 'Every required field in this section is complete.'
        : 'Missing: '.implode(', ', $section['missing']);
@endphp

<span
    class="section-state {{ $stateClasses[$section['status']] ?? 'section-state--not-started' }}"
    title="{{ $missingText }}"
    data-section-status="{{ $section['status'] }}"
>
    <i class="{{ $stateIcons[$section['status']] ?? 'feather-x-circle' }}" aria-hidden="true"></i>
    {{ $section['status_label'] }}
    <small>{{ $section['completed'] }}/{{ $section['total'] }}</small>
</span>
