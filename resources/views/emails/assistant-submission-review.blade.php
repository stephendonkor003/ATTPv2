<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Approval requested</title></head>
<body style="margin:0;padding:0;background:#eff4f2;color:#18362d;font-family:Arial,Helvetica,sans-serif;line-height:1.65;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $creatorName }} has created a {{ strtolower($requestType) }} that needs your urgent attention. Review {{ $submission->reference_no }}.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eff4f2;">
        <tr><td align="center" style="padding:32px 12px;">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #dbe6e0;border-radius:16px;overflow:hidden;">
                <tr><td style="background:#07553d;padding:30px 32px;border-bottom:5px solid #c9ab68;">
                    <div style="color:#c7e7d9;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">{{ config('app.name', 'ATTP') }} &nbsp; / &nbsp; Administrative office</div>
                    <h1 style="color:#ffffff;font-size:28px;line-height:1.25;margin:16px 0 10px;">Your approval is requested</h1>
                    <div style="color:#e4f1eb;font-size:14px;">{{ $requestType }} &middot; {{ $submission->reference_no }} &middot; {{ $isMainCoordinator ? 'Project Coordinator' : 'Administrator review' }}</div>
                </td></tr>
                <tr><td style="padding:30px 32px;">
                    <div style="display:inline-block;background:#fff4d9;color:#805211;font-size:11px;font-weight:bold;letter-spacing:1px;padding:6px 12px;border-radius:20px;text-transform:uppercase;">Urgent attention requested</div>
                    <p style="margin:22px 0 14px;">Hello {{ $recipient->name ?: 'Project Coordinator' }},</p>
                    <p style="margin:0 0 20px;"><strong>{{ $creatorName }}</strong> has created a <strong>{{ strtolower($requestType) }}</strong> that needs your urgent attention. It is awaiting Project Coordinator or administrator approval.</p>
                    <p style="margin:0 0 20px;font-size:14px;color:#4e6258;">The main Project Coordinator is <strong>{{ $coordinatorEmail }}</strong>. Any authorized administrator can also review and approve this submission.</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dfe8e2;border-radius:8px;background:#f8fbf9;font-size:14px;">
                        <tr><td style="padding:12px 16px;border-bottom:1px solid #e4ebe7;color:#5a6d64;width:34%;">Reference</td><td style="padding:12px 16px;border-bottom:1px solid #e4ebe7;font-weight:bold;">{{ $submission->reference_no }}</td></tr>
                        <tr><td style="padding:12px 16px;border-bottom:1px solid #e4ebe7;color:#5a6d64;">Submitted by</td><td style="padding:12px 16px;border-bottom:1px solid #e4ebe7;">{{ $creatorName }}</td></tr>
                        <tr><td style="padding:12px 16px;border-bottom:1px solid #e4ebe7;color:#5a6d64;">Submitted</td><td style="padding:12px 16px;border-bottom:1px solid #e4ebe7;">{{ $submission->created_at?->format('d M Y, H:i T') }}</td></tr>
                        @foreach (collect($submission->summary ?? [])->filter(fn ($value) => is_scalar($value) && (string) $value !== '')->take(5) as $label => $value)
                            <tr><td style="padding:12px 16px;border-bottom:1px solid #e4ebe7;color:#5a6d64;">{{ $label }}</td><td style="padding:12px 16px;border-bottom:1px solid #e4ebe7;word-break:break-word;">{{ \Illuminate\Support\Str::limit((string) $value, 220) }}</td></tr>
                        @endforeach
                        <tr><td style="padding:12px 16px;color:#5a6d64;">Approval status</td><td style="padding:12px 16px;color:#805211;font-weight:bold;">Pending approval</td></tr>
                    </table>
                    <p style="font-size:14px;margin:22px 0;color:#4e6258;">The attached PDF contains the submission details and a list of supporting documents. Open the review page to preview the documents and approve or reject the request with a note.</p>
                    <table role="presentation" cellpadding="0" cellspacing="0"><tr><td bgcolor="#07553d" style="border-radius:8px;"><a href="{{ $reviewUrl }}" style="display:inline-block;padding:14px 24px;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;">Review and make a decision &rarr;</a></td></tr></table>
                    <div style="margin-top:26px;padding:16px 18px;background:#fff9ea;border-left:4px solid #c9ab68;color:#745221;font-size:13px;"><strong>Approval is required.</strong> This submission has no effect on financial or procurement reporting until an authorized reviewer approves it.</div>
                    <p style="font-size:12px;color:#697c71;margin:24px 0 0;">If the button does not open, use this secure review link:<br><a href="{{ $reviewUrl }}" style="color:#07553d;word-break:break-all;">{{ $reviewUrl }}</a><br>Sign in with your authorized account to review the request.</p>
                </td></tr>
                <tr><td style="padding:22px 32px;background:#f6f9f7;border-top:1px solid #e0e8e3;color:#687b70;font-size:12px;">
                    <strong style="color:#2d493b;">{{ config('app.name', 'ATTP') }} &middot; Administrative approvals</strong><br>
                    Clear records. Accountable decisions.<br>
                    This notification was generated when the administrative assistant submitted a request. Please record your decision in the application.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
