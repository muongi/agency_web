<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClientGroup;
use App\Models\Client;
use App\Models\BusinessUnit;

class ClientGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'business.access']);
    }

    /**
     * Display a listing of client groups.
     */
    public function index()
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $groups = $businessUnit->clientGroups()
            ->withCount('clients')
            ->latest()
            ->get();

        return view('clients.groups.index', compact('groups', 'businessUnit'));
    }

    /**
     * Show the form for creating a new client group.
     */
    public function create()
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $clients = $businessUnit->clients()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('clients.groups.create', compact('businessUnit', 'clients'));
    }

    /**
     * Store a newly created client group.
     */
    public function store(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'exists:clients,id',
        ]);

        // Verify all clients belong to the business unit
        if ($request->has('client_ids') && !empty($request->client_ids)) {
            $validClients = $businessUnit->clients()
                ->whereIn('id', $request->client_ids)
                ->pluck('id')
                ->toArray();
            
            if (count($validClients) !== count($request->client_ids)) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', __('common.invalid_client_selected'));
            }
        }

        $validated['business_unit_id'] = $businessUnit->id;
        $group = ClientGroup::create($validated);

        // Attach clients to the group
        if ($request->has('client_ids') && !empty($request->client_ids)) {
            $group->clients()->sync($request->client_ids);
        }

        return redirect()->route('clients.groups.index')
            ->with('success', __('common.group_created_successfully'));
    }

    /**
     * Display the specified client group.
     */
    public function show($id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $group = $businessUnit->clientGroups()
            ->with('clients')
            ->withCount('clients')
            ->findOrFail($id);

        return view('clients.groups.show', compact('group', 'businessUnit'));
    }

    /**
     * Show the form for editing the specified client group.
     */
    public function edit($id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $group = $businessUnit->clientGroups()
            ->with('clients')
            ->findOrFail($id);

        $clients = $businessUnit->clients()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $selectedClientIds = $group->clients->pluck('id')->toArray();

        return view('clients.groups.edit', compact('group', 'businessUnit', 'clients', 'selectedClientIds'));
    }

    /**
     * Update the specified client group.
     */
    public function update(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $group = $businessUnit->clientGroups()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'exists:clients,id',
        ]);

        // Verify all clients belong to the business unit
        if ($request->has('client_ids') && !empty($request->client_ids)) {
            $validClients = $businessUnit->clients()
                ->whereIn('id', $request->client_ids)
                ->pluck('id')
                ->toArray();
            
            if (count($validClients) !== count($request->client_ids)) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', __('common.invalid_client_selected'));
            }
        }

        $group->update($validated);

        // Sync clients to the group
        if ($request->has('client_ids')) {
            $group->clients()->sync($request->client_ids ?? []);
        } else {
            $group->clients()->detach();
        }

        return redirect()->route('clients.groups.index')
            ->with('success', __('common.group_updated_successfully'));
    }

    /**
     * Remove the specified client group.
     */
    public function destroy($id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $group = $businessUnit->clientGroups()->findOrFail($id);
        $group->delete();

        return redirect()->route('clients.groups.index')
            ->with('success', __('common.group_deleted_successfully'));
    }
}
