<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Jobs\NotifyGrmResponsibleOfficer;
use App\Jobs\SendGrmAutoResponse;
use App\Models\GrmEscalationRule;
use App\Models\GrmGrievance;
use App\Models\GrmGrievanceAttachment;
use App\Models\GrmGrievanceEvent;
use App\Models\GrmLevel;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class GrmController extends Controller
{
    use ScopesAssignedPortfolios;

    public function createSubmission(Request $request)
    {
        $programs = $this->submissionProgramsQuery()->get();
        $levels = $this->submissionLevelsQuery()->where('is_active', true)->get();

        return view('grm.submissions.create', [
            'programs' => $programs,
            'levels' => $levels,
            'channels' => $this->channels(),
        ]);
    }

    public function storeSubmission(Request $request)
    {
        $data = $request->validate([
            'program_id' => ['required', 'uuid', 'exists:myb_programs,id'],
            'level_id' => ['nullable', 'uuid', 'exists:grm_levels,id'],
            'submitter_name' => ['nullable', 'string', 'max:255'],
            'submitter_email' => ['nullable', 'email', 'max:255'],
            'submitter_phone' => ['nullable', 'string', 'max:60'],
            'channel' => ['required', Rule::in(array_keys($this->channels()))],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'is_anonymous' => ['nullable', 'boolean'],
            'supporting_documents' => ['nullable', 'array', 'max:10'],
            'supporting_documents.*.title' => ['nullable', 'string', 'max:255'],
            'supporting_documents.*.file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,zip', 'max:20480'],
        ]);

        $program = Program::with('sector')->findOrFail($data['program_id']);
        abort_unless($this->programIsAvailableForSubmission($program), 403);

        $level = filled($data['level_id'] ?? null)
            ? GrmLevel::query()->findOrFail($data['level_id'])
            : null;

        if ($level) {
            abort_unless($this->levelIsAvailableForSubmission($level, $program), 403);
        }

        $rule = $this->resolveEscalationRule($program, $level);
        $responseHours = (int) ($rule?->response_due_hours ?: $level?->response_due_hours ?: 48);
        $resolutionHours = (int) ($rule?->resolution_due_hours ?: $level?->resolution_due_hours ?: 168);
        $responsibleOfficer = $this->resolveResponsibleGrmOfficer($program);
        $isAnonymous = (bool) ($data['is_anonymous'] ?? false);
        $submitterName = $isAnonymous ? null : (filled($data['submitter_name'] ?? null)
            ? $data['submitter_name']
            : Auth::user()?->name);
        $submitterEmail = $isAnonymous ? null : (filled($data['submitter_email'] ?? null)
            ? $data['submitter_email']
            : Auth::user()?->email);
        $submitterPhone = $isAnonymous ? null : ($data['submitter_phone'] ?? null);

        $grievance = DB::transaction(function () use ($request, $data, $program, $level, $rule, $responseHours, $resolutionHours, $responsibleOfficer, $submitterName, $submitterEmail, $submitterPhone, $isAnonymous) {
            $now = now();

            $grievance = GrmGrievance::create([
                'case_number' => GrmGrievance::generateCaseNumber($program),
                'program_id' => $program->id,
                'governance_node_id' => $program->governance_node_id ?: $program->sector?->governance_node_id,
                'level_id' => $level?->id,
                'escalation_rule_id' => $rule?->id,
                'submitted_by' => $isAnonymous ? null : Auth::id(),
                'assigned_to' => $responsibleOfficer?->id,
                'submitter_name' => $submitterName,
                'submitter_email' => $submitterEmail,
                'submitter_phone' => $submitterPhone,
                'channel' => $data['channel'],
                'subject' => $data['subject'],
                'description' => $data['description'],
                'status' => 'submitted',
                'is_anonymous' => $isAnonymous,
                'submitted_at' => $now,
                'due_response_at' => $now->copy()->addHours($responseHours),
                'due_resolution_at' => $now->copy()->addHours($resolutionHours),
            ]);

            $this->recordEvent($grievance, 'submitted', 'Grievance submitted through the GRM workspace.', [
                'channel' => $data['channel'],
                'program_id' => $program->id,
                'level_id' => $level?->id,
            ], $isAnonymous);

            $attachmentCount = $this->storeSupportingDocuments($request, $grievance, $isAnonymous);

            if ($attachmentCount > 0) {
                $this->recordEvent(
                    $grievance,
                    'documents_uploaded',
                    $attachmentCount . ' supporting document' . ($attachmentCount === 1 ? '' : 's') . ' uploaded with the grievance.',
                    ['attachments_count' => $attachmentCount],
                    $isAnonymous
                );
            }

            if ($responsibleOfficer) {
                $this->recordEvent($grievance, 'assigned', 'Case assigned to the program GRM responsible officer.', [
                    'assigned_to' => $responsibleOfficer->id,
                    'assigned_to_email' => $responsibleOfficer->email,
                ], $isAnonymous);
            } else {
                $this->recordEvent($grievance, 'assignment_pending', 'No active GRM responsible officer was found for this program at submission time.', [
                    'program_id' => $program->id,
                ], $isAnonymous);
            }

            return $grievance;
        });

        $this->sendSubmissionNotifications($grievance);

        if (Auth::user()?->hasPermission('grm.view')) {
            return redirect()
                ->route('grm.logs.show', $grievance)
                ->with('success', 'Grievance submitted. Case number: ' . $grievance->case_number);
        }

        return redirect()
            ->route('grm.submissions.create')
            ->with('success', 'Grievance submitted. Case number: ' . $grievance->case_number);
    }

    public function levels()
    {
        return view('grm.configuration.levels', [
            'levels' => $this->visibleLevelsQuery()
                ->with(['program:id,name', 'governanceNode:id,name'])
                ->orderBy('program_id')
                ->orderBy('priority')
                ->get(),
            'programs' => $this->visibleProgramsQuery()->get(),
            'canCreateGlobal' => $this->userCanSeeAll(Auth::user()),
        ]);
    }

    public function storeLevel(Request $request)
    {
        $data = $request->validate([
            'program_id' => ['nullable', 'uuid', 'exists:myb_programs,id'],
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', 'integer', 'min:1', 'max:99'],
            'response_due_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'resolution_due_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $program = $this->resolveOptionalProgramForWrite($data['program_id'] ?? null);
        $slug = Str::slug($data['name']);

        GrmLevel::create([
            'program_id' => $program?->id,
            'governance_node_id' => $program?->governance_node_id ?: $program?->sector?->governance_node_id,
            'name' => $data['name'],
            'slug' => $slug,
            'color' => $data['color'] ?: '#0f766e',
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'response_due_hours' => $data['response_due_hours'] ?? null,
            'resolution_due_hours' => $data['resolution_due_hours'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('grm.levels.index')->with('success', 'Grievance level saved.');
    }

    public function updateLevel(Request $request, GrmLevel $level)
    {
        abort_unless($this->levelIsVisible($level), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', 'integer', 'min:1', 'max:99'],
            'response_due_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'resolution_due_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $level->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'] ?: $level->color,
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'response_due_hours' => $data['response_due_hours'] ?? null,
            'resolution_due_hours' => $data['resolution_due_hours'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('grm.levels.index')->with('success', 'Grievance level updated.');
    }

    public function escalations()
    {
        return view('grm.configuration.escalations', [
            'rules' => $this->visibleEscalationRulesQuery()
                ->with(['program:id,name', 'level:id,name,color', 'governanceNode:id,name'])
                ->latest()
                ->get(),
            'programs' => $this->visibleProgramsQuery()->get(),
            'levels' => $this->visibleLevelsQuery()->where('is_active', true)->orderBy('priority')->get(),
            'canCreateGlobal' => $this->userCanSeeAll(Auth::user()),
        ]);
    }

    public function storeEscalation(Request $request)
    {
        $data = $this->validateEscalation($request);
        $program = $this->resolveOptionalProgramForWrite($data['program_id'] ?? null);
        $level = $data['level_id'] ? GrmLevel::findOrFail($data['level_id']) : null;

        if ($level) {
            abort_unless(! $program || $this->levelIsVisibleForProgram($level, $program), 403);
            abort_unless($program || $this->levelIsVisible($level), 403);
        }

        GrmEscalationRule::create($this->escalationPayload($data, $program, $level));

        return redirect()->route('grm.escalations.index')->with('success', 'Escalation configuration saved.');
    }

    public function updateEscalation(Request $request, GrmEscalationRule $rule)
    {
        abort_unless($this->escalationRuleIsVisible($rule), 403);

        $data = $this->validateEscalation($request);
        $program = $this->resolveOptionalProgramForWrite($data['program_id'] ?? null);
        $level = $data['level_id'] ? GrmLevel::findOrFail($data['level_id']) : null;

        if ($level) {
            abort_unless(! $program || $this->levelIsVisibleForProgram($level, $program), 403);
            abort_unless($program || $this->levelIsVisible($level), 403);
        }

        $rule->update($this->escalationPayload($data, $program, $level));

        return redirect()->route('grm.escalations.index')->with('success', 'Escalation configuration updated.');
    }

    public function logs(Request $request)
    {
        $query = $this->visibleGrievancesQuery()
            ->with(['program:id,name,sector_id,governance_node_id', 'program.sector:id,name', 'level:id,name,color', 'submitter:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->string('level_id')->toString());
        }

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->string('program_id')->toString());
        }

        if ($request->filled('q')) {
            $search = '%' . $request->string('q')->trim()->toString() . '%';
            $query->where(function ($scope) use ($search) {
                $scope->where('case_number', 'like', $search)
                    ->orWhere('subject', 'like', $search)
                    ->orWhere('submitter_name', 'like', $search)
                    ->orWhere('submitter_email', 'like', $search);
            });
        }

        $baseStats = $this->visibleGrievancesQuery();

        return view('grm.logs.index', [
            'grievances' => $query->latest('submitted_at')->paginate(15)->withQueryString(),
            'programs' => $this->visibleProgramsQuery()->get(),
            'levels' => $this->visibleLevelsQuery()->where('is_active', true)->orderBy('priority')->get(),
            'statuses' => GrmGrievance::STATUSES,
            'stats' => [
                'total' => (clone $baseStats)->count(),
                'unattended' => (clone $baseStats)->whereIn('status', ['submitted', 'acknowledged', 'under_review', 'escalated'])->count(),
                'responded' => (clone $baseStats)->whereNotNull('responded_at')->count(),
                'resolved' => (clone $baseStats)->whereIn('status', ['resolved', 'closed'])->count(),
            ],
        ]);
    }

    public function show(GrmGrievance $grievance)
    {
        abort_unless($this->grievanceIsVisible($grievance), 403);

        $grievance->load([
            'program:id,name,sector_id,governance_node_id',
            'program.sector:id,name',
            'governanceNode:id,name',
            'level:id,name,color,response_due_hours,resolution_due_hours',
            'escalationRule',
            'submitter:id,name,email',
            'assignee:id,name,email',
            'attachments.uploader:id,name,email',
            'events.user:id,name,email',
        ]);

        return view('grm.logs.show', [
            'grievance' => $grievance,
            'statuses' => GrmGrievance::STATUSES,
            'assignees' => $this->caseAssignees(),
        ]);
    }

    public function downloadAttachment(Request $request, GrmGrievance $grievance, GrmGrievanceAttachment $attachment)
    {
        abort_unless($this->grievanceIsVisible($grievance), 403);
        abort_unless((string) $attachment->grievance_id === (string) $grievance->id, 404);

        $path = (string) ($attachment->file_path ?? '');
        abort_if($path === '', 404, 'Attachment not found.');

        $privateDisk = Storage::disk('local');

        if (! $privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            $stream = Storage::disk('public')->readStream($path);

            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'Attachment file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('download')) {
            return $privateDisk->download($path, $attachment->file_name ?? basename($path), $headers);
        }

        return $privateDisk->response($path, null, $headers);
    }

    public function updateStatus(Request $request, GrmGrievance $grievance)
    {
        abort_unless($this->grievanceIsVisible($grievance), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(GrmGrievance::STATUSES))],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updates = [
            'status' => $data['status'],
            'assigned_to' => $data['assigned_to'] ?? null,
        ];

        if ($data['status'] === 'acknowledged' && ! $grievance->acknowledged_at) {
            $updates['acknowledged_at'] = now();
        }

        if ($data['status'] === 'responded' && ! $grievance->responded_at) {
            $updates['responded_at'] = now();
        }

        if (in_array($data['status'], ['resolved', 'closed'], true) && ! $grievance->resolved_at) {
            $updates['resolved_at'] = now();
        }

        if ($data['status'] === 'escalated' && ! $grievance->last_escalated_at) {
            $updates['last_escalated_at'] = now();
        }

        $grievance->update($updates);
        $this->recordEvent($grievance, 'status_updated', $data['notes'] ?: 'Case status updated to ' . GrmGrievance::STATUSES[$data['status']] . '.', [
            'status' => $data['status'],
            'assigned_to' => $data['assigned_to'] ?? null,
        ]);

        return redirect()->route('grm.logs.show', $grievance)->with('success', 'Case updated.');
    }

    public function reports(Request $request)
    {
        $request->validate([
            'program_id' => ['nullable', 'uuid', 'exists:myb_programs,id'],
            'level_id' => ['nullable', 'uuid', 'exists:grm_levels,id'],
            'status' => ['nullable', Rule::in(array_keys(GrmGrievance::STATUSES))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = $this->visibleGrievancesQuery()
            ->with(['program:id,name', 'level:id,name,color', 'assignee:id,name'])
            ->withCount('attachments');

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->string('program_id')->toString());
        }

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->string('level_id')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('date_from')) {
            $query->whereDate('submitted_at', '>=', $request->date('date_from')->toDateString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('submitted_at', '<=', $request->date('date_to')->toDateString());
        }

        $cases = $query->get();
        $total = $cases->count();
        $responded = $cases->filter(fn (GrmGrievance $case) => $case->responded_at || in_array($case->status, ['responded', 'resolved', 'closed'], true))->count();
        $unattended = $cases->filter(fn (GrmGrievance $case) => $case->is_unattended)->count();
        $resolved = $cases->whereIn('status', ['resolved', 'closed'])->count();
        $anonymous = $cases->where('is_anonymous', true)->count();
        $withDocuments = $cases->filter(fn (GrmGrievance $case) => (int) ($case->attachments_count ?? 0) > 0)->count();
        $overdueResponse = $cases->filter(fn (GrmGrievance $case) => $case->is_unattended && $case->due_response_at?->isPast())->count();
        $overdueResolution = $cases->filter(fn (GrmGrievance $case) => ! in_array($case->status, ['resolved', 'closed'], true) && $case->due_resolution_at?->isPast())->count();
        $lateResponses = $cases->filter(fn (GrmGrievance $case) => $case->responded_at && $case->due_response_at && $case->responded_at->gt($case->due_response_at))->count();
        $onTimeResponses = $cases->filter(fn (GrmGrievance $case) => $case->responded_at && $case->due_response_at && $case->responded_at->lte($case->due_response_at))->count();
        $responseRate = $total > 0 ? round(($responded / $total) * 100, 1) : 0;
        $resolutionRate = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;
        $documentRate = $total > 0 ? round(($withDocuments / $total) * 100, 1) : 0;
        $anonymousRate = $total > 0 ? round(($anonymous / $total) * 100, 1) : 0;
        $avgResponseHours = round((float) $cases
            ->filter(fn (GrmGrievance $case) => $case->submitted_at && $case->responded_at)
            ->avg(fn (GrmGrievance $case) => $case->submitted_at->diffInHours($case->responded_at)), 1);
        $avgResolutionHours = round((float) $cases
            ->filter(fn (GrmGrievance $case) => $case->submitted_at && $case->resolved_at)
            ->avg(fn (GrmGrievance $case) => $case->submitted_at->diffInHours($case->resolved_at)), 1);

        $trendMonths = collect(range(5, 0))->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset));
        $trendSeries = [
            'labels' => $trendMonths->map(fn ($month) => $month->format('M Y'))->values(),
            'submitted' => $trendMonths->map(fn ($month) => $cases->filter(fn (GrmGrievance $case) => $case->submitted_at?->isSameMonth($month))->count())->values(),
            'resolved' => $trendMonths->map(fn ($month) => $cases->filter(fn (GrmGrievance $case) => $case->resolved_at?->isSameMonth($month))->count())->values(),
        ];

        $statusSeries = $cases
            ->groupBy('status')
            ->map(fn ($items, $status) => [
                'label' => GrmGrievance::STATUSES[$status] ?? Str::headline((string) $status),
                'count' => $items->count(),
            ])
            ->values();

        $levelSeries = $cases
            ->groupBy(fn (GrmGrievance $case) => $case->level?->name ?: 'Unclassified')
            ->map(fn ($items, $level) => [
                'label' => $level,
                'count' => $items->count(),
                'color' => $items->first()?->level?->color ?: '#0f766e',
            ])
            ->values();

        $programSeries = $cases
            ->groupBy(fn (GrmGrievance $case) => $case->program?->name ?: 'No Program')
            ->map(fn ($items, $program) => [
                'label' => $program,
                'count' => $items->count(),
                'unattended' => $items->filter(fn (GrmGrievance $case) => $case->is_unattended)->count(),
            ])
            ->sortByDesc('count')
            ->take(8)
            ->values();

        $assigneeSeries = $cases
            ->groupBy(fn (GrmGrievance $case) => $case->assignee?->name ?: 'Unassigned')
            ->map(fn ($items, $assignee) => [
                'label' => $assignee,
                'count' => $items->count(),
                'open' => $items->filter(fn (GrmGrievance $case) => $case->is_unattended)->count(),
            ])
            ->sortByDesc('count')
            ->take(6)
            ->values();

        $attentionCases = $cases
            ->filter(fn (GrmGrievance $case) => $case->is_unattended || $case->due_response_at?->isPast() || $case->due_resolution_at?->isPast())
            ->sortBy(fn (GrmGrievance $case) => $case->due_response_at?->timestamp ?? PHP_INT_MAX)
            ->take(8)
            ->values();

        $chartData = [
            'responseHealth' => [
                'labels' => ['On-time', 'Late', 'Unanswered'],
                'values' => [$onTimeResponses, $lateResponses, max(0, $total - $responded)],
            ],
            'status' => [
                'labels' => $statusSeries->pluck('label'),
                'values' => $statusSeries->pluck('count'),
            ],
            'trend' => $trendSeries,
            'level' => [
                'labels' => $levelSeries->pluck('label'),
                'values' => $levelSeries->pluck('count'),
                'colors' => $levelSeries->pluck('color'),
            ],
            'programLoad' => [
                'labels' => $programSeries->pluck('label'),
                'cases' => $programSeries->pluck('count'),
                'unattended' => $programSeries->pluck('unattended'),
            ],
            'assignees' => [
                'labels' => $assigneeSeries->pluck('label'),
                'cases' => $assigneeSeries->pluck('count'),
                'open' => $assigneeSeries->pluck('open'),
            ],
        ];

        return view('grm.reports.index', [
            'programs' => $this->visibleProgramsQuery()->get(),
            'levels' => $this->visibleLevelsQuery()->where('is_active', true)->orderBy('priority')->get(),
            'selectedProgram' => $request->string('program_id')->toString(),
            'selectedLevel' => $request->string('level_id')->toString(),
            'selectedStatus' => $request->string('status')->toString(),
            'dateFrom' => $request->string('date_from')->toString(),
            'dateTo' => $request->string('date_to')->toString(),
            'statuses' => GrmGrievance::STATUSES,
            'totals' => [
                'total' => $total,
                'responded' => $responded,
                'unattended' => $unattended,
                'resolved' => $resolved,
                'anonymous' => $anonymous,
                'with_documents' => $withDocuments,
                'overdue_response' => $overdueResponse,
                'overdue_resolution' => $overdueResolution,
                'late_responses' => $lateResponses,
                'on_time_responses' => $onTimeResponses,
            ],
            'rates' => [
                'response' => $responseRate,
                'resolution' => $resolutionRate,
                'documents' => $documentRate,
                'anonymous' => $anonymousRate,
            ],
            'averages' => [
                'response_hours' => $avgResponseHours,
                'resolution_hours' => $avgResolutionHours,
            ],
            'responseSeries' => [
                'Responded' => $responded,
                'Unattended' => $unattended,
            ],
            'statusSeries' => $statusSeries,
            'levelSeries' => $levelSeries,
            'programSeries' => $programSeries,
            'assigneeSeries' => $assigneeSeries,
            'attentionCases' => $attentionCases,
            'chartData' => $chartData,
        ]);
    }

    private function validateEscalation(Request $request): array
    {
        return $request->validate([
            'program_id' => ['nullable', 'uuid', 'exists:myb_programs,id'],
            'level_id' => ['nullable', 'uuid', 'exists:grm_levels,id'],
            'response_due_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'resolution_due_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'reminder_after_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'reminder_interval_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'escalate_after_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'escalation_email' => ['nullable', 'email', 'max:255'],
            'auto_response_subject' => ['nullable', 'string', 'max:255'],
            'auto_response_body' => ['nullable', 'string', 'max:4000'],
            'reminder_subject' => ['nullable', 'string', 'max:255'],
            'reminder_body' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function escalationPayload(array $data, ?Program $program, ?GrmLevel $level): array
    {
        return [
            'program_id' => $program?->id,
            'governance_node_id' => $program?->governance_node_id ?: $program?->sector?->governance_node_id,
            'level_id' => $level?->id,
            'response_due_hours' => $data['response_due_hours'],
            'resolution_due_hours' => $data['resolution_due_hours'],
            'reminder_after_hours' => $data['reminder_after_hours'],
            'reminder_interval_hours' => $data['reminder_interval_hours'],
            'escalate_after_hours' => $data['escalate_after_hours'],
            'escalation_email' => $data['escalation_email'] ?? null,
            'auto_response_subject' => $data['auto_response_subject'] ?? null,
            'auto_response_body' => $data['auto_response_body'] ?? null,
            'reminder_subject' => $data['reminder_subject'] ?? null,
            'reminder_body' => $data['reminder_body'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'created_by' => Auth::id(),
        ];
    }

    private function resolveEscalationRule(Program $program, ?GrmLevel $level): ?GrmEscalationRule
    {
        return GrmEscalationRule::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($program) {
                $query->whereNull('program_id')->orWhere('program_id', $program->id);
            })
            ->where(function (Builder $query) use ($level) {
                $query->whereNull('level_id');

                if ($level) {
                    $query->orWhere('level_id', $level->id);
                }
            })
            ->get()
            ->sortBy(function (GrmEscalationRule $rule) use ($program, $level) {
                return ($rule->program_id === $program->id ? 0 : 10)
                    + ($level && $rule->level_id === $level->id ? 0 : 1);
            })
            ->first();
    }

    private function submissionProgramsQuery(?User $user = null): Builder
    {
        $user ??= Auth::user();
        $query = Program::query()
            ->with(['sector:id,name,governance_node_id', 'governanceNode:id,name'])
            ->orderBy('name');

        if (! $user || $this->userCanSeeAll($user)) {
            return $query;
        }

        if ($user->isFundingPartner() && ($funder = $user->partnerFunder())) {
            return $query->whereHas('approvedFundings', function (Builder $fundingQuery) use ($funder) {
                $fundingQuery->where('funder_id', $funder->id);
            });
        }

        if ($user->user_type === 'ttl') {
            return $query->where('ttl_user_id', $user->id);
        }

        if ($user->user_type === 'vendor') {
            $programIds = $user->vendorSubActivityAssignments()
                ->whereNotNull('program_id')
                ->pluck('program_id')
                ->filter()
                ->unique()
                ->values();

            return $programIds->isNotEmpty()
                ? $query->whereIn('id', $programIds->all())
                : $query;
        }

        if ($this->userHasAssignedPortfolioScope($user)) {
            $this->applyAssignedPortfolioScopeToPrograms($query, $user);
            return $query;
        }

        if ($user->governance_node_id) {
            $nodeId = $user->governance_node_id;
            return $query->where(function (Builder $scope) use ($nodeId) {
                $scope->where('governance_node_id', $nodeId)
                    ->orWhereHas('sector', fn (Builder $sectorQuery) => $sectorQuery->where('governance_node_id', $nodeId));
            });
        }

        return $query;
    }

    private function submissionLevelsQuery(?User $user = null): Builder
    {
        $user ??= Auth::user();
        $query = GrmLevel::query();
        $programIds = $this->submissionProgramsQuery($user)->pluck('id')->all();
        $nodeId = $user?->governance_node_id;

        return $query->where(function (Builder $scope) use ($programIds, $nodeId) {
            $scope->whereNull('program_id');

            if (! empty($programIds)) {
                $scope->orWhereIn('program_id', $programIds);
            }

            if ($nodeId) {
                $scope->orWhere('governance_node_id', $nodeId);
            }
        });
    }

    private function programIsAvailableForSubmission(Program $program, ?User $user = null): bool
    {
        return $this->submissionProgramsQuery($user)
            ->whereKey($program->id)
            ->exists();
    }

    private function levelIsAvailableForSubmission(GrmLevel $level, Program $program, ?User $user = null): bool
    {
        return (! $level->program_id || (string) $level->program_id === (string) $program->id)
            && $this->submissionLevelsQuery($user)
                ->whereKey($level->id)
                ->exists();
    }

    private function visibleProgramsQuery(?User $user = null): Builder
    {
        $user ??= Auth::user();
        $query = Program::query()
            ->with(['sector:id,name,governance_node_id', 'governanceNode:id,name'])
            ->orderBy('name');

        if ($this->userCanSeeAll($user)) {
            return $query;
        }

        if ($this->userHasAssignedPortfolioScope($user)) {
            $this->applyAssignedPortfolioScopeToPrograms($query, $user);
            return $query;
        }

        if ($user?->governance_node_id) {
            $nodeId = $user->governance_node_id;
            $query->where(function (Builder $scope) use ($nodeId) {
                $scope->where('governance_node_id', $nodeId)
                    ->orWhereHas('sector', fn (Builder $sectorQuery) => $sectorQuery->where('governance_node_id', $nodeId));
            });

            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    private function visibleLevelsQuery(?User $user = null): Builder
    {
        $user ??= Auth::user();
        $query = GrmLevel::query();

        if ($this->userCanSeeAll($user)) {
            return $query;
        }

        $programIds = $this->visibleProgramsQuery($user)->pluck('id')->all();
        $nodeId = $user?->governance_node_id;

        return $query->where(function (Builder $scope) use ($programIds, $nodeId) {
            $scope->whereNull('program_id');

            if (! empty($programIds)) {
                $scope->orWhereIn('program_id', $programIds);
            }

            if ($nodeId) {
                $scope->orWhere('governance_node_id', $nodeId);
            }
        });
    }

    private function visibleEscalationRulesQuery(?User $user = null): Builder
    {
        $user ??= Auth::user();
        $query = GrmEscalationRule::query();

        if ($this->userCanSeeAll($user)) {
            return $query;
        }

        $programIds = $this->visibleProgramsQuery($user)->pluck('id')->all();
        $nodeId = $user?->governance_node_id;

        return $query->where(function (Builder $scope) use ($programIds, $nodeId) {
            $scope->whereNull('program_id');

            if (! empty($programIds)) {
                $scope->orWhereIn('program_id', $programIds);
            }

            if ($nodeId) {
                $scope->orWhere('governance_node_id', $nodeId);
            }
        });
    }

    private function visibleGrievancesQuery(?User $user = null): Builder
    {
        $user ??= Auth::user();
        $query = GrmGrievance::query();

        if ($this->userCanSeeAll($user)) {
            return $query;
        }

        if ($this->userHasAssignedPortfolioScope($user)) {
            return $query->whereHas('program', function (Builder $programQuery) use ($user) {
                $this->applyAssignedPortfolioScopeToPrograms($programQuery, $user);
            });
        }

        if ($user?->governance_node_id) {
            $nodeId = $user->governance_node_id;
            return $query->where(function (Builder $scope) use ($nodeId) {
                $scope->where('governance_node_id', $nodeId)
                    ->orWhereHas('program', function (Builder $programQuery) use ($nodeId) {
                        $programQuery->where('governance_node_id', $nodeId)
                            ->orWhereHas('sector', fn (Builder $sectorQuery) => $sectorQuery->where('governance_node_id', $nodeId));
                    });
            });
        }

        return $query->where('submitted_by', $user?->id);
    }

    private function programIsVisible(Program $program, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if ($this->userCanSeeAll($user)) {
            return true;
        }

        if ($this->userHasAssignedPortfolioScope($user)) {
            return $this->programIsInAssignedPortfolio($program, $user);
        }

        if ($user?->governance_node_id) {
            $program->loadMissing('sector:id,governance_node_id');

            return (string) $program->governance_node_id === (string) $user->governance_node_id
                || (string) $program->sector?->governance_node_id === (string) $user->governance_node_id;
        }

        return false;
    }

    private function levelIsVisible(GrmLevel $level, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if ($this->userCanSeeAll($user) || ! $level->program_id) {
            return true;
        }

        $level->loadMissing('program.sector:id,governance_node_id');

        return $level->program && $this->programIsVisible($level->program, $user);
    }

    private function levelIsVisibleForProgram(GrmLevel $level, Program $program): bool
    {
        if (! $this->levelIsVisible($level)) {
            return false;
        }

        return ! $level->program_id || (string) $level->program_id === (string) $program->id;
    }

    private function escalationRuleIsVisible(GrmEscalationRule $rule, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if ($this->userCanSeeAll($user) || ! $rule->program_id) {
            return true;
        }

        $rule->loadMissing('program.sector:id,governance_node_id');

        return $rule->program && $this->programIsVisible($rule->program, $user);
    }

    private function grievanceIsVisible(GrmGrievance $grievance, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if ($this->userCanSeeAll($user)) {
            return true;
        }

        if ($grievance->submitted_by && (string) $grievance->submitted_by === (string) $user?->id) {
            return true;
        }

        $grievance->loadMissing('program.sector:id,governance_node_id');

        return $grievance->program && $this->programIsVisible($grievance->program, $user);
    }

    private function resolveOptionalProgramForWrite(?string $programId): ?Program
    {
        if (! $programId) {
            abort_unless($this->userCanSeeAll(Auth::user()), 403, 'Only system administrators can create global GRM configuration.');
            return null;
        }

        $program = Program::with('sector')->findOrFail($programId);
        abort_unless($this->programIsVisible($program), 403);

        return $program;
    }

    private function userCanSeeAll(?User $user): bool
    {
        return (bool) ($user && ($user->isSuperAdmin() || $user->isAdmin()));
    }

    private function channels(): array
    {
        return [
            'portal' => 'Portal',
            'email' => 'Email',
            'phone' => 'Phone',
            'walk_in' => 'Walk-in',
            'field_visit' => 'Field Visit',
            'other' => 'Other',
        ];
    }

    private function resolveResponsibleGrmOfficer(Program $program): ?User
    {
        $program->loadMissing([
            'sector:id,governance_node_id,portfolio_manager_user_id',
            'sector.portfolioManager',
            'sector.portfolioManager.permissions',
            'sector.portfolioManager.role.permissions',
            'ttlUser',
            'ttlUser.permissions',
            'ttlUser.role.permissions',
        ]);

        $programScopedOfficer = collect([
            $program->sector?->portfolioManager,
            $program->ttlUser,
        ])
            ->filter()
            ->first(fn (User $user) => $this->canReceiveGrmEmail($user)
                && (
                    $user->hasPermission('grm.view')
                    || $user->hasPermission('grm.configure')
                    || $user->hasPermission('grm.escalations')
                ));

        if ($programScopedOfficer) {
            return $programScopedOfficer;
        }

        $nodeIds = collect([
            $program->governance_node_id,
            $program->sector?->governance_node_id,
        ])
            ->filter()
            ->unique()
            ->values();

        if ($nodeIds->isNotEmpty()) {
            $scopedRoleOfficer = $this->grmOfficerQuery()
                ->whereIn('governance_node_id', $nodeIds->all())
                ->whereHas('role', function (Builder $roleQuery) {
                    $roleQuery->where('name', 'like', '%GRM%')
                        ->orWhere('name', 'like', '%Grievance%');
                })
                ->first();

            if ($scopedRoleOfficer) {
                return $scopedRoleOfficer;
            }

            $scopedOfficer = $this->grmOfficerQuery()
                ->whereIn('governance_node_id', $nodeIds->all())
                ->first();

            if ($scopedOfficer) {
                return $scopedOfficer;
            }
        }

        return $this->grmOfficerQuery()
            ->whereHas('role', function (Builder $roleQuery) {
                $roleQuery->where('name', 'like', '%GRM%')
                    ->orWhere('name', 'like', '%Grievance%');
            })
            ->first()
            ?: $this->grmOfficerQuery()->first();
    }

    private function grmOfficerQuery(): Builder
    {
        return User::query()
            ->with('role:id,name')
            ->whereNotNull('email')
            ->where(function (Builder $query) {
                $query->whereNull('is_disabled')
                    ->orWhere('is_disabled', false);
            })
            ->where(function (Builder $query) {
                $query->whereNull('is_blacklisted')
                    ->orWhere('is_blacklisted', false);
            })
            ->where(function (Builder $query) {
                $query->whereHas('permissions', function (Builder $permission) {
                    $permission->whereIn('name', ['grm.view', 'grm.configure', 'grm.escalations']);
                })
                    ->orWhereHas('role.permissions', function (Builder $permission) {
                        $permission->whereIn('name', ['grm.view', 'grm.configure', 'grm.escalations']);
                    })
                    ->orWhereHas('role', function (Builder $role) {
                        $role->whereIn('name', ['System Admin', 'Super Admin'])
                            ->orWhere('name', 'like', '%GRM%')
                            ->orWhere('name', 'like', '%Grievance%');
                    });
            })
            ->orderBy('name');
    }

    private function canReceiveGrmEmail(User $user): bool
    {
        return filled($user->email)
            && ! (bool) $user->is_disabled
            && ! (bool) $user->is_blacklisted;
    }

    private function storeSupportingDocuments(Request $request, GrmGrievance $grievance, bool $isAnonymous): int
    {
        $documentInputs = $request->input('supporting_documents', []);
        $documentFiles = $request->file('supporting_documents', []);
        $uploaded = 0;

        foreach ($documentFiles as $index => $documentRow) {
            $file = is_array($documentRow) ? ($documentRow['file'] ?? null) : null;

            if (! $file || ! $file->isValid()) {
                continue;
            }

            $title = trim((string) data_get($documentInputs, "{$index}.title", ''));
            $path = $file->store("grm/grievances/{$grievance->id}/attachments", 'local');

            $grievance->attachments()->create([
                'uploaded_by' => $isAnonymous ? null : Auth::id(),
                'title' => $title !== '' ? $title : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size_bytes' => $file->getSize(),
            ]);

            $uploaded++;
        }

        return $uploaded;
    }

    private function sendSubmissionNotifications(GrmGrievance $grievance): void
    {
        if (filled($grievance->submitter_email)) {
            try {
                SendGrmAutoResponse::dispatch($grievance->id);
            } catch (Throwable $exception) {
                Log::warning('GRM submitter acknowledgement failed during submission.', [
                    'grievance_id' => $grievance->id,
                    'email' => $grievance->submitter_email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        try {
            NotifyGrmResponsibleOfficer::dispatch($grievance->id);
        } catch (Throwable $exception) {
            Log::warning('GRM responsible officer notification failed during submission.', [
                'grievance_id' => $grievance->id,
                'assigned_to' => $grievance->assigned_to,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function caseAssignees()
    {
        return $this->grmOfficerQuery()
            ->get(['id', 'name', 'email', 'role_id']);
    }

    private function recordEvent(GrmGrievance $grievance, string $type, ?string $notes = null, array $metadata = [], bool $hideActor = false): void
    {
        GrmGrievanceEvent::create([
            'grievance_id' => $grievance->id,
            'user_id' => $hideActor ? null : Auth::id(),
            'event_type' => $type,
            'notes' => $notes,
            'metadata' => $metadata ?: null,
        ]);
    }
}
