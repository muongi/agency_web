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

    /** French headers of the Phase 0 import (same order as IMPORT_COLUMNS). SUNU works in French. */
    public const IMPORT_COLUMNS_FR = [
        'numero_assure', 'numero_police', 'code_produit', 'nom', 'prenom', 'telephone',
        'montant_prime', 'periodicite', 'date_effet', 'id_police_sunulife',
    ];

    /** Extra spellings accepted for the Phase 0 headers (after accents / spaces are normalised). */
    public const IMPORT_HEADER_ALIASES = [
        'numero_assure' => 'insured_number', 'no_assure' => 'insured_number', 'n_assure' => 'insured_number',
        'numero_police' => 'policy_number', 'no_police' => 'policy_number', 'n_police' => 'policy_number',
        'code_produit' => 'product_code', 'nom' => 'last_name', 'prenom' => 'first_name', 'prenoms' => 'first_name', 'prenom_s' => 'first_name',
        'numero_de_police' => 'policy_number', 'numero_d_assure' => 'insured_number', 'code_du_produit' => 'product_code',
        'montant_de_la_prime' => 'premium_amount', 'date_de_effet' => 'effective_date',
        'first_names' => 'first_name', 'telephone' => 'phone', 'tel' => 'phone',
        'montant_prime' => 'premium_amount', 'prime' => 'premium_amount', 'montant' => 'premium_amount',
        'periodicite' => 'premium_frequency', 'frequence' => 'premium_frequency',
        'date_effet' => 'effective_date', 'date_d_effet' => 'effective_date',
        'id_police_sunulife' => 'sunulife_policy_id', 'id_sunulife' => 'sunulife_policy_id',
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
        'productImport' => 'manage-sunu-products',
        'productImportSample' => 'manage-sunu-products',
        'productsExport' => 'view-sunu-products',
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

        return view('business.sunu.products.index', [
            'products' => $this->productsQuery($request, $bu->id)->get(),
            'bu' => $bu,
            'categories' => InsuranceProduct::CATEGORIES,
        ]);
    }

    /** Products list filters (q, category, status), shared by the list and its export. */
    protected function productsQuery(Request $request, int $businessUnitId)
    {
        $query = InsuranceProduct::where('business_unit_id', $businessUnitId)->ordered();

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
                    ->orWhere('sunulife_product_code', 'like', $q)
                    ->orWhere('name_fr', 'like', $q)
                    ->orWhere('name_en', 'like', $q)
                    ->orWhere('visa_number', 'like', $q);
            });
        }

        return $query;
    }

    /**
     * Product list as CSV / Excel in the product import format (French headers and frequencies,
     * codes exactly as stored), so it can be edited and imported back.
     */
    public function productsExport(Request $request)
    {
        $bu = $this->businessUnit();
        if (! $bu) {
            return redirect()->route('business.dashboard')->with('error', __('common.no_business_unit_assigned'));
        }
        $amount = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $rows = $this->productsQuery($request, $bu->id)->get()->map(fn (InsuranceProduct $p) => array_combine(
            self::PRODUCT_IMPORT_COLUMNS_FR,
            [
                (string) $p->code, (string) $p->sunulife_product_code, (string) $p->name_fr, (string) $p->name_en,
                (string) $p->category, self::FREQUENCY_FR[$p->default_premium_frequency] ?? (string) $p->default_premium_frequency,
                $amount($p->default_premium_amount), $amount($p->policy_cost_amount), (string) $p->visa_number,
                (string) $p->display_order, (string) $p->family, $p->is_active ? '1' : '0',
            ]
        ))->all();
        $filename = 'sunu_produits_'.now()->format('Ymd_His');

        return $request->input('format') === 'excel'
            ? $this->exportService->exportExcel($rows, self::PRODUCT_IMPORT_COLUMNS_FR, $filename, 'Produits')
            : $this->exportService->exportCsv($rows, self::PRODUCT_IMPORT_COLUMNS_FR, $filename);
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
        $codeRule = 'required|string|max:64|regex:'.self::PRODUCT_CODE_REGEX.'|unique:insurance_products,code,'
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
            'code.regex' => 'Le code doit être en majuscules, chiffres, _ . - (ex: CPQOT2., DCE-EXAZXX., MICRO_QUIETUDE).',
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
            ['ASS-000001', 'POL-000001', $codes[0], 'NDAYISHIMIYE', 'Jean', '25779000001', '2000', 'Mensuel', $date, ''],
            ['ASS-000002', 'POL-000002', $codes[1], 'IRAKOZE', 'Aline', '25779000002', '5000', 'Annuel', $date, 'SL-12345'],
            // Same insured person, second product: own policy number.
            ['ASS-000001', 'POL-000003', $codes[1], 'NDAYISHIMIYE', 'Jean', '25779000001', '5000', 'Unique', $date, ''],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
            fputcsv($out, self::IMPORT_COLUMNS_FR);
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

        self::forgetProductCache();
        [$handle, $header, $delimiter] = self::openCsv($request->file('file')->getRealPath(), self::IMPORT_HEADER_ALIASES);
        if (! $header) {
            return back()->with('error', __('common.sunu_flash_import_empty'));
        }
        $created = 0;
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if (count($row) < 2) {
                continue;
            }
            $data = array_combine($header, array_slice(array_pad($row, count($header), null), 0, count($header)));
            try {
                $productCode = trim((string) ($data['product_code'] ?? $data['code_produit'] ?? ''));
                $product = $this->findProductForImport($bu->id, $productCode);
                if (! $product) {
                    $errors[] = __('common.sunu_import_error_unknown_product', ['line' => $line, 'code' => $productCode ?: '?']);
                    continue;
                }
                $rawFrequency = $data['premium_frequency'] ?? $data['periodicite'] ?? null;
                $frequency = self::normalizeFrequency($rawFrequency);
                if ($frequency === false) {
                    $errors[] = __('common.sunu_import_error_frequency', ['line' => $line, 'value' => $rawFrequency]);
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
                        'effective_date' => ($data['effective_date'] ?? '') !== '' ? $data['effective_date'] : now()->toDateString(),
                        'premium_amount' => ($data['premium_amount'] ?? '') !== '' ? $data['premium_amount'] : $product->default_premium_amount,
                        'premium_frequency' => $frequency ?? $product->default_premium_frequency,
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

    /** Columns of the product catalogue import CSV (form help text + sample download). */
    public const PRODUCT_IMPORT_COLUMNS = [
        'code', 'sunulife_product_code', 'name_fr', 'name_en', 'category', 'default_premium_frequency',
        'default_premium_amount', 'policy_cost_amount', 'visa_number', 'display_order', 'family', 'is_active',
    ];

    /**
     * Product codes are kept exactly as SUNU's core system (SUNULIFE) exports them, dot included
     * (CPQOT2., DCE-EXAZXX.). Uppercase letters, digits, _ . - only (2026-11-11).
     */
    public const PRODUCT_CODE_REGEX = '/^[A-Z0-9_.\-]+$/';

    /** BO frequency → French word written in exports / samples (normalizeFrequency reads it back). */
    public const FREQUENCY_FR = [
        'daily' => 'Journalier', 'weekly' => 'Hebdomadaire', 'monthly' => 'Mensuel',
        'quarterly' => 'Trimestriel', 'semiannual' => 'Semestriel', 'annual' => 'Annuel',
    ];

    /** French headers of the product import (same order as PRODUCT_IMPORT_COLUMNS). */
    public const PRODUCT_IMPORT_COLUMNS_FR = [
        'code', 'code_sunulife', 'nom_fr', 'nom_en', 'categorie', 'periodicite',
        'prime_par_defaut', 'cout_police', 'numero_visa', 'ordre', 'famille', 'actif',
    ];

    public const PRODUCT_HEADER_ALIASES = [
        'code_produit' => 'code', 'code_sunulife' => 'sunulife_product_code', 'code_produit_sunulife' => 'sunulife_product_code',
        'nom_fr' => 'name_fr', 'nom' => 'name_fr', 'nom_francais' => 'name_fr', 'nom_en' => 'name_en', 'nom_anglais' => 'name_en',
        'categorie' => 'category', 'periodicite' => 'default_premium_frequency', 'periodicite_par_defaut' => 'default_premium_frequency',
        'frequence' => 'default_premium_frequency', 'prime_par_defaut' => 'default_premium_amount', 'prime' => 'default_premium_amount',
        'montant_prime' => 'default_premium_amount', 'cout_police' => 'policy_cost_amount', 'cout_de_police' => 'policy_cost_amount',
        'numero_visa' => 'visa_number', 'visa' => 'visa_number', 'ordre' => 'display_order', 'ordre_affichage' => 'display_order',
        'famille' => 'family', 'actif' => 'is_active',
    ];

    /** Sample CSV for the product import. */
    public function productImportSample(): StreamedResponse
    {
        $rows = [
            ['MICRO_RETRAITE', '', 'Micro Épargne Retraite', 'Micro Retirement Savings', 'Retraite', 'Mensuel', '2500', '1000', 'VISA-001', '1', 'micro-insurance', '1'],
            ['DCE-EXAZXX.', 'DCE-EXAZXX.', 'Dotation Capital Études', 'Education Capital', 'Education', 'Annuel', '', '', '', '', 'sunulife', '1'],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::PRODUCT_IMPORT_COLUMNS_FR);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'sunu_products_sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Product catalogue import. Upsert key = code in this business unit (existing code updated).
     * Empty code → derived from sunulife_product_code (DCE-EXAZXX. → DCE_EXAZXX).
     */
    public function productImport(Request $request)
    {
        $bu = $this->businessUnit();
        if (! $bu) {
            return redirect()->route('business.dashboard')->with('error', __('common.no_business_unit_assigned'));
        }
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        [$handle, $header, $delimiter] = self::openCsv($request->file('file')->getRealPath(), self::PRODUCT_HEADER_ALIASES);
        if (! $header) {
            return back()->with('error', __('common.sunu_flash_import_empty'));
        }
        $created = 0;
        $updated = 0;
        $errors = [];
        $line = 1;
        $nextOrder = (int) InsuranceProduct::where('business_unit_id', $bu->id)->max('display_order') + 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $data = array_map(fn ($v) => $v === null ? null : trim((string) $v),
                array_combine($header, array_slice(array_pad($row, count($header), null), 0, count($header))));
            $sunulifeCode = ($data['sunulife_product_code'] ?? '') ?: null;
            // Kept as SUNU sends it (dot included); empty code = the SUNULIFE code itself.
            $code = strtoupper((string) ($data['code'] ?? '')) ?: strtoupper((string) $sunulifeCode);
            $frequency = self::normalizeFrequency($data['default_premium_frequency'] ?? null);
            if ($frequency === false) {
                $errors[] = __('common.sunu_import_error_frequency', ['line' => $line, 'value' => $data['default_premium_frequency']]);
                continue;
            }
            $values = [
                'code' => $code,
                'sunulife_product_code' => $sunulifeCode,
                'name_fr' => ($data['name_fr'] ?? '') ?: null,
                'name_en' => ($data['name_en'] ?? '') ?: null,
                'category' => ($data['category'] ?? '') ?: null,
                'default_premium_frequency' => $frequency,
                'default_premium_amount' => ($data['default_premium_amount'] ?? '') !== '' ? $data['default_premium_amount'] : null,
                'policy_cost_amount' => ($data['policy_cost_amount'] ?? '') !== '' ? $data['policy_cost_amount'] : null,
                'visa_number' => ($data['visa_number'] ?? '') ?: null,
                'display_order' => ($data['display_order'] ?? '') !== '' ? $data['display_order'] : null,
                'family' => ($data['family'] ?? '') ?: null,
                'is_active' => ($data['is_active'] ?? '') === '' ? true : in_array(strtolower($data['is_active']), ['1', 'true', 'oui', 'yes', 'actif', 'active'], true),
            ];
            $validator = \Illuminate\Support\Facades\Validator::make($values, [
                'code' => 'required|string|max:64|regex:'.self::PRODUCT_CODE_REGEX,
                'sunulife_product_code' => 'nullable|string|max:64',
                'name_fr' => 'required|string|max:191',
                'name_en' => 'nullable|string|max:191',
                'category' => 'nullable|string|in:'.implode(',', InsuranceProduct::CATEGORIES),
                'default_premium_amount' => 'nullable|numeric|min:0|max:99999999',
                'policy_cost_amount' => 'nullable|numeric|min:0|max:99999999',
                'visa_number' => 'nullable|string|max:191',
                'display_order' => 'nullable|integer|min:0|max:9999',
                'family' => 'nullable|string|max:64',
            ]);
            if ($validator->fails()) {
                $errors[] = __('common.sunu_product_import_error_line', ['line' => $line, 'code' => $code ?: '?', 'error' => $validator->errors()->first()]);
                continue;
            }

            $product = InsuranceProduct::where('business_unit_id', $bu->id)->where('code', $code)->first();
            if ($product) {
                // Update only the columns given a value: an empty cell keeps what the office typed.
                $product->update(array_filter($values, fn ($v) => $v !== null) + ['is_active' => $values['is_active']]);
                $updated++;
            } else {
                $values['business_unit_id'] = $bu->id;
                $values['family'] ??= 'micro-insurance';
                $values['default_premium_frequency'] ??= 'monthly';
                $values['display_order'] ??= $nextOrder++;
                InsuranceProduct::create($values);
                $created++;
            }
        }
        fclose($handle);

        self::forgetProductCache();
        Log::info('SUNU_PRODUCT_IMPORT', ['business_unit_id' => $bu->id, 'user_id' => auth()->id(),
            'created' => $created, 'updated' => $updated, 'errors' => count($errors)]);

        return redirect()->route('business.sunu.products.index')
            ->with('success', __('common.sunu_flash_product_import_done', ['created' => $created, 'updated' => $updated]))
            ->with('import_errors', array_slice($errors, 0, 50));
    }

    /**
     * Open an uploaded CSV: strips the Excel BOM and detects the separator from the header line
     * (French Excel saves with ";"). Headers are normalised (accents, case, spaces: "Date d'effet" →
     * date_d_effet) then mapped through $aliases to the internal names, so French and English both work.
     * Returns [handle, header or null, delimiter].
     */
    public static function openCsv(string $path, array $aliases = []): array
    {
        $handle = fopen($path, 'r');
        $first = (string) fgets($handle);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = $first === '' ? null : str_getcsv(rtrim($first, "\r\n"), $delimiter);
        if ($header) {
            $header = array_map(function ($h) use ($aliases) {
                $key = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(Str::ascii(str_replace('°', 'o', (string) $h)))), '_');

                return $aliases[$key] ?? $key;
            }, $header);
        }

        return [$handle, $header, $delimiter];
    }

    /**
     * Premium frequency from the file (BO values, SUNULIFE letters or French words).
     * null = empty (use the product default), false = not recognised.
     * Unique / U → annual (prime unique, decision 2026-10-26).
     */
    public static function normalizeFrequency(?string $value): string|null|false
    {
        $key = strtolower(trim((string) $value));
        if ($key === '') {
            return null;
        }
        $key = strtr($key, ['é' => 'e', 'è' => 'e', 'É' => 'e']);

        return [
            'daily' => 'daily', 'j' => 'daily', 'journalier' => 'daily', 'journaliere' => 'daily', 'quotidien' => 'daily', 'quotidienne' => 'daily',
            'weekly' => 'weekly', 'h' => 'weekly', 'hebdomadaire' => 'weekly',
            'monthly' => 'monthly', 'm' => 'monthly', 'mensuel' => 'monthly', 'mensuelle' => 'monthly',
            'quarterly' => 'quarterly', 't' => 'quarterly', 'trimestriel' => 'quarterly', 'trimestrielle' => 'quarterly',
            'semiannual' => 'semiannual', 'semi-annual' => 'semiannual', 's' => 'semiannual', 'semestriel' => 'semiannual', 'semestrielle' => 'semiannual',
            'annual' => 'annual', 'a' => 'annual', 'annuel' => 'annual', 'annuelle' => 'annual',
            'u' => 'annual', 'unique' => 'annual',
        ][$key] ?? false;
    }

    /**
     * Comparison key only (never stored): "DCE-EXAZXX." and "DCE_EXAZXX" → "DCE_EXAZXX", so a file
     * written before 2026-11-11 (dot removed) still finds its product.
     */
    public static function boProductCode(string $sunulifeCode): string
    {
        $code = preg_replace('/[^A-Z0-9_]/', '_', rtrim(strtoupper(trim($sunulifeCode)), '.'));

        return trim(preg_replace('/_+/', '_', $code), '_');
    }

    /** Product of an import line (customer import). */
    protected function findProductForImport(int $businessUnitId, string $code): ?InsuranceProduct
    {
        // One query per import file (cache reset at the start of each import).
        return self::matchProductCode($businessUnitId, $code,
            self::$productCache[$businessUnitId] ??= InsuranceProduct::where('business_unit_id', $businessUnitId)->get());
    }

    /** @var array<int, \Illuminate\Support\Collection> products per business unit, for one request */
    protected static array $productCache = [];

    /**
     * Product by code: exact code (CPQOT2.), exact SUNULIFE code, else the same code written
     * without the dot / with _ for - (CPQOT2, DCE_EXAZXX). Used by the imports and the TPE API.
     */
    public static function matchProductCode(int $businessUnitId, ?string $code, ?\Illuminate\Support\Collection $products = null): ?InsuranceProduct
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }
        $products ??= InsuranceProduct::where('business_unit_id', $businessUnitId)->get();
        $key = self::boProductCode($code);

        return $products->first(fn ($p) => strtoupper((string) $p->code) === $code)
            ?? $products->first(fn ($p) => strtoupper((string) $p->sunulife_product_code) === $code)
            ?? $products->first(fn ($p) => self::boProductCode((string) $p->code) === $key
                || ($p->sunulife_product_code && self::boProductCode($p->sunulife_product_code) === $key));
    }

    public static function forgetProductCache(): void
    {
        self::$productCache = [];
    }
}
