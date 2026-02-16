<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Card;
use App\Models\Account;
use App\Models\CardSettings;
use App\Services\CardNumberGeneratorService;
use App\Services\ExportService;
use Illuminate\Support\Facades\Hash;

class CardController extends Controller
{
    protected $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->middleware(['auth', 'business.access']);
        $this->exportService = $exportService;
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
            ->with(['account.accountable', 'replacedByCard']);

        // Filters
        if ($request->filled('card_number')) {
            $query->where('card_number', 'like', '%' . $request->card_number . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('client_id')) {
            $query->whereHas('account', function($q) use ($request) {
                $q->where('accountable_type', \App\Models\Client::class)
                  ->where('accountable_id', $request->client_id);
            });
        }

        $cards = $query->latest()->paginate(15);
        
        // Get accounts for filter dropdown
        $accounts = Account::where('business_unit_id', $businessUnit->id)
            ->where('accountable_type', \App\Models\Client::class)
            ->with('accountable')
            ->get();

        return view('cards.index', compact('cards', 'accounts', 'businessUnit'));
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

        // Get card settings
        $cardSettings = CardSettings::getOrCreateForBusinessUnit($businessUnit->id);
        
        // Get client accounts for dropdown
        $accounts = Account::where('business_unit_id', $businessUnit->id)
            ->where('accountable_type', \App\Models\Client::class)
            ->where('status', 'active')
            ->with('accountable')
            ->get();

        // Preview next card number
        $nextCardNumber = CardNumberGeneratorService::previewNext($businessUnit->id);

        return view('cards.create', compact('accounts', 'cardSettings', 'nextCardNumber', 'businessUnit'));
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
            'account_id' => 'required|exists:accounts,id',
            'card_serial' => 'nullable|string|max:32|unique:cards,card_serial',
            'expiration_date' => 'nullable|date|after:today',
            'daily_transaction_limit' => 'nullable|numeric|min:0',
            'daily_withdrawal_limit' => 'nullable|numeric|min:0',
            'pin' => 'nullable|string|min:4|max:6',
            'description' => 'nullable|string|max:500',
        ]);

        // Verify account belongs to business unit
        $account = Account::where('id', $validated['account_id'])
            ->where('business_unit_id', $businessUnit->id)
            ->firstOrFail();

        // Get card settings
        $cardSettings = CardSettings::getOrCreateForBusinessUnit($businessUnit->id);

        // Generate card number
        try {
            $cardNumber = CardNumberGeneratorService::generate($businessUnit->id);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        // Set expiration date if not provided
        $expirationDate = $validated['expiration_date'] ?? $cardSettings->getDefaultExpirationDate();

        // Set limits from defaults if not provided
        $dailyTransactionLimit = $validated['daily_transaction_limit'] ?? $cardSettings->default_daily_transaction_limit;
        $dailyWithdrawalLimit = $validated['daily_withdrawal_limit'] ?? $cardSettings->default_daily_withdrawal_limit;

        // Create card
        $card = Card::create([
            'business_unit_id' => $businessUnit->id,
            'account_id' => $validated['account_id'],
            'card_number' => $cardNumber,
            'card_serial' => $validated['card_serial'] ?? null,
            'prefix' => $cardSettings->card_prefix,
            'status' => 'inactive',
            'expiration_date' => $expirationDate,
            'daily_transaction_limit' => $dailyTransactionLimit,
            'daily_withdrawal_limit' => $dailyWithdrawalLimit,
            'pin_hash' => $validated['pin'] ? Hash::make($validated['pin']) : null,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('cards.show', $card->id)
            ->with('success', __('common.card_created_successfully'));
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
            ->with(['account.accountable', 'replacedByCard', 'replacedCard'])
            ->findOrFail($id);

        return view('cards.show', compact('card', 'businessUnit'));
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
            ->with(['account.accountable', 'replacedByCard']);

        // Apply same filters as index
        if ($request->filled('card_number')) {
            $query->where('card_number', 'like', '%' . $request->card_number . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        if ($request->filled('client_id')) {
            $query->whereHas('account', function($q) use ($request) {
                $q->where('accountable_type', \App\Models\Client::class)
                  ->where('accountable_id', $request->client_id);
            });
        }

        $cards = $query->latest()->get();

        // Transform data for export
        $data = $cards->map(function ($card) {
            return [
                'Card Number' => $card->card_number,
                'Account Number' => $card->account->account_number ?? '-',
                'Client' => ($card->account->accountable_type == 'App\Models\Client' && $card->account->accountable) 
                    ? $card->account->accountable->full_name 
                    : '-',
                'Status' => ucfirst($card->status),
                'Expiration Date' => $card->expiration_date->format('Y-m-d'),
                'Daily Transaction Limit' => $card->daily_transaction_limit ? number_format($card->daily_transaction_limit, 2) : '-',
                'Daily Withdrawal Limit' => $card->daily_withdrawal_limit ? number_format($card->daily_withdrawal_limit, 2) : '-',
                'Issued At' => $card->issued_at ? $card->issued_at->format('Y-m-d H:i') : '-',
                'Activated At' => $card->activated_at ? $card->activated_at->format('Y-m-d H:i') : '-',
            ];
        });

        $headers = ['Card Number', 'Account Number', 'Client', 'Status', 'Expiration Date', 'Daily Transaction Limit', 'Daily Withdrawal Limit', 'Issued At', 'Activated At'];
        $filters = $request->only(['card_number', 'status', 'search']);
        $filename = $this->exportService->generateFilename('cards', $filters);

        switch ($format) {
            case 'excel':
                return $this->exportService->exportExcel($data, $headers, $filename, 'Cards');
            case 'pdf':
                return $this->exportService->exportPdf($data, $headers, $filename, 'Cards Export', $filters);
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
        // Validate business_unit_id from request (query parameter or request body)
        $validated = $request->validate([
            'business_unit_id' => 'required|integer|exists:business_units,id',
        ]);
        
        $businessUnitId = $validated['business_unit_id'];
        
        // Verify business unit exists and is active
        $businessUnit = \App\Models\BusinessUnit::find($businessUnitId);
        if (!$businessUnit || !$businessUnit->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business unit not found or inactive'
            ], 404);
        }

        // Find card by card_serial
        $card = Card::where('business_unit_id', $businessUnitId)
            ->where('card_serial', $uid)
            ->with(['account.accountable'])
            ->first();

        // If not found by serial, try to find a card without serial in this business unit
        // This supports auto-assignment when Android app first reads a card
        // Note: This is a simple implementation - you may want to add additional matching
        // criteria (like card_number) if provided in the request
        if (!$card) {
            // Find first inactive card without serial in this business unit
            // This allows auto-assignment when card is first read
            $card = Card::where('business_unit_id', $businessUnitId)
                ->whereNull('card_serial')
                ->where('status', 'inactive')
                ->with(['account.accountable'])
                ->first();
            
            // Auto-assign card_serial if found
            if ($card) {
                $card->card_serial = $uid;
                $card->save();
            }
        }

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'card' => [
                'id' => $card->id,
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
            ]
        ]);
    }

    /**
     * Mark card as written - API endpoint for Android app
     */
    public function markAsWritten(Request $request, $id)
    {
        // Validate business_unit_id and device_serial from request
        $validated = $request->validate([
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'device_serial' => 'nullable|string|max:255',
        ]);
        
        $businessUnitId = $validated['business_unit_id'];
        
        // Verify business unit exists and is active
        $businessUnit = \App\Models\BusinessUnit::find($businessUnitId);
        if (!$businessUnit || !$businessUnit->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business unit not found or inactive'
            ], 404);
        }

        $card = Card::where('business_unit_id', $businessUnitId)
            ->findOrFail($id);

        $card->update([
            'status' => 'active',
            'written_at' => now(),
            'written_by' => $validated['device_serial'] ?? null,
            'activated_at' => $card->activated_at ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Card marked as written'
        ]);
    }

    /**
     * Import card UIDs from Excel
     */
    public function importUids(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->back()
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!auth()->user()->hasPermission('create-card', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        try {
            // Parse Excel file
            // Note: This requires Maatwebsite\Excel package
            // Install with: composer require maatwebsite/excel
            $imported = 0;
            $errors = [];
            
            $file = $request->file('file');
            $data = \Maatwebsite\Excel\Facades\Excel::toArray([], $file);
            
            if (empty($data) || empty($data[0])) {
                return redirect()->back()
                    ->with('error', 'Excel file is empty');
            }
            
            $cardSettings = CardSettings::getOrCreateForBusinessUnit($businessUnit->id);
            
            foreach ($data[0] as $index => $row) {
                if ($index === 0) continue; // Skip header row
                
                $cardSerial = trim($row[0] ?? ''); // UID column
                $cardNumber = trim($row[1] ?? ''); // Optional card number
                
                if (empty($cardSerial)) {
                    $errors[] = "Row " . ($index + 1) . ": UID is empty";
                    continue;
                }
                
                // Check if UID already exists
                $existing = Card::where('card_serial', $cardSerial)
                    ->where('business_unit_id', $businessUnit->id)
                    ->first();
                    
                if ($existing) {
                    $errors[] = "Row " . ($index + 1) . ": UID $cardSerial already exists";
                    continue;
                }
                
                // Generate card number if not provided
                if (empty($cardNumber)) {
                    try {
                        $cardNumber = CardNumberGeneratorService::generate($businessUnit->id);
                    } catch (\Exception $e) {
                        $errors[] = "Row " . ($index + 1) . ": Failed to generate card number - " . $e->getMessage();
                        continue;
                    }
                }
                
                // Create unassigned card with UID
                Card::create([
                    'business_unit_id' => $businessUnit->id,
                    'card_serial' => $cardSerial,
                    'card_number' => $cardNumber,
                    'status' => 'unassigned',
                    'prefix' => $cardSettings->card_prefix,
                    'expiration_date' => $cardSettings->getDefaultExpirationDate(),
                    'daily_transaction_limit' => $cardSettings->default_daily_transaction_limit,
                    'daily_withdrawal_limit' => $cardSettings->default_daily_withdrawal_limit,
                ]);
                
                $imported++;
            }
            
            return redirect()->back()
                ->with('success', "Imported $imported cards")
                ->with('errors', $errors);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to import: ' . $e->getMessage());
        }
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
