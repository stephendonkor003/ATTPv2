@extends('emails.layouts.base')

@section('content')
    <h2 style="margin-bottom: 14px;">New Member-State Question Submission</h2>

    <p>
        A question has been submitted by <strong>{{ $memberStateName }}</strong> through the member-state portal.
        You are receiving this because you are configured as an AU question responder.
    </p>

    <div class="info-list">
        <p style="margin: 0 0 8px 0;"><strong>Subject:</strong> {{ $question->subject }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Asked On:</strong> {{ optional($question->asked_on)->format('d M Y') }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Priority:</strong> {{ ucfirst((string) $question->priority) }}</p>
        <p style="margin: 0;"><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', (string) $question->status)) }}</p>
    </div>

    <div class="alert alert-info">
        <strong>Question Preview:</strong><br>
        {{ \Illuminate\Support\Str::limit((string) $question->question_text, 420) }}
    </div>

    <a href="{{ $questionsDeskUrl }}" class="btn">Open Questions Desk</a>

    <div class="divider"></div>

    <p style="font-size: 14px; color: #6b7280;">
        This notice was generated automatically by the member-state Ask Questions workflow.
    </p>
@endsection
