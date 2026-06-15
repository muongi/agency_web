<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Account;
use App\Models\BusinessUnit;
use App\Services\ExportService;
use App\Services\KatoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientController extends Controller
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

        $query = $businessUnit->clients();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('id_passport', 'like', "%{$search}%");
            });
        }

        $clients = $query->with([
            'accounts' => function ($q) {
                $q->where('account_type', 'general')
                    ->orderBy('kato_account_code')
                    ->orderBy('id');
            },
        ])->latest()->paginate(15);

        return view('clients.index', compact('clients', 'businessUnit'));
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

        $isKato = $businessUnit->isKatoEnabled();

        return view('clients.create', compact('businessUnit', 'isKato'));
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

        $validated = $request->validate(
            $this->clientValidationRules($businessUnit),
            $this->clientValidationMessages($businessUnit)
        );

        $validated['business_unit_id'] = $businessUnit->id;

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $photoName = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
            $photoPath = $photo->storeAs('client-photos', $photoName, 'public');
            $validated['photo'] = $photoPath;
        }

        $client = Client::create($validated);

        if ($businessUnit->isKatoEnabled()) {
            $result = $this->syncClientWithKato($client, $businessUnit);

            if ($result['success'] && empty($result['already_synced'])) {
                $message = __('common.created_successfully').'. '.__('common.kato_member_registration_success');
                if (! empty($result['data']['memberNo'])) {
                    $message .= ' ('.$result['data']['memberNo'].')';
                }

                return redirect()->route('clients.index')->with('success', $message);
            }

            return redirect()->route('clients.index')
                ->with('warning', __('common.created_successfully').'. '.__('common.kato_member_registration_failed').': '.($result['error'] ?? ''));
        }

        return redirect()->route('clients.index')
            ->with('success', __('common.created_successfully'));
    }

    /**
     * Display the specified resource.
     */
    public function show($id, Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $client = $businessUnit->clients()->with('accounts')->findOrFail($id);

        // Get client transactions
        $transactionsQuery = \App\Models\Transaction::where('client_id', $client->id)
            ->where('business_unit_id', $businessUnit->id)
            ->with(['transactionType', 'agent', 'terminal'])
            ->orderBy('transaction_date', 'desc');

        // Apply filters if provided
        if ($request->filled('start_date')) {
            $transactionsQuery->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $transactionsQuery->whereDate('transaction_date', '<=', $request->end_date);
        }
        if ($request->filled('status')) {
            $transactionsQuery->where('status', $request->status);
        }

        $transactions = $transactionsQuery->paginate(20);

        // Get active tab from request
        $activeTab = $request->get('tab', 'information');
        
        // Ensure tab is valid
        $validTabs = ['information', 'accounts', 'transactions'];
        if (!in_array($activeTab, $validTabs)) {
            $activeTab = 'information';
        }

        $isKato = $businessUnit->isKatoEnabled();

        return view('clients.show', compact('client', 'businessUnit', 'transactions', 'activeTab', 'isKato'));
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

        $client = $businessUnit->clients()->findOrFail($id);

        $isKato = $businessUnit->isKatoEnabled();

        return view('clients.edit', compact('client', 'businessUnit', 'isKato'));
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

        $client = $businessUnit->clients()->findOrFail($id);

        $validated = $request->validate(
            $this->clientValidationRules($businessUnit),
            $this->clientValidationMessages($businessUnit)
        );

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($client->photo && Storage::disk('public')->exists($client->photo)) {
                Storage::disk('public')->delete($client->photo);
            }
            
            $photo = $request->file('photo');
            $photoName = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
            $photoPath = $photo->storeAs('client-photos', $photoName, 'public');
            $validated['photo'] = $photoPath;
        } else {
            // Keep existing photo if no new one uploaded
            unset($validated['photo']);
        }

        $client->update($validated);

        return redirect()->route('clients.index')
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

        $client = $businessUnit->clients()->findOrFail($id);

        // Delete photo if exists
        if ($client->photo && Storage::disk('public')->exists($client->photo)) {
            Storage::disk('public')->delete($client->photo);
        }

        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', __('common.deleted_successfully'));
    }

    /**
     * Store a new account for a client.
     */
    public function storeAccount(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $client = $businessUnit->clients()->findOrFail($id);

        $validated = $request->validate([
            'account_number' => 'nullable|string|max:50',
            'balance' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'status' => 'required|in:active,inactive,suspended,closed',
            'opening_date' => 'required|date',
            'closing_date' => 'nullable|date|after:opening_date',
            'description' => 'nullable|string',
        ]);

        if (empty($validated['account_number']) && $businessUnit->isKatoEnabled()) {
            $katoAccounts = $client->kato_api_response['data']['accounts'] ?? [];
            if (! empty($katoAccounts)) {
                $syncResult = (new KatoService())->syncMemberAccountsFromKato($client, $businessUnit, $katoAccounts);
                if ($syncResult['created'] > 0 || $syncResult['updated'] > 0) {
                    return redirect()->route('clients.show', ['client' => $client->id, 'tab' => 'accounts'])
                        ->with('success', __('common.account_created_successfully'));
                }
                if (! empty($syncResult['errors'])) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', implode('; ', $syncResult['errors']));
                }
            }
        }

        // Generate account number if not provided
        if (empty($validated['account_number'])) {
            $validated['account_number'] = Account::generateAccountNumber(
                $businessUnit->id,
                'general',
                $client->id
            );
        } else {
            // Check if account number is unique
            $exists = Account::where('business_unit_id', $businessUnit->id)
                ->where('account_number', $validated['account_number'])
                ->exists();

            if ($exists) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', __('common.account_number_already_exists'));
            }
        }

        Account::create([
            'business_unit_id' => $businessUnit->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => $validated['account_number'],
            'balance' => $validated['balance'] ?? 0.00,
            'currency' => $validated['currency'] ?? config('currency.default', 'CFA'),
            'status' => $validated['status'],
            'opening_date' => $validated['opening_date'],
            'closing_date' => $validated['closing_date'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('clients.show', $client->id)
            ->with('success', __('common.account_created_successfully'));
    }

    /**
     * Export clients.
     */
    public function export(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $format = $request->get('format', 'csv');
        $query = $businessUnit->clients();

        // Apply same filters as index
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('id_passport', 'like', "%{$search}%");
            });
        }

        $clients = $query->latest()->get();

        // Transform data for export
        $data = $clients->map(function ($client) {
            return [
                'Name' => $client->full_name,
                'Email' => $client->email ?? '-',
                'Phone Number' => $client->phone_number ?? '-',
                'ID/Passport' => $client->id_passport ?? '-',
                'Gender' => $client->gender ?? '-',
                'Year of Birth' => $client->year_of_birth ?? '-',
                'Region' => $client->region ?? '-',
                'Home Town' => $client->home_town ?? '-',
                'Nationality' => $client->nationality ?? '-',
                'Created At' => $client->created_at->format('Y-m-d H:i'),
            ];
        });

        $headers = ['Name', 'Email', 'Phone Number', 'ID/Passport', 'Gender', 'Year of Birth', 'Region', 'Home Town', 'Nationality', 'Created At'];
        $filters = $request->only(['search']);
        $filename = $this->exportService->generateFilename('clients', $filters);

        switch ($format) {
            case 'excel':
                return $this->exportService->exportExcel($data, $headers, $filename, 'Clients');
            case 'pdf':
                return $this->exportService->exportPdf($data, $headers, $filename, 'Clients Export', $filters);
            case 'csv':
            default:
                return $this->exportService->exportCsv($data, $headers, $filename);
        }
    }

    /**
     * Sync a single client with KATO (member register).
     */
    public function syncWithKato($id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! $this->canSyncKatoClients()) {
            return redirect()->back()->with('error', __('common.unauthorized_access'));
        }

        if (! $businessUnit->isKatoEnabled()) {
            return redirect()->back()->with('error', __('common.kato_company_not_registered'));
        }

        $client = $businessUnit->clients()->findOrFail($id);
        $result = $this->syncClientWithKato($client, $businessUnit);

        if (! empty($result['already_synced'])) {
            return redirect()->back()->with('info', __('common.kato_member_already_synced'));
        }

        if ($result['success']) {
            $message = __('common.kato_member_sync_success');
            if (! empty($result['data']['memberNo'])) {
                $message .= ' ('.$result['data']['memberNo'].')';
            }

            return redirect()->back()->with('success', $message);
        }

        return redirect()->back()
            ->with('error', __('common.kato_member_sync_failed').': '.($result['error'] ?? ''));
    }

    /**
     * Bulk sync selected clients with KATO.
     */
    public function syncWithKatoBulk(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! $this->canSyncKatoClients()) {
            return redirect()->back()->with('error', __('common.unauthorized_access'));
        }

        if (! $businessUnit->isKatoEnabled()) {
            return redirect()->back()->with('error', __('common.kato_company_not_registered'));
        }

        $validated = $request->validate([
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'integer',
        ]);

        $synced = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        $clients = $businessUnit->clients()
            ->whereIn('id', $validated['client_ids'])
            ->get();

        foreach ($clients as $client) {
            $result = $this->syncClientWithKato($client, $businessUnit);

            if (! empty($result['already_synced'])) {
                $skipped++;
                continue;
            }

            if ($result['success']) {
                $synced++;
            } else {
                $failed++;
                $errors[] = $client->full_name.': '.($result['error'] ?? __('common.kato_member_sync_failed'));
            }
        }

        $summary = __('common.kato_bulk_sync_summary', [
            'synced' => $synced,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);

        if ($failed > 0) {
            return redirect()->route('clients.index')
                ->with('warning', $summary.' '.implode('; ', array_slice($errors, 0, 3)));
        }

        return redirect()->route('clients.index')->with('success', $summary);
    }

    /**
     * @return array<string, mixed>
     */
    private function syncClientWithKato(Client $client, BusinessUnit $businessUnit): array
    {
        if ($client->kato_member_no) {
            return [
                'success' => true,
                'already_synced' => true,
                'data' => ['memberNo' => $client->kato_member_no],
            ];
        }

        $katoService = new KatoService();
        $result = $katoService->registerMember($client, $businessUnit);

        $accountSyncSummary = null;
        if ($result['success']) {
            $accountSyncSummary = (new KatoService())->syncMemberAccountsFromKato(
                $client,
                $businessUnit,
                $result['data']['accounts'] ?? []
            );
        }

        $registrationError = $result['success'] ? null : ($result['error'] ?? 'Member registration failed');
        if ($result['success'] && $accountSyncSummary && ! empty($accountSyncSummary['errors'])) {
            $registrationError = implode('; ', $accountSyncSummary['errors']);
        }

        $client->update([
            'kato_member_no' => $result['data']['memberNo'] ?? null,
            'kato_registration_error' => $registrationError,
            'kato_api_response' => $result['full_response'] ?? null,
        ]);

        if (! $result['success']) {
            Log::error('KATO member sync failed', [
                'client_id' => $client->id,
                'error' => $result['error'] ?? null,
            ]);
        } elseif ($accountSyncSummary && ! empty($accountSyncSummary['errors'])) {
            Log::warning('KATO member account sync partial failure', [
                'client_id' => $client->id,
                'errors' => $accountSyncSummary['errors'],
            ]);
        }

        $result['account_sync'] = $accountSyncSummary;

        return $result;
    }

    private function canSyncKatoClients(): bool
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;

        return $user->isSuperAdmin()
            || ($businessUnit && $user->hasPermission('edit-client', $businessUnit->id));
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    private function clientValidationRules(BusinessUnit $businessUnit): array
    {
        $businessUnit->loadMissing('coreBankingSystem');

        $rules = [
            'salutation' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'home_address' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'region' => 'nullable|string|max:255',
            'home_town' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'next_of_kin_first_name' => 'nullable|string|max:255',
            'next_of_kin_middle_name' => 'nullable|string|max:255',
            'next_of_kin_last_name' => 'nullable|string|max:255',
            'next_of_kin_phone_number' => 'nullable|string|max:50',
            'next_of_kin_address' => 'nullable|string',
            'next_of_kin_gender' => 'nullable|in:Male,Female,Other',
        ];

        if ($businessUnit->isKatoEnabled()) {
            $rules['gender'] = ['required', Rule::in(['Male', 'Female'])];
            $rules['id_passport'] = 'required|string|max:255';
            $rules['phone_number'] = 'required|string|max:50';
            $rules['year_of_birth'] = 'required|string|max:10';
        } else {
            $rules['gender'] = 'nullable|in:Male,Female,Other';
            $rules['id_passport'] = 'nullable|string|max:255';
            $rules['phone_number'] = 'nullable|string|max:50';
            $rules['year_of_birth'] = 'nullable|string|max:10';
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function clientValidationMessages(BusinessUnit $businessUnit): array
    {
        if (! $businessUnit->isKatoEnabled()) {
            return [];
        }

        return [
            'gender.required' => __('common.gender_required_for_kato'),
            'gender.in' => __('common.gender_required_for_kato'),
            'id_passport.required' => __('common.kato_member_id_required'),
            'phone_number.required' => __('common.kato_member_phone_required'),
            'year_of_birth.required' => __('common.kato_member_dob_required'),
        ];
    }
}
