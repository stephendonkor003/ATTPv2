<?php

namespace App\Http\Controllers\System;

use App\Data\ThinkTank\CreateThinkTankUserData;
use App\Data\ThinkTank\UpdateThinkTankUserData;
use App\Http\Controllers\Controller;
use App\Models\ConsortiumThinkTank;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankUserManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ThinkTankUserController extends Controller
{
    public function __construct(
        private readonly ThinkTankUserManagementService $userManagement,
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->query('q')),
            'think_tank_member_id' => trim((string) $request->query('think_tank_member_id')),
            'access_level' => trim((string) $request->query('access_level')),
            'account_status' => trim((string) $request->query('account_status')),
        ];

        $baseQuery = $this->thinkTankUsersQuery();
        $users = (clone $baseQuery)
            ->with([
                'assignedThinkTankMembership.consortium:id,name',
                'thinkTankMembership.consortium:id,name',
            ])
            ->when($filters['q'], function ($query, $keyword): void {
                $search = '%'.$keyword.'%';
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhereHas('assignedThinkTankMembership', fn ($member) => $member->where('name', 'like', $search))
                        ->orWhereHas('thinkTankMembership', fn ($member) => $member->where('name', 'like', $search));
                });
            })
            ->when($filters['think_tank_member_id'], function ($query, $memberId): void {
                $query->where(function ($nested) use ($memberId): void {
                    $nested->where('think_tank_member_id', $memberId)
                        ->orWhereHas('thinkTankMembership', fn ($member) => $member->whereKey($memberId));
                });
            })
            ->when($filters['access_level'], fn ($query, $level) => $query->where('think_tank_access_level', $level))
            ->when($filters['account_status'] === 'active', fn ($query) => $this->activeUsers($query))
            ->when($filters['account_status'] === 'disabled', fn ($query) => $this->disabledUsers($query))
            ->latest()
            ->paginate(18)
            ->withQueryString();

        $members = ConsortiumThinkTank::query()
            ->with('consortium:id,name')
            ->withCount('portalUsers')
            ->orderBy('name')
            ->get(['id', 'consortium_id', 'name', 'country', 'status']);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => $this->activeUsers(clone $baseQuery)->count(),
            'administrators' => (clone $baseQuery)->where('think_tank_access_level', User::THINK_TANK_ACCESS_ADMIN)->count(),
            'procurement' => (clone $baseQuery)->where('think_tank_access_level', User::THINK_TANK_ACCESS_PROCUREMENT)->count(),
            'me' => (clone $baseQuery)->where('think_tank_access_level', User::THINK_TANK_ACCESS_ME)->count(),
        ];

        $accessLevels = $this->managedAccessLevels();
        $allAccessLevels = User::THINK_TANK_ACCESS_LEVELS;

        return view('think-tank-users.index', compact(
            'users', 'members', 'stats', 'accessLevels', 'allAccessLevels', 'filters'
        ));
    }

    public function show(User $user)
    {
        $this->assertThinkTankUser($user);
        $user->load([
            'assignedThinkTankMembership.consortium:id,name',
            'thinkTankMembership.consortium:id,name',
        ]);

        $members = ConsortiumThinkTank::query()
            ->with('consortium:id,name')
            ->orderBy('name')
            ->get(['id', 'consortium_id', 'name', 'country', 'status']);
        $accessLevels = $this->managedAccessLevels();
        $allAccessLevels = User::THINK_TANK_ACCESS_LEVELS;
        $auditLogs = SystemAuditLog::query()
            ->with('user:id,name,email')
            ->where('module', 'think_tank_users')
            ->where('payload->staff_user_id', $user->id)
            ->latest()
            ->limit(25)
            ->get();

        return view('think-tank-users.show', compact(
            'user', 'members', 'accessLevels', 'allAccessLevels', 'auditLogs'
        ));
    }

    public function store(Request $request)
    {
        $accessLevels = array_keys($this->managedAccessLevels());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'think_tank_member_id' => ['required', 'uuid', Rule::exists('attp_consortium_think_tanks', 'id')],
            'access_level' => ['required', Rule::in($accessLevels)],
        ], [
            'email.unique' => 'This email cannot be used because an account already exists.',
            'think_tank_member_id.required' => 'Select the Think Tank this user belongs to.',
        ]);

        $member = ConsortiumThinkTank::with('consortium')->findOrFail($data['think_tank_member_id']);
        $result = $this->userManagement->createForSystemOversight(
            $request,
            $request->user(),
            $member,
            CreateThinkTankUserData::from($data),
        );
        $user = $result['user'];
        $this->synchronizePrimaryAdministrator($member);

        $this->audit($request, 'think_tank_user_created', 'Think Tank portal user created', [
            'staff_user_id' => $user->id,
            'think_tank_member_id' => $member->id,
            'access_level' => $data['access_level'],
            'invitation_delivered' => $result['invitation_sent'],
        ]);

        return redirect()
            ->route('system.think-tank-users.index')
            ->with('success', $result['invitation_sent']
                ? 'Think Tank user created. A secure, single-use password setup link was sent.'
                : 'Think Tank user created, but the secure invitation could not be delivered. Resend the invitation or ask the user to use Forgot password.');
    }

    public function update(Request $request, User $user)
    {
        $this->assertThinkTankUser($user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'think_tank_member_id' => ['required', 'uuid', Rule::exists('attp_consortium_think_tanks', 'id')],
            'access_level' => ['required', Rule::in(array_keys(User::THINK_TANK_ACCESS_LEVELS))],
            'account_status' => ['required', Rule::in(['active', 'disabled'])],
        ]);

        $oldMember = $user->assignedThinkTankMembership()->first();
        abort_unless($oldMember, 422, 'This user has no explicit Think Tank assignment. Resolve the account-link audit before editing it.');
        $oldAccessLevel = $user->think_tank_access_level;
        $newMember = ConsortiumThinkTank::query()->findOrFail($data['think_tank_member_id']);
        $disable = $data['account_status'] === 'disabled';

        if ((string) $oldMember->getKey() !== (string) $newMember->getKey()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'think_tank_member_id' => ['Tenant reassignment is disabled during the API migration. Create the account in the correct Think Tank or use an audited transfer workflow.'],
            ]);
        }

        $result = $this->userManagement->updateForSystemOversight(
            $request,
            $request->user(),
            $oldMember,
            $user,
            UpdateThinkTankUserData::from([
                'name' => $data['name'],
                'email' => $data['email'],
                'access_level' => $data['access_level'],
                'is_disabled' => $disable,
            ]),
        );
        $user = $result['user'];
        $primaryAssignment = $this->synchronizePrimaryAdministrator($oldMember)?->id;

        $this->audit($request, 'think_tank_user_updated', 'Think Tank portal user access updated', [
            'staff_user_id' => $user->id,
            'think_tank_member_id' => $newMember->id,
            'email' => Str::lower($data['email']),
            'previous_think_tank_member_id' => $oldMember?->id,
            'previous_access_level' => $oldAccessLevel,
            'access_level' => $data['access_level'],
            'is_disabled' => $disable,
            'primary_administrator_assignment' => $primaryAssignment,
            'invitation_delivered' => $result['invitation_sent'],
        ]);

        return back()->with('success', $result['invitation_sent'] === false
            ? 'Account access was updated, but the secure email-change invitation could not be delivered.'
            : 'Account details and access were updated successfully. Primary administrator assignment was synchronized automatically.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->assertThinkTankUser($user);
        $member = $user->assignedThinkTankMembership()->first();
        abort_unless($member, 422, 'This user is not assigned to a Think Tank.');
        $member->loadMissing('consortium');

        $delivered = $this->userManagement->resetPasswordForSystemOversight(
            $request,
            $request->user(),
            $member,
            $user,
        );
        $this->audit($request, 'think_tank_user_password_reset_initiated', 'Think Tank portal password and sessions revoked; secure reset initiated', [
            'staff_user_id' => $user->id,
            'think_tank_member_id' => $member->id,
            'reset_link_delivered' => $delivered,
        ]);

        return back()
            ->with('success', $delivered
                ? 'The previous password and active sessions were revoked, and a secure single-use reset link was sent.'
                : 'The previous password and active sessions were revoked, but the reset link could not be delivered. Retry or ask the user to use Forgot password.');
    }

    private function thinkTankUsersQuery()
    {
        return User::query()
            ->where('user_type', 'think_tank')
            ->whereNotNull('think_tank_member_id');
    }

    private function activeUsers($query)
    {
        return $query->where(function ($active): void {
            $active->where('is_disabled', false)
                ->orWhere(function ($expired): void {
                    $expired->where('is_disabled', true)
                        ->whereNotNull('disabled_until')
                        ->where('disabled_until', '<=', now());
                });
        });
    }

    private function disabledUsers($query)
    {
        return $query->where('is_disabled', true)
            ->where(function ($disabled): void {
                $disabled->whereNull('disabled_until')
                    ->orWhere('disabled_until', '>', now());
            });
    }

    private function managedAccessLevels(): array
    {
        return [
            User::THINK_TANK_ACCESS_ADMIN => 'Think Tank Administrator',
            User::THINK_TANK_ACCESS_PROCUREMENT => 'Procurement Officer',
            User::THINK_TANK_ACCESS_ME => 'M&E Officer',
            User::THINK_TANK_ACCESS_FINANCE => 'Finance Officer',
        ];
    }

    private function synchronizePrimaryAdministrator(ConsortiumThinkTank $member): ?User
    {
        $currentPrimary = $member->portal_user_id
            ? User::query()->find($member->portal_user_id)
            : null;
        $currentPrimaryIsValid = $currentPrimary
            && $currentPrimary->user_type === 'think_tank'
            && (string) $currentPrimary->think_tank_member_id === (string) $member->id
            && $currentPrimary->think_tank_access_level === User::THINK_TANK_ACCESS_ADMIN
            && ! $currentPrimary->hasActiveLoginBlock()
            && ! ConsortiumThinkTank::query()
                ->where('portal_user_id', $currentPrimary->id)
                ->whereKeyNot($member->id)
                ->exists();

        if ($currentPrimaryIsValid) {
            return $currentPrimary;
        }

        $replacement = $this->activeUsers(User::query()
            ->where('user_type', 'think_tank')
            ->where('think_tank_member_id', $member->id)
            ->where('think_tank_access_level', User::THINK_TANK_ACCESS_ADMIN)
            ->whereDoesntHave('thinkTankMembership', fn ($claim) => $claim->whereKeyNot($member->id))
            ->orderBy('created_at'))
            ->first();

        if ((string) $member->portal_user_id !== (string) $replacement?->id) {
            $member->update(['portal_user_id' => $replacement?->id]);
        }

        return $replacement;
    }

    private function assertThinkTankUser(User $user): void
    {
        abort_unless($user->user_type === 'think_tank', 404);
    }

    private function audit(Request $request, string $action, string $message, array $payload): void
    {
        try {
            SystemAuditLog::create([
                'user_id' => $request->user()?->id,
                'module' => 'think_tank_users',
                'action' => $action,
                'action_message' => $message,
                'description' => $message,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                'status_code' => 200,
                'payload' => $payload,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Think Tank user audit event could not be recorded.', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
