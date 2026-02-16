<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TransactionType;

class SuperAdminTransactionTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'superadmin']);
    }

    /**
     * Display a listing of global transaction types.
     */
    public function index(Request $request)
    {
        $query = TransactionType::global();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $transactionTypes = $query->latest()->paginate(15);

        return view('superadmin.transaction-types.index', compact('transactionTypes'));
    }

    /**
     * Show the form for creating a new global transaction type.
     */
    public function create()
    {
        return view('superadmin.transaction-types.create');
    }

    /**
     * Store a newly created global transaction type.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:transaction_types,code,NULL,id,deleted_at,NULL'
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['business_unit_id'] = null; // Global type
        $validated['is_active'] = $request->has('is_active') ? true : false;

        TransactionType::create($validated);

        return redirect()->route('superadmin.transaction-types.index')
            ->with('success', __('common.created_successfully'));
    }

    /**
     * Display the specified global transaction type.
     */
    public function show($id)
    {
        $transactionType = TransactionType::global()->findOrFail($id);
        return view('superadmin.transaction-types.show', compact('transactionType'));
    }

    /**
     * Show the form for editing the specified global transaction type.
     */
    public function edit($id)
    {
        $transactionType = TransactionType::global()->findOrFail($id);
        return view('superadmin.transaction-types.edit', compact('transactionType'));
    }

    /**
     * Update the specified global transaction type.
     */
    public function update(Request $request, $id)
    {
        $transactionType = TransactionType::global()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:transaction_types,code,' . $id . ',id,deleted_at,NULL'
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        $transactionType->update($validated);

        return redirect()->route('superadmin.transaction-types.index')
            ->with('success', __('common.updated_successfully'));
    }

    /**
     * Remove the specified global transaction type.
     */
    public function destroy($id)
    {
        $transactionType = TransactionType::global()->findOrFail($id);

        // Check if any businesses are using this transaction type
        if ($transactionType->businessUnits()->count() > 0) {
            return redirect()->route('superadmin.transaction-types.index')
                ->with('error', __('common.cannot_delete_transaction_type_in_use'));
        }

        $transactionType->delete();

        return redirect()->route('superadmin.transaction-types.index')
            ->with('success', __('common.deleted_successfully'));
    }
}
