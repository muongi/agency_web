<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\BusinessUnit;
use App\Models\Permission;
use Illuminate\Support\Str;

class BusinessRoleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'business.access']);
    }

    public function index()
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        $roles = $businessUnit->roles()->with(['permissions', 'users' => function($query) use ($businessUnit) {
            $query->wherePivot('business_unit_id', $businessUnit->id);
        }])->latest()->get();
        
        return view('business.roles.index', compact('roles', 'businessUnit'));
    }

    public function create()
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        // Get all permissions except super admin only permissions
        $superAdminOnlyPermissions = ['view-business-unit', 'edit-business-unit', 'configure-sms', 'load-sms-credits', 'view-all-sms-balances'];
        $permissions = Permission::whereNotIn('slug', $superAdminOnlyPermissions)
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');
        
        return view('business.roles.create', compact('businessUnit', 'permissions'));
    }

    public function store(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // Filter out super admin only permissions
        $superAdminOnlyPermissions = Permission::whereIn('slug', ['view-business-unit', 'edit-business-unit', 'configure-sms', 'load-sms-credits', 'view-all-sms-balances'])->pluck('id')->toArray();
        $filteredPermissions = $request->has('permissions') 
            ? array_diff($request->permissions, $superAdminOnlyPermissions)
            : [];

        $validated['slug'] = Str::slug($validated['name']);
        $validated['business_unit_id'] = $businessUnit->id;

        $role = Role::create($validated);

        if (!empty($filteredPermissions)) {
            $role->permissions()->sync($filteredPermissions);
        }

        return redirect()->route('business.roles.index')
            ->with('success', __('common.created_successfully'));
    }

    public function edit($id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        $role = $businessUnit->roles()->with('permissions')->findOrFail($id);
        // Get all permissions except super admin only permissions
        $superAdminOnlyPermissions = ['view-business-unit', 'edit-business-unit', 'configure-sms', 'load-sms-credits', 'view-all-sms-balances'];
        $permissions = Permission::whereNotIn('slug', $superAdminOnlyPermissions)
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');
        
        return view('business.roles.edit', compact('role', 'businessUnit', 'permissions'));
    }

    public function update(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        $role = $businessUnit->roles()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update($validated);

        // Filter out super admin only permissions
        $superAdminOnlyPermissions = Permission::whereIn('slug', ['view-business-unit', 'edit-business-unit', 'configure-sms', 'load-sms-credits', 'view-all-sms-balances'])->pluck('id')->toArray();
        if ($request->has('permissions')) {
            $filteredPermissions = array_diff($request->permissions, $superAdminOnlyPermissions);
            $role->permissions()->sync($filteredPermissions);
        } else {
            // If no permissions selected, remove all (including super admin ones)
            $role->permissions()->detach();
        }

        return redirect()->route('business.roles.index')
            ->with('success', __('common.updated_successfully'));
    }
}
