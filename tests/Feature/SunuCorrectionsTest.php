<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\CollectorBalance;
use App\Models\InsuranceProduct;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SunuCorrectionEvent;
use App\Models\SunuCorrectionRequest;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Models\SunuProposal;
use App\Models\Terminal;
use App\Models\User;
use App\Services\SunuReportService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Collector error reports: TPE request (PIN) → web decision → manual correction, all traced.
 */
class SunuCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected Agent $agent;
    protected Agent $otherAgent;
    protected string $serial;
    protected SunuPolicy $policy;
    protected SunuPolicy $otherPolicy;
    protected InsuranceProduct $product;
    protected Role $role;
    protected User $approver;
    protected User $applier;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sunu.core_banking' => false]);
        $this->seed(PermissionsSeeder::class);

        $this->bu = BusinessUnit::create(['name' => 'SUNU Test', 'slug' => 'sunu-corr-'.uniqid(), 'is_active' => true]);
        $this->product = InsuranceProduct::create([
            'business_unit_id' => $this->bu->id, 'code' => 'MICRO_RETRAITE', 'name_fr' => 'Micro Retraite',
            'name_en' => 'Micro Retirement', 'category' => 'micro-insurance', 'default_premium_frequency' => 'monthly',
            'default_premium_amount' => 2500, 'is_active' => true,
        ]);
        $client = Client::create(['business_unit_id' => $this->bu->id, 'last_name' => 'KABORE', 'first_name' => 'Awa', 'phone_number' => '+22670000001']);
        $other = Client::create(['business_unit_id' => $this->bu->id, 'last_name' => 'SAWADOGO', 'first_name' => 'Ali', 'phone_number' => '+22670000002']);
        $this->policy = $this->makePolicy($client, 'POL-C-1');
        $this->otherPolicy = $this->makePolicy($other, 'POL-C-2');

        $this->agent = $this->makeAgent('One');
        $this->otherAgent = $this->makeAgent('Two');
        $this->agent->setCollectionPin('4821', false);

        $this->serial = 'f20'.substr(str_replace('.', '', uniqid('', true)), 0, 13);
        $terminal = Terminal::create([
            'business_unit_id' => $this->bu->id, 'serial_number' => $this->serial, 'simcard_number' => '000',
            'device_alias' => 'F20', 'model_make' => 'Telpo',
        ]);
        $terminal->agents()->attach($this->agent->id, ['assigned_at' => now()]);

        $this->role = Role::create(['name' => 'Superviseur', 'slug' => 'superviseur', 'business_unit_id' => $this->bu->id]);
        $this->role->permissions()->sync(Permission::whereIn('slug', ['view-sunu-corrections', 'approve-sunu-corrections'])->pluck('id'));
        $this->approver = $this->makeUser($this->role);
        $applyRole = Role::create(['name' => 'Back office', 'slug' => 'back-office', 'business_unit_id' => $this->bu->id]);
        $applyRole->permissions()->sync(Permission::whereIn('slug', ['view-sunu-corrections', 'apply-sunu-corrections'])->pluck('id'));
        $this->applier = $this->makeUser($applyRole);
    }

    protected function makePolicy(Client $client, string $number): SunuPolicy
    {
        return SunuPolicy::create([
            'business_unit_id' => $this->bu->id, 'client_id' => $client->id, 'insurance_product_id' => $this->product->id,
            'policy_number' => $number, 'status' => SunuPolicy::STATUS_ACTIVE, 'premium_amount' => 2500,
            'premium_frequency' => 'monthly', 'source' => 'import',
        ]);
    }

    protected function makeAgent(string $name): Agent
    {
        $uniq = str_replace('.', '', uniqid('', true));

        return Agent::create([
            'business_unit_id' => $this->bu->id, 'first_name' => 'Collector', 'second_name' => $name,
            'email' => 'col-'.$uniq.'@test.local', 'username' => 'col'.$uniq, 'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-'.$uniq, 'password' => Hash::make('SunuPilot2026!'),
        ]);
    }

    protected function makeUser(Role $role): User
    {
        $user = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => false]);
        $user->businessUnits()->attach($this->bu->id, ['role_id' => $role->id, 'is_active' => true]);

        return $user;
    }

    protected function collect(float $amount = 2500): SunuPremiumCollection
    {
        $this->postJson('/api/v1/sunu/premiums', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'agent_id' => $this->agent->id,
            'policy_id' => $this->policy->id, 'amount' => $amount, 'collection_kind' => 'recurring_premium',
            'reference' => 'CORR-'.uniqid(), 'pin' => '4821',
        ])->assertStatus(201);

        return SunuPremiumCollection::latest('id')->first();
    }

    protected function requestFor(SunuPremiumCollection $p, array $overrides = [])
    {
        return $this->postJson('/api/v1/sunu/corrections', array_merge([
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'agent_id' => $this->agent->id,
            'target_type' => 'premium', 'target_reference' => $p->reference, 'request_type' => 'cancel',
            'reason_code' => 'duplicate', 'details' => 'Encaissée deux fois', 'pin' => '4821',
        ], $overrides));
    }

    protected function balance(): float
    {
        return (float) CollectorBalance::where('agent_id', $this->agent->id)->value('uncollected_amount');
    }

    public function test_collector_lists_own_operations_and_sends_a_request(): void
    {
        $p = $this->collect();

        $ops = $this->getJson('/api/v1/sunu/agent/operations?'.http_build_query([
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'agent_id' => $this->agent->id,
        ]))->assertOk()->json('data.operations');
        $this->assertSame($p->reference, $ops[0]['reference']);
        $this->assertSame('KABORE Awa', $ops[0]['customer']);

        $res = $this->requestFor($p, ['proposed_changes' => ['amount' => 2000]])->assertStatus(201);
        $this->assertStringStartsWith('COR-', $res->json('data.reference'));

        $req = SunuCorrectionRequest::first();
        $this->assertSame('pending', $req->status);
        $this->assertSame($this->agent->id, $req->requested_by_agent_id);
        $this->assertSame($this->serial, $req->device_serial);
        $ev = $req->events()->first();
        $this->assertSame('requested', $ev->event);
        $this->assertSame('agent', $ev->actor_type);
        $this->assertSame('Collector One', $ev->actor_name);
        $this->assertEquals(['amount' => '2000'], $ev->after);

        $list = $this->getJson('/api/v1/sunu/corrections?'.http_build_query([
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'agent_id' => $this->agent->id,
        ]))->assertOk()->json('data.requests');
        $this->assertSame('pending', $list[0]['status']);
    }

    public function test_request_needs_the_collection_pin(): void
    {
        $p = $this->collect();
        $this->requestFor($p, ['pin' => ''])->assertStatus(401)->assertJson(['code' => 'PIN_REQUIRED']);
        $this->requestFor($p, ['pin' => '0000'])->assertStatus(401)->assertJson(['code' => 'INVALID_PIN']);
        $this->assertSame(0, SunuCorrectionRequest::count());
    }

    public function test_collector_cannot_report_someone_elses_operation_or_twice(): void
    {
        $p = $this->collect();
        $p->update(['agent_id' => $this->otherAgent->id]);
        $this->requestFor($p)->assertStatus(403)->assertJson(['code' => 'NOT_OWN_OPERATION']);

        $p->update(['agent_id' => $this->agent->id]);
        $first = $this->requestFor($p)->assertStatus(201)->json('data.reference');
        $this->requestFor($p)->assertStatus(409)->assertJson(['code' => 'REQUEST_ALREADY_OPEN', 'request_reference' => $first]);
    }

    public function test_decision_and_correction_need_separate_permissions_and_order(): void
    {
        $p = $this->collect();
        $this->requestFor($p)->assertStatus(201);
        $req = SunuCorrectionRequest::first();

        // Applier cannot decide; correction refused while pending.
        $this->actingAs($this->applier)->post(route('business.sunu.corrections.approve', $req->id))->assertForbidden();
        $this->actingAs($this->applier)->post(route('business.sunu.corrections.apply', [$req->id, 'cancel-premium']), ['note' => 'x'])
            ->assertSessionHas('error');
        $this->assertSame('valid', $p->fresh()->status);

        // Approver cannot apply; reject needs a note.
        $this->actingAs($this->approver)->post(route('business.sunu.corrections.apply', [$req->id, 'cancel-premium']), ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->approver)->post(route('business.sunu.corrections.reject', $req->id), ['note' => ''])->assertSessionHasErrors('note');

        $this->actingAs($this->approver)->post(route('business.sunu.corrections.approve', $req->id), ['note' => 'OK doublon'])->assertRedirect();
        $req->refresh();
        $this->assertSame('approved', $req->status);
        $this->assertSame($this->approver->id, $req->decided_by);
        $this->assertNotNull($req->decided_at);
        $this->assertSame('valid', $p->fresh()->status, 'approval alone changes nothing');

        $this->actingAs($this->applier)->get(route('business.sunu.corrections.show', $req->id))->assertOk()->assertSee('Collector One');
    }

    public function test_cancel_premium_reverses_balance_and_leaves_reports(): void
    {
        $p = $this->collect(2500);
        $this->assertSame(2500.0, $this->balance());
        $this->requestFor($p)->assertStatus(201);
        $req = SunuCorrectionRequest::first();
        $this->actingAs($this->approver)->post(route('business.sunu.corrections.approve', $req->id));

        $this->actingAs($this->applier)->post(route('business.sunu.corrections.apply', [$req->id, 'cancel-premium']), ['note' => 'Doublon confirmé'])
            ->assertSessionHas('success');

        $p->refresh();
        $this->assertSame('cancelled', $p->status);
        $this->assertSame($this->applier->id, $p->cancelled_by);
        $this->assertSame($req->id, $p->correction_request_id);
        $this->assertSame(0.0, $this->balance());

        $req->refresh();
        $this->assertSame('done', $req->status);
        $this->assertSame($this->applier->id, $req->resolved_by);
        $this->assertSame(['requested', 'approved', 'premium_cancelled'], $req->events->pluck('event')->all());
        $last = $req->events->last();
        $this->assertSame('valid', $last->before['status']);
        $this->assertSame('cancelled', $last->after['status']);
        $this->assertSame($this->applier->name, $last->actor_name);

        $report = app(SunuReportService::class)->premiums($this->bu->id, [
            'from' => now()->startOfDay(), 'to' => now()->endOfDay(), 'agent_id' => null, 'terminal_id' => null, 'product_id' => null,
        ]);
        $this->assertSame(0, $report['count']);

        // Already done: a second correction is refused.
        $this->actingAs($this->applier)->post(route('business.sunu.corrections.apply', [$req->id, 'premium-amount']), ['amount' => 100, 'note' => 'x'])
            ->assertSessionHas('error');
    }

    public function test_amount_and_policy_corrections(): void
    {
        $p = $this->collect(2500);
        $this->requestFor($p, ['request_type' => 'modify', 'reason_code' => 'wrong_amount'])->assertStatus(201);
        $req = SunuCorrectionRequest::first();
        $this->actingAs($this->approver)->post(route('business.sunu.corrections.approve', $req->id));
        $this->actingAs($this->applier)->post(route('business.sunu.corrections.apply', [$req->id, 'premium-amount']), ['amount' => 2000, 'note' => 'Reçu 2000'])
            ->assertSessionHas('success');
        $this->assertEquals(2000, $p->fresh()->amount);
        $this->assertSame(2000.0, $this->balance());
        $ev = $req->fresh()->events->last();
        $this->assertEquals(2500, $ev->before['amount']);
        $this->assertEquals(2000, $ev->after['amount']);

        // Policy of another customer only with reason "wrong_customer".
        $p2 = $this->collect(2500);
        $this->requestFor($p2, ['request_type' => 'modify', 'reason_code' => 'wrong_policy'])->assertStatus(201);
        $req2 = SunuCorrectionRequest::latest('id')->first();
        $this->actingAs($this->approver)->post(route('business.sunu.corrections.approve', $req2->id));
        $this->actingAs($this->applier)->post(route('business.sunu.corrections.apply', [$req2->id, 'premium-policy']), ['policy_number' => 'POL-C-2', 'note' => 'x'])
            ->assertSessionHas('error');
        $this->assertSame($this->policy->id, $p2->fresh()->policy_id);
    }

    public function test_proposal_identity_fix_and_cancel_guard(): void
    {
        $proposal = SunuProposal::create([
            'business_unit_id' => $this->bu->id, 'agent_id' => $this->agent->id, 'insurance_product_id' => $this->product->id,
            'proposal_number' => 'P-'.uniqid(), 'last_name' => 'OUEDRAGO', 'first_names' => 'Awa', 'phone' => '+22670000009',
            'status' => 'captured',
        ]);
        $this->postJson('/api/v1/sunu/corrections', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'agent_id' => $this->agent->id,
            'target_type' => 'proposal', 'target_id' => $proposal->id, 'request_type' => 'modify',
            'reason_code' => 'identity_error', 'details' => 'Nom mal écrit', 'pin' => '4821',
            'proposed_changes' => ['last_name' => 'OUEDRAOGO'],
        ])->assertStatus(201);
        $req = SunuCorrectionRequest::first();
        $this->actingAs($this->approver)->post(route('business.sunu.corrections.approve', $req->id));
        $this->actingAs($this->applier)->post(route('business.sunu.corrections.apply', [$req->id, 'proposal-identity']), [
            'last_name' => 'OUEDRAOGO', 'note' => 'Corrigé selon CNIB',
        ])->assertSessionHas('success');
        $this->assertSame('OUEDRAOGO', $proposal->fresh()->last_name);
        $this->assertSame('OUEDRAGO', $req->fresh()->events->last()->before['last_name']);

        // A paid subscription cannot be cancelled before its premiums.
        SunuPremiumCollection::create([
            'business_unit_id' => $this->bu->id, 'proposal_id' => $proposal->id, 'agent_id' => $this->agent->id,
            'amount' => 2500, 'collection_kind' => 'first_premium', 'reference' => 'X-'.uniqid(), 'collected_at' => now(),
        ]);
        $this->postJson('/api/v1/sunu/corrections', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'agent_id' => $this->agent->id,
            'target_type' => 'proposal', 'target_id' => $proposal->id, 'request_type' => 'cancel',
            'reason_code' => 'other', 'details' => 'Client a renoncé', 'pin' => '4821',
        ])->assertStatus(201);
        $req2 = SunuCorrectionRequest::latest('id')->first();
        $this->actingAs($this->approver)->post(route('business.sunu.corrections.approve', $req2->id));
        $this->actingAs($this->applier)->post(route('business.sunu.corrections.apply', [$req2->id, 'cancel-proposal']), ['note' => 'x'])
            ->assertSessionHas('error');
        $this->assertSame('captured', $proposal->fresh()->status);
        $this->assertSame(2, SunuCorrectionEvent::where('correction_request_id', $req2->id)->count());
    }

    public function test_policy_change_on_shared_number_needs_the_product(): void
    {
        $horizon = InsuranceProduct::create([
            'business_unit_id' => $this->bu->id, 'code' => 'MIXTE_PLUS_HORIZON', 'name_fr' => 'Mixte Plus Horizon',
            'category' => 'savings', 'default_premium_frequency' => 'monthly', 'is_active' => true,
        ]);
        $second = SunuPolicy::create([
            'business_unit_id' => $this->bu->id, 'client_id' => $this->policy->client_id, 'insurance_product_id' => $horizon->id,
            'policy_number' => 'POL-C-1', 'status' => SunuPolicy::STATUS_ACTIVE, 'premium_frequency' => 'monthly', 'source' => 'import',
        ]);
        $p = $this->collect();
        $this->requestFor($p, ['request_type' => 'modify', 'reason_code' => 'wrong_policy'])->assertStatus(201);
        $req = SunuCorrectionRequest::first();
        $this->actingAs($this->approver)->post(route('business.sunu.corrections.approve', $req->id));

        $this->actingAs($this->applier)->post(route('business.sunu.corrections.apply', [$req->id, 'premium-policy']), ['policy_number' => 'POL-C-1', 'note' => 'x'])
            ->assertSessionHas('error', __('common.sunu_corr_error_policy_ambiguous'));
        $this->actingAs($this->applier)->post(route('business.sunu.corrections.apply', [$req->id, 'premium-policy']), [
            'policy_number' => 'POL-C-1', 'insurance_product_id' => $horizon->id, 'note' => 'Mauvais produit',
        ])->assertSessionHas('success');
        $this->assertSame($second->id, $p->fresh()->policy_id);
        $this->assertSame('MIXTE_PLUS_HORIZON', $req->fresh()->events->last()->after['product']);
    }

    public function test_pages_render_with_history(): void
    {
        $p = $this->collect();
        $this->requestFor($p)->assertStatus(201);
        $req = SunuCorrectionRequest::first();
        $this->actingAs($this->approver)->post(route('business.sunu.corrections.approve', $req->id));
        $this->actingAs($this->applier)->post(route('business.sunu.corrections.apply', [$req->id, 'cancel-premium']), ['note' => 'Doublon']);

        $admin = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => true]);
        $admin->businessUnits()->attach($this->bu->id, ['role_id' => $this->role->id, 'is_active' => true]);
        $this->actingAs($admin)->get(route('business.sunu.corrections.index'))->assertOk()->assertSee($req->reference);
        $this->actingAs($admin)->get(route('business.sunu.corrections.show', $req->id))->assertOk()
            ->assertSee('Collector One')->assertSee($this->approver->name)->assertSee($this->applier->name);
        $this->actingAs($admin)->get(route('business.sunu.premiums.index'))->assertOk()->assertSee($req->reference);
    }
}
