<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>Reset Password – ATTP Portal</title>
    <link rel="icon" href="{{ asset('assets/images/au.png') }}" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --au-green:       #006B3F;
            --au-green-dark:  #004d2e;
            --au-green-light: #009A44;
            --gold:           #fbbc05;
            --red:            #dc2626;
            --red-bg:         #fef2f2;
            --gray-50:        #f9fafb;
            --gray-100:       #f3f4f6;
            --gray-300:       #d1d5db;
            --gray-500:       #6b7280;
            --gray-700:       #374151;
            --gray-900:       #111827;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
        }

        /* ── LEFT BRAND PANEL ── */
        .brand-panel {
            flex: 0 0 420px;
            background: linear-gradient(160deg, var(--au-green-dark) 0%, var(--au-green) 65%, #007a48 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.4rem;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .brand-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        .brand-panel-inner {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .brand-logo {
            width: 88px;
            height: 88px;
            object-fit: contain;
            filter: drop-shadow(0 4px 16px rgba(0,0,0,.3));
            margin-bottom: 1.5rem;
        }

        .brand-panel h2 {
            font-size: 1.7rem;
            font-weight: 700;
            margin: 0 0 .5rem;
            color: var(--gold);
            letter-spacing: -.3px;
        }

        .brand-panel p {
            font-size: .92rem;
            color: rgba(255,255,255,.78);
            line-height: 1.65;
            margin: 0 0 2rem;
        }

        .brand-steps {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-align: left;
            width: 100%;
        }

        .brand-steps li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: .88rem;
            color: rgba(255,255,255,.88);
            line-height: 1.5;
        }

        .step-num {
            flex-shrink: 0;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--gold);
            color: #1a2e22;
            font-weight: 800;
            font-size: .8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 1px;
        }

        /* ── RIGHT FORM PANEL ── */
        .form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            background: var(--gray-50);
        }

        .form-card {
            width: 100%;
            max-width: 440px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--au-green);
            text-decoration: none;
            font-size: .85rem;
            font-weight: 600;
            margin-bottom: 1.8rem;
            opacity: .85;
            transition: opacity .2s;
        }
        .back-link:hover { opacity: 1; }

        .form-card h1 {
            font-size: 1.65rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0 0 .5rem;
        }

        .form-card .subtitle {
            color: var(--gray-500);
            font-size: .92rem;
            line-height: 1.6;
            margin: 0 0 1.8rem;
        }

        /* Status / success message */
        .status-msg {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: .9rem;
            font-weight: 500;
            margin-bottom: 1.4rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .status-msg svg { flex-shrink: 0; margin-top: 1px; }

        /* Error message */
        .field-error {
            color: var(--red);
            font-size: .82rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .field-group { margin-bottom: 1.2rem; }

        label {
            display: block;
            font-size: .85rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
        }

        .field-input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--gray-300);
            border-radius: 10px;
            font: inherit;
            font-size: .95rem;
            color: var(--gray-900);
            background: #fff;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .field-input:focus {
            border-color: var(--au-green);
            box-shadow: 0 0 0 3px rgba(0,107,63,.12);
        }
        .field-input.has-error {
            border-color: var(--red);
            background: var(--red-bg);
        }
        .field-input.has-error:focus {
            box-shadow: 0 0 0 3px rgba(220,38,38,.1);
        }

        .submit-btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--au-green), var(--au-green-light));
            color: #fff;
            font: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: .2px;
            transition: opacity .2s, transform .1s;
            margin-top: .4rem;
        }
        .submit-btn:hover { opacity: .92; }
        .submit-btn:active { transform: scale(.99); }
        .submit-btn:disabled { opacity: .65; cursor: not-allowed; }

        .divider {
            text-align: center;
            color: var(--gray-500);
            font-size: .82rem;
            margin: 1.2rem 0;
            position: relative;
        }
        .divider::before, .divider::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 42%;
            height: 1px;
            background: var(--gray-300);
        }
        .divider::before { left: 0; }
        .divider::after { right: 0; }

        .login-link {
            display: block;
            text-align: center;
            color: var(--au-green);
            font-weight: 600;
            font-size: .9rem;
            text-decoration: none;
        }
        .login-link:hover { text-decoration: underline; }

        /* ── RESPONSIVE ── */
        @media (max-width: 820px) {
            body { flex-direction: column; }
            .brand-panel { flex: none; padding: 2.5rem 2rem; }
            .brand-steps { display: none; }
            .form-panel { padding: 2.5rem 1.5rem; }
        }
    </style>
</head>
<body>

<!-- LEFT BRAND PANEL -->
<div class="brand-panel">
    <div class="brand-panel-inner">
        <img class="brand-logo" src="{{ asset('assets/images/au.png') }}" alt="ATTP Logo">
        <h2>Reset your password</h2>
        <p>We'll send a secure link to your registered email address so you can choose a new password.</p>

        <ul class="brand-steps">
            <li>
                <span class="step-num">1</span>
                <span>Enter the email address linked to your ATTP account below.</span>
            </li>
            <li>
                <span class="step-num">2</span>
                <span>Check your inbox for a secure password-reset link (also check spam).</span>
            </li>
            <li>
                <span class="step-num">3</span>
                <span>Click the link and set a strong new password to regain access.</span>
            </li>
        </ul>
    </div>
</div>

<!-- RIGHT FORM PANEL -->
<div class="form-panel">
    <div class="form-card">

        <a class="back-link" href="{{ route('login') }}">
            &#8592; Back to login
        </a>

        <h1>Forgot password?</h1>
        <p class="subtitle">No problem. Enter your email and we'll send you a reset link.</p>

        @if (session('status'))
            <div class="status-msg">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#166534" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" id="resetForm" novalidate>
            @csrf

            <div class="field-group">
                <label for="email">Email address</label>
                <input
                    id="email"
                    class="field-input {{ $errors->has('email') ? 'has-error' : '' }}"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    maxlength="255"
                    autocomplete="email"
                    autofocus
                    placeholder="you@example.com"
                >
                @error('email')
                    <div class="field-error">
                        <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <button class="submit-btn" type="submit" id="submitBtn">
                Send reset link
            </button>
        </form>

        <div class="divider">or</div>
        <a class="login-link" href="{{ route('login') }}">Return to login</a>

    </div>
</div>

<script>
    document.getElementById('resetForm').addEventListener('submit', function() {
        var btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Sending…';
    });
</script>
</body>
</html>
