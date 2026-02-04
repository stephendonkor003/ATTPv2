<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Vendor Request Response</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #eef5ff; padding: 30px;">
    <div style="max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);">

        <div style="background: linear-gradient(120deg, #2563eb, #60a5fa); color: #ffffff; padding: 24px 30px;">
            <h2 style="margin: 0; font-size: 22px;">
                {{ $type === 'message' ? 'Clarification Response' : 'Information Request Response' }}
            </h2>
            <p style="margin: 8px 0 0; opacity: 0.9;">We have responded to your request.</p>
        </div>

        <div style="padding: 28px 30px; color: #1f2937;">
            <p style="margin-top: 0;">Dear {{ $request->user->name ?? 'Vendor' }},</p>

            <p>
                Our team has reviewed your {{ $type === 'message' ? 'clarification message' : 'information request' }}.
                Please find the response details below.
            </p>

            <div style="background: #f0f7ff; border: 1px solid #c7ddff; border-radius: 12px; padding: 18px; margin: 22px 0;">
                <h4 style="margin: 0 0 10px; color: #1e3a8a;">Request Details</h4>
                <p style="margin: 6px 0;">
                    <strong>Subject:</strong>
                    {{ $type === 'message' ? ($request->subject ?? 'N/A') : ($request->request_topic ?? 'N/A') }}
                </p>
                @if ($request->procurement)
                    <p style="margin: 6px 0;">
                        <strong>Procurement:</strong> {{ $request->procurement->title }}
                    </p>
                @endif
                <p style="margin: 6px 0;">
                    <strong>Submitted:</strong> {{ $request->created_at?->format('M d, Y') ?? now()->format('M d, Y') }}
                </p>
            </div>

            <div style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 16px; margin-bottom: 22px;">
                <h4 style="margin: 0 0 10px;">Your Request</h4>
                <p style="margin: 0;">{{ $type === 'message' ? ($request->message ?? '') : ($request->details ?? '') }}</p>
            </div>

            <div style="background: #ecfeff; border-left: 4px solid #0ea5e9; padding: 16px;">
                <h4 style="margin: 0 0 10px;">Our Response</h4>
                <p style="margin: 0;">{{ $request->response ?? 'No response provided.' }}</p>
                <p style="margin: 10px 0 0; font-size: 13px; color: #4b5563;">
                    Status: {{ ucfirst($request->status ?? 'open') }}
                </p>
            </div>

            <p style="margin-top: 24px;">
                If you need further assistance, please reply through the vendor portal.
            </p>
        </div>

        <div style="background: #1e3a8a; color: #ffffff; text-align: center; padding: 14px;">
            African Union Procurement Portal · Official Communications
        </div>
    </div>
</body>

</html>
