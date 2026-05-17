<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>Secure Login – ATTP Portal</title>
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

        /* ===================================================
           LEFT BRAND PANEL
        =================================================== */
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

        /* subtle dot pattern */
        .brand-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        /* decorative ring */
        .brand-panel::after {
            content: "";
            position: absolute;
            bottom: -120px;
            right: -120px;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            border: 60px solid rgba(255,255,255,0.05);
        }

        .brand-inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        .brand-logo {
            width: 88px;
            height: 88px;
            object-fit: contain;
            margin-bottom: 1.4rem;
            filter: drop-shadow(0 6px 16px rgba(0,0,0,0.35));
        }

        .brand-title {
            font-size: 1.55rem;
            font-weight: 700;
            text-align: center;
            color: #fff;
            line-height: 1.25;
            margin-bottom: 0.4rem;
        }

        .brand-title span { color: var(--gold); }

        .brand-sub {
            font-size: 0.79rem;
            color: rgba(255,255,255,0.62);
            text-align: center;
            letter-spacing: 0.01em;
            margin-bottom: 2.8rem;
        }

        /* security badges */
        .sec-badges {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            margin-bottom: 2.5rem;
        }

        .sec-badge {
            display: flex;
            align-items: flex-start;
            gap: 0.9rem;
            background: rgba(255,255,255,0.09);
            border: 1px solid rgba(255,255,255,0.13);
            border-radius: 10px;
            padding: 0.8rem 0.95rem;
            transition: background 0.2s;
        }

        .sec-badge:hover { background: rgba(255,255,255,0.13); }

        .sec-icon {
            width: 34px;
            height: 34px;
            background: rgba(255,255,255,0.13);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1rem;
        }

        .sec-text h4 {
            font-size: 0.81rem;
            font-weight: 600;
            color: #fff;
            margin: 0 0 0.2rem;
        }

        .sec-text p {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.60);
            margin: 0;
            line-height: 1.45;
        }

        .brand-foot {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.35);
            text-align: center;
        }

        /* ===================================================
           RIGHT FORM PANEL
        =================================================== */
        .form-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-50);
            padding: 2rem 1.5rem;
        }

        .form-card {
            background: #fff;
            border-radius: 20px;
            padding: 2.8rem 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 4px 32px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.81rem;
            color: var(--gray-500);
            text-decoration: none;
            margin-bottom: 1.8rem;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--au-green); }

        .form-head h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0 0 0.35rem;
        }

        .form-head p {
            font-size: 0.87rem;
            color: var(--gray-500);
            margin: 0 0 1.8rem;
        }

        /* alerts */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            font-size: 0.83rem;
            margin-bottom: 1.2rem;
            line-height: 1.5;
        }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-info    { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .alert-error   { background: var(--red-bg); color: var(--red); border: 1px solid #fecaca; }

        /* fields */
        .field-group { margin-bottom: 1.15rem; }

        .field-group label {
            display: block;
            font-size: 0.83rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.42rem;
        }

        .input-wrap { position: relative; }

        .input-wrap input {
            width: 100%;
            padding: 0.78rem 1rem;
            border: 1.5px solid var(--gray-300);
            border-radius: 10px;
            font-size: 0.91rem;
            font-family: inherit;
            color: var(--gray-900);
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .has-toggle input { padding-right: 2.8rem; }

        .input-wrap input::placeholder { color: #9ca3af; }

        .input-wrap input:focus {
            border-color: var(--au-green);
            box-shadow: 0 0 0 3px rgba(0, 107, 63, 0.13);
        }

        .input-wrap input.is-error {
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.10);
        }

        .toggle-pw {
            position: absolute;
            right: 0.72rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray-500);
            padding: 0.2rem;
            display: flex;
            align-items: center;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: var(--au-green); }

        .field-error {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            color: var(--red);
            font-size: 0.77rem;
            margin-top: 0.38rem;
        }

        /* remember + forgot row */
        .form-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.83rem;
            color: var(--gray-700);
            cursor: pointer;
            user-select: none;
        }

        .remember-label input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: var(--au-green);
            cursor: pointer;
            flex-shrink: 0;
        }

        .forgot-link {
            font-size: 0.81rem;
            color: var(--au-green);
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-link:hover { text-decoration: underline; }

        /* submit */
        .btn-submit {
            width: 100%;
            padding: 0.87rem;
            background: linear-gradient(120deg, var(--au-green) 0%, var(--au-green-light) 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.96rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 14px rgba(0, 107, 63, 0.30);
            letter-spacing: 0.01em;
        }
        .btn-submit:hover { opacity: 0.92; transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,107,63,0.35); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.55; cursor: not-allowed; transform: none; box-shadow: none; }

        /* bottom notice */
        .sec-notice {
            margin-top: 1.6rem;
            padding: 0.85rem 1rem;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            font-size: 0.75rem;
            color: #166534;
            line-height: 1.55;
            display: flex;
            gap: 0.5rem;
        }

        /* ===================================================
           RESPONSIVE
        =================================================== */
        @media (max-width: 800px) {
            body { flex-direction: column; }

            .brand-panel {
                flex: none;
                padding: 1.8rem 1.5rem 1.5rem;
            }

            .brand-logo { width: 60px; height: 60px; margin-bottom: 0.9rem; }
            .brand-title { font-size: 1.2rem; }
            .brand-sub { margin-bottom: 0; }
            .sec-badges, .brand-foot { display: none; }
            .brand-panel::after { display: none; }

            .form-card { padding: 2rem 1.4rem; border-radius: 16px; }
        }

        @media (max-width: 420px) {
            .form-panel { padding: 1rem 0.8rem; }
            .form-row { flex-direction: column; align-items: flex-start; gap: 0.6rem; }
        }
    </style>
</head>
<body>

    <!-- ===== LEFT: BRAND PANEL ===== -->
    <div class="brand-panel">
        <div class="brand-inner">
            <img src="{{ asset('assets/images/au.png') }}" alt="ATTP Logo" class="brand-logo">
            <div class="brand-title">ATTP <span>Portal</span></div>
            <div class="brand-sub">African Think Tank Platform Administration</div>

            <div class="sec-badges">
                <div class="sec-badge">
                    <div class="sec-icon">🔐</div>
                    <div class="sec-text">
                        <h4>Two-Factor Authentication</h4>
                        <p>A 6-digit OTP is sent to your registered email after each login.</p>
                    </div>
                </div>
                <div class="sec-badge">
                    <div class="sec-icon">🛡️</div>
                    <div class="sec-text">
                        <h4>Brute-Force Protection</h4>
                        <p>Accounts lock automatically after 5 consecutive failed attempts.</p>
                    </div>
                </div>
                <div class="sec-badge">
                    <div class="sec-icon">🔄</div>
                    <div class="sec-text">
                        <h4>Password Expiry Policy</h4>
                        <p>Passwords expire every 60 days and must be renewed for continued access.</p>
                    </div>
                </div>
                <div class="sec-badge">
                    <div class="sec-icon">🔒</div>
                    <div class="sec-text">
                        <h4>Encrypted Sessions</h4>
                        <p>All sessions are server-side encrypted and invalidated on logout.</p>
                    </div>
                </div>
            </div>

            <div class="brand-foot">© 2026 African Union Commission · ATTP</div>
        </div>
    </div>

    <!-- ===== RIGHT: FORM PANEL ===== -->
    <div class="form-panel">
        <div class="form-card">
            <a href="{{ route('landing.index') }}" class="back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                Back to Homepage
            </a>

            <div class="form-head">
                <h2>Welcome back</h2>
                <p>Sign in to your ATTP account to continue</p>
            </div>

            {{-- Session alerts --}}
            @if (session('status'))
                <div class="alert alert-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    {{ session('status') }}
                </div>
            @endif
            @if (session('info'))
                <div class="alert alert-info">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    {{ session('info') }}
                </div>
            @endif
            @if (session('warning'))
                <div class="alert alert-warning">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    {{ session('warning') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" autocomplete="on" id="loginForm">
                @csrf

                {{-- Honeypot: hidden from real users, bots fill it --}}
                <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                    <label for="website_field">Website</label>
                    <input type="text" id="website_field" name="website" tabindex="-1" autocomplete="off" value="">
                </div>

                {{-- Email --}}
                <div class="field-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrap">
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="your@organisation.com"
                            maxlength="255"
                            class="{{ $errors->has('email') ? 'is-error' : '' }}"
                        >
                    </div>
                    @error('email')
                        <div class="field-error">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="field-group">
                    <label for="password">Password</label>
                    <div class="input-wrap has-toggle">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••••"
                            maxlength="255"
                            class="{{ $errors->has('password') ? 'is-error' : '' }}"
                        >
                        <button type="button" class="toggle-pw" onclick="togglePassword()" aria-label="Toggle password visibility" title="Show / hide password">
                            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <div class="field-error">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                {{-- Remember + Forgot --}}
                <div class="form-row">
                    <label class="remember-label">
                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        Keep me signed in
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
                    @endif
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn-submit" id="submitBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    Sign In Securely
                </button>
            </form>

            <div class="sec-notice">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                Your connection is encrypted. Never share your credentials. Always log out on shared or public devices.
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const open  = document.getElementById('eyeOpen');
            const shut  = document.getElementById('eyeClosed');
            if (input.type === 'password') {
                input.type = 'text';
                open.style.display  = 'none';
                shut.style.display  = '';
            } else {
                input.type = 'password';
                open.style.display  = '';
                shut.style.display  = 'none';
            }
            input.focus();
        }

        // Prevent double-submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 1s linear infinite"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg> Signing in…';
        });
    </script>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</body>
</html>
