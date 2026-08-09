@php
    $accessDescriptions = [
        \App\Models\User::THINK_TANK_ACCESS_ADMIN => 'Dashboard and every think tank work area, including staff access.',
        \App\Models\User::THINK_TANK_ACCESS_PROCUREMENT => 'Dashboard and procurement plans only.',
        \App\Models\User::THINK_TANK_ACCESS_ME => 'Dashboard, M&E data, and report uploads.',
        \App\Models\User::THINK_TANK_ACCESS_FINANCE => 'Dashboard and finance only.',
    ];
@endphp


<x-think-tank.partials.shell :member="$member" title="Team Access">
    <div class="tt-team-page">
        <header class="tt-team-intro">
            <div class="tt-team-eyebrow">Administrator setting</div>
            <h1>Team access</h1>
            <p>Add staff from {{ $member->name }} and give each person only the portal area required for their work.</p>
        </header>

        <section class="tt-team-stats" aria-label="Portal team summary">
            <article class="tt-team-stat">
                <strong>{{ number_format((int) ($teamStats['total'] ?? 0)) }}</strong>
                <span>Portal staff</span>
            </article>
            <article class="tt-team-stat">
                <strong>{{ number_format((int) ($teamStats['active'] ?? 0)) }}</strong>
                <span>Active accounts</span>
            </article>
            <article class="tt-team-stat">
                <strong>{{ number_format((int) ($teamStats['administrators'] ?? 0)) }}</strong>
                <span>Think Tank Admins</span>
            </article>
        </section>

        @if (session('temporary_password'))
            <div class="tt-password-once" role="status">
                <strong>Temporary password—copy it now</strong>
                <p class="tt-panel-copy">Give this password to the new staff member through a secure channel. It will not be shown again.</p>
                <code>{{ session('temporary_password') }}</code>
            </div>
        @endif

        <section class="tt-team-grid">
            <div class="tt-team-create" id="add-user">
                <h2>Add a staff account</h2>
                <p class="tt-panel-copy">The new user must change the temporary password after signing in.</p>

                <form method="POST" action="{{ route('think-tank.team-access.store', $portalRouteParams) }}">
                    @csrf
                    <div class="tt-team-fields">
                        <div class="tt-field">
                            <label for="team-name">Full name <span class="text-danger">*</span></label>
                            <input id="team-name" name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name" required>
                        </div>
                        <div class="tt-field">
                            <label for="team-email">Work email <span class="text-danger">*</span></label>
                            <input id="team-email" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
                        </div>
                        <div class="tt-field">
                            <label for="team-level">Access level <span class="text-danger">*</span></label>
                            <select id="team-level" name="access_level" required>
                                @foreach ($accessLevels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('access_level') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="tt-access-help">Choose the officer role that matches this person's responsibilities.</div>
                        </div>
                    </div>
                    <button class="btn btn-success w-100 mt-3" type="submit">
                        <i class="feather-user-plus me-1" aria-hidden="true"></i> Create staff account
                    </button>
                </form>

                <div class="mt-3 d-grid gap-2">
                    @foreach ($accessLevels as $value => $label)
                        <div class="p-2 rounded-3 bg-light border">
                            <strong class="d-block small">{{ $label }}</strong>
                            <span class="tt-access-help">{{ $accessDescriptions[$value] ?? '' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="tt-team-list" id="team-directory">
                <h2>Portal staff</h2>
                <p class="tt-panel-copy">Changes take effect on the staff member's next request.</p>

                <div class="tt-team-cards">
                    @forelse ($teamMembers as $teamUser)
                        @php
                            $parts = collect(preg_split('/\s+/', trim((string) $teamUser->name)) ?: [])->filter()->take(2);
                            $initials = $parts->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: 'TT';
                            $isCurrentUser = (string) auth()->id() === (string) $teamUser->id;
                            $isPrimaryAccount = (string) $member->portal_user_id === (string) $teamUser->id;
                            $isDisabled = $teamUser->hasActiveLoginBlock();
                        @endphp
                        <article class="tt-team-card">
                            <div class="tt-team-identity">
                                <span class="tt-team-avatar" aria-hidden="true">{{ $initials }}</span>
                                <div>
                                    <div class="tt-team-name">{{ $teamUser->name }}</div>
                                    <div class="tt-team-email">{{ $teamUser->email }}</div>
                                    <div class="tt-team-badges">
                                        <span class="tt-team-badge">{{ $teamUser->thinkTankAccessLabel() }}</span>
                                        @if ($isCurrentUser)<span class="tt-team-badge">Your account</span>@endif
                                        @if ($isPrimaryAccount)<span class="tt-team-badge">Primary account</span>@endif
                                        @if ($isDisabled)<span class="tt-team-badge is-disabled">Disabled</span>@endif
                                    </div>
                                </div>
                            </div>

                            <form class="tt-team-controls" method="POST" action="{{ route('think-tank.team-access.update', array_merge($portalRouteParams, ['teamUser' => $teamUser])) }}">
                                @csrf
                                @method('PUT')
                                <div class="tt-field">
                                    <label for="access-level-{{ $teamUser->id }}">Access level</label>
                                    <select id="access-level-{{ $teamUser->id }}" name="access_level" @disabled($isCurrentUser)>
                                        @foreach ($accessLevels as $value => $label)
                                            <option value="{{ $value }}" @selected($teamUser->resolvedThinkTankAccessLevel() === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @if ($isCurrentUser)
                                        <input type="hidden" name="access_level" value="{{ $teamUser->resolvedThinkTankAccessLevel() }}">
                                    @endif
                                </div>
                                <input type="hidden" name="is_disabled" value="0">
                                <label class="tt-disable-control">
                                    <input type="checkbox" name="is_disabled" value="1" @checked($isDisabled) @disabled($isCurrentUser)>
                                    Disable this account
                                </label>
                                @if ($isCurrentUser)
                                    <input type="hidden" name="is_disabled" value="0">
                                @endif
                                <button class="btn btn-sm btn-outline-success" type="submit">Save access</button>
                            </form>
                        </article>
                    @empty
                        <div class="text-muted small py-4 text-center">No portal staff account is linked yet.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-think-tank.partials.shell>
