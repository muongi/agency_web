<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Account;
use App\Models\BusinessUnit;
use App\Services\ExportService;
use Illuminate\Support\Facades\Storage;

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

        $clients = $query->latest()->paginate(15);

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

        return view('clients.create', compact('businessUnit'));
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
            'salutation' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'id_passport' => 'nullable|string|max:255',
            'home_address' => 'nullable|string',
            'phone_number' => 'nullable|string|max:50',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'gender' => 'nullable|in:Male,Female,Other',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'year_of_birth' => 'nullable|string|max:10',
            'region' => 'nullable|string|max:255',
            'home_town' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'next_of_kin_first_name' => 'nullable|string|max:255',
            'next_of_kin_middle_name' => 'nullable|string|max:255',
            'next_of_kin_last_name' => 'nullable|string|max:255',
            'next_of_kin_phone_number' => 'nullable|string|max:50',
            'next_of_kin_address' => 'nullable|string',
            'next_of_kin_gender' => 'nullable|in:Male,Female,Other',
        ]);

        $validated['business_unit_id'] = $businessUnit->id;

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $photoName = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
            $photoPath = $photo->storeAs('client-photos', $photoName, 'public');
            $validated['photo'] = $photoPath;
        }

        Client::create($validated);

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

        return view('clients.show', compact('client', 'businessUnit', 'transactions', 'activeTab'));
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

        return view('clients.edit', compact('client', 'businessUnit'));
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

        $validated = $request->validate([
            'salutation' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'id_passport' => 'nullable|string|max:255',
            'home_address' => 'nullable|string',
            'phone_number' => 'nullable|string|max:50',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'gender' => 'nullable|in:Male,Female,Other',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'year_of_birth' => 'nullable|string|max:10',
            'region' => 'nullable|string|max:255',
            'home_town' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'next_of_kin_first_name' => 'nullable|string|max:255',
            'next_of_kin_middle_name' => 'nullable|string|max:255',
            'next_of_kin_last_name' => 'nullable|string|max:255',
            'next_of_kin_phone_number' => 'nullable|string|max:50',
            'next_of_kin_address' => 'nullable|string',
            'next_of_kin_gender' => 'nullable|in:Male,Female,Other',
        ]);

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
}
