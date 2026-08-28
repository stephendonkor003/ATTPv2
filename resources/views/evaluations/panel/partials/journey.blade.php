<ol class="pev-journey" aria-label="Procurement evaluation journey">
    @foreach ($steps as $step)
        <li class="pev-step pev-step--{{ $step['state'] }}" @if ($step['state'] === 'current') aria-current="step" @endif>
            <div class="pev-step__rail" aria-hidden="true">
                <span class="pev-step__orb">
                    <i class="{{ $step['state'] === 'complete' ? 'feather-check' : $step['icon'] }}"></i>
                </span>
                @unless ($loop->last)<span class="pev-step__line"></span>@endunless
            </div>
            <div class="pev-step__content">
                <div class="pev-step__heading">
                    <div>
                        <span class="pev-step__index">Step {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <h3>{{ $step['label'] }}</h3>
                    </div>
                    <span class="pev-step__state">{{ match ($step['state']) { 'complete' => 'Complete', 'current' => 'Happening now', default => 'Upcoming' } }}</span>
                </div>
                <p>{{ $step['detail'] }}</p>
                <small><i class="feather-info" aria-hidden="true"></i>{{ $step['meta'] }}</small>
            </div>
        </li>
    @endforeach
</ol>
