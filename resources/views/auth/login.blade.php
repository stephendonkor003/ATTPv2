<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>AUC- Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('assets/img/favicon.ico') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('assets/css/login.css') }}">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('assets/img/au_building.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
            min-height: 100vh;
        }

        /* Dark overlay layer */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 0;
        }

        .login-container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            display: flex;
            gap: 30px;
            max-width: 900px;
            width: 100%;
            align-items: stretch;
        }

        .login-box {
            background: rgba(255, 255, 255, 0.97);
            padding: 40px;
            border-radius: 12px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.25);
        }

        .security-info-panel {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
        }

        .security-info-panel h3 {
            color: #1a365d;
            font-size: 18px;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .security-info-panel h3 svg {
            width: 24px;
            height: 24px;
            color: #3182ce;
        }

        .security-feature {
            background: #f7fafc;
            border-left: 3px solid #3182ce;
            padding: 14px 16px;
            margin-bottom: 14px;
            border-radius: 0 8px 8px 0;
        }

        .security-feature h4 {
            color: #2d3748;
            font-size: 14px;
            margin: 0 0 6px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-feature h4 svg {
            width: 16px;
            height: 16px;
            color: #38a169;
        }

        .security-feature p {
            color: #718096;
            font-size: 13px;
            margin: 0;
            line-height: 1.5;
        }

        .security-tip {
            background: #ebf8ff;
            border-radius: 8px;
            padding: 14px;
            margin-top: auto;
        }

        .security-tip h5 {
            color: #2b6cb0;
            font-size: 13px;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .security-tip ul {
            margin: 0;
            padding-left: 20px;
            color: #4a5568;
            font-size: 12px;
        }

        .security-tip li {
            margin-bottom: 4px;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-container img {
            height: 80px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 500;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #5e2a09;
            outline: none;
            box-shadow: 0 0 4px #007BFF44;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #333;
        }

        .checkbox-group input {
            margin-right: 8px;
        }

        .actions {
            margin-top: 20px;
        }

        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #5e2a09;
            border: none;
            color: white;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s ease;
        }

        button[type="submit"]:hover {
            background-color: #4a2107;
        }

        .form-footer {
            text-align: right;
            margin-top: 12px;
        }

        .form-footer a {
            color: #007BFF;
            text-decoration: none;
            font-size: 14px;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .error {
            display: block;
            color: red;
            font-size: 13px;
            margin-top: 5px;
        }

        .alert {
            padding: 12px 15px;
            margin-bottom: 15px;
            border-radius: 6px;
            font-size: 14px;
        }

        .alert.success {
            background: #e0f8e0;
            color: #2d7a2d;
            border: 1px solid #a3d9a5;
        }

        .alert.info {
            background: #e0f0ff;
            color: #1a5a9a;
            border: 1px solid #a3c9e5;
        }

        .alert.warning {
            background: #fff8e0;
            color: #8a6d3b;
            border: 1px solid #faebcc;
        }

        .back-link {
            text-align: right;
            margin-bottom: 10px;
        }

        .back-link a {
            text-decoration: none;
            font-size: 14px;
            color: #5e2a09;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .back-link a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 850px) {
            .login-wrapper {
                flex-direction: column;
                align-items: center;
            }

            .security-info-panel {
                max-width: 420px;
                order: 2;
            }

            .login-box {
                order: 1;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-wrapper">
            {{-- Login Form --}}
            <div class="login-box">
                <div class="back-link">
                    <a href="{{ route('landing.index') }}">← Back to Homepage</a>
                </div>

                <div class="logo-container">
                    <img src="{{ asset('assets/img/au.png') }}" alt="AU Logo">
                </div>

                <!-- Session Status -->
                @if (session('status'))
                    <div class="alert success">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('info'))
                    <div class="alert info">
                        {{ session('info') }}
                    </div>
                @endif

                @if (session('warning'))
                    <div class="alert warning">
                        {{ session('warning') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required
                            autofocus>
                        @error('email')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" required>
                        @error('password')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="remember"> Remember me
                        </label>
                    </div>

                    <div class="form-group actions">
                        <button type="submit">Log In</button>
                    </div>

                    {{-- @if (Route::has('password.request'))
                        <div class="form-footer">
                            <a href="{{ route('password.request') }}">Forgot your password?</a>
                        </div>
                    @endif --}}
                </form>
            </div>

            {{-- Security Information Panel --}}
            <div class="security-info-panel">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    Your Security Matters
                </h3>

                <div class="security-feature">
                    <h4>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Two-Factor Authentication
                    </h4>
                    <p>After login, a 6-digit verification code will be sent to your email for identity confirmation.
                    </p>
                </div>

                <div class="security-feature">
                    <h4>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Regular Password Updates
                    </h4>
                    <p>For your protection, passwords expire every 60 days. You'll be prompted to create a new secure
                        password.</p>
                </div>

                <div class="security-feature">
                    <h4>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        First Login Password Change
                    </h4>
                    <p>New users must change their temporary password on first login to ensure account security.</p>
                </div>

                <div class="security-tip">
                    <h5>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        Security Best Practices
                    </h5>
                    <ul>
                        <li>Never share your login credentials</li>
                        <li>Use unique passwords for each system</li>
                        <li>Log out when using shared computers</li>
                        <li>Report suspicious activity immediately</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
