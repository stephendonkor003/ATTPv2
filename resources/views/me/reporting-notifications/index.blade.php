@extends('layouts.app')

@section('title', 'Reporting Notifications')
@section('lean_admin_scripts', '1')

@section('content')
<div class="container-fluid py-4" style="max-width:1100px">
    <div class="p-4 rounded-4 text-white mb-4" style="background:linear-gradient(120deg,#163b65,#0b6d50)">
        <div class="d-flex justify-content-between align-items-center gap-3">
            <div><div class="small text-uppercase fw-bold opacity-75">Monitoring &amp; Evaluation</div><h2 class="text-white fw-bold mb-1">Notifications &amp; Reminders</h2><p class="mb-0 opacity-75">Deadlines, review decisions, corrective actions and MOV validation in one place.</p></div>
            <span class="badge bg-light text-dark fs-6">{{ $unreadCount }} unread</span>
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div><a href="{{ route('budget.me.reporting-notifications.index') }}" class="btn btn-sm {{ !$unreadOnly?'btn-success':'btn-outline-success' }}">All</a> <a href="{{ route('budget.me.reporting-notifications.index',['unread'=>1]) }}" class="btn btn-sm {{ $unreadOnly?'btn-success':'btn-outline-success' }}">Unread</a></div>
        @if($unreadCount)<form method="POST" action="{{ route('budget.me.reporting-notifications.read-all') }}">@csrf<button class="btn btn-sm btn-outline-secondary">Mark all read</button></form>@endif
    </div>
    <div class="card border-0 shadow-sm"><div class="list-group list-group-flush">
        @forelse($notifications as $notification)
            @php($data=$notification->data)
            <form method="POST" action="{{ route('budget.me.reporting-notifications.read',$notification->id) }}">
                @csrf
                <button class="list-group-item list-group-item-action text-start w-100 p-3 {{ $notification->read_at?'':'bg-light' }}">
                    <div class="d-flex justify-content-between gap-3"><div><div class="d-flex align-items-center gap-2"><strong>{{ $data['title'] ?? 'Reporting notification' }}</strong>@unless($notification->read_at)<span class="badge bg-primary">New</span>@endunless</div><div class="text-muted mt-1">{{ $data['message'] ?? '' }}</div><small class="text-muted">{{ $notification->created_at?->diffForHumans() }}</small></div><i class="feather-chevron-right"></i></div>
                </button>
            </form>
        @empty
            <div class="text-center text-muted p-5"><i class="feather-bell-off fs-2 d-block mb-2"></i>No reporting notifications in this view.</div>
        @endforelse
    </div>@if($notifications->hasPages())<div class="card-footer bg-white">{{ $notifications->links() }}</div>@endif</div>
</div>
@endsection
