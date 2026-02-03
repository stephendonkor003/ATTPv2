@extends('emails.layouts.base')

@section('content')
    <h2>Purchase Request</h2>

    <p>Hello <strong>{{ $recipientName }}</strong>,</p>

    <p>
        Please find attached a Purchase Request (<strong>{{ $purchaseRequest->reference_no }}</strong>).
    </p>

    <div class="info-list">
        <ul>
            <li><strong>Program:</strong> {{ $purchaseRequest->programFunding?->program?->name ?? '—' }}</li>
            <li><strong>Sub-Activity:</strong> {{ $purchaseRequest->subActivity?->name ?? '—' }}</li>
            <li><strong>Start Year:</strong> {{ $purchaseRequest->start_year }}</li>
            <li><strong>Commitment Date:</strong> {{ $purchaseRequest->commitment_date?->format('F j, Y') ?? '—' }}</li>
            <li><strong>Delivery Date:</strong> {{ $purchaseRequest->delivery_date?->format('F j, Y') ?? '—' }}</li>
            <li>
                <strong>Total Amount:</strong>
                {{ $purchaseRequest->currency ?? $purchaseRequest->programFunding?->program?->currency ?? '' }}
                {{ number_format((float) $purchaseRequest->total_amount, 2) }}
            </li>
        </ul>
    </div>

    @if (!empty($purchaseRequest->description))
        <div class="alert alert-info">
            <strong>Description:</strong> {{ $purchaseRequest->description }}
        </div>
    @endif

    <p>
        The full PDF is attached to this email for your review.
    </p>

    <div class="divider"></div>

    <p style="margin-top: 25px; color: #718096; font-size: 13px;">
        Sent from {{ config('app.name') }} on {{ now()->format('d M Y, H:i') }}.
    </p>
@endsection
