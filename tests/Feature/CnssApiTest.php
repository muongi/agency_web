<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\CnssContribution;
use App\Models\CnssVoluntaryEnrollment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Terminal;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CnssApiTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $businessUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessUnit = BusinessUnit::create([
            'name' => 'Test BU CNSS',
            'slug' => 'test-bu-cnss-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);
    }

    protected function enrollmentPayload(): array
    {
        return [
            'business_unit_id' => $this->businessUnit->id,
            'identification' => [
                'last_name' => 'Ouedraogo',
                'first_names' => 'Jean Baptiste',
                'gender' => 'M',
                'date_of_birth' => '1990-05-01',
                'phone' => '+22670000000',
            ],
            'other_information' => [
                'branch_of_activity' => 'informal',
                'occupation' => 'Trader',
                'insurance_start_date' => '2026-01-01',
                'payment_frequency' => 'monthly',
                'insurance_type_art' => '4',
            ],
            'beneficiary' => [
                'father_name' => 'Father',
            ],
        ];
    }

    public function test_enrollment_creates_numbers_and_is_fetchable(): void
    {
        $response = $this->postJson('/api/v1/cnss/voluntary-enrollments', $this->enrollmentPayload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'client_id',
                    'ledger_account_number',
                    'voluntary_insured_number',
                    'cnss_registration_affiliation_number',
                    'worker_number',
                    'effective_date',
                    'registration_date',
                    'status',
                ],
            ]);

        $id = $response->json('data.id');
        $voluntary = $response->json('data.voluntary_insured_number');
        $this->assertNotNull($response->json('data.client_id'));
        $this->assertNotNull($response->json('data.ledger_account_number'));
        $this->assertDatabaseHas('clients', [
            'id' => $response->json('data.client_id'),
            'business_unit_id' => $this->businessUnit->id,
        ]);
        $this->assertDatabaseHas('accounts', [
            'account_number' => $response->json('data.ledger_account_number'),
            'business_unit_id' => $this->businessUnit->id,
        ]);

        $this->getJson("/api/v1/cnss/voluntary-enrollments/{$id}?business_unit_id=".$this->businessUnit->id)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.voluntary_insured_number', $voluntary)
            ->assertJsonPath('data.form_data.identification.last_name', 'Ouedraogo');
    }

    public function test_enrollment_accepts_and_sanitizes_invalid_utf8_in_identification(): void
    {
        $payload = $this->enrollmentPayload();
        $payload['identification']['last_name'] = 'Ouedraogo'."\xE9";

        // postJson cannot encode invalid UTF-8; real clients often send multipart or form posts.
        $response = $this->post('/api/v1/cnss/voluntary-enrollments', $payload, [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $enrollment = CnssVoluntaryEnrollment::findOrFail($response->json('data.id'));
        $lastName = (string) data_get($enrollment->form_data, 'identification.last_name');

        $this->assertTrue(mb_check_encoding($lastName, 'UTF-8'));
        $this->assertSame('Ouedraogoé', $lastName);
        $this->assertNotFalse(json_encode($enrollment->form_data));
        $this->assertSame('Ouedraogoé', $enrollment->last_name);
    }

    public function test_art_five_requires_existing_cnss_number(): void
    {
        $payload = $this->enrollmentPayload();
        $payload['other_information']['insurance_type_art'] = '5';
        unset($payload['other_information']['existing_cnss_number']);

        $this->postJson('/api/v1/cnss/voluntary-enrollments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['other_information.existing_cnss_number']);
    }

    public function test_enrollment_with_device_serial_sets_terminal_and_first_agent(): void
    {
        $uniq = str_replace('.', '', uniqid('', true));
        $agent = Agent::create([
            'business_unit_id' => $this->businessUnit->id,
            'first_name' => 'Enroll',
            'second_name' => 'Agent',
            'email' => 'enroll-devserial-'.$uniq.'@test.local',
            'username' => 'enrds'.$uniq,
            'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-ENR-'.$uniq,
            'password' => Hash::make('123456'),
        ]);

        $serial = 'HEX-DEVICE-'.uniqid();
        $terminal = Terminal::create([
            'business_unit_id' => $this->businessUnit->id,
            'serial_number' => $serial,
            'simcard_number' => '000',
            'device_alias' => 'T-enroll',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $response = $this->postJson('/api/v1/cnss/voluntary-enrollments', array_merge(
            $this->enrollmentPayload(),
            ['device_serial' => $serial]
        ));

        $response->assertStatus(201);
        $id = $response->json('data.id');
        $enrollment = CnssVoluntaryEnrollment::findOrFail($id);
        $this->assertSame($terminal->id, $enrollment->terminal_id);
        $this->assertSame($agent->id, $enrollment->agent_id);
        $this->assertNotNull($enrollment->client_id);
        $this->assertNotNull(data_get($enrollment->form_data, 'other_information.bank_account_number'));
    }

    public function test_enrollment_json_preserves_declared_bank_account_number_when_client_sends_it(): void
    {
        $payload = $this->enrollmentPayload();
        $payload['other_information']['bank_account_number'] = 'CLIENT-BANK-REF-99';

        $response = $this->postJson('/api/v1/cnss/voluntary-enrollments', $payload);
        $response->assertStatus(201);
        $ledger = $response->json('data.ledger_account_number');
        $this->assertNotNull($ledger);

        $enrollment = CnssVoluntaryEnrollment::findOrFail($response->json('data.id'));
        $this->assertSame('CLIENT-BANK-REF-99', data_get($enrollment->form_data, 'other_information.declared_bank_account_number'));
        $this->assertSame($ledger, data_get($enrollment->form_data, 'other_information.bank_account_number'));
    }

    public function test_enrollment_multipart_creates_row_and_stores_documents(): void
    {
        Storage::fake('local');

        $fields = [
            'business_unit_id' => $this->businessUnit->id,
            'identification' => [
                'last_name' => 'Multipart',
                'first_names' => 'Test User',
                'gender' => 'F',
                'date_of_birth' => '1991-06-01',
                'phone' => '+22671110000',
                'marital_status' => 'Single',
                'birth_department' => 'Kadiogo',
                'country_of_birth' => 'Burkina Faso',
                'birth_certificate_number' => 'BC-99-001',
                'birth_certificate_issue' => 'Ouagadougou',
                'nationality' => 'Burkinabe',
                'address_sector' => 'Sector 1',
                'district' => 'District A',
                'department' => 'Kadiogo',
                'province' => 'Kadiogo',
                'postal_address' => '01 BP 1',
                'email' => 'multipart.cnss.test@example.bf',
            ],
            'other_information' => [
                'branch_of_activity' => 'retail',
                'occupation' => 'Shopkeeper',
                'insurance_start_date' => '2026-02-01',
                'payment_frequency' => 'Monthly',
                'insurance_type_art' => '4',
                'declared_contribution_amount' => '15000',
                'declared_income' => '150000',
                'bank_account_number' => 'BF49-EXTERNAL-999',
            ],
            'beneficiary' => [
                'dependent_children' => 'None',
                'beneficiary_address' => 'Ouagadougou',
                'emergency_contact_name' => 'Emergency Person',
                'emergency_contact_phone' => '+22672220000',
            ],
            'id_document' => UploadedFile::fake()->image('id.jpg', 100, 100),
            'id_document_back' => UploadedFile::fake()->image('id_back.jpg', 100, 100),
            'birth_certificate' => UploadedFile::fake()->image('bc.jpg', 100, 100),
        ];

        $response = $this->post('/api/v1/cnss/voluntary-enrollments', $fields, [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertStatus(201);
        $id = $response->json('data.id');
        $this->assertNotNull($id);

        $enrollment = CnssVoluntaryEnrollment::findOrFail($id);
        $this->assertNotNull($enrollment->id_document_path);
        $this->assertNotNull($enrollment->id_document_back_path);
        $this->assertNotNull($enrollment->birth_certificate_path);
        $this->assertSame('Emergency Person', $enrollment->emergency_contact_name);
        $this->assertSame('+22672220000', $enrollment->emergency_contact_phone);
        $this->assertSame('BF49-EXTERNAL-999', data_get($enrollment->form_data, 'other_information.declared_bank_account_number'));

        Storage::disk('local')->assertExists($enrollment->id_document_path);
        Storage::disk('local')->assertExists($enrollment->id_document_back_path);
        Storage::disk('local')->assertExists($enrollment->birth_certificate_path);
    }

    public function test_enrollment_multipart_requires_id_document_back(): void
    {
        Storage::fake('local');

        $fields = [
            'business_unit_id' => $this->businessUnit->id,
            'identification' => [
                'last_name' => 'Multipart',
                'first_names' => 'Test User',
                'gender' => 'F',
                'date_of_birth' => '1991-06-01',
                'phone' => '+22671110000',
                'marital_status' => 'Single',
                'birth_department' => 'Kadiogo',
                'country_of_birth' => 'Burkina Faso',
                'birth_certificate_number' => 'BC-99-001',
                'birth_certificate_issue' => 'Ouagadougou',
                'nationality' => 'Burkinabe',
                'address_sector' => 'Sector 1',
                'district' => 'District A',
                'department' => 'Kadiogo',
                'province' => 'Kadiogo',
                'postal_address' => '01 BP 1',
                'email' => 'multipart.cnss.test@example.bf',
            ],
            'other_information' => [
                'branch_of_activity' => 'retail',
                'occupation' => 'Shopkeeper',
                'insurance_start_date' => '2026-02-01',
                'payment_frequency' => 'Monthly',
                'insurance_type_art' => '4',
                'declared_contribution_amount' => '15000',
                'declared_income' => '150000',
                'bank_account_number' => 'BF49-EXTERNAL-999',
            ],
            'beneficiary' => [
                'dependent_children' => 'None',
                'beneficiary_address' => 'Ouagadougou',
                'emergency_contact_name' => 'Emergency Person',
                'emergency_contact_phone' => '+22672220000',
            ],
            'id_document' => UploadedFile::fake()->image('id.jpg', 100, 100),
            'birth_certificate' => UploadedFile::fake()->image('bc.jpg', 100, 100),
        ];

        $this->post('/api/v1/cnss/voluntary-enrollments', $fields, [
            'HTTP_ACCEPT' => 'application/json',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_document_back']);
    }

    public function test_enrollment_device_serial_unknown_returns_422(): void
    {
        $this->postJson('/api/v1/cnss/voluntary-enrollments', array_merge(
            $this->enrollmentPayload(),
            ['device_serial' => 'no-such-android-id-hex']
        ))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "Terminal with device_serial 'no-such-android-id-hex' not found for business unit {$this->businessUnit->id}");
    }

    public function test_enrollment_device_serial_terminal_without_agent_returns_422(): void
    {
        $serial = 'ORPHAN-TERM-'.uniqid();
        Terminal::create([
            'business_unit_id' => $this->businessUnit->id,
            'serial_number' => $serial,
            'simcard_number' => '000',
            'device_alias' => 'T0',
            'model_make' => 'Test',
        ]);

        $this->postJson('/api/v1/cnss/voluntary-enrollments', array_merge(
            $this->enrollmentPayload(),
            ['device_serial' => $serial]
        ))
            ->assertStatus(422)
            ->assertJsonPath('message', "Terminal '{$serial}' has no active agent assignment");
    }

    public function test_enrollment_with_terminal_id_serial_sets_terminal_and_first_agent(): void
    {
        $uniq = str_replace('.', '', uniqid('', true));
        $agent = Agent::create([
            'business_unit_id' => $this->businessUnit->id,
            'first_name' => 'Enroll',
            'second_name' => 'AgentTermId',
            'email' => 'enroll-termid-'.$uniq.'@test.local',
            'username' => 'enrtid'.$uniq,
            'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-ENR-TID-'.$uniq,
            'password' => Hash::make('123456'),
        ]);

        $androidId = 'ANDROID-ID-'.uniqid();
        $terminal = Terminal::create([
            'business_unit_id' => $this->businessUnit->id,
            'serial_number' => $androidId,
            'simcard_number' => '000',
            'device_alias' => 'T-termid',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $response = $this->postJson('/api/v1/cnss/voluntary-enrollments', array_merge(
            $this->enrollmentPayload(),
            ['terminal_id' => $androidId]
        ));

        $response->assertStatus(201);
        $id = $response->json('data.id');
        $enrollment = CnssVoluntaryEnrollment::findOrFail($id);
        $this->assertSame($terminal->id, $enrollment->terminal_id);
        $this->assertSame($agent->id, $enrollment->agent_id);
        $this->assertNotNull($enrollment->client_id);
        $this->assertNotNull(data_get($enrollment->form_data, 'other_information.bank_account_number'));
    }

    public function test_enrollment_terminal_id_unknown_returns_422(): void
    {
        $unknown = 'no-such-terminal-id-'.uniqid();
        $this->postJson('/api/v1/cnss/voluntary-enrollments', array_merge(
            $this->enrollmentPayload(),
            ['terminal_id' => $unknown]
        ))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "Terminal with terminal_id '{$unknown}' not found for business unit {$this->businessUnit->id}");
    }

    public function test_enrollment_terminal_id_terminal_without_agent_returns_422(): void
    {
        $serial = 'ORPHAN-TERMID-'.uniqid();
        Terminal::create([
            'business_unit_id' => $this->businessUnit->id,
            'serial_number' => $serial,
            'simcard_number' => '000',
            'device_alias' => 'T-orphan-termid',
            'model_make' => 'Test',
        ]);

        $this->postJson('/api/v1/cnss/voluntary-enrollments', array_merge(
            $this->enrollmentPayload(),
            ['terminal_id' => $serial]
        ))
            ->assertStatus(422)
            ->assertJsonPath('message', "Terminal '{$serial}' has no active agent assignment");
    }

    public function test_member_search_and_record_contribution(): void
    {
        $enroll = $this->postJson('/api/v1/cnss/voluntary-enrollments', $this->enrollmentPayload());
        $enroll->assertStatus(201);
        $voluntary = $enroll->json('data.voluntary_insured_number');
        $affiliation = $enroll->json('data.cnss_registration_affiliation_number');

        $this->getJson('/api/v1/cnss/members?business_unit_id='.$this->businessUnit->id.'&voluntary_no='.urlencode($voluntary))
            ->assertOk()
            ->assertJsonPath('ambiguous', false)
            ->assertJsonPath('data.0.voluntary_insured_number', $voluntary)
            ->assertJsonPath('data.0.business_unit_id', $this->businessUnit->id);

        $contrib = $this->postJson('/api/v1/cnss/contributions', [
            'business_unit_id' => $this->businessUnit->id,
            'voluntary_no' => $voluntary,
            'amount' => 15000.50,
            'reference_period' => '2026-01',
            'payment_frequency' => 'monthly',
            'mode' => CnssContribution::MODE_RECORD,
        ]);

        $contrib->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', 'record')
            ->assertJsonStructure(['data' => ['id', 'reference', 'amount']]);

        $this->assertDatabaseHas('cnss_contributions', [
            'voluntary_insured_number' => $voluntary,
            'affiliation_number' => null,
            'mode' => 'record',
        ]);

        $this->postJson('/api/v1/cnss/contributions', [
            'business_unit_id' => $this->businessUnit->id,
            'voluntary_no' => $voluntary,
            'affiliation_no' => $affiliation,
            'amount' => 100,
            'reference_period' => '2026-02',
            'payment_frequency' => 'monthly',
        ])->assertStatus(201);
    }

    public function test_contribution_unknown_member_returns_422(): void
    {
        $this->postJson('/api/v1/cnss/contributions', [
            'business_unit_id' => $this->businessUnit->id,
            'voluntary_no' => 'VINS99999999',
            'amount' => 10,
            'reference_period' => '2026-01',
            'payment_frequency' => 'monthly',
        ])->assertStatus(422);
    }

    public function test_ledger_contribution_creates_transaction(): void
    {
        $code = 'CNSSLEDG'.substr(str_replace('.', '', uniqid('', true)), 0, 8);
        config(['cnss.ledger_transaction_type_code' => $code]);

        $tt = TransactionType::create([
            'business_unit_id' => null,
            'name' => 'CNSS ledger test',
            'code' => $code,
            'description' => null,
            'is_active' => true,
            'cnss_enabled' => true,
        ]);
        $tt->businessUnits()->syncWithoutDetaching([$this->businessUnit->id]);

        $uniq = str_replace('.', '', uniqid('', true));
        $agent = Agent::create([
            'business_unit_id' => $this->businessUnit->id,
            'first_name' => 'Agent',
            'second_name' => 'One',
            'email' => 'agent-cnss-'.$uniq.'@test.local',
            'username' => 'agcnss'.$uniq,
            'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-'.$uniq,
            'password' => Hash::make('123456'),
        ]);

        $terminal = Terminal::create([
            'business_unit_id' => $this->businessUnit->id,
            'serial_number' => 'SN-CNSS-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T1',
            'model_make' => 'Test',
        ]);

        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $enroll = $this->postJson('/api/v1/cnss/voluntary-enrollments', array_merge(
            $this->enrollmentPayload(),
            ['device_serial' => $terminal->serial_number]
        ));
        $enroll->assertStatus(201);
        $voluntary = $enroll->json('data.voluntary_insured_number');
        $this->assertNotNull($enroll->json('data.ledger_account_number'));

        $res = $this->postJson('/api/v1/cnss/contributions', [
            'business_unit_id' => $this->businessUnit->id,
            'voluntary_no' => $voluntary,
            'amount' => 5000,
            'reference_period' => '2026-03',
            'payment_frequency' => 'monthly',
            'mode' => CnssContribution::MODE_LEDGER,
            'pin' => '123456',
            'device_serial' => $terminal->serial_number,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.mode', 'ledger')
            ->assertJsonStructure([
                'data' => [
                    'transaction' => [
                        'id',
                        'transaction_amount',
                        'transaction_type_code',
                    ],
                ],
            ]);

        $cid = $res->json('data.id');
        $row = CnssContribution::findOrFail($cid);
        $this->assertNotNull($row->transaction_id);
    }

    public function test_ledger_contribution_rejects_bad_pin(): void
    {
        $code = 'CNSSBAD'.substr(str_replace('.', '', uniqid('', true)), 0, 8);
        config(['cnss.ledger_transaction_type_code' => $code]);

        $tt = TransactionType::create([
            'business_unit_id' => null,
            'name' => 'CNSS ledger test 2',
            'code' => $code,
            'is_active' => true,
            'cnss_enabled' => true,
        ]);
        $tt->businessUnits()->syncWithoutDetaching([$this->businessUnit->id]);

        $uniq2 = str_replace('.', '', uniqid('', true));
        $agent = Agent::create([
            'business_unit_id' => $this->businessUnit->id,
            'first_name' => 'A',
            'second_name' => 'B',
            'email' => 'agent2-cnss-'.$uniq2.'@test.local',
            'username' => 'ag2cnss'.$uniq2,
            'phone_number' => '+2267'.substr($uniq2, 0, 8),
            'id_passport' => 'ID-'.$uniq2,
            'password' => Hash::make('999999'),
        ]);

        $terminal = Terminal::create([
            'business_unit_id' => $this->businessUnit->id,
            'serial_number' => 'SN2-'.uniqid(),
            'simcard_number' => '001',
            'device_alias' => 'T2',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $client = Client::create([
            'business_unit_id' => $this->businessUnit->id,
            'first_name' => 'X',
            'last_name' => 'Y',
        ]);
        $account = Account::create([
            'business_unit_id' => $this->businessUnit->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'GEN2-'.uniqid(),
            'opening_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $enroll = $this->postJson('/api/v1/cnss/voluntary-enrollments', $this->enrollmentPayload());
        $voluntary = $enroll->json('data.voluntary_insured_number');

        $this->postJson('/api/v1/cnss/contributions', [
            'business_unit_id' => $this->businessUnit->id,
            'voluntary_no' => $voluntary,
            'amount' => 100,
            'reference_period' => '2026-04',
            'payment_frequency' => 'monthly',
            'mode' => CnssContribution::MODE_LEDGER,
            'pin' => 'wrong-pin',
            'device_serial' => $terminal->serial_number,
            'account_number' => $account->account_number,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid PIN');
    }

    public function test_ledger_contribution_rejects_terminal_without_agent(): void
    {
        $code = 'CNSSNOAG'.substr(str_replace('.', '', uniqid('', true)), 0, 8);
        config(['cnss.ledger_transaction_type_code' => $code]);

        $tt = TransactionType::create([
            'business_unit_id' => null,
            'name' => 'CNSS ledger no agent',
            'code' => $code,
            'description' => null,
            'is_active' => true,
            'cnss_enabled' => true,
        ]);
        $tt->businessUnits()->syncWithoutDetaching([$this->businessUnit->id]);

        $terminal = Terminal::create([
            'business_unit_id' => $this->businessUnit->id,
            'serial_number' => 'SN-NO-AGENT-'.uniqid(),
            'simcard_number' => '002',
            'device_alias' => 'T3',
            'model_make' => 'Test',
        ]);

        $client = Client::create([
            'business_unit_id' => $this->businessUnit->id,
            'first_name' => 'Z',
            'last_name' => 'Z',
        ]);
        $account = Account::create([
            'business_unit_id' => $this->businessUnit->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => 'GEN3-'.uniqid(),
            'opening_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $enroll = $this->postJson('/api/v1/cnss/voluntary-enrollments', $this->enrollmentPayload());
        $enroll->assertStatus(201);
        $voluntary = $enroll->json('data.voluntary_insured_number');

        $this->postJson('/api/v1/cnss/contributions', [
            'business_unit_id' => $this->businessUnit->id,
            'voluntary_no' => $voluntary,
            'amount' => 50,
            'reference_period' => '2026-05',
            'payment_frequency' => 'monthly',
            'mode' => CnssContribution::MODE_LEDGER,
            'pin' => '123456',
            'device_serial' => $terminal->serial_number,
            'account_number' => $account->account_number,
        ])->assertStatus(422)
            ->assertJsonPath('message', "Terminal '{$terminal->serial_number}' has no active agent assignment");
    }

    public function test_ledger_contribution_without_business_unit_id_resolves_bu_from_member(): void
    {
        $code = 'CNSSNOBU'.substr(str_replace('.', '', uniqid('', true)), 0, 8);
        config(['cnss.ledger_transaction_type_code' => $code]);
        config(['cnss.default_ledger_account_number' => null]);

        $tt = TransactionType::create([
            'business_unit_id' => null,
            'name' => 'CNSS ledger no BU param',
            'code' => $code,
            'description' => null,
            'is_active' => true,
            'cnss_enabled' => true,
        ]);
        $tt->businessUnits()->syncWithoutDetaching([$this->businessUnit->id]);

        $uniq = str_replace('.', '', uniqid('', true));
        $agent = Agent::create([
            'business_unit_id' => $this->businessUnit->id,
            'first_name' => 'Agent',
            'second_name' => 'NoBu',
            'email' => 'agent-nobu-'.$uniq.'@test.local',
            'username' => 'agnobu'.$uniq,
            'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-NOBU-'.$uniq,
            'password' => Hash::make('123456'),
        ]);

        $terminal = Terminal::create([
            'business_unit_id' => $this->businessUnit->id,
            'serial_number' => 'SN-NOBU-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T-nobu',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $payload = $this->enrollmentPayload();
        $payload['device_serial'] = $terminal->serial_number;

        $enroll = $this->postJson('/api/v1/cnss/voluntary-enrollments', $payload);
        $enroll->assertStatus(201);
        $voluntary = $enroll->json('data.voluntary_insured_number');

        $res = $this->postJson('/api/v1/cnss/contributions', [
            'voluntary_no' => $voluntary,
            'amount' => 3000,
            'reference_period' => '2026-07',
            'payment_frequency' => 'monthly',
            'mode' => CnssContribution::MODE_LEDGER,
            'pin' => '123456',
            'device_serial' => $terminal->serial_number,
        ]);
        $res->assertStatus(201);
        $cid = $res->json('data.id');
        $row = CnssContribution::findOrFail($cid);
        $this->assertSame($this->businessUnit->id, $row->business_unit_id);
    }

    public function test_contribution_without_business_unit_id_ambiguous_returns_422(): void
    {
        $bu2 = BusinessUnit::create([
            'name' => 'Second BU contrib',
            'slug' => 'test-bu-cnss-contrib-2-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $r1 = $this->postJson('/api/v1/cnss/voluntary-enrollments', $this->enrollmentPayload());
        $r1->assertStatus(201);
        $voluntary = $r1->json('data.voluntary_insured_number');

        $payload2 = $this->enrollmentPayload();
        $payload2['business_unit_id'] = $bu2->id;
        $r2 = $this->postJson('/api/v1/cnss/voluntary-enrollments', $payload2);
        $r2->assertStatus(201);
        $this->assertSame($voluntary, $r2->json('data.voluntary_insured_number'));

        $this->postJson('/api/v1/cnss/contributions', [
            'voluntary_no' => $voluntary,
            'amount' => 10,
            'reference_period' => '2026-01',
            'payment_frequency' => 'monthly',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Multiple CNSS members match this search; include business_unit_id in the request.');
    }

    public function test_ledger_contribution_without_account_source_returns_422(): void
    {
        $code = 'CNSSNOAC'.substr(str_replace('.', '', uniqid('', true)), 0, 8);
        config(['cnss.ledger_transaction_type_code' => $code]);
        config(['cnss.default_ledger_account_number' => null]);

        $tt = TransactionType::create([
            'business_unit_id' => null,
            'name' => 'CNSS ledger no acct',
            'code' => $code,
            'description' => null,
            'is_active' => true,
            'cnss_enabled' => true,
        ]);
        $tt->businessUnits()->syncWithoutDetaching([$this->businessUnit->id]);

        $uniq = str_replace('.', '', uniqid('', true));
        $agent = Agent::create([
            'business_unit_id' => $this->businessUnit->id,
            'first_name' => 'Agent',
            'second_name' => 'NoAcct',
            'email' => 'agent-noacct-'.$uniq.'@test.local',
            'username' => 'agnoac'.$uniq,
            'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-NOAC-'.$uniq,
            'password' => Hash::make('123456'),
        ]);

        $terminal = Terminal::create([
            'business_unit_id' => $this->businessUnit->id,
            'serial_number' => 'SN-NOAC-'.uniqid(),
            'simcard_number' => '000',
            'device_alias' => 'T-noac',
            'model_make' => 'Test',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        $enroll = $this->postJson('/api/v1/cnss/voluntary-enrollments', array_merge(
            $this->enrollmentPayload(),
            ['device_serial' => $terminal->serial_number]
        ));
        $enroll->assertStatus(201);
        $voluntary = $enroll->json('data.voluntary_insured_number');

        $enrollmentModel = CnssVoluntaryEnrollment::where('voluntary_insured_number', $voluntary)->firstOrFail();
        $formData = $enrollmentModel->form_data;
        unset($formData['other_information']['bank_account_number']);
        $enrollmentModel->form_data = $formData;
        $enrollmentModel->save();

        $r = $this->postJson('/api/v1/cnss/contributions', [
            'business_unit_id' => $this->businessUnit->id,
            'voluntary_no' => $voluntary,
            'amount' => 100,
            'reference_period' => '2026-08',
            'payment_frequency' => 'monthly',
            'mode' => CnssContribution::MODE_LEDGER,
            'pin' => '123456',
            'device_serial' => $terminal->serial_number,
        ]);
        $r->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString(
            'account_number is required for ledger mode',
            (string) $r->json('message')
        );
    }

    public function test_two_business_units_each_receive_same_first_voluntary_number(): void
    {
        $bu2 = BusinessUnit::create([
            'name' => 'Second BU CNSS',
            'slug' => 'test-bu-cnss-2-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $r1 = $this->postJson('/api/v1/cnss/voluntary-enrollments', $this->enrollmentPayload());
        $r1->assertStatus(201);
        $v1 = $r1->json('data.voluntary_insured_number');
        $a1 = $r1->json('data.cnss_registration_affiliation_number');

        $payload2 = $this->enrollmentPayload();
        $payload2['business_unit_id'] = $bu2->id;
        $r2 = $this->postJson('/api/v1/cnss/voluntary-enrollments', $payload2);
        $r2->assertStatus(201);
        $v2 = $r2->json('data.voluntary_insured_number');
        $a2 = $r2->json('data.cnss_registration_affiliation_number');

        $this->assertSame($v1, $v2);
        $this->assertSame($a1, $a2);
        $this->assertNotSame($r1->json('data.id'), $r2->json('data.id'));
        $this->assertDatabaseHas('cnss_voluntary_enrollments', [
            'id' => $r1->json('data.id'),
            'business_unit_id' => $this->businessUnit->id,
            'voluntary_insured_number' => $v1,
        ]);
        $this->assertDatabaseHas('cnss_voluntary_enrollments', [
            'id' => $r2->json('data.id'),
            'business_unit_id' => $bu2->id,
            'voluntary_insured_number' => $v2,
        ]);
    }

    public function test_member_search_without_business_unit_id_requires_authentication(): void
    {
        $this->getJson('/api/v1/cnss/members?voluntary_no=VINS00000001')
            ->assertStatus(401);
    }

    public function test_member_search_without_business_unit_id_forbidden_without_permission(): void
    {
        $role = Role::create([
            'name' => 'No CNSS perm',
            'business_unit_id' => $this->businessUnit->id,
        ]);

        $user = User::factory()->create([
            'current_business_unit_id' => $this->businessUnit->id,
        ]);
        $user->businessUnits()->attach($this->businessUnit->id, ['role_id' => $role->id, 'is_active' => true]);

        $token = $user->createToken('api-test')->plainTextToken;

        $this->getJson('/api/v1/cnss/members?voluntary_no=VINS00000001', [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_member_search_without_business_unit_id_ok_with_permission(): void
    {
        $perm = Permission::firstOrCreate(
            ['slug' => 'view-cnss-members'],
            [
                'name' => 'View CNSS Members',
                'description' => 'Test',
                'group' => 'CNSS',
            ]
        );

        $role = Role::create([
            'name' => 'CNSS list API',
            'business_unit_id' => $this->businessUnit->id,
        ]);
        $role->permissions()->sync([$perm->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $this->businessUnit->id,
        ]);
        $user->businessUnits()->attach($this->businessUnit->id, ['role_id' => $role->id, 'is_active' => true]);

        $enroll = $this->postJson('/api/v1/cnss/voluntary-enrollments', $this->enrollmentPayload());
        $enroll->assertStatus(201);
        $voluntary = $enroll->json('data.voluntary_insured_number');

        $token = $user->createToken('api-test')->plainTextToken;

        $this->getJson('/api/v1/cnss/members?voluntary_no='.urlencode($voluntary), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('ambiguous', false)
            ->assertJsonPath('data.0.voluntary_insured_number', $voluntary)
            ->assertJsonPath('data.0.business_unit_id', $this->businessUnit->id);
    }

    public function test_member_search_without_business_unit_id_super_admin_ambiguous_across_units(): void
    {
        $bu2 = BusinessUnit::create([
            'name' => 'Second BU CNSS search',
            'slug' => 'test-bu-cnss-search-2-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $r1 = $this->postJson('/api/v1/cnss/voluntary-enrollments', $this->enrollmentPayload());
        $r1->assertStatus(201);
        $voluntary = $r1->json('data.voluntary_insured_number');

        $payload2 = $this->enrollmentPayload();
        $payload2['business_unit_id'] = $bu2->id;
        $r2 = $this->postJson('/api/v1/cnss/voluntary-enrollments', $payload2);
        $r2->assertStatus(201);
        $this->assertSame($voluntary, $r2->json('data.voluntary_insured_number'));

        $admin = User::factory()->create(['is_super_admin' => true]);
        $token = $admin->createToken('admin-test')->plainTextToken;

        $this->getJson('/api/v1/cnss/members?voluntary_no='.urlencode($voluntary), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('ambiguous', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_member_search_without_business_unit_id_scoped_to_permitted_business_unit(): void
    {
        $bu2 = BusinessUnit::create([
            'name' => 'Other BU CNSS',
            'slug' => 'test-bu-cnss-other-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $r1 = $this->postJson('/api/v1/cnss/voluntary-enrollments', $this->enrollmentPayload());
        $r1->assertStatus(201);
        $voluntary = $r1->json('data.voluntary_insured_number');

        $payload2 = $this->enrollmentPayload();
        $payload2['business_unit_id'] = $bu2->id;
        $r2 = $this->postJson('/api/v1/cnss/voluntary-enrollments', $payload2);
        $r2->assertStatus(201);
        $this->assertSame($voluntary, $r2->json('data.voluntary_insured_number'));

        $perm = Permission::firstOrCreate(
            ['slug' => 'view-cnss-members'],
            [
                'name' => 'View CNSS Members',
                'description' => 'Test',
                'group' => 'CNSS',
            ]
        );

        $role = Role::create([
            'name' => 'CNSS BU1 only',
            'business_unit_id' => $this->businessUnit->id,
        ]);
        $role->permissions()->sync([$perm->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $this->businessUnit->id,
        ]);
        $user->businessUnits()->attach($this->businessUnit->id, ['role_id' => $role->id, 'is_active' => true]);

        $token = $user->createToken('api-test')->plainTextToken;

        $this->getJson('/api/v1/cnss/members?voluntary_no='.urlencode($voluntary), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('ambiguous', false)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.business_unit_id', $this->businessUnit->id);
    }

    public function test_enrollment_forbidden_when_cnss_module_disabled(): void
    {
        $this->businessUnit->update(['cnss_enabled' => false]);

        $this->postJson('/api/v1/cnss/voluntary-enrollments', $this->enrollmentPayload())
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('common.cnss_module_disabled'));
    }
}
