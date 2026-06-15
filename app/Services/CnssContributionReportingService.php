<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CnssContribution;
use App\Models\CnssVoluntaryEnrollment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates for CNSS contribution rows only (cnss_contributions).
 * Both record and ledger modes count toward totals unless product rules change.
 */
class CnssContributionReportingService
{
    public function sumForBusinessUnitBetween(int $businessUnitId, Carbon $start, Carbon $end): float
    {
        return (float) CnssContribution::query()
            ->where('business_unit_id', $businessUnitId)
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
    }

    /**
     * @return array{today: float, month: float, year: float}
     */
    public function totalsTodayMonthYear(int $businessUnitId, ?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();

        return [
            'today' => $this->sumForBusinessUnitBetween(
                $businessUnitId,
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay()
            ),
            'month' => $this->sumForBusinessUnitBetween(
                $businessUnitId,
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth()
            ),
            'year' => $this->sumForBusinessUnitBetween(
                $businessUnitId,
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear()
            ),
        ];
    }

    /**
     * @return Collection<int, object{enrollment: ?CnssVoluntaryEnrollment, total_amount: float}>
     */
    public function topContributorsByEnrollment(int $businessUnitId, int $limit = 5): Collection
    {
        $rows = DB::table('cnss_contributions')
            ->where('business_unit_id', $businessUnitId)
            ->whereNotNull('cnss_voluntary_enrollment_id')
            ->select('cnss_voluntary_enrollment_id', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('cnss_voluntary_enrollment_id')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $enrollments = CnssVoluntaryEnrollment::query()
            ->whereIn('id', $rows->pluck('cnss_voluntary_enrollment_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($enrollments) {
            return (object) [
                'enrollment' => $enrollments->get($row->cnss_voluntary_enrollment_id),
                'total_amount' => (float) $row->total_amount,
            ];
        });
    }

    /**
     * @return Collection<int, object{agent: ?Agent, total_amount: float}>
     */
    public function topAgents(int $businessUnitId, int $limit = 5): Collection
    {
        $rows = DB::table('cnss_contributions')
            ->where('business_unit_id', $businessUnitId)
            ->whereNotNull('agent_id')
            ->select('agent_id', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('agent_id')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $agents = Agent::query()
            ->whereIn('id', $rows->pluck('agent_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($agents) {
            return (object) [
                'agent' => $agents->get($row->agent_id),
                'total_amount' => (float) $row->total_amount,
            ];
        });
    }
}
