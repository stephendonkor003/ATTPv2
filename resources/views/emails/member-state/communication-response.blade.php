@extends('emails.layouts.base')

@section('content')
    <h2 style="margin-bottom: 14px;">Response from the African Union</h2>

    <p>
        Dear <strong>{{ $memberStateName }}</strong>,
    </p>
    <p>
        The African Union has provided a response to your submitted communication.
    </p>

    <div class="info-list">
        <p style="margin: 0 0 8px 0;"><strong>Subject:</strong> {{ $communication->subject }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Communication Date:</strong> {{ optional($communication->communication_date)->format('d M Y') }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Current Status:</strong> {{ ucfirst(str_replace('_', ' ', (string) $communication->status)) }}</p>
        <p style="margin: 0;"><strong>Responded At:</strong> {{ optional($communication->responded_at)->format('d M Y, H:i') ?? 'N/A' }}</p>
    </div>

    <div class="alert alert-success">
        <strong>AU Response:</strong><br>
        {!! nl2br(e((string) $communication->response_text)) !!}
    </div>

    <a href="{{ $portalUrl }}" class="btn">Open Member-State Communications</a>

    <div class="divider"></div>

    <p style="font-size: 14px; color: #6b7280;">
        This is an automated notification from the AU communication desk.
    </p>
@endsection
