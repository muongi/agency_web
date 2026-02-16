<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CommissionConfiguration;
use App\Models\CommissionTier;
use App\Models\TransactionType;
use App\Models\BusinessUnit;

class CommissionConfigurationController extends Controller
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

        $query = CommissionConfiguration::where('business_unit_id', $businessUnit->id)
            ->with('transactionType');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('transactionType', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $commissions = $query->latest()->paginate(15);
        $transactionTypes = TransactionType::where('business_unit_id', $businessUnit->id)
            ->where('is_active', true)
            ->get();

        return view('config.commissions.index', compact('commissions', 'transactionTypes', 'businessUnit'));
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

        // Get transaction types that already have commission configurations
        $configuredTypes = CommissionConfiguration::where('business_unit_id', $businessUnit->id)
            ->pluck('transaction_type_id')
            ->toArray();

        $availableTypes = $transactionTypes->reject(function($type) use ($configuredTypes) {
            return in_array($type->id, $configuredTypes);
        });

        return view('config.commissions.create', compact('availableTypes', 'businessUnit'));
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
                'unique:commission_configurations,transaction_type_id,NULL,id,business_unit_id,' . $businessUnit->id . ',deleted_at,NULL'
            ],
            'commission_type' => 'required|in:fixed,percentage,tiered,tiered_percentage,tiered_fixed',
            'fixed_amount' => 'nullable|required_if:commission_type,fixed|numeric|min:0',
            'percentage_rate' => 'nullable|required_if:commission_type,percentage|numeric|min:0|max:100',
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

        // Remove tier-specific fields if not tiered
        if (!in_array($validated['commission_type'], ['tiered', 'tiered_percentage', 'tiered_fixed'])) {
            unset($validated['fixed_amount']);
            unset($validated['percentage_rate']);
            if ($validated['commission_type'] === 'fixed') {
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

        $commission = CommissionConfiguration::create($validated);

        // Create tiers if commission type is tiered
        if (in_array($request->input('commission_type'), ['tiered', 'tiered_percentage', 'tiered_fixed']) && !empty($tiers)) {
            foreach ($tiers as $index => $tier) {
                if (!empty($tier['min_amount']) || !empty($tier['max_amount']) || !empty($tier['rate']) || !empty($tier['amount'])) {
                    CommissionTier::create([
                        'commission_configuration_id' => $commission->id,
                        'min_amount' => $tier['min_amount'] ?? null,
                        'max_amount' => $tier['max_amount'] ?? null,
                        'rate' => $tier['rate'] ?? null,
                        'amount' => $tier['amount'] ?? null,
                        'order' => $index,
                    ]);
                }
            }
        }

        return redirect()->route('business.config.commissions.index')
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

        $commission = CommissionConfiguration::where('business_unit_id', $businessUnit->id)
            ->with(['transactionType', 'tiers'])
            ->findOrFail($id);

        return view('config.commissions.show', compact('commission', 'businessUnit'));
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

        $commission = CommissionConfiguration::where('business_unit_id', $businessUnit->id)
            ->with(['transactionType', 'tiers'])
            ->findOrFail($id);

        return view('config.commissions.edit', compact('commission', 'businessUnit'));
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

        $commission = CommissionConfiguration::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'commission_type' => 'required|in:fixed,percentage,tiered,tiered_percentage,tiered_fixed',
            'fixed_amount' => 'nullable|required_if:commission_type,fixed|numeric|min:0',
            'percentage_rate' => 'nullable|required_if:commission_type,percentage|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'tiers' => 'nullable|array',
            'tiers.*.min_amount' => 'nullable|numeric|min:0',
            'tiers.*.max_amount' => 'nullable|numeric|min:0|gt:tiers.*.min_amount',
            'tiers.*.rate' => 'nullable|numeric|min:0|max:100',
            'tiers.*.amount' => 'nullable|numeric|min:0',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        // Handle commission type specific fields
        if (!in_array($validated['commission_type'], ['tiered', 'tiered_percentage', 'tiered_fixed'])) {
            if ($validated['commission_type'] === 'fixed') {
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

        $commission->update($validated);

        // Handle tiers - delete existing and create new ones
        if (in_array($validated['commission_type'], ['tiered', 'tiered_percentage', 'tiered_fixed'])) {
            $commission->tiers()->delete();
            
            if (!empty($tiers)) {
                foreach ($tiers as $index => $tier) {
                    if (!empty($tier['min_amount']) || !empty($tier['max_amount']) || !empty($tier['rate']) || !empty($tier['amount'])) {
                        CommissionTier::create([
                            'commission_configuration_id' => $commission->id,
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
            $commission->tiers()->delete();
        }

        return redirect()->route('business.config.commissions.index')
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

        $commission = CommissionConfiguration::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        $commission->delete();

        return redirect()->route('business.config.commissions.index')
            ->with('success', __('common.deleted_successfully'));
    }

    /**
     * Store a new tier for a commission configuration.
     */
    public function storeTier(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return response()->json(['error' => __('common.no_business_unit_assigned')], 403);
        }

        $commission = CommissionConfiguration::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        if (!in_array($commission->commission_type, ['tiered', 'tiered_percentage', 'tiered_fixed'])) {
            return response()->json(['error' => 'Commission type must be tiered'], 400);
        }

        $validated = $request->validate([
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gt:min_amount',
            'rate' => 'nullable|numeric|min:0|max:100',
            'amount' => 'nullable|numeric|min:0',
            'order' => 'required|integer|min:0',
        ]);

        $tier = $commission->tiers()->create($validated);

        return response()->json(['success' => true, 'tier' => $tier]);
    }

    /**
     * Update a tier for a commission configuration.
     */
    public function updateTier(Request $request, $tierId)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return response()->json(['error' => __('common.no_business_unit_assigned')], 403);
        }

        $tier = CommissionTier::with('commissionConfiguration')
            ->whereHas('commissionConfiguration', function($q) use ($businessUnit) {
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
     * Delete a tier for a commission configuration.
     */
    public function destroyTier($tierId)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return response()->json(['error' => __('common.no_business_unit_assigned')], 403);
        }

        $tier = CommissionTier::with('commissionConfiguration')
            ->whereHas('commissionConfiguration', function($q) use ($businessUnit) {
                $q->where('business_unit_id', $businessUnit->id);
            })
            ->findOrFail($tierId);

        $tier->delete();

        return response()->json(['success' => true]);
    }
}
