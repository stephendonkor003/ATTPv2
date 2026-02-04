<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Procurement Invitation</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #eef5ff; padding: 30px;">
    <div style="max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);">

        <div style="background: linear-gradient(120deg, #2563eb, #60a5fa); color: #ffffff; padding: 24px 30px;">
            <h2 style="margin: 0; font-size: 22px;">New Procurement Opportunity</h2>
            <p style="margin: 8px 0 0; opacity: 0.9;">{{ $procurement->title }}</p>
        </div>

        <div style="padding: 28px 30px; color: #1f2937;">
            <p style="margin-top: 0;">Dear {{ $vendor->name ?? 'Vendor' }},</p>

            <p>
                We are pleased to inform you about a new procurement opportunity that matches your vendor category.
            </p>

            <div style="background: #f0f7ff; border: 1px solid #c7ddff; border-radius: 12px; padding: 18px; margin: 22px 0;">
                <h4 style="margin: 0 0 10px; color: #1e3a8a;">Procurement Details</h4>
                <p style="margin: 6px 0;"><strong>Reference:</strong> {{ $procurement->reference_no ?? 'N/A' }}</p>
                <p style="margin: 6px 0;"><strong>Title:</strong> {{ $procurement->title }}</p>
                @if ($procurement->application_end_date)
                    <p style="margin: 6px 0;"><strong>Application Closes:</strong> {{ $procurement->application_end_date->format('M d, Y') }}</p>
                @endif
            </div>

            @if ($message)
                <div style="background: #ecfeff; border-left: 4px solid #0ea5e9; padding: 16px; margin-bottom: 22px;">
                    <h4 style="margin: 0 0 10px;">Message from Procurement Team</h4>
                    <p style="margin: 0;">{{ $message }}</p>
                </div>
            @endif

            <div style="text-align: center; margin-top: 24px;">
                <a href="{{ $link }}" style="background: #2563eb; color: #ffffff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
                    View Procurement & Apply
                </a>
            </div>
        </div>

        <div style="background: #1e3a8a; color: #ffffff; text-align: center; padding: 14px;">
            African Union Procurement Portal · Official Communications
        </div>
    </div>
</body>

</html>
