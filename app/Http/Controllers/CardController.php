<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Card;
use App\Models\Account;
use App\Models\CardSettings;
use App\Services\CardNumberGeneratorService;
use App\Services\ExportService;
use App\Services\SunuSubscriptionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CardController extends Controller
{
    protected $exportService;
    protected $subscriptions;

    public function __construct(ExportService $exportService, SunuSubscriptionService $subscriptions)
    {
        $this->middleware(['auth', 'business.access'])
            ->except(['getByUid', 'markAsWritten']);
        $this->exportService = $exportService;
        $this->subscriptions = $subscriptions;
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

        // Check permission
        if (!auth()->user()->hasPermission('view-cards', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $query = Card::where('business_unit_id', $businessUnit->id)
            ->with(['replacedByCard', 'sunuPolicies.client', 'sunuPolicies.product', 'sunuProposals.client', 'sunuProposals.product']);

        $this->applyIndexFilters($query, $request);

        $cards = $query->latest()->paginate(15)->withQueryString();

        $clients = $this->subscriptions->registeredClients($businessUnit->id);

        return view('cards.index', compact('cards', 'clients', 'businessUnit'));
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

        // Check permission
        if (!auth()->user()->hasPermission('create-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        // SUNU: cards are issued to registered customers (policy or pending proposal), not accounts
        $clients = $this->subscriptions->registeredClients($businessUnit->id);
        $stockUids = $this->stockCards($businessUnit->id)->pluck('card_serial');
        $prefillUid = $this->subscriptions->normalizeUid(request('uid'));

        return view('cards.create', compact('clients', 'businessUnit', 'stockUids', 'prefillUid'));
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

        // Check permission
        if (!auth()->user()->hasPermission('create-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'client_id' => [
                'required', 'integer',
                Rule::exists('clients', 'id')->where('business_unit_id', $businessUnit->id),
            ],
            'card_uid' => ['required', 'string', 'max:64', 'regex:/^[0-9A-Fa-f:\-\s]+$/'],
            'description' => 'nullable|string|max:500',
        ]);

        $uid = $this->subscriptions->normalizeUid($validated['card_uid']);
        if ($uid === null) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['card_uid' => __('common.sunu_card_uid_invalid')]);
        }

        $found = $this->subscriptions->forClient($businessUnit->id, (int) $validated['client_id']);
        if ($found['policies']->isEmpty() && $found['proposals']->isEmpty()) {
            return redirect()->back()
                ->withInput()
                ->with('error', __('common.sunu_card_client_no_subscription'));
        }

        // Reuses the card if the UID already exists in this BU; refuses another customer's card
        try {
            $card = $this->subscriptions->linkCardToAll($businessUnit->id, $uid, $found);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', __('common.sunu_flash_card_link_conflict') . ' ' . $e->getMessage());
        }

        if ($card->status === 'unassigned') {
            $card->status = 'active';
            $card->activated_at = now();
        }
        if (! empty($validated['description'])) {
            $card->description = $validated['description'];
        }
        $card->save();

        Log::info('SUNU_CARD_WEB_ASSIGN', [
            'card_id' => $card->id,
            'card_uid' => $card->card_serial,
            'client_id' => (int) $validated['client_id'],
            'count' => $found['policies']->count() + $found['proposals']->count(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('cards.show', $card->id)
            ->with('success', __('common.sunu_flash_card_linked', ['uid' => $card->card_serial ?? $card->card_number]));
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

        // Check permission
        if (!auth()->user()->hasPermission('view-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $card = Card::where('business_unit_id', $businessUnit->id)
            ->with(['replacedByCard', 'replacedCard', 'sunuPolicies.client', 'sunuProposals.client'])
            ->findOrFail($id);

        // Everything the TPE shows for this card (all payable policies + pending proposals of the customer)
        $subscriptions = $this->subscriptions->forCard($businessUnit->id, $card);

        return view('cards.show', compact('card', 'businessUnit', 'subscriptions'));
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

        // Check permission
        if (!auth()->user()->hasPermission('edit-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $card = Card::where('business_unit_id', $businessUnit->id)
            ->with('account.accountable')
            ->findOrFail($id);

        return view('cards.edit', compact('card', 'businessUnit'));
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

        // Check permission
        if (!auth()->user()->hasPermission('edit-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $card = Card::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'card_serial' => 'nullable|string|max:32|unique:cards,card_serial,' . $card->id,
            'expiration_date' => 'nullable|date|after:today',
            'daily_transaction_limit' => 'nullable|numeric|min:0',
            'daily_withdrawal_limit' => 'nullable|numeric|min:0',
            'pin' => 'nullable|string|min:4|max:6',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|in:active,inactive,blocked',
        ]);

        // Prevent updating card_serial if card is already written
        if ($request->filled('card_serial') && $card->written_at) {
            return redirect()->back()
                ->withInput()
                ->with('error', __('common.cannot_change_card_serial_after_written'));
        }

        // Update PIN if provided
        if ($request->filled('pin')) {
            $card->setPin($request->pin);
        }

        // Update other fields
        $updateData = [
            'expiration_date' => $validated['expiration_date'] ?? $card->expiration_date,
            'daily_transaction_limit' => $validated['daily_transaction_limit'] ?? $card->daily_transaction_limit,
            'daily_withdrawal_limit' => $validated['daily_withdrawal_limit'] ?? $card->daily_withdrawal_limit,
            'description' => $validated['description'] ?? $card->description,
            'status' => $validated['status'] ?? $card->status,
        ];

        // Update card_serial if provided and card is not written
        if ($request->filled('card_serial') && !$card->written_at) {
            $updateData['card_serial'] = $validated['card_serial'];
        }

        $card->update($updateData);

        return redirect()->route('cards.show', $card->id)
            ->with('success', __('common.card_updated_successfully'));
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

        // Check permission
        if (!auth()->user()->hasPermission('delete-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $card = Card::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        // Don't allow deletion of active or replaced cards
        if ($card->isActive() || $card->isReplaced()) {
            return redirect()->back()
                ->with('error', __('common.cannot_delete_active_or_replaced_card'));
        }

        $card->delete();

        return redirect()->route('cards.index')
            ->with('success', __('common.card_deleted_successfully'));
    }

    /**
     * Activate a card.
     */
    public function activate($id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!auth()->user()->hasPermission('activate-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $card = Card::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        if ($card->activate()) {
            return redirect()->back()
                ->with('success', __('common.card_activated_successfully'));
        }

        return redirect()->back()
            ->with('error', __('common.cannot_activate_card'));
    }

    /**
     * Block a card.
     */
    public function block(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!auth()->user()->hasPermission('block-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $card = Card::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        if ($card->block($validated['reason'] ?? null)) {
            return redirect()->back()
                ->with('success', __('common.card_blocked_successfully'));
        }

        return redirect()->back()
            ->with('error', __('common.cannot_block_card'));
    }

    /**
     * Replace a card.
     */
    public function replace(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!auth()->user()->hasPermission('replace-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
            'expiration_date' => 'nullable|date|after:today',
            'daily_transaction_limit' => 'nullable|numeric|min:0',
            'daily_withdrawal_limit' => 'nullable|numeric|min:0',
            'pin' => 'nullable|string|min:4|max:6',
        ]);

        $oldCard = Card::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        if (!$oldCard->canBeReplaced()) {
            return redirect()->back()
                ->with('error', __('common.cannot_replace_card'));
        }

        // Get card settings
        $cardSettings = CardSettings::getOrCreateForBusinessUnit($businessUnit->id);

        // Generate new card number
        try {
            $cardNumber = CardNumberGeneratorService::generate($businessUnit->id);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }

        // Set expiration date
        $expirationDate = $validated['expiration_date'] 
            ?? $oldCard->expiration_date 
            ?? $cardSettings->getDefaultExpirationDate();

        // Set limits (use old card's limits if not provided)
        $dailyTransactionLimit = $validated['daily_transaction_limit'] ?? $oldCard->daily_transaction_limit;
        $dailyWithdrawalLimit = $validated['daily_withdrawal_limit'] ?? $oldCard->daily_withdrawal_limit;

        // Create new card
        $newCard = Card::create([
            'business_unit_id' => $businessUnit->id,
            'account_id' => $oldCard->account_id,
            'card_number' => $cardNumber,
            'prefix' => $cardSettings->card_prefix,
            'status' => 'inactive',
            'expiration_date' => $expirationDate,
            'daily_transaction_limit' => $dailyTransactionLimit,
            'daily_withdrawal_limit' => $dailyWithdrawalLimit,
            'pin_hash' => $validated['pin'] ? Hash::make($validated['pin']) : $oldCard->pin_hash,
            'description' => $oldCard->description,
        ]);

        // Mark old card as replaced
        $oldCard->replace($newCard->id, $validated['reason'] ?? null);

        return redirect()->route('cards.show', $newCard->id)
            ->with('success', __('common.card_replaced_successfully'));
    }

    /** Card list filters shared by index and export: number/UID, status, SUNU client. */
    protected function applyIndexFilters($query, Request $request): void
    {
        if ($request->filled('card_number')) {
            $term = '%' . $request->card_number . '%';
            $query->where(function ($q) use ($term) {
                $q->where('card_number', 'like', $term)->orWhere('card_serial', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id')) {
            $clientId = (int) $request->client_id;
            $query->where(function ($q) use ($clientId) {
                $q->whereHas('sunuPolicies', fn ($p) => $p->where('client_id', $clientId))
                  ->orWhereHas('sunuProposals', fn ($p) => $p->where('client_id', $clientId));
            });
        }
    }

    /**
     * Export cards.
     */
    public function export(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!auth()->user()->hasPermission('view-cards', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $format = $request->get('format', 'csv');
        $query = Card::where('business_unit_id', $businessUnit->id)
            ->with(['sunuPolicies.client', 'sunuPolicies.product', 'sunuProposals.client', 'sunuProposals.product']);
        $this->applyIndexFilters($query, $request);

        $cards = $query->latest()->get();

        // Transform data for export
        $data = $cards->map(function ($card) {
            $client = $card->sunuClient();
            $numbers = $card->sunuPolicies->pluck('policy_number')
                ->merge($card->sunuProposals->pluck('proposal_number'));

            return [
                __('common.export_col_card_number') => $card->card_number,
                __('common.export_col_card_uid') => $card->card_serial ?? '-',
                __('common.export_col_client') => $client ? trim($client->last_name . ' ' . $client->first_name) : '-',
                __('common.export_col_phone') => $client->phone_number ?? '-',
                __('common.export_col_policies_proposals') => $numbers->isNotEmpty() ? $numbers->implode(', ') : '-',
                __('common.export_col_status') => $this->exportService->valueLabel($card->status, ''),
                __('common.export_col_issued_at') => $card->issued_at ? $card->issued_at->format('Y-m-d H:i') : '-',
                __('common.export_col_written_on_tpe') => $card->written_at ? $card->written_at->format('Y-m-d H:i') : '-',
            ];
        });

        $headers = array_map(fn ($k) => __('common.export_col_'.$k), ['card_number', 'card_uid', 'client', 'phone', 'policies_proposals', 'status', 'issued_at', 'written_on_tpe']);
        $filters = $request->only(['card_number', 'status', 'search']);
        $filename = $this->exportService->generateFilename('cards', $filters);

        switch ($format) {
            case 'excel':
                return $this->exportService->exportExcel($data, $headers, $filename, __('common.cards'));
            case 'pdf':
                return $this->exportService->exportPdf($data, $headers, $filename, __('common.cards'), $filters);
            case 'csv':
            default:
                return $this->exportService->exportCsv($data, $headers, $filename);
        }
    }

    /**
     * Get card by UID (card serial number) - API endpoint for Android app
     */
    public function getByUid(Request $request, $uid)
    {
        $normalizedUid = strtoupper((string) $uid);

        Log::info('Card API getByUid start', [
            'uid' => $uid,
            'normalized_uid' => $normalizedUid,
        ]);

        try {
            $validated = $request->validate([
                'business_unit_id' => 'nullable|integer|exists:business_units,id',
            ]);
        } catch (ValidationException $e) {
            Log::info('Card API getByUid validation_failed', [
                'uid' => $uid,
                'query' => $request->query(),
                'input' => $request->all(),
                'errors' => $e->errors(),
                'expected_payload' => [
                    'business_unit_id' => 'integer (query or body), optional',
                    'uid' => 'path: /cards/uid/{uid}',
                ],
            ]);
            throw $e;
        }

        $businessUnitId = $validated['business_unit_id'] ?? null;
        $businessUnitResolvedFrom = $businessUnitId ? 'request' : 'card_serial';
        $matchType = 'not_found';
        $card = null;

        Log::info('Card API getByUid validated', [
            'business_unit_id' => $businessUnitId,
            'uid' => $normalizedUid,
            'business_unit_resolved_from' => $businessUnitResolvedFrom,
        ]);

        if ($businessUnitId) {
            $businessUnit = \App\Models\BusinessUnit::find($businessUnitId);
            if (! $businessUnit || ! $businessUnit->is_active) {
                Log::info('Card API getByUid business unit invalid', [
                    'business_unit_id' => $businessUnitId,
                    'found' => (bool) $businessUnit,
                    'is_active' => $businessUnit?->is_active,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Business unit not found or inactive',
                ], 404);
            }

            $card = Card::where('business_unit_id', $businessUnitId)
                ->where('card_serial', $normalizedUid)
                ->with(['account.accountable'])
                ->first();

            $matchType = $card ? 'serial' : 'not_found';

            if (! $card) {
                $card = Card::where('business_unit_id', $businessUnitId)
                    ->whereNull('card_serial')
                    ->where('status', 'inactive')
                    ->with(['account.accountable'])
                    ->first();

                if ($card) {
                    $matchType = 'auto_assign_inactive';
                    $card->card_serial = $normalizedUid;
                    $card->save();

                    Log::info('Card API getByUid auto_assigned_serial', [
                        'card_id' => $card->id,
                        'uid' => $normalizedUid,
                    ]);
                }
            }
        } else {
            $matches = Card::where('card_serial', $normalizedUid)
                ->with(['account.accountable'])
                ->get();

            if ($matches->count() > 1) {
                Log::warning('Card API getByUid duplicate_serial', [
                    'uid' => $normalizedUid,
                    'card_ids' => $matches->pluck('id')->all(),
                ]);
            }

            $card = $matches->first();
            $matchType = $card ? 'serial_global' : 'not_found';

            if ($card) {
                $businessUnitId = $card->business_unit_id;
                $businessUnit = \App\Models\BusinessUnit::find($businessUnitId);
                if (! $businessUnit || ! $businessUnit->is_active) {
                    Log::info('Card API getByUid business unit invalid', [
                        'business_unit_id' => $businessUnitId,
                        'found' => (bool) $businessUnit,
                        'is_active' => $businessUnit?->is_active,
                        'business_unit_resolved_from' => $businessUnitResolvedFrom,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Business unit not found or inactive',
                    ], 404);
                }
            }
        }

        Log::info('Card API getByUid lookup', [
            'business_unit_id' => $businessUnitId,
            'uid' => $normalizedUid,
            'match_type' => $matchType,
            'card_id' => $card?->id,
            'business_unit_resolved_from' => $businessUnitResolvedFrom,
        ]);

        if (! $card) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'card' => $this->formatCardApiPayload($card),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatCardApiPayload(Card $card): array
    {
        return [
            'id' => $card->id,
            'business_unit_id' => $card->business_unit_id,
            'card_number' => $card->card_number,
            'card_serial' => $card->card_serial,
            'account_id' => $card->account_id,
            'account_number' => $card->account->account_number ?? null,
            'client_name' => ($card->account->accountable_type == 'App\Models\Client' && $card->account->accountable)
                ? $card->account->accountable->full_name
                : null,
            'first_name' => ($card->account->accountable_type == 'App\Models\Client' && $card->account->accountable)
                ? $card->account->accountable->first_name
                : null,
            'last_name' => ($card->account->accountable_type == 'App\Models\Client' && $card->account->accountable)
                ? $card->account->accountable->last_name
                : null,
            'status' => $card->status,
        ];
    }

    /**
     * Mark card as written - API endpoint for Android app
     */
    public function markAsWritten(Request $request, $id)
    {
        Log::info('Card API markAsWritten start', [
            'card_id' => $id,
        ]);

        try {
            $validated = $request->validate([
                'business_unit_id' => 'nullable|integer|exists:business_units,id',
                'device_serial' => 'nullable|string|max:255',
            ]);
        } catch (ValidationException $e) {
            Log::info('Card API markAsWritten validation_failed', [
                'card_id' => $id,
                'query' => $request->query(),
                'input' => $request->all(),
                'errors' => $e->errors(),
                'expected_payload' => [
                    'business_unit_id' => 'integer (query or body), optional',
                    'device_serial' => 'string (optional)',
                    'id' => 'path: /cards/{id}/mark-written',
                ],
            ]);
            throw $e;
        }

        $businessUnitId = $validated['business_unit_id'] ?? null;

        Log::info('Card API markAsWritten validated', [
            'card_id' => $id,
            'business_unit_id' => $businessUnitId,
            'device_serial' => $validated['device_serial'] ?? null,
        ]);

        if ($businessUnitId) {
            $card = Card::where('business_unit_id', $businessUnitId)
                ->findOrFail($id);
        } else {
            $card = Card::findOrFail($id);
            $businessUnitId = $card->business_unit_id;
        }

        $businessUnit = \App\Models\BusinessUnit::find($businessUnitId);
        if (! $businessUnit || ! $businessUnit->is_active) {
            Log::info('Card API markAsWritten business unit invalid', [
                'business_unit_id' => $businessUnitId,
                'card_id' => $id,
                'found' => (bool) $businessUnit,
                'is_active' => $businessUnit?->is_active,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Business unit not found or inactive',
            ], 404);
        }

        $card->update([
            'status' => 'active',
            'written_at' => now(),
            'written_by' => $validated['device_serial'] ?? null,
            'activated_at' => $card->activated_at ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Card marked as written',
        ]);
    }

    /**
     * Free card stock: imported/unknown UIDs not yet linked to any SUNU policy or proposal.
     */
    protected function stockCards(int $businessUnitId)
    {
        return Card::where('business_unit_id', $businessUnitId)
            ->where('status', 'unassigned')
            ->whereNotNull('card_serial')
            ->doesntHave('sunuPolicies')
            ->doesntHave('sunuProposals')
            ->orderBy('card_serial')
            ->get(['id', 'card_serial', 'card_number', 'description']);
    }

    /**
     * Import the manufacturer's card list (CSV/XLSX: uid, card_number?, batch?) as unassigned stock.
     * UIDs are normalised to what the F20 reads (uppercase hex) according to the chosen format.
     */
    public function importUids(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (!$businessUnit) {
            return redirect()->back()
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!auth()->user()->hasPermission('create-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
            'uid_format' => ['nullable', Rule::in(SunuSubscriptionService::UID_FORMATS)],
        ]);
        $format = $validated['uid_format'] ?? 'hex';

        try {
            $sheets = \Maatwebsite\Excel\Facades\Excel::toArray([], $request->file('file'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', __('common.sunu_card_import_unreadable') . ' ' . $e->getMessage());
        }
        $rows = $sheets[0] ?? [];
        if (count($rows) < 2) {
            return redirect()->back()->with('error', __('common.sunu_card_import_empty'));
        }

        $settings = CardSettings::getOrCreateForBusinessUnit($businessUnit->id);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $seen = [];

        DB::transaction(function () use ($rows, $format, $businessUnit, $settings, &$imported, &$skipped, &$errors, &$seen) {
            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue; // header
                }
                $line = $index + 1;
                $raw = trim((string) ($row[0] ?? ''));
                if ($raw === '' && trim(implode('', array_map('strval', $row))) === '') {
                    continue; // blank line
                }

                $uid = $this->subscriptions->normalizeUid($raw, $format);
                if ($uid === null) {
                    $errors[] = __('common.sunu_card_import_row_invalid', ['row' => $line, 'uid' => $raw]);
                    continue;
                }
                if (isset($seen[$uid])) {
                    $skipped++;
                    continue;
                }
                $seen[$uid] = true;

                $existing = Card::withTrashed()->where('card_serial', $uid)->first();
                if ($existing) {
                    if ((int) $existing->business_unit_id === (int) $businessUnit->id) {
                        $skipped++;
                    } else {
                        $errors[] = __('common.sunu_card_import_row_other_bu', ['row' => $line, 'uid' => $uid]);
                    }
                    continue;
                }

                $cardNumber = trim((string) ($row[1] ?? ''));
                if ($cardNumber !== '' && Card::withTrashed()->where('card_number', $cardNumber)->exists()) {
                    $errors[] = __('common.sunu_card_import_row_number_taken', ['row' => $line, 'number' => $cardNumber]);
                    continue;
                }
                $batch = trim((string) ($row[2] ?? ''));

                Card::create([
                    'business_unit_id' => $businessUnit->id,
                    'card_serial' => $uid,
                    'card_number' => $cardNumber !== '' ? $cardNumber : CardNumberGeneratorService::generate($businessUnit->id),
                    'status' => 'unassigned',
                    'prefix' => $settings->card_prefix,
                    'expiration_date' => $settings->getDefaultExpirationDate(),
                    'daily_transaction_limit' => $settings->default_daily_transaction_limit,
                    'daily_withdrawal_limit' => $settings->default_daily_withdrawal_limit,
                    'description' => $batch !== '' ? __('common.sunu_card_import_batch', ['batch' => $batch]) : null,
                ]);
                $imported++;
            }
        });

        Log::info('SUNU_CARD_STOCK_IMPORT', [
            'business_unit_id' => $businessUnit->id,
            'format' => $format,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => count($errors),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('cards.index', ['status' => 'unassigned'])
            ->with($imported > 0 ? 'success' : 'error', __('common.sunu_card_import_summary', [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => count($errors),
            ]))
            ->with('import_errors', array_slice($errors, 0, 50));
    }

    /**
     * Sample file for the manufacturer card list import.
     */
    public function downloadSampleTemplate()
    {
        $csv = "uid,card_number,batch\n04A1B2C3D4E580,,LOT-2026-01\n1A2B3C4D,,LOT-2026-01\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sunu_cards_uid_sample.csv"',
        ]);
    }

    /**
     * Assign card to account
     */
    public function assignToAccount(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->back()
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!auth()->user()->hasPermission('edit-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
        ]);

        $card = Card::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        // Verify account belongs to business unit
        $account = Account::where('id', $validated['account_id'])
            ->where('business_unit_id', $businessUnit->id)
            ->firstOrFail();

        $card->update([
            'account_id' => $validated['account_id'],
            'status' => 'inactive', // Ready to be written
        ]);

        return redirect()->back()
            ->with('success', __('common.card_assigned_successfully'));
    }
}
