<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\CommissionConfiguration;
use App\Models\CommissionTier;
use App\Models\TransactionCostConfiguration;
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

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('transactionType', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $commissions = $query->latest()->paginate(15);
        $transactionTypes = $businessUnit->availableTransactionTypes();

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

        $transactionTypes = $businessUnit->availableTransactionTypes();

        $configuredTypes = CommissionConfiguration::where('business_unit_id', $businessUnit->id)
            ->pluck('transaction_type_id')
            ->toArray();

        $availableTypes = $transactionTypes->reject(function($type) use ($configuredTypes) {
            return in_array($type->id, $configuredTypes);
        });

        $costConfigsByType = $this->costConfigsByType($businessUnit);

        return view('config.commissions.create', compact('availableTypes', 'businessUnit', 'costConfigsByType'));
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

        $eligibleIds = $businessUnit->availableTransactionTypes()->pluck('id')->all();

        $validated = $request->validate([
            'transaction_type_id' => [
                'required',
                'integer',
                Rule::in($eligibleIds),
                'unique:commission_configurations,transaction_type_id,NULL,id,business_unit_id,' . $businessUnit->id . ',deleted_at,NULL'
            ],
            'commission_type' => 'required|in:fixed,percentage,tiered,tiered_percentage,tiered_fixed',
            'fixed_amount' => 'nullable|required_if:commission_type,fixed|numeric|min:0',
            'percentage_rate' => 'nullable|required_if:commission_type,percentage|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'tiers' => 'nullable|array',
            'tiers.*.rate' => 'nullable|numeric|min:0|max:100',
            'tiers.*.amount' => 'nullable|numeric|min:0',
        ]);

        $costConfig = $this->getCostConfigForType($businessUnit, (int) $validated['transaction_type_id']);
        $this->assertTieredCommissionAllowed($validated['commission_type'], $costConfig);

        $validated['business_unit_id'] = $businessUnit->id;
        $validated['is_active'] = $request->has('is_active') ? true : false;

        if (!in_array($validated['commission_type'], ['tiered', 'tiered_percentage', 'tiered_fixed'])) {
            unset($validated['fixed_amount']);
            unset($validated['percentage_rate']);
            if ($validated['commission_type'] === 'fixed') {
                $validated['fixed_amount'] = $request->input('fixed_amount');
            } else {
                $validated['percentage_rate'] = $request->input('percentage_rate');
            }
        } else {
            $validated['fixed_amount'] = null;
            $validated['percentage_rate'] = null;
        }

        $tiers = $validated['tiers'] ?? [];
        unset($validated['tiers']);

        $commission = CommissionConfiguration::create($validated);

        if (in_array($validated['commission_type'], ['tiered', 'tiered_percentage', 'tiered_fixed'], true)) {
            $this->syncCommissionTiersFromCost($commission, $costConfig, $tiers, $validated['commission_type']);
        }

        return redirect()->route('business.config.commissions.index')
            ->with('success', __('common.created_successfully'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $commission = CommissionConfiguration::where('business_unit_id', $businessUnit->id)
            ->with(['transactionType', 'tiers'])
            ->findOrFail($id);

        if ($request->has('amount')) {
            $amount = (float) $request->query('amount');
            $costConfig = $this->getCostConfigForType($businessUnit, $commission->transaction_type_id);

            return response()->json([
                'transaction_amount' => $amount,
                'transaction_cost' => $costConfig ? $costConfig->calculateCost($amount) : 0,
                'commission' => $commission->calculateFromTransactionAmount($amount),
            ]);
        }

        $costConfig = $this->getCostConfigForType($businessUnit, $commission->transaction_type_id);

        return view('config.commissions.show', compact('commission', 'businessUnit', 'costConfig'));
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

        $costConfig = $this->getCostConfigForType($businessUnit, $commission->transaction_type_id);
        $costConfigsByType = $this->costConfigsByType($businessUnit);

        return view('config.commissions.edit', compact('commission', 'businessUnit', 'costConfig', 'costConfigsByType'));
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
            'tiers.*.rate' => 'nullable|numeric|min:0|max:100',
            'tiers.*.amount' => 'nullable|numeric|min:0',
        ]);

        $costConfig = $this->getCostConfigForType($businessUnit, $commission->transaction_type_id);
        $this->assertTieredCommissionAllowed($validated['commission_type'], $costConfig);

        $validated['is_active'] = $request->has('is_active') ? true : false;

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

        if (in_array($validated['commission_type'], ['tiered', 'tiered_percentage', 'tiered_fixed'], true)) {
            $commission->tiers()->delete();
            $this->syncCommissionTiersFromCost($commission, $costConfig, $tiers, $validated['commission_type']);
        } else {
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

        // Hard delete so the unique key (business_unit_id, transaction_type_id)
        // can be reused when creating a replacement configuration.
        $commission->forceDelete();

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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function costConfigsByType(BusinessUnit $businessUnit): array
    {
        return TransactionCostConfiguration::where('business_unit_id', $businessUnit->id)
            ->where('is_active', true)
            ->with('tiers')
            ->get()
            ->mapWithKeys(function (TransactionCostConfiguration $config) {
                return [
                    $config->transaction_type_id => [
                        'id' => $config->id,
                        'edit_url' => route('business.config.transaction-costs.edit', $config->id),
                        'cost_type' => $config->cost_type,
                        'is_tiered' => $config->isTiered(),
                        'fixed_amount' => $config->fixed_amount,
                        'percentage_rate' => $config->percentage_rate,
                        'tiers' => $config->tiers->sortBy('order')->values()->map(function ($tier) {
                            return [
                                'min_amount' => $tier->min_amount,
                                'max_amount' => $tier->max_amount,
                                'rate' => $tier->rate,
                                'amount' => $tier->amount,
                                'order' => $tier->order,
                            ];
                        })->all(),
                    ],
                ];
            })
            ->all();
    }

    private function getCostConfigForType(BusinessUnit $businessUnit, int $transactionTypeId): ?TransactionCostConfiguration
    {
        return TransactionCostConfiguration::where('business_unit_id', $businessUnit->id)
            ->where('transaction_type_id', $transactionTypeId)
            ->where('is_active', true)
            ->with('tiers')
            ->first();
    }

    private function assertTieredCommissionAllowed(string $commissionType, ?TransactionCostConfiguration $costConfig): void
    {
        if (!in_array($commissionType, ['tiered', 'tiered_percentage', 'tiered_fixed'], true)) {
            return;
        }

        if (!$costConfig || !$costConfig->isTiered()) {
            throw ValidationException::withMessages([
                'commission_type' => [__('common.tiered_commission_requires_tiered_cost')],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $inputTiers
     */
    private function syncCommissionTiersFromCost(
        CommissionConfiguration $commission,
        TransactionCostConfiguration $costConfig,
        array $inputTiers,
        string $commissionType
    ): void {
        $costTiers = $costConfig->tiers()->orderBy('order')->get();

        if ($costTiers->count() !== count($inputTiers)) {
            throw ValidationException::withMessages([
                'tiers' => [__('common.commission_tier_count_must_match_cost')],
            ]);
        }

        foreach ($costTiers->values() as $index => $costTier) {
            $input = $inputTiers[$index] ?? [];

            if ($commissionType === 'tiered_percentage' && empty($input['rate']) && $input['rate'] !== '0' && $input['rate'] !== 0) {
                throw ValidationException::withMessages([
                    "tiers.{$index}.rate" => [__('common.required')],
                ]);
            }

            if ($commissionType === 'tiered_fixed' && empty($input['amount']) && $input['amount'] !== '0' && $input['amount'] !== 0) {
                throw ValidationException::withMessages([
                    "tiers.{$index}.amount" => [__('common.required')],
                ]);
            }

            CommissionTier::create([
                'commission_configuration_id' => $commission->id,
                'min_amount' => $costTier->min_amount,
                'max_amount' => $costTier->max_amount,
                'order' => $costTier->order,
                'rate' => $commissionType === 'tiered_percentage' ? ($input['rate'] ?? null) : null,
                'amount' => in_array($commissionType, ['tiered_fixed', 'tiered'], true) ? ($input['amount'] ?? null) : null,
            ]);
        }
    }
}
