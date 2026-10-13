<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\CommissionConfiguration;
use App\Models\CoreBankingSystem;
use App\Models\Terminal;
use App\Models\Transaction;
use App\Models\TransactionCostConfiguration;
use App\Models\TransactionType;
use App\Services\InternalLedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InternalLedgerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createAgent(BusinessUnit $bu, string $emailPrefix, string $userPrefix): Agent
    {
        $uniq = str_replace('.', '', uniqid('', true));

        return Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => $emailPrefix.'-'.$uniq.'@test.local',
            'username' => $userPrefix.substr($uniq, 0, 10),
            'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-'.$uniq,
            'password' => Hash::make('123456'),
        ]);
    }

    protected function seedWithdrawalTypeAndFees(BusinessUnit $bu): TransactionType
    {
        $tt = TransactionType::updateOrCreate(
            ['code' => '0110000'],
            [
                'business_unit_id' => null,
                'name' => 'Cash withdrawal (test)',
                'description' => null,
                'is_active' => true,
                'internal_ledger_flow' => TransactionType::INTERNAL_LEDGER_FLOW_CASH_WITHDRAWAL,
            ]
        );
        $tt->businessUnits()->syncWithoutDetaching([$bu->id]);

        TransactionCostConfiguration::updateOrCreate(
            [
                'business_unit_id' => $bu->id,
                'transaction_type_id' => $tt->id,
            ],
            [
                'cost_type' => 'fixed',
                'fixed_amount' => 100,
                'percentage_rate' => null,
                'is_active' => true,
            ]
        );

        CommissionConfiguration::updateOrCreate(
            [
                'business_unit_id' => $bu->id,
                'transaction_type_id' => $tt->id,
            ],
            [
                'commission_type' => 'fixed',
                'fixed_amount' => 30,
                'percentage_rate' => null,
                'is_active' => true,
            ]
        );

        return $tt;
    }

    protected function seedDepositTypeAndFees(BusinessUnit $bu): TransactionType
    {
        $tt = TransactionType::updateOrCreate(
            ['code' => '210000'],
            [
                'business_unit_id' => null,
                'name' => 'Cash deposit (test)',
                'description' => null,
                'is_active' => true,
                'internal_ledger_flow' => TransactionType::INTERNAL_LEDGER_FLOW_CASH_DEPOSIT,
            ]
        );
        $tt->businessUnits()->syncWithoutDetaching([$bu->id]);

        TransactionCostConfiguration::updateOrCreate(
            [
                'business_unit_id' => $bu->id,
                'transaction_type_id' => $tt->id,
            ],
            [
                'cost_type' => 'fixed',
                'fixed_amount' => 50,
                'percentage_rate' => null,
                'is_active' => true,
            ]
        );

        CommissionConfiguration::updateOrCreate(
            [
                'business_unit_id' => $bu->id,
                'transaction_type_id' => $tt->id,
            ],
            [
                'commission_type' => 'fixed',
                'fixed_amount' => 20,
                'percentage_rate' => null,
                'is_active' => true,
            ]
        );

        return $tt;
    }

    public function test_biometric_withdraw_posts_internal_ledger_when_no_cbs(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Ledger BU',
            'slug' => 'ledger-bu-'.uniqid(),
            'is_active' => true,
            'core_banking_system_id' => null,
        ]);

        $this->seedWithdrawalTypeAndFees($bu);

        $agent = $this->createAgent($bu, 'ledger-agent', 'ledgerag');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-LED-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'GEN-LED-'.uniqid(),
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 50000,
        ]);

        $amount = 10000;
        $expectedCost = 100;
        $expectedCommission = 30;
        $expectedBusinessShare = 70;

        $res = $this->postJson('/api/v1/transactions/biometric-withdraw', [
            'business_unit_id' => $bu->id,
            'account_number' => $account->account_number,
            'amount' => $amount,
            'transaction_type_code' => '0110000',
            'device_serial' => $terminal->serial_number,
            'verification_method' => 'biometric',
            'reference' => 'REF-W-'.uniqid(),
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.client_name', 'C D')
            ->assertJsonPath('data.account_number', $account->account_number)
            ->assertJsonPath('data.transaction_amount', '10000.00')
            ->assertJsonPath('fees', '100.00')
            ->assertJsonPath('commission', '30.00');

        $tx = Transaction::findOrFail($res->json('data.id'));
        $this->assertNotNull($tx->ledger_posted_at);

        $account->refresh();
        $this->assertEquals('39900.00', (string) $account->balance);

        $float = $agent->accounts()->where('account_type', 'float')->first();
        $commissionAcc = $agent->accounts()->where('account_type', 'commission')->first();
        $this->assertEquals('10000.00', (string) $float->balance);
        $this->assertEquals('30.00', (string) $commissionAcc->balance);

        $buFee = Account::where('business_unit_id', $bu->id)
            ->where('accountable_type', BusinessUnit::class)
            ->where('account_type', 'business_fee')
            ->first();
        $this->assertNotNull($buFee);
        $this->assertEquals('70.00', (string) $buFee->balance);

        $this->assertEquals(number_format($expectedCost, 2, '.', ''), (string) $tx->transaction_cost);
        $this->assertEquals(number_format($expectedCommission, 2, '.', ''), (string) $tx->commission);
        $this->assertEquals(number_format($expectedBusinessShare, 2, '.', ''), (string) $tx->business_share);
    }

    public function test_biometric_withdraw_skips_ledger_when_cbs_attached(): void
    {
        $cbs = CoreBankingSystem::create([
            'name' => 'Test CBS '.uniqid(),
            'code' => 'T'.substr(uniqid(), 0, 6),
            'is_active' => true,
        ]);

        $bu = BusinessUnit::create([
            'name' => 'CBS BU',
            'slug' => 'cbs-bu-'.uniqid(),
            'is_active' => true,
            'core_banking_system_id' => $cbs->id,
        ]);

        $this->seedWithdrawalTypeAndFees($bu);

        $agent = $this->createAgent($bu, 'cbs-agent', 'cbsag');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-CBS-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'GEN-CBS-'.uniqid(),
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 50000,
        ]);

        $res = $this->postJson('/api/v1/transactions/biometric-withdraw', [
            'business_unit_id' => $bu->id,
            'account_number' => $account->account_number,
            'amount' => 10000,
            'transaction_type_code' => '0110000',
            'device_serial' => $terminal->serial_number,
            'verification_method' => 'biometric',
            'reference' => 'REF-CBS-'.uniqid(),
        ]);

        $res->assertStatus(201);

        $tx = Transaction::findOrFail($res->json('data.id'));
        $this->assertNull($tx->ledger_posted_at);

        $account->refresh();
        $this->assertEquals('50000.00', (string) $account->balance);
    }

    public function test_biometric_withdraw_insufficient_balance_returns_422(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Low BU',
            'slug' => 'low-bu-'.uniqid(),
            'is_active' => true,
            'core_banking_system_id' => null,
        ]);

        $this->seedWithdrawalTypeAndFees($bu);

        $agent = $this->createAgent($bu, 'low-agent', 'lowag');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-LOW-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'GEN-LOW-'.uniqid(),
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 50,
        ]);

        $reference = 'REF-LOW-'.uniqid();

        $res = $this->postJson('/api/v1/transactions/biometric-withdraw', [
            'business_unit_id' => $bu->id,
            'account_number' => $account->account_number,
            'amount' => 10000,
            'transaction_type_code' => '0110000',
            'device_serial' => $terminal->serial_number,
            'verification_method' => 'biometric',
            'reference' => $reference,
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('transactions', ['reference' => $reference]);
    }

    public function test_cash_deposit_posts_internal_ledger(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Deposit BU',
            'slug' => 'dep-bu-'.uniqid(),
            'is_active' => true,
            'core_banking_system_id' => null,
        ]);

        $this->seedDepositTypeAndFees($bu);

        $agent = $this->createAgent($bu, 'dep-agent', 'depag');

        $float = $agent->accounts()->where('account_type', 'float')->first();
        $float->update(['balance' => 20000]);

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-DEP-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'GEN-DEP-'.uniqid(),
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $amount = 5000;

        $res = $this->postJson('/api/v1/transactions/cash-deposit', [
            'business_unit_id' => $bu->id,
            'agent_id' => $agent->id,
            'pin' => '123456',
            'account_number' => $account->account_number,
            'amount' => $amount,
            'transaction_type_code' => '210000',
            'device_serial' => $terminal->serial_number,
            'reference' => 'REF-DEP-'.uniqid(),
        ]);

        $res->assertStatus(201)->assertJsonPath('success', true);

        $tx = Transaction::findOrFail($res->json('data.id'));
        $this->assertNotNull($tx->ledger_posted_at);

        $float->refresh();
        $this->assertEquals('15000.00', (string) $float->balance);

        $account->refresh();
        $this->assertEquals('4950.00', (string) $account->balance);

        $commissionAcc = $agent->accounts()->where('account_type', 'commission')->first();
        $this->assertEquals('20.00', (string) $commissionAcc->balance);

        $buFee = Account::where('business_unit_id', $bu->id)
            ->where('accountable_type', BusinessUnit::class)
            ->where('account_type', 'business_fee')
            ->first();
        $this->assertNotNull($buFee);
        $this->assertEquals('30.00', (string) $buFee->balance);
    }

    public function test_internal_ledger_posting_is_idempotent(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Idempotency BU',
            'slug' => 'idem-bu-'.uniqid(),
            'is_active' => true,
            'core_banking_system_id' => null,
        ]);

        $this->seedWithdrawalTypeAndFees($bu);

        $agent = $this->createAgent($bu, 'idem-agent', 'idemag');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-IDEM-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'GEN-IDEM-'.uniqid(),
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 50000,
        ]);

        $amount = 10000;
        $reference = 'REF-IDEM-'.uniqid();

        $res = $this->postJson('/api/v1/transactions/biometric-withdraw', [
            'business_unit_id' => $bu->id,
            'account_number' => $account->account_number,
            'amount' => $amount,
            'transaction_type_code' => '0110000',
            'device_serial' => $terminal->serial_number,
            'verification_method' => 'biometric',
            'reference' => $reference,
        ]);

        $res->assertStatus(201);

        $tx = Transaction::findOrFail($res->json('data.id'));
        $tx->refresh();
        $account->refresh();

        $initialLedgerPostedAt = $tx->ledger_posted_at;

        $initialCustomerBalance = (string) $account->balance;
        $initialFloatBalance = (string) $agent->accounts()->where('account_type', 'float')->first()->balance;
        $initialCommissionBalance = (string) $agent->accounts()->where('account_type', 'commission')->first()->balance;
        $initialBusinessFeeBalance = (string) Account::where('business_unit_id', $bu->id)
            ->where('accountable_type', BusinessUnit::class)
            ->where('account_type', 'business_fee')
            ->first()
            ->balance;

        $service = app(InternalLedgerPostingService::class);
        $service->postForNewTransaction($tx);

        $tx->refresh();
        $account->refresh();

        $this->assertEquals($initialLedgerPostedAt, $tx->ledger_posted_at);
        $this->assertEquals($initialCustomerBalance, (string) $account->balance);
        $this->assertEquals($initialFloatBalance, (string) $agent->accounts()->where('account_type', 'float')->first()->balance);
        $this->assertEquals($initialCommissionBalance, (string) $agent->accounts()->where('account_type', 'commission')->first()->balance);
        $this->assertEquals(
            $initialBusinessFeeBalance,
            (string) Account::where('business_unit_id', $bu->id)
                ->where('accountable_type', BusinessUnit::class)
                ->where('account_type', 'business_fee')
                ->first()
                ->balance
        );
    }
}
