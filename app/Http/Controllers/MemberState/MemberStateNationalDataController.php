<?php

namespace App\Http\Controllers\MemberState;

use App\Http\Controllers\Controller;
use App\Models\AuAspiration;
use App\Models\AuFlagshipProject;
use App\Models\AuGoal;
use App\Models\Commodity;
use App\Models\MemberStateNationalData;
use App\Models\SystemAuditLog;
use App\Support\IpGeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MemberStateNationalDataController extends Controller
{
    public function index(Request $request)
    {
        $memberStateId = $request->user()->member_state_id;

        $baseQuery = MemberStateNationalData::query()
            ->where('member_state_id', $memberStateId);

        $query = MemberStateNationalData::query()
            ->with(['aspiration', 'goal', 'reviewer'])
            ->where('member_state_id', $memberStateId);

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($term) {
                $inner->where('indicator_name', 'like', '%' . $term . '%')
                    ->orWhere('notes', 'like', '%' . $term . '%')
                    ->orWhere('policy_actions', 'like', '%' . $term . '%')
                    ->orWhere('public_engagement_summary', 'like', '%' . $term . '%')
                    ->orWhere('national_projects_programs', 'like', '%' . $term . '%')
                    ->orWhere('data_source', 'like', '%' . $term . '%');
            });
        }

        if ($request->filled('year')) {
            $query->whereYear('recorded_on', (int) $request->input('year'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('recorded_on', (int) $request->input('month'));
        }

        if ($request->filled('day')) {
            $query->whereDay('recorded_on', (int) $request->input('day'));
        }

        if ($request->filled('aspiration_id')) {
            $query->where('aspiration_id', $request->input('aspiration_id'));
        }

        if ($request->filled('goal_id')) {
            $query->where('goal_id', $request->input('goal_id'));
        }

        if ($request->filled('reporting_period_type')) {
            $query->where('reporting_period_type', $request->input('reporting_period_type'));
        }

        if ($request->filled('progress_status')) {
            $query->where('progress_status', $request->input('progress_status'));
        }

        if ($request->filled('review_status')) {
            $query->where('review_status', $request->input('review_status'));
        }

        $entries = $query
            ->orderByDesc('recorded_on')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $years = MemberStateNationalData::query()
            ->where('member_state_id', $memberStateId)
            ->selectRaw('EXTRACT(YEAR FROM recorded_on) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        $aspirations = AuAspiration::query()
            ->active()
            ->ordered()
            ->with(['goals' => function ($goalQuery) {
                $goalQuery->active()->ordered();
            }])
            ->get();

        $flagshipProjects = AuFlagshipProject::query()
            ->active()
            ->ordered()
            ->get(['id', 'number', 'name']);

        $commodities = Commodity::query()
            ->orderBy('name')
            ->get(['id', 'name', 'category']);

        $stats = [
            'total_entries' => (clone $baseQuery)->count(),
            'average_cooperation_score' => (float) ((clone $baseQuery)->avg('cooperation_score') ?? 0),
            'average_agenda_awareness_score' => (float) ((clone $baseQuery)->avg('agenda_awareness_score') ?? 0),
            'average_flagship_awareness_score' => (float) ((clone $baseQuery)->avg('flagship_awareness_score') ?? 0),
            'average_outreach_coverage_score' => (float) ((clone $baseQuery)->avg('outreach_coverage_score') ?? 0),
            'completed_count' => (clone $baseQuery)->where('progress_status', 'completed')->count(),
            'active_initiatives_count' => (clone $baseQuery)->whereIn('progress_status', ['in_progress', 'advanced'])->count(),
            'people_reached_total' => (int) ((clone $baseQuery)->sum('people_reached') ?? 0),
            'approved_count' => (clone $baseQuery)->where('review_status', 'approved')->count(),
            'pending_review_count' => (clone $baseQuery)->where('review_status', 'pending')->count(),
            'revision_required_count' => (clone $baseQuery)->where('review_status', 'revision_required')->count(),
            'rejected_count' => (clone $baseQuery)->where('review_status', 'rejected')->count(),
        ];

        $monthlyTrend = MemberStateNationalData::query()
            ->where('member_state_id', $memberStateId)
            ->selectRaw("to_char(recorded_on, 'YYYY-MM') as month_key")
            ->selectRaw('AVG(cooperation_score) as avg_cooperation')
            ->selectRaw('AVG(outreach_coverage_score) as avg_outreach')
            ->selectRaw('COUNT(*) as entries_count')
            ->groupByRaw("to_char(recorded_on, 'YYYY-MM')")
            ->orderBy('month_key')
            ->get();

        return view('member-state.national-data.index', [
            'memberState' => $request->user()->memberState,
            'entries' => $entries,
            'years' => $years,
            'aspirations' => $aspirations,
            'flagshipProjects' => $flagshipProjects,
            'commodities' => $commodities,
            'stats' => $stats,
            'monthlyTrend' => $monthlyTrend,
            'filters' => [
                'q' => (string) $request->input('q', ''),
                'year' => (string) $request->input('year', ''),
                'month' => (string) $request->input('month', ''),
                'day' => (string) $request->input('day', ''),
                'aspiration_id' => (string) $request->input('aspiration_id', ''),
                'goal_id' => (string) $request->input('goal_id', ''),
                'reporting_period_type' => (string) $request->input('reporting_period_type', ''),
                'progress_status' => (string) $request->input('progress_status', ''),
                'review_status' => (string) $request->input('review_status', ''),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'recorded_on' => ['required', 'date'],
            'reporting_period_type' => ['required', 'in:daily,monthly,quarterly,yearly,special'],
            'reporting_year' => ['nullable', 'integer', 'min:1963', 'max:2100'],
            'reporting_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'reporting_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'aspiration_id' => ['nullable', 'exists:myb_au_aspirations,id'],
            'goal_id' => ['nullable', 'exists:myb_au_goals,id'],
            'indicator_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('myb_member_state_national_data', 'indicator_name')
                    ->where(function ($query) use ($user, $request) {
                        return $query
                            ->where('member_state_id', $user->member_state_id)
                            ->whereDate('recorded_on', $request->input('recorded_on'));
                    }),
            ],
            'indicator_value' => ['required', 'numeric'],
            'unit' => ['nullable', 'string', 'max:120'],
            'cooperation_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'progress_status' => ['required', 'in:not_started,in_progress,advanced,completed,stalled'],
            'people_reached' => ['nullable', 'integer', 'min:0'],
            'households_impacted' => ['nullable', 'integer', 'min:0'],
            'budget_allocated_usd' => ['nullable', 'numeric', 'min:0'],
            'budget_executed_usd' => ['nullable', 'numeric', 'min:0'],
            'agenda_relevance_summary' => ['nullable', 'string', 'max:8000'],
            'policy_actions' => ['nullable', 'string', 'max:20000'],
            'institutional_steps' => ['nullable', 'string', 'max:20000'],
            'livelihood_impact_summary' => ['nullable', 'string', 'max:20000'],
            'public_engagement_summary' => ['nullable', 'string', 'max:20000'],
            'awareness_outreach_channels' => ['nullable', 'string', 'max:12000'],
            'national_projects_programs' => ['nullable', 'string', 'max:20000'],
            'youth_women_inclusion_actions' => ['nullable', 'string', 'max:12000'],
            'partnerships_support' => ['nullable', 'string', 'max:12000'],
            'commodity_preservation_policies' => ['nullable', 'string', 'max:12000'],
            'commodity_value_addition' => ['nullable', 'string', 'max:12000'],
            'risk_challenges' => ['nullable', 'string', 'max:12000'],
            'next_steps_commitments' => ['nullable', 'string', 'max:12000'],
            'citizen_feedback_summary' => ['nullable', 'string', 'max:12000'],
            'evidence_links' => ['nullable', 'string', 'max:2000'],
            'agenda_awareness_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'flagship_awareness_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'outreach_coverage_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'flagship_projects_supported' => ['nullable', 'array', 'max:25'],
            'flagship_projects_supported.*' => ['uuid', 'exists:myb_au_flagship_projects,id'],
            'commodity_focus' => ['nullable', 'array', 'max:40'],
            'commodity_focus.*' => ['uuid', 'exists:myb_commodities,id'],
            'data_source' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        if (!empty($validated['goal_id']) && !empty($validated['aspiration_id'])) {
            $goalBelongsToAspiration = AuGoal::query()
                ->where('id', $validated['goal_id'])
                ->where('aspiration_id', $validated['aspiration_id'])
                ->exists();

            if (!$goalBelongsToAspiration) {
                return back()
                    ->withErrors(['goal_id' => 'Selected goal does not belong to the selected aspiration.'])
                    ->withInput();
            }
        }

        $recordedOn = \Carbon\Carbon::parse($validated['recorded_on']);
        $validated['reporting_year'] = $validated['reporting_year'] ?? (int) $recordedOn->year;
        $validated['reporting_month'] = $validated['reporting_month'] ?? (int) $recordedOn->month;

        if ($validated['reporting_period_type'] === 'daily') {
            $validated['reporting_day'] = $validated['reporting_day'] ?? (int) $recordedOn->day;
        } elseif ($validated['reporting_period_type'] !== 'special') {
            $validated['reporting_day'] = null;
        }

        $entry = MemberStateNationalData::create(array_merge($validated, [
            'member_state_id' => $user->member_state_id,
            'review_status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));

        $this->logNationalDataAudit(
            $request,
            'member_state_national_data_submitted',
            'Member state submitted national data for review.',
            [
                'entry_id' => $entry->id,
                'member_state_id' => $entry->member_state_id,
                'review_status' => $entry->review_status,
            ]
        );

        return back()->with('success', 'National data entry submitted successfully and is pending review.');
    }

    public function destroy(Request $request, MemberStateNationalData $entry)
    {
        abort_unless($entry->member_state_id === $request->user()->member_state_id, 403);

        if ($entry->review_status === 'approved') {
            return back()->with('error', 'Approved national data cannot be deleted.');
        }

        $payload = [
            'entry_id' => $entry->id,
            'member_state_id' => $entry->member_state_id,
            'review_status' => $entry->review_status,
        ];

        $entry->delete();

        $this->logNationalDataAudit(
            $request,
            'member_state_national_data_deleted',
            'Member state deleted a national data submission.',
            $payload
        );

        return back()->with('success', 'National data entry deleted.');
    }

    private function logNationalDataAudit(
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
            Log::warning('Failed to persist national data audit log entry.', [
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
