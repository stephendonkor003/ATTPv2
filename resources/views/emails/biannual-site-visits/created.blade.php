<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bi-Annual Site Visit Assignment</title>
</head>
<body style="margin:0;padding:0;background:#eef3f0;font-family:Arial,Helvetica,sans-serif;color:#172033;line-height:1.6;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        You have been assigned to {{ $visit->reference_number }} for {{ $visit->thinkTank?->name }}.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#eef3f0;">
        <tr>
            <td align="center" style="padding:30px 14px;">
                <table role="presentation" width="700" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:700px;background:#ffffff;border:1px solid #dce6e0;border-radius:16px;overflow:hidden;box-shadow:0 16px 38px rgba(15,23,42,.10);">
                    <tr>
                        <td style="background:#006b3f;padding:0;">
                            <div style="height:6px;background:#f4b41a;"></div>
                            <div style="padding:28px 30px 30px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td valign="top">
                                            <div style="font-size:12px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#c9f3df;">
                                                Africa Think Tank Platform
                                            </div>
                                            <h1 style="margin:8px 0 0;font-size:26px;line-height:1.25;color:#ffffff;">
                                                Bi-Annual Site Visit
                                            </h1>
                                            <p style="margin:8px 0 0;font-size:15px;color:#e3fff1;">
                                                Monitoring-team assignment
                                            </p>
                                        </td>
                                        <td align="right" valign="top" style="padding-left:18px;">
                                            <div style="display:inline-block;padding:7px 11px;border:1px solid rgba(255,255,255,.35);border-radius:999px;color:#ffffff;font-size:12px;font-weight:700;white-space:nowrap;">
                                                {{ $visit->reference_number }}
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 14px;font-size:16px;">Hello {{ $recipient->name ?: 'Monitoring Team Member' }},</p>

                            @if ($isLeader)
                                <div style="margin:0 0 20px;padding:15px 17px;border:1px solid #b7dfcb;border-left:5px solid #006b3f;border-radius:10px;background:#f0faf5;">
                                    <div style="font-size:12px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#006b3f;">Team Leader</div>
                                    <div style="margin-top:4px;color:#274437;">
                                        You have been appointed to lead this monitoring team, coordinate the assessment, and submit the consolidated questionnaire.
                                    </div>
                                </div>
                            @else
                                <div style="margin:0 0 20px;padding:15px 17px;border:1px solid #d9e4de;border-left:5px solid #4f7663;border-radius:10px;background:#f7faf8;">
                                    <div style="font-size:12px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#315d48;">Monitoring Team Member</div>
                                    <div style="margin-top:4px;color:#334b40;">
                                        You have been assigned to collaborate with the monitoring team and complete the Bi-Annual Site Visit questionnaire.
                                    </div>
                                </div>
                            @endif

                            <p style="margin:0 0 22px;">
                                A new visit has been scheduled for <strong>{{ $visit->thinkTank?->name ?: 'the selected Think Tank' }}</strong>
                                under the <strong>{{ $portfolioName }}</strong> portfolio. Please review the assignment and prepare for the scheduled assessment.
                            </p>

                            <div style="margin:0 0 22px;border:1px solid #dce6e0;border-radius:12px;overflow:hidden;">
                                <div style="padding:12px 16px;background:#f2f7f4;border-bottom:1px solid #dce6e0;font-size:13px;font-weight:700;color:#214f39;text-transform:uppercase;letter-spacing:.05em;">
                                    Visit details
                                </div>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">
                                    <tr>
                                        <td style="width:34%;padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Think Tank</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $visit->thinkTank?->name ?: 'Not specified' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Portfolio</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $portfolioName }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Cycle</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $visit->cycleLabel() }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Schedule</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">
                                            {{ $visit->starts_on?->format('d M Y') ?: 'To be confirmed' }}
                                            @if ($visit->ends_on && (!$visit->starts_on || !$visit->ends_on->isSameDay($visit->starts_on)))
                                                &ndash; {{ $visit->ends_on->format('d M Y') }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Location</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $visit->location ?: 'To be confirmed' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Questionnaire</td>
                                        <td style="padding:11px 16px;font-size:14px;">{{ $visit->template?->name ?: $visit->title }}</td>
                                    </tr>
                                </table>
                            </div>

                            @if ($visit->objectives)
                                <div style="margin:0 0 22px;padding:16px 18px;border-radius:10px;background:#fff9e9;border:1px solid #f0dda3;">
                                    <div style="margin-bottom:6px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#8a6411;">Visit objectives</div>
                                    <div style="font-size:14px;color:#594c2d;">{!! nl2br(e($visit->objectives)) !!}</div>
                                </div>
                            @endif

                            @if ($teamMembers->isNotEmpty())
                                <div style="margin:0 0 24px;">
                                    <div style="margin-bottom:10px;font-size:13px;font-weight:700;color:#214f39;text-transform:uppercase;letter-spacing:.05em;">
                                        Monitoring team
                                    </div>
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:separate;border-spacing:0 6px;">
                                        @foreach ($teamMembers as $member)
                                            @php
                                                $memberIsLeader = (string) $member->user_id === (string) $group?->leader_id;
                                                $memberSpecialism = $specialisms[(string) $member->user_id] ?? null;
                                            @endphp
                                            <tr>
                                                <td style="padding:10px 13px;border:1px solid #e0e8e3;border-radius:8px;background:{{ (string) $member->user_id === (string) $recipient->id ? '#eef8f3' : '#fafcfb' }};">
                                                    <div style="font-size:14px;font-weight:700;color:#263d32;">
                                                        {{ $member->user?->name ?: 'Monitoring team member' }}
                                                        @if ($memberIsLeader)
                                                            <span style="display:inline-block;margin-left:6px;padding:2px 7px;border-radius:999px;background:#d9f3e6;color:#006b3f;font-size:10px;text-transform:uppercase;letter-spacing:.04em;">Lead</span>
                                                        @endif
                                                    </div>
                                                    @if ($memberSpecialism)
                                                        <div style="margin-top:2px;font-size:12px;color:#6b7d73;">{{ $memberSpecialism }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            @endif

                            <div style="text-align:center;margin:28px 0 18px;">
                                <a href="{{ $openUrl }}" style="display:inline-block;padding:13px 22px;border-radius:9px;background:#006b3f;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;box-shadow:0 5px 12px rgba(0,107,63,.20);">
                                    Open Site Visit Assignment
                                </a>
                            </div>

                            <p style="margin:0;color:#687b71;font-size:12px;text-align:center;">
                                If the button does not work, copy and paste this address into your browser:<br>
                                <a href="{{ $openUrl }}" style="color:#006b3f;word-break:break-all;">{{ $openUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:19px 30px;background:#172b22;border-top:4px solid #f4b41a;color:#cddbd4;font-size:12px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td valign="top">
                                        <strong style="display:block;margin-bottom:3px;color:#ffffff;font-size:13px;">{{ config('app.name', 'ATTP') }}</strong>
                                        Africa Think Tank Platform monitoring workspace
                                    </td>
                                    <td align="right" valign="top" style="padding-left:20px;color:#9fb2a8;">
                                        Automated assignment notice<br>
                                        {{ $visit->reference_number }}
                                    </td>
                                </tr>
                            </table>
                            <div style="margin-top:13px;padding-top:12px;border-top:1px solid #31483d;color:#9fb2a8;">
                                This operational email was sent because you are part of the assigned Bi-Annual Site Visit monitoring team.
                                Please do not forward confidential assessment information outside the authorized team.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
