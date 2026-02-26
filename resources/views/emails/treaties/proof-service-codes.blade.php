<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Treaty Proof-of-Service Codes</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 32px;">
    <div style="max-width: 720px; margin: auto; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="background-color: #0f766e; padding: 20px; text-align: center; color: #ffffff;">
            <h2 style="margin: 0;">Treaty Proof-of-Service Codes</h2>
        </div>

        <div style="padding: 24px; color: #1f2937; background-color: #ffffff;">
            @if ($isResend)
                <p style="margin-top: 0;"><strong>This is a resend request from the member state portal.</strong></p>
            @endif

            <p><strong>Member State:</strong> {{ $memberStateName }}</p>
            <p><strong>Treaty:</strong> {{ $treaty->title }}</p>
            <p><strong>Reference:</strong> {{ $treaty->reference_code ?: 'N/A' }}</p>

            <div style="margin: 20px 0; padding: 16px; border: 1px solid #d1d5db; border-radius: 8px; background: #f9fafb;">
                <h4 style="margin-top: 0; margin-bottom: 10px;">Codes Submitted for Legal Verification</h4>

                @if ($status->signed_service_code)
                    <p style="margin: 6px 0;">
                        <strong>Signed Code:</strong>
                        <span style="font-family: 'Courier New', monospace;">{{ $status->signed_service_code }}</span>
                        @if ($status->signed_service_code_verified_at)
                            <span style="color: #065f46;">(Already verified)</span>
                        @endif
                    </p>
                @endif

                @if ($status->ratified_service_code)
                    <p style="margin: 6px 0;">
                        <strong>Ratified Code:</strong>
                        <span style="font-family: 'Courier New', monospace;">{{ $status->ratified_service_code }}</span>
                        @if ($status->ratified_service_code_verified_at)
                            <span style="color: #065f46;">(Already verified)</span>
                        @endif
                    </p>
                @endif
            </div>

            <p style="margin-bottom: 16px;">
                Please validate these codes in the AU treaty matrix before marking final completion of original submission.
            </p>

            <a href="{{ $reviewUrl }}"
                style="display: inline-block; padding: 10px 14px; border-radius: 6px; background: #0f766e; color: #ffffff; text-decoration: none;">
                Open Treaty Review
            </a>
        </div>

        <div style="background-color: #0f766e; color: #ffffff; text-align: center; padding: 12px;">
            Africa Think Tank Platform - African Union Commission
        </div>
    </div>
</body>

</html>
