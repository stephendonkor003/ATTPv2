<nav class="forum-nav mb-4" aria-label="Discussion controls">
    <a href="{{ route('system.discussions.dashboard') }}"
        @class(['active' => request()->routeIs('system.discussions.dashboard')])>
        <i class="feather-grid"></i> Overview
    </a>
    @canany(['discussions.view', 'discussions.create', 'discussions.manage'])
        <a href="{{ route('system.discussions.topics.index') }}"
            @class(['active' => request()->routeIs('system.discussions.topics.*')])>
            <i class="feather-message-square"></i> Discussions
        </a>
    @endcanany
    @can('discussions.thematic_areas.manage')
        <a href="{{ route('system.discussions.themes.index') }}"
            @class(['active' => request()->routeIs('system.discussions.themes.*')])>
            <i class="feather-layers"></i> Thematic Areas
        </a>
    @endcan
    @can('discussions.participants.manage')
        <a href="{{ route('system.discussions.participants.index') }}"
            @class(['active' => request()->routeIs('system.discussions.participants.*')])>
            <i class="feather-users"></i> Participants
        </a>
    @endcan
    @can('discussions.moderate')
        <a href="{{ route('system.discussions.moderation.live') }}"
            @class(['active' => request()->routeIs('system.discussions.moderation.live*')])>
            <i class="feather-radio"></i> Live Monitor
        </a>
        <a href="{{ route('system.discussions.moderation.index') }}"
            @class(['active' => request()->routeIs('system.discussions.moderation.index')])>
            <i class="feather-shield"></i> Moderation History
        </a>
    @endcan
</nav>
