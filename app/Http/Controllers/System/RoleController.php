<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index()
    {
        return view('system.roles.index', [
            'roles' => Role::with('permissions')->get(),
        ]);
    }

    public function create()
    {
        return view('system.roles.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|unique:roles,name',
            'description' => 'nullable|string',
        ]);

        if (strcasecmp(trim($validated['name']), User::AUDITOR_ROLE) === 0) {
            throw ValidationException::withMessages([
                'name' => ['The canonical Auditor role is managed by the system and cannot be recreated.'],
            ]);
        }

        Role::create($validated);

        return redirect()
            ->route('system.roles.index')
            ->with('success', 'Role created successfully.');
    }

    /**
     * ✅ ADD THIS METHOD
     */
    public function edit(Role $role)
    {
        return view('system.roles.edit', [
            'role' => $role,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|unique:roles,name,'.$role->id,
            'description' => 'nullable|string',
        ]);

        $isReadOnlyAuditor = $role->isReadOnlyAuditor();
        $requestedName = trim($validated['name']);

        if ($isReadOnlyAuditor && $requestedName !== User::AUDITOR_ROLE) {
            throw ValidationException::withMessages([
                'name' => ['The canonical Auditor role cannot be renamed.'],
            ]);
        }

        if (! $isReadOnlyAuditor && strcasecmp($requestedName, User::AUDITOR_ROLE) === 0) {
            throw ValidationException::withMessages([
                'name' => ['The Auditor role name is reserved for the system-managed read-only role.'],
            ]);
        }

        $role->update([
            'name' => $requestedName,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('system.roles.index')
            ->with('success', 'Role updated successfully.');
    }
}
