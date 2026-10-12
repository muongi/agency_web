<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\CollectorCap;
use App\Services\CollectorCapService;
use App\Services\ExportService;
use App\Services\KatoService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    protected $exportService;

    protected $smsService;

    public function __construct(ExportService $exportService, SmsService $smsService)
    {
        $this->middleware(['auth', 'business.access']);
        $this->exportService = $exportService;
        $this->smsService = $smsService;
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
            if ($cbs && strtolower($cbs->code) === 'kato' && ($cbs->base_url || config('kato.default_base_url'))) {
                $katoService = new KatoService();
                $result = $katoService->registerAgent($agent, $businessUnit);

                if ($result['success']) {
                    $agent->update([
                        'kato_agent_id' => $result['data']['agentId'],
                        'kato_registration_error' => null,
                    ]);
                    if (! empty($result['data']['accounts'])) {
                        $katoService->syncAgentAccountsFromKato(
                            $agent,
                            $businessUnit,
                            $result['data']['accounts']
                        );
                    }
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

        $isKato = $businessUnit->isKatoEnabled();
        $katoFloatSync = null;
        $katoFloatSyncedAt = null;

        if ($isKato && $activeTab === 'accounts') {
            $katoService = new KatoService();
            $katoFloatSync = $katoService->syncAgentFloatBalancesFromKato($agent, $businessUnit);

            if ($katoFloatSync['success'] ?? false) {
                $katoFloatSyncedAt = now();
                $agent->load('accounts');
            }
        }

        $collector = app(CollectorCapService::class)->status($agent->id);
        $agentCap = CollectorCap::where('agent_id', $agent->id)->where('is_active', true)->first();

        return view('agents.show', compact(
            'collector',
            'agentCap',
            'agent',
            'businessUnit',
            'availableTerminals',
            'transactions',
            'activeTab',
            'isKato',
            'katoFloatSync',
            'katoFloatSyncedAt'
        ));
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
            if ($cbs && strtolower($cbs->code) === 'kato' && ($cbs->base_url || config('kato.default_base_url'))) {
                $katoService = new KatoService();
                $result = $katoService->registerAgent($agent, $businessUnit);

                if ($result['success']) {
                    $agent->update([
                        'kato_agent_id' => $result['data']['agentId'],
                        'kato_registration_error' => null,
                    ]);
                    if (! empty($result['data']['accounts'])) {
                        $katoService->syncAgentAccountsFromKato(
                            $agent,
                            $businessUnit,
                            $result['data']['accounts']
                        );
                    }
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
     * Grant or revoke SUNU card write / rewrite rights (TPE "Écrire la carte").
     * Separate from update() so only holders of manage-agent-card-rights can change them.
     */
    public function updateCardRights(Request $request, $id)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;

        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->hasPermission('manage-agent-card-rights', $businessUnit->id)) {
            abort(403);
        }

        $agent = $businessUnit->agents()->findOrFail($id);

        $canRewrite = $request->boolean('can_rewrite_cards');
        $canWrite = $canRewrite || $request->boolean('can_write_cards');

        $agent->update([
            'can_write_cards' => $canWrite,
            'can_rewrite_cards' => $canRewrite,
        ]);

        Log::info('SUNU_CARD_RIGHTS_CHANGED', [
            'agent_id' => $agent->id,
            'can_write_cards' => $canWrite,
            'can_rewrite_cards' => $canRewrite,
            'by_user_id' => $user->id,
        ]);

        return redirect()->back()
            ->with('success', __('common.card_rights_updated'));
    }

    /**
     * Collection limit of one agent: business-unit default, or an amount of its own.
     */
    public function updateCollectorCap(Request $request, $id)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;

        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('manage-sunu-collector-caps', $businessUnit->id)) {
            abort(403);
        }

        $agent = $businessUnit->agents()->findOrFail($id);
        $data = $request->validate([
            'mode' => 'required|in:default,custom',
            'cap_amount' => 'required_if:mode,custom|nullable|numeric|min:1',
            'warning_pct' => 'nullable|integer|min:1|max:100',
        ]);

        if ($data['mode'] === 'custom') {
            CollectorCap::updateOrCreate(['agent_id' => $agent->id], [
                'cap_amount' => $data['cap_amount'],
                'warning_threshold_pct' => $data['warning_pct'] ?? 80,
                'currency' => 'XOF',
                'is_active' => true,
            ]);
        } else {
            CollectorCap::where('agent_id', $agent->id)->update(['is_active' => false]);
        }

        $caps = app(CollectorCapService::class);
        $caps->refreshBlock($agent->id, $user->id);

        Log::info('SUNU_COLLECTOR_CAP_CHANGED', [
            'agent_id' => $agent->id,
            'scope' => $data['mode'],
            'cap_amount' => $data['mode'] === 'custom' ? (float) $data['cap_amount'] : null,
            'by_user_id' => $user->id,
        ]);

        return redirect()->route('agents.show', ['agent' => $agent->id, 'tab' => 'terminals'])
            ->with('success', __('common.collector_cap_saved'));
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
                __('common.export_col_name') => $agent->full_name,
                __('common.export_col_username') => $agent->username ?? '-',
                __('common.export_col_email') => $agent->email ?? '-',
                __('common.export_col_phone') => $agent->phone_number ?? '-',
                __('common.export_col_gender') => $this->exportService->valueLabel($agent->gender, 'gender_') ?: '-',
                __('common.export_col_year_of_birth') => $agent->year_of_birth ?? '-',
                __('common.export_col_terminals_count') => $agent->terminals_count ?? 0,
                __('common.export_col_created_at') => $agent->created_at->format('Y-m-d H:i'),
            ];
        });

        $headers = array_map(fn ($k) => __('common.export_col_'.$k), ['name', 'username', 'email', 'phone', 'gender', 'year_of_birth', 'terminals_count', 'created_at']);
        $filters = $request->only(['search']);
        $filename = $this->exportService->generateFilename('agents', $filters);

        switch ($format) {
            case 'excel':
                return $this->exportService->exportExcel($data, $headers, $filename, __('common.agents'));
            case 'pdf':
                return $this->exportService->exportPdf($data, $headers, $filename, __('common.agents'), $filters);
            case 'csv':
            default:
                return $this->exportService->exportCsv($data, $headers, $filename);
        }
    }

    /**
     * Reset the agent's collection PIN (confirms premiums on the TPE). Never changes the login password.
     * The PIN is sent by SMS; when SMS fails or is not configured it is shown once on screen.
     * With config sunu.default_agent_pin set (tests), "use_default" sets that PIN instead of a random one.
     */
    public function sendPinResetOtp(Request $request, $id)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! auth()->user()->hasPermission('edit-agent', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $agent = $businessUnit->agents()->findOrFail($id);

        $default = (string) config('sunu.default_agent_pin');
        $useDefault = $request->boolean('use_default') && preg_match('/^\d{4}$/', $default);
        $pin = $useDefault ? $default : $this->generateNumericPin(4);

        $agent->setCollectionPin($pin, true);

        Log::info('SUNU_AGENT_PIN_RESET', [
            'agent_id' => $agent->id,
            'business_unit_id' => $businessUnit->id,
            'default_pin' => $useDefault,
            'reset_by_user_id' => auth()->id(),
        ]);

        if ($useDefault) {
            return redirect()->back()
                ->with('success', __('common.sunu_pin_reset_default', ['pin' => $pin]));
        }

        $smsResult = ['success' => false, 'message' => 'no phone number'];
        if ($agent->phone_number) {
            try {
                $smsResult = $this->smsService->sendSms(
                    $businessUnit->id,
                    $agent->phone_number,
                    __('sms.agent_pin_reset_web', ['pin' => $pin], 'fr'),
                    null,
                    "AGENT-PIN-RESET-{$agent->id}"
                );
            } catch (\Throwable $e) {
                $smsResult = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        if (! ($smsResult['success'] ?? false)) {
            Log::warning('SUNU_AGENT_PIN_RESET_SMS_FAILED', [
                'agent_id' => $agent->id,
                'business_unit_id' => $businessUnit->id,
                'error' => $smsResult['message'] ?? 'unknown',
            ]);

            // Shown once to the supervisor, who gives it to the agent
            return redirect()->back()
                ->with('warning', __('common.sunu_pin_reset_sms_failed'))
                ->with('pin_reveal', $pin);
        }

        return redirect()->back()
            ->with('success', __('common.sunu_pin_reset_sms_sent'));
    }

    /**
     * Generate a numeric PIN string of the given length.
     */
    protected function generateNumericPin(int $length = 4): string
    {
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
