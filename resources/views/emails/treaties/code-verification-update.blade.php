@extends('emails.layouts.base')

@section('content')
    <h2 style="margin-bottom: 14px;">Treaty {{ $codeLabel }} Code Verification Update</h2>

    <p>
        A treaty <strong>{{ strtolower($codeLabel) }}</strong> proof-of-service code has just been verified by
        <strong>{{ $actorName }}</strong>.
    </p>

    <div class="info-list">
        <p style="margin: 0 0 8px 0;"><strong>Member State:</strong> {{ $memberStateName }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Treaty Title:</strong> {{ $treaty->title }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Treaty Reference:</strong> {{ $treaty->reference_code ?: 'N/A' }}</p>
        <p style="margin: 0;"><strong>Verification Time:</strong>
            @if ($codeType === 'ratified')
                {{ optional($status->ratified_service_code_verified_at)->format('d M Y, H:i') ?: now()->format('d M Y, H:i') }}
            @else
                {{ optional($status->signed_service_code_verified_at)->format('d M Y, H:i') ?: now()->format('d M Y, H:i') }}
            @endif
        </p>
    </div>

    <div style="border: 1px solid #dcfce7; background: #f0fdf4; border-radius: 10px; padding: 16px; margin-bottom: 18px;">
        <p style="margin: 0 0 6px 0; color: #166534;"><strong>{{ $codeLabel }} Service Code</strong></p>
        <p style="margin: 0; font-family: 'Courier New', monospace; font-size: 22px; font-weight: 700; letter-spacing: 1px; color: #14532d;">
            {{ $codeType === 'ratified' ? $status->ratified_service_code : $status->signed_service_code }}
        </p>
    </div>

    <div class="d-flex" style="gap:10px; flex-wrap:wrap;">
        <a href="{{ $reviewUrl }}" class="btn">Open Treaty Review</a>
        <a href="{{ $memberPortalUrl }}" class="btn" style="background:#0ea5e9;">Open Member Treaty Portal</a>
    </div>

    <div class="divider"></div>

    <p style="font-size: 14px; color: #6b7280;">
        This email was sent to relevant member-state and back-office users for treaty workflow visibility.
    </p>
@endsection
