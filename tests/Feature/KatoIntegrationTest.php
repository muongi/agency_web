<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Card;
use App\Models\Client;
use App\Models\CommissionConfiguration;
use App\Models\CoreBankingSystem;
use App\Models\Terminal;
use App\Models\Transaction;
use App\Models\TransactionCostConfiguration;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\KatoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KatoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const KATO_BASE = 'https://kato.test/api/v2/agency';

    protected function createKatoCbs(): CoreBankingSystem
    {
        return CoreBankingSystem::create([
            'name' => 'KATO '.uniqid(),
            'code' => 'kato',
            'base_url' => self::KATO_BASE,
            'is_active' => true,
        ]);
    }

    protected function createKatoBusinessUnit(?string $token = 'test-jwt-token'): BusinessUnit
    {
        $cbs = $this->createKatoCbs();

        return BusinessUnit::create([
            'name' => 'KATO BU '.uniqid(),
            'slug' => 'kato-bu-'.uniqid(),
            'email' => 'bu@example.com',
            'phone' => '0712345678',
            'is_active' => true,
            'core_banking_system_id' => $cbs->id,
            'kato_company_id' => 'company-guid-123',
            'kato_token' => $token,
            'kato_registered_at' => now(),
        ]);
    }

    public function test_register_company_parses_nested_response(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/register' => Http::response([
                'success' => true,
                'message' => 'Company registered successfully',
                'data' => [
                    'companyId' => 'company-guid-abc',
                    'companyName' => 'Test Sacco',
                    'token' => 'jwt-from-register',
                    'expiresIn' => 86400,
                    'adminUsername' => 'testsacco',
                ],
            ], 200),
        ]);

        $cbs = $this->createKatoCbs();
        $bu = BusinessUnit::create([
            'name' => 'New Sacco',
            'slug' => 'new-sacco-'.uniqid(),
            'email' => 'info@test.co.ke',
            'phone' => '0711111111',
            'is_active' => true,
            'core_banking_system_id' => $cbs->id,
        ]);

        $service = new KatoService();
        $result = $service->registerCompany($bu, $cbs);

        $this->assertTrue($result['success']);
        $this->assertSame('company-guid-abc', $result['data']['companyId']);
        $this->assertSame('jwt-from-register', $result['data']['token']);
        $this->assertSame(86400, $result['data']['expiresIn']);

        Http::assertSent(function ($request) {
            if ($request->url() !== self::KATO_BASE.'/company/register') {
                return false;
            }

            $body = $request->data();

            return ($body['currencyCode'] ?? null) === config('kato.default_currency')
                && ($body['currencySymbol'] ?? null) === config('kato.default_currency')
                && ($body['countryCode'] ?? null) === config('kato.default_country_code');
        });
    }

    public function test_legacy_base_url_resolves_to_agency_company_register(): void
    {
        $legacyBase = 'https://kato.test/api/v2/company/register';
        $agencyRegisterUrl = 'https://kato.test/api/v2/agency/company/register';

        Http::fake([
            $agencyRegisterUrl => Http::response([
                'success' => true,
                'message' => 'Company registered successfully',
                'data' => [
                    'companyId' => 'legacy-co-id',
                    'token' => 'legacy-jwt',
                    'expiresIn' => 86400,
                ],
            ], 200),
        ]);

        $cbs = CoreBankingSystem::create([
            'name' => 'KATO Legacy URL',
            'code' => 'kato',
            'base_url' => $legacyBase,
            'is_active' => true,
        ]);

        $bu = BusinessUnit::create([
            'name' => 'Legacy Sacco',
            'slug' => 'legacy-sacco-'.uniqid(),
            'email' => 'legacy@test.co.ke',
            'phone' => '0799999999',
            'is_active' => true,
            'core_banking_system_id' => $cbs->id,
        ]);

        $service = new KatoService();
        $result = $service->registerCompany($bu, $cbs);

        $this->assertTrue($result['success']);
        $this->assertSame('legacy-co-id', $result['data']['companyId']);

        Http::assertSent(function ($request) use ($agencyRegisterUrl) {
            if ($request->url() !== $agencyRegisterUrl) {
                return false;
            }

            $body = $request->data();

            return isset($body['companyName'])
                && $body['companyName'] === 'Legacy Sacco'
                && ! isset($body['saccoName']);
        });
    }

    public function test_register_company_surfaces_api_validation_errors(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/register' => Http::response([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['Sacco name is required'],
            ], 400),
        ]);

        $cbs = $this->createKatoCbs();
        $bu = BusinessUnit::create([
            'name' => 'Error Sacco',
            'slug' => 'error-sacco-'.uniqid(),
            'email' => 'err@test.co.ke',
            'phone' => '0788888888',
            'is_active' => true,
            'core_banking_system_id' => $cbs->id,
        ]);

        $result = (new KatoService())->registerCompany($bu, $cbs);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Validation failed', $result['error']);
        $this->assertStringContainsString('Sacco name is required', $result['error']);
    }

    public function test_register_company_surfaces_duplicate_phone_message(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/register' => Http::response([
                'success' => false,
                'message' => 'A company with this phone number already exists',
            ], 400),
        ]);

        $cbs = $this->createKatoCbs();
        $bu = BusinessUnit::create([
            'name' => 'Dup Phone Sacco',
            'slug' => 'dup-phone-'.uniqid(),
            'email' => 'dup@test.co.ke',
            'phone' => '0721638835',
            'is_active' => true,
            'core_banking_system_id' => $cbs->id,
        ]);

        $result = (new KatoService())->registerCompany($bu, $cbs);

        $this->assertFalse($result['success']);
        $this->assertSame('A company with this phone number already exists', $result['error']);
    }

    public function test_register_agent_uses_agency_onboarding_endpoint(): void
    {
        Http::fake([
            self::KATO_BASE.'/onboarding/register' => Http::response([
                'success' => true,
                'message' => 'Agency registered successfully. Your Agent ID is: AGT26050001',
                'data' => [
                    'agentId' => 'AGT26050001',
                    'businessName' => 'Jane Doe Business',
                    'accounts' => [
                        ['accountNumber' => 'AGT26050001FLOAT', 'code' => 'FLOAT', 'description' => 'Agent Float Account', 'balance' => 0],
                        ['accountNumber' => 'AGT26050001COMM', 'code' => 'COMM', 'description' => 'Agent Commission Account', 'balance' => 0],
                    ],
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Jane',
            'second_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone_number' => '0711222333',
            'id_passport' => '12345678',
            'physical_address' => 'Nairobi CBD',
            'username' => 'jane_agent_'.uniqid(),
            'password' => Hash::make('password123'),
        ]);

        $katoService = new KatoService();
        $result = $katoService->registerAgent($agent, $bu);

        $this->assertTrue($result['success']);
        $this->assertSame('AGT26050001', $result['data']['agentId']);

        $sync = $katoService->syncAgentAccountsFromKato($agent, $bu, $result['data']['accounts']);
        $this->assertSame(2, $sync['updated']);
        $this->assertSame('AGT26050001FLOAT', $agent->fresh()->katoFloatAccountNumber());
        $this->assertSame('AGT26050001COMM', $agent->fresh()->katoCommissionAccountNumber());

        Http::assertSent(function ($request) use ($bu) {
            if ($request->url() !== self::KATO_BASE.'/onboarding/register') {
                return false;
            }

            $body = $request->data();

            return ($body['companyId'] ?? null) === $bu->kato_company_id
                && ($body['phoneNumber'] ?? null) === '0711222333'
                && ! array_key_exists('geoCoordinates', $body);
        });
    }

    public function test_super_admin_store_business_unit_persists_kato_credentials(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/register' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'companyId' => 'co-persist-1',
                    'token' => 'persisted-jwt',
                    'expiresIn' => 3600,
                    'adminUsername' => 'admin1',
                ],
            ], 200),
        ]);

        $cbs = $this->createKatoCbs();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)->post(route('superadmin.business-units.store'), [
            'name' => 'Persist KATO BU',
            'core_banking_system_id' => $cbs->id,
            'email' => 'persist@test.co.ke',
            'phone' => '0722222222',
        ]);

        $response->assertRedirect();

        $bu = BusinessUnit::where('name', 'Persist KATO BU')->first();
        $this->assertNotNull($bu);
        $this->assertSame('co-persist-1', $bu->kato_company_id);
        $this->assertSame('persisted-jwt', $bu->kato_token);
        $this->assertNotNull($bu->kato_admin_username);
        $this->assertNotNull($bu->kato_admin_password);
        $this->assertNotNull($bu->kato_admin_name);
    }

    public function test_super_admin_store_sends_custom_kato_admin_credentials(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/register' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'companyId' => 'co-custom-1',
                    'token' => 'custom-jwt',
                    'expiresIn' => 3600,
                    'adminUsername' => 'myadmin',
                ],
            ], 200),
        ]);

        $cbs = $this->createKatoCbs();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->post(route('superadmin.business-units.store'), [
            'name' => 'Custom Creds BU',
            'core_banking_system_id' => $cbs->id,
            'email' => 'custom@test.co.ke',
            'phone' => '0733333333',
            'kato_admin_name' => 'John Kimemia',
            'kato_admin_username' => 'myadmin',
            'kato_admin_password' => 'SecurePass123',
        ])->assertRedirect();

        $bu = BusinessUnit::where('name', 'Custom Creds BU')->first();
        $this->assertNotNull($bu);
        $this->assertSame('John Kimemia', $bu->kato_admin_name);
        $this->assertSame('myadmin', $bu->kato_admin_username);
        $this->assertSame('SecurePass123', $bu->kato_admin_password);

        Http::assertSent(function ($request) {
            if ($request->url() !== self::KATO_BASE.'/company/register') {
                return false;
            }

            $body = $request->data();

            return ($body['adminName'] ?? null) === 'John Kimemia'
                && ($body['adminUsername'] ?? null) === 'myadmin'
                && ($body['adminPassword'] ?? null) === 'SecurePass123';
        });
    }

    public function test_super_admin_update_kato_credentials_refreshes_token_via_company_login(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/login' => Http::response([
                'success' => true,
                'data' => [
                    'token' => 'refreshed-jwt',
                    'expiresIn' => 7200,
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit('old-jwt-token');
        $bu->update([
            'kato_admin_name' => 'Admin User',
            'kato_admin_username' => 'oldadmin',
            'kato_admin_password' => 'OldPass1234',
        ]);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->put(route('superadmin.business-units.update', $bu->id), [
            'name' => $bu->name,
            'core_banking_system_id' => $bu->core_banking_system_id,
            'kato_admin_name' => 'Admin User',
            'kato_admin_username' => 'newadmin',
            'kato_admin_password' => 'NewPass5678',
        ])->assertRedirect();

        $bu->refresh();
        $this->assertSame('newadmin', $bu->kato_admin_username);
        $this->assertSame('NewPass5678', $bu->kato_admin_password);
        $this->assertSame('refreshed-jwt', $bu->kato_token);

        Http::assertSent(function ($request) use ($bu) {
            if ($request->url() !== self::KATO_BASE.'/company/login') {
                return false;
            }

            $body = $request->data();

            return ($body['companyId'] ?? null) === $bu->kato_company_id
                && ($body['username'] ?? null) === 'newadmin'
                && ($body['password'] ?? null) === 'NewPass5678';
        });
    }

    public function test_super_admin_update_kato_admin_name_only_does_not_call_login(): void
    {
        Http::fake();

        $bu = $this->createKatoBusinessUnit();
        $bu->update([
            'kato_admin_name' => 'Old Admin',
            'kato_admin_username' => 'oldadmin',
            'kato_admin_password' => 'OldPass1234',
        ]);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->put(route('superadmin.business-units.update', $bu->id), [
            'name' => $bu->name,
            'core_banking_system_id' => $bu->core_banking_system_id,
            'kato_admin_name' => 'New Admin Name',
            'kato_admin_username' => 'oldadmin',
        ])->assertRedirect();

        $bu->refresh();
        $this->assertSame('New Admin Name', $bu->kato_admin_name);
        $this->assertSame('oldadmin', $bu->kato_admin_username);

        Http::assertNothingSent();
    }

    public function test_super_admin_refresh_kato_token_route(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/login' => Http::response([
                'success' => true,
                'data' => [
                    'token' => 'manual-refresh-jwt',
                    'expiresIn' => 3600,
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit('stale-jwt');
        $bu->update([
            'kato_admin_username' => 'admin1',
            'kato_admin_password' => 'Pass123456',
        ]);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)
            ->post(route('superadmin.business-units.refresh-kato-token', $bu->id))
            ->assertRedirect(route('superadmin.business-units.edit', $bu->id));

        $bu->refresh();
        $this->assertSame('manual-refresh-jwt', $bu->kato_token);

        Http::assertSent(function ($request) use ($bu) {
            return $request->url() === self::KATO_BASE.'/company/login'
                && ($request->data()['username'] ?? null) === 'admin1';
        });
    }

    public function test_register_member_stores_kato_member_no_on_client_create(): void
    {
        Http::fake([
            self::KATO_BASE.'/members/register' => Http::response(
                $this->katoMemberRegisterPayload('5678'),
                200
            ),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $user = User::factory()->create([
            'is_super_admin' => true,
            'current_business_unit_id' => $bu->id,
        ]);

        $this->actingAs($user)->post(route('clients.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Wanjiku',
            'phone_number' => '0798765432',
            'id_passport' => '87654321',
            'gender' => 'Female',
            'year_of_birth' => '1990',
        ])->assertRedirect(route('clients.index'));

        $client = Client::where('first_name', 'Jane')->first();
        $this->assertNotNull($client);
        $this->assertSame('5678', $client->kato_member_no);
        $this->assertCount(2, $client->accounts);
        $this->assertSame('0100101000001', $client->accounts()->where('kato_account_code', '001')->value('account_number'));
        $this->assertSame('0100101000002', $client->accounts()->where('kato_account_code', '002')->value('account_number'));
    }

    public function test_client_create_without_gender_on_kato_bu_fails_validation(): void
    {
        Http::fake();

        $bu = $this->createKatoBusinessUnit();
        $user = User::factory()->create([
            'is_super_admin' => true,
            'current_business_unit_id' => $bu->id,
        ]);

        $this->actingAs($user)->from(route('clients.create'))
            ->post(route('clients.store'), [
                'first_name' => 'No',
                'last_name' => 'Gender',
                'phone_number' => '0711000001',
                'id_passport' => 'ID-NO-GEN',
                'year_of_birth' => '1990',
            ])
            ->assertSessionHasErrors('gender');

        $this->assertNull(Client::where('first_name', 'No')->first());
        Http::assertNothingSent();
    }

    public function test_member_register_formats_aspnet_gender_validation_error(): void
    {
        Http::fake([
            self::KATO_BASE.'/members/register' => Http::response([
                'type' => 'https://tools.ietf.org/html/rfc9110#section-15.5.1',
                'title' => 'One or more validation errors occurred.',
                'status' => 400,
                'errors' => [
                    'Gender' => ['The Gender field is required.'],
                ],
            ], 400),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone_number' => '0722000000',
            'id_passport' => 'ID123',
            'gender' => 'Male',
            'year_of_birth' => '1985',
        ]);

        $result = (new KatoService())->registerMember($client, $bu);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Gender', $result['error']);
        $this->assertStringContainsString('required', $result['error']);
    }

    public function test_register_member_without_gender_fails_without_http_call(): void
    {
        Http::fake();

        $bu = $this->createKatoBusinessUnit();
        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'No',
            'last_name' => 'Gender',
            'phone_number' => '0722000001',
            'id_passport' => 'ID999',
            'year_of_birth' => '1990',
        ]);

        $result = (new KatoService())->registerMember($client, $bu);

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_sync_with_kato_route_registers_unsynced_client(): void
    {
        Http::fake([
            self::KATO_BASE.'/members/register' => Http::response(
                $this->katoMemberRegisterPayload('9901'),
                200
            ),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Sync',
            'last_name' => 'Me',
            'phone_number' => '0722111222',
            'id_passport' => 'SYNC-ID',
            'gender' => 'Male',
            'year_of_birth' => '1988',
        ]);

        $user = User::factory()->create([
            'is_super_admin' => true,
            'current_business_unit_id' => $bu->id,
        ]);

        $this->actingAs($user)
            ->post(route('clients.sync-kato', $client->id))
            ->assertRedirect();

        $client->refresh();
        $this->assertSame('9901', $client->kato_member_no);
        $this->assertNull($client->kato_registration_error);
        $this->assertCount(2, $client->accounts);
    }

    public function test_sync_member_accounts_from_kato_updates_existing_by_account_code(): void
    {
        $bu = $this->createKatoBusinessUnit();
        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Re',
            'last_name' => 'Sync',
            'phone_number' => '0722999888',
            'id_passport' => 'RESYNC',
            'gender' => 'Male',
            'year_of_birth' => '1985',
            'kato_member_no' => '1',
        ]);

        Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'OLD-NUMBER-001',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $service = new KatoService();
        $summary = $service->syncMemberAccountsFromKato($client, $bu, [
            ['accountNumber' => '0100101000001', 'accountCode' => '001', 'product' => 'Savings', 'balance' => 100],
            ['accountNumber' => '0100101000002', 'accountCode' => '002', 'product' => 'Shares', 'balance' => 0],
        ]);

        $this->assertSame(1, $summary['updated']);
        $this->assertSame(1, $summary['created']);
        $this->assertCount(2, $client->fresh()->accounts);
        $this->assertSame('0100101000001', $client->accounts()->where('kato_account_code', '001')->value('account_number'));
        $this->assertDatabaseMissing('accounts', ['account_number' => 'OLD-NUMBER-001']);
    }

    public function test_register_member_returns_accounts_in_result(): void
    {
        Http::fake([
            self::KATO_BASE.'/members/register' => Http::response(
                $this->katoMemberRegisterPayload('42'),
                200
            ),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Acc',
            'last_name' => 'Test',
            'phone_number' => '0722000111',
            'id_passport' => 'ACC-1',
            'gender' => 'Male',
            'year_of_birth' => '1990',
        ]);

        $result = (new KatoService())->registerMember($client, $bu);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']['accounts']);
        $this->assertSame('42', $result['data']['memberNo']);
    }

    public function test_bulk_sync_with_kato_skips_already_synced_clients(): void
    {
        Http::fake([
            self::KATO_BASE.'/members/register' => Http::response(
                $this->katoMemberRegisterPayload('BULK-01'),
                200
            ),
        ]);

        $bu = $this->createKatoBusinessUnit();

        $synced = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Already',
            'last_name' => 'Synced',
            'kato_member_no' => 'EXISTING',
            'gender' => 'Female',
            'phone_number' => '0722333444',
            'id_passport' => 'EXIST-1',
            'year_of_birth' => '1990',
        ]);

        $pending = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Needs',
            'last_name' => 'Sync',
            'gender' => 'Male',
            'phone_number' => '0722444555',
            'id_passport' => 'PEND-1',
            'year_of_birth' => '1991',
        ]);

        $user = User::factory()->create([
            'is_super_admin' => true,
            'current_business_unit_id' => $bu->id,
        ]);

        $this->actingAs($user)->post(route('clients.sync-kato-bulk'), [
            'client_ids' => [$synced->id, $pending->id],
        ])->assertRedirect(route('clients.index'));

        $pending->refresh();
        $this->assertSame('BULK-01', $pending->kato_member_no);
        $this->assertSame('EXISTING', $synced->fresh()->kato_member_no);

        Http::assertSentCount(1);
    }

    public function test_cash_deposit_kato_first_creates_transaction_with_kato_id(): void
    {
        Http::fake([
            self::KATO_BASE.'/transactions/deposit' => Http::response([
                'success' => true,
                'transactionId' => 'AGD-TEST-001',
                'message' => 'Deposit successful',
            ], 200),
            self::KATO_BASE.'/members/1234/balances' => Http::response([
                'success' => true,
                'data' => [
                    'memberNo' => '1234',
                    'balances' => [
                        ['accountCode' => '001', 'product' => 'Savings', 'balance' => 500],
                    ],
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $this->seedDepositType($bu);

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'ag-'.uniqid().'@test.local',
            'username' => 'ag'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000001',
            'id_passport' => 'ID-'.uniqid(),
            'password' => Hash::make('123456'),
            'kato_agent_id' => 'AGT001',
        ]);
        $this->seedAgentKatoAccounts($agent, 'AGT001');

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
            'kato_member_no' => '1234',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000001',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $ref = 'REF-KATO-DEP-'.uniqid();
        $res = $this->postJson('/api/v1/transactions/cash-deposit', [
            'business_unit_id' => $bu->id,
            'agent_id' => $agent->id,
            'pin' => '123456',
            'account_number' => $account->account_number,
            'amount' => 500,
            'transaction_type_code' => '210000',
            'device_serial' => $terminal->serial_number,
            'reference' => $ref,
        ]);

        $res->assertStatus(201);

        $tx = Transaction::where('reference', $ref)->first();
        $this->assertNotNull($tx);
        $this->assertSame('AGD-TEST-001', $tx->kato_transaction_id);
        $this->assertNotNull($tx->kato_synced_at);
        $this->assertNull($tx->ledger_posted_at);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), '/transactions/deposit')
                && ($body['accountNumber'] ?? null) === '0100101000001'
                && ($body['agentAccountNumber'] ?? null) === 'AGT001FLOAT'
                && ($body['amount'] ?? null) == 500
                && ! isset($body['memberAccountNumber'])
                && $request->hasHeader('X-Company-ID', 'company-guid-123')
                && str_contains($request->header('Authorization')[0] ?? '', 'Bearer test-jwt-token');
        });
    }

    public function test_cash_deposit_sends_agent_commission_from_business_config_to_kato(): void
    {
        Http::fake([
            self::KATO_BASE.'/transactions/deposit' => Http::response([
                'success' => true,
                'transactionId' => 'AGD-COMM-001',
                'agentFloatAccountNumber' => 'AGT001FLOAT',
                'memberAccountNumber' => '0100101000001',
                'amount' => 500,
                'commission' => 25,
                'message' => 'Deposit successful',
            ], 200),
            self::KATO_BASE.'/members/1234/balances' => Http::response([
                'success' => true,
                'data' => [
                    'memberNo' => '1234',
                    'balances' => [
                        ['accountCode' => '001', 'product' => 'Savings', 'balance' => 500],
                    ],
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $depositType = $this->seedDepositType($bu);

        TransactionCostConfiguration::updateOrCreate(
            [
                'business_unit_id' => $bu->id,
                'transaction_type_id' => $depositType->id,
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
                'transaction_type_id' => $depositType->id,
            ],
            [
                'commission_type' => 'fixed',
                'fixed_amount' => 25,
                'percentage_rate' => null,
                'is_active' => true,
            ]
        );

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'ag-comm-'.uniqid().'@test.local',
            'username' => 'agc'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000099',
            'id_passport' => 'ID-COMM-'.uniqid(),
            'password' => Hash::make('123456'),
            'kato_agent_id' => 'AGT001',
        ]);
        $this->seedAgentKatoAccounts($agent, 'AGT001');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-DEP-COMM-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
            'kato_member_no' => '1234',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000001',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $ref = 'REF-KATO-DEP-COMM-'.uniqid();
        $res = $this->postJson('/api/v1/transactions/cash-deposit', [
            'business_unit_id' => $bu->id,
            'agent_id' => $agent->id,
            'pin' => '123456',
            'account_number' => $account->account_number,
            'amount' => 500,
            'transaction_type_code' => '210000',
            'device_serial' => $terminal->serial_number,
            'reference' => $ref,
        ]);

        $res->assertStatus(201);

        $tx = Transaction::where('reference', $ref)->first();
        $this->assertNotNull($tx);
        $this->assertSame(50.0, (float) $tx->transaction_cost);
        $this->assertSame(25.0, (float) $tx->commission);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/transactions/deposit')) {
                return false;
            }
            $body = $request->data();

            return ($body['agentAccountNumber'] ?? null) === 'AGT001FLOAT'
                && ($body['accountNumber'] ?? null) === '0100101000001'
                && ($body['amount'] ?? null) == 500
                && ($body['agentCommission'] ?? null) == 25
                && ! isset($body['memberAccountNumber']);
        });
    }

    public function test_cash_deposit_kato_failure_does_not_create_local_transaction(): void
    {
        Http::fake([
            self::KATO_BASE.'/transactions/deposit' => Http::response([
                'success' => false,
                'message' => 'Insufficient float',
            ], 402),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $this->seedDepositType($bu);

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'ag2-'.uniqid().'@test.local',
            'username' => 'ag2'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000002',
            'id_passport' => 'ID2-'.uniqid(),
            'password' => Hash::make('123456'),
        ]);
        $this->seedAgentKatoAccounts($agent, 'AGT002');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-DEP2-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
            'kato_member_no' => '9999',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'GEN-KATO2-'.uniqid(),
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $ref = 'REF-KATO-FAIL-'.uniqid();
        $res = $this->postJson('/api/v1/transactions/cash-deposit', [
            'business_unit_id' => $bu->id,
            'agent_id' => $agent->id,
            'pin' => '123456',
            'account_number' => $account->account_number,
            'amount' => 500,
            'transaction_type_code' => '210000',
            'device_serial' => $terminal->serial_number,
            'reference' => $ref,
        ]);

        $res->assertStatus(422);
        $this->assertNull(Transaction::where('reference', $ref)->first());
    }

    public function test_biometric_withdraw_kato_requires_member_pin(): void
    {
        $bu = $this->createKatoBusinessUnit();
        $this->seedWithdrawalType($bu);

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'ag3-'.uniqid().'@test.local',
            'username' => 'ag3'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000003',
            'id_passport' => 'ID3-'.uniqid(),
            'password' => Hash::make('123456'),
        ]);

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-WD-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
            'kato_member_no' => '5555',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'GEN-WD-'.uniqid(),
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 50000,
        ]);

        $res = $this->postJson('/api/v1/transactions/biometric-withdraw', [
            'business_unit_id' => $bu->id,
            'account_number' => $account->account_number,
            'amount' => 1000,
            'transaction_type_code' => '0110000',
            'device_serial' => $terminal->serial_number,
            'verification_method' => 'biometric',
            'reference' => 'REF-NOPIN-'.uniqid(),
        ]);

        $res->assertStatus(422);
        $res->assertJsonPath('message', 'Member PIN is required for KATO withdrawal');
    }

    public function test_member_balances_endpoint_returns_kato_data(): void
    {
        Http::fake([
            self::KATO_BASE.'/members/1234/balances' => Http::response([
                'success' => true,
                'data' => [
                    'memberNo' => '1234',
                    'balances' => [
                        ['accountCode' => '001', 'product' => 'Savings', 'balance' => 15000.00],
                    ],
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Bal',
            'last_name' => 'Test',
            'kato_member_no' => '1234',
        ]);

        Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000001',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $res = $this->getJson('/api/v1/members/1234/balances?business_unit_id='.$bu->id);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.memberNo', '1234')
            ->assertJsonCount(1, 'data.balances');

        $this->assertSame('15000.00', (string) $client->accounts()->first()->fresh()->balance);
    }

    public function test_member_balances_returns_422_when_kato_not_enabled(): void
    {
        $cbs = CoreBankingSystem::create([
            'name' => 'Other CBS',
            'code' => 'other',
            'base_url' => 'https://other.test/api',
            'is_active' => true,
        ]);

        $bu = BusinessUnit::create([
            'name' => 'Non KATO BU',
            'slug' => 'non-kato-'.uniqid(),
            'email' => 'nk@example.com',
            'phone' => '0712000000',
            'is_active' => true,
            'core_banking_system_id' => $cbs->id,
        ]);

        $this->getJson('/api/v1/members/1234/balances?business_unit_id='.$bu->id)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_member_mini_statement_forwards_limit(): void
    {
        Http::fake([
            self::KATO_BASE.'/members/1234/mini-statement*' => Http::response([
                'success' => true,
                'data' => [
                    'memberNo' => '1234',
                    'transactions' => [
                        [
                            'transDate' => '2026-05-27T10:30:00',
                            'product' => 'Savings',
                            'direction' => 'CR',
                            'amount' => 500.00,
                            'narration' => 'Agency Deposit',
                            'referenceNo' => 'AGD-001',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();

        $res = $this->getJson('/api/v1/members/1234/mini-statement?business_unit_id='.$bu->id.'&limit=5');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.transactions');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/members/1234/mini-statement')
                && str_contains($request->url(), 'limit=5');
        });
    }

    public function test_account_balances_endpoint_resolves_member_from_account_number(): void
    {
        Http::fake([
            self::KATO_BASE.'/members/1234/balances' => Http::response([
                'success' => true,
                'data' => [
                    'memberNo' => '1234',
                    'balances' => [
                        ['accountCode' => '001', 'product' => 'Savings', 'balance' => 1200.00],
                    ],
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Acc',
            'last_name' => 'Bal',
            'kato_member_no' => '1234',
        ]);

        Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000001',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $res = $this->getJson('/api/v1/accounts/0100101000001/balances?business_unit_id='.$bu->id);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.accountNumber', '0100101000001')
            ->assertJsonPath('data.memberNo', '1234')
            ->assertJsonCount(1, 'data.balances');
    }

    public function test_account_mini_statement_endpoint_resolves_member_from_account_number(): void
    {
        Http::fake([
            self::KATO_BASE.'/members/1234/mini-statement*' => Http::response([
                'success' => true,
                'data' => [
                    'memberNo' => '1234',
                    'transactions' => [
                        ['referenceNo' => 'AGD-1', 'amount' => 100],
                    ],
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Acc',
            'last_name' => 'Stmt',
            'kato_member_no' => '1234',
        ]);

        Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000001',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $res = $this->getJson('/api/v1/accounts/0100101000001/mini-statement?business_unit_id='.$bu->id.'&limit=5');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.accountNumber', '0100101000001')
            ->assertJsonPath('data.memberNo', '1234')
            ->assertJsonCount(1, 'data.transactions');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/members/1234/mini-statement')
                && str_contains($request->url(), 'limit=5');
        });
    }

    public function test_cash_deposit_with_member_no_and_account_code_uses_kato_fallback_payload(): void
    {
        Http::fake([
            self::KATO_BASE.'/transactions/deposit' => Http::response([
                'success' => true,
                'transactionId' => 'AGD-MEM-001',
            ], 200),
            self::KATO_BASE.'/members/1234/balances' => Http::response([
                'success' => true,
                'data' => ['memberNo' => '1234', 'balances' => []],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $this->seedDepositType($bu);

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'ag-mem-'.uniqid().'@test.local',
            'username' => 'agm'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000009',
            'id_passport' => 'ID-MEM-'.uniqid(),
            'password' => Hash::make('123456'),
        ]);
        $this->seedAgentKatoAccounts($agent, 'AGT1234');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-MEM-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
            'kato_member_no' => '1234',
        ]);

        Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000001',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $ref = 'REF-MEM-DEP-'.uniqid();
        $res = $this->postJson('/api/v1/transactions/cash-deposit', [
            'business_unit_id' => $bu->id,
            'agent_id' => $agent->id,
            'pin' => '123456',
            'member_no' => '1234',
            'account_code' => '001',
            'amount' => 250,
            'transaction_type_code' => '210000',
            'device_serial' => $terminal->serial_number,
            'reference' => $ref,
        ]);

        $res->assertStatus(201);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/transactions/deposit')) {
                return false;
            }
            $body = $request->data();

            return ($body['accountNumber'] ?? null) === '0100101000001'
                && ($body['agentAccountNumber'] ?? null) === 'AGT1234FLOAT'
                && ($body['amount'] ?? null) == 250;
        });
    }

    public function test_biometric_withdraw_with_member_no_and_account_code(): void
    {
        Http::fake([
            self::KATO_BASE.'/transactions/withdraw' => Http::response([
                'success' => true,
                'transactionId' => 'AGW-MEM-001',
            ], 200),
            self::KATO_BASE.'/members/5555/balances' => Http::response([
                'success' => true,
                'data' => ['memberNo' => '5555', 'balances' => []],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $this->seedWithdrawalType($bu);

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'ag-wd-mem-'.uniqid().'@test.local',
            'username' => 'agw'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000010',
            'id_passport' => 'ID-WD-MEM-'.uniqid(),
            'password' => Hash::make('123456'),
        ]);
        $this->seedAgentKatoAccounts($agent, 'AGT5555');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-WD-MEM-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
            'kato_member_no' => '5555',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000999',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 50000,
        ]);

        Card::create([
            'business_unit_id' => $bu->id,
            'account_id' => $account->id,
            'card_number' => 'CARD-'.uniqid(),
            'prefix' => 'TST',
            'pin_hash' => Hash::make('1234'),
            'status' => 'active',
            'expiration_date' => now()->addYears(3)->toDateString(),
        ]);

        $ref = 'REF-WD-MEM-'.uniqid();
        $res = $this->postJson('/api/v1/transactions/biometric-withdraw', [
            'business_unit_id' => $bu->id,
            'member_no' => '5555',
            'account_code' => '001',
            'amount' => 200,
            'transaction_type_code' => '0110000',
            'device_serial' => $terminal->serial_number,
            'verification_method' => 'pin',
            'reference' => $ref,
            'pin' => '1234',
        ]);

        $res->assertStatus(201);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/transactions/withdraw')) {
                return false;
            }
            $body = $request->data();

            return ($body['accountNumber'] ?? null) === '0100101000999'
                && ($body['agentAccountNumber'] ?? null) === 'AGT5555FLOAT'
                && ($body['amount'] ?? null) == 200
                && ($body['memberPin'] ?? null) === '1234';
        });
    }

    public function test_biometric_withdraw_with_account_number_uses_account_number_kato_payload(): void
    {
        Http::fake([
            self::KATO_BASE.'/transactions/withdraw' => Http::response([
                'success' => true,
                'transactionId' => 'AGW-ACCT-001',
            ], 200),
            self::KATO_BASE.'/members/6666/balances' => Http::response([
                'success' => true,
                'data' => ['memberNo' => '6666', 'balances' => []],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $this->seedWithdrawalType($bu);

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'ag-wd-acct-'.uniqid().'@test.local',
            'username' => 'agwa'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000012',
            'id_passport' => 'ID-WD-ACCT-'.uniqid(),
            'password' => Hash::make('123456'),
        ]);
        $this->seedAgentKatoAccounts($agent, 'AGT6666');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-WD-ACCT-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
            'kato_member_no' => '6666',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000004',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 50000,
        ]);

        Card::create([
            'business_unit_id' => $bu->id,
            'account_id' => $account->id,
            'card_number' => 'CARD-ACCT-'.uniqid(),
            'prefix' => 'TST',
            'pin_hash' => Hash::make('1234'),
            'status' => 'active',
            'expiration_date' => now()->addYears(3)->toDateString(),
        ]);

        $ref = 'REF-WD-ACCT-'.uniqid();
        $res = $this->postJson('/api/v1/transactions/biometric-withdraw', [
            'business_unit_id' => $bu->id,
            'account_number' => $account->account_number,
            'amount' => 300,
            'transaction_type_code' => '0110000',
            'device_serial' => $terminal->serial_number,
            'verification_method' => 'biometric',
            'reference' => $ref,
            'pin' => '1234',
        ]);

        $res->assertStatus(201);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/transactions/withdraw')) {
                return false;
            }
            $body = $request->data();

            return ($body['accountNumber'] ?? null) === '0100101000004'
                && ($body['agentAccountNumber'] ?? null) === 'AGT6666FLOAT'
                && ($body['amount'] ?? null) == 300
                && ($body['memberPin'] ?? null) === '1234';
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function katoMemberRegisterPayload(string $memberNo): array
    {
        return [
            'success' => true,
            'message' => 'Member registered. Member No: '.$memberNo,
            'data' => [
                'memberNo' => $memberNo,
                'accounts' => [
                    [
                        'accountNumber' => '0100101000001',
                        'accountCode' => '001',
                        'product' => 'Savings',
                        'balance' => 0,
                    ],
                    [
                        'accountNumber' => '0100101000002',
                        'accountCode' => '002',
                        'product' => 'Shares',
                        'balance' => 0,
                    ],
                ],
            ],
        ];
    }

    protected function seedDepositType(BusinessUnit $bu): TransactionType
    {
        $tt = TransactionType::updateOrCreate(
            ['code' => '210000'],
            [
                'business_unit_id' => null,
                'name' => 'Cash deposit (kato test)',
                'is_active' => true,
                'internal_ledger_flow' => TransactionType::INTERNAL_LEDGER_FLOW_CASH_DEPOSIT,
            ]
        );
        $tt->businessUnits()->syncWithoutDetaching([$bu->id]);

        return $tt;
    }

    protected function seedWithdrawalType(BusinessUnit $bu): TransactionType
    {
        $tt = TransactionType::updateOrCreate(
            ['code' => '0110000'],
            [
                'business_unit_id' => null,
                'name' => 'Cash withdrawal (kato test)',
                'is_active' => true,
                'internal_ledger_flow' => TransactionType::INTERNAL_LEDGER_FLOW_CASH_WITHDRAWAL,
            ]
        );
        $tt->businessUnits()->syncWithoutDetaching([$bu->id]);

        return $tt;
    }

    protected function seedBalanceEnquiryType(BusinessUnit $bu): TransactionType
    {
        $tt = TransactionType::updateOrCreate(
            ['code' => '310000'],
            [
                'business_unit_id' => null,
                'name' => 'Balance Enquiry (kato test)',
                'is_active' => true,
                'internal_ledger_flow' => TransactionType::INTERNAL_LEDGER_FLOW_BALANCE_ENQUIRY,
            ]
        );
        $tt->businessUnits()->syncWithoutDetaching([$bu->id]);

        return $tt;
    }

    protected function seedMiniStatementType(BusinessUnit $bu): TransactionType
    {
        $tt = TransactionType::updateOrCreate(
            ['code' => '320000'],
            [
                'business_unit_id' => null,
                'name' => 'Mini Statement (kato test)',
                'is_active' => true,
                'internal_ledger_flow' => TransactionType::INTERNAL_LEDGER_FLOW_MINI_STATEMENT,
            ]
        );
        $tt->businessUnits()->syncWithoutDetaching([$bu->id]);

        return $tt;
    }

    protected function seedAgentKatoAccounts(Agent $agent, string $katoAgentId = 'AGT001'): void
    {
        $agent->floatAccount()?->update(['account_number' => $katoAgentId.'FLOAT']);
        $agent->commissionAccount()?->update(['account_number' => $katoAgentId.'COMM']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sampleWithdrawPayload(): array
    {
        return [
            'agentAccountNumber' => 'AGT4FLOAT',
            'accountNumber' => '0100101000001',
            'amount' => 5000,
            'memberPin' => '1234',
        ];
    }

    public function test_card_cash_withdrawal_posts_to_kato_when_core_banking_is_kato(): void
    {
        Http::fake([
            self::KATO_BASE.'/transactions/withdraw' => Http::response([
                'success' => true,
                'transactionId' => 'AGW-CARD-001',
            ], 200),
            self::KATO_BASE.'/members/7777/balances' => Http::response([
                'success' => true,
                'data' => ['memberNo' => '7777', 'balances' => []],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();

        $tt = TransactionType::updateOrCreate(
            ['code' => '0110000'],
            [
                'business_unit_id' => null,
                'name' => 'Cash withdrawal (card kato test)',
                'is_active' => true,
                'internal_ledger_flow' => null,
            ]
        );
        $tt->businessUnits()->syncWithoutDetaching([$bu->id]);

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'ag-card-wd-'.uniqid().'@test.local',
            'username' => 'agcwd'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000011',
            'id_passport' => 'ID-CARD-WD-'.uniqid(),
            'password' => Hash::make('123456'),
        ]);
        $this->seedAgentKatoAccounts($agent, 'AGT7777');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-CARD-WD-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
            'kato_member_no' => '7777',
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000004',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 50000,
        ]);

        $cardNumber = '7800000002';
        Card::create([
            'business_unit_id' => $bu->id,
            'account_id' => $account->id,
            'card_number' => $cardNumber,
            'prefix' => '7800',
            'pin_hash' => Hash::make('1234'),
            'status' => 'active',
            'expiration_date' => now()->addYears(3)->toDateString(),
        ]);

        $ref = 'TXN-CARD-WD-'.uniqid();
        $res = $this->postJson('/api/v1/transactions', [
            'business_unit_id' => $bu->id,
            'card_number' => $cardNumber,
            'pin' => '1234',
            'otp' => '56789',
            'amount' => 5600,
            'transaction_type_code' => '0110000',
            'device_serial' => $terminal->serial_number,
            'reference' => $ref,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true);

        $tx = Transaction::where('reference', $ref)->first();
        $this->assertNotNull($tx);
        $this->assertSame('AGW-CARD-001', $tx->kato_transaction_id);
        $this->assertNotNull($tx->kato_synced_at);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/transactions/withdraw')) {
                return false;
            }
            $body = $request->data();

            return ($body['accountNumber'] ?? null) === '0100101000004'
                && ($body['agentAccountNumber'] ?? null) === 'AGT7777FLOAT'
                && ($body['amount'] ?? null) == 5600
                && ($body['memberPin'] ?? null) === '1234';
        });
    }

    public function test_proactive_kato_token_refresh_before_withdraw(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/login' => Http::response([
                'success' => true,
                'data' => [
                    'token' => 'fresh-jwt',
                    'expiresIn' => 3600,
                ],
            ], 200),
            self::KATO_BASE.'/transactions/withdraw' => Http::response([
                'success' => true,
                'transactionId' => 'AGW-REFRESH-001',
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit('expired-jwt');
        $bu->update([
            'kato_token_expires_at' => now()->subHour(),
            'kato_admin_username' => 'admin1',
            'kato_admin_password' => 'Pass123456',
        ]);

        $service = new KatoService();
        $result = $service->withdraw($bu, $this->sampleWithdrawPayload());

        $this->assertTrue($result['success']);
        $bu->refresh();
        $this->assertSame('fresh-jwt', $bu->kato_token);

        Http::assertSent(function ($request) {
            return $request->url() === self::KATO_BASE.'/company/login';
        });

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/transactions/withdraw')) {
                return false;
            }

            return str_contains($request->header('Authorization')[0] ?? '', 'Bearer fresh-jwt');
        });
    }

    public function test_kato_withdraw_retries_once_after_401(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/login' => Http::response([
                'success' => true,
                'data' => [
                    'token' => 'retry-jwt',
                    'expiresIn' => 3600,
                ],
            ], 200),
            self::KATO_BASE.'/transactions/withdraw' => Http::sequence()
                ->push('', 401)
                ->push([
                    'success' => true,
                    'transactionId' => 'AGW-RETRY-001',
                ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit('stale-jwt');
        $bu->update([
            'kato_token_expires_at' => now()->addHour(),
            'kato_admin_username' => 'admin1',
            'kato_admin_password' => 'Pass123456',
        ]);

        $service = new KatoService();
        $result = $service->withdraw($bu, $this->sampleWithdrawPayload());

        $this->assertTrue($result['success']);
        $this->assertSame('AGW-RETRY-001', $result['data']['transactionId']);

        $loginCount = 0;
        $withdrawCount = 0;
        Http::recorded(function ($request) use (&$loginCount, &$withdrawCount) {
            if ($request->url() === self::KATO_BASE.'/company/login') {
                $loginCount++;
            }
            if (str_contains($request->url(), '/transactions/withdraw')) {
                $withdrawCount++;
            }
        });

        $this->assertSame(1, $loginCount);
        $this->assertSame(2, $withdrawCount);
    }

    public function test_kato_withdraw_fails_when_token_refresh_fails(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/login' => Http::response('', 401),
            self::KATO_BASE.'/transactions/withdraw' => Http::response('', 401),
        ]);

        $bu = $this->createKatoBusinessUnit('expired-jwt');
        $bu->update([
            'kato_token_expires_at' => now()->subHour(),
            'kato_admin_username' => 'admin1',
            'kato_admin_password' => 'Pass123456',
        ]);

        $service = new KatoService();
        $result = $service->withdraw($bu, $this->sampleWithdrawPayload());

        $this->assertFalse($result['success']);
        $this->assertSame(401, $result['http_status']);
        $this->assertStringContainsString('KATO token refresh failed', $result['error'] ?? '');

        $loginCount = 0;
        $withdrawCount = 0;
        Http::recorded(function ($request) use (&$loginCount, &$withdrawCount) {
            if ($request->url() === self::KATO_BASE.'/company/login') {
                $loginCount++;
            }
            if (str_contains($request->url(), '/transactions/withdraw')) {
                $withdrawCount++;
            }
        });

        $this->assertSame(2, $loginCount);
        $this->assertSame(1, $withdrawCount);
    }

    public function test_register_company_parses_flat_legacy_response(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/register' => Http::response([
                'success' => true,
                'message' => "Sacco 'Legacy Sacco' registered successfully",
                'companyId' => 'legacy-co-1',
                'adminUserId' => 99,
                'companyName' => 'Legacy Sacco',
                'saccoType' => 'Normal',
                'adminUsername' => 'legacyadmin',
            ], 200),
        ]);

        $cbs = $this->createKatoCbs();
        $bu = BusinessUnit::create([
            'name' => 'Legacy Sacco',
            'slug' => 'legacy-sacco-'.uniqid(),
            'email' => 'legacy@test.co.ke',
            'phone' => '0711111111',
            'is_active' => true,
            'core_banking_system_id' => $cbs->id,
        ]);

        $service = new KatoService();
        $result = $service->registerCompany($bu, $cbs);

        $this->assertTrue($result['success']);
        $this->assertSame('legacy-co-1', $result['data']['companyId']);
        $this->assertSame('legacyadmin', $result['data']['adminUsername']);
        $this->assertNotNull($result['data']['adminPassword']);
    }

    public function test_super_admin_store_persists_credentials_from_flat_legacy_response(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/register' => Http::response([
                'success' => true,
                'message' => 'OK',
                'companyId' => 'flat-co-1',
                'adminUserId' => 42,
                'adminUsername' => 'flatadmin',
            ], 200),
        ]);

        $cbs = $this->createKatoCbs();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->post(route('superadmin.business-units.store'), [
            'name' => 'Flat Response BU',
            'core_banking_system_id' => $cbs->id,
            'email' => 'flat@test.co.ke',
            'phone' => '0722222222',
        ])->assertRedirect();

        $bu = BusinessUnit::where('name', 'Flat Response BU')->first();
        $this->assertNotNull($bu);
        $this->assertSame('flat-co-1', $bu->kato_company_id);
        $this->assertSame('flatadmin', $bu->kato_admin_username);
        $this->assertNotNull($bu->kato_admin_password);
    }

    public function test_refresh_falls_back_to_api_response_username(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/login' => Http::response([
                'success' => true,
                'data' => [
                    'token' => 'fallback-jwt',
                    'expiresIn' => 3600,
                ],
            ], 200),
            self::KATO_BASE.'/transactions/withdraw' => Http::response([
                'success' => true,
                'transactionId' => 'AGW-FALLBACK-001',
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit('stale-jwt');
        $bu->update([
            'kato_admin_username' => null,
            'kato_admin_password' => 'Pass123456',
            'kato_token_expires_at' => now()->subHour(),
            'kato_api_response' => [
                'success' => true,
                'data' => [
                    'adminUsername' => 'fromjson',
                    'companyId' => $bu->kato_company_id,
                ],
            ],
        ]);

        $service = new KatoService();
        $result = $service->withdraw($bu, $this->sampleWithdrawPayload());

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            if ($request->url() !== self::KATO_BASE.'/company/login') {
                return false;
            }

            return ($request->data()['username'] ?? null) === 'fromjson';
        });
    }

    public function test_post_balance_enquiry_sends_fees_to_kato_and_creates_transaction(): void
    {
        Http::fake([
            self::KATO_BASE.'/transactions/balance-enquiry' => Http::response([
                'success' => true,
                'data' => [
                    'memberNo' => '1234',
                    'balances' => [
                        ['accountCode' => '001', 'product' => 'Savings', 'balance' => 1500.00],
                    ],
                    'enquiryCommission' => 5.00,
                    'commissionRef' => 'AGE-001',
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $this->seedBalanceEnquiryType($bu);

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'ag-be-'.uniqid().'@test.local',
            'username' => 'agbe'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000020',
            'id_passport' => 'ID-BE-'.uniqid(),
            'password' => Hash::make('123456'),
        ]);
        $this->seedAgentKatoAccounts($agent, 'AGTBE');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-BE-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
            'kato_member_no' => '1234',
        ]);

        Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000001',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $ref = 'REF-BE-'.uniqid();
        $res = $this->postJson('/api/v1/transactions/balance-enquiry', [
            'business_unit_id' => $bu->id,
            'agent_id' => $agent->id,
            'account_number' => '0100101000001',
            'transaction_type_code' => '310000',
            'device_serial' => $terminal->serial_number,
            'reference' => $ref,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.balances.0.balance', 1500);

        $tx = Transaction::where('reference', $ref)->first();
        $this->assertNotNull($tx);
        $this->assertSame('AGE-001', $tx->kato_transaction_id);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/transactions/balance-enquiry')) {
                return false;
            }
            $body = $request->data();

            return ($body['accountNumber'] ?? null) === '0100101000001'
                && ($body['agentAccountNumber'] ?? null) === 'AGTBEFLOAT';
        });
    }

    public function test_post_mini_statement_sends_fees_to_kato_and_creates_transaction(): void
    {
        Http::fake([
            self::KATO_BASE.'/transactions/mini-statement*' => Http::response([
                'success' => true,
                'data' => [
                    'memberNo' => '1234',
                    'count' => 1,
                    'transactions' => [
                        [
                            'transDate' => '2026-06-02T09:15:00',
                            'accountCode' => '001',
                            'product' => 'Savings',
                            'direction' => 'CR',
                            'amount' => 5000.00,
                            'narration' => 'Agency Deposit',
                            'referenceNo' => 'AGD-001',
                        ],
                    ],
                    'enquiryCommission' => 5.00,
                    'commissionRef' => 'AGE-002',
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $this->seedMiniStatementType($bu);

        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'ag-ms-'.uniqid().'@test.local',
            'username' => 'agms'.substr(uniqid(), 0, 8),
            'phone_number' => '+22670000021',
            'id_passport' => 'ID-MS-'.uniqid(),
            'password' => Hash::make('123456'),
        ]);
        $this->seedAgentKatoAccounts($agent, 'AGTMS');

        $terminal = Terminal::create([
            'business_unit_id' => $bu->id,
            'serial_number' => 'SN-MS-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'C',
            'last_name' => 'D',
            'kato_member_no' => '1234',
        ]);

        Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000001',
            'kato_account_code' => '001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $ref = 'REF-MS-'.uniqid();
        $res = $this->postJson('/api/v1/transactions/mini-statement', [
            'business_unit_id' => $bu->id,
            'agent_id' => $agent->id,
            'account_number' => '0100101000001',
            'transaction_type_code' => '320000',
            'device_serial' => $terminal->serial_number,
            'reference' => $ref,
            'limit' => 5,
        ]);

        $res->assertStatus(201)
            ->assertJsonCount(1, 'data.transactions');

        $tx = Transaction::where('reference', $ref)->first();
        $this->assertNotNull($tx);
        $this->assertSame('AGE-002', $tx->kato_transaction_id);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/transactions/mini-statement')) {
                return false;
            }
            $body = $request->data();

            return ($body['accountNumber'] ?? null) === '0100101000001'
                && ($body['agentAccountNumber'] ?? null) === 'AGTMSFLOAT'
                && str_contains($request->url(), 'limit=5');
        });
    }

    public function test_backfill_command_sets_username_from_legacy_response(): void
    {
        $bu = $this->createKatoBusinessUnit();
        $bu->update([
            'kato_admin_username' => null,
            'kato_api_response' => [
                'success' => true,
                'adminUsername' => 'legacyuser',
                'companyId' => $bu->kato_company_id,
            ],
        ]);

        $this->artisan('kato:backfill-credentials', ['--business-unit' => $bu->id])
            ->assertExitCode(0);

        $bu->refresh();
        $this->assertSame('legacyuser', $bu->kato_admin_username);
    }

    public function test_restore_kato_username_from_api_response(): void
    {
        $bu = $this->createKatoBusinessUnit();
        $bu->update([
            'kato_admin_username' => 'wronguser',
            'kato_api_response' => [
                'success' => true,
                'data' => [
                    'adminUsername' => 'correctuser',
                    'companyId' => $bu->kato_company_id,
                ],
            ],
        ]);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)
            ->post(route('superadmin.business-units.restore-kato-username', $bu->id))
            ->assertRedirect(route('superadmin.business-units.edit', $bu->id));

        $bu->refresh();
        $this->assertSame('correctuser', $bu->kato_admin_username);
    }

    public function test_edit_page_shows_disabled_refresh_button_when_credentials_missing(): void
    {
        $bu = $this->createKatoBusinessUnit();
        $bu->update([
            'kato_admin_username' => null,
            'kato_admin_password' => null,
        ]);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)
            ->get(route('superadmin.business-units.edit', $bu->id));

        $response->assertOk();
        $response->assertSee(__('common.kato_refresh_token'), false);
        $response->assertSee(__('common.kato_refresh_token_disabled_help'), false);
        $response->assertSee('disabled', false);
    }

    public function test_super_admin_update_persists_kato_username_and_password(): void
    {
        Http::fake([
            self::KATO_BASE.'/company/login' => Http::response([
                'success' => true,
                'data' => [
                    'token' => 'updated-jwt',
                    'expiresIn' => 3600,
                ],
            ], 200),
        ]);

        $bu = $this->createKatoBusinessUnit();
        $bu->update([
            'kato_admin_username' => 'wronguser',
            'kato_admin_password' => 'OldPass1234',
        ]);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->put(route('superadmin.business-units.update', $bu->id), [
            'name' => $bu->name,
            'core_banking_system_id' => $bu->core_banking_system_id,
            'kato_admin_username' => 'samplebank',
            'kato_admin_password' => 'NewPass5678',
        ])->assertRedirect(route('superadmin.business-units.edit', $bu->id));

        $bu->refresh();
        $this->assertSame('samplebank', $bu->kato_admin_username);
        $this->assertSame('NewPass5678', $bu->kato_admin_password);
    }

    public function test_edit_page_has_single_form_with_formaction_buttons(): void
    {
        $bu = $this->createKatoBusinessUnit();
        $bu->update([
            'kato_api_response' => [
                'success' => true,
                'data' => [
                    'adminUsername' => 'samplebank',
                    'companyId' => $bu->kato_company_id,
                ],
            ],
        ]);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)
            ->get(route('superadmin.business-units.edit', $bu->id));

        $response->assertOk();
        $content = $response->getContent();
        $response->assertSee(route('superadmin.business-units.update', $bu->id), false);
        $response->assertSee('formaction="'.route('superadmin.business-units.restore-kato-username', $bu->id).'"', false);
        $response->assertSee('formaction="'.route('superadmin.business-units.refresh-kato-token', $bu->id).'"', false);
        $response->assertSee('name="kato_admin_username"', false);
        $response->assertSee('name="kato_admin_password"', false);
        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action="[^"]*restore-kato-username/',
            $content
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action="[^"]*refresh-kato-token/',
            $content
        );
    }
}
