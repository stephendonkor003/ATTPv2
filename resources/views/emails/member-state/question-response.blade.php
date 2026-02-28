@extends('emails.layouts.base')

@section('content')
    <h2 style="margin-bottom: 14px;">AU Response to Your Submitted Question</h2>

    <p>
        The African Union back-office has updated the response for a question submitted by
        <strong>{{ $memberStateName }}</strong>.
    </p>

    <div class="info-list">
        <p style="margin: 0 0 8px 0;"><strong>Subject:</strong> {{ $question->subject }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Asked On:</strong> {{ optional($question->asked_on)->format('d M Y') }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Priority:</strong> {{ ucfirst((string) $question->priority) }}</p>
        <p style="margin: 0;"><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', (string) $question->status)) }}</p>
    </div>

    <div class="alert alert-success">
        <strong>Response:</strong><br>
        {{ \Illuminate\Support\Str::limit((string) $question->answer_text, 800) }}
    </div>

    <a href="{{ $questionsPortalUrl }}" class="btn">Open Ask Questions Portal</a>

    <div class="divider"></div>

    <p style="font-size: 14px; color: #6b7280;">
        This message was generated automatically from the AU question response workflow.
    </p>
@endsection
