@extends('emails.layouts.base')

@section('content')
    <h2 style="margin-bottom: 14px;">New Member-State Communication</h2>

    <p>
        A new communication has been submitted from <strong>{{ $memberStateName }}</strong> and requires AU back-office
        review.
    </p>

    <div class="info-list">
        <p style="margin: 0 0 8px 0;"><strong>Subject:</strong> {{ $communication->subject }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Date:</strong> {{ optional($communication->communication_date)->format('d M Y') }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Channel:</strong> {{ ucfirst(str_replace('_', ' ', (string) $communication->channel)) }}</p>
        <p style="margin: 0;"><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', (string) $communication->status)) }}</p>
    </div>

    <div class="alert alert-info">
        <strong>Message Preview:</strong><br>
        {{ \Illuminate\Support\Str::limit((string) $communication->message, 380) }}
    </div>

    <p style="margin-top: 18px;">
        Attachment count:
        <strong>{{ $communication->attachments?->count() ?? 0 }}</strong>
    </p>

    <a href="{{ $reviewUrl }}" class="btn">Open Communications Desk</a>

    <div class="divider"></div>

    <p style="font-size: 14px; color: #6b7280;">
        This notice was generated automatically by the member-state communication workflow.
    </p>
@endsection
