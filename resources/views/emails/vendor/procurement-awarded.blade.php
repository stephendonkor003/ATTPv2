@extends('emails.layouts.base')

@section('content')
    <h2>Procurement Award Notification</h2>

    <p>Hello <strong>{{ $vendor->name ?? 'Vendor' }}</strong>,</p>

    <p>
        We are pleased to inform you that your submission has been selected for award under the procurement
        <strong>{{ $procurement->title }}</strong>.
    </p>

    <div class="info-list">
        <ul>
            <li><strong>Procurement Reference:</strong> {{ $procurement->reference_no ?? 'N/A' }}</li>
            <li><strong>Approved Amount:</strong>
                {{ $negotiation->agreed_amount ? number_format($negotiation->agreed_amount, 2) : 'N/A' }}
            </li>
        </ul>
    </div>

    <div class="alert alert-success">
        You can now submit monthly invoices for this procurement through your vendor portal.
        Once reviewed and approved, the procurement team will generate the corresponding purchase order.
    </div>

    <p>
        You can log in to the vendor portal to review the status and any additional requests:
    </p>

    <p>
        <a href="{{ $portalUrl }}" class="btn">Go to Vendor Portal</a>
    </p>

    <div class="divider"></div>

    <p style="margin-top: 25px; color: #718096; font-size: 13px;">
        Thank you for participating in our procurement process.
    </p>
@endsection
