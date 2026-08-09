<?php

namespace App\Http\Controllers;

use App\Models\ConsortiumThinkTank;
use App\Notifications\MeReportingNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeReportingNotificationController extends Controller
{
    private const CATEGORIES = [
        'me_submission' => 'M&E submissions',
        'reporting_period' => 'Reporting periods',
        'performance_report' => 'Performance reports',
        'mission_report' => 'Mission reports',
        'deadline' => 'Deadlines',
        'corrective_action' => 'Corrective actions',
        'mov_validation' => 'MOV validation',
    ];

    private const SEVERITIES = [
        'danger' => 'Urgent',
        'warning' => 'Attention',
        'info' => 'Information',
        'success' => 'Completed',
        'secondary' => 'Administrative',
    ];

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.reporting_notifications.view')
            ->only(['index', 'read', 'unread', 'readAll', 'bulk']);
    }

    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $baseQuery = $this->notificationQuery($request);
        $query = clone $baseQuery;
        $this->applyFilters($query, $filters);

        match ($filters['sort']) {
            'oldest' => $query->oldest(),
            default => $query->latest(),
        };

        $metrics = [
            'total' => (clone $baseQuery)->count(),
            'unread' => (clone $baseQuery)->whereNull('read_at')->count(),
            'urgent' => (clone $baseQuery)
                ->whereNull('read_at')
                ->where('data', 'like', '%"severity":"danger"%')
                ->count(),
            'today' => (clone $baseQuery)->whereDate('created_at', today())->count(),
        ];

        return view('me.reporting-notifications.index', [
            'notifications' => $query->paginate($filters['per_page'])->withQueryString(),
            'filters' => $filters,
            'metrics' => $metrics,
            'categories' => self::CATEGORIES,
            'severityOptions' => self::SEVERITIES,
            'unreadOnly' => $filters['state'] === 'unread',
            'unreadCount' => $metrics['unread'],
            'activeFilterCount' => collect([
                $filters['q'],
                $filters['category'],
                $filters['severity'],
                $filters['from'],
                $filters['to'],
            ])->filter(fn ($value) => filled($value))->count(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $item = $this->ownedNotification($request, $notification);
        $item->markAsRead();
        $url = $this->safeNotificationUrl($request, data_get($item->data, 'url'));

        return $url
            ? redirect()->to($url)
            : redirect()->route('budget.me.reporting-notifications.index')
                ->with('success', 'The notification was marked as read. Its linked record is no longer available.');
    }

    public function unread(Request $request, string $notification): RedirectResponse
    {
        $this->ownedNotification($request, $notification)->markAsUnread();

        return back()->with('success', 'The notification was marked as unread.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $updated = $request->user()->unreadNotifications()
            ->where('type', MeReportingNotification::class)
            ->update(['read_at' => now()]);

        return back()->with('success', $updated
            ? number_format($updated).' reporting '.str('notification')->plural($updated).' marked as read.'
            : 'There were no unread reporting notifications.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notification_ids' => ['required', 'array', 'min:1', 'max:100'],
            'notification_ids.*' => ['required', 'uuid', 'distinct'],
            'action' => ['required', Rule::in(['mark_read', 'mark_unread'])],
        ]);
        $query = $this->notificationQuery($request)
            ->whereIn('id', $validated['notification_ids']);
        $count = (clone $query)->count();
        abort_if($count !== count($validated['notification_ids']), 404);

        $query->update(['read_at' => $validated['action'] === 'mark_read' ? now() : null]);
        $label = $validated['action'] === 'mark_read' ? 'read' : 'unread';

        return back()->with('success', number_format($count).' reporting '.str('notification')->plural($count).' marked as '.$label.'.');
    }

    public function portalIndex(Request $request): View
    {
        $member = $this->portalMember($request);
        $unreadOnly = $request->boolean('unread');
        $query = $this->notificationQuery($request)
            ->when($unreadOnly, fn ($builder) => $builder->whereNull('read_at'));

        return view('think-tank.reporting-notifications.index', [
            'member' => $member,
            'portalRouteParams' => $this->portalRouteParams($request, $member),
            'notifications' => $query->latest()->paginate(25)->withQueryString(),
            'unreadOnly' => $unreadOnly,
            'unreadCount' => $this->notificationQuery($request)->whereNull('read_at')->count(),
        ]);
    }

    public function portalRead(Request $request, string $notification): RedirectResponse
    {
        $item = $this->ownedNotification($request, $notification);
        $item->markAsRead();
        $url = $this->safeNotificationUrl($request, data_get($item->data, 'url'));
        $member = $this->portalMember($request);

        return $url
            ? redirect()->to($url)
            : redirect()->route('think-tank.reporting-notifications.index', $this->portalRouteParams($request, $member))
                ->with('success', 'The notification was marked as read. Its linked record is no longer available.');
    }

    public function portalReadAll(Request $request): RedirectResponse
    {
        $updated = $request->user()->unreadNotifications()
            ->where('type', MeReportingNotification::class)
            ->update(['read_at' => now()]);

        return back()->with('success', $updated
            ? number_format($updated).' reporting '.str('notification')->plural($updated).' marked as read.'
            : 'There were no unread reporting notifications.');
    }

    private function notificationQuery(Request $request)
    {
        return $request->user()->notifications()->where('type', MeReportingNotification::class);
    }

    private function ownedNotification(Request $request, string $notification)
    {
        return $this->notificationQuery($request)->findOrFail($notification);
    }

    /** @return array{q:string,state:string,category:string,severity:string,from:string,to:string,sort:string,per_page:int} */
    private function validatedFilters(Request $request): array
    {
        if ($request->boolean('unread') && ! $request->filled('state')) {
            $request->merge(['state' => 'unread']);
        }
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', Rule::in(['all', 'unread', 'read'])],
            'category' => ['nullable', Rule::in(array_keys(self::CATEGORIES))],
            'severity' => ['nullable', Rule::in(array_keys(self::SEVERITIES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
        ]);

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'state' => (string) ($validated['state'] ?? 'all'),
            'category' => (string) ($validated['category'] ?? ''),
            'severity' => (string) ($validated['severity'] ?? ''),
            'from' => (string) ($validated['from'] ?? ''),
            'to' => (string) ($validated['to'] ?? ''),
            'sort' => (string) ($validated['sort'] ?? 'newest'),
            'per_page' => (int) ($validated['per_page'] ?? 20),
        ];
    }

    private function applyFilters($query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $like = '%'.addcslashes(strtolower($filters['q']), '%_\\').'%';
            $query->whereRaw('LOWER(data) LIKE ?', [$like]);
        }
        if ($filters['state'] === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filters['state'] === 'read') {
            $query->whereNotNull('read_at');
        }
        if ($filters['category'] !== '') {
            $query->where('data', 'like', '%"category":"'.$filters['category'].'"%');
        }
        if ($filters['severity'] !== '') {
            $query->where('data', 'like', '%"severity":"'.$filters['severity'].'"%');
        }
        if ($filters['from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
    }

    private function safeNotificationUrl(Request $request, mixed $candidate): ?string
    {
        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }
        $candidate = trim($candidate);
        if (preg_match('/[\x00-\x1F\x7F]/', $candidate)) {
            return null;
        }
        if (str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//')) {
            return $request->getSchemeAndHttpHost().$candidate;
        }

        $parts = parse_url($candidate);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || strcasecmp((string) ($parts['host'] ?? ''), $request->getHost()) !== 0) {
            return null;
        }
        $candidatePort = isset($parts['port'])
            ? (int) $parts['port']
            : (strtolower((string) $parts['scheme']) === 'https' ? 443 : 80);
        if ($candidatePort !== $request->getPort()) {
            return null;
        }

        return $candidate;
    }

    private function portalMember(Request $request): ConsortiumThinkTank
    {
        $user = $request->user();
        if (($user->isAdmin() || $user->isSuperAdmin()) && $request->filled('think_tank_member_id')) {
            return ConsortiumThinkTank::query()->findOrFail($request->query('think_tank_member_id'));
        }

        return $user->resolvedThinkTankMembership() ?? abort(403, 'No think tank membership is assigned.');
    }

    private function portalRouteParams(Request $request, ConsortiumThinkTank $member): array
    {
        return ($request->user()->isAdmin() || $request->user()->isSuperAdmin())
            ? ['think_tank_member_id' => $member->id]
            : [];
    }
}
