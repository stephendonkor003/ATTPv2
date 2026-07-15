<!doctype html>
<html>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#172033;line-height:1.6;">
    @php
        $governanceNode = $portfolio->governanceNode;
        $status = ucfirst($portfolio->status ?: 'Active');
    @endphp

    <div style="max-width:700px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 14px 30px rgba(15,23,42,0.08);">
            <div style="background:#0f766e;color:#ffffff;padding:26px;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#ccfbf1;font-weight:700;">
                    ATTP Portfolio Assignment
                </div>
                <h2 style="margin:8px 0 0;font-size:23px;line-height:1.3;">
                    {{ $portfolio->name }}
                </h2>
                <p style="margin:8px 0 0;color:#d1fae5;">
                    You have been assigned as {{ $roleName }} for this portfolio.
                </p>
            </div>

            <div style="padding:24px;">
                <p style="margin-top:0;">Hello {{ $user->name ?? 'Portfolio Leader' }},</p>

                <p>
                    You have been assigned to manage and coordinate this ATTP portfolio. From your workspace,
                    you can oversee the portfolio structure, programs, activities, budgets, commitments,
                    procurement records, M&E, evaluations, and site visit information according to your access rights.
                </p>

                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;width:190px;">Portfolio</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $portfolio->name }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Your Role</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $roleName }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Governance Node</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">
                            {{ $governanceNode?->name ?? 'Not assigned' }}
                            @if ($governanceNode?->level?->name)
                                <div style="color:#64748b;font-size:13px;">{{ $governanceNode->level->name }}</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">TTL</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">
                            {{ $portfolio->ttl_name ?: 'Not assigned' }}
                            @if ($portfolio->ttl_email)
                                <div style="color:#64748b;font-size:13px;">{{ $portfolio->ttl_email }}</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Status</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $status }}</td>
                    </tr>
                </table>

                @if ($plainPassword)
                    <div style="border:1px solid #bbf7d0;background:#f0fdf4;border-radius:10px;padding:14px;margin:20px 0;">
                        <div style="font-weight:700;color:#064e3b;margin-bottom:6px;">Your login details</div>
                        <div>Email: <strong>{{ $user->email }}</strong></div>
                        <div>Temporary password: <strong>{{ $plainPassword }}</strong></div>
                        <div style="color:#64748b;font-size:13px;margin-top:8px;">
                            You may be asked to change this password after your first login.
                        </div>
                    </div>
                @else
                    <div style="border:1px solid #dbeafe;background:#eff6ff;border-radius:10px;padding:14px;margin:20px 0;">
                        <div style="font-weight:700;color:#1e3a8a;margin-bottom:6px;">Use your existing ATTP account</div>
                        <div style="color:#334155;">
                            Sign in with your existing email address: <strong>{{ $user->email }}</strong>
                        </div>
                    </div>
                @endif

                <p style="margin:26px 0;">
                    <a href="{{ $loginUrl }}" style="display:inline-block;background:#006B3F;color:#ffffff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:700;">
                        Login to Manage Portfolio
                    </a>
                    <a href="{{ $portfolioUrl }}" style="display:inline-block;margin-left:8px;background:#522b39;color:#ffffff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:700;">
                        Open Portfolio
                    </a>
                </p>

                <p style="margin:22px 0 0;color:#64748b;font-size:13px;">
                    This assignment gives you responsibility for coordinating the portfolio from portfolio setup
                    through programs, projects, activities, sub-activities, finance, procurement, M&E, evaluations,
                    and implementation follow-up.
                </p>
            </div>

            <div style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 24px;color:#64748b;font-size:12px;">
                <strong style="color:#0f172a;">{{ config('app.name', 'ATTP') }}</strong><br>
                Developed, maintained and supported by the ATTP Technical Team.
            </div>
        </div>
    </div>
</body>
</html>
