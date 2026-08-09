<x-think-tank.partials.shell :member="$member" title="Audit Trails">
    <div class="tt-audit-page">
        <header class="tt-audit-hero">
            <div>
                <div class="tt-audit-eyebrow"><i class="feather-shield" aria-hidden="true"></i> Organization accountability</div>
                <h1>Audit trails</h1>
                <p>A chronological, member-isolated record of procurement decisions, M&amp;E report transitions, and portal administration activity.</p>
            </div>
            <span class="tt-audit-total"><strong>{{ number_format($summary['total']) }}</strong><small>Recorded events</small></span>
        </header>

        <section class="tt-audit-summary" aria-label="Audit activity summary">
            <article><span class="tt-audit-summary-icon"><i class="feather-briefcase"></i></span><div><strong>{{ number_format($summary['procurement']) }}</strong><small>Procurement events</small></div></article>
            <article><span class="tt-audit-summary-icon"><i class="feather-activity"></i></span><div><strong>{{ number_format($summary['me']) }}</strong><small>M&amp;E events</small></div></article>
            <article><span class="tt-audit-summary-icon"><i class="feather-settings"></i></span><div><strong>{{ number_format($summary['portal']) }}</strong><small>Portal events</small></div></article>
        </section>

        <section class="tt-audit-register">
            <div class="tt-audit-toolbar">
                <div>
                    <h2>Activity register</h2>
                    <p>Use the module filters or search for an action, person, reference, or description.</p>
                </div>
                <div class="tt-audit-scope" role="group" aria-label="Filter audit trail by module">
                    @foreach ([
                        'all' => 'All activity',
                        'procurement' => 'Procurement',
                        'me' => 'M&E',
                        'portal' => 'Portal',
                    ] as $value => $label)
                        <a class="{{ $scope === $value ? 'is-active' : '' }}"
                           href="{{ route('think-tank.audit-trails', array_merge($portalRouteParams, ['scope' => $value])) }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            <form class="tt-audit-search" method="GET" action="{{ route('think-tank.audit-trails', $portalRouteParams) }}">
                @foreach ($portalRouteParams as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <input type="hidden" name="scope" value="{{ $scope }}">
                <label>
                    <i class="feather-search" aria-hidden="true"></i>
                    <input type="search" name="q" value="{{ $keyword }}" placeholder="Search the activity register">
                </label>
                <button class="btn btn-primary" type="submit">Search</button>
                @if ($keyword !== '')
                    <a class="btn btn-light border" href="{{ route('think-tank.audit-trails', array_merge($portalRouteParams, ['scope' => $scope])) }}">Clear</a>
                @endif
            </form>

            <div class="tt-audit-list">
                @forelse ($entries as $entry)
                    <article class="tt-audit-entry">
                        <span class="tt-audit-entry-icon is-{{ $entry['tone'] }}"><i class="{{ $entry['icon'] }}" aria-hidden="true"></i></span>
                        <div class="tt-audit-entry-main">
                            <div class="tt-audit-entry-head">
                                <div>
                                    <span class="tt-audit-module">{{ $entry['module'] }}</span>
                                    <h3>{{ $entry['action'] }}</h3>
                                </div>
                                <time datetime="{{ $entry['occurred_at']?->toIso8601String() }}" title="{{ $entry['occurred_at']?->format('d M Y, H:i:s') }}">
                                    {{ $entry['occurred_at']?->diffForHumans() }}
                                </time>
                            </div>
                            <p>{{ $entry['message'] }}</p>
                            <div class="tt-audit-meta">
                                <span><i class="feather-user"></i>{{ $entry['actor'] }}</span>
                                <span><i class="feather-hash"></i>{{ $entry['reference'] }}</span>
                                @if ($entry['context'])<span><i class="feather-file-text"></i>{{ $entry['context'] }}</span>@endif
                                @if ($entry['ip_address'])<span><i class="feather-globe"></i>{{ $entry['ip_address'] }}</span>@endif
                            </div>
                        </div>
                        @if ($entry['url'])
                            <a class="tt-audit-open" href="{{ $entry['url'] }}" aria-label="Open related record"><i class="feather-arrow-up-right"></i></a>
                        @endif
                    </article>
                @empty
                    <div class="tt-audit-empty">
                        <span><i class="feather-inbox"></i></span>
                        <h3>No activity found</h3>
                        <p>No audit event matches the selected module and search terms.</p>
                    </div>
                @endforelse
            </div>

            @if ($entries->hasPages())
                <div class="tt-audit-pagination">{{ $entries->links() }}</div>
            @endif
        </section>
    </div>
</x-think-tank.partials.shell>
