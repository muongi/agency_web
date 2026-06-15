<?php

namespace App\Http\Controllers;

use App\Models\CnssContribution;
use App\Services\CnssContributionReportingService;
use App\Services\ExportService;
use Illuminate\Http\Request;

class CnssContributionWebController extends Controller
{
    public function __construct(
        protected CnssContributionReportingService $reporting,
        protected ExportService $exportService
    ) {
        $this->middleware(['auth', 'business.access']);
    }

    public function index(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! auth()->user()->isSuperAdmin() && ! auth()->user()->hasPermission('view-cnss-members', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $contributions = $this->contributionsQuery($request, $businessUnit->id)
            ->paginate(20)
            ->withQueryString();

        $canViewTransactions = auth()->user()->isSuperAdmin()
            || auth()->user()->hasPermission('view-transaction', $businessUnit->id);

        return view('business.cnss.contributions.index', [
            'contributions' => $contributions,
            'businessUnit' => $businessUnit,
            'canViewTransactions' => $canViewTransactions,
        ]);
    }

    public function export(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! auth()->user()->isSuperAdmin() && ! auth()->user()->hasPermission('view-cnss-members', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $format = $request->get('format', 'csv');
        $contributions = $this->contributionsQuery($request, $businessUnit->id)
            ->with(['enrollment', 'agent', 'transaction'])
            ->get();

        $data = $contributions->map(function (CnssContribution $c) {
            $memberName = $c->enrollment
                ? trim(($c->enrollment->last_name ?? '').' '.($c->enrollment->first_names ?? ''))
                : '-';

            return [
                'Voluntary Insured No.' => $c->voluntary_insured_number ?? '-',
                'Affiliation No.' => $c->affiliation_number ?? '-',
                'Member Name' => $memberName !== '' ? $memberName : '-',
                'Mode' => $c->mode,
                'Amount' => number_format((float) $c->amount, 2),
                'Reference Period' => $c->reference_period,
                'Payment Frequency' => $c->payment_frequency,
                'Agent' => $c->agent?->full_name ?? '-',
                'Transaction Reference' => $c->reference ?? ($c->transaction?->reference ?? '-'),
                'Created At' => $c->created_at?->format('Y-m-d H:i:s') ?? '-',
            ];
        });

        $headers = [
            'Voluntary Insured No.',
            'Affiliation No.',
            'Member Name',
            'Mode',
            'Amount',
            'Reference Period',
            'Payment Frequency',
            'Agent',
            'Transaction Reference',
            'Created At',
        ];

        $filters = $request->only(['mode', 'start_date', 'end_date', 'search']);
        $filename = $this->exportService->generateFilename('cnss_contributions', $filters);

        switch ($format) {
            case 'excel':
                return $this->exportService->exportExcel($data, $headers, $filename, 'CNSS Contributions');
            case 'pdf':
                return $this->exportService->exportPdf($data, $headers, $filename, 'CNSS Contributions Export', $filters);
            case 'csv':
            default:
                return $this->exportService->exportCsv($data, $headers, $filename);
        }
    }

    public function dashboard()
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! auth()->user()->isSuperAdmin() && ! auth()->user()->hasPermission('view-cnss-members', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $totals = $this->reporting->totalsTodayMonthYear($businessUnit->id);
        $topContributors = $this->reporting->topContributorsByEnrollment($businessUnit->id);
        $topAgents = $this->reporting->topAgents($businessUnit->id);

        return view('business.cnss.contributions.dashboard', [
            'businessUnit' => $businessUnit,
            'totals' => $totals,
            'topContributors' => $topContributors,
            'topAgents' => $topAgents,
        ]);
    }

    protected function contributionsQuery(Request $request, int $businessUnitId)
    {
        $query = CnssContribution::query()
            ->where('business_unit_id', $businessUnitId)
            ->with(['enrollment', 'agent'])
            ->orderByDesc('id');

        if ($request->filled('mode') && in_array($request->input('mode'), [CnssContribution::MODE_RECORD, CnssContribution::MODE_LEDGER], true)) {
            $query->where('mode', $request->input('mode'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('voluntary_insured_number', 'like', $term)
                    ->orWhere('affiliation_number', 'like', $term)
                    ->orWhere('reference', 'like', $term);
            });
        }

        return $query;
    }
}
