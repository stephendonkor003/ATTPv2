<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use App\Models\GovernanceNode;
use App\Models\GovernanceReportingLine;
use App\Models\Role;
use App\Models\User;
use App\Services\PortfolioLeaderAssignmentNotificationService;
use App\Services\ThinkTank\ThinkTankUserManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SectorController extends Controller
{
    private const PORTFOLIO_LEADERSHIP_ROLES = [
        'Portfolio Manager',
        'Portfolio Coordinator',
    ];

    public function __construct()
    {
        $this->middleware('permission:sector.view')->only(['index', 'show']);
        $this->middleware('permission:sector.create')->only(['create', 'store']);
        $this->middleware('permission:sector.edit')->only(['edit', 'update']);
        $this->middleware('permission:sector.delete')->only(['destroy']);
    }

    public function index()
    {
        $currentUser = Auth::user();
        $isPortfolioLeader = $this->isPortfolioLeadershipUser($currentUser);
        $scopedNodeIds = $this->scopedNodeIds();
        if (! $isPortfolioLeader && $scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to portfolios.');
        }

        $sectors = Sector::with([
            'governanceNode.level',
            'portfolioManager.role',
            'programs.projects.activities.subActivities',
        ])
            ->withCount('programs')
            ->when($isPortfolioLeader, function ($query) use ($currentUser) {
                $this->applyPortfolioLeaderScope($query, $currentUser);
            })
            ->when(! $isPortfolioLeader && $scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('name')
            ->get()
            ->each(function (Sector $sector) {
                $programs = $sector->programs;
                $projects = $programs->flatMap->projects;
                $activities = $projects->flatMap->activities;

                $sector->setAttribute('projects_count', $projects->count());
                $sector->setAttribute('activities_count', $activities->count());
                $sector->setAttribute('sub_activities_count', $activities->flatMap->subActivities->count());
                $sector->setAttribute('total_budget_value', $programs->sum(fn ($program) => (float) ($program->total_budget ?? 0)));
                $sector->setAttribute('latest_structure_update_at', collect([
                    $sector->updated_at,
                    $programs->max('updated_at'),
                    $projects->max('updated_at'),
                    $activities->max('updated_at'),
                ])->filter()->max());
            });

        $portfolioStats = [
            'total' => $sectors->count(),
            'active' => $sectors->where('status', 'active')->count(),
            'ended' => $sectors->where('status', 'ended')->count(),
            'programs' => $sectors->sum('programs_count'),
            'projects' => $sectors->sum('projects_count'),
            'activities' => $sectors->sum('activities_count'),
            'sub_activities' => $sectors->sum('sub_activities_count'),
            'budget' => $sectors->sum('total_budget_value'),
            'currency' => $this->portfolioCurrencyLabel($sectors),
            'ttl_assigned' => $sectors->filter(fn ($sector) => filled($sector->ttl_name) || filled($sector->ttl_email))->count(),
            'portfolio_leader_assigned' => $sectors->filter(fn ($sector) => filled($sector->portfolio_manager_user_id))->count(),
            'governance_assigned' => $sectors->filter(fn ($sector) => filled($sector->governance_node_id))->count(),
        ];

        $topPortfolios = $sectors
            ->sortByDesc(fn ($sector) => (float) $sector->total_budget_value)
            ->take(5)
            ->values();

        return view('sectors.index', compact('sectors', 'portfolioStats', 'topPortfolios'));
    }

    public function create()
    {
        $nodes = $this->availableNodes();
        return view('sectors.create', compact('nodes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,ended',
            'currency' => 'required|string|max:10',
            'governance_node_id' => 'required|exists:myb_governance_nodes,id',
            'portfolio_manager_name' => 'required|string|max:255',
            'portfolio_manager_email' => 'required|email|max:255',
            'portfolio_manager_role' => 'required|in:Portfolio Manager,Portfolio Coordinator',
            'convert_existing_portfolio_leader' => 'nullable|boolean',
            'ttl_name' => 'required|string|max:255',
            'ttl_email' => 'required|email|max:255',
        ]);

        $this->assertNodeInScope($validated['governance_node_id']);

        if ($redirect = $this->portfolioLeaderConversionRedirectIfNeeded($request, $validated)) {
            return $redirect;
        }

        DB::beginTransaction();

        try {
            $leader = $this->persistPortfolioLeader($request, $validated);

            $sector = Sector::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'currency' => Str::upper($validated['currency'] ?? 'USD'),
                'governance_node_id' => $validated['governance_node_id'],
                'portfolio_manager_user_id' => $leader['user']->id,
                'portfolio_manager_name' => $leader['user']->name,
                'portfolio_manager_email' => $leader['user']->email,
                'portfolio_manager_role' => $validated['portfolio_manager_role'],
                'ttl_name' => $validated['ttl_name'],
                'ttl_email' => $validated['ttl_email'],
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Portfolio creation failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return back()
                ->withErrors(['portfolio' => 'Portfolio could not be created safely. Please review the details and try again.'])
                ->withInput();
        }

        $mailSent = $this->sendPortfolioLeaderMailSafely(
            $leader['user'],
            $sector,
            $validated['portfolio_manager_role'],
            $leader['plain_password']
        );

        $message = $leader['created']
            ? 'Portfolio created successfully and the portfolio leader account was created.'
            : 'Portfolio created successfully and the existing account was assigned as portfolio leader.';

        if ($mailSent) {
            $message .= ' A portfolio assignment email has been queued for the user.';
        } else {
            $message .= $leader['plain_password']
                ? " Notification email failed. Temporary password: {$leader['plain_password']}"
                : ' Notification email failed, but the account assignment was saved.';
        }

        return redirect()
            ->route('budget.portfolios.show', $sector)
            ->with('success', $message);
    }

    public function show(Sector $sector)
    {
        $this->assertSectorInScope($sector);

        $sector->load([
            'governanceNode.level',
            'portfolioManager.role',
            'programs.projects.activities.subActivities',
        ]);

        $programs = $sector->programs;
        $projects = $programs->flatMap->projects;
        $activities = $projects->flatMap->activities;

        $portfolioStats = [
            'programs' => $programs->count(),
            'projects' => $projects->count(),
            'activities' => $activities->count(),
            'sub_activities' => $activities->flatMap->subActivities->count(),
            'budget' => $programs->sum(fn ($program) => (float) ($program->total_budget ?? 0)),
            'currency' => $this->portfolioCurrencyLabel(collect([$sector])),
            'latest_update' => collect([
                $sector->updated_at,
                $programs->max('updated_at'),
                $projects->max('updated_at'),
                $activities->max('updated_at'),
            ])->filter()->max(),
        ];

        return view('sectors.show', compact('sector', 'portfolioStats'));
    }

    public function edit(Sector $sector)
    {
        $this->assertSectorInScope($sector);
        $nodes = $this->availableNodes($sector);
        return view('sectors.edit', compact('sector', 'nodes'));
    }

    public function update(Request $request, Sector $sector)
    {
        $this->assertSectorInScope($sector);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,ended',
            'currency' => 'required|string|max:10',
            'governance_node_id' => 'required|exists:myb_governance_nodes,id',
            'portfolio_manager_name' => 'required|string|max:255',
            'portfolio_manager_email' => 'required|email|max:255',
            'portfolio_manager_role' => 'required|in:Portfolio Manager,Portfolio Coordinator',
            'convert_existing_portfolio_leader' => 'nullable|boolean',
            'ttl_name' => 'required|string|max:255',
            'ttl_email' => 'required|email|max:255',
        ]);

        if ($this->isPortfolioLeadershipUser(Auth::user())) {
            $request->merge([
                'governance_node_id' => $sector->governance_node_id,
            ]);
        }

        $this->assertNodeInScope($request->governance_node_id);

        $validated = $request->only([
            'name',
            'description',
            'status',
            'currency',
            'governance_node_id',
            'portfolio_manager_name',
            'portfolio_manager_email',
            'portfolio_manager_role',
            'convert_existing_portfolio_leader',
            'ttl_name',
            'ttl_email',
        ]);

        if ($redirect = $this->portfolioLeaderConversionRedirectIfNeeded($request, $validated, $sector)) {
            return $redirect;
        }

        DB::beginTransaction();

        try {
            $leader = $this->persistPortfolioLeader($request, $validated, $sector);

            $sector->update([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status ?: ($sector->status ?: 'active'),
                'currency' => Str::upper($request->currency ?: ($sector->currency ?: 'USD')),
                'governance_node_id' => $request->governance_node_id,
                'portfolio_manager_user_id' => $leader['user']->id,
                'portfolio_manager_name' => $leader['user']->name,
                'portfolio_manager_email' => $leader['user']->email,
                'portfolio_manager_role' => $request->portfolio_manager_role,
                'ttl_name' => $request->ttl_name,
                'ttl_email' => $request->ttl_email,
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Portfolio update failed', [
                'portfolio_id' => $sector->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return back()
                ->withErrors(['portfolio' => 'Portfolio could not be updated safely. Please review the details and try again.'])
                ->withInput();
        }

        $shouldNotifyLeader = $leader['created'] || $leader['converted'];
        $mailSent = true;
        if ($shouldNotifyLeader) {
            $mailSent = $this->sendPortfolioLeaderMailSafely(
                $leader['user'],
                $sector,
                $request->portfolio_manager_role,
                $leader['plain_password']
            );
        }

        $message = 'Portfolio updated successfully.';
        if ($leader['created']) {
            $message .= ' A portfolio leader account was created.';
        } elseif ($leader['converted']) {
            $message .= ' The existing account was assigned as portfolio leader.';
        }

        if ($shouldNotifyLeader) {
            if ($mailSent) {
                $message .= ' A portfolio assignment email has been queued for the user.';
            } else {
                $message .= $leader['plain_password']
                    ? " Notification email failed. Temporary password: {$leader['plain_password']}"
                    : ' Notification email failed, but the account assignment was saved.';
            }
        }

        return redirect()
            ->route('budget.portfolios.show', $sector)
            ->with('success', $message);
    }

    public function destroy(Sector $sector)
    {
        $this->assertSectorInScope($sector);
        $sector->delete();

        return redirect()
            ->route('budget.portfolios.index')
            ->with('success', 'Portfolio deleted successfully.');
    }

    private function portfolioLeaderConversionRedirectIfNeeded(Request $request, array $validated, ?Sector $sector = null)
    {
        $existingUser = $this->findUserByEmail($validated['portfolio_manager_email']);
        if (! $existingUser) {
            return null;
        }

        app(ThinkTankUserManagementService::class)
            ->assertNotManagedPortalIdentity($existingUser, 'portfolio_manager_email');

        $targetRoleName = $validated['portfolio_manager_role'];
        $isCurrentLeader = $sector
            && (string) $sector->portfolio_manager_user_id === (string) $existingUser->id;

        if ($isCurrentLeader) {
            return null;
        }

        if ((string) $existingUser->id === (string) $request->user()?->id) {
            return back()
                ->withErrors(['portfolio_manager_email' => 'You cannot convert your own account into a portfolio leadership account from this form.'])
                ->withInput();
        }

        if ($existingUser->isSuperAdmin() || $existingUser->role?->name === 'Super Admin') {
            return back()
                ->withErrors(['portfolio_manager_email' => 'Super Admin accounts cannot be converted into portfolio leadership accounts.'])
                ->withInput();
        }

        $this->assertUserInScopeForLeadership($existingUser);

        if (! $request->boolean('convert_existing_portfolio_leader')) {
            return back()
                ->withInput()
                ->with('portfolio_leader_conversion_prompt', [
                    'name' => $existingUser->name,
                    'email' => $existingUser->email,
                    'current_role' => $existingUser->role?->name ?? 'No role',
                    'current_type' => $existingUser->user_type ?? 'staff',
                    'target_role' => $targetRoleName,
                ]);
        }

        return null;
    }

    private function persistPortfolioLeader(Request $request, array $validated, ?Sector $sector = null): array
    {
        $targetRole = $this->portfolioLeadershipRole($validated['portfolio_manager_role']);
        $existingUser = $this->findUserByEmail($validated['portfolio_manager_email']);

        if ($existingUser) {
            $existingUser = DB::transaction(function () use ($existingUser, $targetRole, $validated): User {
                $lockedUser = User::query()->whereKey($existingUser->getKey())->lockForUpdate()->firstOrFail();
                app(ThinkTankUserManagementService::class)
                    ->assertNotManagedPortalIdentity($lockedUser, 'portfolio_manager_email');
                $lockedUser->update([
                    'name' => $validated['portfolio_manager_name'],
                    'role_id' => $targetRole->id,
                    'governance_node_id' => $validated['governance_node_id'],
                    'member_state_id' => null,
                    'user_type' => 'staff',
                    'vendor_category' => null,
                ]);

                return $lockedUser;
            });

            return [
                'user' => $existingUser->fresh(['role']),
                'plain_password' => null,
                'created' => false,
                'converted' => ! ($sector && (string) $sector->portfolio_manager_user_id === (string) $existingUser->id),
            ];
        }

        $plainPassword = Str::password(10);

        $user = User::create([
            'name' => $validated['portfolio_manager_name'],
            'email' => Str::lower($validated['portfolio_manager_email']),
            'password' => Hash::make($plainPassword),
            'role_id' => $targetRole->id,
            'governance_node_id' => $validated['governance_node_id'],
            'member_state_id' => null,
            'user_type' => 'staff',
            'must_change_password' => true,
        ]);

        return [
            'user' => $user->fresh(['role']),
            'plain_password' => $plainPassword,
            'created' => true,
            'converted' => false,
        ];
    }

    private function portfolioLeadershipRole(string $roleName): Role
    {
        if (! in_array($roleName, self::PORTFOLIO_LEADERSHIP_ROLES, true)) {
            abort(422, 'Invalid portfolio leadership role selected.');
        }

        return Role::firstOrCreate(
            ['name' => $roleName],
            [
                'description' => $roleName === 'Portfolio Manager'
                    ? 'Oversees portfolio delivery across budget, finance, procurement, M&E, evaluation, and site visits.'
                    : 'Coordinates portfolio delivery across budget, finance, procurement, M&E, evaluation, and site visits.',
            ]
        );
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::with('role')
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->first();
    }

    private function assertUserInScopeForLeadership(User $user): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null || ! $user->governance_node_id) {
            return;
        }

        if (! in_array((string) $user->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to convert this user into a portfolio leader.');
        }
    }

    private function sendPortfolioLeaderMailSafely(User $user, Sector $portfolio, string $roleName, ?string $plainPassword): bool
    {
        try {
            return app(PortfolioLeaderAssignmentNotificationService::class)
                ->notify($user, $portfolio, $roleName, $plainPassword);
        } catch (Throwable $e) {
            Log::warning('Portfolio leader account notification could not be queued or sent.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'portfolio_id' => $portfolio->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function portfolioCurrencyLabel($sectors): string
    {
        $currencies = collect($sectors)
            ->flatMap(function (Sector $sector) {
                $programCurrencies = $sector->relationLoaded('programs')
                    ? $sector->programs->pluck('currency')
                    : collect();

                return $programCurrencies->prepend($sector->currency);
            })
            ->filter(fn ($currency) => filled($currency))
            ->map(fn ($currency) => Str::upper((string) $currency))
            ->unique()
            ->values();

        if ($currencies->count() === 0) {
            return 'USD';
        }

        if ($currencies->count() === 1) {
            return $currencies->first();
        }

        return 'Mixed: ' . $currencies->take(3)->implode(', ');
    }

    private function scopedNodeIds(): ?array
    {
        $currentUser = Auth::user();

        if (!$currentUser || $currentUser->isAdmin() || $currentUser->isSuperAdmin()) {
            return null;
        }

        if (!$currentUser->governance_node_id) {
            return [];
        }

        return $this->descendantNodeIds($currentUser->governance_node_id);
    }

    private function availableNodes(?Sector $sector = null)
    {
        $currentUser = Auth::user();

        if ($this->isPortfolioLeadershipUser($currentUser)) {
            $nodeIds = Sector::query()
                ->when($sector, fn ($query) => $query->whereKey($sector->id))
                ->whereNotNull('governance_node_id')
                ->where(function ($query) use ($currentUser) {
                    $this->applyPortfolioLeaderScope($query, $currentUser);
                })
                ->pluck('governance_node_id')
                ->unique()
                ->values();

            return GovernanceNode::with('level')
                ->whereIn('id', $nodeIds)
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

    private function assertSectorInScope(Sector $sector): void
    {
        $currentUser = Auth::user();
        if ($this->isPortfolioLeadershipUser($currentUser)) {
            if (! $this->sectorBelongsToPortfolioLeader($sector, $currentUser)) {
                abort(403, 'You do not have access to this portfolio.');
            }

            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$sector->governance_node_id || !in_array($sector->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this portfolio.');
        }
    }

    private function assertNodeInScope(string $nodeId): void
    {
        $currentUser = Auth::user();
        if ($this->isPortfolioLeadershipUser($currentUser)) {
            $hasAssignedPortfolioInNode = Sector::query()
                ->where('governance_node_id', $nodeId)
                ->where(function ($query) use ($currentUser) {
                    $this->applyPortfolioLeaderScope($query, $currentUser);
                })
                ->exists();

            if (! $hasAssignedPortfolioInNode) {
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

    private function descendantNodeIds(string $rootNodeId): array
    {
        $lines = GovernanceReportingLine::where('line_type', 'primary')->get(['parent_node_id', 'child_node_id']);
        $children = [];

        foreach ($lines as $line) {
            $children[(string) $line->parent_node_id][] = (string) $line->child_node_id;
        }

        $stack = [(string) $rootNodeId];
        $seen = [];

        while ($stack) {
            $current = array_pop($stack);
            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            foreach ($children[$current] ?? [] as $childId) {
                if (!isset($seen[$childId])) {
                    $stack[] = $childId;
                }
            }
        }

        return array_keys($seen);
    }

    private function isPortfolioLeadershipUser(?User $user): bool
    {
        return $user
            && $user->role
            && in_array($user->role->name, self::PORTFOLIO_LEADERSHIP_ROLES, true);
    }

    private function applyPortfolioLeaderScope($query, ?User $user): void
    {
        if (! $user) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($scope) use ($user) {
            $scope->where('portfolio_manager_user_id', $user->id);

            if (filled($user->email)) {
                $scope->orWhereRaw('LOWER(portfolio_manager_email) = ?', [Str::lower($user->email)]);
            }
        });
    }

    private function sectorBelongsToPortfolioLeader(Sector $sector, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ((string) $sector->portfolio_manager_user_id === (string) $user->id) {
            return true;
        }

        return filled($sector->portfolio_manager_email)
            && filled($user->email)
            && Str::lower($sector->portfolio_manager_email) === Str::lower($user->email);
    }
}
