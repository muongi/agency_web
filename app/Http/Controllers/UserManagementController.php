<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\BusinessUnit;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'superadmin']);
    }

    // ========== USERS MANAGEMENT ==========
    public function indexUsers()
    {
        $users = User::with('businessUnits')->latest()->paginate(20);
        $businessUnits = BusinessUnit::all();
        return view('user-management.users.index', compact('users', 'businessUnits'));
    }

    public function createUser()
    {
        $businessUnits = BusinessUnit::all();
        $roles = Role::all();
        return view('user-management.users.create', compact('businessUnits', 'roles'));
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'business_unit_id' => 'required|exists:business_units,id',
            'role_id' => 'nullable|exists:roles,id',
            'preferred_language' => 'nullable|in:en,fr,pt',
            'is_super_admin' => 'boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_super_admin' => $request->has('is_super_admin') ? true : false,
            'preferred_language' => $validated['preferred_language'] ?? 'en',
            'current_business_unit_id' => $validated['business_unit_id'],
        ]);

        // Attach user to business unit
        $businessUnit = BusinessUnit::find($validated['business_unit_id']);
        $businessUnit->users()->attach($user->id, [
            'role_id' => $validated['role_id'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('superadmin.user-management.users.index')
            ->with('success', __('common.created_successfully'));
    }

    public function editUser($id)
    {
        $user = User::with('businessUnits')->findOrFail($id);
        $businessUnits = BusinessUnit::all();
        $roles = Role::all();
        return view('user-management.users.edit', compact('user', 'businessUnits', 'roles'));
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'business_unit_id' => 'required|exists:business_units,id',
            'role_id' => 'nullable|exists:roles,id',
            'preferred_language' => 'nullable|in:en,fr,pt',
            'is_super_admin' => 'boolean',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_super_admin' => $request->has('is_super_admin') ? true : false,
            'preferred_language' => $validated['preferred_language'] ?? $user->preferred_language ?? 'en',
            'current_business_unit_id' => $validated['business_unit_id'],
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        // Update business unit relationship
        $businessUnit = BusinessUnit::find($validated['business_unit_id']);
        if (!$user->hasBusinessUnit($validated['business_unit_id'])) {
            // Remove from old business units and add to new one
            $user->businessUnits()->detach();
            $businessUnit->users()->attach($user->id, [
                'role_id' => $validated['role_id'] ?? null,
                'is_active' => true,
            ]);
        } else {
            // Update existing relationship
            $user->businessUnits()->updateExistingPivot($validated['business_unit_id'], [
                'role_id' => $validated['role_id'] ?? null,
            ]);
        }

        return redirect()->route('superadmin.user-management.users.index')
            ->with('success', __('common.updated_successfully'));
    }

    // ========== ROLES MANAGEMENT ==========
    public function indexRoles()
    {
        $roles = Role::with('businessUnit')->latest()->paginate(20);
        $businessUnits = BusinessUnit::all();
        return view('user-management.roles.index', compact('roles', 'businessUnits'));
    }

    public function createRole()
    {
        $businessUnits = BusinessUnit::all();
        $permissions = Permission::assignable(true)->orderBy('group')->orderBy('name')->get()->groupBy('group');
        return view('user-management.roles.create', compact('businessUnits', 'permissions'));
    }

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $role = Role::create($validated);

        $role->permissions()->sync(Permission::syncIdsForRole(null, $request->input('permissions', []), true));

        return redirect()->route('superadmin.user-management.roles.index')
            ->with('success', __('common.created_successfully'));
    }

    public function editRole($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $businessUnits = BusinessUnit::all();
        $permissions = Permission::assignable(true)->orderBy('group')->orderBy('name')->get()->groupBy('group');
        return view('user-management.roles.edit', compact('role', 'businessUnits', 'permissions'));
    }

    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update($validated);

        // Hidden (banking / CNSS) permissions the role already has are kept
        $role->permissions()->sync(Permission::syncIdsForRole($role, $request->input('permissions', []), true));

        return redirect()->route('superadmin.user-management.roles.index')
            ->with('success', __('common.updated_successfully'));
    }

    // ========== PERMISSIONS MANAGEMENT ==========
    public function indexPermissions()
    {
        $permissions = Permission::all()->groupBy('group');
        return view('user-management.permissions.index', compact('permissions'));
    }

    public function createPermission()
    {
        return view('user-management.permissions.create');
    }

    public function storePermission(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'group' => 'nullable|string|max:100',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        Permission::create($validated);

        return redirect()->route('superadmin.user-management.permissions.index')
            ->with('success', __('common.created_successfully'));
    }

    public function editPermission($id)
    {
        $permission = Permission::findOrFail($id);
        return view('user-management.permissions.edit', compact('permission'));
    }

    public function updatePermission(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'group' => 'nullable|string|max:100',
        ]);

        $permission->update($validated);

        return redirect()->route('superadmin.user-management.permissions.index')
            ->with('success', __('common.updated_successfully'));
    }
}
