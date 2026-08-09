@extends('layouts.app')

@section('title', 'M&E Reporting Notifications')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.reporting-notifications.partials.styles')
@endpush

@section('content')
@php
    $tabQuery = request()->except(['page', 'state', 'unread']);
    $readCount = max(0, $metrics['total'] - $metrics['unread']);
    $categoryInitials = [
        'me_submission' => 'ME',
        'reporting_period' => 'RP',
        'performance_report' => 'PR',
        'mission_report' => 'MR',
        'deadline' => 'DL',
        'corrective_action' => 'CA',
        'mov_validation' => 'MV',
    ];
@endphp
<div class="mel-notify">
    <header class="mn-header">
        <div>
            <span class="mn-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>Reporting notification centre</h1>
            <p>Track submission decisions, reporting deadlines, corrective actions and Means of Verification checks without losing important follow-up work.</p>
        </div>
        <div class="mn-header-count" aria-label="Unread notifications">
            <strong>{{ number_format($metrics['unread']) }}</strong>
            <span>unread<br>notifications</span>
        </div>
    </header>

    @if(session('success'))<div class="alert alert-success mn-alert" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger mn-alert" role="alert">
            <strong>The notification request could not be completed.</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="mn-metrics" aria-label="Notification summary">
        <article class="mn-metric">
            <span class="mn-metric-label">All notifications</span>
            <strong>{{ number_format($metrics['total']) }}</strong>
            <small>Your complete M&amp;E notification history</small>
        </article>
        <article class="mn-metric" style="--metric:#a76b17">
            <span class="mn-metric-label">Unread</span>
            <strong>{{ number_format($metrics['unread']) }}</strong>
            <small>Items still requiring your attention</small>
        </article>
        <article class="mn-metric" style="--metric:#ae3f3d">
            <span class="mn-metric-label">Urgent and unread</span>
            <strong>{{ number_format($metrics['urgent']) }}</strong>
            <small>Overdue or high-priority actions</small>
        </article>
        <article class="mn-metric" style="--metric:#187459">
            <span class="mn-metric-label">Received today</span>
            <strong>{{ number_format($metrics['today']) }}</strong>
            <small>New activity since midnight</small>
        </article>
    </section>

    <details class="mn-panel" @if($activeFilterCount > 0) open @endif>
        <summary class="mn-panel-head mn-filter-summary">
            <div><h2>Search and filter</h2><p>Narrow the inbox by workstream, priority or date.</p></div>
            <span class="mn-badge">{{ $activeFilterCount }} active {{ str('filter')->plural($activeFilterCount) }}</span>
        </summary>
        <div class="mn-panel-body">
            <form method="GET" action="{{ route('budget.me.reporting-notifications.index') }}" class="mn-filter-grid">
                <input type="hidden" name="state" value="{{ $filters['state'] }}">
                <div class="mn-field mn-filter-wide">
                    <label for="notification-search">Search notification content</label>
                    <input id="notification-search" class="form-control" name="q" value="{{ $filters['q'] }}" placeholder="Title, message, event or linked record">
                </div>
                <div class="mn-field">
                    <label for="notification-category">Workstream</label>
                    <select id="notification-category" class="form-select" name="category">
                        <option value="">All workstreams</option>
                        @foreach($categories as $key => $label)<option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="mn-field">
                    <label for="notification-severity">Priority</label>
                    <select id="notification-severity" class="form-select" name="severity">
                        <option value="">All priorities</option>
                        @foreach($severityOptions as $key => $label)<option value="{{ $key }}" @selected($filters['severity'] === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="mn-field">
                    <label for="notification-from">Received from</label>
                    <input id="notification-from" class="form-control" type="date" name="from" value="{{ $filters['from'] }}">
                </div>
                <div class="mn-field">
                    <label for="notification-to">Received to</label>
                    <input id="notification-to" class="form-control" type="date" name="to" value="{{ $filters['to'] }}">
                </div>
                <div class="mn-field">
                    <label for="notification-sort">Sort order</label>
                    <select id="notification-sort" class="form-select" name="sort">
                        <option value="newest" @selected($filters['sort'] === 'newest')>Newest first</option>
                        <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest first</option>
                    </select>
                </div>
                <div class="mn-field">
                    <label for="notification-page-size">Items per page</label>
                    <select id="notification-page-size" class="form-select" name="per_page">
                        @foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }} items</option>@endforeach
                    </select>
                </div>
                <div class="mn-filter-actions">
                    <a class="mn-btn mn-btn-secondary" href="{{ route('budget.me.reporting-notifications.index', $filters['state'] !== 'all' ? ['state' => $filters['state']] : []) }}">Clear filters</a>
                    <button class="mn-btn mn-btn-primary" type="submit">Apply filters</button>
                </div>
            </form>
        </div>
    </details>

    <section class="mn-panel mt-3" aria-labelledby="notification-inbox-title">
        <div class="mn-panel-head">
            <div><h2 id="notification-inbox-title">Personal notification inbox</h2><p>Only notifications assigned to your account are displayed.</p></div>
            <nav class="mn-tabs" aria-label="Notification read status">
                <a class="mn-tab {{ $filters['state'] === 'all' ? 'active' : '' }}" href="{{ route('budget.me.reporting-notifications.index', array_merge($tabQuery, ['state' => 'all'])) }}">All <span>{{ number_format($metrics['total']) }}</span></a>
                <a class="mn-tab {{ $filters['state'] === 'unread' ? 'active' : '' }}" href="{{ route('budget.me.reporting-notifications.index', array_merge($tabQuery, ['state' => 'unread'])) }}">Unread <span>{{ number_format($metrics['unread']) }}</span></a>
                <a class="mn-tab {{ $filters['state'] === 'read' ? 'active' : '' }}" href="{{ route('budget.me.reporting-notifications.index', array_merge($tabQuery, ['state' => 'read'])) }}">Read <span>{{ number_format($readCount) }}</span></a>
            </nav>
        </div>

        <form id="notification-bulk-form" method="POST" action="{{ route('budget.me.reporting-notifications.bulk') }}">@csrf</form>
        <div class="mn-bulk">
            <div class="mn-bulk-left">
                <label class="mn-select-label" for="select-notifications"><input id="select-notifications" class="mn-checkbox" type="checkbox"> Select this page</label>
                <span id="selected-notification-count" class="mn-badge secondary">0 selected</span>
                <span class="mn-showing">@if($notifications->total())Showing {{ number_format($notifications->firstItem()) }}-{{ number_format($notifications->lastItem()) }} of {{ number_format($notifications->total()) }}@else No matching notifications @endif</span>
            </div>
            <div class="mn-bulk-actions">
                <button class="mn-btn mn-btn-secondary mn-bulk-button" type="submit" form="notification-bulk-form" name="action" value="mark_unread" disabled>Mark unread</button>
                <button class="mn-btn mn-btn-primary mn-bulk-button" type="submit" form="notification-bulk-form" name="action" value="mark_read" disabled>Mark read</button>
                @if($metrics['unread'] > 0)
                    <form method="POST" action="{{ route('budget.me.reporting-notifications.read-all') }}">@csrf<button class="mn-btn mn-btn-secondary" type="submit">Mark all read</button></form>
                @endif
            </div>
        </div>

        <div class="mn-list">
            @forelse($notifications as $notification)
                @php
                    $data = is_array($notification->data) ? $notification->data : [];
                    $category = (string) ($data['category'] ?? '');
                    $severity = in_array(($data['severity'] ?? ''), array_keys($severityOptions), true) ? (string) $data['severity'] : 'info';
                    $title = trim((string) ($data['title'] ?? '')) ?: 'Reporting notification';
                    $message = trim((string) ($data['message'] ?? ''));
                    $hasTarget = filled($data['url'] ?? null);
                @endphp
                <article class="mn-item {{ $notification->read_at ? 'read' : 'unread' }}">
                    <div class="pt-2"><input class="mn-checkbox notification-checkbox" type="checkbox" name="notification_ids[]" value="{{ $notification->id }}" form="notification-bulk-form" aria-label="Select {{ $title }}"></div>
                    <div class="mn-item-icon {{ $severity }}" aria-hidden="true">{{ $categoryInitials[$category] ?? 'ME' }}</div>
                    <div class="mn-item-content">
                        <div class="mn-item-title-row">
                            @unless($notification->read_at)<span class="mn-new-dot" title="Unread"></span>@endunless
                            <h3 class="mn-item-title" title="{{ $title }}">{{ $title }}</h3>
                            <span class="mn-badge {{ $severity }}">{{ $severityOptions[$severity] ?? str($severity)->headline() }}</span>
                        </div>
                        @if($message !== '')<p class="mn-message">{{ $message }}</p>@endif
                        <div class="mn-meta">
                            <span>{{ $categories[$category] ?? str($category ?: 'M&E reporting')->headline() }}</span>
                            @if(filled($data['event'] ?? null))<span>Event: {{ str($data['event'])->headline() }}</span>@endif
                            <span title="{{ $notification->created_at?->format('d M Y, H:i:s') }}">{{ $notification->created_at?->diffForHumans() ?: 'Time unavailable' }}</span>
                            @if($notification->read_at)<span>Read {{ $notification->read_at->diffForHumans() }}</span>@endif
                        </div>
                    </div>
                    <div class="mn-item-actions">
                        @if($notification->read_at)
                            <form method="POST" action="{{ route('budget.me.reporting-notifications.unread', $notification->id) }}">@csrf<button class="mn-btn mn-btn-secondary" type="submit">Mark unread</button></form>
                        @endif
                        <form method="POST" action="{{ route('budget.me.reporting-notifications.read', $notification->id) }}">@csrf<button class="mn-btn mn-btn-primary" type="submit">{{ $hasTarget ? 'Open item' : 'Mark read' }}</button></form>
                    </div>
                </article>
            @empty
                <div class="mn-empty">
                    <div class="mn-empty-mark" aria-hidden="true">0</div>
                    <strong>No notifications match this view</strong>
                    <span>Change the read-status tab, clear a filter, or return when new reporting activity is assigned to you.</span>
                    @if($activeFilterCount > 0)<a class="mn-btn mn-btn-secondary mt-3" href="{{ route('budget.me.reporting-notifications.index') }}">Clear all filters</a>@endif
                </div>
            @endforelse
        </div>
        @if($notifications->hasPages())<div class="mn-pagination">{{ $notifications->links() }}</div>@endif
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('select-notifications');
    const checkboxes = Array.from(document.querySelectorAll('.notification-checkbox'));
    const counter = document.getElementById('selected-notification-count');
    const buttons = Array.from(document.querySelectorAll('.mn-bulk-button'));
    if (!selectAll || !counter) return;

    function refreshSelection() {
        const selected = checkboxes.filter(function (item) { return item.checked; }).length;
        counter.textContent = selected + ' selected';
        buttons.forEach(function (button) { button.disabled = selected === 0; });
        selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
        selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
    }
    selectAll.addEventListener('change', function () {
        checkboxes.forEach(function (item) { item.checked = selectAll.checked; });
        refreshSelection();
    });
    checkboxes.forEach(function (item) { item.addEventListener('change', refreshSelection); });
    refreshSelection();
});
</script>
@endpush
