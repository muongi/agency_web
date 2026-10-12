<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Terminal;
use App\Models\BusinessUnit;
use App\Services\ExportService;

class TerminalController extends Controller
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

        $query = $businessUnit->terminals();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                  ->orWhere('simcard_number', 'like', "%{$search}%")
                  ->orWhere('device_alias', 'like', "%{$search}%")
                  ->orWhere('model_make', 'like', "%{$search}%");
            });
        }

        $terminals = $query->latest()->paginate(15);

        return view('terminals.index', compact('terminals', 'businessUnit'));
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

        return view('terminals.create', compact('businessUnit'));
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
            'serial_number' => 'required|string|max:255|unique:terminals,serial_number',
            'simcard_number' => 'required|string|max:255',
            'device_alias' => 'required|string|max:255',
            'model_make' => 'required|string|max:255',
        ]);

        $validated['business_unit_id'] = $businessUnit->id;

        Terminal::create($validated);

        return redirect()->route('terminals.index')
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

        $terminal = $businessUnit->terminals()
            ->with(['agents', 'agentAssignments'])
            ->findOrFail($id);

        // Get terminal transactions
        $transactionsQuery = \App\Models\Transaction::where('terminal_id', $terminal->id)
            ->where('business_unit_id', $businessUnit->id)
            ->with(['transactionType', 'agent', 'client'])
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
        $validTabs = ['information', 'agents', 'transactions'];
        if (!in_array($activeTab, $validTabs)) {
            $activeTab = 'information';
        }

        return view('terminals.show', compact('terminal', 'businessUnit', 'transactions', 'activeTab'));
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

        $terminal = $businessUnit->terminals()->findOrFail($id);

        return view('terminals.edit', compact('terminal', 'businessUnit'));
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

        $terminal = $businessUnit->terminals()->findOrFail($id);

        $validated = $request->validate([
            'serial_number' => 'required|string|max:255|unique:terminals,serial_number,' . $id,
            'simcard_number' => 'required|string|max:255',
            'device_alias' => 'required|string|max:255',
            'model_make' => 'required|string|max:255',
        ]);

        $terminal->update($validated);

        return redirect()->route('terminals.index')
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

        $terminal = $businessUnit->terminals()->findOrFail($id);

        $terminal->delete();

        return redirect()->route('terminals.index')
            ->with('success', __('common.deleted_successfully'));
    }

    /**
     * Export terminals.
     */
    public function export(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        $format = $request->get('format', 'csv');
        $query = $businessUnit->terminals();

        // Apply same filters as index
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                  ->orWhere('simcard_number', 'like', "%{$search}%")
                  ->orWhere('device_alias', 'like', "%{$search}%")
                  ->orWhere('model_make', 'like', "%{$search}%");
            });
        }

        $terminals = $query->latest()->get();

        // Transform data for export
        $data = $terminals->map(function ($terminal) {
            return [
                __('common.export_col_serial_number') => $terminal->serial_number,
                __('common.export_col_sim_number') => $terminal->simcard_number ?? '-',
                __('common.export_col_device_alias') => $terminal->device_alias ?? '-',
                __('common.export_col_model_make') => $terminal->model_make ?? '-',
                __('common.export_col_status') => $this->exportService->valueLabel($terminal->status ?? 'active', ''),
                __('common.export_col_created_at') => $terminal->created_at->format('Y-m-d H:i'),
            ];
        });

        $headers = array_map(fn ($k) => __('common.export_col_'.$k), ['serial_number', 'sim_number', 'device_alias', 'model_make', 'status', 'created_at']);
        $filters = $request->only(['search']);
        $filename = $this->exportService->generateFilename('terminals', $filters);

        switch ($format) {
            case 'excel':
                return $this->exportService->exportExcel($data, $headers, $filename, __('common.terminals'));
            case 'pdf':
                return $this->exportService->exportPdf($data, $headers, $filename, __('common.terminals'), $filters);
            case 'csv':
            default:
                return $this->exportService->exportCsv($data, $headers, $filename);
        }
    }
}
