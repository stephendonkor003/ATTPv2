@php
    $isPublicSubmission = (bool) ($isPublicSubmission ?? false);
    $submissionAction = $submissionAction ?? route('grm.submissions.store');
    $canSelectChannel = (bool) ($canSelectChannel ?? false);
    $automaticChannel = $automaticChannel ?? 'internal_portal';
    $automaticChannelLabel = $channels[$automaticChannel] ?? \Illuminate\Support\Str::headline($automaticChannel);
    $selectableChannels = $selectableChannels ?? $channels;
@endphp

    <style>
        .grm-hero {
            border-radius: 8px;
            padding: 22px;
            color: #fff;
            background: linear-gradient(135deg, #064e3b 0%, #0f766e 58%, #522b39 100%);
            box-shadow: 0 18px 36px rgba(6, 78, 59, 0.18);
        }
        .grm-hero h4,
        .grm-hero p { color: #fff; }
        .grm-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 16px;
            align-items: start;
        }
        .grm-card {
            border: 1px solid #dbe5df;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.06);
        }
        .grm-card-header {
            padding: 16px 18px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 8px 8px 0 0;
        }
        .grm-card-body { padding: 18px; }
        .grm-icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #fff;
            background: #0f766e;
        }
        .grm-step {
            display: flex;
            gap: 12px;
            padding: 13px;
            border: 1px solid #dbe5df;
            border-radius: 8px;
            background: #f8fafc;
        }
        .grm-step + .grm-step { margin-top: 10px; }
        .grm-identity.is-anonymous { display: none; }
        .grm-anonymous-note {
            display: none;
            border: 1px solid #bbf7d0;
            background: #ecfdf5;
            color: #166534;
            border-radius: 8px;
            padding: 12px 14px;
            font-weight: 700;
        }
        .grm-anonymous-note.is-visible { display: flex; gap: 10px; align-items: center; }
        .grm-anonymous-note button {
            margin-left: auto;
            white-space: nowrap;
        }
        .grm-modal-privacy {
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            background: #ecfdf5;
            color: #14532d;
            padding: 13px 14px;
        }
        .grm-modal-privacy strong { color: #14532d; }
        .grm-contact-hint {
            border-left: 3px solid #0f766e;
            background: #f0fdfa;
            color: #475569;
            padding: 10px 12px;
        }
        .grm-source-readonly {
            min-height: 42px;
            display: flex;
            align-items: center;
            gap: 9px;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            background: #ecfdf5;
            color: #166534;
            padding: 9px 12px;
            font-weight: 750;
        }
        .grm-officer-note {
            border: 1px solid #bae6fd;
            border-radius: 8px;
            background: #f0f9ff;
            color: #0c4a6e;
            padding: 12px 14px;
        }
        .grm-document-row {
            display: grid;
            grid-template-columns: minmax(180px, .8fr) minmax(220px, 1fr) auto;
            gap: 10px;
            align-items: end;
            border: 1px solid #dbe5df;
            border-radius: 8px;
            background: #f8fafc;
            padding: 12px;
        }
        .grm-document-remove {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .grm-section-heading,
        .grm-public-assurances,
        .grm-submit-panel,
        .grm-public-timeline,
        .grm-public-privacy-card { display: none; }
        .grm-public-intake {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            width: 100%;
            max-width: 100%;
            padding-top: 32px;
            padding-bottom: 70px;
        }
        .grm-public-intake .grm-shell,
        .grm-public-intake .grm-card,
        .grm-public-intake .grm-card-body,
        .grm-public-intake form,
        .grm-public-intake aside,
        .grm-public-intake .row > * { min-width: 0; }
        .grm-public-intake::before,
        .grm-public-intake::after {
            position: absolute;
            z-index: -1;
            width: 440px;
            height: 440px;
            border-radius: 50%;
            content: '';
            filter: blur(2px);
            pointer-events: none;
        }
        .grm-public-intake::before {
            top: 170px;
            left: -300px;
            background: rgba(15, 118, 110, .08);
        }
        .grm-public-intake::after {
            top: 640px;
            right: -310px;
            background: rgba(82, 43, 57, .07);
        }
        .grm-public-intake .grm-hero {
            position: relative;
            overflow: hidden;
            min-height: 330px;
            display: flex;
            align-items: center;
            border: 0;
            border-radius: 26px;
            padding: 46px 52px;
            background:
                radial-gradient(circle at 86% 18%, rgba(255, 255, 255, .18) 0 2px, transparent 3px) 0 0 / 24px 24px,
                linear-gradient(125deg, #073f32 0%, #0c6b52 54%, #522b39 100%);
            box-shadow: 0 28px 70px rgba(7, 63, 50, .24);
        }
        .grm-public-intake .grm-hero::after {
            position: absolute;
            right: -90px;
            bottom: -160px;
            width: 430px;
            height: 430px;
            border: 70px solid rgba(255, 255, 255, .075);
            border-radius: 50%;
            content: '';
        }
        .grm-public-hero-copy {
            position: relative;
            z-index: 1;
            max-width: 760px;
        }
        .grm-public-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 28px;
            color: rgba(255, 255, 255, .82);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }
        .grm-public-back:hover { color: #fff; }
        .grm-public-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, .24);
            border-radius: 999px;
            padding: 7px 12px;
            background: rgba(255, 255, 255, .12);
            color: #e8fff4;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .11em;
            text-transform: uppercase;
        }
        .grm-public-intake .grm-hero h1 {
            max-width: 680px;
            margin: 0 0 13px;
            color: #fff;
            font-size: clamp(2rem, 4vw, 3.45rem);
            font-weight: 800;
            letter-spacing: -.045em;
            line-height: 1.05;
        }
        .grm-public-intake .grm-hero p {
            max-width: 690px;
            margin: 0;
            color: rgba(255, 255, 255, .83);
            font-size: 16px;
            line-height: 1.75;
        }
        .grm-public-assurances {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 22px;
            margin-top: 26px;
        }
        .grm-public-assurances span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
        }
        .grm-public-assurances i { color: #e4bd54; }
        .grm-public-intake .grm-shell {
            grid-template-columns: minmax(0, 1fr) 350px;
            gap: 28px;
            margin-top: 30px;
        }
        .grm-public-intake .grm-card {
            overflow: hidden;
            border: 1px solid #dfe9e4;
            border-radius: 20px;
            box-shadow: 0 22px 54px rgba(27, 60, 44, .09);
        }
        .grm-public-intake .grm-card-header {
            border-bottom: 1px solid #e5ece8;
            border-radius: 0;
            padding: 23px 28px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbf9 100%);
        }
        .grm-public-intake .grm-card-body { padding: 30px 32px 34px; }
        .grm-public-intake .grm-icon {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 12px;
            background: linear-gradient(145deg, #0f766e, #07543f);
            box-shadow: 0 7px 16px rgba(15, 118, 110, .18);
        }
        .grm-public-intake .grm-form-kicker {
            display: block;
            margin-bottom: 3px;
            color: #0f766e;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .grm-public-intake .grm-section-heading {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 22px;
        }
        .grm-public-intake .grm-section-number {
            width: 34px;
            height: 34px;
            display: inline-flex;
            flex: 0 0 34px;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #e8f5ef;
            color: #0b684c;
            font-size: 12px;
            font-weight: 850;
        }
        .grm-public-intake .grm-section-heading h2 {
            margin: 0 0 3px;
            color: #183c2c;
            font-size: 17px;
            font-weight: 800;
        }
        .grm-public-intake .grm-section-heading p {
            margin: 0;
            color: #708078;
            font-size: 12px;
        }
        .grm-public-intake .form-label {
            margin-bottom: 7px;
            color: #294438;
            font-size: 13px;
        }
        .grm-public-intake .form-control,
        .grm-public-intake .form-select {
            min-height: 49px;
            border-color: #d7e3dd;
            border-radius: 10px;
            background-color: #fbfdfc;
            color: #18342a;
            font-size: 14px;
            box-shadow: none;
        }
        .grm-public-intake textarea.form-control {
            min-height: 148px;
            padding-top: 13px;
            line-height: 1.65;
            resize: vertical;
        }
        .grm-public-intake .form-control:focus,
        .grm-public-intake .form-select:focus {
            border-color: #138265;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(15, 118, 110, .10);
        }
        .grm-public-intake .grm-source-readonly {
            min-height: 49px;
            border-color: #c9e5d8;
            border-radius: 10px;
            padding-inline: 14px;
            background: #f0faf5;
        }
        .grm-public-intake hr {
            margin-top: 30px !important;
            margin-bottom: 28px !important;
            border-color: #e4ebe7;
            opacity: 1;
        }
        .grm-public-intake .grm-anonymous-choice {
            border: 1px solid #d6e7de;
            border-radius: 13px;
            padding: 12px 14px;
            background: #f7fbf9;
        }
        .grm-public-intake .grm-anonymous-choice .form-check-label {
            color: #174c37;
            font-size: 13px;
            font-weight: 750;
        }
        .grm-public-intake .form-check-input:checked {
            border-color: #0f766e;
            background-color: #0f766e;
        }
        .grm-public-intake .grm-contact-hint {
            border: 0;
            border-radius: 9px;
            padding: 11px 13px;
            background: #eef8f3;
            color: #4f6a5e;
        }
        .grm-public-intake .grm-document-row {
            border: 1px dashed #bdd5c9;
            border-radius: 13px;
            padding: 15px;
            background: #f9fcfa;
        }
        .grm-public-intake .grm-submit-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin: 30px -32px -34px;
            border-top: 1px solid #dfe9e4;
            padding: 22px 32px;
            background: #f4f9f6;
        }
        .grm-public-intake .grm-submit-assurance {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #586d63;
            font-size: 12px;
            line-height: 1.45;
        }
        .grm-public-intake .grm-submit-assurance i {
            color: #0f766e;
            font-size: 20px;
        }
        .grm-public-intake .grm-submit-button {
            min-height: 49px;
            border: 0;
            border-radius: 11px;
            padding: 0 24px;
            background: linear-gradient(135deg, #0d7254, #07513d);
            box-shadow: 0 10px 20px rgba(7, 81, 61, .2);
            font-weight: 800;
        }
        .grm-public-intake .grm-submit-button:hover {
            background: linear-gradient(135deg, #0a6249, #063f31);
            transform: translateY(-1px);
        }
        .grm-public-aside { position: sticky; top: 24px; }
        .grm-public-intake .grm-public-privacy-card {
            display: block;
            margin-bottom: 16px;
            border-radius: 20px;
            padding: 24px;
            color: #fff;
            background: linear-gradient(145deg, #522b39 0%, #6b3b4c 100%);
            box-shadow: 0 18px 38px rgba(82, 43, 57, .18);
        }
        .grm-public-privacy-icon {
            width: 46px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 17px;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 14px;
            background: rgba(255, 255, 255, .12);
            font-size: 21px;
        }
        .grm-public-privacy-card h3 {
            margin: 0 0 8px;
            color: #fff;
            font-size: 18px;
            font-weight: 800;
        }
        .grm-public-privacy-card p {
            margin: 0;
            color: rgba(255, 255, 255, .78);
            font-size: 12px;
            line-height: 1.7;
        }
        .grm-public-intake .grm-public-timeline {
            display: block;
            padding: 3px 0;
        }
        .grm-public-timeline-item {
            position: relative;
            display: flex;
            gap: 13px;
            padding-bottom: 22px;
        }
        .grm-public-timeline-item:last-child { padding-bottom: 2px; }
        .grm-public-timeline-item:not(:last-child)::before {
            position: absolute;
            top: 30px;
            bottom: 5px;
            left: 14px;
            width: 1px;
            background: #cfe0d8;
            content: '';
        }
        .grm-public-timeline-dot {
            position: relative;
            z-index: 1;
            width: 29px;
            height: 29px;
            display: inline-flex;
            flex: 0 0 29px;
            align-items: center;
            justify-content: center;
            border: 1px solid #cce1d6;
            border-radius: 50%;
            background: #eef8f3;
            color: #0f766e;
            font-size: 12px;
            font-weight: 850;
        }
        .grm-public-timeline-item strong {
            display: block;
            margin: 2px 0 3px;
            color: #244536;
            font-size: 13px;
        }
        .grm-public-timeline-item p {
            margin: 0;
            color: #738179;
            font-size: 11px;
            line-height: 1.55;
        }
        @media (max-width: 991px) {
            .grm-shell { grid-template-columns: 1fr; }
            .grm-document-row { grid-template-columns: 1fr; }
            .grm-document-remove { width: 100%; }
            .grm-public-intake .grm-shell { grid-template-columns: 1fr; }
            .grm-public-aside { position: static; }
        }
        @media (max-width: 575px) {
            .grm-anonymous-note.is-visible { align-items: flex-start; flex-wrap: wrap; }
            .grm-anonymous-note button { margin-left: 0; width: 100%; }
            .grm-public-intake { padding-top: 14px; padding-bottom: 40px; }
            .grm-public-intake .grm-hero { width: 100%; min-height: 0; border-radius: 18px; padding: 28px 22px; }
            .grm-public-hero-copy { width: 100%; min-width: 0; }
            .grm-public-back { width: fit-content; display: flex; margin-bottom: 12px; }
            .grm-public-eyebrow { width: fit-content; max-width: 100%; display: flex; margin-bottom: 18px; white-space: normal; }
            .grm-public-intake .grm-hero h1 { max-width: 100%; font-size: 2.1rem; overflow-wrap: anywhere; }
            .grm-public-intake .grm-hero p { font-size: 14px; line-height: 1.65; }
            .grm-public-assurances { display: grid; gap: 10px; }
            .grm-public-intake .grm-card { border-radius: 16px; }
            .grm-public-intake .grm-card-header { padding: 20px; }
            .grm-public-intake .grm-card-body { padding: 24px 20px 26px; }
            .grm-public-intake .grm-submit-panel { align-items: stretch; flex-direction: column; margin: 28px -20px -26px; padding: 20px; }
            .grm-public-intake .grm-submit-button { width: 100%; }
        }
    </style>

    <div class="container-fluid {{ $isPublicSubmission ? 'grm-public-intake' : '' }}" @if($isPublicSubmission) style="max-width: 1260px;" @endif>
        <div class="grm-hero mb-3">
            @if ($isPublicSubmission)
                <div class="grm-public-hero-copy">
                    <a href="{{ route('landing.index') }}" class="grm-public-back">
                        <i class="feather-arrow-left"></i> Back to ATTP website
                    </a>
                    <div class="grm-public-eyebrow"><i class="feather-shield"></i> Grievance Redress Mechanism</div>
                    <h1>Speak up. We are listening.</h1>
                    <p>Share a concern about an ATTP program through this secure channel. Your submission will be recorded, directed to the responsible grievance officer, and assigned a traceable case number.</p>
                    <div class="grm-public-assurances" aria-label="Submission assurances">
                        <span><i class="feather-lock"></i> Confidential handling</span>
                        <span><i class="feather-hash"></i> Traceable case number</span>
                        <span><i class="feather-user-check"></i> Responsible officer review</span>
                    </div>
                </div>
            @else
                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
                    <div>
                        <span class="badge bg-light text-success mb-2">Grievance Redress Mechanism</span>
                        <h4 class="mb-1">Log a Grievance</h4>
                        <p class="mb-0">Capture program-linked grievances, notify the responsible program Grievance Redress Mechanism officer, and generate a traceable case number.</p>
                    </div>
                    @can('grm.view')
                        <a href="{{ route('grm.logs.index') }}" class="btn btn-light text-success fw-bold">
                            <i class="feather-clipboard me-1"></i> View Logs
                        </a>
                    @endcan
                </div>
            @endif
        </div>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2">
                <i class="feather-check-circle"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-2">
                <i class="feather-alert-circle"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="grm-shell">
            <form method="POST" action="{{ $submissionAction }}" class="grm-card" enctype="multipart/form-data" id="grmSubmissionForm">
                @csrf
                <div class="grm-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="grm-icon"><i class="feather-edit-3"></i></span>
                        <div>
                            @if ($isPublicSubmission)
                                <span class="grm-form-kicker">Secure online form</span>
                            @endif
                            <h6 class="mb-0 fw-bold">{{ $isPublicSubmission ? 'Submit your grievance' : 'Case Intake' }}</h6>
                            <small class="text-muted">Every submission receives a unique case number{{ $isPublicSubmission ? '. Fields marked * are required.' : '.' }}</small>
                        </div>
                    </div>
                </div>
                <div class="grm-card-body">
                    @if ($canSelectChannel)
                        <div class="grm-officer-note mb-3">
                            <strong><i class="feather-user-check me-1"></i> GRM intake officer access</strong>
                            <div class="small mt-1">When registering a grievance on behalf of another person, select how the complaint was originally received and enter the complainant's details below.</div>
                        </div>
                    @endif
                    @if ($isPublicSubmission)
                        <div class="grm-section-heading">
                            <span class="grm-section-number">01</span>
                            <div>
                                <h2>Tell us what happened</h2>
                                <p>Select the relevant program, then give a clear summary of your concern.</p>
                            </div>
                        </div>
                    @endif
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Program *</label>
                            <select name="program_id" id="grmProgramSelect" class="form-select" required>
                                <option value="">Select program</option>
                                @foreach ($programs as $program)
                                    <option value="{{ $program->id }}" @selected(old('program_id') === $program->id)>
                                        {{ $program->name }}{{ $program->sector ? ' - ' . $program->sector->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if ($canSelectChannel)
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Channel / Received Through *</label>
                                <select name="channel" class="form-select" required>
                                    @foreach ($selectableChannels as $value => $label)
                                        <option value="{{ $value }}" @selected(old('channel', $automaticChannel) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Only GRM management officers can change this field.</div>
                            </div>
                        @else
                            <input type="hidden" name="channel" value="{{ $automaticChannel }}">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Channel</label>
                                <div class="grm-source-readonly">
                                    <i class="feather-navigation"></i>
                                    <span>{{ $automaticChannelLabel }}</span>
                                </div>
                                <div class="form-text">Automatically detected by the system.</div>
                            </div>
                        @endif
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Subject *</label>
                            <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required maxlength="255" placeholder="A short title for your concern">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Incident Details / Summary *</label>
                            <textarea name="description" rows="6" class="form-control" required placeholder="Describe what happened, when and where it occurred, who was affected, and the outcome or remedy you are seeking.">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        @if ($isPublicSubmission)
                            <div class="grm-section-heading mb-0">
                                <span class="grm-section-number">02</span>
                                <div>
                                    <h2>Your contact details</h2>
                                    <p>Tell us how to acknowledge or respond to your case.</p>
                                </div>
                            </div>
                        @else
                            <h6 class="fw-bold mb-0">Complainant Details</h6>
                        @endif
                        <div class="form-check form-switch grm-anonymous-choice mb-0">
                            <input type="hidden" name="is_anonymous" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_anonymous" value="1" id="anonymousSwitch" @checked(old('is_anonymous'))>
                            <label class="form-check-label" for="anonymousSwitch"><i class="feather-eye-off me-1"></i> Submit anonymously</label>
                        </div>
                    </div>

                    <div class="grm-anonymous-note mb-3" id="anonymousNote">
                        <i class="feather-shield"></i>
                        <span>Anonymous mode is active. Your name and account are hidden; your confidential reply contact is kept separately for case responses.</span>
                        <button type="button" class="btn btn-sm btn-outline-success" id="editAnonymousContact">Edit reply contact</button>
                    </div>

                    <div class="row g-3 grm-identity" id="complainantFields">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="submitter_name" class="form-control" value="{{ old('submitter_name', auth()->user()?->name) }}" maxlength="255" data-anonymous-field>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="submitter_email" class="form-control" value="{{ old('submitter_email', auth()->user()?->email) }}" maxlength="255" data-anonymous-field>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="submitter_phone" class="form-control" value="{{ old('submitter_phone') }}" maxlength="60" data-anonymous-field>
                        </div>
                        <div class="col-12">
                            <div class="grm-contact-hint small">Provide an email address or phone number if you would like the grievance team to acknowledge or respond to your case.</div>
                        </div>
                    </div>

                    <div class="modal fade" id="anonymousPrivacyModal" tabindex="-1" aria-labelledby="anonymousPrivacyModalLabel" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg">
                                <div class="modal-header border-0 pb-0">
                                    <div>
                                        <span class="badge bg-success-subtle text-success mb-2">Anonymous submission</span>
                                        <h5 class="modal-title fw-bold" id="anonymousPrivacyModalLabel">Your identity will be hidden</h5>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="anonymousModalClose"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="grm-modal-privacy mb-3">
                                        <strong>Your name and signed-in account will not be attached to the grievance.</strong>
                                        <div class="small mt-1">ATTP still needs one private way to acknowledge the case or contact you if a response is available. Authorized grievance officers may use this contact only for case communication.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="anonymousContactMethod" class="form-label fw-semibold">Preferred reply method *</label>
                                        <select name="anonymous_contact_method" id="anonymousContactMethod" class="form-select" form="grmSubmissionForm">
                                            <option value="">Select a reply method</option>
                                            <option value="email" @selected(old('anonymous_contact_method') === 'email')>Private email address</option>
                                            <option value="phone" @selected(old('anonymous_contact_method') === 'phone')>Private phone number</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="anonymousContactValue" class="form-label fw-semibold" id="anonymousContactLabel">Email address or phone number *</label>
                                        <input type="text" name="anonymous_contact_value" id="anonymousContactValue" class="form-control" value="{{ old('anonymous_contact_value') }}" maxlength="255" autocomplete="off" form="grmSubmissionForm">
                                        <div class="invalid-feedback" id="anonymousContactError">Choose a reply method and enter a valid contact.</div>
                                        <div class="form-text">This contact is encrypted in storage and is not displayed as your complainant identity.</div>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 pt-0">
                                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal" id="anonymousModalCancel">Cancel</button>
                                    <button type="button" class="btn btn-success" id="anonymousModalConfirm">
                                        <i class="feather-shield me-1"></i> Continue anonymously
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        @if ($isPublicSubmission)
                            <div class="grm-section-heading mb-0">
                                <span class="grm-section-number">03</span>
                                <div>
                                    <h2>Add supporting evidence</h2>
                                    <p>Optional: attach documents or images that help explain your grievance.</p>
                                </div>
                            </div>
                        @else
                            <div>
                                <h6 class="fw-bold mb-1">Supporting Documents</h6>
                                <p class="text-muted small mb-0">Attach evidence such as PDFs, Word files, images, spreadsheets, text files, or ZIP archives.</p>
                            </div>
                        @endif
                        <button type="button" class="btn btn-outline-success btn-sm" id="addGrmDocument">
                            <i class="feather-plus me-1"></i> Add Document
                        </button>
                    </div>

                    <div class="d-grid gap-2" id="grmDocumentRows">
                        <div class="grm-document-row" data-document-row>
                            <div>
                                <label class="form-label fw-semibold">Document Name</label>
                                <input type="text" name="supporting_documents[0][title]" class="form-control" placeholder="Example: signed statement">
                            </div>
                            <div>
                                <label class="form-label fw-semibold">File</label>
                                <input type="file" name="supporting_documents[0][file]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.zip">
                            </div>
                            <button type="button" class="btn btn-outline-danger grm-document-remove" data-remove-document title="Remove document">
                                <i class="feather-trash-2"></i>
                            </button>
                        </div>
                    </div>

                    @if ($isPublicSubmission)
                        <div class="grm-submit-panel">
                            <div class="grm-submit-assurance">
                                <i class="feather-lock"></i>
                                <span>Your information is sent securely to the<br class="d-none d-md-block"> ATTP grievance team.</span>
                            </div>
                            <button type="submit" class="btn btn-success grm-submit-button">
                                Submit Grievance <i class="feather-arrow-right ms-2"></i>
                            </button>
                        </div>
                    @else
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-success px-4">
                                <i class="feather-save me-1"></i> Submit Grievance
                            </button>
                        </div>
                    @endif
                </div>
            </form>

            <aside class="{{ $isPublicSubmission ? 'grm-public-aside' : '' }}">
                @if ($isPublicSubmission)
                    <div class="grm-public-privacy-card">
                        <span class="grm-public-privacy-icon"><i class="feather-shield"></i></span>
                        <h3>Your privacy matters</h3>
                        <p>You may submit anonymously. Your identity will be hidden, while one encrypted contact method is retained privately so the grievance team can respond.</p>
                    </div>
                @endif
                <div class="grm-card">
                    <div class="grm-card-header">
                        @if ($isPublicSubmission)
                            <span class="grm-form-kicker">After you submit</span>
                        @endif
                        <h6 class="fw-bold mb-0">{{ $isPublicSubmission ? 'What happens next?' : 'Case Tracking Flow' }}</h6>
                    </div>
                    <div class="grm-card-body">
                        @if ($isPublicSubmission)
                            <div class="grm-public-timeline">
                                <div class="grm-public-timeline-item">
                                    <span class="grm-public-timeline-dot">1</span>
                                    <div><strong>Your case is registered</strong><p>A unique case number is created immediately.</p></div>
                                </div>
                                <div class="grm-public-timeline-item">
                                    <span class="grm-public-timeline-dot">2</span>
                                    <div><strong>The right officer is notified</strong><p>Your grievance is routed to the responsible program officer.</p></div>
                                </div>
                                <div class="grm-public-timeline-item">
                                    <span class="grm-public-timeline-dot">3</span>
                                    <div><strong>The case is reviewed</strong><p>Incident details and supporting evidence are assessed.</p></div>
                                </div>
                                <div class="grm-public-timeline-item">
                                    <span class="grm-public-timeline-dot">4</span>
                                    <div><strong>You receive a response</strong><p>Where contact details are available, the team follows up through your preferred private channel.</p></div>
                                </div>
                            </div>
                        @else
                            <div class="grm-step">
                                <span class="grm-icon"><i class="feather-hash"></i></span>
                                <div>
                                    <strong>Case number</strong>
                                    <p class="text-muted small mb-0">A unique Grievance Redress Mechanism reference is generated immediately.</p>
                                </div>
                            </div>
                            <div class="grm-step">
                                <span class="grm-icon"><i class="feather-clock"></i></span>
                                <div>
                                    <strong>Response clock</strong>
                                    <p class="text-muted small mb-0">Deadlines use the standard response clock until a grievance officer reviews and classifies the case.</p>
                                </div>
                            </div>
                            <div class="grm-step">
                                <span class="grm-icon"><i class="feather-bar-chart-2"></i></span>
                                <div>
                                    <strong>Email notifications</strong>
                                    <p class="text-muted small mb-0">The submitter receives an acknowledgement and the responsible program Grievance Redress Mechanism officer is notified.</p>
                                </div>
                            </div>
                            <div class="grm-step">
                                <span class="grm-icon"><i class="feather-shield"></i></span>
                                <div>
                                    <strong>Anonymous option</strong>
                                    <p class="text-muted small mb-0">When enabled, identity fields are removed while one encrypted, confidential reply contact is retained.</p>
                                </div>
                            </div>
                            <div class="grm-step">
                                <span class="grm-icon"><i class="feather-paperclip"></i></span>
                                <div>
                                    <strong>Evidence upload</strong>
                                    <p class="text-muted small mb-0">Supporting files are stored privately and linked to the generated case number.</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    </div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const anonymousSwitch = document.getElementById('anonymousSwitch');
            const complainantFields = document.getElementById('complainantFields');
            const anonymousNote = document.getElementById('anonymousNote');
            const identityInputs = Array.from(document.querySelectorAll('[data-anonymous-field]'));
            const privacyModalElement = document.getElementById('anonymousPrivacyModal');

            // Bootstrap modals must live at the document root. Keeping this dialog
            // inside the intake card traps it below the backdrop's stacking layer.
            if (privacyModalElement && privacyModalElement.parentElement !== document.body) {
                document.body.appendChild(privacyModalElement);
            }

            const privacyModal = privacyModalElement && window.bootstrap ? new bootstrap.Modal(privacyModalElement) : null;
            const contactMethod = document.getElementById('anonymousContactMethod');
            const contactValue = document.getElementById('anonymousContactValue');
            const contactLabel = document.getElementById('anonymousContactLabel');
            const contactError = document.getElementById('anonymousContactError');
            const modalConfirm = document.getElementById('anonymousModalConfirm');
            const modalCancel = document.getElementById('anonymousModalCancel');
            const modalClose = document.getElementById('anonymousModalClose');
            const editAnonymousContact = document.getElementById('editAnonymousContact');
            const submissionForm = document.getElementById('grmSubmissionForm');
            let anonymousConfirmed = Boolean(anonymousSwitch?.checked && contactMethod?.value && contactValue?.value.trim());

            function updateContactPrompt() {
                const isEmail = contactMethod?.value === 'email';
                if (contactLabel) contactLabel.textContent = isEmail ? 'Private email address *' : (contactMethod?.value === 'phone' ? 'Private phone number *' : 'Email address or phone number *');
                if (contactValue) {
                    contactValue.type = isEmail ? 'email' : 'text';
                    contactValue.inputMode = isEmail ? 'email' : (contactMethod?.value === 'phone' ? 'tel' : 'text');
                    contactValue.placeholder = isEmail ? 'name@example.org' : (contactMethod?.value === 'phone' ? '+254 ...' : 'Enter your private reply contact');
                }
            }

            function anonymousContactIsValid() {
                const method = contactMethod?.value || '';
                const value = contactValue?.value.trim() || '';
                const emailIsValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
                const phoneIsValid = /^[+()\d\s.-]{7,60}$/.test(value);
                const valid = (method === 'email' && emailIsValid) || (method === 'phone' && phoneIsValid);

                contactMethod?.classList.toggle('is-invalid', !method);
                contactValue?.classList.toggle('is-invalid', !valid);
                if (contactError) contactError.textContent = method === 'email'
                    ? 'Enter a valid private email address.'
                    : (method === 'phone' ? 'Enter a valid private phone number.' : 'Choose a reply method and enter a valid contact.');

                return valid;
            }

            function syncAnonymousMode() {
                const isAnonymous = anonymousSwitch && anonymousSwitch.checked;

                complainantFields?.classList.toggle('is-anonymous', isAnonymous);
                anonymousNote?.classList.toggle('is-visible', isAnonymous);

                identityInputs.forEach((input) => {
                    if (isAnonymous) {
                        input.dataset.previousValue = input.value;
                        input.value = '';
                        input.disabled = true;
                    } else {
                        input.disabled = false;
                        if (!input.value && input.dataset.previousValue) {
                            input.value = input.dataset.previousValue;
                        }
                    }
                });

                if (contactMethod) contactMethod.required = Boolean(isAnonymous);
                if (contactValue) contactValue.required = Boolean(isAnonymous);
            }

            anonymousSwitch?.addEventListener('change', function () {
                if (anonymousSwitch.checked && !anonymousConfirmed) {
                    anonymousSwitch.checked = false;
                    syncAnonymousMode();
                    if (privacyModal) {
                        privacyModal.show();
                    } else {
                        anonymousSwitch.checked = window.confirm('Your identity will be hidden, but ATTP requires one private contact method in case a response is available. Continue?');
                        anonymousConfirmed = anonymousSwitch.checked;
                        syncAnonymousMode();
                    }
                    return;
                }

                if (!anonymousSwitch.checked) anonymousConfirmed = false;
                syncAnonymousMode();
            });
            contactMethod?.addEventListener('change', updateContactPrompt);
            contactValue?.addEventListener('input', function () {
                contactValue.classList.remove('is-invalid');
            });
            modalConfirm?.addEventListener('click', function () {
                if (!anonymousContactIsValid()) return;

                anonymousConfirmed = true;
                if (anonymousSwitch) anonymousSwitch.checked = true;
                syncAnonymousMode();
                privacyModal?.hide();
            });
            [modalCancel, modalClose].forEach((button) => button?.addEventListener('click', function () {
                if (!anonymousConfirmed && anonymousSwitch) anonymousSwitch.checked = false;
                syncAnonymousMode();
            }));
            editAnonymousContact?.addEventListener('click', function () {
                privacyModal?.show();
            });
            submissionForm?.addEventListener('submit', function (event) {
                if (anonymousSwitch?.checked && !anonymousContactIsValid()) {
                    event.preventDefault();
                    privacyModal?.show();
                }
            });
            updateContactPrompt();
            syncAnonymousMode();

            const documentRows = document.getElementById('grmDocumentRows');
            const addDocumentButton = document.getElementById('addGrmDocument');

            function renumberDocumentRows() {
                if (!documentRows) return;

                Array.from(documentRows.querySelectorAll('[data-document-row]')).forEach((row, index) => {
                    const titleInput = row.querySelector('input[type="text"]');
                    const fileInput = row.querySelector('input[type="file"]');

                    if (titleInput) {
                        titleInput.name = `supporting_documents[${index}][title]`;
                    }

                    if (fileInput) {
                        fileInput.name = `supporting_documents[${index}][file]`;
                    }
                });
            }

            function createDocumentRow() {
                const template = documentRows?.querySelector('[data-document-row]');
                if (!template) return null;

                const row = template.cloneNode(true);
                row.querySelectorAll('input').forEach((input) => {
                    input.value = '';
                });

                return row;
            }

            addDocumentButton?.addEventListener('click', function () {
                const row = createDocumentRow();
                if (!row || !documentRows) return;

                documentRows.appendChild(row);
                renumberDocumentRows();
            });

            documentRows?.addEventListener('click', function (event) {
                const removeButton = event.target.closest('[data-remove-document]');
                if (!removeButton) return;

                const rows = Array.from(documentRows.querySelectorAll('[data-document-row]'));
                const row = removeButton.closest('[data-document-row]');

                if (rows.length <= 1) {
                    row?.querySelectorAll('input').forEach((input) => {
                        input.value = '';
                    });
                    return;
                }

                row?.remove();
                renumberDocumentRows();
            });
        });
    </script>
@endpush
