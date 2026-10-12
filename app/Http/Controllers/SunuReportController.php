<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Services\ExportService;
use App\Services\SunuReportService;
use Illuminate\Http\Request;

/**
 * SUNU reports: premiums, agent cash reconciliation, subscriptions & policies, cards.
 * Each page takes ?export=csv|excel to download what is on screen.
 */
class SunuReportController extends Controller
{
    public function __construct(
        protected SunuReportService $reports,
        protected ExportService $exports
    ) {
        $this->middleware(['auth', 'business.access']);
    }

    /** Business unit of the page, after checking the report permission (view-sunu-report-<report>). */
    protected function businessUnit(string $report)
    {
        $user = auth()->user();
        $bu = $user->currentBusinessUnit;
        if (! $bu) {
            abort(redirect()->route('business.dashboard')->with('error', __('common.no_business_unit_assigned')));
        }
        if (! $user->isSuperAdmin() && ! $user->hasPermission('view-sunu-report-'.$report, $bu->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        return $bu;
    }

    /** $blankRow = the same export mapper applied to an empty record, for the headings of an empty export. */
    protected function download(Request $request, $rows, $blankRow, string $name, string $title)
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->hasPermission('export-sunu-reports', $user->current_business_unit_id)) {
            abort(403, __('common.unauthorized_access'));
        }
        $rows = collect($rows);
        $headers = array_keys(collect($blankRow)->first());
        $filename = $name.'_'.now()->format('Ymd_His');

        return $request->input('export') === 'excel'
            ? $this->exports->exportExcel($rows, $headers, $filename, mb_substr($title, 0, 31))
            : $this->exports->exportCsv($rows, $headers, $filename);
    }

    protected function view(string $view, array $data, array $f, int $buId)
    {
        return view('business.sunu.reports.'.$view, $data + [
            'f' => $f,
            'options' => $this->reports->filterOptions($buId),
        ]);
    }

    public function premiums(Request $request)
    {
        $bu = $this->businessUnit('premiums');
        $f = $this->reports->filters($request);
        $data = $this->reports->premiums($bu->id, $f);

        if ($request->filled('export')) {
            return $this->download($request, $this->reports->premiumExportRows($data['rows']),
                $this->reports->premiumExportRows(collect([new SunuPremiumCollection])), 'sunu_primes', __('common.sunu_report_premiums'));
        }

        return $this->view('premiums', ['data' => $data], $f, $bu->id);
    }

    public function reconciliation(Request $request)
    {
        $bu = $this->businessUnit('reconciliation');
        $f = $this->reports->filters($request);
        $data = $this->reports->reconciliation($bu->id, $f);

        if ($request->filled('export')) {
            return $this->download($request, $this->reports->reconciliationExportRows($data['rows']),
                $this->reports->reconciliationExportRows(collect([array_fill_keys(['agent', 'collections', 'collected', 'pending', 'confirmed', 'rejected', 'balance'], null)])),
                'sunu_rapprochement_agents', __('common.sunu_report_reconciliation'));
        }

        return $this->view('reconciliation', ['data' => $data], $f, $bu->id);
    }

    public function subscriptions(Request $request)
    {
        $bu = $this->businessUnit('subscriptions');
        $f = $this->reports->filters($request);
        $data = $this->reports->subscriptions($bu->id, $f);

        if ($request->filled('export')) {
            return $this->download($request, $this->reports->overdueExportRows($data['overdue']),
                $this->reports->overdueExportRows(collect([new SunuPolicy])), 'sunu_polices_en_retard', __('common.sunu_report_subscriptions'));
        }

        return $this->view('subscriptions', ['data' => $data], $f, $bu->id);
    }

    public function cards(Request $request)
    {
        $bu = $this->businessUnit('cards');
        $f = $this->reports->filters($request);
        $data = $this->reports->cards($bu->id, $f);

        if ($request->filled('export')) {
            return $this->download($request, $this->reports->cardExportRows($data['rows'], $data['agents']),
                $this->reports->cardExportRows(collect([new Card]), collect()), 'sunu_cartes', __('common.sunu_report_cards'));
        }

        return $this->view('cards', ['data' => $data], $f, $bu->id);
    }
}
