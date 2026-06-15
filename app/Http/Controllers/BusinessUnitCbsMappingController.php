<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\BusinessUnitCbsTransactionMapping;
use App\Models\TransactionType;
use App\Services\Cbs\CbsContextBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessUnitCbsMappingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'superadmin']);
    }

    public function index(BusinessUnit $businessUnit)
    {
        $this->ensureConfigurableCbs($businessUnit);

        $mappings = $businessUnit->cbsTransactionMappings()
            ->with('transactionType')
            ->orderBy('id')
            ->get();

        return view('superadmin.business-units.cbs-mappings.index', compact('businessUnit', 'mappings'));
    }

    public function create(BusinessUnit $businessUnit)
    {
        $this->ensureConfigurableCbs($businessUnit);

        $transactionTypes = $this->availableTransactionTypes($businessUnit);
        $sourceFields = CbsContextBuilder::sourceFieldOptions();
        $transforms = ['none', 'uppercase', 'lowercase', 'float', 'integer', 'string'];

        return view('superadmin.business-units.cbs-mappings.create', compact(
            'businessUnit',
            'transactionTypes',
            'sourceFields',
            'transforms'
        ));
    }

    public function store(Request $request, BusinessUnit $businessUnit)
    {
        $this->ensureConfigurableCbs($businessUnit);

        $validated = $this->validateMapping($request, $businessUnit);

        BusinessUnitCbsTransactionMapping::create(array_merge($validated, [
            'business_unit_id' => $businessUnit->id,
        ]));

        return redirect()
            ->route('superadmin.business-units.cbs-mappings.index', $businessUnit->id)
            ->with('success', __('common.created_successfully'));
    }

    public function edit(BusinessUnit $businessUnit, BusinessUnitCbsTransactionMapping $mapping)
    {
        $this->ensureConfigurableCbs($businessUnit);
        $this->ensureMappingBelongsToBusinessUnit($businessUnit, $mapping);
        $mapping->load('transactionType');

        $sourceFields = CbsContextBuilder::sourceFieldOptions();
        $transforms = ['none', 'uppercase', 'lowercase', 'float', 'integer', 'string'];

        return view('superadmin.business-units.cbs-mappings.edit', compact(
            'businessUnit',
            'mapping',
            'sourceFields',
            'transforms'
        ));
    }

    public function update(Request $request, BusinessUnit $businessUnit, BusinessUnitCbsTransactionMapping $mapping)
    {
        $this->ensureConfigurableCbs($businessUnit);
        $this->ensureMappingBelongsToBusinessUnit($businessUnit, $mapping);

        $validated = $this->validateMapping($request, $businessUnit, $mapping->id);

        $mapping->update($validated);

        return redirect()
            ->route('superadmin.business-units.cbs-mappings.index', $businessUnit->id)
            ->with('success', __('common.updated_successfully'));
    }

    public function destroy(BusinessUnit $businessUnit, BusinessUnitCbsTransactionMapping $mapping)
    {
        $this->ensureConfigurableCbs($businessUnit);
        $this->ensureMappingBelongsToBusinessUnit($businessUnit, $mapping);

        $mapping->delete();

        return redirect()
            ->route('superadmin.business-units.cbs-mappings.index', $businessUnit->id)
            ->with('success', __('common.deleted_successfully'));
    }

    private function ensureConfigurableCbs(BusinessUnit $businessUnit): void
    {
        $businessUnit->loadMissing('coreBankingSystem');

        if (! $businessUnit->hasConfigurableCbs()) {
            abort(404);
        }
    }

    private function ensureMappingBelongsToBusinessUnit(
        BusinessUnit $businessUnit,
        BusinessUnitCbsTransactionMapping $mapping
    ): void {
        if ((int) $mapping->business_unit_id !== (int) $businessUnit->id) {
            abort(404);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, TransactionType>
     */
    private function availableTransactionTypes(BusinessUnit $businessUnit)
    {
        $mappedIds = $businessUnit->cbsTransactionMappings()->pluck('transaction_type_id');

        return $businessUnit->eligibleCbsTransactionTypes()
            ->reject(fn (TransactionType $type) => $mappedIds->contains($type->id));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMapping(
        Request $request,
        BusinessUnit $businessUnit,
        ?int $ignoreMappingId = null
    ): array {
        $eligibleIds = $businessUnit->eligibleCbsTransactionTypes()->pluck('id')->all();

        $uniqueRule = Rule::unique('business_unit_cbs_transaction_mappings', 'transaction_type_id')
            ->where(fn ($query) => $query->where('business_unit_id', $businessUnit->id));

        if ($ignoreMappingId) {
            $uniqueRule->ignore($ignoreMappingId);
        }

        $validated = $request->validate([
            'transaction_type_id' => ['required', 'integer', Rule::in($eligibleIds), $uniqueRule],
            'http_method' => 'required|in:GET,POST,PUT,PATCH,get,post,put,patch',
            'endpoint_path' => 'required|string|max:255',
            'success_path' => 'nullable|string|max:255',
            'success_value' => 'nullable|string|max:255',
            'error_message_path' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'request_mappings' => 'nullable|array',
            'request_mappings.*.source' => 'nullable|string|max:255',
            'request_mappings.*.target' => 'required_with:request_mappings|string|max:255',
            'request_mappings.*.transform' => 'nullable|string|max:50',
            'request_mappings.*.required' => 'nullable|boolean',
            'request_mappings.*.default' => 'nullable|string|max:255',
            'response_mappings' => 'nullable|array',
            'response_mappings.*.cbs_path' => 'required_with:response_mappings|string|max:255',
            'response_mappings.*.internal_key' => 'required_with:response_mappings|string|max:255',
        ]);

        $validated['http_method'] = strtoupper($validated['http_method']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['request_mappings'] = $this->normalizeRequestMappings($validated['request_mappings'] ?? []);
        $validated['response_mappings'] = $this->normalizeResponseMappings($validated['response_mappings'] ?? []);

        return $validated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRequestMappings(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $target = trim((string) ($row['target'] ?? ''));
            if ($target === '') {
                continue;
            }

            $normalized[] = [
                'source' => trim((string) ($row['source'] ?? '')) ?: null,
                'target' => $target,
                'transform' => ($row['transform'] ?? 'none') === 'none' ? null : ($row['transform'] ?? null),
                'required' => (bool) ($row['required'] ?? false),
                'default' => ($row['default'] ?? '') !== '' ? $row['default'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeResponseMappings(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $cbsPath = trim((string) ($row['cbs_path'] ?? ''));
            $internalKey = trim((string) ($row['internal_key'] ?? ''));
            if ($cbsPath === '' || $internalKey === '') {
                continue;
            }

            $normalized[] = [
                'cbs_path' => $cbsPath,
                'internal_key' => $internalKey,
            ];
        }

        return $normalized;
    }
}
