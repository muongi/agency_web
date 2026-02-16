<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Agent;
use App\Models\Client;
use App\Models\TransactionType;
use App\Models\Terminal;
use App\Jobs\GenerateAgentReportJob;
use App\Jobs\GenerateClientReportJob;
use App\Jobs\GenerateTransactionReportJob;
use App\Jobs\GenerateCommissionReportJob;
use App\Jobs\GenerateCostReportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'business.access']);
    }

    /**
     * Display a listing of all reports.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('view-reports', $businessUnit->id)) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.unauthorized_access'));
        }

        $query = Report::where('business_unit_id', $businessUnit->id)
            ->with('user')
            ->latest();

        // Filter by report type
        if ($request->has('type') && $request->type) {
            $query->where('report_type', $request->type);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $reports = $query->paginate(20);

        return view('reports.index', compact('reports', 'businessUnit'));
    }

    /**
     * Request an agent report.
     */
    public function requestAgentReport(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->back()->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('request-agent-reports', $businessUnit->id)) {
            return redirect()->back()->with('error', __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'agent_id' => 'nullable|exists:agents,id',
            'status' => 'nullable|in:pending,completed,failed,cancelled',
        ]);

        $report = Report::create([
            'business_unit_id' => $businessUnit->id,
            'user_id' => $user->id,
            'report_type' => 'agent',
            'status' => 'pending',
            'filters' => array_merge($validated, ['business_unit_id' => $businessUnit->id]),
        ]);

        GenerateAgentReportJob::dispatch($report->id);

        return redirect()->route('business.reports.index', ['type' => 'agent'])
            ->with('success', __('common.report_requested'));
    }

    /**
     * Request a client report.
     */
    public function requestClientReport(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->back()->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('request-client-reports', $businessUnit->id)) {
            return redirect()->back()->with('error', __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'client_id' => 'nullable|exists:clients,id',
            'status' => 'nullable|in:pending,completed,failed,cancelled',
        ]);

        $report = Report::create([
            'business_unit_id' => $businessUnit->id,
            'user_id' => $user->id,
            'report_type' => 'client',
            'status' => 'pending',
            'filters' => array_merge($validated, ['business_unit_id' => $businessUnit->id]),
        ]);

        GenerateClientReportJob::dispatch($report->id);

        return redirect()->route('business.reports.index', ['type' => 'client'])
            ->with('success', __('common.report_requested'));
    }

    /**
     * Request a transaction report.
     */
    public function requestTransactionReport(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->back()->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('request-transaction-reports', $businessUnit->id)) {
            return redirect()->back()->with('error', __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'transaction_type_id' => 'nullable|exists:transaction_types,id',
            'agent_id' => 'nullable|exists:agents,id',
            'client_id' => 'nullable|exists:clients,id',
            'terminal_id' => 'nullable|exists:terminals,id',
            'status' => 'nullable|in:pending,completed,failed,cancelled',
        ]);

        $report = Report::create([
            'business_unit_id' => $businessUnit->id,
            'user_id' => $user->id,
            'report_type' => 'transaction',
            'status' => 'pending',
            'filters' => array_merge($validated, ['business_unit_id' => $businessUnit->id]),
        ]);

        GenerateTransactionReportJob::dispatch($report->id);

        return redirect()->route('business.reports.index', ['type' => 'transaction'])
            ->with('success', __('common.report_requested'));
    }

    /**
     * Request a commission report.
     */
    public function requestCommissionReport(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->back()->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('request-commission-reports', $businessUnit->id)) {
            return redirect()->back()->with('error', __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'agent_id' => 'nullable|exists:agents,id',
            'transaction_type_id' => 'nullable|exists:transaction_types,id',
            'status' => 'nullable|in:pending,completed,failed,cancelled',
        ]);

        $report = Report::create([
            'business_unit_id' => $businessUnit->id,
            'user_id' => $user->id,
            'report_type' => 'commission',
            'status' => 'pending',
            'filters' => array_merge($validated, ['business_unit_id' => $businessUnit->id]),
        ]);

        GenerateCommissionReportJob::dispatch($report->id);

        return redirect()->route('business.reports.index', ['type' => 'commission'])
            ->with('success', __('common.report_requested'));
    }

    /**
     * Request a cost report.
     */
    public function requestCostReport(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->back()->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('request-cost-reports', $businessUnit->id)) {
            return redirect()->back()->with('error', __('common.unauthorized_access'));
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'transaction_type_id' => 'nullable|exists:transaction_types,id',
            'status' => 'nullable|in:pending,completed,failed,cancelled',
        ]);

        $report = Report::create([
            'business_unit_id' => $businessUnit->id,
            'user_id' => $user->id,
            'report_type' => 'cost',
            'status' => 'pending',
            'filters' => array_merge($validated, ['business_unit_id' => $businessUnit->id]),
        ]);

        GenerateCostReportJob::dispatch($report->id);

        return redirect()->route('business.reports.index', ['type' => 'cost'])
            ->with('success', __('common.report_requested'));
    }

    /**
     * Check report status (for polling).
     */
    public function status($id)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return response()->json(['error' => __('common.no_business_unit_assigned')], 403);
        }

        $report = Report::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        return response()->json([
            'id' => $report->id,
            'status' => $report->status,
            'error_message' => $report->error_message,
            'is_ready' => $report->isReady(),
        ]);
    }

    /**
     * Download a completed report.
     */
    public function download($id)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->back()->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('download-reports', $businessUnit->id)) {
            return redirect()->back()->with('error', __('common.unauthorized_access'));
        }

        $report = Report::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        if (!$report->isReady()) {
            return redirect()->back()->with('error', __('common.report_not_ready'));
        }

        if (!Storage::exists($report->file_path)) {
            return redirect()->back()->with('error', __('common.report_file_not_found'));
        }

        return Storage::download($report->file_path, $report->file_name);
    }

    /**
     * Delete a report.
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->back()->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('delete-reports', $businessUnit->id)) {
            return redirect()->back()->with('error', __('common.unauthorized_access'));
        }

        $report = Report::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        // Delete file if exists
        if ($report->file_path && Storage::exists($report->file_path)) {
            Storage::delete($report->file_path);
        }

        $report->delete();

        return redirect()->back()->with('success', __('common.report_deleted'));
    }

    /**
     * Retry a failed report.
     */
    public function retry($id)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->back()->with('error', __('common.no_business_unit_assigned'));
        }

        $report = Report::where('business_unit_id', $businessUnit->id)
            ->findOrFail($id);

        if (!$report->canRetry()) {
            return redirect()->back()->with('error', __('common.report_cannot_retry'));
        }

        // Reset report status
        $report->update([
            'status' => 'pending',
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);

        // Dispatch appropriate job based on report type
        $jobClass = match($report->report_type) {
            'agent' => GenerateAgentReportJob::class,
            'client' => GenerateClientReportJob::class,
            'transaction' => GenerateTransactionReportJob::class,
            'commission' => GenerateCommissionReportJob::class,
            'cost' => GenerateCostReportJob::class,
            default => null,
        };

        if ($jobClass) {
            $jobClass::dispatch($report->id);
        }

        return redirect()->back()->with('success', __('common.report_retry_initiated'));
    }

    /**
     * Show agent reports page with request form.
     */
    public function agentReports()
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('request-agent-reports', $businessUnit->id)) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.unauthorized_access'));
        }

        $agents = $businessUnit->agents()->orderBy('first_name')->get();
        $reports = Report::where('business_unit_id', $businessUnit->id)
            ->where('report_type', 'agent')
            ->with('user')
            ->latest()
            ->paginate(10);

        return view('reports.agents', compact('agents', 'reports', 'businessUnit'));
    }

    /**
     * Show client reports page with request form.
     */
    public function clientReports()
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('request-client-reports', $businessUnit->id)) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.unauthorized_access'));
        }

        $clients = $businessUnit->clients()->orderBy('first_name')->get();
        $reports = Report::where('business_unit_id', $businessUnit->id)
            ->where('report_type', 'client')
            ->with('user')
            ->latest()
            ->paginate(10);

        return view('reports.clients', compact('clients', 'reports', 'businessUnit'));
    }

    /**
     * Show transaction reports page with request form.
     */
    public function transactionReports()
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('request-transaction-reports', $businessUnit->id)) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.unauthorized_access'));
        }

        $transactionTypes = $businessUnit->transactionTypes()->orderBy('name')->get();
        $agents = $businessUnit->agents()->orderBy('first_name')->get();
        $clients = $businessUnit->clients()->orderBy('first_name')->get();
        $terminals = $businessUnit->terminals()->orderBy('device_alias')->get();
        $reports = Report::where('business_unit_id', $businessUnit->id)
            ->where('report_type', 'transaction')
            ->with('user')
            ->latest()
            ->paginate(10);

        return view('reports.transactions', compact('transactionTypes', 'agents', 'clients', 'terminals', 'reports', 'businessUnit'));
    }

    /**
     * Show commission reports page with request form.
     */
    public function commissionReports()
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('request-commission-reports', $businessUnit->id)) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.unauthorized_access'));
        }

        $transactionTypes = $businessUnit->transactionTypes()->orderBy('name')->get();
        $agents = $businessUnit->agents()->orderBy('first_name')->get();
        $reports = Report::where('business_unit_id', $businessUnit->id)
            ->where('report_type', 'commission')
            ->with('user')
            ->latest()
            ->paginate(10);

        return view('reports.commissions', compact('transactionTypes', 'agents', 'reports', 'businessUnit'));
    }

    /**
     * Show cost reports page with request form.
     */
    public function costReports()
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('request-cost-reports', $businessUnit->id)) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.unauthorized_access'));
        }

        $transactionTypes = $businessUnit->transactionTypes()->orderBy('name')->get();
        $reports = Report::where('business_unit_id', $businessUnit->id)
            ->where('report_type', 'cost')
            ->with('user')
            ->latest()
            ->paginate(10);

        return view('reports.costs', compact('transactionTypes', 'reports', 'businessUnit'));
    }
}
