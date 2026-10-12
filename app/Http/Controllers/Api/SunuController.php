<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Card;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\SunuPayIn;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Models\SunuProposal;
use App\Models\Terminal;
use App\Exceptions\CollectorCapException;
use App\Services\CollectorCapService;
use App\Services\SunuSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SunuController extends Controller
{
    public function __construct(
        protected CollectorCapService $capService,
        protected SunuSubscriptionService $subscriptions
    ) {
    }

    public function products(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        $products = InsuranceProduct::where('business_unit_id', (int) $request->input('business_unit_id'))
            ->active()
            ->ordered()
            ->get()
            ->map(fn (InsuranceProduct $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'display_order' => (int) $p->display_order,
                'name_fr' => $p->name_fr,
                'name_en' => $p->name_en,
                'visa_number' => $p->visa_number,
                'category' => $p->category,
                'guarantees_description' => $p->guarantees_description,
                'default_premium_frequency' => $p->default_premium_frequency,
                'default_premium_amount' => $p->default_premium_amount !== null ? (float) $p->default_premium_amount : null,
                'policy_cost_amount' => $p->policy_cost_amount !== null ? (float) $p->policy_cost_amount : null,
                'sunulife_product_code' => $p->sunulife_product_code,
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function storeProposal(Request $request): JsonResponse
    {
        // TPE <= 1.2.4 wrote multipart text with DataOutputStream.writeBytes(): accented letters
        // arrive as single Latin-1 bytes (invalid UTF-8) and saving form_data failed. Repair them.
        $request->merge($this->repairLatin1($request->input()));

        // Accept CNSS-shaped nested fields from the existing Telpo enrollment stepper.
        if (! $request->filled('last_name') && is_array($request->input('identification'))) {
            $id = $request->input('identification');
            $request->merge([
                'last_name' => $id['last_name'] ?? null,
                'first_names' => $id['first_names'] ?? null,
                'phone' => $id['phone'] ?? null,
            ]);
        }
        if (! $request->filled('proposal_number')) {
            $other = $request->input('other_information', []);
            $proposalNo = is_array($other)
                ? ($other['existing_cnss_number'] ?? null)
                : null;
            if (! $proposalNo) {
                $proposalNo = 'PROP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
            }
            $request->merge(['proposal_number' => $proposalNo]);
        }
        if (! $request->filled('insurance_product_id') && $request->filled('business_unit_id')) {
            $defaultProduct = InsuranceProduct::where('business_unit_id', (int) $request->input('business_unit_id'))
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');
            if ($defaultProduct) {
                $request->merge(['insurance_product_id' => $defaultProduct]);
            }
        }
        if (! $request->filled('device_serial') && $request->filled('terminal_id') && ! is_numeric($request->input('terminal_id'))) {
            $request->merge(['device_serial' => $request->input('terminal_id')]);
        }
        if (! $request->hasFile('id_document_front') && $request->hasFile('id_document')) {
            $request->files->set('id_document_front', $request->file('id_document'));
        }
        if (! $request->hasFile('proposal_image') && $request->hasFile('birth_certificate')) {
            $request->files->set('proposal_image', $request->file('birth_certificate'));
        }

        Log::info('SUNU proposal multipart files', [
            'proposal_image' => $request->hasFile('proposal_image'),
            'birth_certificate' => $request->hasFile('birth_certificate'),
            'id_document_front' => $request->hasFile('id_document_front'),
            'id_document' => $request->hasFile('id_document'),
            'id_document_back' => $request->hasFile('id_document_back'),
            'proposal_image_size' => $request->hasFile('proposal_image') ? $request->file('proposal_image')->getSize() : null,
            'id_front_size' => $request->hasFile('id_document_front') ? $request->file('id_document_front')->getSize() : null,
            'id_back_size' => $request->hasFile('id_document_back') ? $request->file('id_document_back')->getSize() : null,
        ]);

        $formData = $request->input('form_data');
        if (! is_array($formData)) {
            $formData = [
                'identification' => $request->input('identification', []),
                'other_information' => $request->input('other_information', []),
                'beneficiary' => $request->input('beneficiary', []),
            ];
            $request->merge(['form_data' => $formData]);
        }

        $v = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'device_serial' => 'nullable|string|max:128',
            'agent_id' => 'nullable|integer',
            'insurance_product_id' => 'required|integer|exists:insurance_products,id',
            'proposal_number' => 'required|string|max:128',
            'existing_policy_number' => 'nullable|string|max:128',
            'existing_insured_number' => 'nullable|string|max:64',
            'last_name' => 'required|string|max:128',
            'first_names' => 'required|string|max:128',
            'phone' => 'nullable|string|max:64',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'client_reference' => 'nullable|string|max:128',
            'form_data' => 'nullable',
            'proposal_image' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
            'id_document_front' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
            'id_document_back' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        try {
            $buId = (int) $request->input('business_unit_id');
            $bu = BusinessUnit::find($buId);
            if (! $bu || ! $bu->is_active) {
                return response()->json(['success' => false, 'message' => 'Business unit not found or inactive'], 404);
            }

            $product = InsuranceProduct::where('business_unit_id', $buId)
                ->where('is_active', true)
                ->find($request->input('insurance_product_id'));
            if (! $product) {
                return response()->json(['success' => false, 'message' => 'Insurance product not found'], 422);
            }

            $resolved = $this->resolveAgentTerminal($request, $buId);
            if ($resolved instanceof JsonResponse) {
                return $resolved;
            }

            if ($request->filled('client_reference')) {
                $existing = SunuProposal::where('client_reference', $request->input('client_reference'))->first();
                if ($existing) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Proposal already recorded (idempotent)',
                        'data' => $this->proposalPayload($existing),
                    ]);
                }
            }

            $formData = $request->input('form_data');
            if (is_string($formData)) {
                $decoded = json_decode($formData, true);
                $formData = is_array($decoded) ? $decoded : ['raw' => $formData];
            }
            if (! is_array($formData)) {
                $formData = [];
            }

            // Existing customer, new product (SUNU rule): reuse the client found by INSURED number
            // (or a policy / proposal number of that person). The product gets its own policy number.
            $existingNo = trim((string) ($request->input('existing_insured_number')
                ?: $request->input('existing_policy_number')
                ?: data_get($formData, 'other_information.existing_cnss_number', '')));
            $existing = $existingNo !== ''
                ? $this->subscriptions->resolveExistingNumber($buId, $existingNo)
                : $this->subscriptions->resolveExistingNumber($buId, (string) $request->input('proposal_number'));
            if ($existingNo !== '' && ! $existing) {
                Log::info('SUNU_EXISTING_POLICY_UNKNOWN', ['number' => $existingNo, 'business_unit_id' => $buId]);
            }
            if ($existing && $this->subscriptions->productsHeldByClient($buId, $existing['client']->id)->contains($product->id)) {
                return response()->json([
                    'success' => false,
                    'code' => 'PRODUCT_ALREADY_SUBSCRIBED',
                    'message' => 'This customer already has this product',
                    'insured_number' => $existing['client']->insured_number,
                ], 422);
            }

            $proposal = DB::transaction(function () use ($request, $buId, $product, $resolved, $formData, $existing, $existingNo) {
                $mismatch = null;
                if ($existing) {
                    $client = $existing['client'];
                    $typed = ['last_name' => $request->input('last_name'), 'first_names' => $request->input('first_names'), 'phone' => $request->input('phone')];
                    $onFile = ['last_name' => $client->last_name, 'first_names' => $client->first_name, 'phone' => $client->phone_number];
                    if (! SunuSubscriptionService::sameName($typed['last_name'], $typed['first_names'], $onFile['last_name'], $onFile['first_names'])
                        || ! SunuSubscriptionService::samePhone($typed['phone'], $onFile['phone'])) {
                        $mismatch = ['typed' => $typed, 'on_file' => $onFile, 'number' => $existing['number'],
                            'insured_number' => $client->insured_number];
                    }
                } else {
                    $client = Client::create([
                        'business_unit_id' => $buId,
                        'first_name' => (string) $request->input('first_names'),
                        'last_name' => (string) $request->input('last_name'),
                        'phone_number' => $request->input('phone'),
                    ]);
                }

                $proposal = SunuProposal::create([
                    'business_unit_id' => $buId,
                    'agent_id' => $resolved['agent']->id,
                    'terminal_id' => $resolved['terminal']->id ?? null,
                    'client_id' => $client->id,
                    'insurance_product_id' => $product->id,
                    'proposal_number' => (string) $request->input('proposal_number'),
                    'status' => SunuProposal::STATUS_TRANSMITTED,
                    'subscribed_at' => now(),
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                    'last_name' => $request->input('last_name'),
                    'first_names' => $request->input('first_names'),
                    'phone' => $request->input('phone'),
                    'form_data' => $formData,
                    'client_reference' => $request->input('client_reference') ?: null,
                    'identity_mismatch' => $mismatch,
                ]);

                if ($existing) {
                    Log::info('SUNU_PROPOSAL_LINKED_TO_CUSTOMER', [
                        'proposal_id' => $proposal->id, 'client_id' => $client->id, 'number' => $existing['number'],
                        'insured_number' => $client->insured_number,
                        'product_id' => $product->id,
                    ]);
                }
                if ($mismatch) {
                    Log::warning('SUNU_PROPOSAL_IDENTITY_MISMATCH', ['proposal_id' => $proposal->id, 'client_id' => $client->id, 'number' => $existing['number']]);
                }

                $updates = [];
                if ($request->hasFile('proposal_image')) {
                    $updates['proposal_image_path'] = $request->file('proposal_image')
                        ->store('sunu/proposals/'.$proposal->id, 'public');
                }
                if ($request->hasFile('id_document_front')) {
                    $updates['id_document_front_path'] = $request->file('id_document_front')
                        ->store('sunu/proposals/'.$proposal->id, 'public');
                }
                if ($request->hasFile('id_document_back')) {
                    $updates['id_document_back_path'] = $request->file('id_document_back')
                        ->store('sunu/proposals/'.$proposal->id, 'public');
                }
                // Existing customer, new product: same CNIB, not photographed again.
                if ($existing && ! isset($updates['id_document_front_path']) && ! isset($updates['id_document_back_path'])) {
                    $source = $this->subscriptions->idDocumentsOnFile($buId, $client->id);
                    if ($source && $source->id !== $proposal->id) {
                        $updates['id_document_front_path'] = $source->id_document_front_path;
                        $updates['id_document_back_path'] = $source->id_document_back_path;
                        $updates['id_documents_source_proposal_id'] = $source->id;
                        Log::info('SUNU_ID_DOCUMENTS_REUSED', ['proposal_id' => $proposal->id, 'source_proposal_id' => $source->id, 'client_id' => $client->id]);
                    }
                }
                if ($updates) {
                    $proposal->fill($updates)->save();
                }

                return $proposal->load(['product', 'agent', 'client']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Proposal captured successfully',
                'data' => array_merge($this->proposalPayload($proposal), [
                    // CNSS POS parser compatibility
                    'enrollment_id' => $proposal->id,
                    'voluntary_insured_number' => $proposal->proposal_number,
                    'cnss_registration_affiliation_number' => $proposal->policy?->policy_number
                        ?? ('AFF-'.$proposal->proposal_number),
                    'worker_number' => null,
                    'effective_date' => optional($proposal->subscribed_at)?->toDateString(),
                    'client_id' => $proposal->client_id,
                    'policy_number' => $proposal->policy?->policy_number,
                    'insured_number' => $proposal->client?->insured_number,
                    'linked_existing_customer' => (bool) $existing,
                    'identity_mismatch' => $proposal->identity_mismatch !== null,
                    'id_documents_reused' => $proposal->id_documents_source_proposal_id !== null,
                ]),
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'sunu_proposals_bu_number_product_unique')) {
                return response()->json(['success' => false, 'message' => 'Proposal number already exists'], 422);
            }
            Log::error('SUNU_PROPOSAL_FAILED', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to create proposal'], 500);
        } catch (\Exception $e) {
            Log::error('SUNU_PROPOSAL_FAILED', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create proposal',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function showProposal(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->query(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        $proposal = SunuProposal::with(['product', 'agent', 'client', 'policy'])
            ->where('business_unit_id', (int) $request->query('business_unit_id'))
            ->find($id);

        if (! $proposal) {
            return response()->json(['success' => false, 'message' => 'Proposal not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->proposalPayload($proposal, true)]);
    }

    public function storePremium(Request $request): JsonResponse
    {
        // Map CNSS contribution payload → SUNU premium fields.
        if (! $request->filled('collection_kind')) {
            $request->merge(['collection_kind' => 'recurring_premium']);
        }
        if (! $request->filled('reference') && $request->filled('transaction_reference')) {
            $request->merge(['reference' => $request->input('transaction_reference')]);
        }
        if (! $request->filled('device_serial') && $request->filled('terminal_id') && ! is_numeric($request->input('terminal_id'))) {
            $request->merge(['device_serial' => $request->input('terminal_id')]);
        }
        // If only voluntary/affiliation numbers are sent, try to resolve policy/proposal.
        if (! $request->filled('policy_id') && ! $request->filled('proposal_id')) {
            $voluntary = $request->input('voluntary_insured_number')
                ?: $request->input('voluntary_no')
                ?: $request->input('policy_number')
                ?: $request->input('proposal_number');
            if ($voluntary && $request->filled('business_unit_id')) {
                $buId = (int) $request->input('business_unit_id');
                // One number may cover several products: the product must then be given.
                $productId = $request->input('insurance_product_id')
                    ?: ($request->filled('product_code')
                        ? InsuranceProduct::where('business_unit_id', $buId)->where('code', $request->input('product_code'))->value('id')
                        : null);
                try {
                    $found = $this->subscriptions->resolveForPremium($buId, (string) $voluntary, $productId ? (int) $productId : null);
                } catch (\DomainException $e) {
                    $products = ($this->subscriptions->lastAmbiguous ?? collect())->map(fn ($c) => [
                        'product_id' => $c['product']?->id,
                        'product_code' => $c['product']?->code,
                        'product_name' => $c['product']?->localizedName(),
                        'policy_id' => $c['policy']?->id,
                        'proposal_id' => $c['proposal']?->id,
                    ])->values();
                    Log::warning('SUNU_AMBIGUOUS_POLICY_NUMBER', ['number' => $voluntary, 'business_unit_id' => $buId]);

                    return response()->json([
                        'success' => false,
                        'code' => 'AMBIGUOUS_POLICY_NUMBER',
                        'message' => 'Several products under this policy number: choose the product',
                        'products' => $products,
                    ], 422);
                }
                if ($found && $found['policy']) {
                    $request->merge(['policy_id' => $found['policy']->id]);
                }
                if ($found && $found['proposal']) {
                    $request->merge(['proposal_id' => $found['proposal']->id]);
                    // First payment of a subscription not yet a policy (old TPE sent the default kind).
                    if (! $found['policy'] && $request->input('collection_kind') === SunuPremiumCollection::KIND_RECURRING
                        && ! $found['proposal']->premiums()->valid()->exists()) {
                        $request->merge(['collection_kind' => SunuPremiumCollection::KIND_FIRST_PREMIUM]);
                    }
                }
            }
        }

        $v = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'device_serial' => 'nullable|string|max:128',
            'agent_id' => 'nullable|integer',
            'policy_id' => 'nullable|integer|exists:sunu_policies,id',
            'proposal_id' => 'nullable|integer|exists:sunu_proposals,id',
            'amount' => 'required|numeric|min:0.01',
            'collection_kind' => 'required|in:policy_cost,first_premium,recurring_premium',
            'reference' => 'nullable|string|max:128',
            'reference_period' => 'nullable|string|max:64',
            'payment_frequency' => 'nullable|string|max:32',
            'payment_instrument' => 'nullable|in:cash,momo,bank',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'receipt_number' => 'nullable|string|max:64',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        if (! $request->filled('policy_id') && ! $request->filled('proposal_id')) {
            return response()->json(['success' => false, 'message' => 'policy_id or proposal_id is required'], 422);
        }

        try {
            $buId = (int) $request->input('business_unit_id');
            $resolved = $this->resolveAgentTerminal($request, $buId);
            if ($resolved instanceof JsonResponse) {
                return $resolved;
            }

            $reference = $request->input('reference') ?: $this->makeReference($resolved['terminal']?->serial_number, $resolved['agent']->id);

            $existing = SunuPremiumCollection::where('reference', $reference)->first();
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Premium already recorded (idempotent)',
                    'data' => $this->premiumPayload($existing),
                    'collector' => $this->capService->status($resolved['agent']->id),
                ]);
            }

            if ($denied = $this->checkCollectionPin($request, $resolved['agent'], $reference)) {
                return $denied;
            }

            if ($request->filled('proposal_id') && SunuProposal::where('business_unit_id', $buId)
                ->whereKey($request->input('proposal_id'))->where('status', SunuProposal::STATUS_CANCELLED)->exists()) {
                return response()->json(['success' => false, 'code' => 'PROPOSAL_CANCELLED', 'message' => 'This subscription was cancelled'], 422);
            }

            $amount = (float) $request->input('amount');
            $this->capService->assertCanCollect($resolved['agent']->id, $amount);

            $premium = DB::transaction(function () use ($request, $buId, $resolved, $reference, $amount) {
                $policyId = $request->input('policy_id');
                $proposalId = $request->input('proposal_id');
                $kind = $request->input('collection_kind');

                // A proposal stays a proposal until the office accepts it and types the policy
                // number; premiums collected before that move onto the policy at acceptance.
                if ($proposalId && ! $policyId) {
                    $policyId = SunuProposal::where('business_unit_id', $buId)->findOrFail($proposalId)->policy?->id;
                }

                $premium = SunuPremiumCollection::create([
                    'business_unit_id' => $buId,
                    'policy_id' => $policyId,
                    'proposal_id' => $proposalId,
                    'agent_id' => $resolved['agent']->id,
                    'terminal_id' => $resolved['terminal']->id ?? null,
                    'amount' => $amount,
                    'collection_kind' => $kind,
                    'reference_period' => $request->input('reference_period'),
                    'payment_frequency' => $request->input('payment_frequency'),
                    'payment_instrument' => $request->input('payment_instrument', 'cash'),
                    'reference' => $reference,
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                    'collected_at' => now(),
                    'receipt_number' => $request->input('receipt_number') ?: ('R-'.Str::upper(Str::random(8))),
                    'metadata' => ['source' => 'pos'],
                ]);

                $this->capService->applyCollection($resolved['agent']->id, $amount);

                return $premium;
            });

            return response()->json([
                'success' => true,
                'message' => 'Premium collected',
                'data' => array_merge($this->premiumPayload($premium), [
                    'contribution_id' => $premium->id,
                    'mode' => 'record',
                ]),
                'collector' => $this->capService->status($resolved['agent']->id),
            ], 201);
        } catch (CollectorCapException $e) {
            // 423 (not 409/422) so TPE <= 1.2.11 does not queue the refused premium to the outbox.
            Log::warning('SUNU_CAP_EXCEEDED', [
                'code' => $e->apiCode,
                'agent_id' => $e->status['agent_id'],
                'amount' => (float) $request->input('amount'),
                'uncollected_amount' => $e->status['uncollected_amount'],
                'cap_amount' => $e->status['cap_amount'],
                'offline_replay' => trim((string) $request->input('pin', '')) === '',
                'reference' => $request->input('reference'),
            ]);

            return response()->json([
                'success' => false,
                'code' => $e->apiCode,
                'message' => $e->getMessage(),
                'collector' => $e->status,
            ], 423);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 423);
        } catch (\Exception $e) {
            Log::error('SUNU_PREMIUM_FAILED', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to collect premium',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function policiesByCard(Request $request, string $uid): JsonResponse
    {
        $v = Validator::make(array_merge($request->all(), ['uid' => $uid]), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'uid' => 'required|string',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        $buId = (int) $request->input('business_unit_id');
        $card = Card::where('business_unit_id', $buId)
            ->where(function ($q) use ($uid) {
                $q->where('card_serial', $uid)->orWhere('card_number', $uid);
            })
            ->first();

        if (! $card) {
            return response()->json(['success' => false, 'message' => 'Card not found'], 404);
        }

        $clientId = $card->account?->accountable_type === Client::class
            ? $card->account->accountable_id
            : null;

        $policies = SunuPolicy::with(['product', 'client'])
            ->where('business_unit_id', $buId)
            ->where('status', SunuPolicy::STATUS_ACTIVE)
            ->where(function ($q) use ($card, $clientId) {
                $q->whereHas('cards', fn ($c) => $c->where('cards.id', $card->id));
                if ($clientId) {
                    $q->orWhere('client_id', $clientId);
                }
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'card' => [
                    'id' => $card->id,
                    'uid' => $card->card_serial ?? $card->card_number,
                    'client_id' => $clientId,
                ],
                'policies' => $policies->map(fn (SunuPolicy $p) => $this->policyPayload($p))->values(),
            ],
        ]);
    }

    /**
     * GET /api/v1/sunu/subscriptions?business_unit_id=&card_uid=|number=
     * Everything the customer can pay a premium for: payable policies + pending proposals.
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'card_uid' => 'nullable|string|max:64|required_without:number',
            'number' => 'nullable|string|max:100|required_without:card_uid',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        $buId = (int) $request->input('business_unit_id');
        $card = null;

        if ($request->filled('card_uid')) {
            $card = $this->subscriptions->findCard($buId, (string) $request->input('card_uid'));
            if (! $card) {
                Log::info('SUNU_SUBSCRIPTIONS_CARD_NOT_FOUND', ['business_unit_id' => $buId, 'card_uid' => $request->input('card_uid')]);

                return response()->json(['success' => false, 'message' => 'Card not found'], 404);
            }
            $found = $this->subscriptions->forCard($buId, $card);
        } else {
            $found = $this->subscriptions->forNumber($buId, (string) $request->input('number'));
            if ($found === null) {
                return response()->json(['success' => false, 'message' => 'No subscription found'], 404);
            }
        }

        $items = $found['policies']->map(fn (SunuPolicy $p) => $this->subscriptions->policyPayload($p))
            ->concat($found['proposals']->map(fn (SunuProposal $p) => $this->subscriptions->proposalPayload($p)))
            ->values();

        if ($items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No subscription found'], 404);
        }

        Log::info('SUNU_SUBSCRIPTIONS_LOOKUP', [
            'business_unit_id' => $buId,
            'by' => $card ? 'card' : 'number',
            'count' => $items->count(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $this->subscriptions->customerPayload($found),
                'card' => $card ? ['id' => $card->id, 'uid' => $card->card_serial ?? $card->card_number] : null,
                'subscriptions' => $items,
            ],
        ]);
    }

    /**
     * POST /api/v1/sunu/cards/write — TPE "Écrire la carte": the chip was written with the
     * customer's name / policy number / phone; link that card to all the customer's subscriptions.
     */
    public function writeCard(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'device_serial' => 'required|string|max:64',
            'card_uid' => ['required', 'string', 'max:64', 'regex:/^[0-9A-Fa-f]+$/'],
            'number' => 'required|string|max:100',
            'chip_written' => 'nullable|boolean',
            'card_type' => 'nullable|string|max:32',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        $buId = (int) $request->input('business_unit_id');
        $resolved = $this->resolveAgentTerminal($request, $buId, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $found = $this->subscriptions->forNumber($buId, (string) $request->input('number'));
        if ($found === null || ($found['policies']->isEmpty() && $found['proposals']->isEmpty())) {
            return response()->json(['success' => false, 'message' => 'No subscription found'], 404);
        }

        $decision = $this->cardWriteDecision($resolved['agent'], $buId, (string) $request->input('card_uid'), $found);
        if (! $decision['allowed']) {
            return $this->denyCardWrite($request, $resolved['agent'], $decision);
        }

        try {
            $card = $this->subscriptions->linkCardToAll($buId, (string) $request->input('card_uid'), $found);
        } catch (\InvalidArgumentException $e) {
            Log::warning('SUNU_CARD_WRITE_CONFLICT', [
                'card_uid' => $request->input('card_uid'),
                'number' => $request->input('number'),
                'message' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        if ($request->boolean('chip_written')) {
            $card->written_at = now();
            $card->written_by = 'agent:'.$resolved['agent']->id.' / '.$request->input('device_serial');
            if ($card->status === 'unassigned') {
                $card->status = 'active';
                $card->activated_at = $card->activated_at ?? now();
            }
            $card->save();
        }

        $items = $found['policies']->map(fn (SunuPolicy $p) => $this->subscriptions->policyPayload($p))
            ->concat($found['proposals']->map(fn (SunuProposal $p) => $this->subscriptions->proposalPayload($p)))
            ->values();

        Log::info('SUNU_CARD_WRITE', [
            'business_unit_id' => $buId,
            'card_uid' => $card->card_serial,
            'number' => $request->input('number'),
            'count' => $items->count(),
            'agent_id' => $resolved['agent']->id,
            'is_rewrite' => $decision['is_rewrite'],
            'chip_written' => $request->boolean('chip_written'),
            'card_type' => $request->input('card_type'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Card linked',
            'data' => [
                'card' => [
                    'id' => $card->id,
                    'uid' => $card->card_serial ?? $card->card_number,
                    'status' => $card->status,
                    'written_at' => optional($card->written_at)->toIso8601String(),
                ],
                'customer' => $this->subscriptions->customerPayload($found),
                'subscriptions' => $items,
            ],
        ]);
    }

    public function collectorStatus(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'device_serial' => 'nullable|string',
            'agent_id' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        $resolved = $this->resolveAgentTerminal($request, (int) $request->input('business_unit_id'));
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        return response()->json([
            'success' => true,
            'data' => $this->capService->status($resolved['agent']->id),
        ]);
    }

    public function storePayIn(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'device_serial' => 'nullable|string',
            'agent_id' => 'nullable|integer',
            'declared_amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:bank_deposit,cash_office',
            'bank_reference' => 'nullable|string|max:128',
            'client_reference' => 'nullable|string|max:128',
            'notes' => 'nullable|string|max:1000',
            'deposit_slip' => 'nullable|file|max:10240',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        $buId = (int) $request->input('business_unit_id');
        $resolved = $this->resolveAgentTerminal($request, $buId);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        if ($request->filled('client_reference')) {
            $existing = SunuPayIn::where('client_reference', $request->input('client_reference'))->first();
            if ($existing) {
                return response()->json(['success' => true, 'message' => 'Pay-in already recorded', 'data' => $this->payInPayload($existing)]);
            }
        }

        $payIn = SunuPayIn::create([
            'business_unit_id' => $buId,
            'agent_id' => $resolved['agent']->id,
            'terminal_id' => $resolved['terminal']->id ?? null,
            'declared_amount' => $request->input('declared_amount'),
            'method' => $request->input('method'),
            'bank_reference' => $request->input('bank_reference'),
            'declared_at' => now(),
            'status' => SunuPayIn::STATUS_DECLARED,
            'notes' => $request->input('notes'),
            'client_reference' => $request->input('client_reference'),
            'deposit_slip_path' => $request->hasFile('deposit_slip')
                ? $request->file('deposit_slip')->store('sunu/payins', 'local')
                : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pay-in declared; awaiting back-office confirmation',
            'data' => $this->payInPayload($payIn),
            'collector' => $this->capService->status($resolved['agent']->id),
        ], 201);
    }

    /**
     * GET /api/v1/sunu/pay-ins — the collector's own deposits (declared on the TPE or recorded
     * by the office), so the TPE can reprint the receipt once the office has validated it.
     */
    public function payIns(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'device_serial' => 'required|string|max:128',
            'agent_id' => 'required|integer',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        $buId = (int) $request->input('business_unit_id');
        $resolved = $this->resolveAgentTerminal($request, $buId, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $rows = SunuPayIn::with(['agent', 'terminal', 'confirmer'])
            ->where('business_unit_id', $buId)
            ->where('agent_id', $resolved['agent']->id)
            ->latest('id')->limit(50)->get()
            ->map(fn (SunuPayIn $p) => $this->payInPayload($p));

        return response()->json(['success' => true, 'data' => ['pay_ins' => $rows]]);
    }

    /** What the TPE needs to show and print a deposit receipt (no slip path, no internal notes). */
    protected function payInPayload(SunuPayIn $p): array
    {
        $p->loadMissing(['agent', 'terminal', 'confirmer']);
        $agentName = $p->agent ? trim($p->agent->first_name.' '.$p->agent->second_name) : null;

        return [
            'id' => $p->id,
            'reference' => $p->reference,
            'status' => $p->status,
            'source' => $p->source,
            'declared_amount' => $p->declared_amount !== null ? (float) $p->declared_amount : null,
            'confirmed_amount' => $p->confirmed_amount !== null ? (float) $p->confirmed_amount : null,
            'method' => $p->method,
            'bank_reference' => $p->bank_reference,
            'bank_name' => $p->bank_name,
            'declared_at' => $p->declared_at?->toIso8601String(),
            'deposited_at' => $p->deposited_at?->toIso8601String(),
            'confirmed_at' => $p->status === SunuPayIn::STATUS_CONFIRMED ? $p->confirmed_at?->toIso8601String() : null,
            'confirmed_by_name' => $p->status === SunuPayIn::STATUS_CONFIRMED ? $p->confirmer?->name : null,
            'reject_note' => $p->rejectNote(),
            'agent_name' => $agentName,
            'terminal_serial' => $p->terminal?->serial_number,
            'client_reference' => $p->client_reference,
        ];
    }

    /**
     * @return array{agent: Agent, terminal: ?Terminal}|JsonResponse
     */
    /**
     * GET /api/v1/sunu/cards/write-check — asked by the TPE after the card tap and
     * before touching the chip, so a refused write never overwrites a card.
     */
    public function cardWriteCheck(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'device_serial' => 'required|string|max:64',
            'agent_id' => 'nullable|integer',
            'card_uid' => ['required', 'string', 'max:64', 'regex:/^[0-9A-Fa-f]+$/'],
            'number' => 'nullable|string|max:100',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        $buId = (int) $request->input('business_unit_id');
        $resolved = $this->resolveAgentTerminal($request, $buId, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $found = $request->filled('number')
            ? $this->subscriptions->forNumber($buId, (string) $request->input('number'))
            : null;
        $decision = $this->cardWriteDecision($resolved['agent'], $buId, (string) $request->input('card_uid'), $found);
        if (! $decision['allowed']) {
            return $this->denyCardWrite($request, $resolved['agent'], $decision);
        }

        return response()->json([
            'success' => true,
            'allowed' => true,
            'is_rewrite' => $decision['is_rewrite'],
        ]);
    }

    /**
     * A write is a "rewrite" when the card was already written on a TPE; that needs
     * can_rewrite_cards. A card linked to a different customer is refused for everyone
     * (409, same rule as linkCardToAll), checked here too so the chip is left untouched.
     *
     * @param array{policies: \Illuminate\Support\Collection, proposals: \Illuminate\Support\Collection}|null $found
     * @return array{allowed: bool, is_rewrite: bool, code: ?string}
     */
    protected function cardWriteDecision(Agent $agent, int $businessUnitId, string $uid, ?array $found): array
    {
        $card = $this->subscriptions->findCard($businessUnitId, strtoupper(trim($uid)));
        $isRewrite = $card !== null && $card->written_at !== null;

        if (! $agent->can_write_cards && ! $agent->can_rewrite_cards) {
            return ['allowed' => false, 'is_rewrite' => $isRewrite, 'code' => 'CARD_WRITE_NOT_ALLOWED'];
        }
        if ($isRewrite && ! $agent->can_rewrite_cards) {
            return ['allowed' => false, 'is_rewrite' => true, 'code' => 'CARD_REWRITE_NOT_ALLOWED'];
        }
        if ($card && $found && $this->subscriptions->cardBelongsToOtherCustomer($businessUnitId, $card, $found)) {
            return ['allowed' => false, 'is_rewrite' => $isRewrite, 'code' => 'CARD_OTHER_CUSTOMER'];
        }

        return ['allowed' => true, 'is_rewrite' => $isRewrite, 'code' => null];
    }

    protected function denyCardWrite(Request $request, Agent $agent, array $decision): JsonResponse
    {
        Log::warning('SUNU_CARD_WRITE_DENIED', [
            'agent_id' => $agent->id,
            'device_serial' => $request->input('device_serial'),
            'card_uid' => $request->input('card_uid'),
            'number' => $request->input('number'),
            'reason' => $decision['code'],
            'path' => $request->path(),
        ]);

        $message = match ($decision['code']) {
            'CARD_REWRITE_NOT_ALLOWED' => 'Card already written: rewrite not allowed for this agent',
            'CARD_OTHER_CUSTOMER' => 'Card already linked to another customer.',
            default => 'Agent is not allowed to write cards',
        };

        return response()->json([
            'success' => false,
            'allowed' => false,
            'code' => $decision['code'],
            'is_rewrite' => $decision['is_rewrite'],
            'message' => $message,
        ], $decision['code'] === 'CARD_OTHER_CUSTOMER' ? 409 : 403);
    }

    /**
     * @param bool $requireAssignedAgent when true and agent_id is sent, that agent must be actively
     *                                   assigned to the device_serial terminal (card writing).
     */
    protected function resolveAgentTerminal(Request $request, int $businessUnitId, bool $requireAssignedAgent = false): array|JsonResponse
    {
        if ($request->filled('device_serial')) {
            $serial = trim((string) $request->input('device_serial'));
            $registered = Terminal::where('business_unit_id', $businessUnitId)
                ->orderBy('id')
                ->pluck('serial_number')
                ->all();

            Log::info('SUNU_TERMINAL_LOOKUP', [
                'received_serial' => $serial,
                'business_unit_id' => $businessUnitId,
                'registered_serials' => $registered,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            $terminal = Terminal::where('business_unit_id', $businessUnitId)
                ->where(function ($q) use ($serial) {
                    $q->where('serial_number', $serial)
                        ->orWhereRaw('LOWER(serial_number) = ?', [strtolower($serial)]);
                })
                ->first();

            if (! $terminal) {
                Log::warning('SUNU_TERMINAL_NOT_FOUND', [
                    'received_serial' => $serial,
                    'business_unit_id' => $businessUnitId,
                    'registered_serials' => $registered,
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                    'payload_keys' => array_keys($request->all()),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Terminal '{$serial}' not found for business unit {$businessUnitId}",
                    'received_serial' => $serial,
                    'registered_serials' => $registered,
                ], 422);
            }

            if ($requireAssignedAgent && $request->filled('agent_id')) {
                $agent = $terminal->agents()->where('agents.id', (int) $request->input('agent_id'))->first();
                if (! $agent) {
                    Log::warning('SUNU_AGENT_TERMINAL_MISMATCH', [
                        'received_serial' => $serial,
                        'agent_id' => $request->input('agent_id'),
                        'terminal_id' => $terminal->id,
                        'business_unit_id' => $businessUnitId,
                        'path' => $request->path(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'code' => 'AGENT_NOT_ASSIGNED_TO_TERMINAL',
                        'message' => 'Agent is not assigned to this terminal',
                    ], 403);
                }
            } else {
                $agent = $terminal->agents()->first();
            }
            if (! $agent) {
                Log::warning('SUNU_TERMINAL_NO_AGENT', [
                    'received_serial' => $serial,
                    'terminal_id' => $terminal->id,
                    'business_unit_id' => $businessUnitId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Terminal '{$serial}' has no active agent assignment",
                    'received_serial' => $serial,
                ], 422);
            }

            Log::info('SUNU_TERMINAL_RESOLVED', [
                'received_serial' => $serial,
                'terminal_id' => $terminal->id,
                'agent_id' => $agent->id,
                'business_unit_id' => $businessUnitId,
            ]);

            return ['agent' => $agent, 'terminal' => $terminal];
        }

        if ($request->filled('agent_id')) {
            $agent = Agent::where('business_unit_id', $businessUnitId)->find($request->input('agent_id'));
            if (! $agent) {
                return response()->json(['success' => false, 'message' => 'Agent not found'], 422);
            }

            return ['agent' => $agent, 'terminal' => $agent->terminals()->first()];
        }

        Log::warning('SUNU_TERMINAL_MISSING_SERIAL', [
            'business_unit_id' => $businessUnitId,
            'path' => $request->path(),
            'payload_keys' => array_keys($request->all()),
            'ip' => $request->ip(),
        ]);

        return response()->json(['success' => false, 'message' => 'device_serial or agent_id is required'], 422);
    }

    protected function makeReference(?string $serial, int $agentId): string
    {
        return implode('-', [
            'SUNU',
            $serial ?: 'NOSERIAL',
            $agentId,
            now()->format('YmdHis'),
            Str::upper(Str::random(4)),
        ]);
    }

    protected function proposalPayload(SunuProposal $p, bool $full = false): array
    {
        $data = [
            'id' => $p->id,
            'proposal_number' => $p->proposal_number,
            'status' => $p->status,
            'last_name' => $p->last_name,
            'first_names' => $p->first_names,
            'phone' => $p->phone,
            'latitude' => $p->latitude,
            'longitude' => $p->longitude,
            'insurance_product_id' => $p->insurance_product_id,
            'product_code' => $p->product?->code,
            'product_name' => $p->product?->localizedName(),
            'product_name_fr' => $p->product?->name_fr,
            'product_name_en' => $p->product?->name_en,
            'client_id' => $p->client_id,
            'agent_id' => $p->agent_id,
            'agent_name' => $p->agent?->full_name,
            'terminal_id' => $p->terminal_id,
            'policy_id' => $p->policy?->id,
            'client_reference' => $p->client_reference,
            'has_proposal_image' => (bool) $p->proposal_image_path,
            'has_id_front' => (bool) $p->id_document_front_path,
            'has_id_back' => (bool) $p->id_document_back_path,
            'subscribed_at' => $p->subscribed_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(),
        ];
        if ($full) {
            $data['form_data'] = $p->form_data;
        }

        return $data;
    }

    protected function premiumPayload(SunuPremiumCollection $c): array
    {
        return [
            'id' => $c->id,
            'reference' => $c->reference,
            'amount' => (float) $c->amount,
            'collection_kind' => $c->collection_kind,
            'policy_id' => $c->policy_id,
            'proposal_id' => $c->proposal_id,
            'receipt_number' => $c->receipt_number,
            'payment_instrument' => $c->payment_instrument,
            'collected_at' => $c->collected_at?->toIso8601String(),
        ];
    }

    protected function policyPayload(SunuPolicy $p): array
    {
        return [
            'id' => $p->id,
            'policy_number' => $p->policy_number,
            'status' => $p->status,
            'premium_amount' => (float) $p->premium_amount,
            'premium_frequency' => $p->premium_frequency,
            'next_due_date' => optional($p->next_due_date)?->toDateString(),
            'product_code' => $p->product?->code,
            'product_name' => $p->product?->localizedName(),
            'product_name_fr' => $p->product?->name_fr,
            'product_name_en' => $p->product?->name_en,
            'client_name' => trim(($p->client?->last_name ?? '').' '.($p->client?->first_name ?? '')),
        ];
    }

    protected function failValidation($v): JsonResponse
    {
        Log::warning('SUNU_VALIDATION_FAILED', [
            'path' => request()->path(),
            'errors' => $v->errors()->toArray(),
            'payload_keys' => array_keys(request()->all()),
            'files' => array_keys(request()->allFiles()),
            'content_type' => request()->header('Content-Type'),
            'content_length' => request()->header('Content-Length'),
            'transfer_encoding' => request()->header('Transfer-Encoding'),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $v->errors(),
        ], 422);
    }

    /**
     * Collection PIN on premiums. Wrong PIN → 401, locked → 423, nothing saved.
     * No PIN in the request = offline outbox replay (the TPE strips the PIN before queuing):
     * accepted and logged, unless the agent is locked or has unresolved wrong tries.
     * Agents without a PIN yet are not checked (switchover).
     */
    protected function checkCollectionPin(Request $request, Agent $agent, string $reference): ?JsonResponse
    {
        if (! $agent->hasCollectionPin()) {
            return null;
        }

        $pin = trim((string) $request->input('pin', ''));
        if ($pin === '') {
            // TPE <= 1.2.5 queued a premium refused for a wrong PIN and replays it without PIN:
            // not accepted while the agent is locked or has unresolved wrong tries.
            if ($agent->isPinLocked()) {
                Log::warning('SUNU_PIN_LOCKED', ['agent_id' => $agent->id, 'reference' => $reference, 'pin' => 'absent']);

                return response()->json(['success' => false, 'code' => 'PIN_LOCKED', 'message' => 'PIN locked. Ask your supervisor to reset it.'], 423);
            }
            if ($agent->pin_failed_attempts > 0) {
                Log::warning('SUNU_PIN_REQUIRED', ['agent_id' => $agent->id, 'reference' => $reference, 'attempts' => $agent->pin_failed_attempts]);

                return response()->json(['success' => false, 'code' => 'PIN_REQUIRED', 'message' => 'PIN required', 'attempts_left' => $agent->pinAttemptsLeft()], 401);
            }
            Log::info('SUNU_PIN_ABSENT', ['agent_id' => $agent->id, 'reference' => $reference]);

            return null;
        }

        $result = $agent->checkCollectionPin($pin);
        if ($result === 'ok') {
            return null;
        }

        if ($result === 'locked') {
            Log::warning('SUNU_PIN_LOCKED', ['agent_id' => $agent->id, 'reference' => $reference]);

            return response()->json([
                'success' => false,
                'code' => 'PIN_LOCKED',
                'message' => 'PIN locked. Ask your supervisor to reset it.',
            ], 423);
        }

        Log::warning('SUNU_PIN_FAILED', ['agent_id' => $agent->id, 'attempts' => $agent->pin_failed_attempts, 'reference' => $reference]);

        return response()->json([
            'success' => false,
            'code' => 'INVALID_PIN',
            'message' => 'Invalid PIN',
            'attempts_left' => $agent->pinAttemptsLeft(),
        ], 401);
    }

    /**
     * Recursively re-encode strings that are not valid UTF-8 from ISO-8859-1.
     */
    protected function repairLatin1($value)
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->repairLatin1($v), $value);
        }
        if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
            Log::info('SUNU_LATIN1_REPAIRED', ['sample' => mb_substr(mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1'), 0, 40)]);

            return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }

        return $value;
    }
}
