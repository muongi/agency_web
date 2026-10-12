<?php

namespace App\Console\Commands;

use App\Models\Agent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Test only: give every agent without a collection PIN the default PIN from SUNU_DEFAULT_AGENT_PIN.
 */
class SunuAgentsDefaultPin extends Command
{
    protected $signature = 'sunu:agents-default-pin {--business-unit= : Limit to one business unit id} {--force : Also overwrite agents that already have a PIN}';

    protected $description = 'Set the default collection PIN (SUNU_DEFAULT_AGENT_PIN) for agents without one (tests)';

    public function handle(): int
    {
        $pin = (string) config('sunu.default_agent_pin');
        if (! preg_match('/^\d{4}$/', $pin)) {
            $this->error('SUNU_DEFAULT_AGENT_PIN must be set to 4 digits in .env (then php artisan config:clear).');

            return self::FAILURE;
        }

        $agents = Agent::query()
            ->when($this->option('business-unit'), fn ($q, $bu) => $q->where('business_unit_id', (int) $bu))
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('collection_pin_hash'))
            ->get();

        foreach ($agents as $agent) {
            $agent->setCollectionPin($pin, true);
            $this->line("PIN set: #{$agent->id} {$agent->username}");
        }

        Log::info('SUNU_AGENTS_DEFAULT_PIN', ['count' => $agents->count(), 'force' => (bool) $this->option('force')]);
        $this->info($agents->count().' agent(s) updated.');

        return self::SUCCESS;
    }
}
