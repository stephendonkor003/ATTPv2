<?php

namespace App\Http\Controllers;

use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class SystemAuditController extends Controller
{
    public function index(Request $request)
    {
        // Load recent logs for DataTable (client-side search/sort/pagination)
        $logs = SystemAuditLog::with('user')
            ->latest()
            ->limit(1000) // Limit to last 1000 entries for performance
            ->get();

        $impersonatorIds = $logs
            ->pluck('payload')
            ->map(fn ($payload) => data_get($payload, '_impersonation.administrator_id'))
            ->filter()
            ->unique()
            ->values();

        $impersonators = User::query()
            ->whereKey($impersonatorIds)
            ->get(['id', 'name', 'email'])
            ->keyBy(fn (User $user) => (string) $user->id);

        return view('system.audit.index', compact('logs', 'impersonators'));
    }
}
