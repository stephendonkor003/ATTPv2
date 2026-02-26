<?php

namespace App\Http\Controllers\AuMasterData;

use App\Http\Controllers\Controller;
use App\Models\AuMemberState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AuMemberStateController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings.au_master_data.view')->only(['index']);
        $this->middleware('permission:settings.au_master_data.create')->only(['create', 'store']);
        $this->middleware('permission:settings.au_master_data.edit')->only(['edit', 'update']);
        $this->middleware('permission:settings.au_master_data.delete')->only(['destroy']);
    }

    public function index()
    {
        $memberStates = AuMemberState::ordered()->get();
        return view('au-master-data.member-states.index', compact('memberStates'));
    }

    public function create()
    {
        return view('au-master-data.member-states.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:myb_au_member_states,name',
            'code' => 'nullable|string|max:3',
            'code_alpha2' => 'nullable|string|max:2',
            'flag_image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp,svg|max:5120',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['flag_path'] = $this->storeFlagImage($request);

        AuMemberState::create($validated);

        return redirect()
            ->route('settings.au.member-states.index')
            ->with('success', 'Member state created successfully.');
    }

    public function edit(AuMemberState $member_state)
    {
        return view('au-master-data.member-states.edit', ['memberState' => $member_state]);
    }

    public function update(Request $request, AuMemberState $member_state)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:myb_au_member_states,name,' . $member_state->id,
            'code' => 'nullable|string|max:3',
            'code_alpha2' => 'nullable|string|max:2',
            'flag_image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp,svg|max:5120',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $newFlagPath = $this->storeFlagImage($request);
        if ($newFlagPath) {
            $this->deleteFlagImage($member_state->flag_path);
            $validated['flag_path'] = $newFlagPath;
        }

        $member_state->update($validated);

        return redirect()
            ->route('settings.au.member-states.index')
            ->with('success', 'Member state updated successfully.');
    }

    public function destroy(AuMemberState $member_state)
    {
        $this->deleteFlagImage($member_state->flag_path);
        $member_state->delete();

        return redirect()
            ->route('settings.au.member-states.index')
            ->with('success', 'Member state deleted successfully.');
    }

    private function storeFlagImage(Request $request): ?string
    {
        if (!$request->hasFile('flag_image')) {
            return null;
        }

        $file = $request->file('flag_image');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $filename = (string) Str::uuid() . '.' . $extension;
        $relativeDirectory = 'uploads/member-states/flags';
        $absoluteDirectory = public_path($relativeDirectory);

        if (!File::exists($absoluteDirectory)) {
            File::makeDirectory($absoluteDirectory, 0755, true);
        }

        $file->move($absoluteDirectory, $filename);

        return $relativeDirectory . '/' . $filename;
    }

    private function deleteFlagImage(?string $flagPath): void
    {
        if (!$flagPath) {
            return;
        }

        $absolutePath = public_path($flagPath);
        if (File::exists($absolutePath)) {
            File::delete($absolutePath);
        }
    }
}
