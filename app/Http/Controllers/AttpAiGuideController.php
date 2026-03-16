<?php

namespace App\Http\Controllers;

use App\Models\AttpAiGuideSetting;
use App\Models\Role;
use Illuminate\Http\Request;

class AttpAiGuideController extends Controller
{
    /**
     * Display ATTP AI Guide settings
     */
    public function settings()
    {
        $this->authorize('users.manage');

        $settings = AttpAiGuideSetting::first() ?? new AttpAiGuideSetting();
        $roles = Role::orderBy('name')->get();

        return view('system.attp-ai-guide.settings', compact('settings', 'roles'));
    }

    /**
     * Update ATTP AI Guide settings
     */
    public function updateSettings(Request $request)
    {
        $this->authorize('users.manage');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'enabled' => 'boolean',
            'tawk_property_id' => 'nullable|string|max:255',
            'tawk_widget_id' => 'nullable|string|max:255',
            'show_to_authenticated_only' => 'boolean',
            'show_to_guests' => 'boolean',
            'targeted_user_roles' => 'nullable|array',
            'targeted_user_roles.*' => 'integer|exists:roles,id',
            'welcome_message' => 'nullable|string|max:1000',
        ]);

        // Cast targeted_user_roles to integers
        if (!empty($validated['targeted_user_roles'])) {
            $validated['targeted_user_roles'] = array_map('intval', $validated['targeted_user_roles']);
        }

        $settings = AttpAiGuideSetting::first() ?? new AttpAiGuideSetting();
        $settings->fill($validated);
        $settings->save();

        return redirect()
            ->back()
            ->with('success', 'ATTP AI Guide settings updated successfully!');
    }

    /**
     * Test the Tawk widget
     */
    public function testWidget()
    {
        $this->authorize('users.manage');

        $settings = AttpAiGuideSetting::active();

        if (!$settings || !$settings->enabled) {
            return response()->json(['error' => 'ATTP AI Guide is not enabled'], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Test widget trigger sent. Check your page for the Tawk widget.',
            'settings' => [
                'property_id' => $settings->tawk_property_id,
                'widget_id' => $settings->tawk_widget_id,
            ],
        ]);
    }
}
