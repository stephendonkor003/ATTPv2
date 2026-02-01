<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('app.name') }}</title>
    <style>
        /* Reset */
        body,
        table,
        td,
        p,
        a,
        li,
        blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            color: #333333;
            line-height: 1.6;
        }

        table {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            border: 0;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }

        a {
            color: #0066cc;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        /* Container */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        /* Header */
        .email-header {
            background: linear-gradient(135deg, #1a365d 0%, #2d4a7c 100%);
            padding: 30px 40px;
            text-align: center;
        }

        .email-header img {
            max-width: 180px;
            height: auto;
        }

        .email-header h1 {
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
            margin: 15px 0 5px 0;
        }

        .email-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin: 0;
        }

        /* Body */
        .email-body {
            padding: 40px;
        }

        .email-body h2 {
            color: #1a365d;
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 20px 0;
        }

        .email-body p {
            color: #4a5568;
            font-size: 15px;
            margin: 0 0 16px 0;
        }

        /* OTP Box */
        .otp-box {
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            border: 2px dashed #cbd5e0;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            margin: 25px 0;
        }

        .otp-code {
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 8px;
            color: #1a365d;
            font-family: 'Courier New', monospace;
        }

        .otp-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        /* Alert Boxes */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }

        .alert-warning {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }

        .alert-info {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }

        .alert-success {
            background-color: #ecfdf5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        /* Button */
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #1a365d 0%, #2d4a7c 100%);
            color: #ffffff !important;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 8px;
            margin: 20px 0;
        }

        .btn:hover {
            text-decoration: none;
            opacity: 0.9;
        }

        /* Info List */
        .info-list {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .info-list ul {
            margin: 0;
            padding-left: 20px;
        }

        .info-list li {
            color: #4a5568;
            font-size: 14px;
            margin-bottom: 8px;
        }

        /* Divider */
        .divider {
            border-top: 1px solid #e2e8f0;
            margin: 30px 0;
        }

        /* Footer */
        .email-footer {
            background-color: #f8fafc;
            padding: 30px 40px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .email-footer p {
            color: #718096;
            font-size: 13px;
            margin: 0 0 10px 0;
        }

        .email-footer .social-links {
            margin: 15px 0;
        }

        .email-footer .social-links a {
            display: inline-block;
            margin: 0 8px;
            color: #718096;
        }

        .email-footer .copyright {
            font-size: 12px;
            color: #a0aec0;
            margin-top: 20px;
        }

        /* Responsive */
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }

            .email-header,
            .email-body,
            .email-footer {
                padding: 25px !important;
            }

            .otp-code {
                font-size: 28px !important;
                letter-spacing: 4px !important;
            }
        }
    </style>
</head>

<body>
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f6f9; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table class="email-container" width="600" cellpadding="0" cellspacing="0">
                    {{-- HEADER --}}
                    <tr>
                        <td class="email-header">
                            <h1>{{ config('app.name', 'African Union Commission') }}</h1>
                            <p>African Union Commission</p>
                        </td>
                    </tr>

                    {{-- BODY --}}
                    <tr>
                        <td class="email-body">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- FOOTER --}}
                    <tr>
                        <td class="email-footer">
                            <p>
                                <strong>{{ config('app.name', 'African Union Commission') }}</strong><br>
                                African Union Commission -
                            </p>

                            <p>
                                If you have any questions, please contact our support team at<br>
                                <a href="mailto:attpinfo@africanunion.org">attpinfo@africanunion.org</a>
                            </p>

                            <div class="divider"></div>

                            <p class="copyright">
                                &copy; {{ date('Y') }} African Union Commission. All rights reserved.<br>
                                This is an automated message. Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
