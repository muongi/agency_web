<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CoreBankingSystem;

class CoreBankingSystemController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'superadmin']);
    }

    /**
     * Display a listing of core banking systems
     */
    public function index()
    {
        $cbs = CoreBankingSystem::withCount('businessUnits')
            ->latest()
            ->paginate(20);
        
        return view('superadmin.core-banking-systems.index', compact('cbs'));
    }

    /**
     * Show the form for creating a new core banking system
     */
    public function create()
    {
        return view('superadmin.core-banking-systems.create');
    }

    /**
     * Store a newly created core banking system
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:core_banking_systems,name',
            'code' => 'nullable|string|max:50|unique:core_banking_systems,code',
            'description' => 'nullable|string',
            'base_url' => 'nullable|string|max:255|url',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        CoreBankingSystem::create($validated);

        return redirect()->route('superadmin.core-banking-systems.index')
            ->with('success', __('common.created_successfully'));
    }

    /**
     * Show the form for editing the specified core banking system
     */
    public function edit($id)
    {
        $cbs = CoreBankingSystem::findOrFail($id);
        return view('superadmin.core-banking-systems.edit', compact('cbs'));
    }

    /**
     * Update the specified core banking system
     */
    public function update(Request $request, $id)
    {
        $cbs = CoreBankingSystem::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:core_banking_systems,name,' . $id,
            'code' => 'nullable|string|max:50|unique:core_banking_systems,code,' . $id,
            'description' => 'nullable|string',
            'base_url' => 'nullable|string|max:255|url',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? $cbs->is_active;

        $cbs->update($validated);

        return redirect()->route('superadmin.core-banking-systems.index')
            ->with('success', __('common.updated_successfully'));
    }

    /**
     * Remove the specified core banking system
     */
    public function destroy($id)
    {
        $cbs = CoreBankingSystem::findOrFail($id);

        // Check if any business units are using this CBS
        if ($cbs->businessUnits()->count() > 0) {
            return redirect()->route('superadmin.core-banking-systems.index')
                ->with('error', __('common.cbs_in_use_cannot_delete'));
        }

        $cbs->delete();

        return redirect()->route('superadmin.core-banking-systems.index')
            ->with('success', __('common.deleted_successfully'));
    }
}