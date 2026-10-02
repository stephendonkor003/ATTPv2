<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountSetupInvitationService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index()
{
    $users = \App\Models\User::latest()->get(); // ✅ Collection

    return view('system.users.index', compact('users'));
}


    /**
     * Show create user form
     */
    public function create()
    {
        $this->authorize('users.manage');

        $roles = Role::orderBy('name')->get();

        return view('system.users.create', compact('roles'));
    }

    /**
     * Store new user
     */
    public function store(Request $request)
    {
        $this->authorize('users.manage');

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'role_id'  => 'required|exists:roles,id',
        ]);

        $invitations = app(AccountSetupInvitationService::class);

        $user = User::create([
            'name'                 => $request->name,
            'email'                => $request->email,
            'password'             => $invitations->unknownPasswordHash(),
            'role_id'              => $request->role_id,
            'user_type'            => 'staff', // legacy support
            'must_change_password' => true,
        ]);

        $invitationSent = $invitations->send($user, AccountSetupInvitationService::PURPOSE_STAFF);

        return redirect()
            ->route('system.users.index')
            ->with('success', $invitationSent
                ? 'User account created successfully. A secure account setup link was sent.'
                : 'User account created successfully, but the secure setup link could not be delivered. Verify mail delivery and resend a password link.');
    }

    /**
     * Show edit form
     */
    public function edit(User $user)
    {
        $this->authorize('users.manage');

        $roles = Role::orderBy('name')->get();

        return view('system.users.edit', compact('user', 'roles'));
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('users.manage');

        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:users,email,' . $user->id,
            'role_id' => 'required|exists:roles,id',
        ]);

        $user->update([
            'name'    => $request->name,
            'email'   => $request->email,
            'role_id' => $request->role_id,
        ]);

        return redirect()
            ->route('system.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Delete user
     */
    public function destroy(User $user)
    {
        $this->authorize('users.manage');

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()
            ->route('system.users.index')
            ->with('success', 'User deleted successfully.');
    }

}
