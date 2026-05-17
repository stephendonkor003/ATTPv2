<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>Set New Password – ATTP Portal</title>
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

        .brand-tips {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
            text-align: left;
            width: 100%;
        }

        .brand-tips li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .86rem;
            color: rgba(255,255,255,.85);
            line-height: 1.4;
        }

        .tip-icon {
            flex-shrink: 0;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(251,188,5,.2);
            border: 1px solid rgba(251,188,5,.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            color: var(--gold);
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

        .field-wrap {
            position: relative;
        }

        .field-input {
            width: 100%;
            padding: 12px 44px 12px 14px;
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

        .toggle-pw {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: var(--gray-500);
            display: flex;
            align-items: center;
        }
        .toggle-pw:hover { color: var(--au-green); }

        /* strength bar */
        .strength-bar {
            height: 4px;
            border-radius: 2px;
            background: var(--gray-200, #e5e7eb);
            margin-top: 8px;
            overflow: hidden;
        }
        .strength-fill {
            height: 100%;
            border-radius: 2px;
            width: 0;
            transition: width .3s, background .3s;
        }
        .strength-label {
            font-size: .76rem;
            color: var(--gray-500);
            margin-top: 4px;
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

        .back-link {
            display: block;
            text-align: center;
            color: var(--au-green);
            font-weight: 600;
            font-size: .88rem;
            text-decoration: none;
            margin-top: 1.2rem;
        }
        .back-link:hover { text-decoration: underline; }

        /* ── RESPONSIVE ── */
        @media (max-width: 820px) {
            body { flex-direction: column; }
            .brand-panel { flex: none; padding: 2.5rem 2rem; }
            .brand-tips { display: none; }
            .form-panel { padding: 2.5rem 1.5rem; }
        }
    </style>
</head>
<body>

<!-- LEFT BRAND PANEL -->
<div class="brand-panel">
    <div class="brand-panel-inner">
        <img class="brand-logo" src="{{ asset('assets/images/au.png') }}" alt="ATTP Logo">
        <h2>Choose a strong password</h2>
        <p>Your new password protects access to the ATTP portal and all its data.</p>

        <ul class="brand-tips">
            <li>
                <span class="tip-icon">✓</span>
                At least 8 characters long
            </li>
            <li>
                <span class="tip-icon">✓</span>
                Mix uppercase &amp; lowercase letters
            </li>
            <li>
                <span class="tip-icon">✓</span>
                Include numbers and symbols
            </li>
            <li>
                <span class="tip-icon">✓</span>
                Never reuse a previous password
            </li>
        </ul>
    </div>
</div>

<!-- RIGHT FORM PANEL -->
<div class="form-panel">
    <div class="form-card">

        <h1>Set new password</h1>
        <p class="subtitle">Enter and confirm your new password below.</p>

        <form method="POST" action="{{ route('password.store') }}" id="resetForm" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <!-- Email (pre-filled from reset link) -->
            <div class="field-group">
                <label for="email">Email address</label>
                <input
                    id="email"
                    class="field-input {{ $errors->has('email') ? 'has-error' : '' }}"
                    type="email"
                    name="email"
                    value="{{ old('email', $request->email) }}"
                    required
                    maxlength="255"
                    autocomplete="username"
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

            <!-- New password -->
            <div class="field-group">
                <label for="password">New password</label>
                <div class="field-wrap">
                    <input
                        id="password"
                        class="field-input {{ $errors->has('password') ? 'has-error' : '' }}"
                        type="password"
                        name="password"
                        required
                        maxlength="255"
                        autocomplete="new-password"
                        placeholder="Min. 8 characters"
                        oninput="checkStrength(this.value)"
                    >
                    <button type="button" class="toggle-pw" onclick="togglePw('password', 'eyeIcon1')" aria-label="Show password">
                        <svg id="eyeIcon1" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-label" id="strengthLabel"></div>
                @error('password')
                    <div class="field-error">
                        <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <!-- Confirm password -->
            <div class="field-group">
                <label for="password_confirmation">Confirm new password</label>
                <div class="field-wrap">
                    <input
                        id="password_confirmation"
                        class="field-input"
                        type="password"
                        name="password_confirmation"
                        required
                        maxlength="255"
                        autocomplete="new-password"
                        placeholder="Repeat your new password"
                    >
                    <button type="button" class="toggle-pw" onclick="togglePw('password_confirmation', 'eyeIcon2')" aria-label="Show password">
                        <svg id="eyeIcon2" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button class="submit-btn" type="submit" id="submitBtn">
                Reset password
            </button>
        </form>

        <a class="back-link" href="{{ route('login') }}">&#8592; Back to login</a>
    </div>
</div>

<script>
    function togglePw(fieldId, iconId) {
        var field = document.getElementById(fieldId);
        var isText = field.type === 'text';
        field.type = isText ? 'password' : 'text';
        var icon = document.getElementById(iconId);
        icon.innerHTML = isText
            ? '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>'
            : '<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
    }

    function checkStrength(val) {
        var fill = document.getElementById('strengthFill');
        var label = document.getElementById('strengthLabel');
        if (!val) { fill.style.width = '0'; label.textContent = ''; return; }
        var score = 0;
        if (val.length >= 8)  score++;
        if (val.length >= 12) score++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        var levels = [
            { w: '20%', bg: '#ef4444', text: 'Very weak' },
            { w: '40%', bg: '#f97316', text: 'Weak' },
            { w: '60%', bg: '#eab308', text: 'Fair' },
            { w: '80%', bg: '#22c55e', text: 'Strong' },
            { w: '100%', bg: '#15803d', text: 'Very strong' },
        ];
        var lvl = levels[Math.min(score - 1, 4)] || levels[0];
        fill.style.width = lvl.w;
        fill.style.background = lvl.bg;
        label.textContent = lvl.text;
        label.style.color = lvl.bg;
    }

    document.getElementById('resetForm').addEventListener('submit', function() {
        var btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Resetting…';
    });
</script>
</body>
</html>
