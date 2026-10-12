<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BiometricsApiTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $businessUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessUnit = BusinessUnit::create([
            'name' => 'Biometrics API BU',
            'slug' => 'biometrics-api-bu-'.uniqid(),
            'is_active' => true,
        ]);
    }

    /**
     * @return array{agent: Agent, client: Client, account: Account}
     */
    protected function createAgentClientAccount(array $clientOverrides = []): array
    {
        $uniq = str_replace('.', '', uniqid('', true));

        $agent = Agent::create([
            'business_unit_id' => $this->businessUnit->id,
            'first_name' => 'Bio',
            'second_name' => 'Agent',
            'email' => 'bio-agent-'.$uniq.'@test.local',
            'username' => 'bio'.$uniq,
            'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-BIO-'.$uniq,
            'password' => Hash::make('123456'),
        ]);

        $client = Client::create(array_merge([
            'business_unit_id' => $this->businessUnit->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ], $clientOverrides));

        $account = Account::create([
            'business_unit_id' => $this->businessUnit->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0000000003',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        return compact('agent', 'client', 'account');
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchPayload(Agent $agent, Account $account, array $overrides = []): array
    {
        return array_merge([
            'account_number' => $account->account_number,
            'device_serial' => 'a1b2c3d4e5f67890',
            'agent_id' => $agent->id,
            'mfi_code' => '7',
        ], $overrides);
    }

    public function test_fetch_returns_fingerprints_when_enabled(): void
    {
        $template = str_repeat('A', 684);
        ['agent' => $agent, 'account' => $account] = $this->createAgentClientAccount([
            'biometrics_status' => true,
            'fingerprint1' => $template,
        ]);

        $response = $this->postJson('/api/v1/biometrics/fetch', $this->fetchPayload($agent, $account));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('account_number', $account->account_number)
            ->assertJsonPath('holder_name', 'Jean  Dupont')
            ->assertJsonCount(4, 'fingerprints')
            ->assertJsonPath('fingerprints.0', $template)
            ->assertJsonPath('fingerprints.1', null)
            ->assertJsonPath('fingerprints.2', null)
            ->assertJsonPath('fingerprints.3', null);
    }

    public function test_fetch_returns_null_fingerprints_when_biometrics_disabled(): void
    {
        ['agent' => $agent, 'account' => $account] = $this->createAgentClientAccount([
            'biometrics_status' => false,
            'fingerprint1' => str_repeat('A', 684),
        ]);

        $response = $this->postJson('/api/v1/biometrics/fetch', $this->fetchPayload($agent, $account));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('fingerprints.0', null)
            ->assertJsonPath('fingerprints.1', null)
            ->assertJsonPath('fingerprints.2', null)
            ->assertJsonPath('fingerprints.3', null);
    }

    public function test_fetch_validation_fails_without_required_fields(): void
    {
        $this->postJson('/api/v1/biometrics/fetch', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['account_number', 'agent_id']);
    }

    public function test_fetch_fails_when_account_not_found(): void
    {
        ['agent' => $agent, 'account' => $account] = $this->createAgentClientAccount();

        $this->postJson('/api/v1/biometrics/fetch', $this->fetchPayload($agent, $account, [
            'account_number' => '9999999999',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "Account '9999999999' not found for this business unit");
    }

    public function test_fetch_fails_when_agent_not_found(): void
    {
        ['agent' => $agent, 'account' => $account] = $this->createAgentClientAccount();

        $this->postJson('/api/v1/biometrics/fetch', $this->fetchPayload($agent, $account, [
            'agent_id' => 99999,
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['agent_id']);
    }
}
