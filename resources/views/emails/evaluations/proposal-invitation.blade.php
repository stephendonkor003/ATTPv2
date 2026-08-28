<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $communication->subject }}</title>
</head>
<body style="margin:0;padding:0;background:#eef3f0;font-family:Arial,Helvetica,sans-serif;color:#172033;line-height:1.6;">
    @php
        $appName = \App\Support\PdfBranding::PLATFORM_NAME;
        $reference = trim((string) ($procurement?->reference_no ?? '')) ?: 'Not provided';
        $title = trim((string) ($procurement?->title ?? '')) ?: 'Procurement opportunity';
        $supportEmail = trim((string) config('mail.from.address'));
        $proposalRound = $communication->technicalProposalRound;
    @endphp

    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">You are invited to submit your proposal through the vendor portal.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#eef3f0;">
        <tr>
            <td align="center" style="padding:30px 14px;">
                <table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #dce6e0;border-radius:14px;overflow:hidden;box-shadow:0 16px 38px rgba(15,23,42,.10);">
                    <tr>
                        <td style="background:#006b3f;padding:0;">
                            <div style="height:6px;background:#f4b41a;"></div>
                            <div style="padding:27px 30px 29px;">
                                <div style="font-size:12px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#c9f3df;">{{ $appName }}</div>
                                <h1 style="margin:8px 0 0;font-size:25px;line-height:1.25;color:#ffffff;">Invitation to submit a proposal</h1>
                                <p style="margin:8px 0 0;font-size:14px;color:#e3fff1;">Your EOI qualified for the next procurement stage.</p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 16px;">Dear <strong>{{ $recipient->recipient_name }}</strong>,</p>
                            <div style="margin:0 0 22px;color:#27364a;">{!! nl2br(e($communication->message)) !!}</div>

                            <div style="margin:0 0 22px;border:1px solid #dce6e0;border-radius:10px;overflow:hidden;">
                                <div style="padding:12px 16px;background:#f2f7f4;font-size:13px;font-weight:700;color:#214f39;text-transform:uppercase;letter-spacing:.05em;">Invitation details</div>
                                <div style="padding:14px 16px;"><strong>{{ $title }}</strong><br><span style="color:#64748b;">Reference: {{ $reference }} &middot; Outcome: {{ $recipient->outcome_label }}</span></div>
                            </div>

                            @if ($proposalRound)
                                <div style="margin:0 0 22px;border:1px solid #cfe2d7;border-radius:10px;overflow:hidden;">
                                    <div style="padding:12px 16px;background:#edf8f2;font-size:13px;font-weight:700;color:#075f38;text-transform:uppercase;letter-spacing:.05em;">Technical proposal requirements</div>
                                    <div style="padding:14px 16px;font-size:13px;color:#344054;">
                                        <strong>Deadline:</strong> {{ $proposalRound->deadline_at ? $proposalRound->deadline_at->timezone($proposalRound->timezone)->format('d M Y, H:i').' '.$proposalRound->timezone : 'No fixed deadline' }}<br>
                                        <strong>Portal:</strong> {{ str($proposalRound->portal_requirement)->replace('_', ' ')->headline() }} &middot;
                                        <strong>Email:</strong> {{ str($proposalRound->email_requirement)->replace('_', ' ')->headline() }} &middot;
                                        <strong>Physical copy:</strong> {{ str($proposalRound->physical_requirement)->replace('_', ' ')->headline() }}
                                        @if ($proposalRound->rules->isNotEmpty())
                                            <ol style="margin:12px 0 0;padding-left:20px;">
                                                @foreach ($proposalRound->rules as $rule)
                                                    <li style="margin:0 0 7px;"><strong>{{ $rule->title }}</strong>@if($rule->is_disqualifying) <span style="color:#b42318;">(may disqualify)</span>@endif @if($rule->description)<br><span style="color:#64748b;">{{ $rule->description }}</span>@endif</li>
                                                @endforeach
                                            </ol>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if ($communication->attachments->isNotEmpty())
                                <p style="margin:0 0 16px;font-size:13px;color:#516172;">{{ $communication->attachments->count() }} proposal {{ Str::plural('template', $communication->attachments->count()) }} {{ $proposalRound ? Str::plural('is', $communication->attachments->count()).' available securely in the portal.' : Str::plural('is', $communication->attachments->count()).' attached and also available securely in the portal.' }}</p>
                            @endif

                            <a href="{{ $portalUrl }}" style="display:inline-block;border-radius:8px;background:#006b3f;padding:13px 20px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">Open invitation &amp; submit proposal</a>
                            <p style="margin:18px 0 0;font-size:12px;color:#748196;">For your security, sign in to the vendor portal before downloading templates or uploading proposal documents.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #dce6e0;background:#f7faf8;padding:18px 30px;color:#64748b;font-size:12px;line-height:1.55;">
                            This invitation was issued through {{ $appName }}.
                            @if ($supportEmail !== '')<br>Support: {{ $supportEmail }}@endif
                        </td>
                    </tr>
                </table>
                <div style="margin-top:12px;color:#8a9a91;font-size:11px;">&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
            </td>
        </tr>
    </table>
</body>
</html>
