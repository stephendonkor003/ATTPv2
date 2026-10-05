<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PermissionController extends Controller
{
    /**
     * List all permissions
     */
    public function index()
    {
        return view('system.permissions.index', [
            'permissions' => Permission::orderBy('module')->get(),
        ]);
    }

    /**
     * Show assign permissions page for a role
     */
    public function assign(Role $role)
    {
        return view('system.permissions.assign', [
            'role' => $role,
            'permissions' => Permission::orderBy('module')->get(),
        ]);
    }

    /**
     * Store assigned permissions for a role
     */
    public function storeAssign(Request $request, Role $role)
    {
        if ($role->isReadOnlyAuditor()) {
            throw ValidationException::withMessages([
                'permissions' => ['Auditor permissions are system-managed and cannot be changed manually.'],
            ]);
        }

        $request->validate([
            'permissions' => 'array',
        ]);

        $role->permissions()->sync($request->permissions ?? []);

        return redirect()
            ->route('system.permissions.assign', $role->id)
            ->with('success', 'Permissions updated successfully.');
    }
}
