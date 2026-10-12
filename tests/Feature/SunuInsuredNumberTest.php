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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * INSURED number (typed at acceptance, unique per BU, groups the person's products),
 * POLICY number (typed at acceptance, one per product) and the Payé / Non payé flag.
 */
class SunuInsuredNumberTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected InsuranceProduct $product;
    protected string $serial;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $this->bu = BusinessUnit::create(['name' => 'SUNU Test', 'slug' => 'sunu-ins-'.uniqid(), 'is_active' => true]);
        $this->product = InsuranceProduct::create([
            'business_unit_id' => $this->bu->id, 'code' => 'MICRO_RETRAITE', 'name_fr' => 'Micro Retraite', 'name_en' => 'Micro Retirement',
            'category' => 'micro-insurance', 'default_premium_frequency' => 'monthly', 'default_premium_amount' => 2500, 'is_active' => true,
        ]);
        $uniq = str_replace('.', '', uniqid('', true));
        $agent = Agent::create([
            'business_unit_id' => $this->bu->id, 'first_name' => 'Collector', 'second_name' => 'One',
            'email' => 'ins-'.$uniq.'@test.local', 'username' => 'ins'.$uniq, 'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-'.$uniq, 'password' => Hash::make('SunuPilot2026!'),
        ]);
        $this->serial = 'f20'.substr($uniq, 0, 13);
        Terminal::create([
            'business_unit_id' => $this->bu->id, 'serial_number' => $this->serial, 'simcard_number' => '000',
            'device_alias' => 'F20', 'model_make' => 'Telpo',
        ])->agents()->attach($agent->id, ['assigned_at' => now()]);

        $this->admin = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => true]);
        $this->admin->businessUnits()->attach($this->bu->id, ['is_active' => true]);
    }

    protected function proposal(string $number, string $last = 'KABORE', string $first = 'Awa'): SunuProposal
    {
        $client = Client::create(['business_unit_id' => $this->bu->id, 'last_name' => $last, 'first_name' => $first]);

        return SunuProposal::create([
            'business_unit_id' => $this->bu->id, 'client_id' => $client->id, 'insurance_product_id' => $this->product->id,
            'proposal_number' => $number, 'last_name' => $last, 'first_names' => $first, 'status' => SunuProposal::STATUS_TRANSMITTED,
        ]);
    }

    protected function pay(SunuProposal $proposal, array $overrides = [])
    {
        return $this->postJson('/api/v1/sunu/premiums', array_merge([
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'proposal_id' => $proposal->id,
            'amount' => 2500, 'collection_kind' => 'first_premium', 'reference' => 'PAY-'.uniqid(),
        ], $overrides));
    }

    public function test_accept_requires_insured_and_policy_numbers(): void
    {
        $proposal = $this->proposal('PRO-1');

        $this->actingAs($this->admin)->post(route('business.sunu.proposals.accept', $proposal->id), [])
            ->assertSessionHasErrors(['insured_number', 'policy_number']);
        $this->assertSame(0, SunuPolicy::count());

        $this->actingAs($this->admin)->post(route('business.sunu.proposals.accept', $proposal->id), [
            'insured_number' => 'ASS-1', 'policy_number' => 'POL-1',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('accepted', $proposal->fresh()->status);
        $this->assertSame('ASS-1', $proposal->client->fresh()->insured_number);
        $this->assertSame('POL-1', $proposal->fresh()->policy->policy_number);
    }

    public function test_insured_and_policy_numbers_are_unique(): void
    {
        $first = $this->proposal('PRO-1');
        $this->actingAs($this->admin)->post(route('business.sunu.proposals.accept', $first->id), [
            'insured_number' => 'ASS-1', 'policy_number' => 'POL-1',
        ])->assertSessionHasNoErrors();

        $other = $this->proposal('PRO-2', 'TRAORE', 'Fati');
        $this->actingAs($this->admin)->post(route('business.sunu.proposals.accept', $other->id), [
            'insured_number' => 'ASS-1', 'policy_number' => 'POL-2',
        ])->assertSessionHasErrors('insured_number');
        $this->actingAs($this->admin)->post(route('business.sunu.proposals.accept', $other->id), [
            'insured_number' => 'ASS-2', 'policy_number' => 'POL-1',
        ])->assertSessionHasErrors('policy_number');

        $this->assertNull($other->fresh()->policy);
        $this->assertNull($other->client->fresh()->insured_number);
    }

    public function test_insured_number_can_be_corrected_but_not_taken_from_another_customer(): void
    {
        $a = $this->proposal('PRO-1');
        $a->client->update(['insured_number' => 'ASS-1']);
        $b = $this->proposal('PRO-2', 'TRAORE', 'Fati');
        $b->client->update(['insured_number' => 'ASS-2']);

        $this->actingAs($this->admin)->post(route('business.sunu.proposals.insured-number', $a->id), ['insured_number' => 'ASS-2'])
            ->assertSessionHasErrors('insured_number');
        $this->actingAs($this->admin)->post(route('business.sunu.proposals.insured-number', $a->id), ['insured_number' => 'ASS-10'])
            ->assertSessionHasNoErrors();
        $this->assertSame('ASS-10', $a->client->fresh()->insured_number);
    }

    public function test_search_by_insured_number_on_the_web_lists(): void
    {
        $proposal = $this->proposal('PRO-1');
        $this->actingAs($this->admin)->post(route('business.sunu.proposals.accept', $proposal->id), [
            'insured_number' => 'ASS-XYZ', 'policy_number' => 'POL-1',
        ]);
        $this->proposal('PRO-OTHER', 'TRAORE', 'Fati');

        $this->actingAs($this->admin)->get(route('business.sunu.proposals.index', ['q' => 'ASS-XYZ']))
            ->assertOk()->assertSee('PRO-1')->assertDontSee('PRO-OTHER');
        $this->actingAs($this->admin)->get(route('business.sunu.policies.index', ['q' => 'ASS-XYZ']))
            ->assertOk()->assertSee('POL-1');
    }

    public function test_paid_badge_and_filter(): void
    {
        $unpaid = $this->proposal('PRO-UNPAID');
        $paid = $this->proposal('PRO-PAID', 'TRAORE', 'Fati');
        $cancelled = $this->proposal('PRO-CANC', 'SAWADOGO', 'Ali');
        $viaPolicy = $this->proposal('PRO-VIAPOL', 'OUEDRAOGO', 'Issa');

        $this->pay($paid)->assertCreated();
        $this->pay($cancelled)->assertCreated();
        SunuPremiumCollection::where('proposal_id', $cancelled->id)->update(['status' => SunuPremiumCollection::STATUS_CANCELLED]);
        // Accepted without payment, then paid on the policy (no proposal_id on the premium).
        $this->actingAs($this->admin)->post(route('business.sunu.proposals.accept', $viaPolicy->id), [
            'insured_number' => 'ASS-VP', 'policy_number' => 'POL-VP',
        ]);
        $this->postJson('/api/v1/sunu/premiums', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'policy_id' => $viaPolicy->fresh()->policy->id,
            'amount' => 2500, 'collection_kind' => 'recurring_premium', 'reference' => 'VP-'.uniqid(),
        ])->assertCreated();

        $flags = SunuProposal::withFirstPremiumPaid()->pluck('first_premium_paid', 'proposal_number')->map(fn ($v) => (bool) $v);
        $this->assertFalse($flags['PRO-UNPAID']);
        $this->assertTrue($flags['PRO-PAID']);
        $this->assertFalse($flags['PRO-CANC'], 'cancelled premium does not count');
        $this->assertTrue($flags['PRO-VIAPOL']);
        $this->assertFalse($unpaid->isFirstPremiumPaid());
        $this->assertTrue($paid->isFirstPremiumPaid());

        $this->actingAs($this->admin)->get(route('business.sunu.proposals.index', ['paid' => '0']))
            ->assertOk()->assertSee('PRO-UNPAID')->assertSee('PRO-CANC')->assertDontSee('PRO-PAID')->assertDontSee('PRO-VIAPOL');
        $this->actingAs($this->admin)->get(route('business.sunu.proposals.index', ['paid' => '1']))
            ->assertOk()->assertSee('PRO-PAID')->assertSee('PRO-VIAPOL')->assertDontSee('PRO-UNPAID')
            ->assertSee(__('common.sunu_first_premium_paid'));
        $this->actingAs($this->admin)->get(route('business.sunu.proposals.show', $unpaid->id))
            ->assertOk()->assertSee(__('common.sunu_first_premium_unpaid'))->assertSee(__('common.sunu_accept_help'));
    }

    public function test_first_premium_does_not_create_a_policy(): void
    {
        $proposal = $this->proposal('PRO-1');
        $this->pay($proposal)->assertCreated()->assertJsonPath('data.policy_id', null);
        $this->assertSame(0, SunuPolicy::count());
        $this->assertNotSame('accepted', $proposal->fresh()->status);
    }
}
