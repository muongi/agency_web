<?php

namespace App\Console\Commands;

use App\Models\BusinessUnit;
use Illuminate\Console\Command;

class BackfillKatoCredentials extends Command
{
    protected $signature = 'kato:backfill-credentials
                            {--dry-run : Show changes without writing to the database}
                            {--business-unit= : Limit to a single business unit ID}';

    protected $description = 'Backfill KATO admin usernames from kato_api_response for registered business units';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $businessUnitId = $this->option('business-unit');

        $query = BusinessUnit::query()
            ->with('coreBankingSystem')
            ->whereNotNull('kato_company_id');

        if ($businessUnitId) {
            $query->where('id', $businessUnitId);
        }

        $updated = 0;
        $skipped = 0;
        $passwordMissing = 0;
        $mismatches = 0;

        foreach ($query->cursor() as $businessUnit) {
            if (! $businessUnit->isKatoEnabled()) {
                $skipped++;
                continue;
            }

            $registeredUsername = $businessUnit->getKatoRegisteredAdminUsername();
            if (! $registeredUsername) {
                $this->warn("BU {$businessUnit->id} ({$businessUnit->name}): no adminUsername in kato_api_response");
                $skipped++;
                continue;
            }

            if ($businessUnit->kato_admin_username === $registeredUsername) {
                if (! $businessUnit->kato_admin_password) {
                    $this->line("BU {$businessUnit->id} ({$businessUnit->name}): username OK, password still missing");
                    $passwordMissing++;
                }
                continue;
            }

            if ($businessUnit->kato_admin_username && $businessUnit->kato_admin_username !== $registeredUsername) {
                $this->warn(
                    "BU {$businessUnit->id} ({$businessUnit->name}): username mismatch stored="
                    ."{$businessUnit->kato_admin_username} registered={$registeredUsername}"
                );
                $mismatches++;
            }

            if ($dryRun) {
                $this->info("BU {$businessUnit->id} ({$businessUnit->name}): would set username to {$registeredUsername}");
            } else {
                $businessUnit->update(['kato_admin_username' => $registeredUsername]);
                $this->info("BU {$businessUnit->id} ({$businessUnit->name}): username set to {$registeredUsername}");
            }

            $updated++;

            if (! $businessUnit->kato_admin_password) {
                $this->line("BU {$businessUnit->id} ({$businessUnit->name}): password still missing — enter manually in Superadmin");
                $passwordMissing++;
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete.' : 'Backfill complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Updated usernames', $updated],
                ['Skipped', $skipped],
                ['Password still missing', $passwordMissing],
                ['Username mismatches flagged', $mismatches],
            ]
        );

        return self::SUCCESS;
    }
}
