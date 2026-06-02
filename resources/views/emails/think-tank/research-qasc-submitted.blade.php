<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Annex B QASC Submitted</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
    <p>Dear {{ $member->name }},</p>

    <p>
        Your Annex B ATTP Quality Assurance Self-Certification has been submitted for
        <strong>{{ $output->title }}</strong>.
    </p>

    <p>
        A PDF copy is attached to this email. You can also preview it from the think tank portal:
        <a href="{{ $previewUrl }}">{{ $previewUrl }}</a>
    </p>

    <p>
        Submission date:
        <strong>{{ $output->submitted_at?->format('d M Y H:i') ?? now()->format('d M Y H:i') }}</strong>
    </p>

    <p>Regards,<br>ATTP Secretariat</p>
</body>
</html>
