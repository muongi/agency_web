<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Models\SunuProposal;
use App\Models\Terminal;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SUNU rule (2026-09-30): one insured person (INSURED number, typed by the office) can hold
 * several products; each product has its own policy number, typed at acceptance.
 */
class SunuMultiProductTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected InsuranceProduct $retraite;
    protected InsuranceProduct $horizon;
    protected Agent $agent;
    protected string $serial;
    protected ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $this->bu = BusinessUnit::create(['name' => 'SUNU Test', 'slug' => 'sunu-multi-'.uniqid(), 'is_active' => true]);
        $this->retraite = $this->product('MICRO_RETRAITE', 'Micro Retraite', 2500);
        $this->horizon = $this->product('MIXTE_PLUS_HORIZON', 'Mixte Plus Horizon', 5000);

        $uniq = str_replace('.', '', uniqid('', true));
        $this->agent = Agent::create([
            'business_unit_id' => $this->bu->id, 'first_name' => 'Collector', 'second_name' => 'One',
            'email' => 'col-'.$uniq.'@test.local', 'username' => 'col'.$uniq, 'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-'.$uniq, 'password' => Hash::make('SunuPilot2026!'),
        ]);
        $this->serial = 'f20'.substr($uniq, 0, 13);
        Terminal::create([
            'business_unit_id' => $this->bu->id, 'serial_number' => $this->serial, 'simcard_number' => '000',
            'device_alias' => 'F20', 'model_make' => 'Telpo',
        ])->agents()->attach($this->agent->id, ['assigned_at' => now()]);
    }

    protected function product(string $code, string $name, int $amount): InsuranceProduct
    {
        return InsuranceProduct::create([
            'business_unit_id' => $this->bu->id, 'code' => $code, 'name_fr' => $name, 'name_en' => $name,
            'category' => 'micro-insurance', 'default_premium_frequency' => 'monthly',
            'default_premium_amount' => $amount, 'is_active' => true,
        ]);
    }

    protected function subscribe(array $overrides = [])
    {
        return $this->postJson('/api/v1/sunu/proposals', array_merge([
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial,
            'insurance_product_id' => $this->retraite->id, 'proposal_number' => 'PRO-100',
            'last_name' => 'KABORE', 'first_names' => 'Awa', 'phone' => '+22670000001',
        ], $overrides));
    }

    protected function admin(): User
    {
        if (! $this->admin) {
            $this->admin = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => true]);
            $this->admin->businessUnits()->attach($this->bu->id, ['is_active' => true]);
        }

        return $this->admin;
    }

    protected function accept(SunuProposal $proposal, string $policyNumber, ?string $insured = null)
    {
        return $this->actingAs($this->admin())->post(route('business.sunu.proposals.accept', $proposal->id),
            array_filter(['policy_number' => $policyNumber, 'insured_number' => $insured]));
    }

    public function test_second_product_shares_the_insured_customer_with_its_own_policy_number(): void
    {
        $this->subscribe()->assertStatus(201)->assertJsonPath('data.linked_existing_customer', false);
        $first = SunuProposal::firstOrFail();
        $this->accept($first, 'POL-1', 'ASS-1')->assertSessionHasNoErrors();
        $this->assertSame('ASS-1', Client::first()->insured_number);

        $this->subscribe([
            'insurance_product_id' => $this->horizon->id, 'proposal_number' => 'PRO-101',
            'existing_insured_number' => 'ASS-1', 'last_name' => 'Kaboré', 'first_names' => 'AWA',
        ])->assertStatus(201)
            ->assertJsonPath('data.linked_existing_customer', true)
            ->assertJsonPath('data.identity_mismatch', false)
            ->assertJsonPath('data.insured_number', 'ASS-1')
            ->assertJsonPath('data.policy_number', null);
        $second = SunuProposal::where('proposal_number', 'PRO-101')->firstOrFail();

        $this->assertSame(1, Client::count(), 'no duplicate customer');
        $this->assertSame($first->client_id, $second->client_id);
        $this->assertNull($second->sunulife_policy_number, 'no shared policy number any more');

        // Insured number already on file: only the policy number is typed.
        $this->accept($second, 'POL-2')->assertSessionHasNoErrors();
        $policies = SunuPolicy::orderBy('id')->get();
        $this->assertSame(['POL-1', 'POL-2'], $policies->pluck('policy_number')->all());
        $this->assertSame([$first->client_id], $policies->pluck('client_id')->unique()->values()->all());

        // INSURED number, or any policy of that person, lists both products.
        foreach (['ASS-1', 'POL-1'] as $number) {
            $res = $this->getJson('/api/v1/sunu/subscriptions?business_unit_id='.$this->bu->id.'&number='.$number)->assertOk();
            $this->assertCount(2, $res->json('data.subscriptions'), $number);
            $res->assertJsonPath('data.customer.insured_number', 'ASS-1');
        }
    }

    public function test_existing_number_with_other_name_is_linked_and_flagged(): void
    {
        $this->subscribe()->assertStatus(201);
        $this->subscribe([
            'insurance_product_id' => $this->horizon->id, 'proposal_number' => 'PRO-102',
            'existing_policy_number' => 'PRO-100', 'last_name' => 'OUEDRAOGO', 'first_names' => 'Issa',
        ])->assertStatus(201)->assertJsonPath('data.identity_mismatch', true);

        $second = SunuProposal::where('proposal_number', 'PRO-102')->firstOrFail();
        $this->assertSame(1, Client::count());
        $this->assertSame('OUEDRAOGO', $second->identity_mismatch['typed']['last_name']);
        $this->assertSame('KABORE', $second->identity_mismatch['on_file']['last_name']);
        $this->assertSame('KABORE', Client::first()->last_name, 'file untouched');

        $admin = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => true]);
        $admin->businessUnits()->attach($this->bu->id, ['is_active' => true]);
        $this->actingAs($admin)->get(route('business.sunu.proposals.show', $second->id))->assertOk()
            ->assertSee(__('common.sunu_identity_mismatch'))->assertSee('Micro Retraite');
    }

    public function test_same_product_twice_under_same_number_is_refused(): void
    {
        $this->subscribe()->assertStatus(201);
        $this->subscribe(['proposal_number' => 'PRO-103', 'existing_policy_number' => 'PRO-100'])
            ->assertStatus(422)->assertJsonPath('code', 'PRODUCT_ALREADY_SUBSCRIBED');
    }

    public function test_import_groups_products_by_insured_number(): void
    {
        $csv = "insured_number,policy_number,product_code,last_name,first_name,phone,premium_amount\n"
            ."ASS-9,POL-9A,MICRO_RETRAITE,SAWADOGO,Ali,+22670000009,2500\n"
            ."ASS-9,POL-9B,MIXTE_PLUS_HORIZON,SAWADOGO,Ali,+22670000009,5000\n"
            ."ASS-9,POL-9B,MIXTE_PLUS_HORIZON,SAWADOGO,Ali,+22670000009,6000\n"
            ."ASS-9,POL-9C,MICRO_RETRAITE,TRAORE,Fati,+22670000010,2500\n"
            ."ASS-10,POL-9A,MIXTE_PLUS_HORIZON,TRAORE,Fati,+22670000010,2500\n";

        $this->actingAs($this->admin())->post(route('business.sunu.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ])->assertSessionHas('import_errors', fn ($errors) => count($errors) === 2
            && str_contains($errors[0], 'ASS-9') && str_contains($errors[1], 'POL-9A'));

        $policies = SunuPolicy::orderBy('policy_number')->get();
        $this->assertSame(['POL-9A', 'POL-9B'], $policies->pluck('policy_number')->all(), 'row 3 updates POL-9B, rows 4-5 refused');
        $this->assertSame(1, $policies->pluck('client_id')->unique()->count());
        $this->assertSame('ASS-9', Client::find($policies->first()->client_id)->insured_number);
        $this->assertEquals(6000, $policies->firstWhere('policy_number', 'POL-9B')->premium_amount);
    }

    public function test_premium_by_insured_number_needs_the_product(): void
    {
        $client = Client::create(['business_unit_id' => $this->bu->id, 'last_name' => 'SAWADOGO', 'first_name' => 'Ali', 'insured_number' => 'ASS-77']);
        foreach ([$this->retraite, $this->horizon] as $i => $product) {
            SunuPolicy::create([
                'business_unit_id' => $this->bu->id, 'client_id' => $client->id, 'insurance_product_id' => $product->id,
                'policy_number' => 'POL-77-'.$i, 'status' => SunuPolicy::STATUS_ACTIVE, 'premium_frequency' => 'monthly', 'source' => 'import',
            ]);
        }
        $base = [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'policy_number' => 'ASS-77',
            'amount' => 5000, 'collection_kind' => 'recurring_premium',
        ];

        $this->postJson('/api/v1/sunu/premiums', $base + ['reference' => 'A-'.uniqid()])
            ->assertStatus(422)->assertJsonPath('code', 'AMBIGUOUS_POLICY_NUMBER')->assertJsonCount(2, 'products');

        $this->postJson('/api/v1/sunu/premiums', $base + ['reference' => 'B-'.uniqid(), 'product_code' => 'MIXTE_PLUS_HORIZON'])
            ->assertStatus(201);
        $this->assertSame('POL-77-1', SunuPremiumCollection::first()->policy->policy_number);

        // A policy number is one product: no product needed.
        $this->postJson('/api/v1/sunu/premiums', array_merge($base, ['policy_number' => 'POL-77-0', 'reference' => 'C-'.uniqid()]))
            ->assertStatus(201);
    }

    /** 1st product accepted (POL-1, ASS-1), 2nd product still a proposal of the same insured person. */
    protected function policyPlusPendingProposal(): array
    {
        $this->subscribe()->assertStatus(201);
        $first = SunuProposal::firstOrFail();
        $this->postJson('/api/v1/sunu/premiums', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'proposal_id' => $first->id,
            'amount' => 2500, 'collection_kind' => 'first_premium', 'reference' => 'F-'.uniqid(),
        ])->assertStatus(201);
        $this->accept($first, 'POL-1', 'ASS-1')->assertSessionHasNoErrors();
        $this->subscribe([
            'insurance_product_id' => $this->horizon->id, 'proposal_number' => 'PRO-200', 'existing_insured_number' => 'ASS-1',
        ])->assertStatus(201);

        return [$first, SunuProposal::where('proposal_number', 'PRO-200')->firstOrFail()];
    }

    public function test_number_only_premium_is_not_booked_on_the_wrong_product(): void
    {
        $this->policyPlusPendingProposal();

        $this->postJson('/api/v1/sunu/premiums', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'voluntary_no' => 'ASS-1',
            'amount' => 5000, 'reference' => 'N-'.uniqid(),
        ])->assertStatus(422)->assertJsonPath('code', 'AMBIGUOUS_POLICY_NUMBER')->assertJsonCount(2, 'products');
        $this->assertSame(1, SunuPremiumCollection::count());
    }

    public function test_number_plus_product_pays_the_pending_second_product(): void
    {
        [, $second] = $this->policyPlusPendingProposal();

        $this->postJson('/api/v1/sunu/premiums', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'voluntary_no' => 'ASS-1',
            'insurance_product_id' => $this->horizon->id, 'amount' => 5000, 'reference' => 'P-'.uniqid(),
        ])->assertStatus(201);

        $premium = SunuPremiumCollection::latest('id')->first();
        $this->assertSame($second->id, $premium->proposal_id);
        $this->assertSame('first_premium', $premium->collection_kind);
        $this->assertNull($premium->policy_id, 'no policy before the office accepts');

        $this->accept($second->fresh(), 'POL-2')->assertSessionHasNoErrors();
        $this->assertSame('POL-2', $premium->fresh()->policy->policy_number, 'premium moved onto the typed policy');
    }

    public function test_premiums_before_acceptance_stay_on_the_proposal_then_move_to_the_policy(): void
    {
        $this->subscribe()->assertStatus(201);
        $proposal = SunuProposal::firstOrFail();
        $this->postJson('/api/v1/sunu/premiums', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'voluntary_no' => 'PRO-100',
            'amount' => 2500, 'collection_kind' => 'first_premium', 'reference' => 'S-'.uniqid(),
        ])->assertStatus(201);

        // Lookup now suggests a recurring premium, and a number-only payment is not relabelled first_premium.
        $this->getJson('/api/v1/sunu/subscriptions?business_unit_id='.$this->bu->id.'&number=PRO-100')
            ->assertOk()->assertJsonPath('data.subscriptions.0.suggested_kind', 'recurring_premium');
        $this->postJson('/api/v1/sunu/premiums', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'voluntary_no' => 'PRO-100',
            'amount' => 2500, 'reference' => 'S2-'.uniqid(),
        ])->assertStatus(201)->assertJsonPath('data.collection_kind', 'recurring_premium');

        $this->assertSame(0, SunuPolicy::count());
        $this->assertSame(2, SunuPremiumCollection::where('proposal_id', $proposal->id)->whereNull('policy_id')->count());

        $this->accept($proposal, 'POL-100', 'ASS-100')->assertSessionHasNoErrors();
        $this->assertSame(2, SunuPremiumCollection::where('policy_id', SunuPolicy::firstOrFail()->id)->count());
    }

    public function test_existing_customer_new_product_reuses_the_cnib(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $img = fn ($n) => UploadedFile::fake()->image($n.'.jpg', 40, 40);
        $this->post('/api/v1/sunu/proposals', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial,
            'insurance_product_id' => $this->retraite->id, 'proposal_number' => 'PRO-300',
            'last_name' => 'KABORE', 'first_names' => 'Awa',
            'proposal_image' => $img('form1'), 'id_document_front' => $img('front'), 'id_document_back' => $img('back'),
        ], ['Accept' => 'application/json'])->assertStatus(201);
        $first = SunuProposal::firstOrFail();

        $this->getJson('/api/v1/sunu/subscriptions?business_unit_id='.$this->bu->id.'&number=PRO-300')
            ->assertOk()->assertJsonPath('data.customer.has_id_documents', true);

        // New product: only the new proposal form photo.
        $this->post('/api/v1/sunu/proposals', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial,
            'insurance_product_id' => $this->horizon->id, 'proposal_number' => 'PRO-301', 'existing_policy_number' => 'PRO-300',
            'last_name' => 'KABORE', 'first_names' => 'Awa', 'proposal_image' => $img('form2'),
        ], ['Accept' => 'application/json'])->assertStatus(201)->assertJsonPath('data.id_documents_reused', true);
        $second = SunuProposal::where('proposal_number', 'PRO-301')->firstOrFail();

        $this->assertSame($first->id_document_front_path, $second->id_document_front_path);
        $this->assertSame($first->id_document_back_path, $second->id_document_back_path);
        $this->assertSame($first->id, $second->id_documents_source_proposal_id);
        $this->assertNotSame($first->proposal_image_path, $second->proposal_image_path, 'own subscription form');

        // A renewed CNIB sent with a 3rd product is kept as its own.
        $this->post('/api/v1/sunu/proposals', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial,
            'insurance_product_id' => $this->product('EPARGNE', 'Epargne', 1000)->id, 'proposal_number' => 'PRO-302',
            'existing_policy_number' => 'PRO-300', 'last_name' => 'KABORE', 'first_names' => 'Awa',
            'proposal_image' => $img('form3'), 'id_document_front' => $img('front2'), 'id_document_back' => $img('back2'),
        ], ['Accept' => 'application/json'])->assertStatus(201)->assertJsonPath('data.id_documents_reused', false);
        $this->assertNotSame($first->id_document_front_path, SunuProposal::where('proposal_number', 'PRO-302')->value('id_document_front_path'));

        $admin = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => true]);
        $admin->businessUnits()->attach($this->bu->id, ['is_active' => true]);
        $this->actingAs($admin)->get(route('business.sunu.proposals.show', $second->id))->assertOk()->assertSee('PRO-300');
    }

    public function test_imported_customer_has_no_cnib_on_file(): void
    {
        $client = Client::create(['business_unit_id' => $this->bu->id, 'last_name' => 'SAWADOGO', 'first_name' => 'Ali']);
        SunuPolicy::create([
            'business_unit_id' => $this->bu->id, 'client_id' => $client->id, 'insurance_product_id' => $this->retraite->id,
            'policy_number' => 'POL-IMP', 'status' => SunuPolicy::STATUS_ACTIVE, 'premium_frequency' => 'monthly', 'source' => 'sunulife',
        ]);
        $this->getJson('/api/v1/sunu/subscriptions?business_unit_id='.$this->bu->id.'&number=POL-IMP')
            ->assertOk()->assertJsonPath('data.customer.has_id_documents', false);
    }
}
