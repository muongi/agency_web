<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BusinessUnit;
use App\Models\AccountSms;
use App\Models\Permission;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\SmsMessage;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsController extends Controller
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->middleware('auth');
        $this->smsService = $smsService;
    }

    /**
     * Display SMS balance and transaction history for current business unit
     */
    public function index()
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!$user->isSuperAdmin() && !$user->hasPermission('view-sms', $businessUnit->id)) {
            abort(403, __('common.unauthorized_action'));
        }

        $smsBalance = AccountSms::getBalance($businessUnit->id);
        $smsUsed = AccountSms::where('business_unit_id', $businessUnit->id)->sum('debit');
        $smsCredited = AccountSms::where('business_unit_id', $businessUnit->id)->sum('credit');

        return view('sms.index', compact('businessUnit', 'smsBalance', 'smsUsed', 'smsCredited'));
    }

    /**
     * Show the form for loading SMS credits (superadmin only)
     */
    public function loadCredits(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isSuperAdmin()) {
            abort(403, __('common.unauthorized_action'));
        }

        // Get all business units (don't exclude any - superadmin should see all)
        $businessUnits = BusinessUnit::orderBy('name')->get();

        // Get pre-selected business unit from query parameter if provided
        $selectedBusinessUnitId = $request->query('business_unit');

        return view('sms.load_credits', compact('businessUnits', 'selectedBusinessUnitId'));
    }

    /**
     * Store SMS credits for a business unit (superadmin only)
     */
    public function storeCredits(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isSuperAdmin()) {
            abort(403, __('common.unauthorized_action'));
        }

        $validated = $request->validate([
            'target_business_unit_id' => 'required|exists:business_units,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255'
        ]);

        try {
            DB::beginTransaction();

            $targetBusinessUnitId = $validated['target_business_unit_id'];
            $amount = $validated['amount'];
            $description = $validated['description'] ?? 'SMS Credit Loaded';

            // Credit to target business unit
            AccountSms::insertAccountSms(
                $targetBusinessUnitId,
                0, // no debit
                $amount, // credit
                "SMS Credit loaded by superadmin - {$description}",
                $user->id,
                null,
                ['loaded_by' => $user->name]
            );

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => 1,
                    'msg' => __('common.sms_credits_loaded_successfully')
                ]);
            }

            return redirect()->route('business.sms.index')
                ->with('success', __('common.sms_credits_loaded_successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('common.something_went_wrong')
                ]);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', __('common.something_went_wrong'));
        }
    }

    /**
     * Get SMS transactions for DataTables
     */
    public function getSmsTransactions(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return response()->json([
                'draw' => $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'No business unit assigned'
            ]);
        }

        // Check permission
        if (!$user->isSuperAdmin() && !$user->hasPermission('view-sms', $businessUnit->id)) {
            return response()->json([
                'draw' => $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Unauthorized'
            ]);
        }

        $smsTransactions = AccountSms::where('business_unit_id', $businessUnit->id)
            ->with('user')
            ->orderBy('created_at', 'desc');

        // Calculate running balance for each transaction
        $transactions = $smsTransactions->get()->map(function ($transaction, $index) use ($businessUnit) {
            // Calculate balance up to this transaction
            $balance = AccountSms::where('business_unit_id', $businessUnit->id)
                ->where('created_at', '<=', $transaction->created_at)
                ->selectRaw('SUM(credit) - SUM(debit) as balance')
                ->first()
                ->balance ?? 0;

            return [
                'id' => $transaction->id,
                'transaction_type' => $transaction->credit > 0 ? 'Credit' : 'Debit',
                'amount' => $transaction->credit > 0 ? $transaction->credit : $transaction->debit,
                'description' => $transaction->description,
                'date' => $transaction->created_at->format('Y-m-d H:i:s'),
                'balance_after' => number_format($balance, 2),
                'user' => $transaction->user ? $transaction->user->name : '-',
            ];
        });

        return response()->json([
            'draw' => $request->input('draw', 0),
            'recordsTotal' => $transactions->count(),
            'recordsFiltered' => $transactions->count(),
            'data' => $transactions->values()
        ]);
    }

    /**
     * Get SMS balance for a business unit
     */
    public function getBalance(Request $request)
    {
        $businessUnitId = $request->input('business_unit_id', auth()->user()->current_business_unit_id);
        
        if (!$businessUnitId) {
            return response()->json([
                'balance' => 0,
                'has_balance' => false
            ]);
        }

        $balance = AccountSms::getBalance($businessUnitId);
        
        return response()->json([
            'balance' => number_format($balance, 2),
            'has_balance' => $balance > 0
        ]);
    }

    /**
     * Show SMS balance for all businesses (superadmin only)
     */
    public function allBusinessesBalance()
    {
        $user = auth()->user();
        
        if (!$user->isSuperAdmin()) {
            abort(403, __('common.unauthorized_action'));
        }

        $businessUnits = BusinessUnit::all();

        // Add SMS balance to each business unit
        foreach ($businessUnits as $businessUnit) {
            $businessUnit->setAttribute('sms_balance', AccountSms::getBalance($businessUnit->id));
            $businessUnit->setAttribute('sms_used', AccountSms::where('business_unit_id', $businessUnit->id)->sum('debit'));
            $businessUnit->setAttribute('sms_credited', AccountSms::where('business_unit_id', $businessUnit->id)->sum('credit'));
        }

        return view('sms.all_balances', compact('businessUnits'));
    }

    /**
     * Show form to send single SMS
     */
    public function sendSingle()
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!$user->isSuperAdmin() && !$user->hasPermission('send-single-sms', $businessUnit->id)) {
            abort(403, __('common.unauthorized_action'));
        }

        // Validate sender number is configured
        if (!$businessUnit->sms_sender_number) {
            return redirect()->route('business.sms.index')
                ->with('error', __('common.sms_sender_number_not_configured'));
        }

        $clients = Client::where('business_unit_id', $businessUnit->id)
            ->whereNotNull('phone_number')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $smsBalance = AccountSms::getBalance($businessUnit->id);

        return view('sms.send_single', compact('businessUnit', 'clients', 'smsBalance'));
    }

    /**
     * Process single SMS send
     */
    public function storeSingle(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('common.no_business_unit_assigned')
                ]);
            }
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!$user->isSuperAdmin() && !$user->hasPermission('send-single-sms', $businessUnit->id)) {
            abort(403, __('common.unauthorized_action'));
        }

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'message' => 'required|string|max:160',
        ]);

        // Verify client belongs to business unit
        $client = Client::where('id', $validated['client_id'])
            ->where('business_unit_id', $businessUnit->id)
            ->first();

        if (!$client) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('common.client_not_found')
                ]);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', __('common.client_not_found'));
        }

        if (!$client->phone_number) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('common.client_phone_number_not_set')
                ]);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', __('common.client_phone_number_not_set'));
        }

        try {
            $result = $this->smsService->sendSms(
                $businessUnit->id,
                $client->phone_number,
                $validated['message'],
                $client->id
            );

            if ($result['success']) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => 1,
                        'msg' => __('common.sms_sent_successfully')
                    ]);
                }
                return redirect()->route('business.sms.index')
                    ->with('success', __('common.sms_sent_successfully'));
            } else {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => 0,
                        'msg' => $result['message']
                    ]);
                }
                return redirect()->back()
                    ->withInput()
                    ->with('error', $result['message']);
            }
        } catch (\Exception $e) {
            Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('common.something_went_wrong')
                ]);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', __('common.something_went_wrong'));
        }
    }

    /**
     * Show form to send bulk SMS
     */
    public function sendBulk()
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!$user->isSuperAdmin() && !$user->hasPermission('send-bulk-sms', $businessUnit->id)) {
            abort(403, __('common.unauthorized_action'));
        }

        // Validate sender number is configured
        if (!$businessUnit->sms_sender_number) {
            return redirect()->route('business.sms.index')
                ->with('error', __('common.sms_sender_number_not_configured'));
        }

        $clients = Client::where('business_unit_id', $businessUnit->id)
            ->whereNotNull('phone_number')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $groups = $businessUnit->clientGroups()
            ->with('clients')
            ->withCount('clients')
            ->latest()
            ->get();

        $smsBalance = AccountSms::getBalance($businessUnit->id);

        return view('sms.send_bulk', compact('businessUnit', 'clients', 'groups', 'smsBalance'));
    }

    /**
     * Process bulk SMS send
     */
    public function storeBulk(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('common.no_business_unit_assigned')
                ]);
            }
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!$user->isSuperAdmin() && !$user->hasPermission('send-bulk-sms', $businessUnit->id)) {
            abort(403, __('common.unauthorized_action'));
        }

        $validated = $request->validate([
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'exists:clients,id',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'exists:client_groups,id',
            'message' => 'required|string|max:160',
        ]);

        // Collect client IDs from groups
        $clientIdsFromGroups = [];
        if ($request->has('group_ids') && !empty($validated['group_ids'])) {
            $groups = ClientGroup::whereIn('id', $validated['group_ids'])
                ->where('business_unit_id', $businessUnit->id)
                ->with('clients')
                ->get();

            foreach ($groups as $group) {
                $clientIdsFromGroups = array_merge($clientIdsFromGroups, $group->clients->pluck('id')->toArray());
            }
        }

        // Merge client IDs from groups with individually selected clients
        $allClientIds = array_unique(array_merge(
            $validated['client_ids'] ?? [],
            $clientIdsFromGroups
        ));

        // Ensure at least one client is selected
        if (empty($allClientIds)) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('common.no_clients_selected')
                ]);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', __('common.no_clients_selected'));
        }

        // Verify clients belong to business unit and have phone numbers
        $clients = Client::whereIn('id', $allClientIds)
            ->where('business_unit_id', $businessUnit->id)
            ->whereNotNull('phone_number')
            ->get();

        if ($clients->isEmpty()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('common.no_valid_clients_selected')
                ]);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', __('common.no_valid_clients_selected'));
        }

        // Check if balance is sufficient
        $smsBalance = AccountSms::getBalance($businessUnit->id);
        if ($smsBalance < $clients->count()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('common.insufficient_sms_balance')
                ]);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', __('common.insufficient_sms_balance'));
        }

        try {
            // Prepare recipients array
            $recipients = $clients->map(function ($client) {
                return [
                    'client_id' => $client->id,
                    'phone_number' => $client->phone_number
                ];
            })->toArray();

            $result = $this->smsService->sendBulkSms(
                $businessUnit->id,
                $recipients,
                $validated['message']
            );

            if ($request->ajax()) {
                return response()->json([
                    'success' => $result['success'] ? 1 : 0,
                    'msg' => __('common.bulk_sms_sent', [
                        'total' => $result['total'],
                        'success' => $result['success_count'],
                        'failed' => $result['failure_count']
                    ]),
                    'result' => $result
                ]);
            }

            if ($result['success']) {
                return redirect()->route('business.sms.index')
                    ->with('success', __('common.bulk_sms_sent', [
                        'total' => $result['total'],
                        'success' => $result['success_count'],
                        'failed' => $result['failure_count']
                    ]));
            } else {
                return redirect()->back()
                    ->withInput()
                    ->with('warning', __('common.bulk_sms_partial_success', [
                        'total' => $result['total'],
                        'success' => $result['success_count'],
                        'failed' => $result['failure_count']
                    ]));
            }
        } catch (\Exception $e) {
            Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('common.something_went_wrong')
                ]);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', __('common.something_went_wrong'));
        }
    }

    /**
     * Show SMS message history
     */
    public function history()
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!$user->isSuperAdmin() && !$user->hasPermission('view-sms-history', $businessUnit->id)) {
            abort(403, __('common.unauthorized_action'));
        }

        return view('sms.history', compact('businessUnit'));
    }

    /**
     * Get SMS message history for DataTables
     */
    public function getHistory(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return response()->json([
                'draw' => $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'No business unit assigned'
            ]);
        }

        // Check permission
        if (!$user->isSuperAdmin() && !$user->hasPermission('view-sms-history', $businessUnit->id)) {
            return response()->json([
                'draw' => $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Unauthorized'
            ]);
        }

        $query = SmsMessage::where('business_unit_id', $businessUnit->id)
            ->with(['recipientClient', 'sentByUser'])
            ->orderBy('sent_at', 'desc');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id')) {
            $query->where('recipient_client_id', $request->client_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->date_to);
        }

        $totalRecords = $query->count();

        // Get paginated results
        $messages = $query->skip($request->input('start', 0))
            ->take($request->input('length', 10))
            ->get();

        $data = $messages->map(function ($message) {
            return [
                'id' => $message->id,
                'sent_at' => $message->sent_at ? $message->sent_at->format('Y-m-d H:i:s') : '-',
                'sender_number' => $message->sender_number,
                'recipient_number' => $message->recipient_number,
                'recipient_name' => $message->recipientClient ? $message->recipientClient->full_name : '-',
                'message_preview' => Str::limit($message->message, 50),
                'message' => $message->message,
                'status' => $message->status,
                'status_label' => ucfirst($message->status),
                'sent_by' => $message->sentByUser ? $message->sentByUser->name : '-',
                'error_message' => $message->error_message,
            ];
        });

        return response()->json([
            'draw' => $request->input('draw', 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data->values()
        ]);
    }
}
