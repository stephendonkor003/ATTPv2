<!doctype html>
<html>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#172033;line-height:1.6;">
    @php
        $vendor = $purchaseRequest->user;
        $subActivity = $purchaseRequest->subActivity;
        $activity = $subActivity?->activity;
        $project = $activity?->project;
        $program = $project?->program;
        $currency = $purchaseRequest->currency ?: 'USD';
        $requestedAmount = $currency . ' ' . number_format((float) $purchaseRequest->requested_amount, 2);
    @endphp

    <div style="max-width:680px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 14px 30px rgba(15,23,42,0.08);">
            <div style="background:#0f172a;color:#ffffff;padding:24px;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#93c5fd;font-weight:700;">
                    Vendor Purchase Request
                </div>
                <h2 style="margin:8px 0 0;font-size:22px;line-height:1.3;">
                    {{ $purchaseRequest->reference_no }}
                </h2>
                <p style="margin:8px 0 0;color:#cbd5e1;">
                    A vendor has submitted a purchase request for admin review.
                </p>
            </div>

            <div style="padding:24px;">
                <p style="margin-top:0;">Hello {{ $admin->name ?? 'Admin' }},</p>

                <p>
                    A new vendor purchase request is waiting in the admin queue. Please review the request details,
                    attached documents, and line items before moving it through the approval process.
                </p>

                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;width:190px;">Vendor</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">
                            {{ $vendor?->name ?? 'Vendor' }}
                            @if ($vendor?->email)
                                <div style="color:#64748b;font-size:13px;">{{ $vendor->email }}</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Title</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $purchaseRequest->title }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Requested Amount</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;font-weight:700;color:#006B3F;">{{ $requestedAmount }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Priority</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ ucfirst($purchaseRequest->priority ?? 'normal') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Date</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $purchaseRequest->needed_by?->format('d M Y') ?? 'Not set' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Funding Source</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">
                            {{ $subActivity?->name ?? 'N/A' }}
                            @if ($project?->name || $program?->name)
                                <div style="color:#64748b;font-size:13px;">
                                    {{ collect([$project?->name, $program?->name])->filter()->join(' / ') }}
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Submitted</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $purchaseRequest->created_at?->format('d M Y H:i') ?? 'N/A' }}</td>
                    </tr>
                </table>

                @if ($purchaseRequest->description)
                    <div style="margin:20px 0;padding:14px 16px;border:1px solid #dbe4ef;border-radius:8px;background:#f8fafc;">
                        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;margin-bottom:6px;">
                            Vendor Note
                        </div>
                        <div>{{ $purchaseRequest->description }}</div>
                    </div>
                @endif

                <div style="display:flex;gap:12px;flex-wrap:wrap;margin:22px 0;">
                    <div style="flex:1 1 180px;border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#ffffff;">
                        <div style="color:#64748b;font-size:12px;font-weight:700;text-transform:uppercase;">Line Items</div>
                        <div style="font-size:20px;font-weight:800;color:#0f172a;">{{ number_format($purchaseRequest->items->count()) }}</div>
                    </div>
                    <div style="flex:1 1 180px;border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#ffffff;">
                        <div style="color:#64748b;font-size:12px;font-weight:700;text-transform:uppercase;">Documents</div>
                        <div style="font-size:20px;font-weight:800;color:#0f172a;">{{ number_format($purchaseRequest->documents->count()) }}</div>
                    </div>
                </div>

                <p style="margin:26px 0;">
                    <a href="{{ $reviewUrl }}" style="display:inline-block;background:#006B3F;color:#ffffff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:700;">
                        Review Purchase Request
                    </a>
                </p>

                <p style="margin:22px 0 0;color:#64748b;font-size:13px;">
                    This notification was sent to active administrators in {{ config('app.name', 'ATTP') }}.
                </p>
            </div>

            <div style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 24px;color:#64748b;font-size:12px;">
                <strong style="color:#0f172a;">{{ config('app.name', 'ATTP') }}</strong><br>
                Automated vendor request alert. Please do not reply directly to this email.
            </div>
        </div>
    </div>
</body>
</html>
