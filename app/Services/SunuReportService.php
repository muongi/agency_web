<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Card;
use App\Models\InsuranceProduct;
use App\Models\SunuPayIn;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Models\SunuProposal;
use App\Models\Terminal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * SUNU reports (premiums, agent cash reconciliation, subscriptions & policies, cards)
 * and the SUNU dashboard. Same queries feed the screens and the CSV/Excel exports.
 */
class SunuReportService
{
    /**
     * @return array{from: Carbon, to: Carbon, agent_id: ?int, terminal_id: ?int, product_id: ?int}
     */
    public function filters(Request $request): array
    {
        $from = $this->date($request->input('from')) ?? now()->startOfMonth();
        $to = $this->date($request->input('to')) ?? now();

        return [
            'from' => $from->copy()->startOfDay(),
            'to' => $to->copy()->endOfDay(),
            'agent_id' => $request->filled('agent_id') ? (int) $request->input('agent_id') : null,
            'terminal_id' => $request->filled('terminal_id') ? (int) $request->input('terminal_id') : null,
            'product_id' => $request->filled('product_id') ? (int) $request->input('product_id') : null,
        ];
    }

    /** Dropdown data for the filter bar. */
    public function filterOptions(int $buId): array
    {
        return [
            'agents' => Agent::where('business_unit_id', $buId)->orderBy('first_name')->get(),
            'terminals' => Terminal::where('business_unit_id', $buId)->orderBy('device_alias')->get(),
            'products' => InsuranceProduct::where('business_unit_id', $buId)->orderBy('display_order')->get(),
        ];
    }

    // ---------------------------------------------------------------- premiums

    public function premiumsQuery(int $buId, array $f)
    {
        return SunuPremiumCollection::with(['policy.product', 'policy.client', 'proposal.product', 'proposal.client', 'agent', 'terminal'])
            ->where('business_unit_id', $buId)
            ->valid()
            ->whereBetween('collected_at', [$f['from'], $f['to']])
            ->when($f['agent_id'], fn ($q, $id) => $q->where('agent_id', $id))
            ->when($f['terminal_id'], fn ($q, $id) => $q->where('terminal_id', $id))
            ->when($f['product_id'], function ($q, $id) {
                $q->where(function ($w) use ($id) {
                    $w->whereHas('policy', fn ($p) => $p->where('insurance_product_id', $id))
                      ->orWhereHas('proposal', fn ($p) => $p->where('insurance_product_id', $id));
                });
            })
            ->orderByDesc('collected_at');
    }

    public function premiums(int $buId, array $f): array
    {
        $rows = $this->premiumsQuery($buId, $f)->get();

        $group = function (Collection $items, callable $key, callable $label) {
            return $items->groupBy($key)->map(fn ($g) => [
                'label' => $label($g->first()),
                'count' => $g->count(),
                'amount' => (float) $g->sum('amount'),
            ])->sortByDesc('amount')->values();
        };

        return [
            'total' => (float) $rows->sum('amount'),
            'count' => $rows->count(),
            'by_kind' => $rows->groupBy('collection_kind')->map(fn ($g) => ['count' => $g->count(), 'amount' => (float) $g->sum('amount')]),
            'by_agent' => $group($rows, fn ($c) => $c->agent_id ?? 0, fn ($c) => $c->agent?->full_name ?? '—'),
            'by_product' => $group($rows, fn ($c) => $this->premiumProduct($c)?->id ?? 0, fn ($c) => $this->premiumProduct($c)?->localizedName() ?? '—'),
            'by_day' => $rows->groupBy(fn ($c) => optional($c->collected_at)->format('Y-m-d'))
                ->map(fn ($g, $day) => ['label' => $day, 'count' => $g->count(), 'amount' => (float) $g->sum('amount')])
                ->sortKeys()->values(),
            'rows' => $rows,
        ];
    }

    public function premiumExportRows(Collection $rows): Collection
    {
        return $rows->map(function (SunuPremiumCollection $c) {
            // Before acceptance the premium sits on the proposal only.
            $client = $c->policy?->client ?? $c->proposal?->client;

            return [
                __('common.export_col_reference') => $c->reference,
                __('common.export_col_date') => optional($c->collected_at)->format('Y-m-d H:i'),
                __('common.export_col_kind') => $this->kindLabel($c->collection_kind),
                __('common.export_col_amount') => (float) $c->amount,
                __('common.export_col_insured_number') => $client?->insured_number ?? '',
                __('common.export_col_policy') => $c->policy?->policy_number ?? '',
                __('common.export_col_proposal') => $c->proposal?->proposal_number ?? '',
                __('common.export_col_client') => trim(($client?->last_name ?? $c->proposal?->last_name ?? '').' '.($client?->first_name ?? $c->proposal?->first_names ?? '')),
                __('common.export_col_product') => $this->premiumProduct($c)?->localizedName() ?? '',
                __('common.export_col_agent') => $c->agent?->full_name ?? '',
                __('common.export_col_terminal') => $c->terminal?->device_alias ?? $c->terminal?->serial_number ?? '',
                __('common.export_col_instrument') => $this->exports()->valueLabel($c->payment_instrument, 'sunu_instrument_'),
                __('common.export_col_receipt') => $c->receipt_number ?? '',
                __('common.export_col_status') => __('common.sunu_premium_status_'.($c->status ?: SunuPremiumCollection::STATUS_VALID)),
                // SUNULIFE processing
                __('common.export_col_product_code') => $this->premiumProduct($c)?->code ?? '',
                __('common.export_col_sunulife_policy_id') => $c->policy?->sunulife_policy_id ?? '',
                __('common.export_col_reference_period') => $c->reference_period ?? '',
            ];
        });
    }

    // ---------------------------------------------------------- reconciliation

    /**
     * Per agent over the period: premiums collected vs pay-ins declared / confirmed / rejected.
     * Balance = collected − confirmed: cash the agent still holds (pending pay-ins included).
     */
    public function reconciliation(int $buId, array $f): array
    {
        $collected = SunuPremiumCollection::where('business_unit_id', $buId)
            ->valid()
            ->whereBetween('collected_at', [$f['from'], $f['to']])
            ->when($f['agent_id'], fn ($q, $id) => $q->where('agent_id', $id))
            ->selectRaw('agent_id, COUNT(*) as n, SUM(amount) as total')
            ->groupBy('agent_id')->get()->keyBy('agent_id');

        $payins = SunuPayIn::where('business_unit_id', $buId)
            ->whereBetween('declared_at', [$f['from'], $f['to']])
            ->when($f['agent_id'], fn ($q, $id) => $q->where('agent_id', $id))
            ->get()->groupBy('agent_id');

        $agentIds = $collected->keys()->merge($payins->keys())->unique()->filter();
        $agents = Agent::whereIn('id', $agentIds)->get()->keyBy('id');

        $rows = $agentIds->map(function ($agentId) use ($collected, $payins, $agents) {
            $p = $payins->get($agentId, collect());
            $collectedAmount = (float) ($collected->get($agentId)->total ?? 0);
            $pending = (float) $p->where('status', SunuPayIn::STATUS_DECLARED)->sum('declared_amount');
            $confirmed = (float) $p->where('status', SunuPayIn::STATUS_CONFIRMED)->sum(fn ($x) => $x->confirmed_amount ?? $x->declared_amount);
            $rejected = (float) $p->where('status', SunuPayIn::STATUS_REJECTED)->sum('declared_amount');

            return [
                'agent_id' => $agentId,
                'agent' => $agents->get($agentId)?->full_name ?? '—',
                'collections' => (int) ($collected->get($agentId)->n ?? 0),
                'collected' => $collectedAmount,
                'pending' => $pending,
                'confirmed' => $confirmed,
                'rejected' => $rejected,
                'balance' => $collectedAmount - $confirmed,
            ];
        })->sortByDesc('balance')->values();

        return [
            'rows' => $rows,
            'totals' => [
                'collected' => $rows->sum('collected'),
                'pending' => $rows->sum('pending'),
                'confirmed' => $rows->sum('confirmed'),
                'rejected' => $rows->sum('rejected'),
                'balance' => $rows->sum('balance'),
            ],
        ];
    }

    public function reconciliationExportRows(Collection $rows): Collection
    {
        return $rows->map(fn ($r) => [
            __('common.export_col_agent') => $r['agent'],
            __('common.export_col_collections') => $r['collections'],
            __('common.export_col_premiums_collected') => $r['collected'],
            __('common.export_col_payins_pending') => $r['pending'],
            __('common.export_col_payins_confirmed') => $r['confirmed'],
            __('common.export_col_payins_rejected') => $r['rejected'],
            __('common.export_col_balance_due') => $r['balance'],
        ]);
    }

    // -------------------------------------------------- subscriptions & policies

    public function subscriptions(int $buId, array $f): array
    {
        $proposals = SunuProposal::with(['agent', 'product'])
            ->where('business_unit_id', $buId)
            ->whereBetween('created_at', [$f['from'], $f['to']])
            ->when($f['agent_id'], fn ($q, $id) => $q->where('agent_id', $id))
            ->when($f['terminal_id'], fn ($q, $id) => $q->where('terminal_id', $id))
            ->when($f['product_id'], fn ($q, $id) => $q->where('insurance_product_id', $id))
            ->get();

        $policies = SunuPolicy::where('business_unit_id', $buId)
            ->when($f['product_id'], fn ($q, $id) => $q->where('insurance_product_id', $id))
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $overdue = $this->overdueQuery($buId, $f['product_id'])->get();

        return [
            'proposal_count' => $proposals->count(),
            'proposals_by_status' => $proposals->countBy('status'),
            'proposals_by_agent' => $proposals->groupBy(fn ($p) => $p->agent_id ?? 0)
                ->map(fn ($g) => ['label' => $g->first()->agent?->full_name ?? '—', 'count' => $g->count()])
                ->sortByDesc('count')->values(),
            'proposals_by_product' => $proposals->groupBy(fn ($p) => $p->insurance_product_id ?? 0)
                ->map(fn ($g) => ['label' => $g->first()->product?->localizedName() ?? '—', 'count' => $g->count()])
                ->sortByDesc('count')->values(),
            'policies_by_status' => $policies,
            'overdue' => $overdue,
        ];
    }

    /** Payable policies whose next due date has passed. */
    public function overdueQuery(int $buId, ?int $productId = null)
    {
        return SunuPolicy::with(['client', 'product'])
            ->withMax('premiums', 'collected_at')
            ->where('business_unit_id', $buId)
            ->whereIn('status', SunuSubscriptionService::PAYABLE_POLICY_STATUSES)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<', now()->toDateString())
            ->when($productId, fn ($q, $id) => $q->where('insurance_product_id', $id))
            ->orderBy('next_due_date');
    }

    public function overdueExportRows(Collection $rows): Collection
    {
        return $rows->map(fn (SunuPolicy $p) => [
            __('common.export_col_policy') => $p->policy_number,
            __('common.export_col_client') => trim(($p->client?->last_name ?? '').' '.($p->client?->first_name ?? '')),
            __('common.export_col_phone') => $p->client?->phone_number ?? '',
            __('common.export_col_product') => $p->product?->localizedName() ?? '',
            __('common.export_col_premium') => (float) ($p->premium_amount ?? 0),
            __('common.export_col_due_date') => optional($p->next_due_date)->format('Y-m-d'),
            __('common.export_col_days_overdue') => $p->next_due_date ? (int) $p->next_due_date->diffInDays(now()) : '',
            __('common.export_col_last_payment') => $p->premiums_max_collected_at ? Carbon::parse($p->premiums_max_collected_at)->format('Y-m-d') : '',
            __('common.export_col_status') => $this->exports()->valueLabel($p->status, 'sunu_status_'),
        ]);
    }

    // ------------------------------------------------------------------- cards

    public function cards(int $buId, array $f): array
    {
        $base = fn () => Card::where('business_unit_id', $buId);
        $linked = function ($q) {
            $q->whereHas('sunuPolicies')->orWhereHas('sunuProposals');
        };

        $written = $base()->with(['sunuPolicies.client', 'sunuProposals.client'])
            ->whereBetween('written_at', [$f['from'], $f['to']])
            ->orderByDesc('written_at')->get();

        $agentIds = $written->map(fn ($c) => $this->writtenByAgentId($c->written_by))->filter()->unique();
        $agents = Agent::whereIn('id', $agentIds)->get()->keyBy('id');
        if ($f['agent_id']) {
            $written = $written->filter(fn ($c) => $this->writtenByAgentId($c->written_by) === $f['agent_id'])->values();
        }

        return [
            'stock' => $base()->where('status', 'unassigned')->whereDoesntHave('sunuPolicies')->whereDoesntHave('sunuProposals')->count(),
            'assigned' => $base()->where($linked)->count(),
            'written_total' => $base()->whereNotNull('written_at')->count(),
            'blocked' => $base()->where('status', 'blocked')->count(),
            'written_in_period' => $written->count(),
            'by_agent' => $written->groupBy(fn ($c) => $this->writtenByAgentId($c->written_by) ?? 0)
                ->map(fn ($g, $id) => ['label' => $agents->get($id)?->full_name ?? '—', 'count' => $g->count()])
                ->sortByDesc('count')->values(),
            'by_day' => $written->groupBy(fn ($c) => $c->written_at->format('Y-m-d'))
                ->map(fn ($g, $day) => ['label' => $day, 'count' => $g->count()])->sortKeys()->values(),
            'rows' => $written,
            'agents' => $agents,
        ];
    }

    public function cardExportRows(Collection $rows, Collection $agents): Collection
    {
        return $rows->map(function (Card $c) use ($agents) {
            $client = $c->sunuClient();

            return [
                __('common.export_col_card_number') => $c->card_number,
                __('common.export_col_card_uid') => $c->card_serial ?? '',
                __('common.export_col_client') => $client ? trim($client->last_name.' '.$client->first_name) : '',
                __('common.export_col_status') => $this->exports()->valueLabel($c->status, ''),
                __('common.export_col_written_at') => optional($c->written_at)->format('Y-m-d H:i'),
                __('common.export_col_agent') => $agents->get($this->writtenByAgentId($c->written_by))?->full_name ?? '',
                __('common.export_col_written_by') => $c->written_by ?? '',
            ];
        });
    }

    /** cards.written_by is "agent:{id} / {serial}" (older rows: serial only). */
    public function writtenByAgentId(?string $writtenBy): ?int
    {
        return $writtenBy && preg_match('/agent:(\d+)/', $writtenBy, $m) ? (int) $m[1] : null;
    }

    // --------------------------------------------------------------- dashboard

    public function dashboard(int $buId): array
    {
        $monthStart = now()->startOfMonth();
        $monthPremiums = SunuPremiumCollection::where('business_unit_id', $buId)->valid()->where('collected_at', '>=', $monthStart);

        $year = (int) now()->format('Y');
        $monthly = SunuPremiumCollection::where('business_unit_id', $buId)->valid()
            ->whereYear('collected_at', $year)
            ->get(['amount', 'collected_at'])
            ->groupBy(fn ($c) => (int) $c->collected_at->format('n'));

        $topAgents = SunuPremiumCollection::with('agent')
            ->where('business_unit_id', $buId)->valid()->where('collected_at', '>=', $monthStart)
            ->selectRaw('agent_id, COUNT(*) as n, SUM(amount) as total')
            ->groupBy('agent_id')->orderByDesc('total')->take(5)->get();

        return [
            'premiums_month_amount' => (float) (clone $monthPremiums)->sum('amount'),
            'premiums_month_count' => (clone $monthPremiums)->count(),
            'proposals_month' => SunuProposal::where('business_unit_id', $buId)->where('created_at', '>=', $monthStart)->count(),
            'active_policies' => SunuPolicy::where('business_unit_id', $buId)->where('status', SunuPolicy::STATUS_ACTIVE)->count(),
            'overdue_policies' => $this->overdueQuery($buId)->count(),
            'payins_pending_count' => SunuPayIn::where('business_unit_id', $buId)->where('status', SunuPayIn::STATUS_DECLARED)->count(),
            'payins_pending_amount' => (float) SunuPayIn::where('business_unit_id', $buId)->where('status', SunuPayIn::STATUS_DECLARED)->sum('declared_amount'),
            'cards_stock' => Card::where('business_unit_id', $buId)->where('status', 'unassigned')->whereDoesntHave('sunuPolicies')->whereDoesntHave('sunuProposals')->count(),
            'top_agents' => $topAgents,
            'monthly' => collect(range(1, 12))->map(fn ($m) => [
                'month' => Carbon::create($year, $m, 1)->translatedFormat('M'),
                'amount' => (float) ($monthly->get($m)?->sum('amount') ?? 0),
                'count' => $monthly->get($m)?->count() ?? 0,
            ]),
            'year' => $year,
        ];
    }

    // ----------------------------------------------------------------- helpers

    protected function exports(): ExportService
    {
        return app(ExportService::class);
    }

    public function kindLabel(?string $kind): string
    {
        $key = 'common.sunu_kind_'.$kind;
        $label = __($key);

        return $label === $key ? (string) $kind : $label;
    }

    protected function premiumProduct(SunuPremiumCollection $c): ?InsuranceProduct
    {
        return $c->policy?->product ?? $c->proposal?->product;
    }

    protected function date($value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
