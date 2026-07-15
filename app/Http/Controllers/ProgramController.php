<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Jobs\NotifyProgramTtlAssigned;
use App\Models\Sector;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\GovernanceNode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProgramController extends Controller
{
    use ScopesAssignedPortfolios;

    /**
     * PROGRAM RBAC
     * Matches routes:
     * permission:budget.structure.manage
     */
     public function __construct()
    {
        $this->middleware(['auth', 'verified']);

        $this->middleware('permission:program.view')
            ->only(['index', 'show']);

        $this->middleware('permission:program.create')
            ->only(['create', 'store']);

        $this->middleware('permission:program.edit')
            ->only(['edit', 'update']);

        $this->middleware('permission:program.delete')
            ->only(['destroy']);
    }


    /**
     * List all programs
     */
    public function index()
    {
        $currentUser = Auth::user();
        $isPortfolioLeader = $this->userHasAssignedPortfolioScope($currentUser);
        $scopedNodeIds = $this->scopedNodeIds();
        if (! $isPortfolioLeader && $scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to programs.');
        }

        $programs = Program::with([
            'sector',
            'governanceNode.level',
            'ttlUser:id,name,email',
            'projects.activities.subActivities',
        ])
            ->when($isPortfolioLeader, function ($query) use ($currentUser) {
                $this->applyAssignedPortfolioScopeToPrograms($query, $currentUser);
            })
            ->when(! $isPortfolioLeader && $scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->latest()
            ->get()
            ->each(function (Program $program) {
                $projects = $program->projects;
                $activities = $projects->flatMap->activities;

                $program->setAttribute('projects_count', $projects->count());
                $program->setAttribute('activities_count', $activities->count());
                $program->setAttribute('sub_activities_count', $activities->flatMap->subActivities->count());
                $program->setAttribute('project_budget_value', $projects->sum(fn ($project) => (float) ($project->total_budget ?? 0)));
                $program->setAttribute('budget_utilization_percent', (float) ($program->total_budget ?? 0) > 0
                    ? min(100, round(($program->project_budget_value / (float) $program->total_budget) * 100))
                    : 0);
                $program->setAttribute('latest_structure_update_at', collect([
                    $program->updated_at,
                    $projects->max('updated_at'),
                    $activities->max('updated_at'),
                ])->filter()->sortByDesc(fn ($date) => $date->getTimestamp())->first());
            });

        $programStats = [
            'total' => $programs->count(),
            'portfolios' => $programs->pluck('sector_id')->filter()->unique()->count(),
            'projects' => $programs->sum('projects_count'),
            'activities' => $programs->sum('activities_count'),
            'sub_activities' => $programs->sum('sub_activities_count'),
            'total_budget' => $programs->sum(fn ($program) => (float) ($program->total_budget ?? 0)),
            'project_budget' => $programs->sum('project_budget_value'),
            'governance_assigned' => $programs->filter(fn ($program) => filled($program->governance_node_id))->count(),
        ];

        $topPrograms = $programs
            ->sortByDesc(fn ($program) => (float) ($program->total_budget ?? 0))
            ->take(5)
            ->values();

        return view('budget.programs.index', compact('programs', 'programStats', 'topPrograms'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $sectors = $this->availableSectors();
        $approvedPrograms = $this->approvedProgramNames();
        $approvedProgramFunding = $this->approvedProgramFundingMap();

        return view('budget.programs.create', compact(
            'sectors',
            'approvedPrograms',
            'approvedProgramFunding'
        ));
    }

    /**
     * Store Program
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sector_id'   => 'required|exists:myb_sectors,id',
            'program_id'  => 'required|string|max:50|unique:myb_programs,program_id',
            'program_name' => 'required|string|max:255',
            'ttl_name' => 'required|string|max:255',
            'ttl_email' => 'required|email|max:255',
            'expected_outcome_type' => 'required|in:percentage,text',
            'expected_outcome_percentage' => 'nullable|numeric|min:0|max:100',
            'expected_outcome_text' => 'nullable|string|max:2000',
            'currency'    => 'required|string|max:10',
            'start_year'  => 'required|integer|min:1900|max:2100',
            'end_year'    => 'required|integer|min:1900|max:2100|gte:start_year',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $this->assertSectorInScope($validated['sector_id']);
            $sector = Sector::findOrFail($validated['sector_id']);
            $this->assertProgramNameAllowed($validated['program_name']);

            $fundingMap = $this->approvedProgramFundingMap();
            $funding = $fundingMap->get($validated['program_name']);
            if (!$funding || $funding['total_budget'] === null) {
                throw new \Exception('Approved funding amount not found for the selected program.');
            }

            $validated['currency'] = $funding['currency'];
            $validated['start_year'] = $funding['start_year'];
            $validated['end_year'] = $funding['end_year'];
            $validated['total_budget'] = $funding['total_budget'];

            $validated['total_years'] =
                ($validated['end_year'] - $validated['start_year']) + 1;

            $validated['created_by'] = auth()->id();
            $validated['governance_node_id'] = $sector->governance_node_id ?: Auth::user()?->governance_node_id;
            $ttlAssignment = $this->persistProgramTtl($validated);

            $expectedOutcomeValue = $validated['expected_outcome_type'] === 'percentage'
                ? (string) ($validated['expected_outcome_percentage'] ?? '')
                : ($validated['expected_outcome_text'] ?? '');

            if ($validated['expected_outcome_type'] === 'percentage' && $expectedOutcomeValue === '') {
                throw new \Exception('Expected outcome percentage is required.');
            }

            if ($validated['expected_outcome_type'] === 'text' && $expectedOutcomeValue === '') {
                throw new \Exception('Expected outcome description is required.');
            }

            $program = Program::create([
                'program_id' => $validated['program_id'],
                'sector_id' => $validated['sector_id'],
                'name' => $validated['program_name'],
                'currency' => $validated['currency'],
                'start_year' => $validated['start_year'],
                'end_year' => $validated['end_year'],
                'total_budget' => $validated['total_budget'],
                'description' => $validated['description'] ?? null,
                'expected_outcome_type' => $validated['expected_outcome_type'],
                'expected_outcome_value' => $expectedOutcomeValue,
                'total_years' => $validated['total_years'],
                'created_by' => $validated['created_by'],
                'governance_node_id' => $validated['governance_node_id'],
                'ttl_user_id' => $ttlAssignment['user']->id,
                'ttl_name' => $validated['ttl_name'],
                'ttl_email' => Str::lower($validated['ttl_email']),
            ]);

            DB::commit();

            $mailSent = $this->sendProgramTtlMailSafely(
                $program,
                $ttlAssignment['user'],
                $ttlAssignment['plain_password']
            );

            return redirect()
                ->route('budget.programs.index')
                ->with('success', $mailSent
                    ? 'Program created successfully. TTL access email has been sent.'
                    : 'Program created successfully, but the TTL access email could not be sent. Check the Laravel log or update the TTL assignment.');

        } catch (Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Failed to create program: ' . $e->getMessage());
        }
    }

    /**
     * Show a single program
     */
    public function show(Program $program)
    {
        $this->assertProgramInScope($program);
        $program->load([
            'sector',
            'governanceNode.level',
            'ttlUser:id,name,email',
            'projects.activities.subActivities',
            'indicators.level',
            'indicators.frequency',
            'indicators.unit',
        ]);

        $projects = $program->projects;
        $activities = $projects->flatMap->activities;
        $subActivities = $activities->flatMap->subActivities;
        $projectBudget = $projects->sum(fn ($project) => (float) ($project->total_budget ?? 0));
        $programBudget = (float) ($program->total_budget ?? 0);

        $programStats = [
            'projects' => $projects->count(),
            'activities' => $activities->count(),
            'sub_activities' => $subActivities->count(),
            'indicators' => $program->indicators->count(),
            'program_budget' => $programBudget,
            'project_budget' => $projectBudget,
            'remaining_budget' => max($programBudget - $projectBudget, 0),
            'budget_utilization_percent' => $programBudget > 0 ? min(100, round(($projectBudget / $programBudget) * 100)) : 0,
        ];

        $projects->each(function ($project) {
            $activities = $project->activities;
            $project->setAttribute('activities_count', $activities->count());
            $project->setAttribute('sub_activities_count', $activities->flatMap->subActivities->count());
            $project->setAttribute('duration_years_display', $project->total_years ?: (($project->start_year && $project->end_year) ? (($project->end_year - $project->start_year) + 1) : null));
        });

        return view('budget.programs.show', compact('program', 'programStats'));
    }

    /**
     * Edit program
     */
    public function edit(Program $program)
    {
        $this->assertProgramInScope($program);
        $sectors = $this->availableSectors();
        $nodes = $this->availableNodes();
        $approvedPrograms = $this->approvedProgramNames($program->name);
        $approvedProgramFunding = $this->approvedProgramFundingMap($program->name, $program);

        return view('budget.programs.edit', compact(
            'program',
            'sectors',
            'nodes',
            'approvedPrograms',
            'approvedProgramFunding'
        ));
    }

    /**
     * Update program
     */
    public function update(Request $request, Program $program)
    {
        $this->assertProgramInScope($program);
        $validated = $request->validate([
            'sector_id'   => 'required|exists:myb_sectors,id',
            'program_name' => 'required|string|max:255',
            'ttl_name' => 'required|string|max:255',
            'ttl_email' => 'required|email|max:255',
            'expected_outcome_type' => 'required|in:percentage,text',
            'expected_outcome_percentage' => 'nullable|numeric|min:0|max:100',
            'expected_outcome_text' => 'nullable|string|max:2000',
            'currency'    => 'required|string|max:10',
            'start_year'  => 'required|integer|min:1900|max:2100',
            'end_year'    => 'required|integer|min:1900|max:2100|gte:start_year',
            'description' => 'nullable|string',
            'governance_node_id' => 'required|exists:myb_governance_nodes,id',
        ]);

        $this->assertNodeInScope($validated['governance_node_id']);
        $this->assertSectorInScope($validated['sector_id']);
        $this->assertSectorBelongsToNode($validated['sector_id'], $validated['governance_node_id']);
        $this->assertProgramNameAllowedForUpdate($validated['program_name'], $program);

        $fundingMap = $this->approvedProgramFundingMap();
        $funding = $fundingMap->get($validated['program_name']);
        if (!$funding || $funding['total_budget'] === null) {
            abort(422, 'Approved funding amount not found for the selected program.');
        }

        $validated['currency'] = $funding['currency'];
        $validated['start_year'] = $funding['start_year'];
        $validated['end_year'] = $funding['end_year'];
        $validated['total_budget'] = $funding['total_budget'];

        $validated['total_years'] =
            ($validated['end_year'] - $validated['start_year']) + 1;

        $validated['updated_by'] = auth()->id();
        $previousTtlUserId = $program->ttl_user_id;
        $previousTtlEmail = Str::lower((string) $program->ttl_email);
        $ttlAssignment = $this->persistProgramTtl($validated);

        $expectedOutcomeValue = $validated['expected_outcome_type'] === 'percentage'
            ? (string) ($validated['expected_outcome_percentage'] ?? '')
            : ($validated['expected_outcome_text'] ?? '');

        if ($validated['expected_outcome_type'] === 'percentage' && $expectedOutcomeValue === '') {
            abort(422, 'Expected outcome percentage is required.');
        }

        if ($validated['expected_outcome_type'] === 'text' && $expectedOutcomeValue === '') {
            abort(422, 'Expected outcome description is required.');
        }

        $program->update([
            'sector_id' => $validated['sector_id'],
            'name' => $validated['program_name'],
            'currency' => $validated['currency'],
            'start_year' => $validated['start_year'],
            'end_year' => $validated['end_year'],
            'total_budget' => $validated['total_budget'],
            'description' => $validated['description'] ?? null,
            'expected_outcome_type' => $validated['expected_outcome_type'],
            'expected_outcome_value' => $expectedOutcomeValue,
            'total_years' => $validated['total_years'],
            'updated_by' => $validated['updated_by'],
            'governance_node_id' => $validated['governance_node_id'],
            'ttl_user_id' => $ttlAssignment['user']->id,
            'ttl_name' => $validated['ttl_name'],
            'ttl_email' => Str::lower($validated['ttl_email']),
        ]);

        $shouldNotifyTtl = $ttlAssignment['created']
            || (string) $previousTtlUserId !== (string) $ttlAssignment['user']->id
            || $previousTtlEmail !== Str::lower($validated['ttl_email']);

        $mailSent = true;
        if ($shouldNotifyTtl) {
            $mailSent = $this->sendProgramTtlMailSafely(
                $program->fresh(['sector', 'governanceNode.level', 'ttlUser']),
                $ttlAssignment['user'],
                $ttlAssignment['plain_password']
            );
        }

        return redirect()
            ->route('budget.programs.index')
            ->with('success', $mailSent
                ? 'Program updated successfully.'
                : 'Program updated successfully, but the TTL access email could not be sent. Check the Laravel log.');
    }

    /**
     * Delete program (cascade-safe)
     */
    public function destroy(Program $program)
    {
        $this->assertProgramInScope($program);
        DB::beginTransaction();

        try {
            foreach ($program->projects as $project) {
                foreach ($project->activities as $activity) {
                    foreach ($activity->subActivities as $sub) {
                        $sub->allocations()?->delete();
                        $sub->delete();
                    }

                    $activity->allocations()?->delete();
                    $activity->delete();
                }

                $project->allocations()?->delete();
                $project->delete();
            }

            $program->delete();

            DB::commit();

            return back()->with('success', 'Program deleted successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    private function scopedNodeIds(): ?array
    {
        $currentUser = Auth::user();

        if (!$currentUser || $this->hasGlobalGovernanceScope()) {
            return null;
        }

        if (!$currentUser->governance_node_id) {
            return [];
        }

        return [$currentUser->governance_node_id];
    }

    private function availableSectors()
    {
        $currentUser = Auth::user();
        $isPortfolioLeader = $this->userHasAssignedPortfolioScope($currentUser);
        $scopedNodeIds = $this->scopedNodeIds();

        return Sector::orderBy('name')
            ->when($isPortfolioLeader, function ($query) use ($currentUser) {
                $this->applyAssignedPortfolioScopeToSectors($query, $currentUser);
            })
            ->when(! $isPortfolioLeader && $scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->get();
    }

    private function availableNodes()
    {
        $currentUser = Auth::user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            return GovernanceNode::with('level')
                ->whereIn('id', $this->assignedPortfolioNodeIds($currentUser))
                ->orderBy('name')
                ->get();
        }

        $scopedNodeIds = $this->scopedNodeIds();

        return GovernanceNode::with('level')
            ->orderBy('name')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('id', $scopedNodeIds);
            })
            ->get();
    }

    private function hasGlobalGovernanceScope(): bool
    {
        $currentUser = Auth::user();

        return (bool) ($currentUser && ($currentUser->isAdmin() || $currentUser->isSuperAdmin()));
    }

    private function approvedProgramNames(?string $includeName = null)
    {
        $names = $this->approvedProgramFundingMap()->keys();

        if ($includeName && !$names->contains($includeName)) {
            $names->push($includeName);
        }

        return $names;
    }

    private function approvedProgramFundingMap(?string $includeName = null, ?Program $program = null)
    {
        $scopedNodeIds = $this->scopedNodeIds();
        $query = ProgramFunding::where('status', 'approved')
            ->whereNotNull('program_name')
            ->orderByDesc('id');

        if ($scopedNodeIds !== null) {
            $query->whereIn('governance_node_id', $scopedNodeIds);
        }

        $fundings = $query
            ->with('funder:id,name')
            ->get(['id', 'program_name', 'currency', 'start_year', 'end_year', 'approved_amount', 'funder_id', 'funding_type', 'approved_at']);

        $map = $fundings->groupBy('program_name')->map(function ($rows) {
            $row = $rows->first();
            $funders = $rows
                ->map(function (ProgramFunding $funding) {
                    return [
                        'name' => $funding->funder?->name ?? 'Unassigned funding partner',
                        'amount' => (float) ($funding->approved_amount ?? 0),
                        'currency' => $funding->currency ?: 'USD',
                        'funding_type' => $funding->funding_type ?: 'grant',
                        'period' => trim(($funding->start_year ?: 'N/A') . ' - ' . ($funding->end_year ?: 'N/A')),
                        'approved_at' => $funding->approved_at?->format('d M Y'),
                    ];
                })
                ->values();

            return [
                'currency' => $row->currency,
                'start_year' => $row->start_year,
                'end_year' => $row->end_year,
                'total_budget' => $row->approved_amount,
                'funders' => $funders->all(),
                'funder_summary' => $funders->pluck('name')->unique()->implode(', '),
                'funding_type' => $row->funding_type ?: 'grant',
                'approved_at' => $row->approved_at?->format('d M Y'),
            ];
        });

        if ($includeName && !$map->has($includeName)) {
            $map->put($includeName, [
                'currency' => $program?->currency,
                'start_year' => $program?->start_year,
                'end_year' => $program?->end_year,
                'total_budget' => null,
                'funders' => [],
                'funder_summary' => 'No approved funding partner linked',
                'funding_type' => null,
                'approved_at' => null,
            ]);
        }

        return $map;
    }

    private function assertProgramNameAllowed(string $name): void
    {
        $allowed = $this->approvedProgramNames();
        if (!$allowed->contains($name)) {
            abort(422, 'Program name must come from approved funding.');
        }

        $exists = Program::where('name', $name)->exists();
        if ($exists) {
            abort(422, 'That approved funding has already been used by another program. Please choose a different approved program.');
        }
    }

    private function assertProgramNameAllowedForUpdate(string $name, Program $program): void
    {
        $allowed = $this->approvedProgramNames($program->name);
        if (!$allowed->contains($name)) {
            abort(422, 'Program name must come from approved funding.');
        }

        $exists = Program::where('name', $name)
            ->where('id', '!=', $program->id)
            ->exists();
        if ($exists) {
            abort(422, 'That approved funding has already been used by another program. Please choose a different approved program.');
        }
    }

    private function assertProgramInScope(Program $program): void
    {
        $currentUser = Auth::user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            if (! $this->programIsInAssignedPortfolio($program, $currentUser)) {
                abort(403, 'You do not have access to this program.');
            }

            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$program->governance_node_id || !in_array($program->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this program.');
        }
    }

    private function assertSectorInScope(string $sectorId): void
    {
        $currentUser = Auth::user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            if (! $this->sectorIsAssignedToUser($sectorId, $currentUser)) {
                abort(403, 'You do not have access to this portfolio.');
            }

            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        $sector = Sector::find($sectorId);
        if (!$sector || !$sector->governance_node_id || !in_array($sector->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this portfolio.');
        }
    }

    private function assertNodeInScope(string $nodeId): void
    {
        $currentUser = Auth::user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            if (! in_array((string) $nodeId, $this->assignedPortfolioNodeIds($currentUser), true)) {
                abort(403, 'You do not have access to assign this governance node.');
            }

            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to assign this governance node.');
        }
    }

    private function assertSectorBelongsToNode(string $sectorId, string $nodeId): void
    {
        $sector = Sector::find($sectorId);

        if (!$sector || (string) $sector->governance_node_id !== (string) $nodeId) {
            abort(422, 'Selected portfolio does not belong to the selected governance node.');
        }
    }

    private function persistProgramTtl(array $validated): array
    {
        $ttlRole = Role::firstOrCreate(
            ['name' => 'Task Team Leader'],
            ['description' => 'Program-level TTL workspace access for assigned programs, projects, activities, budgets and partner-facing progress.']
        );

        $email = Str::lower(trim((string) $validated['ttl_email']));
        $name = trim((string) $validated['ttl_name']);

        $user = User::with('role')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user) {
            if ($user->isSuperAdmin() || $user->role?->name === 'Super Admin') {
                abort(422, 'Super Admin accounts cannot be assigned as program TTLs from this form.');
            }

            if ((bool) $user->is_disabled || (bool) $user->is_blacklisted) {
                abort(422, 'The selected TTL email belongs to a blocked user. Please reactivate the account or use another email.');
            }

            $updates = ['name' => $name];

            if ($user->user_type === 'ttl' || (! $user->role_id && in_array($user->user_type, [null, 'staff'], true))) {
                $updates['role_id'] = $ttlRole->id;
            }

            if (! $user->user_type) {
                $updates['user_type'] = 'ttl';
            }

            if (! $user->governance_node_id && ! empty($validated['governance_node_id'])) {
                $updates['governance_node_id'] = $validated['governance_node_id'];
            }

            $user->update($updates);

            return [
                'user' => $user->fresh(['role']),
                'plain_password' => null,
                'created' => false,
            ];
        }

        $plainPassword = Str::password(12);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'role_id' => $ttlRole->id,
            'governance_node_id' => $validated['governance_node_id'] ?? null,
            'member_state_id' => null,
            'user_type' => 'ttl',
            'must_change_password' => true,
        ]);

        return [
            'user' => $user->fresh(['role']),
            'plain_password' => $plainPassword,
            'created' => true,
        ];
    }

    private function sendProgramTtlMailSafely(Program $program, User $user, ?string $plainPassword): bool
    {
        try {
            NotifyProgramTtlAssigned::dispatchSync($program->id, $user->id, $plainPassword);

            return true;
        } catch (Throwable $e) {
            Log::warning('Program TTL assignment notification could not be sent.', [
                'program_id' => $program->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
