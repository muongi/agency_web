<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\CoreBankingSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClientAccountApiTest extends TestCase
{
    use RefreshDatabase;

    private const KATO_BASE = 'https://kato.test/api/v2/agency';

    protected function createBusinessUnit(): BusinessUnit
    {
        return BusinessUnit::create([
            'name' => 'Client Account API BU',
            'slug' => 'client-account-api-bu-'.uniqid(),
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validClientAccountPayload(BusinessUnit $businessUnit, array $clientOverrides = []): array
    {
        return [
            'business_unit_id' => $businessUnit->id,
            'client' => array_merge([
                'first_name' => 'DROGBA',
                'last_name' => 'DEDIER',
                'email' => 'n@gmail.com',
                'phone_number' => '07545548484',
                'gender' => 'Male',
            ], $clientOverrides),
            'account' => [
                'status' => 'active',
                'opening_date' => '2026-07-01',
            ],
        ];
    }

    public function test_open_account_accepts_empty_biometrics_status(): void
    {
        $businessUnit = $this->createBusinessUnit();

        $response = $this->post('/api/v1/client/account/open', $this->validClientAccountPayload($businessUnit, [
            'biometrics_status' => '',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $client = Client::query()->where('business_unit_id', $businessUnit->id)->first();

        $this->assertNotNull($client);
        $this->assertFalse($client->biometrics_status);
    }

    public function test_open_account_logs_request_data_with_client_fields(): void
    {
        Log::spy();

        $businessUnit = $this->createBusinessUnit();

        $this->post('/api/v1/client/account/open', $this->validClientAccountPayload($businessUnit, [
            'biometrics_status' => 'false',
        ]))->assertCreated();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'API Client Account Creation Request'
                    && ($context['request_data']['client']['gender'] ?? null) === 'Male'
                    && ($context['request_data']['client']['biometrics_status'] ?? null) === 'false';
            })
            ->once();
    }

    public function test_open_account_accepts_string_true_biometrics_status(): void
    {
        $businessUnit = $this->createBusinessUnit();

        $response = $this->post('/api/v1/client/account/open', $this->validClientAccountPayload($businessUnit, [
            'biometrics_status' => 'true',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $client = Client::query()->where('business_unit_id', $businessUnit->id)->first();

        $this->assertNotNull($client);
        $this->assertTrue($client->biometrics_status);
    }

    public function test_open_account_accepts_string_false_biometrics_status(): void
    {
        $businessUnit = $this->createBusinessUnit();

        $response = $this->post('/api/v1/client/account/open', $this->validClientAccountPayload($businessUnit, [
            'biometrics_status' => 'false',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $client = Client::query()->where('business_unit_id', $businessUnit->id)->first();

        $this->assertNotNull($client);
        $this->assertFalse($client->biometrics_status);
    }

    public function test_open_account_accepts_string_true_with_fingerprints(): void
    {
        $businessUnit = $this->createBusinessUnit();

        $response = $this->post('/api/v1/client/account/open', $this->validClientAccountPayload($businessUnit, [
            'biometrics_status' => 'true',
            'fingerprint1' => str_repeat('A', 684),
            'fingerprint2' => str_repeat('B', 684),
            'fingerprint3' => str_repeat('C', 684),
            'fingerprint4' => str_repeat('D', 684),
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $client = Client::query()->where('business_unit_id', $businessUnit->id)->first();

        $this->assertNotNull($client);
        $this->assertTrue($client->biometrics_status);
        $this->assertNotNull($client->fingerprint1);
    }

    public function test_open_account_rejects_invalid_gender(): void
    {
        Log::spy();

        $businessUnit = $this->createBusinessUnit();

        $response = $this->post('/api/v1/client/account/open', $this->validClientAccountPayload($businessUnit, [
            'gender' => 'Homme',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'errors' => [
                    'client' => ['gender'],
                ],
            ]);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'API Client Account Creation Validation Failed'
                    && ($context['request_data']['client']['gender'] ?? null) === 'Homme';
            })
            ->once();
    }

    protected function createKatoBusinessUnit(): BusinessUnit
    {
        $cbs = CoreBankingSystem::create([
            'name' => 'KATO '.uniqid(),
            'code' => 'kato',
            'base_url' => self::KATO_BASE,
            'is_active' => true,
        ]);

        return BusinessUnit::create([
            'name' => 'KATO Client API BU '.uniqid(),
            'slug' => 'kato-client-api-bu-'.uniqid(),
            'is_active' => true,
            'core_banking_system_id' => $cbs->id,
            'kato_company_id' => 'company-guid-api',
            'kato_token' => 'test-jwt-token',
            'kato_registered_at' => now(),
        ]);
    }

    protected function createKatoRegistrationAgent(BusinessUnit $businessUnit, string $katoAgentId = 'AGTAPI'): Agent
    {
        $agent = Agent::create([
            'business_unit_id' => $businessUnit->id,
            'first_name' => 'API',
            'second_name' => 'Agent',
            'email' => 'api-agent-'.uniqid().'@test.local',
            'username' => 'apiagent'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000111',
            'id_passport' => 'ID-'.uniqid(),
            'password' => Hash::make('password'),
            'kato_agent_id' => $katoAgentId,
        ]);

        $agent->floatAccount()?->update(['account_number' => $katoAgentId.'FLOAT']);
        $agent->commissionAccount()?->update(['account_number' => $katoAgentId.'COMM']);

        return $agent;
    }

    public function test_open_account_on_kato_bu_requires_agent_id(): void
    {
        $businessUnit = $this->createKatoBusinessUnit();

        $response = $this->post('/api/v1/client/account/open', array_merge(
            $this->validClientAccountPayload($businessUnit, [
                'id_passport' => 'B2578999',
                'year_of_birth' => '1990',
            ]),
            []
        ));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'errors' => [
                    'agent_id',
                ],
            ]);
    }

    public function test_open_account_on_kato_bu_sends_agent_account_number_to_kato(): void
    {
        Http::fake([
            self::KATO_BASE.'/members/register' => Http::response([
                'success' => true,
                'data' => [
                    'memberNo' => '88001',
                    'accounts' => [
                        [
                            'accountNumber' => '0100101000999',
                            'accountCode' => '001',
                            'product' => 'Savings',
                            'balance' => 0,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $businessUnit = $this->createKatoBusinessUnit();
        $agent = $this->createKatoRegistrationAgent($businessUnit, 'AGTAPI');

        $response = $this->post('/api/v1/client/account/open', array_merge(
            $this->validClientAccountPayload($businessUnit, [
                'id_passport' => 'B2578999',
                'year_of_birth' => '1990',
            ]),
            ['agent_id' => $agent->id]
        ));

        $response->assertCreated()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            if ($request->url() !== self::KATO_BASE.'/members/register') {
                return false;
            }

            return ($request->data()['agentAccountNumber'] ?? null) === 'AGTAPIFLOAT';
        });
    }

    public function test_open_account_maps_android_location_to_kato_town(): void
    {
        Http::fake([
            self::KATO_BASE.'/members/register' => Http::response([
                'success' => true,
                'data' => [
                    'memberNo' => '88004',
                    'accounts' => [
                        [
                            'accountNumber' => '0100101000888',
                            'accountCode' => '001',
                            'product' => 'Savings',
                            'balance' => 0,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $businessUnit = $this->createKatoBusinessUnit();
        $businessUnit->update([
            'address' => 'Av. Houari Boumediene Quartier Koulouba Ouagadougou',
        ]);
        $agent = $this->createKatoRegistrationAgent($businessUnit, 'AGTLOC');

        $response = $this->post('/api/v1/client/account/open', array_merge(
            $this->validClientAccountPayload($businessUnit, [
                'id_passport' => 'B1344556',
                'year_of_birth' => '2026',
                'date_of_birth' => '2026-07-01',
                'location' => 'Ouga',
            ]),
            ['agent_id' => $agent->id]
        ));

        $response->assertCreated();

        Http::assertSent(function ($request) {
            if ($request->url() !== self::KATO_BASE.'/members/register') {
                return false;
            }

            $body = $request->data();

            return ($body['town'] ?? null) === 'Ouga'
                && ($body['dob'] ?? null) === '2026-07-01';
        });
    }
}
