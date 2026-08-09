<x-think-tank.partials.shell :member="$member" title="Reporting Notifications">
    <div class="tt-notifications">
        <div class="tt-notification-hero">
            <div class="tt-notification-hero-row">
                <div><div class="tt-notification-kicker">M&amp;E reporting</div><h2>Notifications &amp; Reminders</h2><p>Deadlines, returned reports, approvals and outstanding actions.</p></div>
                <span class="badge bg-light text-dark fs-6">{{ $unreadCount }} unread</span>
            </div>
        </div>
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
            <div><a href="{{ route('think-tank.reporting-notifications.index',$portalRouteParams) }}" class="btn btn-sm {{ !$unreadOnly?'btn-success':'btn-outline-success' }}">All</a> <a href="{{ route('think-tank.reporting-notifications.index',array_merge($portalRouteParams,['unread'=>1])) }}" class="btn btn-sm {{ $unreadOnly?'btn-success':'btn-outline-success' }}">Unread</a></div>
            @if($unreadCount)<form method="POST" action="{{ route('think-tank.reporting-notifications.read-all',$portalRouteParams) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Mark all read</button></form>@endif
        </div>
        <div class="card border-0 shadow-sm"><div class="list-group list-group-flush">
            @forelse($notifications as $notification)
                @php($data=$notification->data)
                <form method="POST" action="{{ route('think-tank.reporting-notifications.read',array_merge(['notification'=>$notification->id],$portalRouteParams)) }}">@csrf
                    <button class="list-group-item list-group-item-action text-start w-100 p-3 {{ $notification->read_at?'':'bg-light' }}">
                        <div class="d-flex justify-content-between gap-3"><div><strong>{{ $data['title'] ?? 'Reporting notification' }}</strong>@unless($notification->read_at)<span class="badge bg-primary ms-2">New</span>@endunless<div class="text-muted mt-1">{{ $data['message'] ?? '' }}</div><small class="text-muted">{{ $notification->created_at?->diffForHumans() }}</small></div><i class="feather-chevron-right"></i></div>
                    </button>
                </form>
            @empty
                <div class="text-center text-muted p-5"><i class="feather-bell-off fs-2 d-block mb-2"></i>No reporting notifications in this view.</div>
            @endforelse
        </div>@if($notifications->hasPages())<div class="card-footer bg-white">{{ $notifications->links() }}</div>@endif</div>
    </div>
</x-think-tank.partials.shell>
