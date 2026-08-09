<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 13px 13px 42px; }
        body { margin: 0; color: #17343e; font-family: DejaVu Sans, Arial, sans-serif; font-size: 7px; line-height: 1.32; }
        .header { padding: 9px 11px; border-bottom: 4px solid #77adba; background: #075c7a; color: #fff; }
        .header-table,.meta-table,.summary-table,.visual-table,.register-table,.control-table,.footer-table { width: 100%; border-collapse: collapse; }
        .brand-cell { width: 118px; }
        .logo-wrap { display: block; width: 106px; height: 55px; overflow: hidden; border: 2px solid rgba(255,255,255,.45); background: #fff; }
        .logo-wrap img { width: 106px; height: 60px; }
        .title { margin: 0 0 2px; color: #fff; font-size: 17px; font-weight: 900; }
        .subtitle { color: #cce8ee; font-size: 7.5px; font-weight: 700; }
        .header-side { color: #d9edf1; font-size: 6.5px; text-align: right; }
        .section { margin-top: 8px; }
        .section-title { padding: 5px 7px; background: #075c7a; color: #fff; font-size: 7px; font-weight: 900; letter-spacing: .4px; text-transform: uppercase; }
        .section-note { padding: 4px 7px; border: 1px solid #d7e3e6; border-top: 0; background: #f7fafb; color: #647980; font-size: 5.8px; }
        .meta-table td,.summary-table td { padding: 5px 6px; border: 1px solid #d7e3e6; vertical-align: top; }
        .meta-table td { width: 25%; }
        .meta-label,.summary-label { display: block; color: #647980; font-size: 5.5px; font-weight: 900; text-transform: uppercase; }
        .meta-value { display: block; margin-top: 2px; color: #17343e; font-size: 6.6px; font-weight: 800; }
        .summary-table td { width: 16.666%; }
        .summary-value { display: block; margin-top: 2px; color: #075c7a; font-size: 10px; font-weight: 900; }
        .summary-meta { display: block; margin-top: 1px; color: #647980; font-size: 5.2px; }
        .privacy-note { margin-top: 6px; padding: 5px 7px; border: 1px solid #e5c891; background: #fff8e9; color: #755019; }
        .visual-table td { width: 33.333%; padding: 6px 7px; border: 1px solid #d7e3e6; vertical-align: top; }
        .visual-title { margin-bottom: 5px; color: #294850; font-size: 6.2px; font-weight: 900; text-transform: uppercase; }
        .bar-row { margin-bottom: 4px; white-space: nowrap; }
        .bar-label { display: inline-block; width: 77px; overflow: hidden; color: #647980; font-size: 5.2px; text-overflow: ellipsis; vertical-align: middle; }
        .bar-track { display: inline-block; width: 112px; height: 7px; background: #e8eff1; vertical-align: middle; }
        .bar-fill { display: block; height: 7px; background: #075c7a; }
        .bar-value { display: inline-block; width: 20px; color: #294850; font-size: 5.5px; font-weight: 900; text-align: right; }
        .register-table,.control-table { table-layout: fixed; page-break-inside: auto; }
        .register-table thead,.control-table thead { display: table-header-group; }
        .register-table tr,.control-table tr { page-break-inside: avoid; }
        .register-table th,.control-table th { padding: 4px 3px; border: 1px solid #075c7a; background: #075c7a; color: #fff; font-size: 5px; font-weight: 900; text-align: left; text-transform: uppercase; vertical-align: middle; }
        .register-table td,.control-table td { padding: 4px 3px; border: 1px solid #d7e3e6; color: #294750; font-size: 5.25px; overflow-wrap: anywhere; vertical-align: top; }
        .register-table tbody tr:nth-child(even) td,.control-table tbody tr:nth-child(even) td { background: #f8fafb; }
        .organization-col { width: 17%; }.contact-col { width: 17%; }.account-col { width: 17%; }.readiness-col { width: 13%; }.authority-col { width: 13%; }.source-col { width: 10%; }.notes-col { width: 13%; }
        .strong { color: #17343e; font-weight: 900; }.muted { display: block; margin-top: 1px; color: #6b7e85; font-size: 4.8px; }
        .status { font-weight: 900; text-transform: uppercase; }.success { color: #176348; }.warning { color: #8a5a13; }.danger { color: #993f3d; }.neutral { color: #61757c; }.info { color: #17667e; }
        .check { font-weight: 900; }.check-pass { color: #176348; }.check-fail { color: #993f3d; }
        .page-break { page-break-before: always; }
        .empty { padding: 18px!important; color: #647980!important; text-align: center; }
        .footer { position: fixed; right: 0; bottom: -32px; left: 0; padding: 6px 10px 5px; border-top: 3px solid #77adba; background: #05465d; color: #d7ebef; font-size: 5.8px; }
        .footer-table td { width: 33.333%; border: 0; padding: 0; vertical-align: middle; }.footer-brand { color: #fff; font-weight: 900; }.footer-context { color: #cde9ef; text-align: center; }.footer-page { color: #fff; font-weight: 800; text-align: right; }.page-number:after { content: "Page " counter(page) " of " counter(pages); }
    </style>
</head>
<body>
@php
    $maxReadiness = max(1, (int) collect($charts['readiness'])->max('count'));
    $maxConsortium = max(1, (int) collect($charts['consortia'])->max('contacts'));
    $maxCountry = max(1, (int) collect($charts['countries'])->max('contacts'));
    $yesNo = static fn ($condition): string => $condition ? 'PASS' : 'ACTION';
@endphp
<header class="header">
    <table class="header-table"><tr>
        <td class="brand-cell"><span class="logo-wrap"><img src="{{ public_path('assets/images/attp-logo.jpeg') }}" alt="ATTP"></span></td>
        <td><h1 class="title">M&amp;E Focal Unit Responsibility Register</h1><div class="subtitle">Africa Think Tank Platform | Account readiness, organizational authority and reporting-access control</div></td>
        <td class="header-side"><strong>{{ $isIndividual ? 'INDIVIDUAL CONTROL SHEET' : 'CONSOLIDATED REGISTER' }}</strong><br>Generated {{ $generatedAt->format('d M Y, H:i') }}<br>{{ $generatedAt->timezoneName }}</td>
    </tr></table>
</header>

<section class="section">
    <div class="section-title">Report context and control</div>
    <table class="meta-table"><tr>
        <td><span class="meta-label">Reporting scope</span><span class="meta-value">{{ $scopeLabel }}</span></td>
        <td><span class="meta-label">Prepared by</span><span class="meta-value">{{ $generatedBy->name }}</span><span class="muted">{{ $generatedBy->email }}</span></td>
        <td><span class="meta-label">Register reference</span><span class="meta-value">ATTP-MEL-FU-{{ $generatedAt->format('Ymd-Hi') }}</span></td>
        <td><span class="meta-label">Classification</span><span class="meta-value">Internal operational use</span><span class="muted">Contains business contact and access-control data</span></td>
    </tr></table>
    <div class="privacy-note"><strong>Controlled distribution:</strong> use this report to verify reporting responsibility and access readiness. Do not publish focal-person email addresses or account-control information outside authorized ATTP operations.</div>
</section>

<section class="section">
    <div class="section-title">Executive readiness summary</div>
    <table class="summary-table"><tr>
        <td><span class="summary-label">Organizations mapped</span><span class="summary-value">{{ number_format($metrics['mapped_organizations']) }} / {{ number_format($metrics['organization_target']) }}</span><span class="summary-meta">Active organization target</span></td>
        <td><span class="summary-label">Ready organizations</span><span class="summary-value">{{ number_format($metrics['ready_organizations']) }}</span><span class="summary-meta">{{ number_format($metrics['readiness_rate'], 1) }}% readiness</span></td>
        <td><span class="summary-label">Contacts in scope</span><span class="summary-value">{{ number_format($metrics['contacts']) }}</span><span class="summary-meta">{{ number_format($metrics['primary_contacts']) }} primary</span></td>
        <td><span class="summary-label">Account coverage</span><span class="summary-value">{{ number_format($metrics['account_coverage'], 1) }}%</span><span class="summary-meta">{{ number_format($metrics['account_matches']) }} exact email matches</span></td>
        <td><span class="summary-label">Links required</span><span class="summary-value">{{ number_format($metrics['link_required']) }}</span><span class="summary-meta">Matching accounts not formally linked</span></td>
        <td><span class="summary-label">Access attention</span><span class="summary-value">{{ number_format($metrics['disabled'] + $metrics['blacklisted']) }}</span><span class="summary-meta">Disabled or blacklisted</span></td>
    </tr></table>
</section>

<section class="section">
    <div class="section-title">Coverage profile</div>
    <table class="visual-table"><tr>
        <td><div class="visual-title">Readiness distribution</div>@forelse($charts['readiness'] as $row)<div class="bar-row"><span class="bar-label">{{ $row['label'] }}</span><span class="bar-track"><span class="bar-fill" style="width:{{ round(($row['count'] / $maxReadiness) * 100, 1) }}%;background:{{ $row['color'] }}"></span></span><span class="bar-value">{{ $row['count'] }}</span></div>@empty<span class="muted">No records in this scope.</span>@endforelse</td>
        <td><div class="visual-title">Contacts by consortium</div>@forelse($charts['consortia'] as $row)<div class="bar-row"><span class="bar-label">{{ $row['label'] }}</span><span class="bar-track"><span class="bar-fill" style="width:{{ round(($row['contacts'] / $maxConsortium) * 100, 1) }}%"></span></span><span class="bar-value">{{ $row['contacts'] }}</span></div>@empty<span class="muted">No consortium data in this scope.</span>@endforelse</td>
        <td><div class="visual-title">Contacts by country</div>@forelse($charts['countries'] as $row)<div class="bar-row"><span class="bar-label">{{ $row['label'] }}</span><span class="bar-track"><span class="bar-fill" style="width:{{ round(($row['contacts'] / $maxCountry) * 100, 1) }}%;background:#3f8aa0"></span></span><span class="bar-value">{{ $row['contacts'] }}</span></div>@empty<span class="muted">Country data depends on organization mapping.</span>@endforelse</td>
    </tr></table>
</section>

<section class="section">
    <div class="section-title">Detailed focal responsibility register</div>
    <div class="section-note">Readiness is calculated from the contact state, organization mapping, exact email account match, formal register link, M&amp;E role and account health.</div>
    <table class="register-table"><thead><tr><th class="organization-col">Consortium / organization</th><th class="contact-col">Focal contact</th><th class="account-col">Platform account</th><th class="readiness-col">Readiness</th><th class="authority-col">Responsibility</th><th class="source-col">Source / updated</th><th class="notes-col">Notes</th></tr></thead><tbody>
    @forelse($contacts as $contact)
        @php
            $account = $contact->resolvedAccount;
        @endphp
        <tr>
            <td><span class="strong">{{ $contact->think_tank_label }}</span><span class="muted">{{ $contact->thinkTank?->name ?: 'Organization mapping required' }}</span><span class="muted">{{ $contact->consortium_name }}@if($contact->thinkTank?->country) | {{ $contact->thinkTank->country }}@endif</span></td>
            <td><span class="strong">{{ $contact->focal_person_name }}</span><span class="muted">{{ $contact->email }}</span><span class="muted">{{ $contact->is_primary ? 'Primary organizational contact' : 'Supporting contact' }}</span></td>
            <td>@if($account)<span class="strong">{{ $account->name }}</span><span class="muted">{{ $account->email }}</span><span class="muted">{{ $account->thinkTankAccessLabel() }}</span>@else<span class="status danger">No exact account match</span>@endif</td>
            <td><span class="status {{ $contact->readiness_tone }}">{{ $contact->readiness_label }}</span><span class="muted">Register: {{ $contact->is_active ? 'Active' : 'Archived' }}</span></td>
            <td><span class="strong">{{ $contact->is_primary ? 'Primary' : 'Supporting' }}</span><span class="muted">{{ $contact->user_id ? 'Formally linked' : 'Not formally linked' }}</span></td>
            <td><span class="strong">{{ $contact->source ?: 'Platform maintained' }}</span><span class="muted">{{ $contact->updated_at?->format('d M Y, H:i') }}</span></td>
            <td>{{ $contact->notes ?: 'No additional responsibility notes.' }}</td>
        </tr>
    @empty
        <tr><td class="empty" colspan="7">No focal contacts match the selected report scope.</td></tr>
    @endforelse
    </tbody></table>
</section>

@if($contacts->isNotEmpty())
<section class="section page-break">
    <div class="section-title">Accountability and access-control checklist</div>
    <div class="section-note">ACTION denotes a missing or non-compliant control. Resolve account identity and organization assignment before permitting M&amp;E reporting activity.</div>
    <table class="control-table"><thead><tr><th style="width:19%">Organization / focal contact</th><th style="width:12%">Organization mapped</th><th style="width:13%">Exact account match</th><th style="width:12%">Formal register link</th><th style="width:13%">Think tank assignment</th><th style="width:12%">M&amp;E role</th><th style="width:11%">Login health</th><th style="width:8%">Overall</th></tr></thead><tbody>
    @foreach($contacts as $contact)
        @php
            $account = $contact->resolvedAccount;
            $mapped = filled($contact->think_tank_member_id);
            $matched = (bool) $account && strtolower((string) $account->email) === strtolower((string) $contact->email);
            $linked = $account && (string) $contact->user_id === (string) $account->id;
            $assigned = $account && $account->user_type === 'think_tank' && (string) $account->think_tank_member_id === (string) $contact->think_tank_member_id;
            $roleReady = $account && in_array($account->think_tank_access_level, [\App\Models\User::THINK_TANK_ACCESS_ADMIN, \App\Models\User::THINK_TANK_ACCESS_ME], true);
            $healthy = $account && ! $account->is_disabled && ! $account->is_blacklisted;
        @endphp
        <tr><td><span class="strong">{{ $contact->think_tank_label }}</span><span class="muted">{{ $contact->focal_person_name }} | {{ $contact->email }}</span></td>@foreach([$mapped,$matched,$linked,$assigned,$roleReady,$healthy] as $check)<td><span class="check {{ $check ? 'check-pass' : 'check-fail' }}">{{ $yesNo($check) }}</span></td>@endforeach<td><span class="status {{ $contact->readiness_tone }}">{{ $contact->readiness_key === 'ready' ? 'READY' : 'ACTION' }}</span></td></tr>
    @endforeach
    </tbody></table>
</section>
@endif

<footer class="footer"><table class="footer-table"><tr><td class="footer-brand">AFRICA THINK TANK PLATFORM</td><td class="footer-context">M&amp;E focal responsibility and account-control register</td><td class="footer-page"><span class="page-number"></span></td></tr></table></footer>
</body>
</html>
