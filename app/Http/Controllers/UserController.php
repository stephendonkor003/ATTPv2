<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserAccountCreated;
use Throwable;

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

        // Generate secure password
        $plainPassword = str()->random(10);

        $user = User::create([
            'name'                 => $request->name,
            'email'                => $request->email,
            'password'             => Hash::make($plainPassword),
            'role_id'              => $request->role_id,
            'user_type'            => 'staff', // legacy support
            'must_change_password' => true,
        ]);

        $mailSent = $this->sendUserMailSafely($user, new UserAccountCreated($user, $plainPassword), $plainPassword);

        return redirect()
            ->route('system.users.index')
            ->with('success', $mailSent
                ? 'User account created successfully.'
                : "User account created successfully, but email delivery failed. Temporary password: {$plainPassword}");
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

    private function sendUserMailSafely(User $user, $mail, string $plainPassword): bool
    {
        try {
            Mail::to($user->email)->send($mail);

            return true;
        } catch (Throwable $exception) {
            Log::warning('User account created email could not be sent.', [
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
