<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Application Received</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #eef5ff; padding: 30px;">
    <div style="max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);">

        <!-- Header -->
        <div style="background: linear-gradient(120deg, #2563eb, #60a5fa); color: #ffffff; padding: 24px 30px;">
            <h2 style="margin: 0; font-size: 22px;">Application Received</h2>
            <p style="margin: 8px 0 0; opacity: 0.9;">Procurement: {{ $procurement->title }}</p>
        </div>

        <!-- Body -->
        <div style="padding: 28px 30px; color: #1f2937;">
            <p style="margin-top: 0;">Dear {{ $vendor->name ?? 'Vendor' }},</p>

            <p>
                Thank you for submitting your application for
                <strong>{{ $procurement->title }}</strong>.
                We confirm that your application has been successfully received.
            </p>

            <p>
                We wish you success throughout the entire procurement process. Our team will review your submission and
                keep you updated.
            </p>

            <div style="background: #f0f7ff; border: 1px solid #c7ddff; border-radius: 12px; padding: 18px; margin: 22px 0;">
                <h4 style="margin: 0 0 10px; color: #1e3a8a;">Your Application Details</h4>
                <p style="margin: 6px 0;">
                    <strong>Application Reference:</strong> {{ $submission->procurement_submission_code ?? 'N/A' }}
                </p>
                <p style="margin: 6px 0;">
                    <strong>Submitted On:</strong> {{ $submission->submitted_at?->format('M d, Y') ?? now()->format('M d, Y') }}
                </p>
            </div>

            <div style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 16px; margin-bottom: 22px;">
                <h4 style="margin: 0 0 10px;">Vendor Portal Login</h4>
                <p style="margin: 6px 0;"><strong>Portal:</strong> <a href="{{ $portalUrl }}" style="color: #2563eb;">{{ $portalUrl }}</a></p>
                <p style="margin: 6px 0;"><strong>Email:</strong> {{ $vendor->email }}</p>

                @if ($temporaryPassword)
                    <p style="margin: 6px 0;"><strong>Temporary Password:</strong> {{ $temporaryPassword }}</p>
                    <p style="margin: 10px 0 0; font-size: 13px; color: #4b5563;">
                        Please log in with this temporary password. You will be required to change it on first login.
                    </p>
                @else
                    <p style="margin: 10px 0 0; font-size: 13px; color: #4b5563;">
                        Use your existing password to access the vendor portal.
                    </p>
                @endif
            </div>

            <p style="margin-bottom: 0;">
                If you have any questions, please contact our procurement support team.
            </p>
        </div>

        <!-- Footer -->
        <div style="background: #1e3a8a; color: #ffffff; text-align: center; padding: 14px;">
            African Union Procurement Portal · Official Communications
        </div>
    </div>
</body>

</html>
