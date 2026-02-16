<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\Client;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Services\ExportService;

class AccountController extends Controller
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

        $query = Account::where('business_unit_id', $businessUnit->id)
            ->with(['accountable']);

        // Filter by account type
        if ($request->has('account_type') && $request->account_type) {
            $query->where('account_type', $request->account_type);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('account_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHasMorph('accountable', [Client::class], function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  })
                  ->orWhereHasMorph('accountable', [Agent::class], function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('second_name', 'like', "%{$search}%");
                  });
            });
        }

        $accounts = $query->latest()->paginate(15);

        return view('accounts.index', compact('accounts', 'businessUnit'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Get client or agent ID if provided
        $accountableType = $request->get('accountable_type');
        $accountableId = $request->get('accountable_id');
        $accountable = null;

        if ($accountableType && $accountableId) {
            if ($accountableType === 'client') {
                $accountable = Client::where('business_unit_id', $businessUnit->id)->findOrFail($accountableId);
            } elseif ($accountableType === 'agent') {
                $accountable = Agent::where('business_unit_id', $businessUnit->id)->findOrFail($accountableId);
            }
        }

        $clients = Client::where('business_unit_id', $businessUnit->id)->get();
        $agents = Agent::where('business_unit_id', $businessUnit->id)->get();

        return view('accounts.create', compact('businessUnit', 'accountable', 'clients', 'agents'));
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
            'accountable_type' => 'required|in:client,agent',
            'accountable_id' => 'required|integer',
            'account_type' => 'required|in:general,float,commission',
            'account_number' => 'nullable|string|max:50',
            'balance' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:3',
            'status' => 'required|in:active,inactive,suspended,closed',
            'opening_date' => 'required|date',
            'closing_date' => 'nullable|date|after:opening_date',
            'description' => 'nullable|string',
        ]);

        // Determine the accountable model
        $accountableModel = $validated['accountable_type'] === 'client' ? Client::class : Agent::class;
        
        // Verify the accountable exists and belongs to this business unit
        $accountable = $accountableModel::where('business_unit_id', $businessUnit->id)
            ->findOrFail($validated['accountable_id']);

        // For agents, validate account type
        if ($accountableModel === Agent::class) {
            if (!in_array($validated['account_type'], ['float', 'commission'])) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', __('common.agent_accounts_must_be_float_or_commission'));
            }

            // Check if account type already exists for this agent
            $existingAccount = Account::where('accountable_type', Agent::class)
                ->where('accountable_id', $accountable->id)
                ->where('account_type', $validated['account_type'])
                ->exists();

            if ($existingAccount) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', __('common.agent_already_has_account_type'));
            }
        } else {
            // For clients, account type must be general
            $validated['account_type'] = 'general';
        }

        // Generate account number if not provided
        if (empty($validated['account_number'])) {
            $validated['account_number'] = Account::generateAccountNumber(
                $businessUnit->id,
                $validated['account_type'],
                $accountable->id
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

        $validated['business_unit_id'] = $businessUnit->id;
        $validated['accountable_type'] = $accountableModel;
        $validated['balance'] = $validated['balance'] ?? 0.00;
        $validated['currency'] = $validated['currency'] ?? config('currency.default', 'CFA');

        Account::create($validated);

        return redirect()->route('accounts.index')
            ->with('success', __('common.created_successfully'));
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

        $account = Account::where('business_unit_id', $businessUnit->id)
            ->with(['accountable', 'businessUnit'])
            ->findOrFail($id);

        return view('accounts.show', compact('account', 'businessUnit'));
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

        $account = Account::where('business_unit_id', $businessUnit->id)
            ->with(['accountable'])
            ->findOrFail($id);

        return view('accounts.edit', compact('account', 'businessUnit'));
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

        $account = Account::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'account_number' => 'required|string|max:50',
            'balance' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'status' => 'required|in:active,inactive,suspended,closed',
            'opening_date' => 'required|date',
            'closing_date' => 'nullable|date|after:opening_date',
            'description' => 'nullable|string',
        ]);

        // Check account number uniqueness if changed
        if (isset($validated['account_number']) && $validated['account_number'] !== $account->account_number) {
            $exists = Account::where('business_unit_id', $businessUnit->id)
                ->where('account_number', $validated['account_number'])
                ->where('id', '!=', $account->id)
                ->exists();

            if ($exists) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', __('common.account_number_already_exists'));
            }
        }

        // Set default currency if not provided
        if (empty($validated['currency'])) {
            $validated['currency'] = config('currency.default', 'CFA');
        }

        $account->update($validated);

        return redirect()->route('accounts.index')
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

        $account = Account::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        // Prevent deletion of required agent accounts
        if ($account->isRequired()) {
            return redirect()->route('accounts.index')
                ->with('error', __('common.cannot_delete_required_account'));
        }

        $account->delete();

        return redirect()->route('accounts.index')
            ->with('success', __('common.deleted_successfully'));
    }

    /**
     * Export accounts.
     */
    public function export(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $format = $request->get('format', 'csv');
        $query = Account::where('business_unit_id', $businessUnit->id)
            ->with(['accountable']);

        // Apply same filters as index
        if ($request->has('account_type') && $request->account_type) {
            $query->where('account_type', $request->account_type);
        }
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('account_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHasMorph('accountable', [Client::class], function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  })
                  ->orWhereHasMorph('accountable', [Agent::class], function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('second_name', 'like', "%{$search}%");
                  });
            });
        }

        $accounts = $query->latest()->get();

        // Transform data for export
        $data = $accounts->map(function ($account) {
            $accountableName = '-';
            if ($account->accountable) {
                if ($account->accountable_type == Client::class) {
                    $accountableName = $account->accountable->full_name;
                } elseif ($account->accountable_type == Agent::class) {
                    $accountableName = $account->accountable->full_name;
                }
            }

            return [
                'Account Number' => $account->account_number,
                'Account Type' => ucfirst($account->account_type ?? '-'),
                'Owner' => $accountableName,
                'Owner Type' => $account->accountable_type ? class_basename($account->accountable_type) : '-',
                'Balance' => number_format($account->balance, 2),
                'Currency' => $account->currency ?? '-',
                'Status' => ucfirst($account->status ?? 'active'),
                'Opening Date' => $account->opening_date ? $account->opening_date->format('Y-m-d') : '-',
                'Created At' => $account->created_at->format('Y-m-d H:i'),
            ];
        });

        $headers = ['Account Number', 'Account Type', 'Owner', 'Owner Type', 'Balance', 'Currency', 'Status', 'Opening Date', 'Created At'];
        $filters = $request->only(['account_type', 'status', 'search']);
        $filename = $this->exportService->generateFilename('accounts', $filters);

        switch ($format) {
            case 'excel':
                return $this->exportService->exportExcel($data, $headers, $filename, 'Accounts');
            case 'pdf':
                return $this->exportService->exportPdf($data, $headers, $filename, 'Accounts Export', $filters);
            case 'csv':
            default:
                return $this->exportService->exportCsv($data, $headers, $filename);
        }
    }
}
