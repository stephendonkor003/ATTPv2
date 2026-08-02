<?php

namespace App\Http\Controllers;

use App\Models\ConsortiumThinkTank;
use App\Models\MeFocalUnitContact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeFocalUnitController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.configuration.view|me.configuration.manage')->only('index');
        $this->middleware('permission:me.configuration.manage')->except('index');
    }

    public function index()
    {
        $contacts = MeFocalUnitContact::query()
            ->with(['thinkTank:id,name,country,status', 'user:id,name,email,user_type,think_tank_member_id,think_tank_access_level,is_disabled'])
            ->orderBy('consortium_name')->orderBy('think_tank_label')->orderByDesc('is_primary')->orderBy('focal_person_name')->get();
        $emails = $contacts->pluck('email')->map(fn ($email) => strtolower($email))->all();

        return view('me.focal-units.index', [
            'contacts' => $contacts,
            'thinkTanks' => ConsortiumThinkTank::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'country']),
            'matchingUsers' => User::query()
                ->whereIn(DB::raw('LOWER(email)'), $emails)
                ->get()
                ->keyBy(fn ($user) => strtolower($user->email)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->rules($request);
        MeFocalUnitContact::query()->create($validated + [
            'email' => strtolower($validated['email']),
            'is_primary' => $request->boolean('is_primary'),
            'source' => 'Platform maintained',
            'is_active' => true,
        ]);

        return back()->with('success', 'M&E focal contact added.');
    }

    public function update(Request $request, MeFocalUnitContact $contact)
    {
        $validated = $this->rules($request, $contact);
        $contact->update($validated + [
            'email' => strtolower($validated['email']),
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return back()->with('success', 'M&E focal contact updated.');
    }

    public function linkAccount(Request $request, MeFocalUnitContact $contact)
    {
        $validated = $request->validate(['user_id' => 'required|uuid|exists:users,id']);
        if (! $contact->think_tank_member_id) {
            throw ValidationException::withMessages(['contact' => 'Map the focal contact to an active think tank before linking an account.']);
        }
        $user = User::query()->findOrFail($validated['user_id']);
        if (strtolower((string) $user->email) !== strtolower($contact->email)) {
            throw ValidationException::withMessages(['user_id' => 'The selected account email must match the focal register email.']);
        }

        $user->update([
            'user_type' => 'think_tank',
            'think_tank_member_id' => $contact->think_tank_member_id,
            'think_tank_access_level' => User::THINK_TANK_ACCESS_ME,
        ]);
        $contact->update(['user_id' => $user->id]);

        return back()->with('success', $contact->focal_person_name.' is linked as the organization’s M&E Officer.');
    }

    public function destroy(MeFocalUnitContact $contact)
    {
        if ($contact->user_id) {
            throw ValidationException::withMessages(['contact' => 'Unlink or reassign the user account before deleting this focal contact.']);
        }
        $contact->delete();

        return back()->with('success', 'Focal contact removed from the register.');
    }

    private function rules(Request $request, ?MeFocalUnitContact $contact = null): array
    {
        return $request->validate([
            'consortium_name' => 'required|string|max:120',
            'think_tank_member_id' => [
                'nullable',
                'uuid',
                Rule::exists('attp_consortium_think_tanks', 'id')->where('status', 'active'),
            ],
            'think_tank_label' => 'required|string|max:160',
            'focal_person_name' => 'required|string|max:180',
            'email' => ['required', 'email', 'max:255', Rule::unique('me_focal_unit_contacts', 'email')->ignore($contact?->id)],
            'is_primary' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);
    }
}
