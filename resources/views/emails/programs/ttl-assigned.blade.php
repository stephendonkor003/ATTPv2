<!doctype html>
<html>
<body style="margin:0;background:#f3f7f5;font-family:Arial,sans-serif;color:#172033;line-height:1.6;">
    @php
        $portfolio = $program->sector;
        $governanceNode = $program->governanceNode;
        $projectCount = $program->projects_count ?? $program->projects()->count();
    @endphp

    <div style="max-width:720px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #dbe6df;border-radius:12px;overflow:hidden;box-shadow:0 14px 30px rgba(15,23,42,0.08);">
            <div style="background:#006b3f;color:#ffffff;padding:28px;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#d8f7e8;font-weight:700;">
                    ATTP Task Team Leader Workspace
                </div>
                <h2 style="margin:8px 0 0;font-size:24px;line-height:1.3;">
                    {{ $program->name }}
                </h2>
                <p style="margin:8px 0 0;color:#e6fff3;">
                    You have been assigned as the Task Team Leader for this program.
                </p>
            </div>

            <div style="padding:24px;">
                <p style="margin-top:0;">Hello {{ $user->name ?? 'Task Team Leader' }},</p>

                <p>
                    Your ATTP TTL workspace is ready. You can review the program, linked projects,
                    activities, sub-activities, budget progress, funding context, and delivery updates
                    attached to your assignment.
                </p>

                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;width:190px;">Program</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $program->name }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Portfolio</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $portfolio?->name ?? 'Not assigned' }}</td>
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
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Budget</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">
                            {{ $program->currency ?: 'USD' }} {{ number_format((float) ($program->total_budget ?? 0), 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Projects</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ number_format($projectCount) }}</td>
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
                        Login
                    </a>
                    <a href="{{ $programUrl }}" style="display:inline-block;margin-left:8px;background:#522b39;color:#ffffff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:700;">
                        Open TTL Workspace
                    </a>
                </p>

                <p style="margin:22px 0 0;color:#64748b;font-size:13px;">
                    Funding partners will see your name and email as the responsible TTL for this program and its projects.
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
