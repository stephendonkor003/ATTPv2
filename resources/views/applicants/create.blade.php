@extends('layouts.public')

@section('title', 'ATTP Call for Proposals')
@section('description', 'Official Africa Think Tank Platform call for proposals, eligibility guidance and consortium application portal.')

@push('styles')
    <style>
        .cfp-page {
            --cfp-green-950: #073d2d;
            --cfp-green-900: #0a4c38;
            --cfp-green-700: #137052;
            --cfp-green-100: #e7f3ed;
            --cfp-gold: #d9aa3e;
            --cfp-gold-soft: #fbf2d9;
            --cfp-ink: #14251f;
            --cfp-muted: #5f6f68;
            --cfp-line: #dbe6e0;
            --cfp-surface: #ffffff;
            background: #f4f8f6;
            color: var(--cfp-ink);
        }

        .cfp-page *,
        .cfp-page *::before,
        .cfp-page *::after { box-sizing: border-box; }

        .cfp-shell {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
        }

        .cfp-hero {
            position: relative;
            overflow: hidden;
            padding: clamp(72px, 9vw, 118px) 0 92px;
            color: #fff;
            background:
                radial-gradient(circle at 88% 18%, rgba(217, 170, 62, .24), transparent 28%),
                radial-gradient(circle at 10% 90%, rgba(112, 190, 151, .18), transparent 30%),
                linear-gradient(130deg, #063729 0%, #0a503a 56%, #116b4e 100%);
        }

        .cfp-hero::before,
        .cfp-hero::after {
            position: absolute;
            content: '';
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 50%;
        }

        .cfp-hero::before { width: 360px; height: 360px; right: -120px; top: -160px; }
        .cfp-hero::after { width: 250px; height: 250px; right: 80px; bottom: -180px; }

        .cfp-hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 310px;
            align-items: end;
            gap: 56px;
        }

        .cfp-kicker {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 20px;
            color: #d8f4e7;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .cfp-kicker::before { width: 28px; height: 2px; content: ''; background: var(--cfp-gold); }

        .cfp-hero h1 {
            max-width: 830px;
            margin: 0;
            color: #fff;
            font-size: clamp(2.35rem, 5vw, 4.35rem);
            font-weight: 800;
            letter-spacing: -.045em;
            line-height: 1.02;
        }

        .cfp-hero-copy {
            max-width: 760px;
            margin: 24px 0 0;
            color: rgba(255, 255, 255, .78);
            font-size: clamp(1rem, 1.6vw, 1.16rem);
            line-height: 1.72;
        }

        .cfp-hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }

        .cfp-btn {
            display: inline-flex;
            min-height: 46px;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 0 20px;
            border: 1px solid transparent;
            border-radius: 9px;
            font-size: .88rem;
            font-weight: 800;
            text-decoration: none;
            transition: transform .2s ease, background-color .2s ease, border-color .2s ease;
        }

        .cfp-btn:hover { transform: translateY(-2px); }
        .cfp-btn-primary { color: #173023; background: var(--cfp-gold); }
        .cfp-btn-primary:hover { color: #173023; background: #e4b94f; }
        .cfp-btn-ghost { color: #fff; border-color: rgba(255, 255, 255, .38); background: rgba(255, 255, 255, .07); }
        .cfp-btn-ghost:hover { color: #fff; border-color: rgba(255, 255, 255, .7); background: rgba(255, 255, 255, .13); }

        .cfp-status-panel {
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 16px;
            background: rgba(5, 43, 31, .55);
            box-shadow: 0 24px 50px rgba(0, 0, 0, .15);
            backdrop-filter: blur(12px);
        }

        .cfp-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .cfp-status-badge.open { color: #d9fbe9; background: rgba(50, 168, 106, .28); }
        .cfp-status-badge.closed { color: #ffe6ac; background: rgba(207, 141, 25, .24); }
        .cfp-status-badge::before { width: 7px; height: 7px; content: ''; border-radius: 50%; background: currentColor; }
        .cfp-status-panel h2 { margin: 18px 0 8px; color: #fff; font-size: 1.2rem; font-weight: 800; }
        .cfp-status-panel p { margin: 0; color: rgba(255, 255, 255, .68); font-size: .88rem; line-height: 1.6; }
        .cfp-status-meta { display: grid; gap: 13px; margin-top: 22px; }
        .cfp-status-row { display: flex; align-items: flex-start; gap: 10px; color: rgba(255, 255, 255, .86); font-size: .81rem; }
        .cfp-status-row i { margin-top: 2px; color: var(--cfp-gold); }

        .cfp-facts {
            position: relative;
            z-index: 3;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-top: -38px;
            border: 1px solid var(--cfp-line);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 18px 45px rgba(20, 57, 42, .1);
        }

        .cfp-fact { min-width: 0; padding: 23px 25px; border-right: 1px solid var(--cfp-line); }
        .cfp-fact:last-child { border-right: 0; }
        .cfp-fact-label { display: block; margin-bottom: 7px; color: var(--cfp-muted); font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .cfp-fact strong { display: block; color: var(--cfp-green-950); font-size: 1rem; line-height: 1.4; }

        .cfp-main { padding: 76px 0 90px; }
        .cfp-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; align-items: start; gap: 50px; }
        .cfp-section + .cfp-section { margin-top: 70px; }
        .cfp-section-label { margin-bottom: 10px; color: var(--cfp-green-700); font-size: .72rem; font-weight: 800; letter-spacing: .11em; text-transform: uppercase; }
        .cfp-section h2 { max-width: 720px; margin: 0; color: var(--cfp-green-950); font-size: clamp(1.7rem, 3vw, 2.45rem); font-weight: 800; letter-spacing: -.03em; line-height: 1.16; }
        .cfp-lead { max-width: 760px; margin: 17px 0 0; color: var(--cfp-muted); font-size: 1rem; line-height: 1.78; }

        .cfp-theme-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 27px; }
        .cfp-theme {
            display: flex;
            min-height: 84px;
            align-items: center;
            gap: 13px;
            padding: 16px;
            border: 1px solid var(--cfp-line);
            border-radius: 10px;
            background: var(--cfp-surface);
            color: var(--cfp-ink);
            font-size: .84rem;
            font-weight: 750;
            line-height: 1.35;
        }

        .cfp-theme-icon { display: grid; width: 35px; height: 35px; flex: 0 0 35px; place-items: center; border-radius: 8px; color: var(--cfp-green-700); background: var(--cfp-green-100); }

        .cfp-process { display: grid; gap: 14px; margin-top: 27px; }
        .cfp-process-item { display: grid; grid-template-columns: 44px 1fr; gap: 15px; padding: 20px; border: 1px solid var(--cfp-line); border-radius: 11px; background: #fff; }
        .cfp-process-number { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 50%; color: #fff; background: var(--cfp-green-700); font-size: .78rem; font-weight: 800; }
        .cfp-process-item h3 { margin: 1px 0 6px; color: var(--cfp-green-950); font-size: .98rem; font-weight: 800; }
        .cfp-process-item p { margin: 0; color: var(--cfp-muted); font-size: .85rem; line-height: 1.65; }

        .cfp-accordion { display: grid; gap: 11px; margin-top: 27px; }
        .cfp-accordion details { overflow: hidden; border: 1px solid var(--cfp-line); border-radius: 11px; background: #fff; }
        .cfp-accordion summary { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 19px 20px; color: var(--cfp-green-950); cursor: pointer; list-style: none; font-size: .92rem; font-weight: 800; }
        .cfp-accordion summary::-webkit-details-marker { display: none; }
        .cfp-accordion summary::after { content: '+'; color: var(--cfp-green-700); font-size: 1.35rem; font-weight: 500; }
        .cfp-accordion details[open] summary { border-bottom: 1px solid var(--cfp-line); background: #f9fcfa; }
        .cfp-accordion details[open] summary::after { content: '−'; }
        .cfp-accordion-body { padding: 20px; color: var(--cfp-muted); font-size: .88rem; line-height: 1.7; }
        .cfp-accordion-body ul { display: grid; gap: 8px; margin: 0; padding-left: 19px; }
        .cfp-accordion-body strong { color: var(--cfp-ink); }

        .cfp-sidebar { position: sticky; top: 24px; display: grid; gap: 16px; }
        .cfp-side-card { padding: 22px; border: 1px solid var(--cfp-line); border-radius: 12px; background: #fff; }
        .cfp-side-card.accent { border-color: #ead494; background: var(--cfp-gold-soft); }
        .cfp-side-card h3 { margin: 0 0 10px; color: var(--cfp-green-950); font-size: .96rem; font-weight: 800; }
        .cfp-side-card p { margin: 0; color: var(--cfp-muted); font-size: .82rem; line-height: 1.65; }
        .cfp-side-card a { color: var(--cfp-green-700); font-weight: 800; }
        .cfp-side-links { display: grid; margin-top: 13px; }
        .cfp-side-links a { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 0; border-top: 1px solid rgba(9, 76, 55, .12); color: var(--cfp-green-900); font-size: .8rem; text-decoration: none; }

        .cfp-closed {
            display: grid;
            grid-template-columns: 52px 1fr;
            gap: 18px;
            margin-top: 28px;
            padding: 24px;
            border: 1px solid #e8cb7b;
            border-radius: 12px;
            background: var(--cfp-gold-soft);
        }

        .cfp-closed-icon { display: grid; width: 48px; height: 48px; place-items: center; border-radius: 11px; color: #7b5610; background: #f3d788; font-size: 1.15rem; }
        .cfp-closed h3 { margin: 1px 0 7px; color: #684a0c; font-size: 1.05rem; font-weight: 800; }
        .cfp-closed p { margin: 0; color: #765d25; font-size: .88rem; line-height: 1.65; }

        .cfp-error-summary { margin-bottom: 25px; padding: 20px; border: 1px solid #f1b3b3; border-radius: 11px; color: #792626; background: #fff2f2; }
        .cfp-error-summary strong { display: block; margin-bottom: 8px; }
        .cfp-error-summary ul { margin: 0; padding-left: 20px; }

        .cfp-application { margin-top: 30px; border: 1px solid var(--cfp-line); border-radius: 15px; background: #fff; box-shadow: 0 18px 45px rgba(20, 57, 42, .08); }
        .cfp-form-head { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 25px 28px; border-bottom: 1px solid var(--cfp-line); }
        .cfp-form-head h3 { margin: 0; color: var(--cfp-green-950); font-size: 1.15rem; font-weight: 800; }
        .cfp-form-head p { margin: 5px 0 0; color: var(--cfp-muted); font-size: .8rem; }
        .cfp-stepper { display: flex; gap: 8px; }
        .cfp-step { display: inline-flex; align-items: center; gap: 7px; padding: 7px 10px; border-radius: 999px; color: var(--cfp-muted); background: #edf2ef; font-size: .7rem; font-weight: 800; }
        .cfp-step.active { color: #fff; background: var(--cfp-green-700); }
        .cfp-stage { display: none; padding: 28px; }
        .cfp-stage.active { display: block; }
        .cfp-stage-title { margin-bottom: 22px; }
        .cfp-stage-title h4 { margin: 0 0 6px; color: var(--cfp-green-950); font-size: 1.05rem; font-weight: 800; }
        .cfp-stage-title p { margin: 0; color: var(--cfp-muted); font-size: .82rem; }
        .cfp-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .cfp-field.full { grid-column: 1 / -1; }
        .cfp-field label { display: block; margin-bottom: 7px; color: var(--cfp-ink); font-size: .78rem; font-weight: 800; }
        .cfp-required { color: #b42318; }
        .cfp-field .form-control,
        .cfp-field .form-select { min-height: 46px; border-color: #cfdcd5; border-radius: 8px; color: var(--cfp-ink); font-size: .86rem; box-shadow: none; }
        .cfp-field textarea.form-control { min-height: 98px; resize: vertical; }
        .cfp-field select[multiple] { min-height: 142px; }
        .cfp-field .form-control:focus,
        .cfp-field .form-select:focus { border-color: var(--cfp-green-700); box-shadow: 0 0 0 3px rgba(19, 112, 82, .12); }
        .cfp-help { display: block; margin-top: 6px; color: var(--cfp-muted); font-size: .72rem; line-height: 1.45; }
        .cfp-choice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px; }
        .cfp-choice { display: flex; align-items: flex-start; gap: 9px; padding: 11px; border: 1px solid var(--cfp-line); border-radius: 8px; color: var(--cfp-ink); background: #fbfdfc; font-size: .77rem; font-weight: 650; }
        .cfp-choice input { margin-top: 2px; accent-color: var(--cfp-green-700); }
        .cfp-upload { padding: 15px; border: 1px dashed #b7cdc1; border-radius: 9px; background: #f9fcfa; }
        .cfp-upload .form-control { min-height: 42px; background: #fff; }
        .cfp-form-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 28px; padding-top: 22px; border-top: 1px solid var(--cfp-line); }
        .cfp-form-button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; gap: 8px; padding: 0 18px; border: 1px solid transparent; border-radius: 8px; font-size: .82rem; font-weight: 800; }
        .cfp-form-button.primary { color: #fff; background: var(--cfp-green-700); }
        .cfp-form-button.secondary { color: var(--cfp-green-900); border-color: var(--cfp-line); background: #fff; }
        .cfp-form-alert { display: none; margin-top: 14px; color: #a32d27; font-size: .77rem; font-weight: 700; }
        .cfp-form-alert.visible { display: block; }

        @media (max-width: 991px) {
            .cfp-hero-grid,
            .cfp-layout { grid-template-columns: 1fr; }
            .cfp-hero-grid { align-items: start; gap: 34px; }
            .cfp-status-panel { max-width: 540px; }
            .cfp-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .cfp-fact:nth-child(2) { border-right: 0; }
            .cfp-fact:nth-child(-n+2) { border-bottom: 1px solid var(--cfp-line); }
            .cfp-sidebar { position: static; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 700px) {
            .cfp-shell { width: min(100% - 22px, 1180px); }
            .cfp-hero { padding-top: 56px; }
            .cfp-theme-grid,
            .cfp-form-grid,
            .cfp-choice-grid,
            .cfp-sidebar { grid-template-columns: 1fr; }
            .cfp-form-head { align-items: flex-start; flex-direction: column; }
            .cfp-stage { padding: 21px 17px; }
            .cfp-form-head { padding: 21px 17px; }
        }

        @media (max-width: 520px) {
            .cfp-facts { grid-template-columns: 1fr; margin-top: -30px; }
            .cfp-fact { border-right: 0; border-bottom: 1px solid var(--cfp-line); }
            .cfp-fact:last-child { border-bottom: 0; }
            .cfp-closed { grid-template-columns: 1fr; }
            .cfp-form-actions { align-items: stretch; flex-direction: column-reverse; }
            .cfp-form-button { width: 100%; }
        }
    </style>
@endpush

@section('content')
    @php
        $submissionsOpen = (bool) ($callForProposalSubmissionsOpen ?? false);
        $themes = [
            ['icon' => 'feather-trending-up', 'name' => 'Economic transformation and governance'],
            ['icon' => 'feather-cloud-rain', 'name' => 'Climate change'],
            ['icon' => 'feather-repeat', 'name' => 'Regional trade'],
            ['icon' => 'feather-box', 'name' => 'Food security'],
            ['icon' => 'feather-users', 'name' => 'Human capital development'],
            ['icon' => 'feather-cpu', 'name' => 'Digitalization'],
        ];
        $regions = ['West Africa', 'East Africa', 'Central Africa', 'North Africa', 'Southern Africa'];
        $countries = [
            'Algeria', 'Angola', 'Benin', 'Botswana', 'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cameroon',
            'Central African Republic', 'Chad', 'Comoros', 'Congo (Brazzaville)', 'Congo (Kinshasa)',
            "Côte d'Ivoire", 'Djibouti', 'Egypt', 'Equatorial Guinea', 'Eritrea', 'Eswatini', 'Ethiopia',
            'Gabon', 'Gambia', 'Ghana', 'Guinea', 'Guinea-Bissau', 'Kenya', 'Lesotho', 'Liberia', 'Libya',
            'Madagascar', 'Malawi', 'Mali', 'Mauritania', 'Mauritius', 'Morocco', 'Mozambique', 'Namibia',
            'Niger', 'Nigeria', 'Rwanda', 'Sao Tome and Principe', 'Senegal', 'Seychelles', 'Sierra Leone',
            'Somalia', 'South Africa', 'South Sudan', 'Sudan', 'Tanzania', 'Togo', 'Tunisia', 'Uganda',
            'Zambia', 'Zimbabwe',
        ];
        $uploads = [
            ['name' => 'application_form', 'label' => 'Completed application form', 'help' => 'The official consortium application form.'],
            ['name' => 'legal_registration', 'label' => 'Legal registration documents', 'help' => 'Combined certificates for every consortium member.'],
            ['name' => 'trustees_formation', 'label' => 'Board or trustees documentation', 'help' => 'Formation or governance evidence for consortium members.'],
            ['name' => 'audited_reports', 'label' => 'Audited financial reports', 'help' => 'Latest available audited accounts for each member.'],
            ['name' => 'commitment_letter', 'label' => 'Consortium commitment letter', 'help' => 'Signed by every participating think tank.'],
            ['name' => 'work_plan_budget', 'label' => 'Work plan and budget', 'help' => 'Activity schedule, Gantt chart and financial plan.'],
            ['name' => 'cv_coordinator', 'label' => 'CV of consortium coordinator', 'help' => 'Leadership and relevant programme experience.'],
            ['name' => 'cv_deputy', 'label' => 'CV of deputy coordinator', 'help' => 'Deputy leadership and delivery experience.'],
            ['name' => 'cv_team_members', 'label' => 'CVs of key team members', 'help' => 'Combine the relevant team CVs into one file.'],
            ['name' => 'past_research', 'label' => 'Past research and engagement', 'help' => 'Evidence of relevant policy research or engagement.'],
        ];
    @endphp

    <div class="cfp-page">
        <section class="cfp-hero" aria-labelledby="cfp-title">
            <div class="cfp-shell cfp-hero-grid">
                <div>
                    <div class="cfp-kicker">Africa Think Tank Platform</div>
                    <h1 id="cfp-title">Strengthening African policy research and regional collaboration</h1>
                    <p class="cfp-hero-copy">
                        A continental call supporting consortia of African think tanks to produce policy-relevant
                        research, deepen institutional capacity and strengthen evidence-informed decision-making.
                    </p>
                    <div class="cfp-hero-actions">
                        <a href="#call-details" class="cfp-btn cfp-btn-primary">
                            <i class="feather-book-open" aria-hidden="true"></i> Review the call
                        </a>
                        <a href="{{ route('events') }}" class="cfp-btn cfp-btn-ghost">
                            <i class="feather-play-circle" aria-hidden="true"></i> Applicant webinars
                        </a>
                    </div>
                </div>

                <aside class="cfp-status-panel" id="application-status" aria-label="Call status">
                    <span class="cfp-status-badge {{ $submissionsOpen ? 'open' : 'closed' }}">
                        {{ $submissionsOpen ? 'Applications open' : 'Applications closed' }}
                    </span>
                    <h2>{{ $submissionsOpen ? 'Submit your consortium application' : 'This application window has ended' }}</h2>
                    <p>
                        {{ $submissionsOpen
                            ? 'Prepare every required document before beginning the online submission.'
                            : 'The call information remains available for transparency and reference. New submissions are not being accepted.' }}
                    </p>
                    <div class="cfp-status-meta">
                        <div class="cfp-status-row"><i class="feather-calendar"></i><span>Deadline: 24 September 2025, 23:59 EAT</span></div>
                        <div class="cfp-status-row"><i class="feather-mail"></i><span>attpinfo@africanunion.org</span></div>
                    </div>
                </aside>
            </div>
        </section>

        <div class="cfp-shell cfp-facts" aria-label="Call summary">
            <div class="cfp-fact"><span class="cfp-fact-label">Applicant structure</span><strong>Consortium of 3–5 African think tanks</strong></div>
            <div class="cfp-fact"><span class="cfp-fact-label">Geographic reach</span><strong>At least two African sub-regions</strong></div>
            <div class="cfp-fact"><span class="cfp-fact-label">Thematic experience</span><strong>At least four of six priority themes</strong></div>
            <div class="cfp-fact"><span class="cfp-fact-label">Selection route</span><strong>Eligibility screening and expert evaluation</strong></div>
        </div>

        <main class="cfp-main" id="call-details">
            <div class="cfp-shell cfp-layout">
                <div>
                    @if (session('success'))
                        <div class="alert alert-success border-0 shadow-sm mb-4" role="status">
                            <strong>Application received.</strong> A confirmation has been sent to the submitted email address.
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="cfp-error-summary" role="alert" tabindex="-1" id="application-errors">
                            <strong>Please correct the following before continuing:</strong>
                            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <section class="cfp-section" aria-labelledby="about-call">
                        <div class="cfp-section-label">About the opportunity</div>
                        <h2 id="about-call">A stronger African evidence ecosystem, built through collaboration</h2>
                        <p class="cfp-lead">
                            Funded by the World Bank and implemented by the African Union Commission, ATTP connects
                            African think tanks working on cross-border policy priorities. Selected consortia support
                            high-quality research, policy dialogue, institutional development and stronger participation
                            of women in policy research and leadership.
                        </p>

                        <div class="cfp-theme-grid" aria-label="ATTP priority themes">
                            @foreach ($themes as $theme)
                                <div class="cfp-theme">
                                    <span class="cfp-theme-icon"><i class="{{ $theme['icon'] }}" aria-hidden="true"></i></span>
                                    <span>{{ $theme['name'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="cfp-section" aria-labelledby="selection-process">
                        <div class="cfp-section-label">How selection works</div>
                        <h2 id="selection-process">A clear and accountable review process</h2>
                        <div class="cfp-process">
                            <article class="cfp-process-item">
                                <span class="cfp-process-number">01</span>
                                <div><h3>Eligibility screening</h3><p>Applications are checked for consortium structure, African registration, geographic coverage, thematic experience and completeness.</p></div>
                            </article>
                            <article class="cfp-process-item">
                                <span class="cfp-process-number">02</span>
                                <div><h3>Independent technical evaluation</h3><p>Eligible proposals are assessed for research quality, policy relevance, institutional capacity, impact, financial soundness, gender and geographic diversity.</p></div>
                            </article>
                            <article class="cfp-process-item">
                                <span class="cfp-process-number">03</span>
                                <div><h3>Steering Committee decision</h3><p>Final approval is made by the Think Tank Platform Steering Committee following the independent assessment.</p></div>
                            </article>
                        </div>
                    </section>

                    <section class="cfp-section" aria-labelledby="application-guide">
                        <div class="cfp-section-label">Application guide</div>
                        <h2 id="application-guide">Everything to prepare before submission</h2>
                        <div class="cfp-accordion">
                            <details open>
                                <summary>Eligibility requirements</summary>
                                <div class="cfp-accordion-body">
                                    <ul>
                                        <li>Apply as a consortium of three to five legally registered African think tanks.</li>
                                        <li>Designate one organization as the lead applicant.</li>
                                        <li>Include organizations from at least two African sub-regions.</li>
                                        <li>Demonstrate experience in at least four ATTP priority themes.</li>
                                        <li>Align proposed research, engagement, capacity building and gender actions with ATTP objectives.</li>
                                    </ul>
                                </div>
                            </details>
                            <details>
                                <summary>Required application package</summary>
                                <div class="cfp-accordion-body">
                                    <ul>
                                        <li><strong>Application form:</strong> consortium profile, research priorities, activities, capacity building and gender strategy.</li>
                                        <li><strong>Work plan and budget:</strong> activity schedule, Gantt chart and justified financial plan.</li>
                                        <li><strong>Legal and governance documents:</strong> registration and board/trustee evidence for member organizations.</li>
                                        <li><strong>Audited financial reports:</strong> recent accounts demonstrating accountability and grant readiness.</li>
                                        <li><strong>Personnel documents:</strong> coordinator, deputy and key researcher CVs.</li>
                                        <li><strong>Commitment letter and experience:</strong> signed consortium commitment and evidence of past research or policy engagement.</li>
                                    </ul>
                                </div>
                            </details>
                            <details>
                                <summary>Important dates and applicant support</summary>
                                <div class="cfp-accordion-body">
                                    <ul>
                                        <li>Call launch: 24 July 2025.</li>
                                        <li>Applicant webinars: 5 August, 26 August, 9 September and 23 September 2025.</li>
                                        <li>Submission deadline: <strong>24 September 2025 at 23:59 East Africa Time.</strong></li>
                                        <li>Questions: <a href="mailto:attpinfo@africanunion.org">attpinfo@africanunion.org</a>.</li>
                                    </ul>
                                </div>
                            </details>
                        </div>
                    </section>

                    <section class="cfp-section" id="apply" aria-labelledby="application-heading">
                        <div class="cfp-section-label">Online application</div>
                        <h2 id="application-heading">Consortium registration and submission</h2>

                        @if (! $submissionsOpen)
                            <div class="cfp-closed">
                                <span class="cfp-closed-icon"><i class="feather-lock" aria-hidden="true"></i></span>
                                <div>
                                    <h3>Submissions are closed</h3>
                                    <p>
                                        The application deadline has passed and the portal no longer accepts new
                                        submissions. For programme updates, review the applicant webinars or contact
                                        the ATTP Secretariat.
                                    </p>
                                </div>
                            </div>
                        @else
                            <form id="consortiumApplication" class="cfp-application" action="{{ route('applicants.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                                @csrf
                                <div class="cfp-form-head">
                                    <div><h3>Submit the consortium proposal</h3><p>Fields marked with an asterisk are required.</p></div>
                                    <div class="cfp-stepper" aria-label="Application progress">
                                        <span class="cfp-step active" data-step-indicator="1">1 · Profile</span>
                                        <span class="cfp-step" data-step-indicator="2">2 · Documents</span>
                                    </div>
                                </div>

                                <section class="cfp-stage active" data-stage="1" aria-labelledby="profile-stage-title">
                                    <div class="cfp-stage-title"><h4 id="profile-stage-title">Consortium profile</h4><p>Identify the lead organization, partners, thematic experience and geographic reach.</p></div>
                                    <div class="cfp-form-grid">
                                        <div class="cfp-field">
                                            <label for="think_tank_name">Lead think tank <span class="cfp-required">*</span></label>
                                            <select name="think_tank_name" id="think_tank_name" class="form-select @error('think_tank_name') is-invalid @enderror" required>
                                                <option value="">Select the lead think tank</option>
                                                @foreach ($thinkTanks as $tank)<option value="{{ $tank }}" @selected(old('think_tank_name') === $tank)>{{ $tank }}</option>@endforeach
                                                <option value="Other" @selected(old('think_tank_name') === 'Other')>Other — enter the official name</option>
                                            </select>
                                            <small class="cfp-help">Use the organization’s full registered name.</small>
                                        </div>

                                        <div class="cfp-field {{ old('think_tank_name') === 'Other' ? '' : 'd-none' }}" id="customThinkTankField">
                                            <label for="custom_think_tank">Official think-tank name <span class="cfp-required">*</span></label>
                                            <input type="text" name="custom_think_tank" id="custom_think_tank" value="{{ old('custom_think_tank') }}" class="form-control" maxlength="255">
                                        </div>

                                        <div class="cfp-field">
                                            <label for="country">Lead think-tank country <span class="cfp-required">*</span></label>
                                            <select name="country" id="country" class="form-select @error('country') is-invalid @enderror" required>
                                                <option value="">Select country</option>
                                                @foreach ($countries as $country)<option value="{{ $country }}" @selected(old('country') === $country)>{{ $country }}</option>@endforeach
                                            </select>
                                        </div>

                                        <div class="cfp-field">
                                            <label for="consortium_name">Consortium name <span class="cfp-required">*</span></label>
                                            <input type="text" name="consortium_name" id="consortium_name" value="{{ old('consortium_name') }}" class="form-control @error('consortium_name') is-invalid @enderror" maxlength="255" required>
                                        </div>

                                        <div class="cfp-field full">
                                            <label for="members_names">Consortium member organizations <span class="cfp-required">*</span></label>
                                            <textarea name="members_names" id="members_names" class="form-control @error('members_names') is-invalid @enderror" maxlength="6000" placeholder="List the participating think tanks, separated by commas or one per line." required>{{ old('members_names') }}</textarea>
                                        </div>

                                        <div class="cfp-field full">
                                            <label>ATTP priority themes — select at least four <span class="cfp-required">*</span></label>
                                            <div class="cfp-choice-grid" id="focusAreaChoices">
                                                @foreach ($themes as $theme)
                                                    <label class="cfp-choice"><input type="checkbox" name="focus_areas[]" value="{{ $theme['name'] }}" @checked(collect(old('focus_areas', []))->contains($theme['name']))><span>{{ $theme['name'] }}</span></label>
                                                @endforeach
                                            </div>
                                            <small class="cfp-form-alert" id="focusAreaError">Select at least four priority themes.</small>
                                        </div>

                                        <div class="cfp-field full">
                                            <label>African sub-regions represented <span class="cfp-required">*</span></label>
                                            <div class="cfp-choice-grid">
                                                @foreach ($regions as $region)
                                                    <label class="cfp-choice"><input type="checkbox" name="sub_region[]" value="{{ $region }}" @checked(collect(old('sub_region', []))->contains($region))><span>{{ $region }}</span></label>
                                                @endforeach
                                            </div>
                                            <small class="cfp-help">The consortium should represent at least two sub-regions.</small>
                                        </div>

                                        <div class="cfp-field">
                                            <label for="consortium_region">Primary coordinating region</label>
                                            <select name="consortium_region" id="consortium_region" class="form-select">
                                                <option value="">Select region</option>
                                                @foreach ($regions as $region)<option value="{{ $region }}" @selected(old('consortium_region') === $region)>{{ $region }}</option>@endforeach
                                            </select>
                                        </div>

                                        <div class="cfp-field">
                                            <label for="email">Official consortium email <span class="cfp-required">*</span></label>
                                            <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" maxlength="255" required>
                                            <small class="cfp-help">Use a monitored institutional address where possible.</small>
                                        </div>

                                        <div class="cfp-field full">
                                            <label for="covered_countries">Countries covered by the consortium</label>
                                            <select name="covered_countries[]" id="covered_countries" class="form-select" multiple>
                                                @foreach ($countries as $country)<option value="{{ $country }}" @selected(collect(old('covered_countries', []))->contains($country))>{{ $country }}</option>@endforeach
                                            </select>
                                            <small class="cfp-help">Use Ctrl/Command to select multiple countries.</small>
                                        </div>
                                    </div>

                                    <div class="cfp-form-actions">
                                        <span class="cfp-help">Your documents are added in the next step.</span>
                                        <button type="button" class="cfp-form-button primary" data-next-step>Continue to documents <i class="feather-arrow-right"></i></button>
                                    </div>
                                </section>

                                <section class="cfp-stage" data-stage="2" aria-labelledby="documents-stage-title">
                                    <div class="cfp-stage-title"><h4 id="documents-stage-title">Required documents</h4><p>Upload PDF, DOC or DOCX files. Each file may be up to 10 MB.</p></div>
                                    <div class="cfp-form-grid">
                                        @foreach ($uploads as $upload)
                                            <div class="cfp-field cfp-upload">
                                                <label for="{{ $upload['name'] }}">{{ $upload['label'] }} <span class="cfp-required">*</span></label>
                                                <input type="file" name="{{ $upload['name'] }}" id="{{ $upload['name'] }}" class="form-control @error($upload['name']) is-invalid @enderror" accept=".pdf,.doc,.docx" required>
                                                <small class="cfp-help">{{ $upload['help'] }}</small>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="cfp-form-actions">
                                        <button type="button" class="cfp-form-button secondary" data-previous-step><i class="feather-arrow-left"></i> Back to profile</button>
                                        <button type="submit" class="cfp-form-button primary"><i class="feather-send"></i> Submit application</button>
                                    </div>
                                </section>
                            </form>
                        @endif
                    </section>
                </div>

                <aside class="cfp-sidebar" aria-label="Applicant information">
                    <div class="cfp-side-card accent">
                        <h3>{{ $submissionsOpen ? 'Before you begin' : 'Call status' }}</h3>
                        <p>
                            {{ $submissionsOpen
                                ? 'Gather all ten required documents and confirm the official consortium email before starting.'
                                : 'This historical call is closed. Do not send application documents by ordinary email unless specifically requested by the Secretariat.' }}
                        </p>
                    </div>
                    <div class="cfp-side-card">
                        <h3>Applicant support</h3>
                        <p>For formal clarification, contact the ATTP Secretariat.</p>
                        <div class="cfp-side-links">
                            <a href="mailto:attpinfo@africanunion.org"><span>Email the Secretariat</span><i class="feather-arrow-up-right"></i></a>
                            <a href="{{ route('events') }}"><span>View webinars</span><i class="feather-arrow-right"></i></a>
                            <a href="{{ route('landing.index') }}"><span>About ATTP</span><i class="feather-arrow-right"></i></a>
                        </div>
                    </div>
                    <div class="cfp-side-card">
                        <h3>Integrity reminder</h3>
                        <p>Applications should use accurate institutional information and complete, verifiable supporting documents.</p>
                    </div>
                </aside>
            </div>
        </main>
    </div>
@endsection

@push('scripts')
    @if ($submissionsOpen)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('consortiumApplication');
                const stages = Array.from(document.querySelectorAll('[data-stage]'));
                const indicators = Array.from(document.querySelectorAll('[data-step-indicator]'));
                const leadTank = document.getElementById('think_tank_name');
                const customField = document.getElementById('customThinkTankField');
                const customInput = document.getElementById('custom_think_tank');
                const focusError = document.getElementById('focusAreaError');

                const showStage = (number) => {
                    stages.forEach(stage => stage.classList.toggle('active', stage.dataset.stage === String(number)));
                    indicators.forEach(indicator => indicator.classList.toggle('active', indicator.dataset.stepIndicator === String(number)));
                    document.getElementById('application-heading')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                };

                const updateCustomTank = () => {
                    const isOther = leadTank?.value === 'Other';
                    customField?.classList.toggle('d-none', !isOther);
                    if (customInput) customInput.required = isOther;
                };

                const validateProfile = () => {
                    const stage = document.querySelector('[data-stage="1"]');
                    const requiredFields = Array.from(stage.querySelectorAll('input[required], select[required], textarea[required]'));
                    const fieldsValid = requiredFields.every(field => {
                        const valid = field.checkValidity();
                        field.classList.toggle('is-invalid', !valid);
                        return valid;
                    });
                    const selectedThemes = document.querySelectorAll('input[name="focus_areas[]"]:checked').length;
                    const themesValid = selectedThemes >= 4;
                    focusError?.classList.toggle('visible', !themesValid);

                    if (!fieldsValid) stage.querySelector(':invalid')?.reportValidity();
                    return fieldsValid && themesValid;
                };

                leadTank?.addEventListener('change', updateCustomTank);
                document.querySelector('[data-next-step]')?.addEventListener('click', () => {
                    if (validateProfile()) showStage(2);
                });
                document.querySelector('[data-previous-step]')?.addEventListener('click', () => showStage(1));
                form?.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        form.reportValidity();
                    }
                });

                updateCustomTank();
                @if ($errors->any())
                    document.getElementById('application-errors')?.focus();
                @endif
            });
        </script>
    @endif
@endpush
