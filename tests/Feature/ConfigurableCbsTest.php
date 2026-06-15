<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\BusinessUnitCbsCredential;
use App\Models\BusinessUnitCbsTransactionMapping;
use App\Models\Client;
use App\Models\CommissionConfiguration;
use App\Models\CoreBankingSystem;
use App\Models\Terminal;
use App\Models\Transaction;
use App\Models\TransactionCostConfiguration;
use App\Models\TransactionType;
use App\Services\Cbs\CbsContextBuilder;
use App\Services\Cbs\CbsGateway;
use App\Services\Cbs\JsonPayloadMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfigurableCbsTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_payload_mapper_builds_nested_payload(): void
    {
        $mapper = new JsonPayloadMapper();

        $payload = $mapper->mapRequest([
            [
                'source' => 'transaction.amount',
                'target' => 'txn.amount',
                'transform' => 'float',
                'required' => true,
            ],
            [
                'source' => 'account.account_number',
                'target' => 'acctNo',
                'required' => true,
            ],
            [
                'source' => null,
                'target' => 'channel',
                'default' => 'CASH',
            ],
        ], [
            'transaction.amount' => '500',
            'account.account_number' => 'ACC-001',
        ]);

        $this->assertSame(500.0, $payload['txn']['amount']);
        $this->assertSame('ACC-001', $payload['acctNo']);
        $this->assertSame('CASH', $payload['channel']);
    }

    public function test_configurable_cbs_deposit_with_basic_auth_posts_to_external_api(): void
    {
        Http::fake([
            'https://cbs.example.com/*' => Http::response([
                'success' => true,
                'transactionId' => 'CBS-TX-123',
            ], 200),
        ]);

        $setup = $this->seedConfigurableCbsDepositScenario();

        $res = $this->postJson('/api/v1/transactions/cash-deposit', [
            'business_unit_id' => $setup['businessUnit']->id,
            'agent_id' => $setup['agent']->id,
            'pin' => '123456',
            'account_number' => $setup['account']->account_number,
            'amount' => 5000,
            'transaction_type_code' => '210000',
            'device_serial' => $setup['terminal']->serial_number,
            'reference' => 'REF-CBS-'.uniqid(),
        ]);

        $res->assertStatus(201)->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://cbs.example.com/transactions/deposit'
                && $request->hasHeader('Authorization')
                && str_starts_with($request->header('Authorization')[0], 'Basic ')
                && $request['acctNo'] === 'ACC-CBS-001'
                && (float) $request['txnAmount'] === 5000.0;
        });

        $tx = Transaction::findOrFail($res->json('data.id'));
        $this->assertSame('CBS-TX-123', $tx->metadata['cbs_transaction_id'] ?? null);
    }

    public function test_configurable_cbs_withdraw_uses_jwt_bearer_auth(): void
    {
        Http::fake([
            'https://cbs.example.com/*' => Http::response([
                'success' => true,
                'data' => ['transactionId' => 'CBS-WD-99'],
            ], 200),
        ]);

        $bu = BusinessUnit::create([
            'name' => 'CBS Withdraw BU',
            'slug' => 'cbs-wd-'.uniqid(),
            'is_active' => true,
        ]);

        $cbs = CoreBankingSystem::create([
            'name' => 'Flex Withdraw',
            'code' => 'flexwd',
            'base_url' => 'https://cbs.example.com/api',
            'driver' => 'configurable',
            'auth_type' => 'jwt_bearer',
            'is_active' => true,
        ]);

        $bu->update(['core_banking_system_id' => $cbs->id]);

        BusinessUnitCbsCredential::create([
            'business_unit_id' => $bu->id,
            'bearer_token' => 'static-jwt-token',
        ]);

        $tt = TransactionType::updateOrCreate(
            ['code' => '0110000'],
            [
                'business_unit_id' => null,
                'name' => 'Cash withdrawal CBS',
                'is_active' => true,
                'internal_ledger_flow' => TransactionType::INTERNAL_LEDGER_FLOW_CASH_WITHDRAWAL,
            ]
        );
        $tt->businessUnits()->syncWithoutDetaching([$bu->id]);

        BusinessUnitCbsTransactionMapping::create([
            'business_unit_id' => $bu->id,
            'transaction_type_id' => $tt->id,
            'http_method' => 'POST',
            'endpoint_path' => 'transactions/withdraw',
            'request_mappings' => [
                ['source' => 'account.account_number', 'target' => 'accountNumber', 'required' => true],
                ['source' => 'transaction.amount', 'target' => 'amount', 'transform' => 'float', 'required' => true],
                ['source' => 'request.pin', 'target' => 'memberPin', 'required' => true],
            ],
            'response_mappings' => [
                ['cbs_path' => 'data.transactionId', 'internal_key' => 'transactionId'],
            ],
            'success_path' => 'success',
            'success_value' => 'true',
            'error_message_path' => 'message',
            'is_active' => true,
        ]);

        TransactionCostConfiguration::updateOrCreate(
            ['business_unit_id' => $bu->id, 'transaction_type_id' => $tt->id],
            ['cost_type' => 'fixed', 'fixed_amount' => 0, 'is_active' => true]
        );
        CommissionConfiguration::updateOrCreate(
            ['business_unit_id' => $bu->id, 'transaction_type_id' => $tt->id],
            ['commission_type' => 'fixed', 'fixed_amount' => 0, 'is_active' => true]
        );

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'wd-'.uniqid().'@test.local',
            'username' => 'wd'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000001',
            'id_passport' => 'WD-'.uniqid(),
            'password' => Hash::make('123456'),
        ]);
        $agent->accounts()->where('account_type', 'float')->first()?->update(['balance' => 50000]);

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-WD-'.uniqid(),
            'simcard_number' => '001',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'L',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'ACC-WD-001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 10000,
        ]);

        $gateway = app(CbsGateway::class);
        $result = $gateway->executeIfMapped($bu->fresh(['coreBankingSystem', 'cbsCredentials']), $tt, [
            'transaction' => ['amount' => 1000, 'reference' => 'REF-1', 'type_code' => '0110000'],
            'account' => $account,
            'client' => $client,
            'agent' => $agent,
            'request' => ['pin' => '1234'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('CBS-WD-99', $result['data']['transactionId']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/transactions/withdraw')
                && $request->hasHeader('Authorization')
                && $request->header('Authorization')[0] === 'Bearer static-jwt-token'
                && $request['memberPin'] === '1234';
        });
    }

    public function test_cbs_gateway_returns_null_when_no_mapping_exists(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'No Map BU',
            'slug' => 'nomap-'.uniqid(),
            'is_active' => true,
        ]);

        $cbs = CoreBankingSystem::create([
            'name' => 'Flex',
            'code' => 'flex',
            'base_url' => 'https://cbs.example.com',
            'driver' => 'configurable',
            'auth_type' => 'basic',
            'is_active' => true,
        ]);
        $bu->update(['core_banking_system_id' => $cbs->id]);

        $tt = TransactionType::create([
            'code' => '999999',
            'name' => 'Unmapped',
            'is_active' => true,
        ]);
        $tt->businessUnits()->attach($bu->id);

        $gateway = app(CbsGateway::class);
        $result = $gateway->executeIfMapped($bu->fresh('coreBankingSystem'), $tt, []);

        $this->assertNull($result);
    }

    /**
     * @return array{businessUnit: BusinessUnit, agent: Agent, terminal: Terminal, account: Account}
     */
    private function seedConfigurableCbsDepositScenario(): array
    {
        $bu = BusinessUnit::create([
            'name' => 'CBS Deposit BU',
            'slug' => 'cbs-dep-'.uniqid(),
            'is_active' => true,
        ]);

        $cbs = CoreBankingSystem::create([
            'name' => 'Flex Deposit',
            'code' => 'flexdep',
            'base_url' => 'https://cbs.example.com',
            'driver' => 'configurable',
            'auth_type' => 'basic',
            'is_active' => true,
        ]);

        $bu->update(['core_banking_system_id' => $cbs->id]);

        BusinessUnitCbsCredential::create([
            'business_unit_id' => $bu->id,
            'username' => 'api-user',
            'password' => 'secret-pass',
        ]);

        $tt = TransactionType::updateOrCreate(
            ['code' => '210000'],
            [
                'business_unit_id' => null,
                'name' => 'Cash deposit CBS',
                'is_active' => true,
                'internal_ledger_flow' => TransactionType::INTERNAL_LEDGER_FLOW_CASH_DEPOSIT,
            ]
        );
        $tt->businessUnits()->syncWithoutDetaching([$bu->id]);

        BusinessUnitCbsTransactionMapping::create([
            'business_unit_id' => $bu->id,
            'transaction_type_id' => $tt->id,
            'http_method' => 'POST',
            'endpoint_path' => 'transactions/deposit',
            'request_mappings' => [
                ['source' => 'account.account_number', 'target' => 'acctNo', 'required' => true],
                ['source' => 'transaction.amount', 'target' => 'txnAmount', 'transform' => 'float', 'required' => true],
            ],
            'response_mappings' => [
                ['cbs_path' => 'transactionId', 'internal_key' => 'transactionId'],
            ],
            'success_path' => 'success',
            'success_value' => 'true',
            'is_active' => true,
        ]);

        TransactionCostConfiguration::updateOrCreate(
            ['business_unit_id' => $bu->id, 'transaction_type_id' => $tt->id],
            ['cost_type' => 'fixed', 'fixed_amount' => 0, 'is_active' => true]
        );
        CommissionConfiguration::updateOrCreate(
            ['business_unit_id' => $bu->id, 'transaction_type_id' => $tt->id],
            ['commission_type' => 'fixed', 'fixed_amount' => 0, 'is_active' => true]
        );

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'dep-'.uniqid().'@test.local',
            'username' => 'dep'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000002',
            'id_passport' => 'DEP-'.uniqid(),
            'password' => Hash::make('123456'),
        ]);
        $agent->accounts()->where('account_type', 'float')->first()?->update(['balance' => 50000]);

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-DEP-'.uniqid(),
            'simcard_number' => '002',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'L',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'ACC-CBS-001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        return compact('bu', 'agent', 'terminal', 'account') + ['businessUnit' => $bu];
    }
}
