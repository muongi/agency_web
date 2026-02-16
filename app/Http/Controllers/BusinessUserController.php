<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\BusinessUnit;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class BusinessUserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'business.access']);
    }

    public function index()
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        $users = $businessUnit->users()->with('roles')->get();
        $roles = $businessUnit->roles;
        
        return view('business.users.index', compact('users', 'businessUnit', 'roles'));
    }

    public function create()
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        $roles = $businessUnit->roles;
        
        return view('business.users.create', compact('businessUnit', 'roles'));
    }

    public function store(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'preferred_language' => 'nullable|in:en,fr,pt',
        ]);

        // Verify role belongs to this business unit
        if ($request->filled('role_id')) {
            $role = Role::find($validated['role_id']);
            if ($role && $role->business_unit_id != $businessUnit->id) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Invalid role selected.');
            }
        }

        // Create the user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'preferred_language' => $validated['preferred_language'] ?? 'en',
            'current_business_unit_id' => $businessUnit->id,
        ]);

        // Attach user to this business unit
        $businessUnit->users()->attach($user->id, [
            'role_id' => $validated['role_id'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('business.users.index')
            ->with('success', __('common.created_successfully'));
    }

    public function edit($id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        $user = $businessUnit->users()->where('users.id', $id)->firstOrFail();
        $roles = $businessUnit->roles;
        
        return view('business.users.edit', compact('user', 'businessUnit', 'roles'));
    }

    public function update(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        $user = $businessUnit->users()->where('users.id', $id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'preferred_language' => 'nullable|in:en,fr,pt',
        ]);

        // Verify role belongs to this business unit
        if ($request->filled('role_id')) {
            $role = Role::find($validated['role_id']);
            if ($role && $role->business_unit_id != $businessUnit->id) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Invalid role selected.');
            }
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'preferred_language' => $validated['preferred_language'] ?? $user->preferred_language ?? 'en',
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        // Update role in business unit
        $businessUnit->users()->updateExistingPivot($user->id, [
            'role_id' => $validated['role_id'] ?? null,
        ]);

        return redirect()->route('business.users.index')
            ->with('success', __('common.updated_successfully'));
    }
}
