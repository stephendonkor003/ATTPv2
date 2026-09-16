<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>Verify your email &ndash; ATTP Portal</title>
    <link rel="icon" href="{{ asset('assets/images/au.png') }}" type="image/png">
    <style>
        :root {
            --au-green: #006b3f;
            --au-green-dark: #004d2e;
            --au-green-light: #009a44;
            --gold: #fbbc05;
            --ink: #14211b;
            --muted: #647067;
            --line: #dfe7e2;
            --surface: #ffffff;
            --canvas: #f4f7f5;
            --success: #166534;
            --success-bg: #f0fdf4;
            --danger: #b42318;
            --danger-bg: #fff1f0;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background: var(--canvas);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .page-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(320px, 430px) minmax(0, 1fr);
        }

        .brand-panel {
            position: relative;
            isolation: isolate;
            display: flex;
            align-items: center;
            overflow: hidden;
            padding: 52px;
            background: linear-gradient(150deg, var(--au-green-dark), var(--au-green) 62%, #08794b);
            color: #fff;
        }

        .brand-panel::before,
        .brand-panel::after {
            position: absolute;
            z-index: -1;
            content: "";
            border-radius: 999px;
            border: 54px solid rgba(255, 255, 255, .055);
        }

        .brand-panel::before {
            width: 360px;
            height: 360px;
            right: -190px;
            top: -150px;
        }

        .brand-panel::after {
            width: 300px;
            height: 300px;
            left: -170px;
            bottom: -150px;
        }

        .brand-content {
            width: 100%;
            max-width: 320px;
            margin: 0 auto;
        }

        .brand-logo {
            width: 84px;
            height: 84px;
            margin-bottom: 30px;
            object-fit: contain;
            filter: drop-shadow(0 8px 20px rgba(0, 0, 0, .25));
        }

        .brand-kicker {
            margin: 0 0 10px;
            color: var(--gold);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .brand-panel h2 {
            margin: 0;
            font-size: clamp(29px, 3vw, 38px);
            line-height: 1.12;
            letter-spacing: -.035em;
        }

        .brand-copy {
            margin: 18px 0 30px;
            color: rgba(255, 255, 255, .78);
            font-size: 15px;
            line-height: 1.7;
        }

        .trust-list {
            display: grid;
            gap: 14px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .trust-list li {
            display: grid;
            grid-template-columns: 30px 1fr;
            gap: 11px;
            align-items: center;
            color: rgba(255, 255, 255, .9);
            font-size: 13px;
            line-height: 1.45;
        }

        .trust-icon {
            display: grid;
            width: 30px;
            height: 30px;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 9px;
            background: rgba(255, 255, 255, .1);
            color: var(--gold);
            font-weight: 800;
        }

        .content-panel {
            display: grid;
            place-items: center;
            padding: 42px 24px;
        }

        .card {
            width: min(100%, 520px);
            padding: clamp(28px, 5vw, 46px);
            border: 1px solid rgba(0, 77, 46, .08);
            border-radius: 24px;
            background: var(--surface);
            box-shadow: 0 24px 70px rgba(25, 54, 39, .1), 0 2px 8px rgba(25, 54, 39, .04);
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            padding: 7px 11px;
            border-radius: 999px;
            background: #eaf7ef;
            color: var(--au-green-dark);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--au-green-light);
            box-shadow: 0 0 0 4px rgba(0, 154, 68, .12);
        }

        h1 {
            margin: 0;
            color: var(--ink);
            font-size: clamp(30px, 5vw, 40px);
            line-height: 1.1;
            letter-spacing: -.04em;
        }

        .intro {
            margin: 16px 0 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
        }

        .email-box {
            margin: 24px 0;
            padding: 16px 18px;
            border: 1px solid var(--line);
            border-radius: 13px;
            background: #f8faf8;
        }

        .email-label {
            display: block;
            margin-bottom: 4px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .email-value {
            overflow-wrap: anywhere;
            font-size: 15px;
            font-weight: 700;
        }

        .alert {
            margin: 0 0 20px;
            padding: 14px 16px;
            border: 1px solid;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.55;
        }

        .alert-success {
            border-color: #bbf7d0;
            background: var(--success-bg);
            color: var(--success);
        }

        .alert-error {
            border-color: #fecaca;
            background: var(--danger-bg);
            color: var(--danger);
        }

        .alert-error ul {
            margin: 0;
            padding-left: 18px;
        }

        .actions {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: stretch;
        }

        .actions form {
            margin: 0;
        }

        button {
            min-height: 48px;
            border-radius: 11px;
            font: inherit;
            font-size: 14px;
            font-weight: 750;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
        }

        button:hover:not(:disabled) {
            transform: translateY(-1px);
        }

        button:focus-visible {
            outline: 3px solid rgba(0, 154, 68, .25);
            outline-offset: 2px;
        }

        button:disabled {
            cursor: wait;
            opacity: .7;
        }

        .primary-button {
            width: 100%;
            padding: 0 20px;
            border: 0;
            background: linear-gradient(125deg, var(--au-green), var(--au-green-light));
            color: #fff;
            box-shadow: 0 10px 24px rgba(0, 107, 63, .2);
        }

        .secondary-button {
            padding: 0 18px;
            border: 1px solid var(--line);
            background: #fff;
            color: #46534b;
        }

        .help-text {
            margin: 22px 0 0;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        @media (max-width: 820px) {
            .page-shell {
                grid-template-columns: 1fr;
            }

            .brand-panel {
                padding: 30px 24px;
            }

            .brand-content {
                display: grid;
                grid-template-columns: 60px 1fr;
                gap: 0 18px;
                max-width: 520px;
            }

            .brand-logo {
                grid-row: 1 / span 3;
                width: 60px;
                height: 60px;
                margin: 0;
            }

            .brand-panel h2 {
                font-size: 25px;
            }

            .brand-copy {
                margin: 8px 0 0;
            }

            .trust-list {
                display: none;
            }

            .content-panel {
                padding: 28px 16px;
            }
        }

        @media (max-width: 480px) {
            .brand-kicker {
                display: none;
            }

            .actions {
                grid-template-columns: 1fr;
            }

            .secondary-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<main class="page-shell">
    <aside class="brand-panel" aria-label="ATTP account security">
        <div class="brand-content">
            <img class="brand-logo" src="{{ asset('assets/images/au.png') }}" alt="Africa Think Tank Platform">
            <p class="brand-kicker">Account security</p>
            <h2>One last step before your workspace</h2>
            <p class="brand-copy">Email verification protects your account and ensures important platform notices reach the right person.</p>

            <ul class="trust-list">
                <li><span class="trust-icon" aria-hidden="true">&#10003;</span><span>The verification link is securely signed.</span></li>
                <li><span class="trust-icon" aria-hidden="true">&#10003;</span><span>Links expire automatically for your protection.</span></li>
                <li><span class="trust-icon" aria-hidden="true">&#10003;</span><span>Your password is never included in the email.</span></li>
            </ul>
        </div>
    </aside>

    <section class="content-panel">
        <div class="card">
            <div class="status-chip"><span class="status-dot" aria-hidden="true"></span>Email verification required</div>
            <h1>Verify your email address</h1>
            <p class="intro">Confirm your email address to continue to the protected areas of the ATTP Portal. We will send a secure, time-limited link when you use the button below.</p>

            <div class="email-box">
                <span class="email-label">Verification email</span>
                <span class="email-value">{{ auth()->user()?->email }}</span>
            </div>

            @if (session('status') === 'verification-link-sent')
                <div class="alert alert-success" role="status">
                    A new verification link has been sent. Please check your inbox and spam folder.
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="actions">
                <form method="POST" action="{{ route('verification.send') }}" data-resend-form>
                    @csrf
                    <button class="primary-button" type="submit" data-resend-button>Send a new verification link</button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="secondary-button" type="submit">Sign out</button>
                </form>
            </div>

            <p class="help-text">The link may take a few minutes to arrive. If the address above is incorrect or you still cannot receive the message, sign out and contact your ATTP administrator or support team.</p>
        </div>
    </section>
</main>

<script>
    document.querySelector('[data-resend-form]')?.addEventListener('submit', function () {
        var button = document.querySelector('[data-resend-button]');

        if (button) {
            button.disabled = true;
            button.textContent = 'Sending verification link...';
        }
    });
</script>
</body>
</html>
