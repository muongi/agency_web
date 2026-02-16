<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TransactionType;
use App\Models\BusinessUnit;

class TransactionTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'business.access']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Get both assigned global types and business-specific types
        $assignedGlobalTypes = $businessUnit->assignedTransactionTypes()
            ->with('businessUnit')
            ->get();
        
        $businessSpecificTypes = TransactionType::where('business_unit_id', $businessUnit->id)
            ->get();
        
        // Combine and deduplicate (in case a global type was also created as business-specific)
        $allTransactionTypes = $assignedGlobalTypes->merge($businessSpecificTypes)->unique('id');
        
        // Apply search if provided
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $allTransactionTypes = $allTransactionTypes->filter(function($type) use ($search) {
                return stripos($type->name, $search) !== false 
                    || stripos($type->code, $search) !== false
                    || ($type->description && stripos($type->description, $search) !== false);
            });
        }
        
        // Paginate manually
        $currentPage = $request->get('page', 1);
        $perPage = 15;
        $items = $allTransactionTypes->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $transactionTypes = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $allTransactionTypes->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('config.transaction-types.index', compact('transactionTypes', 'businessUnit'));
    }

    /**
     * Show the form for selecting an assigned transaction type.
     */
    public function create()
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Get assigned global transaction types
        $assignedTransactionTypes = $businessUnit->assignedTransactionTypes()
            ->active()
            ->orderBy('name')
            ->get();

        return view('config.transaction-types.create', compact('businessUnit', 'assignedTransactionTypes'));
    }

    /**
     * Verify and confirm selection of an assigned transaction type.
     */
    public function store(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $validated = $request->validate([
            'transaction_type_id' => [
                'required',
                'exists:transaction_types,id',
            ],
        ]);

        // Verify the transaction type is assigned to this business
        $transactionType = TransactionType::findOrFail($validated['transaction_type_id']);
        
        if (!$businessUnit->assignedTransactionTypes()->where('transaction_types.id', $transactionType->id)->exists()) {
            return redirect()->back()
                ->with('error', __('common.transaction_type_not_assigned_to_business'));
        }

        // Transaction type is already assigned and available - no need to create anything
        // Just redirect with success message
        return redirect()->route('business.config.transaction-types.index')
            ->with('success', __('common.transaction_type_available'));
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $transactionType = TransactionType::findOrFail($id);
        
        // Verify access: either business-specific or assigned global type
        $isBusinessSpecific = $transactionType->business_unit_id === $businessUnit->id;
        $isAssignedGlobal = $transactionType->is_global && $businessUnit->assignedTransactionTypes()->where('transaction_types.id', $id)->exists();
        
        if (!$isBusinessSpecific && !$isAssignedGlobal) {
            abort(403, __('common.unauthorized_access'));
        }

        return view('config.transaction-types.show', compact('transactionType', 'businessUnit'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $transactionType = TransactionType::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        return view('config.transaction-types.edit', compact('transactionType', 'businessUnit'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $transactionType = TransactionType::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:transaction_types,code,' . $id . ',id,business_unit_id,' . $businessUnit->id . ',deleted_at,NULL'
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        $transactionType->update($validated);

        return redirect()->route('business.config.transaction-types.index')
            ->with('success', __('common.updated_successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Only allow deleting business-specific types, not global types
        $transactionType = TransactionType::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        $transactionType->delete();

        return redirect()->route('business.config.transaction-types.index')
            ->with('success', __('common.deleted_successfully'));
    }
}
