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
                @can('evaluations.manage')
                    @if (! empty($step['actions']))
                        <div class="pev-step__admin-tools" aria-label="Step {{ $loop->iteration }} administrator tools">
                            <span class="pev-step__admin-label"><i class="feather-lock" aria-hidden="true"></i> Admin tools</span>
                            <div class="pev-step__actions">
                                @foreach ($step['actions'] as $action)
                                    @if ($action['disabled'] ?? false)
                                        <span class="pev-btn pev-btn--disabled" aria-disabled="true" title="Complete the preceding stage first">
                                            <i class="{{ $action['icon'] }}" aria-hidden="true"></i>{{ $action['label'] }}
                                        </span>
                                    @else
                                        <a class="pev-btn pev-btn--{{ $action['style'] ?? 'outline' }}" href="{{ $action['url'] }}">
                                            <i class="{{ $action['icon'] }}" aria-hidden="true"></i>{{ $action['label'] }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                            @if (! empty($step['action_note']))
                                <p class="pev-step__admin-note">{{ $step['action_note'] }}</p>
                            @endif
                        </div>
                    @endif
                @endcan
            </div>
        </li>
    @endforeach
</ol>
