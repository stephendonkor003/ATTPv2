@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/evaluation-chart-details.css') }}?v={{ filemtime(public_path('assets/css/evaluation-chart-details.css')) }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('assets/js/evaluation-chart-details.js') }}?v={{ filemtime(public_path('assets/js/evaluation-chart-details.js')) }}" defer></script>
    @endpush
@endonce

@php
    $chartInteraction = $chart['interaction'] ?? [];
    $chartRecords = $chartInteraction['records'] ?? [];
    $chartIsPie = ($chartInteraction['kind'] ?? '') === 'pie';
    $chartColor = static fn ($color) => is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#176b87';
@endphp

<figure class="eval-chart-card eval-interactive-chart" data-eval-chart data-eval-kind="{{ $chartInteraction['kind'] ?? '' }}" data-eval-records="{{ json_encode($chartRecords, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}">
    <h4>{{ $chart['title'] }}</h4>
    @if($chartRecords)<p class="eval-chart-help" hidden>Hover or tap for details. With the chart focused, use arrow keys to compare values and Escape to close.</p>@endif
    <div class="eval-chart-scroll" tabindex="0" role="region" aria-label="{{ $chart['title'] }}">
        <div class="eval-chart-stage">
            <img class="eval-chart" src="{{ $chart['src'] }}" alt="{{ $chart['alt'] }}">
            @if($chartRecords && !empty($chartInteraction['targets']))
                <svg class="eval-chart-overlay" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {{ (int) $chartInteraction['width'] }} {{ (int) $chartInteraction['height'] }}" role="group" aria-label="{{ $chart['title'] }} data points">
                    @foreach($chartInteraction['targets'] as $chartTarget)
                        @php($chartRecord = $chartRecords[$chartTarget['record']] ?? null)
                        @if($chartRecord)
                            <g class="eval-chart-target" role="button" tabindex="0" data-eval-record="{{ (int) $chartTarget['record'] }}" aria-label="{{ $chartRecord['label'] }}" style="--eval-point-color:{{ $chartColor($chartRecord['color'] ?? null) }}">
                                <title>{{ $chartRecord['label'] }}</title>
                                @if($chartTarget['shape'] === 'rect')
                                    <rect x="{{ (float) $chartTarget['x'] }}" y="{{ (float) $chartTarget['y'] }}" width="{{ (float) $chartTarget['width'] }}" height="{{ (float) $chartTarget['height'] }}" rx="2"/>
                                @elseif($chartTarget['shape'] === 'circle')
                                    <circle cx="{{ (float) $chartTarget['cx'] }}" cy="{{ (float) $chartTarget['cy'] }}" r="{{ (float) $chartTarget['r'] }}"/>
                                @elseif($chartTarget['shape'] === 'polygon')
                                    <polygon points="{{ $chartTarget['points'] }}"/>
                                @endif
                            </g>
                        @endif
                    @endforeach
                </svg>
            @endif
        </div>
    </div>
    @if(!empty($chart['note']))<figcaption>{{ $chart['note'] }}</figcaption>@endif
    @if($chartRecords)
        <details class="eval-chart-data">
            <summary>View chart data <span>{{ count($chartRecords) }} {{ $chartIsPie ? 'categories' : 'observations' }}</span></summary>
            <div class="eval-table-scroll" tabindex="0" role="region" aria-label="{{ $chart['title'] }} values">
                <table class="eval-table">
                    <thead><tr>@if($chartIsPie)<th scope="col">Category</th><th scope="col">Recorded count</th><th scope="col">Share of recorded total</th>@else<th scope="col">Applicant</th><th scope="col">Evaluator</th><th scope="col">{{ $chartRecords[0]['metric'] ?? 'Score' }}</th>@endif</tr></thead>
                    <tbody>
                        @foreach($chartRecords as $chartRecord)
                            <tr>
                                @if($chartIsPie)
                                    <th scope="row"><span class="eval-chart-swatch" style="background:{{ $chartColor($chartRecord['color']) }}" aria-hidden="true"></span>{{ $chartRecord['name'] }}</th>
                                    <td>{{ $chartRecord['display_value'] }}</td><td>{{ $chartRecord['display_percentage'] }}</td>
                                @else
                                    <th scope="row">{{ $chartRecord['applicant'] }}</th>
                                    <td><span class="eval-chart-swatch" style="background:{{ $chartColor($chartRecord['color']) }}" aria-hidden="true"></span>{{ $chartRecord['evaluator'] }}</td><td>{{ $chartRecord['display_value'] }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</figure>
