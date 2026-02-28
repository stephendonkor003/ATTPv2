<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\AuAspiration;
use App\Models\AuMemberState;
use App\Models\MemberStateNationalData;
use App\Models\SystemAuditLog;
use App\Support\IpGeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MemberStateNationalDataReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:national_data.review')->only(['index']);
        $this->middleware('permission:national_data.approve')->only(['updateStatus']);
    }

    public function index(Request $request)
    {
        $baseQuery = MemberStateNationalData::query();

        $query = MemberStateNationalData::query()
            ->with([
                'memberState:id,name,code,code_alpha2',
                'aspiration:id,number,title',
                'goal:id,number,title',
                'creator:id,name,email',
                'reviewer:id,name,email',
            ]);

        if ($request->filled('member_state_id')) {
            $query->where('member_state_id', $request->input('member_state_id'));
        }

        if ($request->filled('review_status')) {
            $query->where('review_status', $request->input('review_status'));
        }

        if ($request->filled('progress_status')) {
            $query->where('progress_status', $request->input('progress_status'));
        }

        if ($request->filled('aspiration_id')) {
            $query->where('aspiration_id', $request->input('aspiration_id'));
        }

        if ($request->filled('goal_id')) {
            $query->where('goal_id', $request->input('goal_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('recorded_on', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('recorded_on', '<=', $request->input('to'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($term) {
                $inner->where('indicator_name', 'like', '%' . $term . '%')
                    ->orWhere('notes', 'like', '%' . $term . '%')
                    ->orWhere('policy_actions', 'like', '%' . $term . '%')
                    ->orWhere('public_engagement_summary', 'like', '%' . $term . '%')
                    ->orWhere('national_projects_programs', 'like', '%' . $term . '%')
                    ->orWhereHas('memberState', function ($memberStateQuery) use ($term) {
                        $memberStateQuery->where('name', 'like', '%' . $term . '%')
                            ->orWhere('code', 'like', '%' . $term . '%')
                            ->orWhere('code_alpha2', 'like', '%' . $term . '%');
                    });
            });
        }

        $entries = $query
            ->orderByRaw("
                CASE review_status
                    WHEN 'pending' THEN 1
                    WHEN 'revision_required' THEN 2
                    WHEN 'rejected' THEN 3
                    WHEN 'approved' THEN 4
                    ELSE 5
                END
            ")
            ->orderByDesc('recorded_on')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('review_status', 'pending')->count(),
            'approved' => (clone $baseQuery)->where('review_status', 'approved')->count(),
            'revision_required' => (clone $baseQuery)->where('review_status', 'revision_required')->count(),
            'rejected' => (clone $baseQuery)->where('review_status', 'rejected')->count(),
            'approved_avg_score' => (float) ((clone $baseQuery)->where('review_status', 'approved')->avg('cooperation_score') ?? 0),
        ];

        $memberStates = AuMemberState::query()->active()->ordered()->get(['id', 'name']);
        $aspirations = AuAspiration::query()
            ->active()
            ->ordered()
            ->with(['goals' => function ($goalQuery) {
                $goalQuery->active()->ordered();
            }])
            ->get(['id', 'number', 'title']);

        return view('system.national-data-reviews.index', [
            'entries' => $entries,
            'memberStates' => $memberStates,
            'aspirations' => $aspirations,
            'stats' => $stats,
            'filters' => [
                'q' => (string) $request->input('q', ''),
                'member_state_id' => (string) $request->input('member_state_id', ''),
                'review_status' => (string) $request->input('review_status', ''),
                'progress_status' => (string) $request->input('progress_status', ''),
                'aspiration_id' => (string) $request->input('aspiration_id', ''),
                'goal_id' => (string) $request->input('goal_id', ''),
                'from' => (string) $request->input('from', ''),
                'to' => (string) $request->input('to', ''),
            ],
        ]);
    }

    public function updateStatus(Request $request, MemberStateNationalData $entry)
    {
        $validated = $request->validate([
            'review_status' => ['required', 'in:pending,approved,revision_required,rejected'],
            'review_notes' => ['nullable', 'string', 'max:12000'],
        ]);

        $isPending = $validated['review_status'] === 'pending';

        $entry->update([
            'review_status' => $validated['review_status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by' => $isPending ? null : $request->user()->id,
            'reviewed_at' => $isPending ? null : now(),
            'updated_by' => $request->user()->id,
        ]);

        $this->logReviewAudit(
            $request,
            'national_data_review_status_updated',
            'Back-office reviewer updated national data review status.',
            [
                'entry_id' => $entry->id,
                'member_state_id' => $entry->member_state_id,
                'review_status' => $validated['review_status'],
            ]
        );

        return back()->with('success', 'National data review status updated successfully.');
    }

    private function logReviewAudit(
        Request $request,
        string $action,
        string $actionMessage,
        array $payload = []
    ): void {
        try {
            SystemAuditLog::create([
                'user_id' => optional($request->user())->id,
                'module' => 'national_data',
                'action' => $action,
                'action_message' => $actionMessage,
                'description' => $actionMessage,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => optional($request->route())->getName(),
                'ip_address' => $request->ip(),
                'country' => IpGeo::countryForIp($request->ip()),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'status_code' => 200,
                'payload' => $payload,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to persist national data review audit log entry.', [
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
