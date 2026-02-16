<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Services\ExportService;
use App\Services\KatoService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
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

        $query = $businessUnit->agents()->withCount('terminals');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('second_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $agents = $query->latest()->paginate(15);

        return view('agents.index', compact('agents', 'businessUnit'));
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

        return view('agents.create', compact('businessUnit'));
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
            'first_name' => 'required|string|max:255',
            'second_name' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'email' => 'required|email|unique:agents,email',
            'phone_number' => 'required|string|max:50',
            'id_passport' => 'required|string|max:255',
            'physical_address' => 'nullable|string',
            'po_box' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:agents,username',
            'password' => 'required|string|min:8',
        ]);

        $validated['business_unit_id'] = $businessUnit->id;
        $validated['password'] = Hash::make($validated['password']);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $photoName = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
            $photoPath = $photo->storeAs('agent-photos', $photoName, 'public');
            $validated['photo'] = $photoPath;
        }

        $agent = Agent::create($validated);

        // Attempt KATO registration if business unit is registered with KATO
        if ($businessUnit->core_banking_system_id && $businessUnit->kato_company_id) {
            $cbs = $businessUnit->coreBankingSystem;
            if ($cbs && strtolower($cbs->code) === 'kato' && $cbs->base_url) {
                $katoService = new KatoService();
                $result = $katoService->registerAgent($agent, $businessUnit);
                
                if ($result['success']) {
                    $agent->update([
                        'kato_agent_id' => $result['data']['agentId'],
                        'kato_registration_error' => null,
                    ]);
                    return redirect()->route('agents.index')
                        ->with('success', __('common.created_successfully') . '. ' . __('common.kato_agent_registration_success'));
                } else {
                    $agent->update([
                        'kato_agent_id' => null,
                        'kato_registration_error' => $result['error'],
                    ]);
                    Log::error('KATO agent registration failed', [
                        'agent_id' => $agent->id,
                        'error' => $result['error']
                    ]);
                    return redirect()->route('agents.index')
                        ->with('warning', __('common.created_successfully') . '. ' . __('common.kato_agent_registration_failed') . ': ' . $result['error']);
                }
            }
        }

        return redirect()->route('agents.index')
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

        $agent = $businessUnit->agents()->with(['terminals', 'accounts'])->findOrFail($id);
        
        // Get terminals that are not currently actively assigned to any agent
        // (either never assigned, or unassigned_at is not null)
        $availableTerminals = $businessUnit->terminals()
            ->whereDoesntHave('agents', function($query) {
                $query->whereNull('agent_terminal.unassigned_at');
            })
            ->get();

        // Get agent transactions
        $transactionsQuery = \App\Models\Transaction::where('agent_id', $agent->id)
            ->where('business_unit_id', $businessUnit->id)
            ->with(['transactionType', 'terminal', 'client'])
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

        // Get active tab from request, default to 'information'
        $activeTab = $request->get('tab', 'information');
        
        // Ensure tab is valid
        $validTabs = ['information', 'accounts', 'terminals', 'transactions'];
        if (!in_array($activeTab, $validTabs)) {
            $activeTab = 'information';
        }

        return view('agents.show', compact('agent', 'businessUnit', 'availableTerminals', 'transactions', 'activeTab'));
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

        $agent = $businessUnit->agents()->findOrFail($id);

        return view('agents.edit', compact('agent', 'businessUnit'));
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

        $agent = $businessUnit->agents()->findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'second_name' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'email' => 'required|email|unique:agents,email,' . $id,
            'phone_number' => 'required|string|max:50',
            'id_passport' => 'required|string|max:255',
            'physical_address' => 'nullable|string',
            'po_box' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:agents,username,' . $id,
            'password' => 'nullable|string|min:8',
        ]);

        // Handle password update
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($agent->photo && Storage::disk('public')->exists($agent->photo)) {
                Storage::disk('public')->delete($agent->photo);
            }
            
            $photo = $request->file('photo');
            $photoName = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
            $photoPath = $photo->storeAs('agent-photos', $photoName, 'public');
            $validated['photo'] = $photoPath;
        } else {
            // Keep existing photo if no new one uploaded
            unset($validated['photo']);
        }

        $agent->update($validated);

        // Attempt KATO registration if agent doesn't have kato_agent_id yet
        if (!$agent->kato_agent_id && $businessUnit->core_banking_system_id && $businessUnit->kato_company_id) {
            $cbs = $businessUnit->coreBankingSystem;
            if ($cbs && strtolower($cbs->code) === 'kato' && $cbs->base_url) {
                $katoService = new KatoService();
                $result = $katoService->registerAgent($agent, $businessUnit);
                
                if ($result['success']) {
                    $agent->update([
                        'kato_agent_id' => $result['data']['agentId'],
                        'kato_registration_error' => null,
                    ]);
                    return redirect()->route('agents.index')
                        ->with('success', __('common.updated_successfully') . '. ' . __('common.kato_agent_registration_success'));
                } else {
                    $agent->update([
                        'kato_agent_id' => null,
                        'kato_registration_error' => $result['error'],
                    ]);
                    Log::error('KATO agent registration failed', [
                        'agent_id' => $agent->id,
                        'error' => $result['error']
                    ]);
                    return redirect()->route('agents.index')
                        ->with('warning', __('common.updated_successfully') . '. ' . __('common.kato_agent_registration_failed') . ': ' . $result['error']);
                }
            }
        }

        return redirect()->route('agents.index')
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

        $agent = $businessUnit->agents()->findOrFail($id);

        // Delete photo if exists
        if ($agent->photo && Storage::disk('public')->exists($agent->photo)) {
            Storage::disk('public')->delete($agent->photo);
        }

        $agent->delete();

        return redirect()->route('agents.index')
            ->with('success', __('common.deleted_successfully'));
    }

    /**
     * Assign terminal to agent.
     */
    public function assignTerminal(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $agent = $businessUnit->agents()->findOrFail($id);

        $validated = $request->validate([
            'terminal_id' => 'required|exists:terminals,id',
        ]);

        // Verify terminal belongs to same business unit
        $terminal = $businessUnit->terminals()->findOrFail($validated['terminal_id']);

        // Check if already actively assigned
        if ($agent->terminals()->where('terminals.id', $validated['terminal_id'])->exists()) {
            return redirect()->back()
                ->with('error', __('common.terminal_already_assigned'));
        }

        // Check if previously assigned (unassigned)
        $existingAssignment = $agent->terminalAssignments()
            ->where('terminals.id', $validated['terminal_id'])
            ->whereNotNull('agent_terminal.unassigned_at')
            ->first();

        if ($existingAssignment) {
            // Reactivate the assignment
            DB::table('agent_terminal')
                ->where('agent_id', $agent->id)
                ->where('terminal_id', $validated['terminal_id'])
                ->update([
                    'assigned_at' => now(),
                    'unassigned_at' => null,
                    'updated_at' => now(),
                ]);
        } else {
            // Create new assignment
            $agent->terminals()->attach($validated['terminal_id'], [
                'assigned_at' => now(),
                'unassigned_at' => null,
            ]);
        }

        return redirect()->back()
            ->with('success', __('common.terminal_assigned_successfully'));
    }

    /**
     * Unassign terminal from agent.
     */
    public function unassignTerminal(Request $request, $id, $terminalId)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $agent = $businessUnit->agents()->findOrFail($id);
        $terminal = $businessUnit->terminals()->findOrFail($terminalId);

        // Set unassigned_at instead of detaching to keep history
        DB::table('agent_terminal')
            ->where('agent_id', $agent->id)
            ->where('terminal_id', $terminalId)
            ->whereNull('unassigned_at')
            ->update([
                'unassigned_at' => now(),
                'updated_at' => now(),
            ]);

        return redirect()->back()
            ->with('success', __('common.terminal_unassigned_successfully'));
    }

    /**
     * Export agents.
     */
    public function export(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $format = $request->get('format', 'csv');
        $query = $businessUnit->agents()->withCount('terminals');

        // Apply same filters as index
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('second_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $agents = $query->latest()->get();

        // Transform data for export
        $data = $agents->map(function ($agent) {
            return [
                'Name' => $agent->full_name,
                'Username' => $agent->username ?? '-',
                'Email' => $agent->email ?? '-',
                'Phone Number' => $agent->phone_number ?? '-',
                'Gender' => $agent->gender ?? '-',
                'Year of Birth' => $agent->year_of_birth ?? '-',
                'Terminals Count' => $agent->terminals_count ?? 0,
                'Created At' => $agent->created_at->format('Y-m-d H:i'),
            ];
        });

        $headers = ['Name', 'Username', 'Email', 'Phone Number', 'Gender', 'Year of Birth', 'Terminals Count', 'Created At'];
        $filters = $request->only(['search']);
        $filename = $this->exportService->generateFilename('agents', $filters);

        switch ($format) {
            case 'excel':
                return $this->exportService->exportExcel($data, $headers, $filename, 'Agents');
            case 'pdf':
                return $this->exportService->exportPdf($data, $headers, $filename, 'Agents Export', $filters);
            case 'csv':
            default:
                return $this->exportService->exportCsv($data, $headers, $filename);
        }
    }
}
