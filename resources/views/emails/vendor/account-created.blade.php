<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Vendor Account Created</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #eef5ff; padding: 30px;">
    <div style="max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);">

        <div style="background: linear-gradient(120deg, #2563eb, #60a5fa); color: #ffffff; padding: 24px 30px;">
            <h2 style="margin: 0; font-size: 22px;">Vendor Account Created</h2>
            <p style="margin: 8px 0 0; opacity: 0.9;">Welcome to the Vendor Portal</p>
        </div>

        <div style="padding: 28px 30px; color: #1f2937;">
            <p style="margin-top: 0;">Dear {{ $vendor->name ?? 'Vendor' }},</p>

            <p>
                Your vendor account has been created successfully. Use the credentials below to access the vendor
                portal and manage your procurement applications.
            </p>

            <div style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 16px; margin: 22px 0;">
                <h4 style="margin: 0 0 10px;">Vendor Portal Login</h4>
                <p style="margin: 6px 0;"><strong>Portal:</strong> <a href="{{ $portalUrl }}" style="color: #2563eb;">{{ $portalUrl }}</a></p>
                <p style="margin: 6px 0;"><strong>Email:</strong> {{ $vendor->email }}</p>
                <p style="margin: 6px 0;"><strong>Temporary Password:</strong> {{ $plainPassword }}</p>
                <p style="margin: 10px 0 0; font-size: 13px; color: #4b5563;">
                    Please log in with this temporary password. You will be required to change it on first login.
                </p>
            </div>

            <p style="margin-bottom: 0;">
                If you have any questions, please contact our procurement support team.
            </p>
        </div>

        <div style="background: #1e3a8a; color: #ffffff; text-align: center; padding: 14px;">
            African Union Procurement Portal · Official Communications
        </div>
    </div>
</body>

</html>
