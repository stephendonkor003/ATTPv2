@extends('layouts.app')
@section('title', 'Think Tank Users')

@push('styles')
<style>
    .ttu-page{display:grid;gap:16px;padding-bottom:30px;color:#172b23}.ttu-hero{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:28px;padding:28px 30px;border-radius:16px;background:linear-gradient(120deg,#102f27,#0d5a47 64%,#18765d);color:#fff;box-shadow:0 14px 34px rgba(13,62,49,.17)}.ttu-hero:after{position:absolute;right:-70px;bottom:-135px;width:270px;height:270px;border:42px solid rgba(255,255,255,.055);border-radius:50%;content:""}.ttu-hero-copy,.ttu-hero-aside{position:relative;z-index:1}.ttu-kicker{color:#bfe8d8;font-size:9px;font-weight:900;letter-spacing:.09em;text-transform:uppercase}.ttu-hero h1{margin:5px 0 7px;color:#fff;font-size:clamp(1.55rem,2.8vw,2.2rem);font-weight:900;letter-spacing:-.025em}.ttu-hero p{max-width:720px;margin:0;color:#d6eee5;font-size:12px;line-height:1.6}.ttu-hero-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:17px}.ttu-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:38px;padding:8px 13px;border:1px solid #c7d8d0;border-radius:8px;background:#fff;color:#234a3b;font-size:10px;font-weight:900;text-decoration:none}.ttu-btn:hover{border-color:#79aa96;color:#0d6248}.ttu-btn.primary{border-color:#0f766e;background:#0f766e;color:#fff}.ttu-btn.light{border-color:rgba(255,255,255,.25);background:rgba(255,255,255,.12);color:#fff}.ttu-btn.danger{border-color:#edb7b7;background:#fff7f7;color:#9a3030}.ttu-btn:disabled{cursor:not-allowed;opacity:.55}.ttu-hero-aside{align-self:stretch;padding:15px;border:1px solid rgba(255,255,255,.17);border-radius:12px;background:rgba(6,41,31,.31)}.ttu-hero-aside>span{display:grid;width:34px;height:34px;place-items:center;border-radius:9px;background:rgba(246,197,80,.15);color:#f6c550}.ttu-hero-aside strong{display:block;margin-top:11px;font-size:11px}.ttu-hero-aside p{margin-top:3px;color:#c9e2d9;font-size:9px}.ttu-role-mini{display:flex;flex-wrap:wrap;gap:5px;margin-top:12px}.ttu-role-mini span{padding:5px 7px;border:1px solid rgba(255,255,255,.13);border-radius:999px;color:#e6f3ee;font-size:7px;font-weight:800}.ttu-metrics{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.ttu-metric{display:flex;align-items:center;gap:11px;padding:14px 15px;border:1px solid #dce6e1;border-radius:11px;background:#fff;box-shadow:0 4px 14px rgba(18,47,38,.035)}.ttu-metric>span{display:grid;width:37px;height:37px;flex:0 0 37px;place-items:center;border-radius:9px;background:#e8f4ef;color:#17694f}.ttu-metric:nth-child(2)>span{background:#e8f1f8;color:#296789}.ttu-metric:nth-child(3)>span{background:#f0ebf8;color:#70519a}.ttu-metric:nth-child(4)>span{background:#fff1df;color:#965d1e}.ttu-metric:nth-child(5)>span{background:#e7f5f5;color:#1a7372}.ttu-metric small,.ttu-metric strong{display:block}.ttu-metric small{color:#7b8b84;font-size:7px;font-weight:900;letter-spacing:.055em;text-transform:uppercase}.ttu-metric strong{margin-top:1px;color:#19392d;font-size:19px;font-weight:900}.ttu-alert{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border:1px solid #b9dbc9;border-radius:10px;background:#f2faf6;color:#235c45}.ttu-alert.error{border-color:#edb9b9;background:#fff7f7;color:#943434}.ttu-alert.password{border-color:#e6ca87;background:#fffbeb;color:#654d14}.ttu-alert>i{margin-top:2px}.ttu-alert strong{display:block;font-size:10px}.ttu-alert p{margin:2px 0 0;font-size:9px;line-height:1.5}.ttu-password-line{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin-top:8px}.ttu-password-line code{padding:7px 9px;border:1px solid #dfc478;border-radius:7px;background:#fff;color:#473707;font-size:11px;user-select:all}.ttu-workspace{display:grid;grid-template-columns:minmax(310px,.34fr) minmax(0,1fr);gap:14px;align-items:start}.ttu-panel{border:1px solid #dce6e1;border-radius:13px;background:#fff;box-shadow:0 6px 18px rgba(18,47,38,.04)}.ttu-create{position:sticky;top:82px;overflow:hidden}.ttu-panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:16px 17px;border-bottom:1px solid #e4ebe8}.ttu-panel-head h2{margin:2px 0 3px;color:#17362a;font-size:14px;font-weight:900}.ttu-panel-head p{margin:0;color:#718078;font-size:9px;line-height:1.5}.ttu-panel-icon{display:grid;width:36px;height:36px;flex:0 0 36px;place-items:center;border-radius:9px;background:#e8f4ef;color:#176a4f}.ttu-form{display:grid;gap:11px;padding:16px 17px}.ttu-field label,.ttu-role-label{display:block;margin-bottom:4px;color:#5d7168;font-size:8px;font-weight:900;letter-spacing:.045em;text-transform:uppercase}.ttu-field input,.ttu-field select,.ttu-filter input,.ttu-filter select,.ttu-manage-form select{width:100%;min-height:40px;border:1px solid #cbd9d2;border-radius:8px;background:#fff;padding:8px 10px;color:#29473b;font-size:10px}.ttu-field input:focus,.ttu-field select:focus,.ttu-filter input:focus,.ttu-filter select:focus,.ttu-manage-form select:focus{outline:0;border-color:#5ca087;box-shadow:0 0 0 3px rgba(15,118,110,.1)}.ttu-error{display:block;margin-top:4px;color:#a83737;font-size:8px}.ttu-role-options{display:grid;gap:7px}.ttu-role-option{position:relative;display:grid;grid-template-columns:31px minmax(0,1fr);align-items:center;gap:9px;padding:9px;border:1px solid #dce6e1;border-radius:9px;background:#fbfcfc;cursor:pointer}.ttu-role-option:hover{border-color:#a8c7b9;background:#f8fcfa}.ttu-role-option input{position:absolute;opacity:0;pointer-events:none}.ttu-role-option:has(input:checked){border-color:#4d9479;background:#edf7f2;box-shadow:0 0 0 2px rgba(42,129,96,.08)}.ttu-role-option>span{display:grid;width:30px;height:30px;place-items:center;border-radius:8px;background:#e8f2ed;color:#1d6b50}.ttu-role-option strong,.ttu-role-option small{display:block}.ttu-role-option strong{color:#28473b;font-size:9px}.ttu-role-option small{margin-top:2px;color:#74847c;font-size:7px;line-height:1.4}.ttu-create-note{display:flex;gap:7px;padding:10px;border-radius:8px;background:#f4f7f6;color:#607269;font-size:8px;line-height:1.5}.ttu-directory{min-width:0;overflow:hidden}.ttu-filter-shell{padding:14px 16px;border-bottom:1px solid #e4ebe8;background:#f9fbfa}.ttu-filter{display:grid;grid-template-columns:minmax(180px,1fr) repeat(3,minmax(125px,.55fr)) auto;align-items:end;gap:7px}.ttu-filter label{display:block;margin-bottom:4px;color:#697b72;font-size:7px;font-weight:900;text-transform:uppercase}.ttu-filter-search{position:relative}.ttu-filter-search i{position:absolute;left:10px;top:50%;color:#819189;transform:translateY(-50%)}.ttu-filter-search input{padding-left:29px}.ttu-filter-actions{display:flex;gap:6px}.ttu-directory-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 16px;border-bottom:1px solid #e7edea;color:#718078;font-size:9px}.ttu-directory-summary strong{color:#294c3e}.ttu-clear{color:#9a4e3d;font-size:8px;font-weight:900;text-decoration:none}.ttu-users{display:grid}.ttu-user{display:grid;grid-template-columns:minmax(215px,1.2fr) minmax(190px,.9fr) minmax(155px,.65fr) auto;align-items:center;gap:13px;padding:13px 16px;border-bottom:1px solid #e7edea}.ttu-user:last-child{border-bottom:0}.ttu-user:hover{background:#fbfdfc}.ttu-user-identity{display:flex;align-items:center;gap:10px;min-width:0}.ttu-avatar{display:grid;width:39px;height:39px;flex:0 0 39px;place-items:center;border-radius:10px;background:#e8f4ef;color:#17664d;font-size:10px;font-weight:900}.ttu-user-name,.ttu-user-email{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ttu-user-name{color:#1f3b30;font-size:10px;font-weight:900}.ttu-user-email{margin-top:2px;color:#7b8a83;font-size:8px}.ttu-user-member strong,.ttu-user-member span{display:block}.ttu-user-member strong{color:#315145;font-size:9px}.ttu-user-member span{margin-top:2px;color:#829088;font-size:8px}.ttu-user-access{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.ttu-badge{display:inline-flex;align-items:center;gap:4px;padding:5px 7px;border-radius:999px;background:#edf5f1;color:#32634f;font-size:7px;font-weight:900}.ttu-badge.procurement_officer{background:#e8f1f8;color:#2b6385}.ttu-badge.me_officer{background:#f0ebf8;color:#71509b}.ttu-badge.finance_officer{background:#fff1df;color:#91591c}.ttu-badge.disabled{background:#feeaea;color:#973535}.ttu-status-dot{width:6px;height:6px;border-radius:50%;background:#28a674}.ttu-status-dot.disabled{background:#cc5555}.ttu-user-actions{display:flex;align-items:center;justify-content:flex-end;gap:6px}.ttu-user details{grid-column:1/-1;margin-top:-2px;border:1px solid #e1e9e5;border-radius:9px;background:#f8faf9}.ttu-user details summary{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;color:#557066;font-size:8px;font-weight:900;cursor:pointer;list-style:none}.ttu-user details summary::-webkit-details-marker{display:none}.ttu-user details summary:after{font-family:feather!important;content:"\e842"}.ttu-user details[open] summary:after{transform:rotate(90deg)}.ttu-manage-body{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;padding:10px;border-top:1px solid #e1e9e5}.ttu-manage-form{display:grid;grid-template-columns:minmax(180px,1fr) minmax(155px,.8fr) minmax(110px,.5fr) auto;gap:7px;align-items:end}.ttu-manage-form label{display:block;margin-bottom:3px;color:#697b72;font-size:7px;font-weight:900;text-transform:uppercase}.ttu-reset-form{align-self:end}.ttu-empty{padding:48px 20px;text-align:center}.ttu-empty>span{display:grid;width:46px;height:46px;margin:auto;place-items:center;border-radius:12px;background:#eaf4ef;color:#2d6f57;font-size:17px}.ttu-empty h3{margin:10px 0 3px;color:#29463a;font-size:13px}.ttu-empty p{margin:0;color:#7b8a83;font-size:9px}.ttu-pagination{padding:12px 16px;border-top:1px solid #e4ebe8}.ttu-audit-note{display:flex;align-items:center;gap:7px;padding:10px 13px;border:1px solid #dce6e1;border-radius:9px;background:#f8faf9;color:#687a71;font-size:8px}.ttu-audit-note i{color:#187054}
    @media(max-width:1200px){.ttu-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}.ttu-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.ttu-filter-actions{grid-column:1/-1}.ttu-user{grid-template-columns:minmax(200px,1fr) minmax(180px,.8fr)}.ttu-user-access{grid-column:1}.ttu-user-actions{grid-column:2;justify-content:flex-end}.ttu-manage-form{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:900px){.ttu-hero{grid-template-columns:1fr}.ttu-workspace{grid-template-columns:1fr}.ttu-create{position:static}.ttu-user{grid-template-columns:1fr 1fr}.ttu-user-access,.ttu-user-actions{grid-column:auto}.ttu-user-actions{justify-content:flex-start}}
    @media(max-width:650px){.ttu-hero{padding:23px 20px}.ttu-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.ttu-metric:last-child{grid-column:1/-1}.ttu-filter,.ttu-user,.ttu-manage-form,.ttu-manage-body{grid-template-columns:1fr}.ttu-filter-actions,.ttu-user-access,.ttu-user-actions{grid-column:1}.ttu-filter-actions .ttu-btn{flex:1}.ttu-user-actions{justify-content:stretch}.ttu-user-actions .ttu-btn,.ttu-reset-form .ttu-btn{width:100%}.ttu-directory-summary{align-items:flex-start;flex-direction:column}}
</style>
@endpush

@section('content')
@php
    $hasFilters = collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty();
    $roleDetails = [
        \App\Models\User::THINK_TANK_ACCESS_ADMIN => [
            'icon' => 'feather-shield',
            'description' => 'Full Think Tank portal access, including team administration.',
        ],
        \App\Models\User::THINK_TANK_ACCESS_PROCUREMENT => [
            'icon' => 'feather-shopping-bag',
            'description' => 'Procurement plans, items, supporting documents and execution.',
        ],
        \App\Models\User::THINK_TANK_ACCESS_ME => [
            'icon' => 'feather-activity',
            'description' => 'M&E data collection, indicators and performance reporting.',
        ],
    ];
@endphp

<div class="nxl-container">
    <main class="ttu-page">
        <section class="ttu-hero">
            <div class="ttu-hero-copy">
                <div class="ttu-kicker">Think Tank Module &middot; Access administration</div>
                <h1>Think Tank users</h1>
                <p>Create secure portal accounts for each Think Tank and assign only the work area each officer needs. Organization boundaries remain enforced across procurement, M&amp;E and administration.</p>
                <div class="ttu-hero-actions">
                    <a class="ttu-btn light" href="#create-think-tank-user"><i class="feather-user-plus"></i> Create new user</a>
                    <a class="ttu-btn light" href="{{ route('think-tank-procurement.index') }}"><i class="feather-clipboard"></i> Procurement workspace</a>
                </div>
            </div>
            <aside class="ttu-hero-aside">
                <span><i class="feather-lock"></i></span>
                <strong>Role-based portal access</strong>
                <p>Every account is tied to one Think Tank. Users cannot see another organization’s records.</p>
                <div class="ttu-role-mini">
                    <span>Administrator</span><span>Procurement</span><span>M&amp;E</span>
                </div>
            </aside>
        </section>

        <section class="ttu-metrics" aria-label="Think Tank user summary">
            <article class="ttu-metric"><span><i class="feather-users"></i></span><div><small>Total users</small><strong>{{ number_format($stats['total']) }}</strong></div></article>
            <article class="ttu-metric"><span><i class="feather-user-check"></i></span><div><small>Active accounts</small><strong>{{ number_format($stats['active']) }}</strong></div></article>
            <article class="ttu-metric"><span><i class="feather-shield"></i></span><div><small>Administrators</small><strong>{{ number_format($stats['administrators']) }}</strong></div></article>
            <article class="ttu-metric"><span><i class="feather-shopping-bag"></i></span><div><small>Procurement officers</small><strong>{{ number_format($stats['procurement']) }}</strong></div></article>
            <article class="ttu-metric"><span><i class="feather-activity"></i></span><div><small>M&amp;E officers</small><strong>{{ number_format($stats['me']) }}</strong></div></article>
        </section>

        @if(session('success'))
            <div class="ttu-alert" role="status"><i class="feather-check-circle"></i><div><strong>Action completed</strong><p>{{ session('success') }}</p></div></div>
        @endif
        @if($errors->any())
            <div class="ttu-alert error" role="alert"><i class="feather-alert-circle"></i><div><strong>Please check the information provided</strong><p>{{ $errors->first() }}</p></div></div>
        @endif
        @if(session('temporary_password'))
            <div class="ttu-alert password" role="status">
                <i class="feather-key"></i>
                <div>
                    <strong>Temporary password for {{ session('temporary_password_user') ?: 'the user' }} — copy it now</strong>
                    <p>This password is shown once. The user must change it after signing in.</p>
                    <div class="ttu-password-line"><code data-temporary-password>{{ session('temporary_password') }}</code><button class="ttu-btn" type="button" data-copy-password><i class="feather-copy"></i> Copy password</button></div>
                </div>
            </div>
        @endif

        <section class="ttu-workspace">
            <aside id="create-think-tank-user" class="ttu-panel ttu-create">
                <header class="ttu-panel-head">
                    <div><div class="ttu-kicker">New portal account</div><h2>Create a Think Tank user</h2><p>Credentials are emailed and the temporary password must be changed.</p></div>
                    <span class="ttu-panel-icon"><i class="feather-user-plus"></i></span>
                </header>
                <form class="ttu-form" method="POST" action="{{ route('system.think-tank-users.store') }}">
                    @csrf
                    <div class="ttu-field">
                        <label for="ttu-name">Full name</label>
                        <input id="ttu-name" name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name" placeholder="Officer’s full name" required>
                        @error('name')<span class="ttu-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ttu-field">
                        <label for="ttu-email">Work email address</label>
                        <input id="ttu-email" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" placeholder="name@organisation.org" required>
                        @error('email')<span class="ttu-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ttu-field">
                        <label for="ttu-member">Think Tank</label>
                        <select id="ttu-member" name="think_tank_member_id" required>
                            <option value="">Select the user’s organization</option>
                            @foreach($members as $member)
                                <option value="{{ $member->id }}" @selected(old('think_tank_member_id') === $member->id)>{{ $member->name }}{{ $member->country ? ' — '.$member->country : '' }}</option>
                            @endforeach
                        </select>
                        @error('think_tank_member_id')<span class="ttu-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <span class="ttu-role-label">Portal role</span>
                        <div class="ttu-role-options">
                            @foreach($accessLevels as $value => $label)
                                <label class="ttu-role-option">
                                    <input type="radio" name="access_level" value="{{ $value }}" @checked(old('access_level', \App\Models\User::THINK_TANK_ACCESS_PROCUREMENT) === $value) required>
                                    <span><i class="{{ $roleDetails[$value]['icon'] }}"></i></span>
                                    <span><strong>{{ $label }}</strong><small>{{ $roleDetails[$value]['description'] }}</small></span>
                                </label>
                            @endforeach
                        </div>
                        @error('access_level')<span class="ttu-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ttu-create-note"><i class="feather-info"></i><span>The new user receives organization-specific access only. All account creation and access changes are written to the system audit trail.</span></div>
                    <button class="ttu-btn primary" type="submit"><i class="feather-user-check"></i> Create user and send access</button>
                </form>
            </aside>

            <section class="ttu-panel ttu-directory">
                <header class="ttu-panel-head">
                    <div><div class="ttu-kicker">User directory</div><h2>Manage Think Tank access</h2><p>Find an account, change its organization or officer role, disable access, or issue a new password.</p></div>
                    <span class="ttu-panel-icon"><i class="feather-users"></i></span>
                </header>
                <div class="ttu-filter-shell">
                    <form class="ttu-filter" method="GET" action="{{ route('system.think-tank-users.index') }}">
                        <div><label for="ttu-search">Search</label><div class="ttu-filter-search"><i class="feather-search"></i><input id="ttu-search" name="q" value="{{ $filters['q'] }}" placeholder="Name, email or Think Tank"></div></div>
                        <div><label for="ttu-filter-member">Think Tank</label><select id="ttu-filter-member" name="think_tank_member_id"><option value="">All Think Tanks</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected($filters['think_tank_member_id'] === $member->id)>{{ $member->name }}</option>@endforeach</select></div>
                        <div><label for="ttu-filter-role">Role</label><select id="ttu-filter-role" name="access_level"><option value="">All roles</option>@foreach($allAccessLevels as $value => $label)<option value="{{ $value }}" @selected($filters['access_level'] === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div><label for="ttu-filter-status">Status</label><select id="ttu-filter-status" name="account_status"><option value="">All accounts</option><option value="active" @selected($filters['account_status'] === 'active')>Active</option><option value="disabled" @selected($filters['account_status'] === 'disabled')>Disabled</option></select></div>
                        <div class="ttu-filter-actions"><button class="ttu-btn primary" type="submit"><i class="feather-filter"></i> Filter</button></div>
                    </form>
                </div>
                <div class="ttu-directory-summary">
                    <span><strong>{{ number_format($users->total()) }}</strong> {{ Str::plural('user', $users->total()) }} match this view</span>
                    @if($hasFilters)<a class="ttu-clear" href="{{ route('system.think-tank-users.index') }}"><i class="feather-x"></i> Clear filters</a>@endif
                </div>
                <div class="ttu-users">
                    @forelse($users as $teamUser)
                        @php
                            $membership = $teamUser->assignedThinkTankMembership ?: $teamUser->thinkTankMembership;
                            $level = $teamUser->resolvedThinkTankAccessLevel();
                            $isDisabled = $teamUser->hasActiveLoginBlock();
                            $initials = collect(preg_split('/\s+/', trim((string) $teamUser->name)) ?: [])->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('') ?: 'TT';
                        @endphp
                        <article class="ttu-user">
                            <div class="ttu-user-identity"><span class="ttu-avatar">{{ $initials }}</span><div><span class="ttu-user-name">{{ $teamUser->name }}</span><span class="ttu-user-email">{{ $teamUser->email }}</span></div></div>
                            <div class="ttu-user-member"><strong>{{ $membership?->name ?: 'Organization not assigned' }}</strong><span>{{ $membership?->country ?: 'Country not set' }} @if($membership?->consortium)&middot; {{ $membership->consortium->name }}@endif</span></div>
                            <div class="ttu-user-access"><span class="ttu-badge {{ $level }}">{{ $teamUser->thinkTankAccessLabel() }}</span><span class="ttu-badge"><i class="ttu-status-dot {{ $isDisabled ? 'disabled' : '' }}"></i>{{ $isDisabled ? 'Disabled' : 'Active' }}</span></div>
                            <div class="ttu-user-actions"><a class="ttu-btn" href="{{ route('system.think-tank-users.show', $teamUser) }}"><i class="feather-edit-3"></i> View &amp; edit</a></div>
                        </article>
                    @empty
                        <div class="ttu-empty"><span><i class="feather-user-x"></i></span><h3>No Think Tank users found</h3><p>Adjust the filters or create the first portal user from the form.</p></div>
                    @endforelse
                </div>
                @if($users->hasPages())<div class="ttu-pagination">{{ $users->links() }}</div>@endif
            </section>
        </section>

        <div class="ttu-audit-note"><i class="feather-shield"></i><span>Account creation, role changes, organization reassignment, login disabling and password resets are recorded in the audit trail.</span></div>
    </main>
</div>
@endsection

@push('scripts')
<script>
document.querySelector('[data-copy-password]')?.addEventListener('click', async event => {
    const password = document.querySelector('[data-temporary-password]')?.textContent?.trim();
    if (!password) return;
    await navigator.clipboard.writeText(password);
    event.currentTarget.innerHTML = '<i class="feather-check"></i> Copied';
});
</script>
@endpush
