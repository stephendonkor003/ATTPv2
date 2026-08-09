@extends('layouts.app')
@section('title', 'Think Tank User — '.$user->name)

@push('styles')
<style>
    .ttud{display:grid;gap:15px;padding-bottom:30px;color:#172b23}.ttud-hero{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:22px;padding:24px 27px;border-radius:15px;background:linear-gradient(120deg,#102f27,#0d5b48 70%,#18775d);color:#fff;box-shadow:0 13px 31px rgba(13,62,49,.16)}.ttud-hero:after{position:absolute;right:-55px;bottom:-115px;width:235px;height:235px;border:38px solid rgba(255,255,255,.055);border-radius:50%;content:""}.ttud-hero-main,.ttud-hero-actions{position:relative;z-index:1}.ttud-back{display:inline-flex;align-items:center;gap:5px;color:#c6e6da;font-size:9px;font-weight:850;text-decoration:none}.ttud-back:hover{color:#fff}.ttud-identity{display:flex;align-items:center;gap:13px;margin-top:13px}.ttud-avatar{display:grid;width:54px;height:54px;flex:0 0 54px;place-items:center;border:1px solid rgba(255,255,255,.26);border-radius:13px;background:rgba(255,255,255,.12);font-size:14px;font-weight:900}.ttud-identity h1{margin:0;color:#fff;font-size:clamp(1.35rem,2.5vw,1.9rem);font-weight:900}.ttud-identity p{margin:4px 0 0;color:#d2e9e0;font-size:10px}.ttud-hero-actions{display:flex;flex-wrap:wrap;gap:7px}.ttud-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:38px;padding:8px 13px;border:1px solid #c8d8d1;border-radius:8px;background:#fff;color:#234a3b;font-size:10px;font-weight:900;text-decoration:none}.ttud-btn:hover{border-color:#7bac98;color:#0e644a}.ttud-btn.primary{border-color:#0f766e;background:#0f766e;color:#fff}.ttud-btn.light{border-color:rgba(255,255,255,.26);background:rgba(255,255,255,.11);color:#fff}.ttud-btn.warn{border-color:#e5c779;background:#fff9e9;color:#7d5a08}.ttud-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.ttud-metric{display:flex;align-items:center;gap:10px;padding:13px 14px;border:1px solid #dce6e1;border-radius:10px;background:#fff}.ttud-metric>span{display:grid;width:34px;height:34px;flex:0 0 34px;place-items:center;border-radius:9px;background:#e8f4ef;color:#17684f}.ttud-metric small,.ttud-metric strong{display:block}.ttud-metric small{color:#7c8b84;font-size:7px;font-weight:900;text-transform:uppercase}.ttud-metric strong{overflow:hidden;margin-top:2px;color:#28473a;font-size:9px;font-weight:900;text-overflow:ellipsis;white-space:nowrap}.ttud-alert{display:flex;align-items:flex-start;gap:9px;padding:12px 14px;border:1px solid #badbca;border-radius:10px;background:#f2faf6;color:#245e47}.ttud-alert.error{border-color:#ecbaba;background:#fff7f7;color:#963535}.ttud-alert.password{border-color:#e4ca83;background:#fffbeb;color:#665015}.ttud-alert strong{display:block;font-size:10px}.ttud-alert p{margin:2px 0 0;font-size:9px}.ttud-password{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin-top:8px}.ttud-password code{padding:7px 9px;border:1px solid #dec477;border-radius:7px;background:#fff;color:#443606;font-size:11px;user-select:all}.ttud-grid{display:grid;grid-template-columns:minmax(0,1fr) 315px;gap:13px;align-items:start}.ttud-panel{border:1px solid #dce6e1;border-radius:12px;background:#fff;box-shadow:0 5px 17px rgba(18,47,38,.04)}.ttud-panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:15px 17px;border-bottom:1px solid #e4ebe8}.ttud-panel-head h2{margin:2px 0 3px;color:#19392d;font-size:14px;font-weight:900}.ttud-panel-head p{margin:0;color:#718078;font-size:9px;line-height:1.5}.ttud-kicker{color:#0f766e;font-size:8px;font-weight:900;letter-spacing:.07em;text-transform:uppercase}.ttud-panel-icon{display:grid;width:35px;height:35px;flex:0 0 35px;place-items:center;border-radius:9px;background:#e8f4ef;color:#17694f}.ttud-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:17px}.ttud-field label{display:block;margin-bottom:4px;color:#5e7168;font-size:8px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.ttud-field input,.ttud-field select{width:100%;min-height:42px;border:1px solid #cad8d1;border-radius:8px;background:#fff;padding:8px 10px;color:#29473b;font-size:10px}.ttud-field input:focus,.ttud-field select:focus{outline:0;border-color:#5a9f86;box-shadow:0 0 0 3px rgba(15,118,110,.1)}.ttud-field small{display:block;margin-top:4px;color:#849189;font-size:7px}.ttud-error{display:block;margin-top:4px;color:#a53838;font-size:8px}.ttud-form-footer{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:4px}.ttud-form-footer p{margin:0;color:#7b8982;font-size:8px}.ttud-side{display:grid;gap:12px}.ttud-security-body{display:grid;gap:11px;padding:16px}.ttud-security-item{display:flex;gap:9px;padding:10px;border:1px solid #e0e8e4;border-radius:9px;background:#fafcfb}.ttud-security-item>span{display:grid;width:29px;height:29px;flex:0 0 29px;place-items:center;border-radius:7px;background:#eaf3ef;color:#276b52}.ttud-security-item strong,.ttud-security-item small{display:block}.ttud-security-item strong{color:#315044;font-size:9px}.ttud-security-item small{margin-top:2px;color:#7a8881;font-size:7px;line-height:1.45}.ttud-reset{padding-top:2px}.ttud-reset .ttud-btn{width:100%}.ttud-role-guide{display:grid;gap:7px;padding:15px}.ttud-role{padding:9px;border:1px solid #e0e8e4;border-radius:8px;background:#fafcfb}.ttud-role strong,.ttud-role span{display:block}.ttud-role strong{color:#315044;font-size:9px}.ttud-role span{margin-top:2px;color:#7a8881;font-size:7px;line-height:1.45}.ttud-audit{overflow:hidden}.ttud-audit-list{display:grid}.ttud-event{display:grid;grid-template-columns:31px minmax(0,1fr) auto;gap:10px;padding:12px 16px;border-bottom:1px solid #e7edea}.ttud-event:last-child{border-bottom:0}.ttud-event-icon{display:grid;width:30px;height:30px;place-items:center;border-radius:8px;background:#e9f3ee;color:#236a50}.ttud-event strong{display:block;color:#2b493d;font-size:9px}.ttud-event p{margin:2px 0 0;color:#78877f;font-size:8px}.ttud-event time{color:#85928c;font-size:7px;white-space:nowrap}.ttud-empty{padding:30px;text-align:center;color:#7b8982;font-size:9px}
    @media(max-width:950px){.ttud-grid{grid-template-columns:1fr}.ttud-side{grid-template-columns:repeat(2,minmax(0,1fr))}.ttud-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:650px){.ttud-hero{align-items:flex-start;flex-direction:column}.ttud-hero-actions,.ttud-hero-actions .ttud-btn{width:100%}.ttud-form,.ttud-side{grid-template-columns:1fr}.ttud-form-footer{align-items:stretch;flex-direction:column}.ttud-form-footer .ttud-btn{width:100%}.ttud-event{grid-template-columns:31px 1fr}.ttud-event time{grid-column:2}.ttud-metrics{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $membership = $user->assignedThinkTankMembership ?: $user->thinkTankMembership;
    $level = $user->resolvedThinkTankAccessLevel();
    $isDisabled = $user->hasActiveLoginBlock();
    $initials = collect(preg_split('/\s+/', trim((string) $user->name)) ?: [])->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('') ?: 'TT';
    $roleDetails = [
        \App\Models\User::THINK_TANK_ACCESS_ADMIN => 'Full portal access, including administration of the Think Tank team.',
        \App\Models\User::THINK_TANK_ACCESS_PROCUREMENT => 'Procurement plans, items, documents, evaluation and execution access.',
        \App\Models\User::THINK_TANK_ACCESS_ME => 'M&E data collection, indicators and performance-reporting access.',
    ];
    $eventIcons = [
        'think_tank_user_created' => 'feather-user-plus',
        'think_tank_user_updated' => 'feather-edit-3',
        'think_tank_user_password_reset' => 'feather-key',
    ];
@endphp

<div class="nxl-container">
    <main class="ttud">
        <section class="ttud-hero">
            <div class="ttud-hero-main">
                <a class="ttud-back" href="{{ route('system.think-tank-users.index') }}"><i class="feather-arrow-left"></i> Think Tank users</a>
                <div class="ttud-identity">
                    <span class="ttud-avatar">{{ $initials }}</span>
                    <div><h1>{{ $user->name }}</h1><p>{{ $user->email }} &middot; {{ $user->thinkTankAccessLabel() }}</p></div>
                </div>
            </div>
            <div class="ttud-hero-actions">
                <a class="ttud-btn light" href="#edit-account"><i class="feather-edit-3"></i> Edit account</a>
                <a class="ttud-btn light" href="#account-audit"><i class="feather-clock"></i> Audit history</a>
            </div>
        </section>

        <section class="ttud-metrics" aria-label="User account summary">
            <article class="ttud-metric"><span><i class="feather-briefcase"></i></span><div><small>Think Tank</small><strong>{{ $membership?->name ?: 'Not assigned' }}</strong></div></article>
            <article class="ttud-metric"><span><i class="feather-shield"></i></span><div><small>Portal role</small><strong>{{ $user->thinkTankAccessLabel() }}</strong></div></article>
            <article class="ttud-metric"><span><i class="{{ $isDisabled ? 'feather-user-x' : 'feather-user-check' }}"></i></span><div><small>Account status</small><strong>{{ $isDisabled ? 'Disabled' : 'Active' }}</strong></div></article>
            <article class="ttud-metric"><span><i class="feather-calendar"></i></span><div><small>Account created</small><strong>{{ $user->created_at?->format('d M Y') ?: 'Not recorded' }}</strong></div></article>
        </section>

        @if(session('success'))<div class="ttud-alert" role="status"><i class="feather-check-circle"></i><div><strong>Action completed</strong><p>{{ session('success') }}</p></div></div>@endif
        @if($errors->any())<div class="ttud-alert error" role="alert"><i class="feather-alert-circle"></i><div><strong>Please review the highlighted information</strong><p>{{ $errors->first() }}</p></div></div>@endif
        @if(session('temporary_password'))
            <div class="ttud-alert password" role="status"><i class="feather-key"></i><div><strong>New temporary password — copy it now</strong><p>The user must change this password after signing in.</p><div class="ttud-password"><code data-temporary-password>{{ session('temporary_password') }}</code><button class="ttud-btn" type="button" data-copy-password><i class="feather-copy"></i> Copy password</button></div></div></div>
        @endif

        <section class="ttud-grid">
            <div id="edit-account" class="ttud-panel">
                <header class="ttud-panel-head"><div><div class="ttud-kicker">Account details</div><h2>View and edit user information</h2><p>Update the user’s identity, email address, organization, portal role and login status.</p></div><span class="ttud-panel-icon"><i class="feather-edit"></i></span></header>
                <form class="ttud-form" method="POST" action="{{ route('system.think-tank-users.update', $user) }}">
                    @csrf
                    @method('PUT')
                    <div class="ttud-field"><label for="user-name">Full name</label><input id="user-name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" autocomplete="name" required>@error('name')<span class="ttud-error">{{ $message }}</span>@enderror</div>
                    <div class="ttud-field"><label for="user-email">Email address</label><input id="user-email" type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email" required><small>This becomes the user’s portal login email.</small>@error('email')<span class="ttud-error">{{ $message }}</span>@enderror</div>
                    <div class="ttud-field"><label for="user-member">Think Tank</label><select id="user-member" name="think_tank_member_id" required>@foreach($members as $member)<option value="{{ $member->id }}" @selected((string) old('think_tank_member_id', $membership?->id) === (string) $member->id)>{{ $member->name }}{{ $member->country ? ' — '.$member->country : '' }}</option>@endforeach</select><small>Controls which organization’s records this user can access.</small>@error('think_tank_member_id')<span class="ttud-error">{{ $message }}</span>@enderror</div>
                    <div class="ttud-field"><label for="user-access">Portal role</label><select id="user-access" name="access_level" required>@if($level && !array_key_exists($level, $accessLevels))<option value="{{ $level }}" selected>{{ $allAccessLevels[$level] ?? Str::headline($level) }} (existing)</option>@endif @foreach($accessLevels as $value => $label)<option value="{{ $value }}" @selected(old('access_level', $level) === $value)>{{ $label }}</option>@endforeach</select><small>Defines the modules and actions available to this officer.</small>@error('access_level')<span class="ttud-error">{{ $message }}</span>@enderror</div>
                    <div class="ttud-field"><label for="user-status">Login status</label><select id="user-status" name="account_status"><option value="active" @selected(old('account_status', $isDisabled ? 'disabled' : 'active') === 'active')>Active — login allowed</option><option value="disabled" @selected(old('account_status', $isDisabled ? 'disabled' : 'active') === 'disabled')>Disabled — login blocked</option></select><small>Disabling preserves records but immediately blocks portal access.</small>@error('account_status')<span class="ttud-error">{{ $message }}</span>@enderror</div>
                    <div class="ttud-field"><label>Account identifier</label><input value="{{ $user->id }}" readonly><small>Permanent system identifier; it cannot be changed.</small></div>
                    <div class="ttud-form-footer"><p>Saving creates an audit entry. If this administrator changes role, organization or status, primary access is reassigned automatically when another administrator exists.</p><button class="ttud-btn primary" type="submit"><i class="feather-save"></i> Save account changes</button></div>
                </form>
            </div>

            <aside class="ttud-side">
                <section class="ttud-panel">
                    <header class="ttud-panel-head"><div><div class="ttud-kicker">Security</div><h2>Password access</h2><p>Passwords cannot be viewed. Generate a secure temporary password when access must be recovered.</p></div><span class="ttud-panel-icon"><i class="feather-lock"></i></span></header>
                    <div class="ttud-security-body">
                        <div class="ttud-security-item"><span><i class="feather-key"></i></span><div><strong>{{ $user->must_change_password ? 'Password change required' : 'Password established' }}</strong><small>{{ $user->password_changed_at ? 'Last changed '.$user->password_changed_at->diffForHumans() : 'No password-change date is recorded.' }}</small></div></div>
                        <div class="ttud-security-item"><span><i class="feather-mail"></i></span><div><strong>Credentials sent by email</strong><small>A reset generates a new password and invalidates the previous password immediately.</small></div></div>
                        <form class="ttud-reset" method="POST" action="{{ route('system.think-tank-users.reset-password', $user) }}" onsubmit="return confirm('Generate and email a new temporary password for this user?')">@csrf<button class="ttud-btn warn" type="submit"><i class="feather-refresh-cw"></i> Reset and email password</button></form>
                    </div>
                </section>
                <section class="ttud-panel">
                    <header class="ttud-panel-head"><div><div class="ttud-kicker">Access guide</div><h2>Officer permissions</h2></div><span class="ttud-panel-icon"><i class="feather-info"></i></span></header>
                    <div class="ttud-role-guide">@foreach($accessLevels as $value => $label)<div class="ttud-role"><strong>{{ $label }}</strong><span>{{ $roleDetails[$value] }}</span></div>@endforeach</div>
                </section>
            </aside>
        </section>

        <section id="account-audit" class="ttud-panel ttud-audit">
            <header class="ttud-panel-head"><div><div class="ttud-kicker">Account audit</div><h2>Recent user-management activity</h2><p>Who changed this account, what happened and when it occurred.</p></div><span class="ttud-panel-icon"><i class="feather-clock"></i></span></header>
            <div class="ttud-audit-list">
                @forelse($auditLogs as $event)
                    <article class="ttud-event"><span class="ttud-event-icon"><i class="{{ $eventIcons[$event->action] ?? 'feather-activity' }}"></i></span><div><strong>{{ $event->action_message ?: Str::headline($event->action) }}</strong><p>By {{ $event->user?->name ?: 'System' }} &middot; {{ $event->ip_address ?: 'IP not recorded' }}</p></div><time datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->format('d M Y, H:i') }}</time></article>
                @empty
                    <div class="ttud-empty">No dedicated Think Tank user-management events have been recorded for this account yet.</div>
                @endforelse
            </div>
        </section>
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
