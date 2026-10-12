<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Agent;
use App\Models\Terminal;
use App\Models\Client;
use App\Services\TransactionCalculationService;
use App\Services\ExportService;

class TransactionController extends Controller
{
    protected $calculationService;
    protected $exportService;

    public function __construct(TransactionCalculationService $calculationService, ExportService $exportService)
    {
        $this->middleware(['auth', 'business.access']);
        $this->calculationService = $calculationService;
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
        if (!auth()->user()->hasPermission('view-transactions', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $query = Transaction::where('business_unit_id', $businessUnit->id)
            ->with(['transactionType', 'agent', 'terminal', 'client']);

        // Filters
        if ($request->filled('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date . ' 23:59:59');
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->filled('terminal_id')) {
            $query->where('terminal_id', $request->terminal_id);
        }

        if ($request->filled('transaction_type_id')) {
            $query->where('transaction_type_id', $request->transaction_type_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('agent', function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('second_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('terminal', function($q) use ($search) {
                      $q->where('serial_number', 'like', "%{$search}%");
                  });
            });
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->paginate(20);

        // Get filter options
        $transactionTypes = $businessUnit->availableTransactionTypes();
        $agents = Agent::where('business_unit_id', $businessUnit->id)->get();
        $terminals = Terminal::where('business_unit_id', $businessUnit->id)->get();

        return view('transactions.index', compact('transactions', 'transactionTypes', 'agents', 'terminals', 'businessUnit'));
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
        if (!auth()->user()->hasPermission('view-transaction', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $transaction = Transaction::where('business_unit_id', $businessUnit->id)
            ->with(['transactionType', 'agent', 'terminal', 'client', 'createdBy'])
            ->findOrFail($id);

        return view('transactions.show', compact('transaction', 'businessUnit'));
    }

    /**
     * Export transactions.
     */
    public function export(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        // Check permission
        if (!auth()->user()->hasPermission('view-transactions', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $format = $request->get('format', 'csv');
        $query = Transaction::where('business_unit_id', $businessUnit->id)
            ->with(['transactionType', 'agent', 'terminal', 'client']);

        // Apply same filters as index
        if ($request->filled('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date . ' 23:59:59');
        }
        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }
        if ($request->filled('terminal_id')) {
            $query->where('terminal_id', $request->terminal_id);
        }
        if ($request->filled('transaction_type_id')) {
            $query->where('transaction_type_id', $request->transaction_type_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('agent', function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('second_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('terminal', function($q) use ($search) {
                      $q->where('serial_number', 'like', "%{$search}%");
                  });
            });
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->get();

        // Transform data for export
        $data = $transactions->map(function ($transaction) {
            return [
                'Date' => $transaction->transaction_date->format('Y-m-d H:i:s'),
                'Type' => $transaction->transactionType->name ?? '-',
                'Reference' => $transaction->reference ?? '-',
                'Amount' => number_format($transaction->transaction_amount, 2),
                'Cost' => number_format($transaction->transaction_cost, 2),
                'Commission' => number_format($transaction->commission, 2),
                'Business Share' => number_format($transaction->business_share, 2),
                'Agent' => $transaction->agent ? $transaction->agent->full_name : '-',
                'Terminal' => $transaction->terminal ? $transaction->terminal->serial_number : '-',
                'Client' => $transaction->client ? $transaction->client->full_name : '-',
                'Status' => ucfirst($transaction->status),
            ];
        });

        $headers = ['Date', 'Type', 'Reference', 'Amount', 'Cost', 'Commission', 'Business Share', 'Agent', 'Terminal', 'Client', 'Status'];
        $filters = $request->only(['start_date', 'end_date', 'status', 'search']);
        $filename = $this->exportService->generateFilename('transactions', $filters);

        switch ($format) {
            case 'excel':
                return $this->exportService->exportExcel($data, $headers, $filename, 'Transactions');
            case 'pdf':
                return $this->exportService->exportPdf($data, $headers, $filename, 'Transactions Export', $filters);
            case 'csv':
            default:
                return $this->exportService->exportCsv($data, $headers, $filename);
        }
    }
}

