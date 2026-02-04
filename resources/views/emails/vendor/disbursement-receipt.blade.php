@extends('emails.layouts.base')

@section('content')
    <h2>Payment Receipt</h2>

    <p>Hello <strong>{{ $disbursement->vendor?->name ?? 'Vendor' }}</strong>,</p>

    <p>
        We confirm that a payment has been disbursed for the procurement
        <strong>{{ $disbursement->procurement?->title ?? 'N/A' }}</strong>.
    </p>

    <div class="info-list">
        <ul>
            <li><strong>Receipt Reference:</strong> {{ $disbursement->reference_no ?? 'N/A' }}</li>
            <li><strong>Purchase Order:</strong> {{ $disbursement->purchaseOrder?->reference_no ?? 'N/A' }}</li>
            <li><strong>Amount:</strong> {{ $disbursement->amount ? number_format($disbursement->amount, 2) : 'N/A' }}
                {{ $disbursement->currency ?? '' }}</li>
            <li><strong>Paid On:</strong> {{ $disbursement->paid_at?->format('M d, Y') ?? 'N/A' }}</li>
        </ul>
    </div>

    <p>
        Your official receipt is attached to this email. You can also access your records through the vendor portal.
    </p>

    <p>
        <a href="{{ $portalUrl }}" class="btn">Go to Vendor Portal</a>
    </p>
@endsection
