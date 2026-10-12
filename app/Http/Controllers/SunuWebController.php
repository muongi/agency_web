<?php

namespace App\Http\Controllers;

use App\Models\InsuranceProduct;
use App\Models\SunuPayIn;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Models\SunuProposal;
use App\Services\CollectorCapService;
use App\Services\ExportService;
use App\Services\SunuReportService;
use App\Services\SunuSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SunuWebController extends Controller
{
    /** Columns of the Phase 0 import CSV (form help text + sample download). */
    public const IMPORT_COLUMNS = [
        'insured_number', 'policy_number', 'product_code', 'last_name', 'first_name', 'phone',
        'premium_amount', 'premium_frequency', 'effective_date', 'sunulife_policy_id',
    ];

    /** Permission needed by each action (role page groups "SUNU …" and "Configuration"). */
    public const PERMISSIONS = [
        'proposalsIndex' => 'view-sunu-proposals',
        'proposalShow' => 'view-sunu-proposals',
        'proposalFile' => 'view-sunu-proposals',
        'proposalAccept' => 'accept-sunu-proposal',
        'proposalInsuredNumber' => 'accept-sunu-proposal',
        'proposalLinkCard' => 'link-sunu-card',
        'policyLinkCard' => 'link-sunu-card',
        'policiesIndex' => 'view-sunu-policies',
        'premiumsIndex' => 'view-sunu-premiums',
        'premiumsExport' => 'export-sunu-premiums',
        'payInsIndex' => 'view-sunu-payins',
        'payInConfirm' => 'validate-sunu-payins',
        'payInReject' => 'validate-sunu-payins',
        'payInStore' => 'validate-sunu-payins',
        'payInSlip' => 'view-sunu-payins',
        'collectorCapDefault' => 'manage-sunu-collector-caps',
        'collectorRelease' => 'manage-sunu-collector-caps',
        'productsIndex' => 'view-sunu-products',
        'productCreate' => 'manage-sunu-products',
        'productStore' => 'manage-sunu-products',
        'productEdit' => 'manage-sunu-products',
        'productUpdate' => 'manage-sunu-products',
        'productToggle' => 'manage-sunu-products',
        'importForm' => 'import-sunu-policies',
        'importSample' => 'import-sunu-policies',
        'importStore' => 'import-sunu-policies',
    ];

    public function __construct(
        protected CollectorCapService $capService,
        protected ExportService $exportService,
        protected SunuSubscriptionService $subscriptions
    ) {
        $this->middleware(['auth', 'business.access']);
        $this->middleware(function (Request $request, $next) {
            $slug = self::PERMISSIONS[$request->route()->getActionMethod()] ?? null;
            $user = $request->user();
            $bu = $user->currentBusinessUnit;
            if ($slug && $bu && ! $user->isSuperAdmin() && ! $user->hasPermission($slug, $bu->id)) {
                abort(403, __('common.unauthorized_access'));
            }

            return $next($request);
        });
    }

    protected function businessUnit()
    {
        return auth()->user()->currentBusinessUnit;
    }

    public function proposalsIndex(Request $request)
    {
        $bu = $this->businessUnit();
        if (! $bu) {
            return redirect()->route('business.dashboard')->with('error', __('common.no_business_unit_assigned'));
        }

        $query = SunuProposal::with(['product', 'agent', 'client', 'policy'])
            ->withFirstPremiumPaid()
            ->where('business_unit_id', $bu->id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if (in_array($request->input('paid'), ['0', '1'], true)) {
            $query->firstPremiumPaid($request->input('paid') === '1');
        }
        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($w) use ($q) {
                $w->where('proposal_number', 'like', $q)
                    ->orWhere('last_name', 'like', $q)
                    ->orWhere('first_names', 'like', $q)
                    ->orWhere('phone', 'like', $q)
                    ->orWhereHas('client', fn ($c) => $c->where('insured_number', 'like', $q))
                    ->orWhereHas('policy', fn ($c) => $c->where('policy_number', 'like', $q));
            });
        }

        return view('business.sunu.proposals.index', [
            'proposals' => $query->paginate(20)->withQueryString(),
            'businessUnit' => $bu,
        ]);
    }

    public function proposalShow(int $id)
    {
        $bu = $this->businessUnit();
        $proposal = SunuProposal::with(['product', 'agent', 'terminal', 'client', 'card', 'policy.cards', 'premiums', 'correctionRequests.events', 'idDocumentsSource'])
            ->where('business_unit_id', $bu->id)
            ->findOrFail($id);

        // Other products of the same customer (same policy number or same client row).
        $otherProducts = collect();
        if ($proposal->client_id) {
            $otherProducts = SunuPolicy::with('product')->where('business_unit_id', $bu->id)
                ->where('client_id', $proposal->client_id)
                ->when($proposal->policy, fn ($q) => $q->whereKeyNot($proposal->policy->id))
                ->get()
                ->map(fn ($p) => ['number' => $p->policy_number, 'product' => $p->product?->localizedName(),
                    'status' => __('common.sunu_status_'.$p->status), 'proposal_id' => $p->proposal_id])
                ->concat(SunuProposal::with('product')->where('business_unit_id', $bu->id)
                    ->where('client_id', $proposal->client_id)->whereKeyNot($proposal->id)->doesntHave('policy')->get()
                    ->map(fn ($p) => ['number' => $p->sunulife_policy_number ?: $p->proposal_number, 'product' => $p->product?->localizedName(),
                        'status' => __('common.sunu_status_'.$p->status), 'proposal_id' => $p->id]));
        }

        return view('business.sunu.proposals.show', compact('proposal', 'bu', 'otherProducts'));
    }

    public function proposalFile(int $id, string $which)
    {
        $bu = $this->businessUnit();
        $proposal = SunuProposal::where('business_unit_id', $bu->id)->findOrFail($id);
        $allowed = ['proposal_image_path', 'id_document_front_path', 'id_document_back_path'];
        if (! in_array($which, $allowed, true)) {
            abort(404);
        }
        $path = $proposal->{$which};
        if (! $path) {
            abort(404);
        }

        foreach (['public', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path);
            }
        }

        abort(404);
    }

    /**
     * Accept: the back office types the policy number (one per product, unique in the BU) and,
     * for a customer without one yet, the INSURED number that groups all their products.
     */
    public function proposalAccept(Request $request, int $id)
    {
        $bu = $this->businessUnit();
        $proposal = SunuProposal::with(['product', 'client', 'policy'])->where('business_unit_id', $bu->id)->findOrFail($id);
        if ($proposal->policy || in_array($proposal->status, [SunuProposal::STATUS_ACCEPTED, SunuProposal::STATUS_CANCELLED], true)) {
            return back()->with('error', __('common.sunu_accept_not_possible'));
        }

        $client = $proposal->client;
        $data = $request->validate([
            'policy_number' => 'required|string|max:64',
            'insured_number' => [$client?->insured_number ? 'nullable' : 'required', 'string', 'max:64'],
        ]);
        $policyNumber = trim($data['policy_number']);
        if ($this->subscriptions->policyNumberTaken($bu->id, $policyNumber)) {
            return back()->withInput()->withErrors(['policy_number' => __('common.sunu_policy_number_taken', ['number' => $policyNumber])]);
        }
        $insured = $client?->insured_number ?: trim((string) ($data['insured_number'] ?? ''));
        if ($error = $this->insuredNumberError($bu->id, $insured, $client?->id)) {
            return back()->withInput()->withErrors(['insured_number' => $error]);
        }

        DB::transaction(function () use ($proposal, $client, $insured, $policyNumber) {
            if ($client && ! $client->insured_number) {
                $client->insured_number = $insured;
                $client->save();
            }
            $this->subscriptions->ensurePolicyFromProposal($proposal, $policyNumber);
            $proposal->status = SunuProposal::STATUS_ACCEPTED;
            $proposal->save();
        });
        Log::info('SUNU_PROPOSAL_ACCEPTED', [
            'proposal_id' => $proposal->id, 'client_id' => $client?->id, 'insured_number' => $insured,
            'policy_number' => $policyNumber, 'by_user_id' => auth()->id(),
        ]);

        return back()->with('success', __('common.sunu_flash_proposal_accepted'));
    }

    /** Set / fix the INSURED number of the proposal's customer (applies to all their products). */
    public function proposalInsuredNumber(Request $request, int $id)
    {
        $bu = $this->businessUnit();
        $proposal = SunuProposal::with('client')->where('business_unit_id', $bu->id)->findOrFail($id);
        $client = $proposal->client;
        abort_unless($client, 404);

        $data = $request->validate(['insured_number' => 'required|string|max:64']);
        $insured = trim($data['insured_number']);
        if ($error = $this->insuredNumberError($bu->id, $insured, $client->id)) {
            return back()->withInput()->withErrors(['insured_number' => $error]);
        }

        $old = $client->insured_number;
        $client->insured_number = $insured;
        $client->save();
        Log::info('SUNU_INSURED_NUMBER_CHANGED', [
            'client_id' => $client->id, 'old' => $old, 'new' => $insured, 'by_user_id' => auth()->id(),
        ]);

        return back()->with('success', __('common.sunu_flash_insured_number_saved'));
    }

    /** INSURED number unique per business unit: never merge two customers silently. */
    protected function insuredNumberError(int $businessUnitId, string $insured, ?int $clientId): ?string
    {
        $owner = $this->subscriptions->clientForInsuredNumber($businessUnitId, $insured);
        if ($owner && $owner->id !== $clientId) {
            return __('common.sunu_insured_number_taken', [
                'number' => $insured, 'name' => trim($owner->last_name.' '.$owner->first_name),
            ]);
        }

        return null;
    }

    public function proposalLinkCard(Request $request, int $id)
    {
        $bu = $this->businessUnit();
        $proposal = SunuProposal::with(['client', 'policy'])->where('business_unit_id', $bu->id)->findOrFail($id);

        return $this->linkCard($request, $bu->id, $proposal);
    }

    public function policyLinkCard(Request $request, int $id)
    {
        $bu = $this->businessUnit();
        $policy = SunuPolicy::with('client')->where('business_unit_id', $bu->id)->findOrFail($id);

        return $this->linkCard($request, $bu->id, $policy);
    }

    protected function linkCard(Request $request, int $buId, SunuProposal|SunuPolicy $subscription)
    {
        $data = $request->validate([
            'card_uid' => ['required', 'string', 'max:64', 'regex:/^[0-9A-Za-z\-]+$/'],
        ]);

        try {
            $card = $this->subscriptions->linkCard($buId, $data['card_uid'], $subscription);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', __('common.sunu_flash_card_link_conflict').' '.$e->getMessage());
        }

        return back()->with('success', __('common.sunu_flash_card_linked', ['uid' => $card->card_serial ?? $card->card_number]));
    }

    public function premiumsIndex(Request $request)
    {
        $bu = $this->businessUnit();
        if (! $bu) {
            return redirect()->route('business.dashboard')->with('error', __('common.no_business_unit_assigned'));
        }

        $query = SunuPremiumCollection::with(['policy.product', 'proposal', 'agent', 'terminal', 'correctionRequests'])
            ->where('business_unit_id', $bu->id)
            ->latest('collected_at');

        $this->filterPremiums($query, $request);

        return view('business.sunu.premiums.index', [
            'premiums' => $query->paginate(20)->withQueryString(),
            'businessUnit' => $bu,
            'agents' => $bu->agents()->orderBy('first_name')->get(),
        ]);
    }

    /** Same columns as the Primes encaissées report, in the user's language (CSV ";" in French). */
    public function premiumsExport(Request $request)
    {
        $bu = $this->businessUnit();
        $query = SunuPremiumCollection::with(['policy.product', 'policy.client', 'proposal.product', 'proposal.client', 'agent', 'terminal'])
            ->where('business_unit_id', $bu->id)
            ->latest('collected_at');
        $this->filterPremiums($query, $request);

        $reports = app(SunuReportService::class);
        $rows = $reports->premiumExportRows($query->get());
        $headers = array_keys($reports->premiumExportRows(collect([new SunuPremiumCollection]))->first());
        $filename = 'sunu_primes_'.now()->format('Ymd_His');

        return $request->input('format') === 'excel'
            ? $this->exportService->exportExcel($rows, $headers, $filename, mb_substr(__('common.sunu_premiums'), 0, 31))
            : $this->exportService->exportCsv($rows, $headers, $filename);
    }

    /** Filters shared by the premiums list and its CSV / Excel export (SUNULIFE processing). */
    protected function filterPremiums($query, Request $request): void
    {
        $query->when($request->input('collection_kind'), fn ($q, $v) => $q->where('collection_kind', $v))
            ->when($request->input('agent_id'), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->input('status') === SunuPremiumCollection::STATUS_VALID, fn ($q) => $q->valid())
            ->when($request->input('status') === SunuPremiumCollection::STATUS_CANCELLED, fn ($q) => $q->where('status', SunuPremiumCollection::STATUS_CANCELLED))
            ->when($request->input('from'), fn ($q, $v) => $q->where('collected_at', '>=', $v.' 00:00:00'))
            ->when($request->input('to'), fn ($q, $v) => $q->where('collected_at', '<=', $v.' 23:59:59'));
    }

    public function policiesIndex(Request $request)
    {
        $bu = $this->businessUnit();
        $query = SunuPolicy::with(['product', 'client', 'cards'])->where('business_unit_id', $bu->id)->latest();
        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($w) use ($q) {
                $w->where('policy_number', 'like', $q)
                    ->orWhereHas('client', fn ($c) => $c->where('insured_number', 'like', $q))
                    ->orWhereHas('cards', fn ($c) => $c->where('card_serial', 'like', $q)->orWhere('card_number', 'like', $q));
            });
        }

        return view('business.sunu.policies.index', [
            'policies' => $query->paginate(20)->withQueryString(),
            'businessUnit' => $bu,
        ]);
    }

    public function productsIndex(Request $request)
    {
        $bu = $this->businessUnit();
        if (! $bu) {
            return redirect()->route('business.dashboard')->with('error', __('common.no_business_unit_assigned'));
        }

        $query = InsuranceProduct::where('business_unit_id', $bu->id)->ordered();

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }
        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', $q)
                    ->orWhere('name_fr', 'like', $q)
                    ->orWhere('name_en', 'like', $q)
                    ->orWhere('visa_number', 'like', $q);
            });
        }

        return view('business.sunu.products.index', [
            'products' => $query->get(),
            'bu' => $bu,
            'categories' => InsuranceProduct::CATEGORIES,
        ]);
    }

    public function productCreate()
    {
        $bu = $this->businessUnit();
        if (! $bu) {
            return redirect()->route('business.dashboard')->with('error', __('common.no_business_unit_assigned'));
        }

        $nextOrder = (int) InsuranceProduct::where('business_unit_id', $bu->id)->max('display_order') + 1;

        return view('business.sunu.products.create', [
            'bu' => $bu,
            'product' => new InsuranceProduct(['display_order' => $nextOrder, 'is_active' => true, 'default_premium_frequency' => 'monthly']),
            'categories' => InsuranceProduct::CATEGORIES,
            'frequencies' => InsuranceProduct::FREQUENCIES,
        ]);
    }

    public function productStore(Request $request)
    {
        $bu = $this->businessUnit();
        if (! $bu) {
            return redirect()->route('business.dashboard')->with('error', __('common.no_business_unit_assigned'));
        }

        $data = $this->validateProduct($request, $bu->id);
        $data['business_unit_id'] = $bu->id;
        $data['family'] = $data['family'] ?? 'micro-insurance';

        $product = InsuranceProduct::create($data);

        return redirect()->route('business.sunu.products.index')
            ->with('success', __('common.sunu_flash_product_created', ['code' => $product->code]));
    }

    public function productEdit($id)
    {
        $bu = $this->businessUnit();
        $product = InsuranceProduct::where('business_unit_id', $bu?->id)->findOrFail($id);

        return view('business.sunu.products.edit', [
            'bu' => $bu,
            'product' => $product,
            'categories' => InsuranceProduct::CATEGORIES,
            'frequencies' => InsuranceProduct::FREQUENCIES,
        ]);
    }

    public function productUpdate(Request $request, $id)
    {
        $bu = $this->businessUnit();
        $product = InsuranceProduct::where('business_unit_id', $bu?->id)->findOrFail($id);

        $product->update($this->validateProduct($request, $bu->id, $product->id));

        return redirect()->route('business.sunu.products.index')
            ->with('success', __('common.sunu_flash_product_updated', ['code' => $product->code]));
    }

    /**
     * Products are never deleted: policies and proposals reference them, so the
     * catalogue is switched on and off instead.
     */
    public function productToggle($id)
    {
        $bu = $this->businessUnit();
        $product = InsuranceProduct::where('business_unit_id', $bu?->id)->findOrFail($id);
        $product->update(['is_active' => ! $product->is_active]);

        return redirect()->route('business.sunu.products.index')
            ->with('success', __($product->is_active ? 'common.sunu_flash_product_activated' : 'common.sunu_flash_product_deactivated', ['code' => $product->code]));
    }

    protected function validateProduct(Request $request, int $businessUnitId, ?int $ignoreId = null): array
    {
        $codeRule = 'required|string|max:64|regex:/^[A-Z0-9_]+$/|unique:insurance_products,code,'
            .($ignoreId ?? 'NULL').',id,business_unit_id,'.$businessUnitId;

        return $request->validate([
            'code' => $codeRule,
            'display_order' => 'nullable|integer|min:0|max:9999',
            'name_fr' => 'required|string|max:191',
            'name_en' => 'nullable|string|max:191',
            'visa_number' => 'nullable|string|max:191',
            'category' => 'nullable|string|in:'.implode(',', InsuranceProduct::CATEGORIES),
            'guarantees_description' => 'nullable|string|max:8000',
            'family' => 'nullable|string|max:64',
            'default_premium_frequency' => 'nullable|string|in:'.implode(',', InsuranceProduct::FREQUENCIES),
            'default_premium_amount' => 'nullable|numeric|min:0|max:99999999',
            'policy_cost_amount' => 'nullable|numeric|min:0|max:99999999',
            'sunulife_product_code' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
        ], [
            'code.regex' => 'Le code doit être en majuscules, chiffres ou underscore (ex: MICRO_QUIETUDE).',
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    public function payInsIndex(Request $request)
    {
        $bu = $this->businessUnit();
        if (! $bu) {
            return redirect()->route('business.dashboard')->with('error', __('common.no_business_unit_assigned'));
        }

        $query = SunuPayIn::with(['agent', 'confirmer', 'recorder'])
            ->where('business_unit_id', $bu->id)
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('agent_id'), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->input('from'), fn ($q, $v) => $q->where('declared_at', '>=', $v.' 00:00:00'))
            ->when($request->input('to'), fn ($q, $v) => $q->where('declared_at', '<=', $v.' 23:59:59'))
            ->latest('declared_at');

        if (in_array($request->input('export'), ['csv', 'excel'], true)) {
            $rows = $this->payInExportRows($query->get());
            $headers = array_keys($this->payInExportRows(collect([new SunuPayIn]))->first());
            $filename = 'sunu_versements_'.now()->format('Ymd_His');

            return $request->input('export') === 'excel'
                ? $this->exportService->exportExcel($rows, $headers, $filename, mb_substr(__('common.sunu_payins_title'), 0, 31))
                : $this->exportService->exportCsv($rows, $headers, $filename);
        }

        $agents = $bu->agents()->orderBy('first_name')->get();
        $collectors = $agents->map(fn ($agent) => [
            'agent' => $agent,
            'status' => $this->capService->status($agent->id),
        ])->sortByDesc(fn ($c) => [(int) $c['status']['is_blocked'], $c['status']['uncollected_amount']])->values();

        return view('business.sunu.payins.index', [
            'payIns' => $query->paginate(20)->withQueryString(),
            'bu' => $bu,
            'agents' => $agents,
            'collectors' => $collectors,
        ]);
    }

    /** Deposit recorded by the office from the bank slip / cash-desk receipt: confirmed at once. */
    public function payInStore(Request $request)
    {
        $bu = $this->businessUnit();
        $data = $request->validate([
            'agent_id' => ['required', 'integer', Rule::exists('agents', 'id')->where('business_unit_id', $bu->id)],
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:'.SunuPayIn::METHOD_BANK.','.SunuPayIn::METHOD_CASH_OFFICE,
            'bank_reference' => 'required_if:method,'.SunuPayIn::METHOD_BANK.'|nullable|string|max:128',
            'bank_name' => 'nullable|string|max:128',
            'bank_account' => 'nullable|string|max:64',
            'deposited_at' => 'required|date|before_or_equal:now',
            'deposit_slip' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'notes' => 'nullable|string|max:1000',
        ]);

        $amount = (float) $data['amount'];
        $payIn = DB::transaction(function () use ($bu, $data, $amount, $request) {
            $payIn = SunuPayIn::create([
                'business_unit_id' => $bu->id,
                'agent_id' => $data['agent_id'],
                'declared_amount' => $amount,
                'method' => $data['method'],
                'bank_reference' => $data['bank_reference'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account' => $data['bank_account'] ?? null,
                'deposited_at' => $data['deposited_at'],
                'declared_at' => now(),
                'status' => SunuPayIn::STATUS_CONFIRMED,
                'source' => SunuPayIn::SOURCE_BO,
                'recorded_by' => auth()->id(),
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
                'confirmed_amount' => $amount,
                'variance' => 0,
                'notes' => $data['notes'] ?? null,
            ]);
            if ($request->hasFile('deposit_slip')) {
                $payIn->deposit_slip_path = $request->file('deposit_slip')->store('sunu/payins/'.$payIn->id, 'local');
                $payIn->save();
            }

            return $payIn;
        });

        $balance = $this->capService->applyPayInConfirm($payIn->agent_id, $amount);
        Log::info('SUNU_PAYIN_RECORDED', [
            'pay_in_id' => $payIn->id, 'agent_id' => $payIn->agent_id, 'amount' => $amount,
            'method' => $payIn->method, 'bank_reference' => $payIn->bank_reference, 'by_user_id' => auth()->id(),
        ]);

        return back()->with('success', $this->payInFlash('common.sunu_flash_payin_recorded', $balance));
    }

    public function payInConfirm(Request $request, int $id)
    {
        $bu = $this->businessUnit();
        $payIn = SunuPayIn::where('business_unit_id', $bu->id)->findOrFail($id);
        if ($payIn->status !== SunuPayIn::STATUS_DECLARED) {
            return back()->with('error', __('common.sunu_flash_payin_already'));
        }

        $needsReference = $payIn->method === SunuPayIn::METHOD_BANK && ! $payIn->bank_reference;
        $data = $request->validate([
            'confirmed_amount' => 'nullable|numeric|min:0',
            'bank_reference' => ($needsReference ? 'required' : 'nullable').'|string|max:128',
            'bank_name' => 'nullable|string|max:128',
            'deposited_at' => 'nullable|date|before_or_equal:now',
            'deposit_slip' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'notes' => 'nullable|string|max:1000',
        ]);

        $confirmed = (float) ($data['confirmed_amount'] ?? $payIn->declared_amount);
        $payIn->status = SunuPayIn::STATUS_CONFIRMED;
        $payIn->confirmed_by = auth()->id();
        $payIn->confirmed_at = now();
        $payIn->confirmed_amount = $confirmed;
        $payIn->variance = $confirmed - (float) $payIn->declared_amount;
        $payIn->bank_reference = $data['bank_reference'] ?? $payIn->bank_reference;
        $payIn->bank_name = $data['bank_name'] ?? $payIn->bank_name;
        $payIn->deposited_at = $data['deposited_at'] ?? $payIn->deposited_at;
        if ($request->hasFile('deposit_slip')) {
            $payIn->deposit_slip_path = $request->file('deposit_slip')->store('sunu/payins/'.$payIn->id, 'local');
        }
        $payIn->notes = trim(($payIn->notes ? $payIn->notes."\n" : '').(string) ($data['notes'] ?? ''));
        $payIn->save();

        $balance = $this->capService->applyPayInConfirm($payIn->agent_id, $confirmed);

        return back()->with('success', $this->payInFlash('common.sunu_flash_payin_confirmed', $balance));
    }

    public function payInReject(Request $request, int $id)
    {
        $bu = $this->businessUnit();
        $payIn = SunuPayIn::where('business_unit_id', $bu->id)->findOrFail($id);
        if ($payIn->status !== SunuPayIn::STATUS_DECLARED) {
            return back()->with('error', __('common.sunu_flash_payin_already'));
        }
        $data = $request->validate(['notes' => 'required|string|max:1000']);

        $payIn->status = SunuPayIn::STATUS_REJECTED;
        $payIn->confirmed_by = auth()->id();
        $payIn->confirmed_at = now();
        $payIn->notes = trim(($payIn->notes ? $payIn->notes."\n" : '').'REJECTED: '.$data['notes']);
        $payIn->save();

        return back()->with('success', __('common.sunu_flash_payin_rejected'));
    }

    public function payInSlip(int $id)
    {
        $bu = $this->businessUnit();
        $payIn = SunuPayIn::where('business_unit_id', $bu->id)->findOrFail($id);
        abort_unless($payIn->deposit_slip_path && Storage::disk('local')->exists($payIn->deposit_slip_path), 404);

        return Storage::disk('local')->response($payIn->deposit_slip_path);
    }

    /** Default collection limit of the business unit (agents without their own limit). */
    public function collectorCapDefault(Request $request)
    {
        $bu = $this->businessUnit();
        $data = $request->validate([
            'cap_amount' => 'nullable|numeric|min:0',
            'warning_pct' => 'required|integer|min:1|max:100',
        ]);

        $bu->sunu_collector_cap_amount = ($data['cap_amount'] ?? null) ?: null;
        $bu->sunu_collector_cap_warning_pct = $data['warning_pct'];
        $bu->save();

        foreach ($bu->agents()->pluck('id') as $agentId) {
            $this->capService->refreshBlock($agentId, auth()->id());
        }
        Log::info('SUNU_COLLECTOR_CAP_CHANGED', [
            'business_unit_id' => $bu->id, 'scope' => 'default', 'cap_amount' => $bu->sunu_collector_cap_amount,
            'warning_pct' => $bu->sunu_collector_cap_warning_pct, 'by_user_id' => auth()->id(),
        ]);

        return back()->with('success', __('common.collector_cap_saved'));
    }

    /** Release a blocked TPE without a full deposit (exception, note kept). */
    public function collectorRelease(Request $request, int $agentId)
    {
        $bu = $this->businessUnit();
        $agent = $bu->agents()->findOrFail($agentId);
        $data = $request->validate(['note' => 'required|string|max:1000']);

        $this->capService->release($agent->id, auth()->id(), $data['note']);

        return back()->with('success', __('common.collector_released', ['name' => $agent->full_name]));
    }

    protected function payInFlash(string $key, $balance): string
    {
        $message = __($key);
        if ($balance->is_blocked) {
            return $message.' '.__('common.collector_still_blocked', [
                'amount' => number_format((float) $balance->uncollected_amount, 0, ',', ' '),
            ]);
        }

        return $message.' '.__('common.collector_tpe_active');
    }

    protected function payInExportRows($payIns)
    {
        return $payIns->map(fn (SunuPayIn $p) => [
            __('common.sunu_payin_reference') => $p->reference,
            __('common.export_col_date') => optional($p->declared_at)->format('Y-m-d H:i'),
            __('common.export_col_agent') => $p->agent?->full_name ?? '',
            __('common.sunu_declared_amount') => (float) $p->declared_amount,
            __('common.sunu_confirmed_amount') => $p->confirmed_amount !== null ? (float) $p->confirmed_amount : '',
            __('common.sunu_method') => $p->method ? __('common.sunu_payin_method_'.$p->method) : '',
            __('common.sunu_payin_bank_reference') => $p->bank_reference ?? '',
            __('common.sunu_payin_bank_name') => $p->bank_name ?? '',
            __('common.sunu_payin_deposited_at') => optional($p->deposited_at)->format('Y-m-d'),
            __('common.sunu_payin_source') => $p->source ? __('common.sunu_payin_source_'.$p->source) : '',
            __('common.export_col_status') => $p->status ? __('common.sunu_status_'.$p->status) : '',
            __('common.sunu_payin_confirmed_by') => $p->confirmer?->name ?? '',
            __('common.notes') => $p->notes ?? '',
        ]);
    }

    public function importForm()
    {
        $bu = $this->businessUnit();

        return view('business.sunu.import', ['businessUnit' => $bu]);
    }

    /** Sample CSV for the Phase 0 import, using this business unit's real product codes. */
    public function importSample(): StreamedResponse
    {
        $bu = $this->businessUnit();
        $codes = InsuranceProduct::where('business_unit_id', $bu?->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->limit(2)
            ->pluck('code')
            ->all();
        $codes += ['MICRO_QUIETUDE', 'MIXTE_PLUS_HORIZON'];
        $date = now()->startOfMonth()->toDateString();

        $rows = [
            ['ASS-000001', 'POL-000001', $codes[0], 'NDAYISHIMIYE', 'Jean', '25779000001', '2000', 'monthly', $date, ''],
            ['ASS-000002', 'POL-000002', $codes[1], 'IRAKOZE', 'Aline', '25779000002', '5000', 'monthly', $date, 'SL-12345'],
            // Same insured person, second product: own policy number.
            ['ASS-000001', 'POL-000003', $codes[1], 'NDAYISHIMIYE', 'Jean', '25779000001', '5000', 'monthly', $date, ''],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
            fputcsv($out, self::IMPORT_COLUMNS);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'sunu_import_sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importStore(Request $request)
    {
        $bu = $this->businessUnit();
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            return back()->with('error', __('common.sunu_flash_import_empty'));
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]); // Excel UTF-8 BOM
        $header = array_map(fn ($h) => Str::snake(trim(strtolower((string) $h))), $header);
        $created = 0;
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count($row) < 2) {
                continue;
            }
            $data = array_combine($header, array_pad($row, count($header), null));
            try {
                $productCode = $data['product_code'] ?? $data['code_produit'] ?? null;
                $product = InsuranceProduct::where('business_unit_id', $bu->id)
                    ->where('code', $productCode)
                    ->first();
                if (! $product) {
                    $errors[] = 'Unknown product: '.($productCode ?? '?');
                    continue;
                }
                $policyNumber = trim((string) ($data['policy_number'] ?? $data['numero_police'] ?? ''));
                if ($policyNumber === '') {
                    $errors[] = __('common.sunu_import_error_no_number');
                    continue;
                }
                $lastName = $data['last_name'] ?? $data['nom'] ?? 'Import';
                $firstName = $data['first_name'] ?? $data['prenom'] ?? $data['first_names'] ?? 'Client';

                $insured = trim((string) ($data['insured_number'] ?? $data['numero_assure'] ?? ''));

                // One policy number = one product contract; the same number on another product is refused.
                $samePolicy = SunuPolicy::with('client')->where('business_unit_id', $bu->id)
                    ->where('policy_number', $policyNumber)->first();
                if ($samePolicy && (int) $samePolicy->insurance_product_id !== (int) $product->id) {
                    $errors[] = __('common.sunu_import_error_policy_other_product', ['number' => $policyNumber]);
                    continue;
                }

                // Customer: by INSURED number (groups all their products), else the policy's customer.
                $client = $insured !== ''
                    ? $this->subscriptions->clientForInsuredNumber($bu->id, $insured)
                    : null;
                $client ??= $samePolicy?->client;
                if ($client && ! SunuSubscriptionService::sameName($lastName, $firstName, $client->last_name, $client->first_name)) {
                    $errors[] = $insured !== '' && $client->insured_number === $insured
                        ? __('common.sunu_import_insured_taken', ['line' => $line, 'number' => $insured, 'name' => trim($client->last_name.' '.$client->first_name)])
                        : __('common.sunu_import_error_other_customer', ['number' => $policyNumber, 'name' => trim($client->last_name.' '.$client->first_name)]);
                    continue;
                }
                if ($client && $insured !== '' && $client->insured_number && $client->insured_number !== $insured) {
                    $errors[] = __('common.sunu_import_error_other_customer', ['number' => $policyNumber, 'name' => trim($client->last_name.' '.$client->first_name)]);
                    continue;
                }
                $client ??= \App\Models\Client::firstOrCreate([
                    'business_unit_id' => $bu->id,
                    'phone_number' => $data['phone'] ?? $data['telephone'] ?? null,
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                ]);
                if ($insured !== '' && ! $client->insured_number) {
                    $client->insured_number = $insured;
                    $client->save();
                }

                SunuPolicy::updateOrCreate(
                    [
                        'business_unit_id' => $bu->id,
                        'policy_number' => $policyNumber,
                        'insurance_product_id' => $product->id,
                    ],
                    [
                        'client_id' => $client->id,
                        'status' => SunuPolicy::STATUS_ACTIVE,
                        'effective_date' => $data['effective_date'] ?? now()->toDateString(),
                        'premium_amount' => $data['premium_amount'] ?? $product->default_premium_amount,
                        'premium_frequency' => $data['premium_frequency'] ?? $product->default_premium_frequency,
                        'source' => 'sunulife',
                        'sunulife_policy_id' => $data['sunulife_policy_id'] ?? null,
                        'synced_at' => now(),
                    ]
                );
                $created++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        fclose($handle);

        return back()->with('success', __('common.sunu_flash_import_done', ['count' => $created]))
            ->with('import_errors', array_slice($errors, 0, 20));
    }
}
