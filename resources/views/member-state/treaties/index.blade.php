@extends('layouts.app')

@section('title', 'Member State Treaty Dashboard')

@section('content')
    <style>
        .member-hero {
            background: linear-gradient(135deg, #0f172a 0%, #0f766e 50%, #15803d 100%);
            border-radius: 14px;
            color: #f8fafc;
            padding: 1.25rem;
            display: grid;
            grid-template-columns: 1fr 260px;
            gap: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            position: relative;
            overflow: hidden;
        }

        .member-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(2, 6, 23, 0.34), rgba(2, 6, 23, 0.12));
            pointer-events: none;
        }

        .member-hero>* {
            position: relative;
            z-index: 1;
        }

        .member-hero .hero-kicker {
            font-size: 0.76rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.92);
            margin-bottom: 0.35rem;
        }

        .member-hero h3 {
            margin: 0 0 0.35rem;
            font-weight: 700;
            color: #ffffff;
            text-shadow: 0 1px 3px rgba(2, 6, 23, 0.55);
        }

        .member-hero p {
            margin: 0;
            color: rgba(255, 255, 255, 0.96);
            max-width: 780px;
            text-shadow: 0 1px 2px rgba(2, 6, 23, 0.45);
        }

        .hero-chip-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            margin-top: 0.9rem;
        }

        .hero-chip {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 999px;
            padding: 0.3rem 0.7rem;
            font-size: 0.77rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: #ffffff;
        }

        .member-hero-side {
            background: rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 0.8rem 0.95rem;
            border: 1px solid rgba(255, 255, 255, 0.16);
        }

        .member-hero-side .date-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 0.25rem;
        }

        .member-hero-side .date-value {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
        }

        .member-hero-side .date-note {
            font-size: 0.8rem;
            color: rgba(248, 250, 252, 0.85);
            line-height: 1.35;
        }

        .flag-showcase {
            border: 1px solid #dbeafe;
            border-radius: 14px;
            background: linear-gradient(115deg, #eff6ff 0%, #f0fdfa 45%, #f8fafc 100%);
            padding: 1.15rem;
            margin-bottom: 1rem;
        }

        .flag-showcase-inner {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 1rem;
            align-items: center;
        }

        .flag-wave-frame {
            height: 220px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.15);
            background: #0f172a;
            position: relative;
        }

        .flag-wave-flag {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform-origin: left center;
            animation: flagWave 4.6s ease-in-out infinite;
            filter: saturate(1.1) contrast(1.03);
        }

        .flag-wave-frame::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(100deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.25), rgba(255, 255, 255, 0.06));
            mix-blend-mode: soft-light;
            animation: flagLight 3.8s ease-in-out infinite;
            pointer-events: none;
        }

        .flag-welcome-title {
            color: #0f172a;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .flag-welcome-text {
            color: #334155;
            margin-bottom: 0.45rem;
            line-height: 1.5;
        }

        @keyframes flagWave {
            0%,
            100% {
                transform: perspective(900px) rotateY(0deg) skewY(0deg) scaleX(1);
            }
            25% {
                transform: perspective(900px) rotateY(-9deg) skewY(1.5deg) scaleX(1.01);
            }
            50% {
                transform: perspective(900px) rotateY(4deg) skewY(-1.2deg) scaleX(0.99);
            }
            75% {
                transform: perspective(900px) rotateY(-7deg) skewY(1deg) scaleX(1.01);
            }
        }

        @keyframes flagLight {
            0%,
            100% {
                opacity: 0.65;
                transform: translateX(-8%);
            }
            50% {
                opacity: 1;
                transform: translateX(8%);
            }
        }

        .summary-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
            height: 100%;
        }

        .summary-card .label {
            font-size: 0.78rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .flow-wrap {
            display: grid;
            grid-template-columns: repeat(3, minmax(180px, 1fr));
            gap: 0.75rem;
        }

        .flow-step {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 0.9rem;
            background: #fafafa;
            position: relative;
        }

        .flow-step .step-no {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            color: #fff;
            margin-bottom: 0.55rem;
        }

        .flow-step.sign .step-no {
            background: #0ea5e9;
        }

        .flow-step.ratify .step-no {
            background: #6366f1;
        }

        .flow-step.original .step-no {
            background: #059669;
        }

        .status-pill {
            border-radius: 999px;
            font-size: 0.76rem;
            padding: 0.25rem 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }

        .section-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
        }

        .treaty-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
        }

        @media (max-width: 991.98px) {
            .member-hero {
                grid-template-columns: 1fr;
            }

            .flag-showcase-inner {
                grid-template-columns: 1fr;
            }

            .flag-wave-frame {
                height: 190px;
            }
        }

        @media (max-width: 767.98px) {
            .flow-wrap {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <main class="nxl-container">
        <div class="nxl-content">
            @php
                $accountName = $memberState->name ?? 'Member State';
                $displayName = auth()->user()->name ?? 'Representative';
                $flagUrl = $memberState->flag_url ?? null;
                $hour = now()->hour;
                $welcomeText = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
            @endphp

            <div class="member-hero mb-4">
                <div>
                    <div class="hero-kicker">Member State Treaty Workspace</div>
                    <h3>{{ $welcomeText }}, {{ $displayName }}.</h3>
                    <p>
                        Welcome to the {{ $accountName }} dashboard. Track every treaty step from signature to AU legal
                        original-copy submission.
                    </p>
                    <div class="hero-chip-wrap">
                        <span class="hero-chip"><i class="feather-map-pin"></i> {{ $accountName }}</span>
                        <span class="hero-chip"><i class="feather-repeat"></i> Sign -> Ratify -> Submit Original</span>
                    </div>
                </div>
                <div class="member-hero-side">
                    <div class="date-label">Today</div>
                    <div class="date-value">{{ now()->format('d M Y') }}</div>
                    <div class="date-note">Keep treaty records updated for AU Legal Directorate review and compliance.</div>
                </div>
            </div>

            <div class="flag-showcase">
                <div class="flag-showcase-inner">
                    <div class="flag-wave-frame">
                        @if ($flagUrl)
                            <img src="{{ $flagUrl }}" alt="{{ $accountName }} flag" class="flag-wave-flag">
                        @else
                            <div class="d-flex align-items-center justify-content-center w-100 h-100 text-white fw-semibold">
                                Flag not uploaded
                            </div>
                        @endif
                    </div>
                    <div>
                        <h4 class="flag-welcome-title">Welcome, {{ $accountName }}</h4>
                        <p class="flag-welcome-text">
                            You are now in the official member-state treaty portal. This workspace helps your team manage
                            treaty signature, ratification, and final submission of original copies to the AU Legal Directorate.
                        </p>
                        <p class="flag-welcome-text mb-0">
                            Keep each step up to date so your country's treaty status remains accurate in continental reporting.
                        </p>
                    </div>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('info'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    {{ session('info') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card summary-card h-100">
                        <div class="card-body">
                            <div class="label">Total Treaties</div>
                            <div class="h3 mb-0">{{ $summary['total_treaties'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card h-100">
                        <div class="card-body">
                            <div class="label">Signed</div>
                            <div class="h3 mb-0 text-info">{{ $summary['signed_count'] }}</div>
                            <div class="small text-muted">Pending Sign: {{ $summary['pending_sign_count'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card h-100">
                        <div class="card-body">
                            <div class="label">Ratified</div>
                            <div class="h3 mb-0 text-primary">{{ $summary['ratified_count'] }}</div>
                            <div class="small text-muted">Pending Ratify: {{ $summary['pending_ratification_count'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card h-100">
                        <div class="card-body">
                            <div class="label">Original Submitted</div>
                            <div class="h3 mb-0 text-success">{{ $summary['original_submitted_count'] }}</div>
                            <div class="small text-muted">Pending Submit: {{ $summary['pending_original_submission_count'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card section-card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Treaty Workflow Diagram</h5>
                </div>
                <div class="card-body">
                    <div class="flow-wrap">
                        <div class="flow-step sign">
                            <span class="step-no">1</span>
                            <div class="fw-semibold">Sign Treaty</div>
                            <div class="small text-muted">Upload signed evidence and signing date.</div>
                        </div>
                        <div class="flow-step ratify">
                            <span class="step-no">2</span>
                            <div class="fw-semibold">Ratify Treaty</div>
                            <div class="small text-muted">Can happen months or years after signature.</div>
                        </div>
                        <div class="flow-step original">
                            <span class="step-no">3</span>
                            <div class="fw-semibold">Submit Original Copies</div>
                            <div class="small text-muted">Send originals to AU Legal; completion waits for matching signed and ratified service-code verification.</div>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0 small">
                        This dashboard explicitly supports delayed transitions. A member state can remain in any stage
                        (signed, ratified, or awaiting original submission) for extended periods.
                    </div>
                </div>
            </div>

            <div class="row g-3">
                @forelse ($treaties as $treaty)
                    @php
                        $status = $treaty->memberStateStatuses->first();
                        $stage = 'Not Signed';
                        $progressPercent = 0;
                        $hasOriginalSubmission = !empty($status?->original_document_path);
                        $legalCodesVerified = !empty($status?->signed_service_code_verified_at) && !empty($status?->ratified_service_code_verified_at);
                        $isSignedCodePending = !empty($status?->signed_service_code) && empty($status?->signed_service_code_verified_at);
                        $isRatifiedCodePending = !empty($status?->ratified_service_code) && empty($status?->ratified_service_code_verified_at);
                        $canResendServiceEmail = $isSignedCodePending || $isRatifiedCodePending;
                        if ($status?->is_signed) {
                            $stage = 'Signed';
                            $progressPercent = 33;
                        }
                        if ($status?->is_ratified) {
                            $stage = 'Ratified';
                            $progressPercent = 66;
                        }
                        if ($hasOriginalSubmission) {
                            $stage = 'Original Submitted - Pending AU Legal Verification';
                            $progressPercent = 85;
                        }
                        if ($status?->is_original_submitted && $legalCodesVerified) {
                            $stage = 'Original Submission Verified by AU Legal';
                            $progressPercent = 100;
                        }

                        $signToRatifyDays = ($status?->signed_at && $status?->ratified_at) ? $status->signed_at->diffInDays($status->ratified_at) : null;
                        $ratifyToOriginalDays = ($status?->ratified_at && $status?->original_submitted_at) ? $status->ratified_at->diffInDays($status->original_submitted_at) : null;
                    @endphp
                    <div class="col-12">
                        <div class="card treaty-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                    <div>
                                        <h5 class="mb-1">{{ $treaty->title }}</h5>
                                        <div class="small text-muted">
                                            {{ $treaty->reference_code ?: 'No reference code' }} |
                                            Adoption: {{ optional($treaty->adoption_date)->format('d M Y') ?: '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        @if ($status?->is_original_submitted)
                                            <span class="status-pill bg-success text-white">{{ $stage }}</span>
                                        @elseif($hasOriginalSubmission)
                                            <span class="status-pill bg-warning text-dark">{{ $stage }}</span>
                                        @elseif($status?->is_ratified)
                                            <span class="status-pill bg-primary text-white">{{ $stage }}</span>
                                        @elseif($status?->is_signed)
                                            <span class="status-pill bg-info text-white">{{ $stage }}</span>
                                        @else
                                            <span class="status-pill bg-secondary text-white">{{ $stage }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" style="width: {{ $progressPercent }}%;"
                                            aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressPercent }}">
                                        </div>
                                    </div>
                                    <div class="small text-muted mt-1">Progress: {{ $progressPercent }}%</div>
                                </div>

                                @if ($treaty->description)
                                    <p class="mb-3 text-muted">{{ $treaty->description }}</p>
                                @endif

                                <div class="row g-2 mb-3">
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">Signed Date</div>
                                            <div class="fw-semibold">{{ optional($status?->signed_at)->format('d M Y') ?: '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">Ratified Date</div>
                                            <div class="fw-semibold">{{ optional($status?->ratified_at)->format('d M Y') ?: '-' }}</div>
                                            @if (!is_null($signToRatifyDays))
                                                <div class="small text-warning">Delay from sign: {{ $signToRatifyDays }} day(s)</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">Original Submitted to AU Legal</div>
                                            <div class="fw-semibold">
                                                {{ optional($status?->original_submitted_at)->format('d M Y') ?: '-' }}
                                            </div>
                                            @if ($hasOriginalSubmission && !$legalCodesVerified)
                                                <div class="small text-warning">Pending legal code verification</div>
                                            @elseif($status?->is_original_submitted && $legalCodesVerified)
                                                <div class="small text-success">Verified by AU Legal</div>
                                            @endif
                                            @if (!is_null($ratifyToOriginalDays))
                                                <div class="small text-warning">Delay from ratify: {{ $ratifyToOriginalDays }} day(s)</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if ($treaty->supportingDocuments->count())
                                    <div class="mb-3">
                                        <div class="small fw-semibold text-muted mb-2">Treaty Supporting Documents</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach ($treaty->supportingDocuments as $supportingDocument)
                                                <a href="{{ route('treaties.supporting-documents.download', $supportingDocument->id) }}?download=1"
                                                    class="btn btn-sm btn-outline-secondary">
                                                    <i class="feather-paperclip me-1"></i>
                                                    {{ $supportingDocument->title ?: $supportingDocument->file_name }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="row g-3">
                                    <div class="col-lg-4">
                                        <div class="border rounded p-3 h-100">
                                            <h6 class="mb-2">1. Sign Treaty</h6>
                                            @if ($status?->signed_document_path)
                                                <a href="{{ route('treaty-statuses.documents.download', ['treatyStatus' => $status->id, 'type' => 'signed']) }}?download=1"
                                                    class="btn btn-sm btn-outline-info mb-2">
                                                    <i class="feather-download me-1"></i> Current Signed File
                                                </a>
                                            @endif
                                            @if ($status?->signed_service_code)
                                                <div class="small text-muted mb-1">Signed Proof-of-Service Code</div>
                                                <div class="mb-2"><code>{{ $status->signed_service_code }}</code></div>
                                                @if ($status?->signed_service_code_verified_at)
                                                    <div class="small text-success mb-2">
                                                        AU Legal verified on {{ optional($status->signed_service_code_verified_at)->format('d M Y H:i') }}.
                                                    </div>
                                                @else
                                                    <div class="small text-warning mb-2">Awaiting AU Legal code confirmation.</div>
                                                @endif
                                            @endif
                                            <form method="POST"
                                                action="{{ route('member-state.treaties.status.update', $treaty->id) }}"
                                                enctype="multipart/form-data">
                                                @csrf
                                                <input type="hidden" name="action_type" value="sign">
                                                <div class="mb-2">
                                                    <input type="date" name="action_date" class="form-control form-control-sm">
                                                </div>
                                                <div class="mb-2">
                                                    <input type="file" name="proof_document" class="form-control form-control-sm"
                                                        required>
                                                </div>
                                                <div class="mb-2">
                                                    <textarea name="notes" rows="2" class="form-control form-control-sm"
                                                        placeholder="Signing note (optional)"></textarea>
                                                </div>
                                                <button class="btn btn-sm btn-info text-white">
                                                    <i class="feather-check me-1"></i>
                                                    {{ $status?->is_signed ? 'Update Signature' : 'Mark as Signed' }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="border rounded p-3 h-100">
                                            <h6 class="mb-2">2. Ratify Treaty</h6>
                                            @if ($status?->ratified_document_path)
                                                <a href="{{ route('treaty-statuses.documents.download', ['treatyStatus' => $status->id, 'type' => 'ratified']) }}?download=1"
                                                    class="btn btn-sm btn-outline-primary mb-2">
                                                    <i class="feather-download me-1"></i> Current Ratified File
                                                </a>
                                            @endif
                                            @if ($status?->ratified_service_code)
                                                <div class="small text-muted mb-1">Ratified Proof-of-Service Code</div>
                                                <div class="mb-2"><code>{{ $status->ratified_service_code }}</code></div>
                                                @if ($status?->ratified_service_code_verified_at)
                                                    <div class="small text-success mb-2">
                                                        AU Legal verified on {{ optional($status->ratified_service_code_verified_at)->format('d M Y H:i') }}.
                                                    </div>
                                                @else
                                                    <div class="small text-warning mb-2">Awaiting AU Legal code confirmation.</div>
                                                @endif
                                            @endif

                                            @if ($status?->is_signed)
                                                <form method="POST"
                                                    action="{{ route('member-state.treaties.status.update', $treaty->id) }}"
                                                    enctype="multipart/form-data">
                                                    @csrf
                                                    <input type="hidden" name="action_type" value="ratify">
                                                    <div class="mb-2">
                                                        <input type="date" name="action_date"
                                                            class="form-control form-control-sm">
                                                    </div>
                                                    <div class="mb-2">
                                                        <input type="file" name="proof_document"
                                                            class="form-control form-control-sm" required>
                                                    </div>
                                                    <div class="mb-2">
                                                        <textarea name="notes" rows="2" class="form-control form-control-sm"
                                                            placeholder="Ratification note (optional)"></textarea>
                                                    </div>
                                                    <button class="btn btn-sm btn-primary">
                                                        <i class="feather-award me-1"></i>
                                                        {{ $status?->is_ratified ? 'Update Ratification' : 'Mark as Ratified' }}
                                                    </button>
                                                </form>
                                            @else
                                                <div class="alert alert-warning small mb-0">
                                                    This step opens after signature.
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="border rounded p-3 h-100">
                                            <h6 class="mb-2">3. Submit Original to AU Legal Directorate</h6>
                                            @if ($status?->original_document_path)
                                                <a href="{{ route('treaty-statuses.documents.download', ['treatyStatus' => $status->id, 'type' => 'original']) }}?download=1"
                                                    class="btn btn-sm btn-outline-success mb-2">
                                                    <i class="feather-download me-1"></i> Current Original Submission File
                                                </a>
                                            @endif

                                            @if ($status?->is_ratified)
                                                <form method="POST"
                                                    action="{{ route('member-state.treaties.status.update', $treaty->id) }}"
                                                    enctype="multipart/form-data">
                                                    @csrf
                                                    <input type="hidden" name="action_type" value="submit_original">
                                                    <div class="mb-2">
                                                        <input type="date" name="action_date"
                                                            class="form-control form-control-sm">
                                                    </div>
                                                    <div class="mb-2">
                                                        <input type="file" name="original_document"
                                                            class="form-control form-control-sm" required>
                                                    </div>
                                                    <div class="mb-2">
                                                        <textarea name="notes" rows="2" class="form-control form-control-sm"
                                                            placeholder="Submission note to AU legal directorate"></textarea>
                                                    </div>
                                                    <button class="btn btn-sm btn-success">
                                                        <i class="feather-send me-1"></i>
                                                        {{ $hasOriginalSubmission ? 'Update Submission (Re-verification Required)' : 'Submit Original Copy' }}
                                                    </button>
                                                </form>
                                                @if ($hasOriginalSubmission && !$legalCodesVerified)
                                                    <div class="alert alert-warning small mt-2 mb-0">
                                                        Original copy uploaded. Final completion will be marked only after AU Legal enters matching signed and ratified service codes.
                                                    </div>
                                                @elseif($status?->is_original_submitted && $legalCodesVerified)
                                                    <div class="alert alert-success small mt-2 mb-0">
                                                        Final submission is complete. AU Legal verified both service codes.
                                                    </div>
                                                @else
                                                    <div class="alert alert-info small mt-2 mb-0">
                                                        Keep the signed and ratified proof-of-service codes ready for AU Legal verification.
                                                    </div>
                                                @endif
                                            @else
                                                <div class="alert alert-warning small mb-0">
                                                    This step opens after ratification.
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($canResendServiceEmail)
                                        <div class="col-12">
                                            <div class="border rounded p-3">
                                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                    <div>
                                                        <div class="fw-semibold">Proof-of-Service Email</div>
                                                        <div class="small text-muted">
                                                            Resend to AU Legal if they did not receive the previous email.
                                                            @if ($isSignedCodePending && $isRatifiedCodePending)
                                                                Pending confirmation: Signed and Ratified codes.
                                                            @elseif($isSignedCodePending)
                                                                Pending confirmation: Signed code.
                                                            @elseif($isRatifiedCodePending)
                                                                Pending confirmation: Ratified code.
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <form method="POST"
                                                        action="{{ route('member-state.treaties.status.resend-proof-email', $treaty->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-dark">
                                                            <i class="feather-mail me-1"></i> Resend Email
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            No treaties are available at the moment.
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </main>
@endsection
