<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PartnerProfileController extends Controller
{
    /**
     * Show the profile edit form
     */
    public function edit()
    {
        $user = Auth::user();
        $funder = $user->partnerFunder();

        if (!$funder) {
            abort(403, 'Unauthorized access');
        }

        // Log activity
        PartnerActivityLog::logActivity(
            $funder->id,
            $user->id,
            'view_profile_settings'
        );

        return view('partner.profile.edit', compact('user', 'funder'));
    }

    /**
     * Update the partner's profile information
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $funder = $user->partnerFunder();

        if (!$funder) {
            abort(403, 'Unauthorized access');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'current_password' => 'nullable|required_with:new_password|current_password',
            'new_password' => [
                'nullable',
                'required_with:current_password',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
        ]);

        // Update user information
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Update password if provided
        if ($request->filled('new_password')) {
            $user->password = Hash::make($validated['new_password']);
            $user->must_change_password = false;
        }

        $user->save();

        // Log activity
        PartnerActivityLog::logActivity(
            $funder->id,
            $user->id,
            'update_profile',
            null,
            null,
            [
                'updated_fields' => array_keys($validated),
                'password_changed' => $request->filled('new_password'),
            ]
        );

        return redirect()
            ->route('partner.profile.edit')
            ->with('success', __('partner.profile_updated_successfully'));
    }
}
