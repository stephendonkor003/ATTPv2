<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Mail\UserAccountCreated;
use App\Mail\UserPasswordReset;
use App\Mail\VendorAccountCreated;
use App\Models\AuMemberState;
use App\Models\GovernanceNode;
use App\Models\GovernanceReportingLine;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorCategory;
use App\Services\ThinkTank\ThinkTankUserManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class UserAccessController extends Controller
{
    /* ======================================================
     | USERS LIST
     ====================================================== */
    public function index()
    {
        $scopedNodeIds = $this->scopedNodeIds();

        return view('system.users.index', [
            'users' => User::with(['role.permissions', 'governanceNode', 'memberState'])
                ->where(fn ($query) => $query
                    ->whereNull('user_type')
                    ->orWhere('user_type', '!=', 'think_tank'))
                ->whereNull('think_tank_member_id')
                ->whereDoesntHave('thinkTankMembership')
                ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                    $query->whereIn('governance_node_id', $scopedNodeIds);
                })
                ->latest()
                ->get(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    /* ======================================================
     | CREATE USER
     ====================================================== */
    public function create(Request $request)
    {
        $roles = Role::orderBy('name')->get();
        $nodes = $this->availableNodes();
        $memberStates = AuMemberState::ordered()->get();
        $vendorCategories = VendorCategory::where('is_active', true)
            ->orderBy('name')
            ->pluck('name');
        $defaultUserType = in_array($request->query('user_type'), $this->allowedUserTypes(), true)
            ? $request->query('user_type')
            : 'staff';

        return view('system.users.create', compact('roles', 'nodes', 'memberStates', 'vendorCategories', 'defaultUserType'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'role_id' => [
                Rule::requiredIf(fn () => $request->input('user_type') !== 'vendor'),
                'nullable',
                'exists:roles,id',
            ],
            'user_type' => ['required', Rule::in($this->allowedUserTypes())],
            'vendor_category' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('vendor_categories', 'name')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'governance_node_id' => 'nullable|exists:myb_governance_nodes,id',
            'member_state_id' => 'nullable|required_if:user_type,member_state|exists:myb_au_member_states,id',
            'convert_existing_vendor' => 'nullable|boolean',
        ]);

        $isVendor = $validated['user_type'] === 'vendor';
        $existingUser = $this->findUserByEmail($validated['email']);

        if ($existingUser) {
            if (! $isVendor) {
                return back()
                    ->withErrors(['email' => 'This email address is already in use.'])
                    ->withInput();
            }

            if ($existingUser->user_type === 'vendor') {
                return back()
                    ->withErrors(['email' => 'This email address already belongs to a vendor account.'])
                    ->withInput();
            }

            if ((string) $existingUser->id === (string) $request->user()?->id) {
                return back()
                    ->withErrors(['email' => 'You cannot convert your own back-office account into a vendor account.'])
                    ->withInput();
            }

            if ($existingUser->role && $existingUser->role->name === 'Super Admin') {
                return back()
                    ->withErrors(['email' => 'Super Admin accounts cannot be converted into vendor accounts.'])
                    ->withInput();
            }

            $this->assertUserInScope($existingUser);

            if (! $request->boolean('convert_existing_vendor')) {
                return back()
                    ->withInput()
                    ->with('vendor_conversion_prompt', $this->vendorConversionPromptData($existingUser));
            }

            $existingUser->update([
                'name' => $validated['name'],
                'user_type' => 'vendor',
                'role_id' => null,
                'governance_node_id' => null,
                'member_state_id' => null,
                'vendor_category' => $validated['vendor_category'] ?? null,
            ]);

            $redirectRoute = $request->user()?->can('vendor.manage')
                ? 'vendors.index'
                : 'system.users.index';

            return redirect()
                ->route($redirectRoute)
                ->with('success', 'Existing back-office user converted to a vendor account successfully. The user can sign in with their existing password.');
        }

        if (! $isVendor && $request->filled('governance_node_id')) {
            $this->assertNodeInScope((string) $request->governance_node_id);
        }

        $role = ! $isVendor && ! empty($validated['role_id'])
            ? Role::find($validated['role_id'])
            : null;

        if ($role?->name === 'Monitoring and Evaluation Manager' && ! $request->filled('governance_node_id')) {
            return back()
                ->withErrors(['governance_node_id' => 'A governance node is required for Monitoring and Evaluation Manager users.'])
                ->withInput();
        }

        $plainPassword = str()->random(10);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($plainPassword),
            'role_id' => $isVendor ? null : $request->role_id,
            'governance_node_id' => $isVendor ? null : $request->input('governance_node_id'),
            'member_state_id' => $validated['user_type'] === 'member_state' ? $request->input('member_state_id') : null,
            'user_type' => $validated['user_type'],
            'vendor_category' => $isVendor ? ($validated['vendor_category'] ?? null) : null,
            'must_change_password' => true,
        ]);

        $mailSent = $this->sendUserMailSafely(
            $user,
            $isVendor
                ? new VendorAccountCreated($user, $plainPassword)
                : new UserAccountCreated($user, $plainPassword),
            $isVendor
                ? 'Vendor account created email could not be sent.'
                : 'User account created email could not be sent.',
            $plainPassword
        );

        $redirectRoute = $isVendor && $request->user()?->can('vendor.manage')
            ? 'vendors.index'
            : 'system.users.index';

        return redirect()
            ->route($redirectRoute)
            ->with('success', $mailSent
                ? ($isVendor ? 'Vendor account created successfully.' : 'User account created successfully.')
                : (($isVendor ? 'Vendor account created successfully' : 'User account created successfully').", but email delivery failed. Temporary password: {$plainPassword}"));
    }

    /* ======================================================
     | EDIT USER
     ====================================================== */
    public function edit(User $user)
    {
        $this->assertUserInScope($user);
        $roles = Role::orderBy('name')->get();
        $nodes = $this->availableNodes();
        $memberStates = AuMemberState::ordered()->get();
        $vendorCategories = VendorCategory::where('is_active', true)
            ->orderBy('name')
            ->pluck('name');

        return view('system.users.edit', compact('user', 'roles', 'nodes', 'memberStates', 'vendorCategories'));
    }

    public function update(Request $request, User $user)
    {
        if ($user->role && $user->role->name === 'Super Admin') {
            return back()->with('error', 'Super Admin cannot be modified.');
        }

        $this->assertUserInScope($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role_id' => [
                Rule::requiredIf(fn () => $request->input('user_type') !== 'vendor'),
                'nullable',
                'exists:roles,id',
            ],
            'user_type' => ['required', Rule::in($this->allowedUserTypes())],
            'vendor_category' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('vendor_categories', 'name')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'governance_node_id' => 'nullable|exists:myb_governance_nodes,id',
            'member_state_id' => 'nullable|required_if:user_type,member_state|exists:myb_au_member_states,id',
            'confirm_user_type_conversion' => 'nullable|boolean',
        ]);

        $isVendor = $validated['user_type'] === 'vendor';
        $changesVendorBoundary = ($user->user_type === 'vendor') !== $isVendor;

        if ($changesVendorBoundary && ! $request->boolean('confirm_user_type_conversion')) {
            $targetRole = ! $isVendor && ! empty($validated['role_id'])
                ? Role::find($validated['role_id'])
                : null;

            return back()
                ->withInput()
                ->with('user_type_conversion_prompt', $this->userTypeConversionPromptData(
                    $user,
                    $validated['user_type'],
                    $targetRole,
                    $validated['vendor_category'] ?? null
                ));
        }

        if (! $isVendor && $request->filled('governance_node_id')) {
            $this->assertNodeInScope((string) $request->governance_node_id);
        }

        $role = ! $isVendor && ! empty($validated['role_id'])
            ? Role::find($validated['role_id'])
            : null;

        if ($role?->name === 'Monitoring and Evaluation Manager' && ! $request->filled('governance_node_id')) {
            return back()
                ->withErrors(['governance_node_id' => 'A governance node is required for Monitoring and Evaluation Manager users.'])
                ->withInput();
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role_id' => $isVendor ? null : $request->role_id,
            'user_type' => $validated['user_type'],
            'governance_node_id' => $isVendor ? null : $request->input('governance_node_id'),
            'member_state_id' => $validated['user_type'] === 'member_state' ? $request->input('member_state_id') : null,
            'vendor_category' => $isVendor ? ($validated['vendor_category'] ?? null) : null,
        ]);

        return redirect()
            ->route('system.users.index')
            ->with('success', 'User updated successfully.');
    }

    /* ======================================================
     | DELETE USER
     ====================================================== */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->role && $user->role->name === 'Super Admin') {
            return back()->with('error', 'Super Admin cannot be deleted.');
        }

        $this->assertUserInScope($user);

        $user->delete();

        return redirect()
            ->route('system.users.index')
            ->with('success', 'User deleted successfully.');
    }

    /* ======================================================
     | RESET PASSWORD (EMAIL NEW PASSWORD)
     ====================================================== */
    public function resetPassword(User $user)
    {
        if ($user->role && $user->role->name === 'Super Admin') {
            return back()->with('error', 'Super Admin password cannot be reset here.');
        }

        $this->assertUserInScope($user);

        $plainPassword = str()->random(10);

        $user->update([
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
        ]);

        $mailSent = $this->sendUserMailSafely(
            $user,
            new UserPasswordReset($user, $plainPassword),
            'User password reset email could not be sent.',
            $plainPassword
        );

        return back()->with('success', $mailSent
            ? 'Password reset and emailed successfully.'
            : "Password reset successfully, but email delivery failed. Temporary password: {$plainPassword}");
    }

    public function blockLogin(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot block your own login.');
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin login cannot be blocked.');
        }

        $this->assertUserInScope($user);

        $validated = $request->validate([
            'block_type' => 'required|in:temporary,permanent',
            'duration_value' => 'nullable|required_if:block_type,temporary|integer|min:1|max:3650',
            'duration_unit' => 'nullable|required_if:block_type,temporary|in:minutes,hours,days,weeks,months',
            'reason' => 'nullable|string|max:255',
        ]);

        $disabledUntil = null;
        if ($validated['block_type'] === 'temporary') {
            $duration = (int) $validated['duration_value'];
            $disabledUntil = match ($validated['duration_unit']) {
                'minutes' => now()->addMinutes($duration),
                'hours' => now()->addHours($duration),
                'days' => now()->addDays($duration),
                'weeks' => now()->addWeeks($duration),
                'months' => now()->addMonths($duration),
            };
        }

        $reason = trim((string) ($validated['reason'] ?? ''));
        if ($reason === '') {
            $reason = $validated['block_type'] === 'temporary'
                ? 'Temporarily blocked by user management.'
                : 'Permanently blocked by user management.';
        }

        $user->update([
            'is_disabled' => true,
            'disabled_at' => now(),
            'disabled_until' => $disabledUntil,
            'disabled_reason' => $reason,
        ]);

        $statusMessage = $disabledUntil
            ? 'User login blocked temporarily until '.$disabledUntil->format('d M Y H:i').'.'
            : 'User login blocked permanently.';

        return back()->with('success', $statusMessage);
    }

    public function unblockLogin(User $user)
    {
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin login is always allowed.');
        }

        $this->assertUserInScope($user);

        $user->update([
            'is_disabled' => false,
            'disabled_at' => null,
            'disabled_until' => null,
            'disabled_reason' => null,
        ]);

        return back()->with('success', 'User login unblocked successfully.');
    }

    public function bulkLoginAccess(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:disable,enable',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|exists:users,id',
        ]);

        $userIds = array_values(array_unique($validated['user_ids']));
        $users = User::whereIn('id', $userIds)->get();
        $updated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $this->assertUserInScope($user);

            if ($user->id === auth()->id() || $user->isAdmin() || $user->isSuperAdmin()) {
                $skipped++;

                continue;
            }

            if ($validated['action'] === 'disable') {
                $user->update([
                    'is_disabled' => true,
                    'disabled_at' => now(),
                    'disabled_until' => null,
                    'disabled_reason' => 'Login disabled by bulk user management.',
                ]);
            } else {
                $user->update([
                    'is_disabled' => false,
                    'disabled_at' => null,
                    'disabled_until' => null,
                    'disabled_reason' => null,
                ]);
            }

            $updated++;
        }

        if ($updated === 0) {
            return back()->with('error', 'No selected users could be updated.');
        }

        $message = $validated['action'] === 'disable'
            ? "{$updated} user login(s) disabled successfully."
            : "{$updated} user login(s) enabled successfully.";

        if ($skipped > 0) {
            $message .= " {$skipped} protected account(s) skipped.";
        }

        return back()->with('success', $message);
    }

    /* ======================================================
     | INLINE ROLE UPDATE (USED IN INDEX)
     ====================================================== */
    public function updateRole(Request $request, User $user)
    {
        if ($user->role && $user->role->name === 'Super Admin') {
            return back()->with('error', 'Super Admin role cannot be changed.');
        }

        $this->assertUserInScope($user);
        if ($user->user_type === 'vendor') {
            return back()->with('error', 'Vendor portal accounts do not use system roles.');
        }

        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $user->update([
            'role_id' => $request->role_id,
        ]);

        return back()->with('success', 'User role updated successfully.');
    }

    /* ======================================================
     | USER DIRECT PERMISSIONS (OPTIONAL)
     ====================================================== */
    public function permissions(User $user)
    {
        $this->assertUserInScope($user);

        return view('system.users.permissions', [
            'user' => $user,
            'permissions' => Permission::orderBy('module')->get()->groupBy('module'),
        ]);
    }

    public function syncPermissions(Request $request, User $user)
    {
        $this->assertUserInScope($user);

        $user->permissions()->sync(
            $request->input('permissions', [])
        );

        return back()->with('success', 'Permissions updated successfully.');
    }

    private function scopedNodeIds(): ?array
    {
        $currentUser = Auth::user();

        if (! $currentUser || $currentUser->isAdmin() || $currentUser->isSuperAdmin()) {
            return null;
        }

        if (! $currentUser->governance_node_id) {
            return [];
        }

        return $this->descendantNodeIds($currentUser->governance_node_id);
    }

    private function allowedUserTypes(): array
    {
        // Think Tank identities are tenant-bound and may only be created or
        // changed through the dedicated, audited portal-user service.
        return ['admin', 'staff', 'member_state', 'vendor', 'funding_partner', 'evaluator', 'ttl'];
    }

    private function availableNodes()
    {
        $scopedNodeIds = $this->scopedNodeIds();

        return GovernanceNode::orderBy('name')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('id', $scopedNodeIds);
            })
            ->get();
    }

    private function assertUserInScope(User $user): void
    {
        app(ThinkTankUserManagementService::class)
            ->assertNotManagedPortalIdentity($user);

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (! $user->governance_node_id || ! in_array($user->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to manage this user.');
        }
    }

    private function assertNodeInScope(string $nodeId): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (! in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to assign this governance node.');
        }
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::with('role')
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->first();
    }

    private function vendorConversionPromptData(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'user_type' => ucfirst(str_replace('_', ' ', (string) $user->user_type)),
            'role' => $user->role?->name,
        ];
    }

    private function userTypeConversionPromptData(
        User $user,
        string $targetUserType,
        ?Role $targetRole = null,
        ?string $vendorCategory = null
    ): array {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'current_user_type' => ucfirst(str_replace('_', ' ', (string) $user->user_type)),
            'current_role' => $user->role?->name,
            'target_user_type' => ucfirst(str_replace('_', ' ', $targetUserType)),
            'target_role' => $targetRole?->name,
            'vendor_category' => $vendorCategory,
        ];
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

            foreach ($children[(string) $current] ?? [] as $childId) {
                if (! isset($seen[$childId])) {
                    $stack[] = $childId;
                }
            }
        }

        return array_keys($seen);
    }

    private function sendUserMailSafely(User $user, $mail, string $logMessage, string $plainPassword): bool
    {
        try {
            Mail::to($user->email)->send($mail);

            return true;
        } catch (Throwable $exception) {
            Log::warning($logMessage, [
                'user_id' => $user->id,
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
            ]);

            if (app()->environment(['local', 'testing'])) {
                Log::info('Local development temporary user password fallback.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'temporary_password' => $plainPassword,
                ]);
            }

            return false;
        }
    }
}
