<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TransactionCostConfiguration;
use App\Models\TransactionCostTier;
use App\Models\TransactionType;
use App\Models\BusinessUnit;

class TransactionCostConfigurationController extends Controller
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

        $query = TransactionCostConfiguration::where('business_unit_id', $businessUnit->id)
            ->with('transactionType');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('transactionType', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $costConfigs = $query->latest()->paginate(15);
        $transactionTypes = TransactionType::where('business_unit_id', $businessUnit->id)
            ->where('is_active', true)
            ->get();

        return view('config.transaction-costs.index', compact('costConfigs', 'transactionTypes', 'businessUnit'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $transactionTypes = TransactionType::where('business_unit_id', $businessUnit->id)
            ->where('is_active', true)
            ->get();

        // Get transaction types that already have cost configurations
        $configuredTypes = TransactionCostConfiguration::where('business_unit_id', $businessUnit->id)
            ->pluck('transaction_type_id')
            ->toArray();

        $availableTypes = $transactionTypes->reject(function($type) use ($configuredTypes) {
            return in_array($type->id, $configuredTypes);
        });

        return view('config.transaction-costs.create', compact('availableTypes', 'businessUnit'));
    }

    /**
     * Store a newly created resource in storage.
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
                'unique:transaction_cost_configurations,transaction_type_id,NULL,id,business_unit_id,' . $businessUnit->id . ',deleted_at,NULL'
            ],
            'cost_type' => 'required|in:fixed,percentage,tiered_fixed,tiered_percentage',
            'fixed_amount' => 'nullable|required_if:cost_type,fixed|numeric|min:0',
            'percentage_rate' => 'nullable|required_if:cost_type,percentage|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'tiers' => 'nullable|array',
            'tiers.*.min_amount' => 'nullable|numeric|min:0',
            'tiers.*.max_amount' => 'nullable|numeric|min:0|gt:tiers.*.min_amount',
            'tiers.*.rate' => 'nullable|numeric|min:0|max:100',
            'tiers.*.amount' => 'nullable|numeric|min:0',
        ]);

        // Verify transaction type belongs to this business unit
        $transactionType = TransactionType::where('business_unit_id', $businessUnit->id)
            ->findOrFail($validated['transaction_type_id']);

        $validated['business_unit_id'] = $businessUnit->id;
        $validated['is_active'] = $request->has('is_active') ? true : false;

        // Remove cost-type-specific fields if not tiered
        if (!in_array($validated['cost_type'], ['tiered_fixed', 'tiered_percentage'])) {
            unset($validated['fixed_amount']);
            unset($validated['percentage_rate']);
            if ($validated['cost_type'] === 'fixed') {
                $validated['fixed_amount'] = $request->input('fixed_amount');
            } else {
                $validated['percentage_rate'] = $request->input('percentage_rate');
            }
        } else {
            unset($validated['fixed_amount']);
            unset($validated['percentage_rate']);
        }

        $tiers = $validated['tiers'] ?? [];
        unset($validated['tiers']);

        $costConfig = TransactionCostConfiguration::create($validated);

        // Create tiers if cost type is tiered
        if (in_array($request->input('cost_type'), ['tiered_fixed', 'tiered_percentage']) && !empty($tiers)) {
            foreach ($tiers as $index => $tier) {
                if (!empty($tier['min_amount']) || !empty($tier['max_amount']) || !empty($tier['rate']) || !empty($tier['amount'])) {
                    TransactionCostTier::create([
                        'transaction_cost_configuration_id' => $costConfig->id,
                        'min_amount' => $tier['min_amount'] ?? null,
                        'max_amount' => $tier['max_amount'] ?? null,
                        'rate' => $tier['rate'] ?? null,
                        'amount' => $tier['amount'] ?? null,
                        'order' => $index,
                    ]);
                }
            }
        }

        return redirect()->route('business.config.transaction-costs.index')
            ->with('success', __('common.created_successfully'));
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

        $costConfig = TransactionCostConfiguration::where('business_unit_id', $businessUnit->id)
            ->with(['transactionType', 'tiers'])
            ->findOrFail($id);

        return view('config.transaction-costs.show', compact('costConfig', 'businessUnit'));
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

        $costConfig = TransactionCostConfiguration::where('business_unit_id', $businessUnit->id)
            ->with(['transactionType', 'tiers'])
            ->findOrFail($id);

        return view('config.transaction-costs.edit', compact('costConfig', 'businessUnit'));
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

        $costConfig = TransactionCostConfiguration::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'cost_type' => 'required|in:fixed,percentage,tiered_fixed,tiered_percentage',
            'fixed_amount' => 'nullable|required_if:cost_type,fixed|numeric|min:0',
            'percentage_rate' => 'nullable|required_if:cost_type,percentage|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'tiers' => 'nullable|array',
            'tiers.*.min_amount' => 'nullable|numeric|min:0',
            'tiers.*.max_amount' => 'nullable|numeric|min:0|gt:tiers.*.min_amount',
            'tiers.*.rate' => 'nullable|numeric|min:0|max:100',
            'tiers.*.amount' => 'nullable|numeric|min:0',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        // Handle cost type specific fields
        if (!in_array($validated['cost_type'], ['tiered_fixed', 'tiered_percentage'])) {
            if ($validated['cost_type'] === 'fixed') {
                $validated['percentage_rate'] = null;
            } else {
                $validated['fixed_amount'] = null;
            }
        } else {
            $validated['fixed_amount'] = null;
            $validated['percentage_rate'] = null;
        }

        $tiers = $validated['tiers'] ?? [];
        unset($validated['tiers']);

        $costConfig->update($validated);

        // Handle tiers - delete existing and create new ones
        if (in_array($validated['cost_type'], ['tiered_fixed', 'tiered_percentage'])) {
            $costConfig->tiers()->delete();
            
            if (!empty($tiers)) {
                foreach ($tiers as $index => $tier) {
                    if (!empty($tier['min_amount']) || !empty($tier['max_amount']) || !empty($tier['rate']) || !empty($tier['amount'])) {
                        TransactionCostTier::create([
                            'transaction_cost_configuration_id' => $costConfig->id,
                            'min_amount' => $tier['min_amount'] ?? null,
                            'max_amount' => $tier['max_amount'] ?? null,
                            'rate' => $tier['rate'] ?? null,
                            'amount' => $tier['amount'] ?? null,
                            'order' => $index,
                        ]);
                    }
                }
            }
        } else {
            // Delete all tiers if changing from tiered to another type
            $costConfig->tiers()->delete();
        }

        return redirect()->route('business.config.transaction-costs.index')
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

        $costConfig = TransactionCostConfiguration::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        $costConfig->delete();

        return redirect()->route('business.config.transaction-costs.index')
            ->with('success', __('common.deleted_successfully'));
    }

    /**
     * Store a new tier for a transaction cost configuration.
     */
    public function storeTier(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return response()->json(['error' => __('common.no_business_unit_assigned')], 403);
        }

        $costConfig = TransactionCostConfiguration::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        if (!in_array($costConfig->cost_type, ['tiered_fixed', 'tiered_percentage'])) {
            return response()->json(['error' => 'Cost type must be tiered'], 400);
        }

        $validated = $request->validate([
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gt:min_amount',
            'rate' => 'nullable|numeric|min:0|max:100',
            'amount' => 'nullable|numeric|min:0',
            'order' => 'required|integer|min:0',
        ]);

        $tier = $costConfig->tiers()->create($validated);

        return response()->json(['success' => true, 'tier' => $tier]);
    }

    /**
     * Update a tier for a transaction cost configuration.
     */
    public function updateTier(Request $request, $tierId)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return response()->json(['error' => __('common.no_business_unit_assigned')], 403);
        }

        $tier = TransactionCostTier::with('transactionCostConfiguration')
            ->whereHas('transactionCostConfiguration', function($q) use ($businessUnit) {
                $q->where('business_unit_id', $businessUnit->id);
            })
            ->findOrFail($tierId);

        $validated = $request->validate([
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gt:min_amount',
            'rate' => 'nullable|numeric|min:0|max:100',
            'amount' => 'nullable|numeric|min:0',
            'order' => 'required|integer|min:0',
        ]);

        $tier->update($validated);

        return response()->json(['success' => true, 'tier' => $tier]);
    }

    /**
     * Delete a tier for a transaction cost configuration.
     */
    public function destroyTier($tierId)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return response()->json(['error' => __('common.no_business_unit_assigned')], 403);
        }

        $tier = TransactionCostTier::with('transactionCostConfiguration')
            ->whereHas('transactionCostConfiguration', function($q) use ($businessUnit) {
                $q->where('business_unit_id', $businessUnit->id);
            })
            ->findOrFail($tierId);

        $tier->delete();

        return response()->json(['success' => true]);
    }
}

