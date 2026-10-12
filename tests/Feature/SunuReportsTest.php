<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Card;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\Role;
use App\Models\SunuPayIn;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Models\SunuProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Core banking hidden (config sunu.core_banking off) + SUNU reports and dashboard.
 */
class SunuReportsTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected User $user;
    protected Agent $agent;
    protected InsuranceProduct $product;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sunu.core_banking' => false]);

        $this->bu = BusinessUnit::create(['name' => 'SUNU Test', 'slug' => 'sunu-rep-'.uniqid(), 'is_active' => true]);
        $role = Role::create(['name' => 'SUNU admin', 'business_unit_id' => $this->bu->id]);
        $this->user = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => true]);
        $this->user->businessUnits()->attach($this->bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $this->product = InsuranceProduct::create([
            'business_unit_id' => $this->bu->id, 'code' => 'MICRO_RETRAITE', 'name_fr' => 'Micro Retraite',
            'name_en' => 'Micro Retirement', 'category' => 'micro-insurance', 'default_premium_frequency' => 'monthly',
            'default_premium_amount' => 2500, 'is_active' => true,
        ]);
        $this->client = Client::create(['business_unit_id' => $this->bu->id, 'last_name' => 'OUEDRAOGO', 'first_name' => 'Awa', 'phone_number' => '+22670000001']);
        $this->agent = Agent::create([
            'business_unit_id' => $this->bu->id, 'first_name' => 'Issa', 'second_name' => 'Collecteur',
            'email' => 'issa@test.local', 'username' => 'issa', 'phone_number' => '+22670000002',
            'id_passport' => 'ID-ISSA', 'password' => Hash::make('secret123'),
        ]);
    }

    protected function policy(string $number, string $status = SunuPolicy::STATUS_ACTIVE, ?string $due = null): SunuPolicy
    {
        return SunuPolicy::create([
            'business_unit_id' => $this->bu->id, 'client_id' => $this->client->id, 'insurance_product_id' => $this->product->id,
            'policy_number' => $number, 'status' => $status, 'premium_amount' => 2500, 'premium_frequency' => 'monthly',
            'source' => 'import', 'next_due_date' => $due,
        ]);
    }

    protected function premium(SunuPolicy $policy, float $amount, string $kind = 'recurring_premium'): SunuPremiumCollection
    {
        return SunuPremiumCollection::create([
            'business_unit_id' => $this->bu->id, 'policy_id' => $policy->id, 'agent_id' => $this->agent->id,
            'amount' => $amount, 'collection_kind' => $kind, 'reference' => 'REF-'.uniqid(), 'collected_at' => now(),
        ]);
    }

    protected function payIn(float $amount, string $status, ?float $confirmed = null): SunuPayIn
    {
        return SunuPayIn::create([
            'business_unit_id' => $this->bu->id, 'agent_id' => $this->agent->id, 'declared_amount' => $amount,
            'method' => 'bank', 'declared_at' => now(), 'status' => $status, 'confirmed_amount' => $confirmed,
        ]);
    }

    public function test_core_banking_pages_hidden_and_menu_clean(): void
    {
        $this->actingAs($this->user);
        $this->get('/accounts')->assertNotFound();
        $this->get('/transactions')->assertNotFound();
        $this->get(route('business.config.transaction-types.index'))->assertNotFound();
        $this->get(route('business.reports.index'))->assertNotFound();

        $this->get(route('business.dashboard'))
            ->assertOk()
            ->assertDontSee(route('accounts.index'), false)
            ->assertDontSee(route('transactions.index'), false)
            ->assertSee(route('business.sunu.reports.premiums'), false);

        // API untouched
        $this->postJson('/api/v1/agent/login', ['username' => 'issa', 'password' => 'secret123'])->assertOk();
    }

    public function test_core_banking_comes_back_with_flag(): void
    {
        config(['sunu.core_banking' => true]);
        $this->actingAs($this->user)->get('/accounts')->assertOk();
    }

    public function test_premiums_report_totals_and_export(): void
    {
        $pol = $this->policy('POL-1');
        $this->premium($pol, 2500);
        $this->premium($pol, 1000, 'policy_cost');

        $this->actingAs($this->user)->get(route('business.sunu.reports.premiums'))
            ->assertOk()
            ->assertSee('3 500 XOF')
            ->assertSee('POL-1')
            ->assertSee('Issa Collecteur');

        $csv = $this->actingAs($this->user)->withSession(['locale' => 'fr'])->get(route('business.sunu.reports.premiums', ['export' => 'csv']));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('Montant', $content);
        $this->assertStringContainsString('POL-1', $content);
    }

    public function test_reconciliation_balance_is_collected_minus_confirmed(): void
    {
        $pol = $this->policy('POL-1');
        $this->premium($pol, 10000);
        $this->payIn(6000, SunuPayIn::STATUS_CONFIRMED, 6000);
        $this->payIn(3000, SunuPayIn::STATUS_DECLARED);
        $this->payIn(500, SunuPayIn::STATUS_REJECTED);

        $data = app(\App\Services\SunuReportService::class)->reconciliation($this->bu->id, [
            'from' => now()->startOfMonth(), 'to' => now()->endOfDay(), 'agent_id' => null, 'terminal_id' => null, 'product_id' => null,
        ]);
        $row = $data['rows']->first();
        $this->assertEquals(10000, $row['collected']);
        $this->assertEquals(6000, $row['confirmed']);
        $this->assertEquals(3000, $row['pending']);
        $this->assertEquals(500, $row['rejected']);
        $this->assertEquals(4000, $row['balance']);

        $this->actingAs($this->user)->get(route('business.sunu.reports.reconciliation'))->assertOk()->assertSee('4 000');
    }

    public function test_subscriptions_report_lists_only_payable_overdue_policies(): void
    {
        $this->policy('POL-LATE', SunuPolicy::STATUS_ACTIVE, now()->subDays(10)->toDateString());
        $this->policy('POL-OK', SunuPolicy::STATUS_ACTIVE, now()->addDays(10)->toDateString());
        $this->policy('POL-DEAD', SunuPolicy::STATUS_TERMINATED, now()->subDays(10)->toDateString());
        SunuProposal::create([
            'business_unit_id' => $this->bu->id, 'client_id' => $this->client->id, 'insurance_product_id' => $this->product->id,
            'agent_id' => $this->agent->id, 'proposal_number' => 'P-1', 'status' => SunuProposal::STATUS_TRANSMITTED,
            'last_name' => 'OUEDRAOGO', 'first_names' => 'Awa',
        ]);

        $this->actingAs($this->user)->get(route('business.sunu.reports.subscriptions'))
            ->assertOk()
            ->assertSee('POL-LATE')
            ->assertDontSee('POL-OK')
            ->assertDontSee('POL-DEAD');

        $csv = $this->actingAs($this->user)->get(route('business.sunu.reports.subscriptions', ['export' => 'csv']))->streamedContent();
        $this->assertStringContainsString('POL-LATE', $csv);
        $this->assertStringNotContainsString('POL-DEAD', $csv);
    }

    public function test_cards_report_and_dashboard(): void
    {
        Card::create([
            'business_unit_id' => $this->bu->id, 'card_number' => 'C-1', 'card_serial' => 'AAAA0001', 'prefix' => 'S',
            'status' => 'active', 'expiration_date' => now()->addYears(3), 'written_at' => now(),
            'written_by' => "agent:{$this->agent->id} / f20abc",
        ]);
        Card::create([
            'business_unit_id' => $this->bu->id, 'card_number' => 'C-2', 'card_serial' => 'AAAA0002', 'prefix' => 'S',
            'status' => 'unassigned', 'expiration_date' => now()->addYears(3),
        ]);

        $this->actingAs($this->user)->get(route('business.sunu.reports.cards'))
            ->assertOk()
            ->assertSee('AAAA0001')
            ->assertSee('Issa Collecteur');

        $this->premium($this->policy('POL-1'), 2500);
        $this->actingAs($this->user)->get(route('business.dashboard'))
            ->assertOk()
            ->assertSee('2 500 XOF')
            ->assertSee(__('common.sunu_dash_top_agents'));
    }
}
