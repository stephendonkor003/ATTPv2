@extends('emails.layouts.base')

@section('content')
    <h2>Contract Termination Notice</h2>

    <p>Hello <strong>{{ $vendor->name ?? 'Vendor' }}</strong>,</p>

    <p>
        This is to formally notify you that the contract for the procurement
        <strong>{{ $procurement->title }}</strong> (Reference: {{ $procurement->reference_no ?? 'N/A' }})
        has been terminated.
    </p>

    <div class="alert alert-danger">
        <strong>Reason for termination:</strong> {{ $reason }}
    </div>

    <p>
        If you have questions or require further clarification, please contact the procurement team
        through the vendor portal.
    </p>

    <p>
        <a href="{{ $portalUrl }}" class="btn">Go to Vendor Portal</a>
    </p>

    <div class="divider"></div>

    <p style="margin-top: 25px; color: #718096; font-size: 13px;">
        Thank you for your cooperation.
    </p>
@endsection
