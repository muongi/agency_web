<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class AuditTrailController extends Controller
{
    protected $auditTrailService;

    public function __construct(AuditTrailService $auditTrailService)
    {
        $this->middleware(['auth', 'business.access']);
        $this->auditTrailService = $auditTrailService;
    }

    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('view-audit-trail', $businessUnit->id)) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.unauthorized_access'));
        }

        $query = ActivityLog::query();

        // Filter by business unit (unless superadmin viewing all)
        if (!$user->isSuperAdmin() || $request->has('business_unit_id')) {
            $query->where('business_unit_id', $businessUnit->id);
        }

        // Apply filters
        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('model_type')) {
            $query->byModelType($request->model_type);
        }

        if ($request->filled('event')) {
            $query->byEvent($request->event);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('properties', 'like', "%{$search}%");
            });
        }

        $auditLogs = $query->with(['causer', 'subject', 'businessUnit'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get unique model types for filter
        $modelTypes = ActivityLog::select('subject_type')
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(function($type) {
                return [
                    'value' => $type,
                    'label' => $this->auditTrailService->getModelDisplayName($type),
                ];
            })
            ->sortBy('label')
            ->values();

        // Get users for filter
        $users = User::whereHas('businessUnits', function($q) use ($businessUnit) {
            $q->where('business_units.id', $businessUnit->id);
        })->get();

        return view('audit-trail.index', compact('auditLogs', 'modelTypes', 'users', 'businessUnit'));
    }

    /**
     * Display the specified audit log entry.
     */
    public function show($id)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('view-audit-details', $businessUnit->id)) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.unauthorized_access'));
        }

        $auditLog = ActivityLog::with(['causer', 'subject', 'businessUnit'])
            ->findOrFail($id);

        // Check business unit access
        if (!$user->isSuperAdmin() && $auditLog->business_unit_id !== $businessUnit->id) {
            return redirect()->route('audit-trail.index')
                ->with('error', __('common.unauthorized_access'));
        }

        $changes = $this->auditTrailService->formatChanges($auditLog->properties);

        return view('audit-trail.show', compact('auditLog', 'changes', 'businessUnit'));
    }

    /**
     * Display audit history for a specific model instance.
     */
    public function byModel($modelType, $modelId)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('view-audit-trail', $businessUnit->id)) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.unauthorized_access'));
        }

        // Decode model type if needed
        $modelClass = str_replace('_', '\\', $modelType);
        if (!class_exists($modelClass)) {
            $modelClass = 'App\\Models\\' . $modelType;
        }
        
        if (!class_exists($modelClass)) {
            return redirect()->route('audit-trail.index')
                ->with('error', 'Invalid model type');
        }

        $history = $this->auditTrailService->getModelHistory($modelClass, $modelId);
        
        $model = $modelClass::find($modelId);
        $modelDisplayName = $this->auditTrailService->getModelDisplayName($modelClass);

        return view('audit-trail.model-history', compact('history', 'model', 'modelDisplayName', 'businessUnit'));
    }

    /**
     * Display all activities by a specific user.
     */
    public function byUser($userId)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('view-audit-trail', $businessUnit->id)) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.unauthorized_access'));
        }

        $targetUser = User::findOrFail($userId);
        $activities = $this->auditTrailService->getUserActivity($userId);

        return view('audit-trail.user-activity', compact('activities', 'targetUser', 'businessUnit'));
    }

    /**
     * Export audit logs.
     */
    public function export(Request $request)
    {
        $user = auth()->user();
        $businessUnit = $user->currentBusinessUnit;
        
        if (!$businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (!$user->isSuperAdmin() && !$user->hasPermission('export-audit-trail', $businessUnit->id)) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.unauthorized_access'));
        }

        // Similar query logic as index
        $query = ActivityLog::query();

        if (!$user->isSuperAdmin() || $request->has('business_unit_id')) {
            $query->where('business_unit_id', $businessUnit->id);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('model_type')) {
            $query->byModelType($request->model_type);
        }

        if ($request->filled('event')) {
            $query->byEvent($request->event);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $auditLogs = $query->with(['causer', 'subject', 'businessUnit'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Generate CSV
        $filename = 'audit_trail_' . now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $exports = app(\App\Services\ExportService::class);
        $callback = function() use ($auditLogs, $exports) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 (é, è…)
            $delimiter = $exports->csvDelimiter();

            // Headers
            fputcsv($file, array_map(fn ($k) => __('common.export_col_'.$k), [
                'datetime', 'user', 'action', 'model_type', 'model_id', 'description', 'ip_address', 'user_agent',
            ]), $delimiter);

            // Data
            foreach ($auditLogs as $log) {
                fputcsv($file, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->causer ? $log->causer->name : __('common.system'),
                    $log->event ? $exports->valueLabel($log->event, '') : '-',
                    $this->auditTrailService->getModelDisplayName($log->subject_type),
                    $log->subject_id ?? '-',
                    $log->description,
                    $log->ip_address ?? '-',
                    $log->user_agent ?? '-',
                ], $delimiter);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

