<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\CommissionConfiguration;
use App\Models\CommissionTier;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TransactionCostConfiguration;
use App\Models\TransactionCostTier;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\TransactionCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionTierInheritanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Core banking is hidden by default (SUNU deployment); these tests cover the banking pages.
        config(['sunu.core_banking' => true]);
    }

    private function createBusinessUser(BusinessUnit $businessUnit): User
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'view-commissions'],
            [
                'name' => 'View Commissions',
                'description' => 'Test',
                'group' => 'Test',
            ]
        );

        $role = Role::create([
            'name' => 'Commission tester',
            'business_unit_id' => $businessUnit->id,
        ]);
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $businessUnit->id,
        ]);
        $user->businessUnits()->attach($businessUnit->id, ['role_id' => $role->id, 'is_active' => true]);

        return $user;
    }

    private function seedTieredCostSetup(BusinessUnit $businessUnit): array
    {
        $transactionType = TransactionType::create([
            'business_unit_id' => $businessUnit->id,
            'name' => 'Tiered transfer',
            'code' => 'TTR'.uniqid(),
            'is_active' => true,
        ]);

        $costConfig = TransactionCostConfiguration::create([
            'business_unit_id' => $businessUnit->id,
            'transaction_type_id' => $transactionType->id,
            'cost_type' => 'tiered_fixed',
            'is_active' => true,
        ]);

        TransactionCostTier::create([
            'transaction_cost_configuration_id' => $costConfig->id,
            'min_amount' => 0,
            'max_amount' => 10000,
            'amount' => 100,
            'order' => 0,
        ]);

        TransactionCostTier::create([
            'transaction_cost_configuration_id' => $costConfig->id,
            'min_amount' => 10000.01,
            'max_amount' => null,
            'amount' => 250,
            'order' => 1,
        ]);

        return compact('transactionType', 'costConfig');
    }

    public function test_tiered_percentage_commission_inherits_cost_tier_order(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Tier Inherit BU',
            'slug' => 'tier-inherit-'.uniqid(),
            'is_active' => true,
        ]);

        $setup = $this->seedTieredCostSetup($businessUnit);

        $commission = CommissionConfiguration::create([
            'business_unit_id' => $businessUnit->id,
            'transaction_type_id' => $setup['transactionType']->id,
            'commission_type' => 'tiered_percentage',
            'is_active' => true,
        ]);

        CommissionTier::create([
            'commission_configuration_id' => $commission->id,
            'min_amount' => 0,
            'max_amount' => 10000,
            'rate' => 10,
            'order' => 0,
        ]);

        CommissionTier::create([
            'commission_configuration_id' => $commission->id,
            'min_amount' => 10000.01,
            'max_amount' => null,
            'rate' => 20,
            'order' => 1,
        ]);

        $this->assertEquals(10.0, $commission->calculateFromTransactionAmount(5000));
        $this->assertEquals(50.0, $commission->calculateFromTransactionAmount(20000));
    }

    public function test_fixed_commission_uses_computed_transaction_cost(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Fixed Commission BU',
            'slug' => 'fixed-commission-'.uniqid(),
            'is_active' => true,
        ]);

        $setup = $this->seedTieredCostSetup($businessUnit);

        $commission = CommissionConfiguration::create([
            'business_unit_id' => $businessUnit->id,
            'transaction_type_id' => $setup['transactionType']->id,
            'commission_type' => 'fixed',
            'fixed_amount' => 30,
            'is_active' => true,
        ]);

        $this->assertEquals(30.0, $commission->calculateFromTransactionAmount(5000));
        $this->assertEquals(30.0, $commission->calculateFromTransactionAmount(20000));
    }

    public function test_calculate_split_uses_inherited_tiered_commission(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Split BU',
            'slug' => 'split-bu-'.uniqid(),
            'is_active' => true,
        ]);

        $setup = $this->seedTieredCostSetup($businessUnit);

        $commission = CommissionConfiguration::create([
            'business_unit_id' => $businessUnit->id,
            'transaction_type_id' => $setup['transactionType']->id,
            'commission_type' => 'tiered_percentage',
            'is_active' => true,
        ]);

        CommissionTier::create([
            'commission_configuration_id' => $commission->id,
            'min_amount' => 0,
            'max_amount' => 10000,
            'rate' => 10,
            'order' => 0,
        ]);

        CommissionTier::create([
            'commission_configuration_id' => $commission->id,
            'min_amount' => 10000.01,
            'max_amount' => null,
            'rate' => 20,
            'order' => 1,
        ]);

        $service = new TransactionCalculationService();
        $result = $service->calculateSplit($setup['transactionType']->id, 5000, $businessUnit->id);

        $this->assertEquals(100.0, $result['transaction_cost']);
        $this->assertEquals(10.0, $result['commission']);
        $this->assertEquals(90.0, $result['business_share']);
    }

    public function test_store_rejects_tiered_commission_when_cost_is_not_tiered(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Reject Tiered BU',
            'slug' => 'reject-tiered-'.uniqid(),
            'is_active' => true,
        ]);

        $transactionType = TransactionType::create([
            'business_unit_id' => $businessUnit->id,
            'name' => 'Flat cost type',
            'code' => 'FLAT'.uniqid(),
            'is_active' => true,
        ]);

        TransactionCostConfiguration::create([
            'business_unit_id' => $businessUnit->id,
            'transaction_type_id' => $transactionType->id,
            'cost_type' => 'fixed',
            'fixed_amount' => 50,
            'is_active' => true,
        ]);

        $user = $this->createBusinessUser($businessUnit);

        $this->actingAs($user)
            ->from(route('business.config.commissions.create'))
            ->post(route('business.config.commissions.store'), [
                'transaction_type_id' => $transactionType->id,
                'commission_type' => 'tiered_percentage',
                'is_active' => 1,
                'tiers' => [
                    ['rate' => 10],
                ],
            ])
            ->assertSessionHasErrors('commission_type');
    }

    public function test_store_rejects_mismatched_commission_tier_count(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Mismatch BU',
            'slug' => 'mismatch-'.uniqid(),
            'is_active' => true,
        ]);

        $setup = $this->seedTieredCostSetup($businessUnit);
        $user = $this->createBusinessUser($businessUnit);

        $this->actingAs($user)
            ->from(route('business.config.commissions.create'))
            ->post(route('business.config.commissions.store'), [
                'transaction_type_id' => $setup['transactionType']->id,
                'commission_type' => 'tiered_percentage',
                'is_active' => 1,
                'tiers' => [
                    ['rate' => 10],
                ],
            ])
            ->assertSessionHasErrors('tiers');
    }

    public function test_store_syncs_commission_tiers_from_cost_tiers(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Sync BU',
            'slug' => 'sync-'.uniqid(),
            'is_active' => true,
        ]);

        $setup = $this->seedTieredCostSetup($businessUnit);
        $user = $this->createBusinessUser($businessUnit);

        $this->actingAs($user)
            ->post(route('business.config.commissions.store'), [
                'transaction_type_id' => $setup['transactionType']->id,
                'commission_type' => 'tiered_percentage',
                'is_active' => 1,
                'tiers' => [
                    ['rate' => 15],
                    ['rate' => 25],
                ],
            ])
            ->assertRedirect(route('business.config.commissions.index'));

        $commission = CommissionConfiguration::first();
        $tiers = $commission->tiers()->orderBy('order')->get();

        $this->assertCount(2, $tiers);
        $this->assertEquals(0, $tiers[0]->min_amount);
        $this->assertEquals(10000, $tiers[0]->max_amount);
        $this->assertEquals(15, $tiers[0]->rate);
        $this->assertEquals(10000.01, $tiers[1]->min_amount);
        $this->assertNull($tiers[1]->max_amount);
        $this->assertEquals(25, $tiers[1]->rate);
    }
}
