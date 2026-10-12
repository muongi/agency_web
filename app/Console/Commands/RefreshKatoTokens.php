<?php

namespace App\Console\Commands;

use App\Models\BusinessUnit;
use App\Models\CoreBankingSystem;
use App\Services\KatoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshKatoTokens extends Command
{
    protected $signature = 'kato:refresh-tokens
                            {--business-unit= : Refresh only this business unit ID}
                            {--dry-run : List business units that would be refreshed without calling KATO}';

    protected $description = 'Proactively refresh KATO JWT tokens for registered business units before they expire';

    public function handle(KatoService $katoService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $businessUnitId = $this->option('business-unit');
        $leadMinutes = (int) config('kato.token_refresh_lead_minutes', 15);

        $katoCbsIds = CoreBankingSystem::query()
            ->whereRaw('LOWER(code) = ?', ['kato'])
            ->pluck('id');

        $query = BusinessUnit::query()
            ->with('coreBankingSystem')
            ->whereNotNull('kato_company_id')
            ->whereIn('core_banking_system_id', $katoCbsIds)
            ->where('is_active', true);

        if ($businessUnitId) {
            $query->where('id', $businessUnitId);
        }

        $refreshed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($query->cursor() as $businessUnit) {
            if (! $businessUnit->hasKatoRefreshCredentials()) {
                $this->line("BU {$businessUnit->id} ({$businessUnit->name}): skipped — refresh credentials missing");
                $skipped++;

                continue;
            }

            if (! $businessUnit->isKatoTokenExpiringWithin($leadMinutes)) {
                $this->line("BU {$businessUnit->id} ({$businessUnit->name}): skipped — token still valid");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->info("BU {$businessUnit->id} ({$businessUnit->name}): would refresh token");
                $refreshed++;

                continue;
            }

            $result = $katoService->refreshKatoToken($businessUnit);

            if ($result['success']) {
                $this->info("BU {$businessUnit->id} ({$businessUnit->name}): token refreshed");
                $refreshed++;
            } else {
                $error = $result['error'] ?? 'unknown error';
                $this->warn("BU {$businessUnit->id} ({$businessUnit->name}): refresh failed — {$error}");
                Log::warning('KATO scheduled token refresh failed', [
                    'business_unit_id' => $businessUnit->id,
                    'error' => $error,
                ]);
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Refreshed/would refresh: {$refreshed}, skipped: {$skipped}, failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
