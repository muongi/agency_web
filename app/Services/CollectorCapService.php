<?php

namespace App\Services;

use App\Exceptions\CollectorCapException;
use App\Models\Agent;
use App\Models\CollectorBalance;
use App\Models\CollectorCap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Collector collection limit (TDR): cash held = premiums collected − confirmed deposits.
 * Limit = agent override (collector_caps, active) → else business-unit default → else unlimited.
 * While under the limit every premium is accepted, even one that passes it (SUNU rule
 * 2026-09-30); reaching or passing the limit then blocks the TPE until the full amount is
 * deposited and confirmed, or the office releases it.
 */
class CollectorCapService
{
    public function ensureBalance(int $agentId): CollectorBalance
    {
        return CollectorBalance::firstOrCreate(
            ['agent_id' => $agentId],
            ['uncollected_amount' => 0, 'is_blocked' => false]
        );
    }

    /**
     * @return array{amount: float, warning_pct: int, currency: string, source: string}|null
     */
    public function effectiveCap(int $agentId): ?array
    {
        $cap = CollectorCap::where('agent_id', $agentId)->where('is_active', true)->first();
        if ($cap) {
            return [
                'amount' => (float) $cap->cap_amount,
                'warning_pct' => (int) $cap->warning_threshold_pct,
                'currency' => $cap->currency ?: 'XOF',
                'source' => 'agent',
            ];
        }

        $bu = Agent::with('businessUnit')->find($agentId)?->businessUnit;
        if ($bu && $bu->sunu_collector_cap_amount !== null && (float) $bu->sunu_collector_cap_amount > 0) {
            return [
                'amount' => (float) $bu->sunu_collector_cap_amount,
                'warning_pct' => (int) ($bu->sunu_collector_cap_warning_pct ?: 80),
                'currency' => 'XOF',
                'source' => 'default',
            ];
        }

        return null;
    }

    public function status(int $agentId): array
    {
        $cap = $this->effectiveCap($agentId);
        $balance = $this->ensureBalance($agentId);
        $capAmount = $cap['amount'] ?? null;
        $uncollected = (float) $balance->uncollected_amount;
        $pct = ($capAmount && $capAmount > 0) ? round(($uncollected / $capAmount) * 100, 2) : null;

        return [
            'agent_id' => $agentId,
            'cap_amount' => $capAmount,
            'cap_source' => $cap['source'] ?? null,
            'warning_threshold_pct' => $cap['warning_pct'] ?? null,
            'currency' => $cap['currency'] ?? 'XOF',
            'uncollected_amount' => $uncollected,
            'remaining_amount' => $capAmount !== null ? max(0, round($capAmount - $uncollected, 2)) : null,
            'utilisation_pct' => $pct,
            'warning' => $pct !== null && $pct >= ($cap['warning_pct'] ?? 80),
            'is_blocked' => (bool) $balance->is_blocked,
            'blocked_at' => $balance->blocked_at?->toIso8601String(),
            'last_pay_in_at' => $balance->last_pay_in_at?->toIso8601String(),
            'released_at' => $balance->released_at?->toIso8601String(),
        ];
    }

    /**
     * Only a collector already blocked / at the limit is refused. A premium that crosses the
     * limit is accepted; applyCollection() then blocks.
     *
     * @throws CollectorCapException when the collector is blocked or already at the limit
     */
    public function assertCanCollect(int $agentId, float $amount): void
    {
        $status = $this->status($agentId);
        $held = number_format($status['uncollected_amount'], 0, ',', ' ');
        $cap = $status['cap_amount'] !== null ? number_format($status['cap_amount'], 0, ',', ' ') : '—';

        if ($status['is_blocked'] || ($status['cap_amount'] !== null && $status['uncollected_amount'] >= $status['cap_amount'] - 0.0001)) {
            throw new CollectorCapException(
                CollectorCapException::BLOCKED,
                $status,
                "Collection limit reached ({$held} / {$cap} {$status['currency']}): deposit the amount collected, then wait for back-office confirmation"
            );
        }
    }

    public function applyCollection(int $agentId, float $amount): CollectorBalance
    {
        return DB::transaction(function () use ($agentId, $amount) {
            $this->ensureBalance($agentId);
            $balance = CollectorBalance::where('agent_id', $agentId)->lockForUpdate()->first();
            $cap = $this->effectiveCap($agentId);
            $newAmount = (float) $balance->uncollected_amount + $amount;
            $balance->uncollected_amount = $newAmount;
            if ($cap && $newAmount >= $cap['amount'] - 0.0001 && ! $balance->is_blocked) {
                if ($newAmount > $cap['amount'] + 0.0001) {
                    Log::info('SUNU_CAP_CROSSED', [
                        'agent_id' => $agentId,
                        'amount' => $amount,
                        'uncollected_amount' => $newAmount,
                        'cap_amount' => $cap['amount'],
                    ]);
                }
                $this->block($balance, $cap['amount']);
            }
            $balance->save();

            return $balance;
        });
    }

    /**
     * Correction of an already counted collection (cancel → -amount, amount fix → new - old).
     * Never goes below zero; a reduction releases the collector when back under the limit.
     */
    public function adjustCollection(int $agentId, float $delta): CollectorBalance
    {
        return DB::transaction(function () use ($agentId, $delta) {
            $this->ensureBalance($agentId);
            $balance = CollectorBalance::where('agent_id', $agentId)->lockForUpdate()->first();
            $newAmount = max(0, (float) $balance->uncollected_amount + $delta);
            $balance->uncollected_amount = $newAmount;
            $cap = $this->effectiveCap($agentId);
            if ($cap && $newAmount >= $cap['amount'] - 0.0001) {
                if (! $balance->is_blocked) {
                    $this->block($balance, $cap['amount']);
                }
            } elseif ($delta < 0 && $balance->is_blocked) {
                $balance->is_blocked = false;
                $balance->blocked_at = null;
            }
            $balance->save();

            return $balance;
        });
    }

    /**
     * Confirmed deposit. The TPE is released only when everything held has been deposited (TDR);
     * a partial deposit lowers the amount held but a blocked collector stays blocked.
     */
    public function applyPayInConfirm(int $agentId, float $confirmedAmount): CollectorBalance
    {
        return DB::transaction(function () use ($agentId, $confirmedAmount) {
            $this->ensureBalance($agentId);
            $balance = CollectorBalance::where('agent_id', $agentId)->lockForUpdate()->first();
            $remaining = max(0, round((float) $balance->uncollected_amount - $confirmedAmount, 2));
            $balance->uncollected_amount = $remaining;
            $balance->last_pay_in_at = now();
            if ($balance->is_blocked && $remaining <= 0) {
                $balance->is_blocked = false;
                $balance->blocked_at = null;
                Log::info('SUNU_COLLECTOR_RELEASED', ['agent_id' => $agentId, 'reason' => 'full_deposit']);
            }
            $balance->save();

            return $balance;
        });
    }

    /** Office releases a blocked TPE without a full deposit (exception, note required). */
    public function release(int $agentId, int $userId, string $note): CollectorBalance
    {
        return DB::transaction(function () use ($agentId, $userId, $note) {
            $this->ensureBalance($agentId);
            $balance = CollectorBalance::where('agent_id', $agentId)->lockForUpdate()->first();
            $balance->is_blocked = false;
            $balance->blocked_at = null;
            $balance->released_by = $userId;
            $balance->released_at = now();
            $balance->release_note = $note;
            $balance->save();

            Log::info('SUNU_COLLECTOR_RELEASED', [
                'agent_id' => $agentId, 'reason' => 'manual', 'by_user_id' => $userId,
                'uncollected_amount' => (float) $balance->uncollected_amount,
            ]);

            return $balance;
        });
    }

    /** Limit changed on the web: block when the amount held now reaches it, release when it no longer does. */
    public function refreshBlock(int $agentId, ?int $userId = null): CollectorBalance
    {
        return DB::transaction(function () use ($agentId, $userId) {
            $this->ensureBalance($agentId);
            $balance = CollectorBalance::where('agent_id', $agentId)->lockForUpdate()->first();
            $cap = $this->effectiveCap($agentId);
            $held = (float) $balance->uncollected_amount;
            if ($cap && $held >= $cap['amount'] - 0.0001) {
                if (! $balance->is_blocked) {
                    $this->block($balance, $cap['amount']);
                }
            } elseif ($balance->is_blocked) {
                $balance->is_blocked = false;
                $balance->blocked_at = null;
                $balance->released_by = $userId;
                $balance->released_at = now();
                $balance->release_note = 'limit changed';
                Log::info('SUNU_COLLECTOR_RELEASED', ['agent_id' => $agentId, 'reason' => 'limit_changed', 'by_user_id' => $userId]);
            }
            $balance->save();

            return $balance;
        });
    }

    protected function block(CollectorBalance $balance, float $capAmount): void
    {
        $balance->is_blocked = true;
        $balance->blocked_at = now();
        Log::info('SUNU_COLLECTOR_BLOCKED', [
            'agent_id' => $balance->agent_id,
            'uncollected_amount' => (float) $balance->uncollected_amount,
            'cap_amount' => $capAmount,
        ]);
    }
}
