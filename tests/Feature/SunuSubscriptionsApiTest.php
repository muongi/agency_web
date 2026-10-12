<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Card;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SunuPolicy;
use App\Models\SunuProposal;
use App\Models\Terminal;
use App\Models\User;
use App\Services\SunuSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SunuSubscriptionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected InsuranceProduct $retraite;
    protected InsuranceProduct $obseques;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bu = BusinessUnit::create([
            'name' => 'SUNU Test',
            'slug' => 'sunu-test-'.uniqid(),
            'is_active' => true,
        ]);
        $this->retraite = InsuranceProduct::create([
            'business_unit_id' => $this->bu->id,
            'code' => 'MICRO_RETRAITE',
            'name_fr' => 'Micro Retraite',
            'name_en' => 'Micro Retirement Savings',
            'category' => 'micro-insurance',
            'default_premium_frequency' => 'monthly',
            'default_premium_amount' => 2500,
            'policy_cost_amount' => 1000,
            'is_active' => true,
        ]);
        $this->obseques = InsuranceProduct::create([
            'business_unit_id' => $this->bu->id,
            'code' => 'MICRO_OBSEQUES',
            'name_fr' => 'Micro Obsèques',
            'name_en' => 'Micro Funeral',
            'category' => 'micro-insurance',
            'default_premium_frequency' => 'monthly',
            'default_premium_amount' => 1500,
            'is_active' => true,
        ]);
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

    protected function proposal(Client $client, InsuranceProduct $product, string $number, string $status = SunuProposal::STATUS_TRANSMITTED): SunuProposal
    {
        return SunuProposal::create([
            'business_unit_id' => $this->bu->id,
            'client_id' => $client->id,
            'insurance_product_id' => $product->id,
            'proposal_number' => $number,
            'status' => $status,
            'last_name' => $client->last_name,
            'first_names' => $client->first_name,
            'phone' => $client->phone_number,
        ]);
    }

    protected function policy(Client $client, InsuranceProduct $product, string $number, ?SunuProposal $proposal = null, string $status = SunuPolicy::STATUS_ACTIVE): SunuPolicy
    {
        return SunuPolicy::create([
            'business_unit_id' => $this->bu->id,
            'client_id' => $client->id,
            'insurance_product_id' => $product->id,
            'proposal_id' => $proposal?->id,
            'policy_number' => $number,
            'status' => $status,
            'premium_amount' => 3000,
            'premium_frequency' => 'monthly',
            'source' => 'local_proposal',
        ]);
    }

    protected function lookup(array $query)
    {
        return $this->getJson('/api/v1/sunu/subscriptions?'.http_build_query(array_merge(['business_unit_id' => $this->bu->id], $query)));
    }

    public function test_lookup_by_policy_number_returns_policies_and_pending_proposals_of_customer(): void
    {
        $client = $this->client();
        $accepted = $this->proposal($client, $this->retraite, 'P-001', SunuProposal::STATUS_ACCEPTED);
        $this->policy($client, $this->retraite, 'POL-P-001', $accepted);
        $this->proposal($client, $this->obseques, 'P-002');
        $this->proposal($client, $this->obseques, 'P-003', SunuProposal::STATUS_REJECTED);
        $this->policy($client, $this->obseques, 'POL-OLD', null, SunuPolicy::STATUS_TERMINATED);

        $response = $this->lookup(['number' => 'POL-P-001']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customer.name', 'OUEDRAOGO Awa')
            ->assertJsonPath('data.card', null)
            ->assertJsonCount(2, 'data.subscriptions')
            ->assertJsonPath('data.subscriptions.0.type', 'policy')
            ->assertJsonPath('data.subscriptions.0.number', 'POL-P-001')
            ->assertJsonPath('data.subscriptions.0.premium_amount', 3000)
            ->assertJsonPath('data.subscriptions.0.suggested_kind', 'recurring_premium')
            ->assertJsonPath('data.subscriptions.1.type', 'proposal')
            ->assertJsonPath('data.subscriptions.1.number', 'P-002')
            ->assertJsonPath('data.subscriptions.1.policy_id', null)
            ->assertJsonPath('data.subscriptions.1.premium_amount', 1500)
            ->assertJsonPath('data.subscriptions.1.suggested_kind', 'first_premium');
    }

    public function test_lookup_by_proposal_number_finds_pending_proposal(): void
    {
        $this->proposal($this->client(), $this->retraite, 'P-100');

        $this->lookup(['number' => 'P-100'])
            ->assertOk()
            ->assertJsonCount(1, 'data.subscriptions')
            ->assertJsonPath('data.subscriptions.0.status', SunuProposal::STATUS_TRANSMITTED);
    }

    public function test_lookup_by_card_uses_linked_subscriptions(): void
    {
        $client = $this->client();
        $proposal = $this->proposal($client, $this->retraite, 'P-200');
        $other = $this->proposal($this->client('KABORE', 'Issa'), $this->obseques, 'P-201');

        $card = app(SunuSubscriptionService::class)->linkCard($this->bu->id, '04a1b2c3d4', $proposal);

        $this->assertSame('04A1B2C3D4', $card->card_serial);
        $this->assertSame('unassigned', $card->status);

        $this->lookup(['card_uid' => '04A1B2C3D4'])
            ->assertOk()
            ->assertJsonPath('data.card.uid', '04A1B2C3D4')
            ->assertJsonCount(1, 'data.subscriptions')
            ->assertJsonPath('data.subscriptions.0.proposal_id', $proposal->id);

        $this->assertNotContains($other->id, collect($this->lookup(['card_uid' => '04A1B2C3D4'])->json('data.subscriptions'))->pluck('proposal_id'));
    }

    public function test_card_link_follows_proposal_to_policy_on_first_premium(): void
    {
        $proposal = $this->proposal($this->client(), $this->retraite, 'P-300');
        $card = app(SunuSubscriptionService::class)->linkCard($this->bu->id, 'AABBCCDD', $proposal);

        $proposal->refresh();
        $policy = $this->policy($proposal->client, $this->retraite, 'POL-P-300', $proposal);
        app(SunuSubscriptionService::class)->carryCardToPolicy($proposal, $policy);

        $this->assertTrue($policy->cards()->whereKey($card->id)->exists());

        $this->lookup(['card_uid' => 'AABBCCDD'])
            ->assertOk()
            ->assertJsonCount(1, 'data.subscriptions')
            ->assertJsonPath('data.subscriptions.0.type', 'policy')
            ->assertJsonPath('data.subscriptions.0.number', 'POL-P-300');
    }

    public function test_card_cannot_be_linked_to_a_different_customer(): void
    {
        $service = app(SunuSubscriptionService::class);
        $service->linkCard($this->bu->id, 'CAFE0001', $this->proposal($this->client(), $this->retraite, 'P-400'));

        $this->expectException(\InvalidArgumentException::class);
        $service->linkCard($this->bu->id, 'CAFE0001', $this->proposal($this->client('KABORE', 'Issa'), $this->obseques, 'P-401'));
    }

    public function test_same_person_with_duplicate_client_rows_can_share_card(): void
    {
        $service = app(SunuSubscriptionService::class);
        $service->linkCard($this->bu->id, 'CAFE0002', $this->proposal($this->client(), $this->retraite, 'P-500'));
        $service->linkCard($this->bu->id, 'CAFE0002', $this->proposal($this->client('Ouedraogo', 'AWA'), $this->obseques, 'P-501'));

        $this->lookup(['card_uid' => 'CAFE0002'])
            ->assertOk()
            ->assertJsonCount(2, 'data.subscriptions');
    }

    public function test_not_found_and_validation(): void
    {
        $this->lookup(['card_uid' => 'DEADBEEF'])->assertNotFound()->assertJsonPath('message', 'Card not found');
        $this->lookup(['number' => 'NOPE'])->assertNotFound()->assertJsonPath('message', 'No subscription found');
        $this->lookup([])->assertStatus(422);
    }

    public function test_web_link_card_on_proposal_and_accept_carries_card(): void
    {
        $role = Role::create(['name' => 'SUNU admin', 'business_unit_id' => $this->bu->id]);
        $user = User::factory()->create(['current_business_unit_id' => $this->bu->id]);
        $user->businessUnits()->attach($this->bu->id, ['role_id' => $role->id, 'is_active' => true]);
        foreach (['view-sunu-proposals', 'link-sunu-card', 'accept-sunu-proposal', 'view-sunu-policies'] as $slug) {
            $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'SUNU'])->id);
        }

        $proposal = $this->proposal($this->client(), $this->retraite, 'P-600');

        $this->actingAs($user)
            ->post(route('business.sunu.proposals.link-card', $proposal->id), ['card_uid' => '11223344'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get(route('business.sunu.proposals.show', $proposal->id))
            ->assertOk()
            ->assertSee('11223344');

        $this->actingAs($user)
            ->post(route('business.sunu.proposals.accept', $proposal->id), ['insured_number' => 'ASS-600', 'policy_number' => 'POL-600'])
            ->assertRedirect();

        $policy = SunuPolicy::where('proposal_id', $proposal->id)->firstOrFail();
        $card = Card::where('card_serial', '11223344')->firstOrFail();
        $this->assertTrue($policy->cards()->whereKey($card->id)->exists());

        $this->actingAs($user)
            ->get(route('business.sunu.policies.index', ['q' => '11223344']))
            ->assertOk()
            ->assertSee('POL-600');
    }

    protected ?Agent $collector = null;

    protected function registerTerminal(array $rights = ['can_write_cards' => true, 'can_rewrite_cards' => true]): string
    {
        $uniq = str_replace('.', '', uniqid('', true));
        $agent = Agent::create([
            'business_unit_id' => $this->bu->id,
            'first_name' => 'Collector',
            'second_name' => 'One',
            'email' => 'collector-'.$uniq.'@test.local',
            'username' => 'col'.$uniq,
            'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-COL-'.$uniq,
            'password' => Hash::make('123456'),
        ] + $rights);
        $this->collector = $agent;
        $serial = 'f20'.substr($uniq, 0, 13);
        $terminal = Terminal::create([
            'business_unit_id' => $this->bu->id,
            'serial_number' => $serial,
            'simcard_number' => '000',
            'device_alias' => 'F20',
            'model_make' => 'Telpo',
        ]);
        $terminal->agents()->attach($agent->id, ['assigned_at' => now()]);

        return $serial;
    }

    protected function writeCard(array $overrides = [])
    {
        return $this->postJson('/api/v1/sunu/cards/write', array_merge([
            'business_unit_id' => $this->bu->id,
            'chip_written' => true,
            'card_type' => 'M1',
        ], $overrides));
    }

    public function test_write_card_links_all_customer_subscriptions_and_marks_written(): void
    {
        $serial = $this->registerTerminal();
        $client = $this->client();
        $accepted = $this->proposal($client, $this->retraite, 'P-700', SunuProposal::STATUS_ACCEPTED);
        $policy = $this->policy($client, $this->retraite, 'POL-P-700', $accepted);
        $pending = $this->proposal($client, $this->obseques, 'P-701');

        $this->writeCard(['device_serial' => $serial, 'card_uid' => 'a1b2c3d4', 'number' => 'POL-P-700'])
            ->assertOk()
            ->assertJsonPath('data.card.uid', 'A1B2C3D4')
            ->assertJsonPath('data.card.status', 'active')
            ->assertJsonPath('data.customer.last_name', 'OUEDRAOGO')
            ->assertJsonPath('data.customer.first_name', 'Awa')
            ->assertJsonCount(2, 'data.subscriptions');

        $card = Card::where('card_serial', 'A1B2C3D4')->firstOrFail();
        $this->assertNotNull($card->written_at);
        $this->assertSame('agent:'.$this->collector->id.' / '.$serial, $card->written_by);
        $this->assertTrue($policy->cards()->whereKey($card->id)->exists());
        $this->assertSame($card->id, $pending->fresh()->card_id);

        $this->lookup(['card_uid' => 'A1B2C3D4'])->assertOk()->assertJsonCount(2, 'data.subscriptions');
    }

    public function test_write_card_rejects_unknown_number_terminal_and_other_customer(): void
    {
        $serial = $this->registerTerminal();
        $this->proposal($this->client(), $this->retraite, 'P-800');
        $this->proposal($this->client('KABORE', 'Issa'), $this->obseques, 'P-801');

        $this->writeCard(['device_serial' => $serial, 'card_uid' => 'B1B2B3B4', 'number' => 'NOPE'])->assertNotFound();
        $this->writeCard(['device_serial' => 'unknown-f20', 'card_uid' => 'B1B2B3B4', 'number' => 'P-800'])->assertStatus(422);
        $this->writeCard(['device_serial' => $serial, 'card_uid' => 'not-hex!', 'number' => 'P-800'])->assertStatus(422);

        $this->writeCard(['device_serial' => $serial, 'card_uid' => 'B1B2B3B4', 'number' => 'P-800'])->assertOk();
        $this->writeCard(['device_serial' => $serial, 'card_uid' => 'B1B2B3B4', 'number' => 'P-801'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    protected function writeCheck(array $params)
    {
        return $this->getJson('/api/v1/sunu/cards/write-check?'.http_build_query(array_merge([
            'business_unit_id' => $this->bu->id,
        ], $params)));
    }

    public function test_agent_without_card_rights_cannot_write(): void
    {
        $serial = $this->registerTerminal([]);
        $this->proposal($this->client(), $this->retraite, 'P-900');

        $this->writeCheck(['device_serial' => $serial, 'card_uid' => 'C1C2C3C4', 'number' => 'P-900'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'CARD_WRITE_NOT_ALLOWED');
        $this->writeCard(['device_serial' => $serial, 'card_uid' => 'C1C2C3C4', 'number' => 'P-900'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'CARD_WRITE_NOT_ALLOWED');
        $this->assertNull(Card::where('card_serial', 'C1C2C3C4')->first());
    }

    public function test_write_right_allows_blank_card_but_not_rewrite(): void
    {
        $serial = $this->registerTerminal(['can_write_cards' => true]);
        $this->proposal($this->client(), $this->retraite, 'P-910');

        $this->writeCheck(['device_serial' => $serial, 'card_uid' => 'D1D2D3D4', 'number' => 'P-910'])
            ->assertOk()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('is_rewrite', false);
        $this->writeCard(['device_serial' => $serial, 'card_uid' => 'D1D2D3D4', 'number' => 'P-910'])->assertOk();

        // Same card again (e.g. to update the phone) is a rewrite.
        $this->writeCheck(['device_serial' => $serial, 'card_uid' => 'D1D2D3D4', 'number' => 'P-910'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'CARD_REWRITE_NOT_ALLOWED');
        $this->writeCard(['device_serial' => $serial, 'card_uid' => 'D1D2D3D4', 'number' => 'P-910'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'CARD_REWRITE_NOT_ALLOWED');

        $this->collector->update(['can_rewrite_cards' => true]);
        $this->writeCheck(['device_serial' => $serial, 'card_uid' => 'D1D2D3D4', 'number' => 'P-910'])
            ->assertOk()
            ->assertJsonPath('is_rewrite', true);
        $this->writeCard(['device_serial' => $serial, 'card_uid' => 'D1D2D3D4', 'number' => 'P-910'])->assertOk();
    }

    public function test_write_check_refuses_card_of_another_customer_before_chip_write(): void
    {
        $serial = $this->registerTerminal();
        $this->proposal($this->client(), $this->retraite, 'P-920');
        $this->proposal($this->client('KABORE', 'Issa'), $this->obseques, 'P-921');
        $this->writeCard(['device_serial' => $serial, 'card_uid' => 'E1E2E3E4', 'number' => 'P-920'])->assertOk();

        $this->writeCheck(['device_serial' => $serial, 'card_uid' => 'E1E2E3E4', 'number' => 'P-921'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CARD_OTHER_CUSTOMER');
    }

    public function test_agent_id_must_be_assigned_to_the_terminal(): void
    {
        $serial = $this->registerTerminal();
        $assigned = $this->collector;
        $this->registerTerminal();
        $other = $this->collector;
        $this->proposal($this->client(), $this->retraite, 'P-930');

        $this->writeCard(['device_serial' => $serial, 'agent_id' => $other->id, 'card_uid' => 'F1F2F3F4', 'number' => 'P-930'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'AGENT_NOT_ASSIGNED_TO_TERMINAL');
        $this->writeCard(['device_serial' => $serial, 'agent_id' => $assigned->id, 'card_uid' => 'F1F2F3F4', 'number' => 'P-930'])
            ->assertOk();
    }

    public function test_card_rights_are_granted_only_with_permission(): void
    {
        $this->registerTerminal([]);
        $agent = $this->collector;
        $role = Role::create(['name' => 'Cashier', 'business_unit_id' => $this->bu->id]);
        $user = User::factory()->create(['current_business_unit_id' => $this->bu->id]);
        $user->businessUnits()->attach($this->bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('agents.show', ['agent' => $agent->id, 'tab' => 'terminals']))
            ->assertOk()
            ->assertSee(__('common.card_right_rewrite'))
            ->assertDontSee(route('agents.card-rights', $agent->id));
        $this->actingAs($user)
            ->post(route('agents.card-rights', $agent->id), ['can_write_cards' => 1])
            ->assertForbidden();
        $this->assertFalse($agent->fresh()->can_write_cards);

        $permission = Permission::updateOrCreate(
            ['slug' => 'manage-agent-card-rights'],
            ['name' => 'Manage Agent Card Rights', 'group' => 'Agents']
        );
        $role->permissions()->attach($permission->id);

        $this->actingAs($user)
            ->get(route('agents.show', ['agent' => $agent->id, 'tab' => 'terminals']))
            ->assertOk()
            ->assertSee(route('agents.card-rights', $agent->id));

        $this->actingAs($user)
            ->post(route('agents.card-rights', $agent->id), ['can_rewrite_cards' => 1])
            ->assertRedirect()
            ->assertSessionHas('success');
        $agent->refresh();
        $this->assertTrue($agent->can_rewrite_cards);
        $this->assertTrue($agent->can_write_cards, 'rewrite implies write');

        $this->actingAs($user)->post(route('agents.card-rights', $agent->id), [])->assertRedirect();
        $this->assertFalse($agent->fresh()->can_write_cards);
    }

    public function test_proposal_with_latin1_bytes_from_old_tpe_is_repaired(): void
    {
        $serial = $this->registerTerminal();
        // What TPE <= 1.2.4 sent for "Kaboré" / "Hélène": DataOutputStream.writeBytes() -> one Latin-1 byte per char
        $latin1 = fn (string $utf8) => mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');

        $response = $this->post('/api/v1/sunu/proposals', [
            'business_unit_id' => $this->bu->id,
            'device_serial' => $serial,
            'insurance_product_id' => $this->retraite->id,
            'proposal_number' => 'P-UTF8-1',
            'last_name' => $latin1('KABORÉ'),
            'first_names' => $latin1('Hélène'),
            'form_data' => ['identification' => $latin1('{"last_name":"KABORÉ","first_names":"Hélène","address":"Secteur 15, Ouaga"}')],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $proposal = SunuProposal::where('proposal_number', 'P-UTF8-1')->firstOrFail();
        $this->assertSame('KABORÉ', $proposal->last_name);
        $this->assertSame('Hélène', $proposal->first_names);
        $this->assertStringContainsString('Hélène', json_encode($proposal->form_data, JSON_UNESCAPED_UNICODE));
    }
}
