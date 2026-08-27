<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verify Login | {{ config('app.name', 'ATTP') }}</title>
    <link rel="icon" href="{{ asset('assets/images/au.png') }}" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --green: #006B3F;
            --green-dark: #004d2e;
            --blue: #1d4ed8;
            --slate: #0f172a;
            --muted: #64748b;
            --border: #e5e7eb;
            --danger: #dc2626;
            --success: #15803d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, Arial, sans-serif;
            color: var(--slate);
            background:
                radial-gradient(circle at top left, rgba(14, 165, 233, .16), transparent 34%),
                linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .shell {
            width: min(100%, 940px);
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .16);
        }

        .brand {
            padding: 38px;
            color: #fff;
            background: linear-gradient(145deg, var(--green-dark) 0%, var(--green) 58%, #0ea5e9 100%);
            position: relative;
            overflow: hidden;
        }

        .brand::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            right: -90px;
            bottom: -90px;
            border-radius: 999px;
            border: 44px solid rgba(255, 255, 255, .08);
        }

        .brand-content {
            position: relative;
            z-index: 1;
        }

        .logo {
            width: 78px;
            height: 78px;
            object-fit: contain;
            margin-bottom: 20px;
        }

        .brand h1 {
            margin: 0 0 12px;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.05;
            color: #fff;
        }

        .brand p {
            margin: 0;
            color: rgba(255, 255, 255, .84);
            line-height: 1.7;
        }

        .panel {
            padding: 38px;
        }

        .eyebrow {
            display: inline-flex;
            padding: 6px 12px;
            border-radius: 999px;
            color: var(--blue);
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        h2 {
            margin: 18px 0 8px;
            font-size: 28px;
            color: var(--slate);
        }

        .muted {
            margin: 0 0 22px;
            color: var(--muted);
            line-height: 1.65;
        }

        .notice {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            border: 1px solid var(--border);
        }

        .notice.success {
            color: var(--success);
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .notice.warning {
            color: #92400e;
            background: #fffbeb;
            border-color: #fde68a;
        }

        .notice.error {
            color: var(--danger);
            background: #fef2f2;
            border-color: #fecaca;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: #334155;
        }

        input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 15px 16px;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: .32em;
            text-align: center;
            color: var(--slate);
            outline: none;
        }

        input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(29, 78, 216, .12);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
        }

        button,
        .link-button {
            border: 0;
            border-radius: 12px;
            padding: 13px 16px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }

        button.primary {
            color: #fff;
            background: var(--blue);
        }

        button.secondary,
        .link-button {
            color: var(--slate);
            background: #f8fafc;
            border: 1px solid #cbd5e1;
        }

        .hint {
            margin-top: 16px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .signout-row {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
        }

        button.danger {
            color: #b91c1c;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        @media (max-width: 820px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .brand,
            .panel {
                padding: 28px;
            }
        }
    </style>
</head>
<body>
    @include('layouts.partials.impersonation-banner')
    <main class="shell">
        <section class="brand">
            <div class="brand-content">
                <img class="logo" src="{{ asset('assets/images/au.png') }}" alt="ATTP">
                <h1>Secure Login Verification</h1>
                <p>Enter the six-digit code sent to your email address to continue into the ATTP portal.</p>
            </div>
        </section>

        <section class="panel">
            <span class="eyebrow">One-Time Code</span>
            <h2>Check your email</h2>
            <p class="muted">
                We sent a verification code to <strong>{{ $user->email }}</strong>.
                The code protects your account and expires shortly.
            </p>

            @if (session('success'))
                <div class="notice success">{{ session('success') }}</div>
            @endif

            @if (session('warning'))
                <div class="notice warning">{{ session('warning') }}</div>
            @endif

            @if (session('otpSent') || $otpSent)
                <div class="notice success">A fresh verification code has been sent.</div>
            @endif

            @if (app()->environment(['local', 'testing']) && session('devOtpCode'))
                <div class="notice warning">
                    Local development OTP: <strong>{{ session('devOtpCode') }}</strong>
                </div>
            @endif

            @if (isset($errors) && $errors->any())
                <div class="notice error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('security.otp.verify') }}">
                @csrf
                <label for="otp_code">Verification code</label>
                <input
                    id="otp_code"
                    name="otp_code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    placeholder="000000"
                    required
                    autofocus
                >

                <div class="actions">
                    <button class="primary" type="submit">Verify and continue</button>
                </div>
            </form>

            <form method="POST" action="{{ route('security.otp.resend') }}" class="actions">
                @csrf
                <button class="secondary" type="submit">Send a new code</button>
                <a class="link-button" href="{{ url()->previous() ?: route('login') }}">Go back</a>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="signout-row">
                @csrf
                <button class="danger" type="submit">Sign out and return to login</button>
            </form>

            <p class="hint">
                If the code is not in your inbox, check spam or wait a few seconds before requesting a new one.
            </p>
        </section>
    </main>
</body>
</html>
