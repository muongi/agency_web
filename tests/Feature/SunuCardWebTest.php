<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Card;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\Role;
use App\Models\SunuPolicy;
use App\Models\SunuProposal;
use App\Models\User;
use App\Services\SunuSubscriptionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Web BO /cards: SUNU cards are issued to a registered customer (policies + pending proposals), not an account.
 */
class SunuCardWebTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected InsuranceProduct $product;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bu = BusinessUnit::create([
            'name' => 'SUNU Test',
            'slug' => 'sunu-test-'.uniqid(),
            'is_active' => true,
        ]);
        $this->product = InsuranceProduct::create([
            'business_unit_id' => $this->bu->id,
            'code' => 'MICRO_RETRAITE',
            'name_fr' => 'Micro Retraite',
            'name_en' => 'Micro Retirement Savings',
            'category' => 'micro-insurance',
            'default_premium_frequency' => 'monthly',
            'default_premium_amount' => 2500,
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'SUNU admin', 'business_unit_id' => $this->bu->id]);
        $this->user = User::factory()->create([
            'current_business_unit_id' => $this->bu->id,
            'is_super_admin' => true,
        ]);
        $this->user->businessUnits()->attach($this->bu->id, ['role_id' => $role->id, 'is_active' => true]);
    }

    protected function client(string $last = 'OUEDRAOGO', string $first = 'Awa'): Client
    {
        return Client::create([
            'business_unit_id' => $this->bu->id,
            'last_name' => $last,
            'first_name' => $first,
            'phone_number' => '+22670000001',
        ]);
    }

    protected function proposal(Client $client, string $number, string $status = SunuProposal::STATUS_TRANSMITTED): SunuProposal
    {
        return SunuProposal::create([
            'business_unit_id' => $this->bu->id,
            'client_id' => $client->id,
            'insurance_product_id' => $this->product->id,
            'proposal_number' => $number,
            'status' => $status,
            'last_name' => $client->last_name,
            'first_names' => $client->first_name,
            'phone' => $client->phone_number,
        ]);
    }

    protected function policy(Client $client, string $number, string $status = SunuPolicy::STATUS_ACTIVE): SunuPolicy
    {
        return SunuPolicy::create([
            'business_unit_id' => $this->bu->id,
            'client_id' => $client->id,
            'insurance_product_id' => $this->product->id,
            'policy_number' => $number,
            'status' => $status,
            'premium_amount' => 3000,
            'premium_frequency' => 'monthly',
            'source' => 'import',
        ]);
    }

    public function test_create_lists_registered_clients_only(): void
    {
        $registered = $this->client();
        $this->policy($registered, 'POL-1');
        $this->client('SANS', 'Police');

        $this->actingAs($this->user)
            ->get(route('cards.create'))
            ->assertOk()
            ->assertSee('OUEDRAOGO Awa')
            ->assertSee('POL-1')
            ->assertDontSee('SANS Police')
            ->assertDontSee('name="account_id"', false);
    }

    public function test_store_links_card_to_all_client_subscriptions(): void
    {
        $client = $this->client();
        $policy = $this->policy($client, 'POL-1');
        $proposal = $this->proposal($client, 'P-2');

        $response = $this->actingAs($this->user)->post(route('cards.store'), [
            'client_id' => $client->id,
            'card_uid' => 'a1b2c3d4',
        ]);

        $card = Card::where('card_serial', 'A1B2C3D4')->firstOrFail();
        $response->assertRedirect(route('cards.show', $card->id))->assertSessionHas('success');

        $this->assertSame('active', $card->status);
        $this->assertNull($card->account_id);
        $this->assertTrue($policy->cards()->whereKey($card->id)->exists());
        $this->assertSame($card->id, $proposal->fresh()->card_id);

        // What the F20 premium screen gets when the card is tapped
        $this->getJson('/api/v1/sunu/subscriptions?'.http_build_query(['business_unit_id' => $this->bu->id, 'card_uid' => 'A1B2C3D4']))
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'OUEDRAOGO Awa')
            ->assertJsonCount(2, 'data.subscriptions');
    }

    public function test_store_reuses_existing_uid_instead_of_duplicating(): void
    {
        $client = $this->client();
        $this->policy($client, 'POL-1');

        foreach ([1, 2] as $ignored) {
            $this->actingAs($this->user)->post(route('cards.store'), [
                'client_id' => $client->id,
                'card_uid' => 'CAFE0001',
            ])->assertSessionHas('success');
        }

        $this->assertSame(1, Card::where('card_serial', 'CAFE0001')->count());
    }

    public function test_store_refuses_card_of_another_customer(): void
    {
        $awa = $this->client();
        $this->policy($awa, 'POL-1');
        $other = $this->client('KABORE', 'Issa');
        $otherPolicy = $this->policy($other, 'POL-2');

        $this->actingAs($this->user)->post(route('cards.store'), ['client_id' => $awa->id, 'card_uid' => 'BEEF0001'])
            ->assertSessionHas('success');

        $this->actingAs($this->user)->post(route('cards.store'), ['client_id' => $other->id, 'card_uid' => 'BEEF0001'])
            ->assertSessionHas('error');

        $this->assertFalse($otherPolicy->cards()->exists());
    }

    public function test_store_refuses_client_without_subscription(): void
    {
        $client = $this->client();
        $this->policy($client, 'POL-OLD', SunuPolicy::STATUS_TERMINATED);

        $this->actingAs($this->user)->post(route('cards.store'), ['client_id' => $client->id, 'card_uid' => 'DEAD0001'])
            ->assertSessionHas('error');

        $this->assertFalse(Card::where('card_serial', 'DEAD0001')->exists());
    }

    public function test_store_requires_uid_and_client(): void
    {
        $this->actingAs($this->user)->post(route('cards.store'), [])
            ->assertSessionHasErrors(['client_id', 'card_uid']);
    }

    public function test_index_and_show_render_cards_without_account(): void
    {
        $client = $this->client();
        $this->policy($client, 'POL-1');
        $this->actingAs($this->user)->post(route('cards.store'), ['client_id' => $client->id, 'card_uid' => 'FACE0001']);
        $card = Card::where('card_serial', 'FACE0001')->firstOrFail();

        // A card linked nowhere (e.g. created by an unknown UID) must not break the list either
        Card::create([
            'business_unit_id' => $this->bu->id,
            'card_number' => 'ORPHAN-1',
            'card_serial' => 'ORPHAN01',
            'prefix' => 'SUNU',
            'status' => 'unassigned',
            'expiration_date' => now()->addYears(3),
        ]);

        $this->actingAs($this->user)->get(route('cards.index'))
            ->assertOk()
            ->assertSee('FACE0001')
            ->assertSee('OUEDRAOGO Awa')
            ->assertSee('POL-1')
            ->assertSee('ORPHAN01');

        $this->actingAs($this->user)->get(route('cards.index', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('FACE0001')
            ->assertDontSee('ORPHAN01');

        $this->actingAs($this->user)->get(route('cards.show', $card->id))
            ->assertOk()
            ->assertSee('OUEDRAOGO Awa')
            ->assertSee('POL-1');
    }

    public function test_normalize_uid_formats(): void
    {
        $svc = app(SunuSubscriptionService::class);

        $this->assertSame('04A1B2C3', $svc->normalizeUid('04:a1:b2:c3'));
        $this->assertSame('04A1B2C3D4E580', $svc->normalizeUid('04 A1 B2 C3 D4 E5 80'));
        $this->assertSame('C3B2A104', $svc->normalizeUid('04A1B2C3', 'hex_reversed'));
        $this->assertSame('A1B2C3D4', $svc->normalizeUid('2712847316', 'decimal'));
        $this->assertSame('000000FF', $svc->normalizeUid('255', 'decimal'));
        $this->assertNull($svc->normalizeUid('ABC'));          // too short
        $this->assertNull($svc->normalizeUid('04A1B2C3D'));    // odd length
        $this->assertNull($svc->normalizeUid('XYZ12345'));     // not hex
        $this->assertNull($svc->normalizeUid('12AB', 'decimal'));
    }

    protected function importCsv(string $csv, string $format = 'hex')
    {
        return $this->actingAs($this->user)->post(route('cards.import-uids'), [
            'file' => UploadedFile::fake()->createWithContent('cards.csv', $csv),
            'uid_format' => $format,
        ]);
    }

    public function test_import_adds_normalised_stock_and_reports_rows(): void
    {
        $otherBu = BusinessUnit::create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'is_active' => true]);
        Card::create([
            'business_unit_id' => $otherBu->id, 'card_number' => 'OTHER-1', 'card_serial' => 'EEEEEEEE',
            'prefix' => 'X', 'status' => 'unassigned', 'expiration_date' => now()->addYear(),
        ]);

        $csv = "uid,card_number,batch\n04:a1:b2:c3,,LOT-1\n04A1B2C3,,LOT-1\nnot-a-uid,,\neeeeeeee,,\n\n1A2B3C4D5E6F70,MY-NUM-1,\n";
        $this->importCsv($csv)
            ->assertRedirect(route('cards.index', ['status' => 'unassigned']))
            ->assertSessionHas('success');

        $this->assertSame(1, Card::where('card_serial', '04A1B2C3')->count());
        $this->assertSame('unassigned', Card::where('card_serial', '04A1B2C3')->value('status'));
        $this->assertStringContainsString('LOT-1', Card::where('card_serial', '04A1B2C3')->value('description'));
        $this->assertSame('MY-NUM-1', Card::where('card_serial', '1A2B3C4D5E6F70')->value('card_number'));
        $this->assertSame($otherBu->id, Card::where('card_serial', 'EEEEEEEE')->value('business_unit_id'));
        $this->assertCount(2, session('import_errors')); // bad UID + other BU

        // Re-import: everything already known, nothing duplicated
        $this->importCsv($csv);
        $this->assertSame(1, Card::where('card_serial', '04A1B2C3')->count());
    }

    public function test_import_decimal_and_reversed_formats(): void
    {
        $this->importCsv("uid\n2712847316\n", 'decimal')->assertSessionHas('success');
        $this->assertTrue(Card::where('card_serial', 'A1B2C3D4')->exists());

        $this->importCsv("uid\n11223344\n", 'hex_reversed')->assertSessionHas('success');
        $this->assertTrue(Card::where('card_serial', '44332211')->exists());
    }

    public function test_sample_download_and_stock_in_create_form(): void
    {
        $this->actingAs($this->user)->get(route('cards.import-uids.sample'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('uid,card_number,batch');

        $this->importCsv("uid\nAAAA0001\nAAAA0002\n");
        $client = $this->client();
        $this->policy($client, 'POL-1');
        $this->actingAs($this->user)->post(route('cards.store'), ['client_id' => $client->id, 'card_uid' => 'aa:aa:00:01'])
            ->assertSessionHas('success');

        // Linked card leaves the stock list; the free one stays and ?uid pre-fills
        $this->actingAs($this->user)->get(route('cards.create', ['uid' => 'aaaa0002']))
            ->assertOk()
            ->assertSee('<option value="AAAA0002">', false)
            ->assertDontSee('<option value="AAAA0001">', false)
            ->assertSee('value="AAAA0002"', false);

        $this->assertSame(1, Card::where('card_serial', 'AAAA0001')->count());
        $this->assertSame('active', Card::where('card_serial', 'AAAA0001')->value('status'));
    }

    public function test_store_rejects_malformed_uid(): void
    {
        $client = $this->client();
        $this->policy($client, 'POL-1');

        $this->actingAs($this->user)->post(route('cards.store'), ['client_id' => $client->id, 'card_uid' => 'ABC'])
            ->assertSessionHasErrors('card_uid');
        $this->actingAs($this->user)->post(route('cards.store'), ['client_id' => $client->id, 'card_uid' => 'ZZZZZZZZ'])
            ->assertSessionHasErrors('card_uid');
        $this->assertSame(0, Card::count());
    }
}
