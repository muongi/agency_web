<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Services\KatoService;
use Illuminate\Console\Command;

class BackfillKatoAgentAccounts extends Command
{
    protected $signature = 'kato:backfill-agent-accounts
                            {--dry-run : Show what would be updated without writing}
                            {--business-unit= : Limit to a single business unit ID}';

    protected $description = 'Backfill KATO float/commission account numbers for agents with kato_agent_id';

    public function handle(KatoService $katoService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $businessUnitId = $this->option('business-unit');

        $query = Agent::query()
            ->whereNotNull('kato_agent_id')
            ->with(['businessUnit.coreBankingSystem', 'accounts']);

        if ($businessUnitId) {
            $query->where('business_unit_id', $businessUnitId);
        }

        $synced = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($query->cursor() as $agent) {
            $businessUnit = $agent->businessUnit;

            if (! $businessUnit instanceof BusinessUnit || ! $businessUnit->isKatoEnabled()) {
                $skipped++;
                continue;
            }

            if ($agent->hasKatoCbsAccounts()) {
                $skipped++;
                continue;
            }

            $floatAccountNumber = $agent->kato_agent_id.'FLOAT';

            if ($dryRun) {
                $this->info("Agent {$agent->id} ({$agent->kato_agent_id}): would fetch float via GET /float?agentAccountNumber={$floatAccountNumber}");
                $synced++;
                continue;
            }

            try {
                $result = $katoService->syncAgentFloatBalancesFromKato($agent, $businessUnit);

                if (! $result['success']) {
                    $this->warn("Agent {$agent->id}: ".($result['error'] ?? 'Sync failed'));
                    $failed++;
                    continue;
                }

                $updated = (int) ($result['sync']['updated'] ?? 0);

                if ($updated > 0) {
                    $this->info("Agent {$agent->id}: synced {$updated} KATO account(s)");
                    $synced++;
                } else {
                    $this->warn("Agent {$agent->id}: no accounts updated");
                    $failed++;
                }

                foreach ($result['sync']['errors'] ?? [] as $error) {
                    $this->warn("Agent {$agent->id}: {$error}");
                }
            } catch (\Throwable $e) {
                $this->error("Agent {$agent->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                [$dryRun ? 'Would sync' : 'Synced', $synced],
                ['Skipped', $skipped],
                ['Failed', $failed],
            ]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
