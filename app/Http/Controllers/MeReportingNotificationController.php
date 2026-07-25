<?php

namespace App\Http\Controllers;

use App\Models\ConsortiumThinkTank;
use App\Notifications\MeReportingNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeReportingNotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.reporting_notifications.view')->only(['index', 'read', 'readAll']);
    }

    public function index(Request $request): View
    {
        $unreadOnly = $request->boolean('unread');
        $query = $request->user()->notifications()
            ->where('type', MeReportingNotification::class)
            ->when($unreadOnly, fn ($builder) => $builder->whereNull('read_at'));

        return view('me.reporting-notifications.index', [
            'notifications' => $query->latest()->paginate(25)->withQueryString(),
            'unreadOnly' => $unreadOnly,
            'unreadCount' => $request->user()->unreadNotifications()
                ->where('type', MeReportingNotification::class)
                ->count(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()
            ->where('type', MeReportingNotification::class)
            ->findOrFail($notification);
        $item->markAsRead();
        $url = data_get($item->data, 'url');

        return $url ? redirect()->to($url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->where('type', MeReportingNotification::class)
            ->update(['read_at' => now()]);

        return back()->with('success', 'All reporting notifications marked as read.');
    }

    public function portalIndex(Request $request): View
    {
        $member = $this->portalMember($request);
        $unreadOnly = $request->boolean('unread');
        $query = $request->user()->notifications()
            ->where('type', MeReportingNotification::class)
            ->when($unreadOnly, fn ($builder) => $builder->whereNull('read_at'));

        return view('think-tank.reporting-notifications.index', [
            'member' => $member,
            'portalRouteParams' => $this->portalRouteParams($request, $member),
            'notifications' => $query->latest()->paginate(25)->withQueryString(),
            'unreadOnly' => $unreadOnly,
            'unreadCount' => $request->user()->unreadNotifications()
                ->where('type', MeReportingNotification::class)
                ->count(),
        ]);
    }

    public function portalRead(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()
            ->where('type', MeReportingNotification::class)
            ->findOrFail($notification);
        $item->markAsRead();
        $url = data_get($item->data, 'url');

        return $url ? redirect()->to($url) : back();
    }

    public function portalReadAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->where('type', MeReportingNotification::class)
            ->update(['read_at' => now()]);

        return back()->with('success', 'All reporting notifications marked as read.');
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
