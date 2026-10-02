@extends('layouts.app')
@section('title', 'Think Tank User — '.$user->name)

@push('styles')
<style>
    .ttud{display:grid;gap:15px;padding-bottom:30px;color:#172b23}.ttud-hero{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:22px;padding:24px 27px;border-radius:15px;background:linear-gradient(120deg,#102f27,#0d5b48 70%,#18775d);color:#fff;box-shadow:0 13px 31px rgba(13,62,49,.16)}.ttud-hero:after{position:absolute;right:-55px;bottom:-115px;width:235px;height:235px;border:38px solid rgba(255,255,255,.055);border-radius:50%;content:""}.ttud-hero-main,.ttud-hero-actions{position:relative;z-index:1}.ttud-back{display:inline-flex;align-items:center;gap:5px;color:#c6e6da;font-size:9px;font-weight:850;text-decoration:none}.ttud-back:hover{color:#fff}.ttud-identity{display:flex;align-items:center;gap:13px;margin-top:13px}.ttud-avatar{display:grid;width:54px;height:54px;flex:0 0 54px;place-items:center;border:1px solid rgba(255,255,255,.26);border-radius:13px;background:rgba(255,255,255,.12);font-size:14px;font-weight:900}.ttud-identity h1{margin:0;color:#fff;font-size:clamp(1.35rem,2.5vw,1.9rem);font-weight:900}.ttud-identity p{margin:4px 0 0;color:#d2e9e0;font-size:10px}.ttud-hero-actions{display:flex;flex-wrap:wrap;gap:7px}.ttud-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:38px;padding:8px 13px;border:1px solid #c8d8d1;border-radius:8px;background:#fff;color:#234a3b;font-size:10px;font-weight:900;text-decoration:none}.ttud-btn:hover{border-color:#7bac98;color:#0e644a}.ttud-btn.primary{border-color:#0f766e;background:#0f766e;color:#fff}.ttud-btn.light{border-color:rgba(255,255,255,.26);background:rgba(255,255,255,.11);color:#fff}.ttud-btn.warn{border-color:#e5c779;background:#fff9e9;color:#7d5a08}.ttud-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.ttud-metric{display:flex;align-items:center;gap:10px;padding:13px 14px;border:1px solid #dce6e1;border-radius:10px;background:#fff}.ttud-metric>span{display:grid;width:34px;height:34px;flex:0 0 34px;place-items:center;border-radius:9px;background:#e8f4ef;color:#17684f}.ttud-metric small,.ttud-metric strong{display:block}.ttud-metric small{color:#7c8b84;font-size:7px;font-weight:900;text-transform:uppercase}.ttud-metric strong{overflow:hidden;margin-top:2px;color:#28473a;font-size:9px;font-weight:900;text-overflow:ellipsis;white-space:nowrap}.ttud-alert{display:flex;align-items:flex-start;gap:9px;padding:12px 14px;border:1px solid #badbca;border-radius:10px;background:#f2faf6;color:#245e47}.ttud-alert.error{border-color:#ecbaba;background:#fff7f7;color:#963535}.ttud-alert.password{border-color:#e4ca83;background:#fffbeb;color:#665015}.ttud-alert strong{display:block;font-size:10px}.ttud-alert p{margin:2px 0 0;font-size:9px}.ttud-password{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin-top:8px}.ttud-password code{padding:7px 9px;border:1px solid #dec477;border-radius:7px;background:#fff;color:#443606;font-size:11px;user-select:all}.ttud-grid{display:grid;grid-template-columns:minmax(0,1fr) 315px;gap:13px;align-items:start}.ttud-panel{border:1px solid #dce6e1;border-radius:12px;background:#fff;box-shadow:0 5px 17px rgba(18,47,38,.04)}.ttud-panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:15px 17px;border-bottom:1px solid #e4ebe8}.ttud-panel-head h2{margin:2px 0 3px;color:#19392d;font-size:14px;font-weight:900}.ttud-panel-head p{margin:0;color:#718078;font-size:9px;line-height:1.5}.ttud-kicker{color:#0f766e;font-size:8px;font-weight:900;letter-spacing:.07em;text-transform:uppercase}.ttud-panel-icon{display:grid;width:35px;height:35px;flex:0 0 35px;place-items:center;border-radius:9px;background:#e8f4ef;color:#17694f}.ttud-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:17px}.ttud-field label{display:block;margin-bottom:4px;color:#5e7168;font-size:8px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.ttud-field input,.ttud-field select{width:100%;min-height:42px;border:1px solid #cad8d1;border-radius:8px;background:#fff;padding:8px 10px;color:#29473b;font-size:10px}.ttud-field input:focus,.ttud-field select:focus{outline:0;border-color:#5a9f86;box-shadow:0 0 0 3px rgba(15,118,110,.1)}.ttud-field small{display:block;margin-top:4px;color:#849189;font-size:7px}.ttud-error{display:block;margin-top:4px;color:#a53838;font-size:8px}.ttud-form-footer{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:4px}.ttud-form-footer p{margin:0;color:#7b8982;font-size:8px}.ttud-side{display:grid;gap:12px}.ttud-security-body{display:grid;gap:11px;padding:16px}.ttud-security-item{display:flex;gap:9px;padding:10px;border:1px solid #e0e8e4;border-radius:9px;background:#fafcfb}.ttud-security-item>span{display:grid;width:29px;height:29px;flex:0 0 29px;place-items:center;border-radius:7px;background:#eaf3ef;color:#276b52}.ttud-security-item strong,.ttud-security-item small{display:block}.ttud-security-item strong{color:#315044;font-size:9px}.ttud-security-item small{margin-top:2px;color:#7a8881;font-size:7px;line-height:1.45}.ttud-reset{padding-top:2px}.ttud-reset .ttud-btn{width:100%}.ttud-role-guide{display:grid;gap:7px;padding:15px}.ttud-role{padding:9px;border:1px solid #e0e8e4;border-radius:8px;background:#fafcfb}.ttud-role strong,.ttud-role span{display:block}.ttud-role strong{color:#315044;font-size:9px}.ttud-role span{margin-top:2px;color:#7a8881;font-size:7px;line-height:1.45}.ttud-audit{overflow:hidden}.ttud-audit-list{display:grid}.ttud-event{display:grid;grid-template-columns:31px minmax(0,1fr) auto;gap:10px;padding:12px 16px;border-bottom:1px solid #e7edea}.ttud-event:last-child{border-bottom:0}.ttud-event-icon{display:grid;width:30px;height:30px;place-items:center;border-radius:8px;background:#e9f3ee;color:#236a50}.ttud-event strong{display:block;color:#2b493d;font-size:9px}.ttud-event p{margin:2px 0 0;color:#78877f;font-size:8px}.ttud-event time{color:#85928c;font-size:7px;white-space:nowrap}.ttud-empty{padding:30px;text-align:center;color:#7b8982;font-size:9px}
    @media(max-width:950px){.ttud-grid{grid-template-columns:1fr}.ttud-side{grid-template-columns:repeat(2,minmax(0,1fr))}.ttud-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:650px){.ttud-hero{align-items:flex-start;flex-direction:column}.ttud-hero-actions,.ttud-hero-actions .ttud-btn{width:100%}.ttud-form,.ttud-side{grid-template-columns:1fr}.ttud-form-footer{align-items:stretch;flex-direction:column}.ttud-form-footer .ttud-btn{width:100%}.ttud-event{grid-template-columns:31px 1fr}.ttud-event time{grid-column:2}.ttud-metrics{grid-template-columns:1fr}}

    /* Readable account-detail treatment */
    .ttud{gap:18px;padding-bottom:40px}.ttud-hero{padding:30px 32px;border-radius:19px;background:radial-gradient(circle at 88% 14%,rgba(255,255,255,.13),transparent 28%),linear-gradient(125deg,#102f27,#0d5b48 62%,#168064);box-shadow:0 18px 42px rgba(13,62,49,.17)}
    .ttud-back{gap:7px;font-size:13px}.ttud-identity{gap:16px;margin-top:17px}.ttud-avatar{width:65px;height:65px;flex-basis:65px;border-radius:17px;font-size:19px}.ttud-identity h1{font-size:clamp(1.55rem,2.8vw,2.2rem);letter-spacing:-.025em}.ttud-identity p{margin-top:7px;font-size:13px}.ttud-hero-actions{gap:9px}.ttud-btn{min-height:43px;padding:10px 15px;border-radius:10px;font-size:13px;transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}.ttud-btn:hover{transform:translateY(-1px);text-decoration:none}.ttud-btn:disabled{cursor:not-allowed;opacity:.52;transform:none}.ttud-btn:focus-visible,.ttud-back:focus-visible,.ttud-dialog-close:focus-visible{outline:3px solid rgba(62,183,142,.3);outline-offset:2px}.ttud-btn.hero-reset{border-color:#fff;background:#fff;color:#155d48;box-shadow:0 9px 22px rgba(5,38,29,.18)}.ttud-btn.hero-reset:hover{color:#0a4937}
    .ttud-metrics{gap:12px}.ttud-metric{gap:12px;padding:16px;border-radius:13px;box-shadow:0 7px 20px rgba(18,55,43,.045)}.ttud-metric>span{width:42px;height:42px;flex-basis:42px;border-radius:11px;font-size:17px}.ttud-metric small{font-size:10px;letter-spacing:.06em}.ttud-metric strong{margin-top:3px;font-size:13px}.ttud-alert{gap:12px;padding:15px 17px;border-radius:12px}.ttud-alert>i{margin-top:2px;font-size:18px}.ttud-alert strong{font-size:14px}.ttud-alert p,.ttud-alert ul{font-size:13px;line-height:1.55}.ttud-alert ul{margin:6px 0 0;padding-left:18px}
    .ttud-grid{grid-template-columns:minmax(0,1fr) 365px;gap:16px}.ttud-panel{overflow:hidden;border-radius:15px;box-shadow:0 8px 24px rgba(18,55,43,.05)}.ttud-panel-head{gap:16px;padding:20px 22px}.ttud-panel-head h2{margin:4px 0 5px;font-size:18px}.ttud-panel-head p{font-size:13px;line-height:1.55}.ttud-kicker{font-size:10px;letter-spacing:.09em}.ttud-panel-icon{width:42px;height:42px;flex-basis:42px;border-radius:11px;font-size:17px}.ttud-form{gap:18px;padding:22px}.ttud-field label{margin-bottom:7px;font-size:12px;letter-spacing:0;text-transform:none}.ttud-field input,.ttud-field select{min-height:45px;padding:10px 12px;border-radius:10px;font-size:13px}.ttud-field small,.ttud-error{margin-top:6px;font-size:11px;line-height:1.45}.ttud-form-footer{gap:20px;padding-top:4px}.ttud-form-footer p{max-width:540px;font-size:12px;line-height:1.55}.ttud-side{gap:16px}
    .ttud-security-panel{border-color:#e3d6b6}.ttud-security-hero{display:flex;align-items:center;gap:13px;padding:20px 20px 0;background:linear-gradient(150deg,#fffdf5,#fff)}.ttud-security-hero h2{margin:3px 0 0;color:#4b3b16;font-size:18px;font-weight:900}.ttud-security-icon{display:grid;width:45px;height:45px;flex:0 0 45px;place-items:center;border-radius:12px;background:#fff2d3;color:#8a6209;font-size:18px}.ttud-security-body{gap:13px;padding:16px 20px 20px;background:linear-gradient(150deg,#fffdf5,#fff 70%)}.ttud-security-lead{margin:0;color:#665f50;font-size:13px;line-height:1.6}.ttud-reset-destination{display:flex;align-items:center;gap:10px;padding:12px;border:1px solid #eadab2;border-radius:10px;background:rgba(255,255,255,.86)}.ttud-reset-destination>i{color:#9a710f}.ttud-reset-destination div{min-width:0}.ttud-reset-destination small,.ttud-reset-destination strong{display:block}.ttud-reset-destination small{color:#85775a;font-size:10px;font-weight:850;letter-spacing:.045em;text-transform:uppercase}.ttud-reset-destination strong{overflow-wrap:anywhere;margin-top:2px;color:#4b401f;font-size:13px}.ttud-security-checks{display:grid;gap:9px;margin:0;padding:0;list-style:none}.ttud-security-checks li{display:flex;align-items:flex-start;gap:8px;color:#665f50;font-size:12px;line-height:1.48}.ttud-security-checks i{margin-top:2px;color:#18805f}.ttud-security-item{gap:10px;padding:12px;border-radius:10px}.ttud-security-item>span{width:34px;height:34px;flex-basis:34px}.ttud-security-item strong{font-size:12px}.ttud-security-item small{margin-top:3px;font-size:11px}.ttud-reset .ttud-btn{width:100%}.ttud-security-footnote{margin:0;color:#7d7159;font-size:11px;line-height:1.5;text-align:center}.ttud-security-warning{display:flex;gap:7px;margin:0;padding:10px;border-radius:9px;background:#fff1ed;color:#924534;font-size:11px;line-height:1.45}
    .ttud-role-guide{gap:9px;padding:17px}.ttud-role{padding:12px;border-radius:10px}.ttud-role.current{border-color:#a8d3c2;background:#f0f9f5;box-shadow:inset 3px 0 0 #0f766e}.ttud-role-title{display:flex;align-items:center;justify-content:space-between;gap:8px}.ttud-role-title em{padding:3px 6px;border-radius:999px;background:#d9efe5;color:#126148;font-size:8px;font-style:normal;font-weight:900;letter-spacing:.055em;text-transform:uppercase}.ttud-role strong{font-size:12px}.ttud-role span{margin-top:4px;font-size:11px;line-height:1.5}.ttud-event{grid-template-columns:38px minmax(0,1fr) auto;gap:13px;padding:16px 21px}.ttud-event-icon{width:38px;height:38px;border-radius:10px}.ttud-event strong{font-size:13px;line-height:1.45}.ttud-event p,.ttud-event time{font-size:11px;line-height:1.5}.ttud-empty{padding:38px 24px;font-size:13px}
    .ttud-dialog{width:min(92vw,530px);max-width:530px;padding:0;border:0;border-radius:17px;background:#fff;color:#15372d;box-shadow:0 28px 80px rgba(7,35,27,.3)}.ttud-dialog::backdrop{background:rgba(7,29,23,.66);backdrop-filter:blur(3px)}.ttud-dialog-head{position:relative;padding:24px 60px 18px 24px;border-bottom:1px solid #e4ece8;background:linear-gradient(145deg,#f8fcfa,#fff)}.ttud-dialog-icon{display:grid;width:45px;height:45px;margin-bottom:14px;place-items:center;border-radius:12px;background:#fff2d3;color:#8a6209;font-size:19px}.ttud-dialog-head h2{margin:0;color:#17392e;font-size:20px;font-weight:900}.ttud-dialog-head p{margin:7px 0 0;color:#687b73;font-size:13px;line-height:1.55}.ttud-dialog-close{position:absolute;top:17px;right:17px;display:grid;width:36px;height:36px;place-items:center;border:1px solid #d7e2dd;border-radius:9px;background:#fff;color:#567066;cursor:pointer;font-size:19px}.ttud-dialog-body{display:grid;gap:13px;padding:20px 24px}.ttud-confirm-user{display:flex;align-items:center;gap:12px;padding:13px;border:1px solid #dce8e3;border-radius:11px;background:#f7faf9}.ttud-confirm-user>span{display:grid;width:43px;height:43px;flex:0 0 43px;place-items:center;border-radius:11px;background:#dfeee8;color:#175e48;font-size:13px;font-weight:900}.ttud-confirm-user div{min-width:0}.ttud-confirm-user strong,.ttud-confirm-user small{display:block;overflow-wrap:anywhere}.ttud-confirm-user strong{font-size:13px}.ttud-confirm-user small{margin-top:2px;color:#6e7f77;font-size:12px}.ttud-dialog-warning{display:flex;gap:9px;padding:11px;border-radius:9px;background:#fff8e7;color:#6d551a;font-size:12px;line-height:1.5}.ttud-dialog-warning i{margin-top:2px}.ttud-dialog-actions{display:flex;justify-content:flex-end;gap:9px;padding:17px 24px 22px;border-top:1px solid #e7eeea}
    @media(max-width:1050px){.ttud-grid{grid-template-columns:1fr}.ttud-side{grid-template-columns:repeat(2,minmax(0,1fr))}.ttud-security-panel{grid-column:1/-1}}
    @media(max-width:650px){.ttud-hero{padding:22px 18px}.ttud-hero-actions{width:100%}.ttud-hero-actions .ttud-btn{width:100%}.ttud-form,.ttud-panel-head,.ttud-dialog-body{padding-left:17px;padding-right:17px}.ttud-side{grid-template-columns:1fr}.ttud-security-panel{grid-column:auto}.ttud-event{grid-template-columns:38px 1fr;padding:15px 17px}.ttud-dialog-actions{align-items:stretch;flex-direction:column-reverse}.ttud-dialog-actions .ttud-btn{width:100%}}
    @media(prefers-reduced-motion:reduce){.ttud-btn{transition:none}.ttud-btn:hover{transform:none}}
</style>
@endpush

@section('content')
@php
    $assignedMembership = $user->assignedThinkTankMembership;
    $membership = $assignedMembership ?: $user->thinkTankMembership;
    $level = $user->resolvedThinkTankAccessLevel();
    $isDisabled = $user->hasActiveLoginBlock();
    $normalizedEmail = mb_strtolower(trim((string) $user->email));
    $emailIsValid = $normalizedEmail !== ''
        && filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) !== false
        && hash_equals($normalizedEmail, (string) $user->email);
    $canSendReset = $emailIsValid && filled($assignedMembership?->id) && ! $user->is_blacklisted;
    $resetDisabledReason = $user->is_blacklisted
        ? 'This account is blacklisted. Remove the blacklist through the authorized access-review process before sending a reset.'
        : (! $emailIsValid
            ? 'Save a valid, lowercase email address with no leading or trailing spaces before sending a reset.'
            : (! filled($assignedMembership?->id)
                ? 'Resolve this user’s direct Think Tank assignment before sending a reset.'
                : null));
    $initials = collect(preg_split('/\s+/', trim((string) $user->name)) ?: [])->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('') ?: 'TT';
    $roleDetails = [
        \App\Models\User::THINK_TANK_ACCESS_ADMIN => 'Full portal access, including administration of the Think Tank team.',
        \App\Models\User::THINK_TANK_ACCESS_PROCUREMENT => 'Procurement plans, items, documents, evaluation and execution access.',
        \App\Models\User::THINK_TANK_ACCESS_ME => 'M&E data collection, indicators and performance-reporting access.',
        \App\Models\User::THINK_TANK_ACCESS_FINANCE => 'Finance and budget management for Think Tank spending and disbursements.',
        \App\Models\User::THINK_TANK_ACCESS_EVALUATOR => 'Assigned procurement evaluations only; no plan, finance, M&E, or user administration access.',
    ];
    $eventIcons = [
        'think_tank_user_created' => 'feather-user-plus',
        'think_tank_user_updated' => 'feather-edit-3',
        'think_tank_user_password_reset' => 'feather-key',
        'think_tank_user_password_reset_requested' => 'feather-mail',
        'think_tank_user_password_reset_initiated' => 'feather-shield',
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
                <a class="ttud-btn hero-reset" href="#password-reset" data-reset-open><i class="feather-send"></i> Email reset link</a>
            </div>
        </section>

        <section class="ttud-metrics" aria-label="User account summary">
            <article class="ttud-metric"><span><i class="feather-briefcase"></i></span><div><small>Think Tank</small><strong>{{ $membership?->name ?: 'Not assigned' }}</strong></div></article>
            <article class="ttud-metric"><span><i class="feather-shield"></i></span><div><small>Portal role</small><strong>{{ $user->thinkTankAccessLabel() }}</strong></div></article>
            <article class="ttud-metric"><span><i class="{{ $isDisabled ? 'feather-user-x' : 'feather-user-check' }}"></i></span><div><small>Account status</small><strong>{{ $isDisabled ? 'Disabled' : 'Active' }}</strong></div></article>
            <article class="ttud-metric"><span><i class="feather-calendar"></i></span><div><small>Account created</small><strong>{{ $user->created_at?->format('d M Y') ?: 'Not recorded' }}</strong></div></article>
        </section>

        @if(session('success'))<div class="ttud-alert" role="status" aria-live="polite"><i class="feather-check-circle"></i><div><strong>Action completed</strong><p>{{ session('success') }}</p></div></div>@endif
        @if(session('error'))<div class="ttud-alert error" role="alert"><i class="feather-alert-circle"></i><div><strong>The reset was not completed</strong><p>{{ session('error') }}</p></div></div>@endif
        @if($errors->any())<div class="ttud-alert error" role="alert"><i class="feather-alert-circle"></i><div><strong>Please review the highlighted information</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div></div>@endif
        <section class="ttud-grid">
            <div id="edit-account" class="ttud-panel">
                <header class="ttud-panel-head"><div><div class="ttud-kicker">Account details</div><h2>View and edit user information</h2><p>Update the user’s identity, email address, organization, portal role and login status.</p></div><span class="ttud-panel-icon"><i class="feather-edit"></i></span></header>
                <form class="ttud-form" method="POST" action="{{ route('system.think-tank-users.update', $user) }}">
                    @csrf
                    @method('PUT')
                    <div class="ttud-field"><label for="user-name">Full name</label><input id="user-name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" autocomplete="name" required>@error('name')<span class="ttud-error">{{ $message }}</span>@enderror</div>
                    <div class="ttud-field"><label for="user-email">Email address</label><input id="user-email" type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email" required><small>This becomes the user’s portal login email and reset-email destination.</small>@error('email')<span class="ttud-error">{{ $message }}</span>@enderror</div>
                    <div class="ttud-field"><label for="user-member">Think Tank</label><select id="user-member" name="think_tank_member_id" required>@foreach($members as $member)<option value="{{ $member->id }}" @selected((string) old('think_tank_member_id', $membership?->id) === (string) $member->id)>{{ $member->name }}{{ $member->country ? ' — '.$member->country : '' }}</option>@endforeach</select><small>Controls which organization’s records this user can access.</small>@error('think_tank_member_id')<span class="ttud-error">{{ $message }}</span>@enderror</div>
                    <div class="ttud-field"><label for="user-access">Portal role</label><select id="user-access" name="access_level" required>@if($level && !array_key_exists($level, $accessLevels))<option value="{{ $level }}" selected>{{ $allAccessLevels[$level] ?? Str::headline($level) }} (existing)</option>@endif @foreach($accessLevels as $value => $label)<option value="{{ $value }}" @selected(old('access_level', $level) === $value)>{{ $label }}</option>@endforeach</select><small>Defines the modules and actions available to this officer.</small>@error('access_level')<span class="ttud-error">{{ $message }}</span>@enderror</div>
                    <div class="ttud-field"><label for="user-status">Login status</label><select id="user-status" name="account_status"><option value="active" @selected(old('account_status', $isDisabled ? 'disabled' : 'active') === 'active')>Active — login allowed</option><option value="disabled" @selected(old('account_status', $isDisabled ? 'disabled' : 'active') === 'disabled')>Disabled — login blocked</option></select><small>Disabling preserves records but immediately blocks portal access.</small>@error('account_status')<span class="ttud-error">{{ $message }}</span>@enderror</div>
                    <div class="ttud-field"><label>Account identifier</label><input value="{{ $user->id }}" readonly><small>Permanent system identifier; it cannot be changed.</small></div>
                    <div class="ttud-form-footer"><p>Saving creates an audit entry. If this administrator changes role, organization or status, primary access is reassigned automatically when another administrator exists.</p><button class="ttud-btn primary" type="submit"><i class="feather-save"></i> Save account changes</button></div>
                </form>
            </div>

            <aside class="ttud-side">
                <section class="ttud-panel ttud-security-panel" id="password-reset" aria-labelledby="password-reset-heading">
                    <div class="ttud-security-hero">
                        <span class="ttud-security-icon"><i class="feather-mail"></i></span>
                        <div><div class="ttud-kicker">Secure recovery</div><h2 id="password-reset-heading">Email password reset</h2></div>
                    </div>
                    <div class="ttud-security-body">
                        <p class="ttud-security-lead">Revoke access and send reset link directly to this user’s account email. The user chooses a new private password through a secure, single-use link.</p>
                        <div class="ttud-reset-destination">
                            <i class="feather-at-sign"></i>
                            <div><small>Reset email will be sent to</small><strong>{{ $emailIsValid ? $user->email : 'No valid email address' }}</strong></div>
                        </div>
                        <ul class="ttud-security-checks">
                            <li><i class="feather-check"></i><span>No plaintext password or reset token is displayed on this page; the single-use token stays inside the secure emailed link.</span></li>
                            <li><i class="feather-check"></i><span>The current password, MFA challenge and active sessions are revoked only after email delivery is accepted.</span></li>
                            <li><i class="feather-check"></i><span>The emailed reset link is single-use and expires automatically.</span></li>
                        </ul>
                        <div class="ttud-security-item"><span><i class="feather-key"></i></span><div><strong>{{ $user->must_change_password ? 'Password change required' : 'Password established' }}</strong><small>{{ $user->password_changed_at ? 'Last changed '.$user->password_changed_at->diffForHumans() : 'No password-change date is recorded.' }}</small></div></div>
                        <form class="ttud-reset" method="POST" action="{{ route('system.think-tank-users.reset-password', $user) }}" data-reset-form onsubmit="return confirm('Email this user a secure password reset link? Current access will be revoked after delivery is accepted.')">
                            @csrf
                            <button class="ttud-btn warn" type="submit" data-reset-submit aria-label="Send secure reset link by email" @disabled(! $canSendReset)><i class="feather-send"></i><span>Email secure reset link</span></button>
                        </form>
                        @unless($canSendReset)<p class="ttud-security-warning"><i class="feather-alert-circle"></i> {{ $resetDisabledReason }}</p>@endunless
                        @if($isDisabled)<p class="ttud-security-warning"><i class="feather-user-x"></i> This account is disabled. Resetting the password will not restore login access until the account is re-enabled.</p>@endif
                        <p class="ttud-security-footnote">You will confirm the exact recipient before anything is sent. No plaintext password is displayed or sent.</p>
                    </div>
                </section>
                <section class="ttud-panel">
                    <header class="ttud-panel-head"><div><div class="ttud-kicker">Access guide</div><h2>Officer permissions</h2></div><span class="ttud-panel-icon"><i class="feather-info"></i></span></header>
                    <div class="ttud-role-guide">
                        @foreach($accessLevels as $value => $label)
                            <div class="ttud-role {{ $level === $value ? 'current' : '' }}"><div class="ttud-role-title"><strong>{{ $label }}</strong>@if($level === $value)<em>Current</em>@endif</div><span>{{ $roleDetails[$value] ?? 'Officer permissions and module access for this role.' }}</span></div>
                        @endforeach
                    </div>
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

@if($canSendReset)
    <dialog class="ttud-dialog" id="reset-email-dialog" aria-labelledby="reset-email-title" aria-describedby="reset-email-description">
        <header class="ttud-dialog-head">
            <span class="ttud-dialog-icon"><i class="feather-shield"></i></span>
            <button class="ttud-dialog-close" type="button" data-reset-close aria-label="Close reset confirmation"><i class="feather-x"></i></button>
            <h2 id="reset-email-title">Email a secure reset link?</h2>
            <p id="reset-email-description">Confirm the account and destination before starting password recovery.</p>
        </header>
        <div class="ttud-dialog-body">
            <div class="ttud-confirm-user"><span>{{ $initials }}</span><div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div></div>
            <div class="ttud-dialog-warning"><i class="feather-alert-triangle"></i><span>After the mail provider accepts delivery, the current password, MFA challenge and active sessions will be revoked. The user must use the emailed link to choose a new password.</span></div>
        </div>
        <footer class="ttud-dialog-actions">
            <button class="ttud-btn" type="button" data-reset-close>Keep account unchanged</button>
            <button class="ttud-btn primary" type="button" data-reset-confirm><i class="feather-send"></i><span>Email reset link</span></button>
        </footer>
    </dialog>
@endif
@endsection

@push('scripts')
@if($canSendReset)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-reset-form]');
        const dialog = document.getElementById('reset-email-dialog');
        const submit = form?.querySelector('[data-reset-submit]');
        const confirmButton = dialog?.querySelector('[data-reset-confirm]');
        if (!form || !dialog || !submit || !confirmButton) return;

        let opener = null;
        const openDialog = function (trigger) {
            opener = trigger || submit;
            if (typeof dialog.showModal === 'function') dialog.showModal();
            else dialog.setAttribute('open', '');
            window.setTimeout(function () { confirmButton.focus(); }, 40);
        };
        const closeDialog = function () {
            if (typeof dialog.close === 'function') dialog.close();
            else dialog.removeAttribute('open');
            opener?.focus();
        };

        form.removeAttribute('onsubmit');
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === 'true') {
                submit.disabled = true;
                submit.setAttribute('aria-busy', 'true');
                submit.querySelector('span').textContent = 'Sending secure link…';
                return;
            }
            event.preventDefault();
            openDialog(submit);
        });
        document.querySelectorAll('[data-reset-open]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                openDialog(trigger);
            });
        });
        dialog.querySelectorAll('[data-reset-close]').forEach(function (button) {
            button.addEventListener('click', closeDialog);
        });
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) closeDialog();
        });
        dialog.addEventListener('cancel', function () {
            window.setTimeout(function () { opener?.focus(); }, 0);
        });
        confirmButton.addEventListener('click', function () {
            form.dataset.confirmed = 'true';
            if (typeof dialog.close === 'function') dialog.close();
            else dialog.removeAttribute('open');
            if (typeof form.requestSubmit === 'function') form.requestSubmit(submit);
            else form.submit();
        });
    });
</script>
@endif
@endpush
