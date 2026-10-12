<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\CommissionConfiguration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TransactionCostConfiguration;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionFeeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Core banking is hidden by default (SUNU deployment); these tests cover the banking pages.
        config(['sunu.core_banking' => true]);
    }

    private function createBusinessUserWithPermissions(BusinessUnit $businessUnit, array $permissionSlugs): User
    {
        $permissionIds = collect($permissionSlugs)->map(function (string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => ucwords(str_replace('-', ' ', $slug)),
                    'description' => 'Test permission',
                    'group' => 'Test',
                ]
            )->id;
        })->all();

        $role = Role::create([
            'name' => 'Fee config tester',
            'business_unit_id' => $businessUnit->id,
        ]);
        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create([
            'current_business_unit_id' => $businessUnit->id,
        ]);
        $user->businessUnits()->attach($businessUnit->id, ['role_id' => $role->id, 'is_active' => true]);

        return $user;
    }

    private function createAssignedGlobalTransactionType(BusinessUnit $businessUnit): TransactionType
    {
        $uniq = str_replace('.', '', uniqid('', true));

        $transactionType = TransactionType::create([
            'business_unit_id' => null,
            'name' => 'Global deposit '.$uniq,
            'code' => 'GTT'.$uniq,
            'description' => 'Assigned global type for tests',
            'is_active' => true,
        ]);

        $businessUnit->assignedTransactionTypes()->attach($transactionType->id);

        return $transactionType;
    }

    public function test_transaction_cost_setup_lists_assigned_global_transaction_types(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Fee Config BU',
            'slug' => 'fee-config-'.uniqid(),
            'is_active' => true,
        ]);

        $transactionType = $this->createAssignedGlobalTransactionType($businessUnit);
        $user = $this->createBusinessUserWithPermissions($businessUnit, ['view-transaction-costs']);

        $this->actingAs($user)
            ->get(route('business.config.transaction-costs.index'))
            ->assertOk()
            ->assertDontSee(__('common.no_transaction_types_available'))
            ->assertDontSee(__('common.no_assigned_transaction_types'))
            ->assertSee(route('business.config.transaction-costs.create'));
    }

    public function test_transaction_cost_create_includes_assigned_global_type(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Fee Config BU 2',
            'slug' => 'fee-config2-'.uniqid(),
            'is_active' => true,
        ]);

        $transactionType = $this->createAssignedGlobalTransactionType($businessUnit);
        $user = $this->createBusinessUserWithPermissions($businessUnit, ['view-transaction-costs']);

        $this->actingAs($user)
            ->get(route('business.config.transaction-costs.create'))
            ->assertOk()
            ->assertSee($transactionType->name)
            ->assertSee($transactionType->code);
    }

    public function test_transaction_cost_store_accepts_assigned_global_type(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Fee Config BU 3',
            'slug' => 'fee-config3-'.uniqid(),
            'is_active' => true,
        ]);

        $transactionType = $this->createAssignedGlobalTransactionType($businessUnit);
        $user = $this->createBusinessUserWithPermissions($businessUnit, ['view-transaction-costs']);

        $this->actingAs($user)
            ->post(route('business.config.transaction-costs.store'), [
                'transaction_type_id' => $transactionType->id,
                'cost_type' => 'fixed',
                'fixed_amount' => 50,
                'is_active' => 1,
            ])
            ->assertRedirect(route('business.config.transaction-costs.index'));

        $this->assertDatabaseHas('transaction_cost_configurations', [
            'business_unit_id' => $businessUnit->id,
            'transaction_type_id' => $transactionType->id,
            'cost_type' => 'fixed',
            'fixed_amount' => 50,
        ]);
    }

    public function test_commission_setup_lists_assigned_global_transaction_types(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Commission Config BU',
            'slug' => 'commission-config-'.uniqid(),
            'is_active' => true,
        ]);

        $this->createAssignedGlobalTransactionType($businessUnit);
        $user = $this->createBusinessUserWithPermissions($businessUnit, ['view-commissions']);

        $this->actingAs($user)
            ->get(route('business.config.commissions.index'))
            ->assertOk()
            ->assertDontSee(__('common.no_transaction_types_available'))
            ->assertDontSee(__('common.no_assigned_transaction_types'))
            ->assertSee(route('business.config.commissions.create'));
    }

    public function test_commission_create_and_store_accepts_assigned_global_type(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Commission Config BU 2',
            'slug' => 'commission-config2-'.uniqid(),
            'is_active' => true,
        ]);

        $transactionType = $this->createAssignedGlobalTransactionType($businessUnit);
        $user = $this->createBusinessUserWithPermissions($businessUnit, ['view-commissions']);

        $this->actingAs($user)
            ->get(route('business.config.commissions.create'))
            ->assertOk()
            ->assertSee($transactionType->name)
            ->assertSee($transactionType->code);

        $this->actingAs($user)
            ->post(route('business.config.commissions.store'), [
                'transaction_type_id' => $transactionType->id,
                'commission_type' => 'percentage',
                'percentage_rate' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect(route('business.config.commissions.index'));

        $this->assertDatabaseHas('commission_configurations', [
            'business_unit_id' => $businessUnit->id,
            'transaction_type_id' => $transactionType->id,
            'commission_type' => 'percentage',
            'percentage_rate' => 10,
        ]);
    }

    public function test_commission_can_be_recreated_after_delete_for_same_transaction_type(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Commission Recreate BU',
            'slug' => 'commission-recreate-'.uniqid(),
            'is_active' => true,
        ]);

        $transactionType = $this->createAssignedGlobalTransactionType($businessUnit);
        $user = $this->createBusinessUserWithPermissions($businessUnit, ['view-commissions']);

        $this->actingAs($user)
            ->post(route('business.config.commissions.store'), [
                'transaction_type_id' => $transactionType->id,
                'commission_type' => 'percentage',
                'percentage_rate' => 12,
                'is_active' => 1,
            ])
            ->assertRedirect(route('business.config.commissions.index'));

        $existing = CommissionConfiguration::where('business_unit_id', $businessUnit->id)
            ->where('transaction_type_id', $transactionType->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->delete(route('business.config.commissions.destroy', $existing->id))
            ->assertRedirect(route('business.config.commissions.index'));

        $this->actingAs($user)
            ->post(route('business.config.commissions.store'), [
                'transaction_type_id' => $transactionType->id,
                'commission_type' => 'fixed',
                'fixed_amount' => 250,
                'is_active' => 1,
            ])
            ->assertRedirect(route('business.config.commissions.index'));

        $this->assertEquals(
            1,
            CommissionConfiguration::withTrashed()
                ->where('business_unit_id', $businessUnit->id)
                ->where('transaction_type_id', $transactionType->id)
                ->count()
        );

        $this->assertDatabaseHas('commission_configurations', [
            'business_unit_id' => $businessUnit->id,
            'transaction_type_id' => $transactionType->id,
            'commission_type' => 'fixed',
            'fixed_amount' => 250,
            'deleted_at' => null,
        ]);
    }

    public function test_transaction_cost_store_rejects_duplicate_for_same_transaction_type(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Duplicate Cost BU',
            'slug' => 'duplicate-cost-'.uniqid(),
            'is_active' => true,
        ]);

        $transactionType = $this->createAssignedGlobalTransactionType($businessUnit);
        $user = $this->createBusinessUserWithPermissions($businessUnit, ['view-transaction-costs']);

        $this->actingAs($user)
            ->post(route('business.config.transaction-costs.store'), [
                'transaction_type_id' => $transactionType->id,
                'cost_type' => 'fixed',
                'fixed_amount' => 50,
                'is_active' => 1,
            ])
            ->assertRedirect(route('business.config.transaction-costs.index'));

        $this->actingAs($user)
            ->from(route('business.config.transaction-costs.create'))
            ->post(route('business.config.transaction-costs.store'), [
                'transaction_type_id' => $transactionType->id,
                'cost_type' => 'tiered_fixed',
                'is_active' => 1,
                'tiers' => [
                    ['min_amount' => 0, 'max_amount' => 10000, 'amount' => 100],
                ],
            ])
            ->assertSessionHasErrors('transaction_type_id');

        $this->assertEquals(1, TransactionCostConfiguration::where('business_unit_id', $businessUnit->id)
            ->where('transaction_type_id', $transactionType->id)
            ->count());
    }

    public function test_transaction_cost_store_rejects_duplicate_when_prior_row_is_soft_deleted(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Soft Deleted Cost BU',
            'slug' => 'soft-deleted-cost-'.uniqid(),
            'is_active' => true,
        ]);

        $transactionType = $this->createAssignedGlobalTransactionType($businessUnit);
        $user = $this->createBusinessUserWithPermissions($businessUnit, ['view-transaction-costs']);

        $existing = TransactionCostConfiguration::create([
            'business_unit_id' => $businessUnit->id,
            'transaction_type_id' => $transactionType->id,
            'cost_type' => 'fixed',
            'fixed_amount' => 25,
            'is_active' => true,
        ]);
        $existing->delete();

        $this->actingAs($user)
            ->from(route('business.config.transaction-costs.create'))
            ->post(route('business.config.transaction-costs.store'), [
                'transaction_type_id' => $transactionType->id,
                'cost_type' => 'fixed',
                'fixed_amount' => 75,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('transaction_type_id');

        $this->assertEquals(1, TransactionCostConfiguration::withTrashed()
            ->where('business_unit_id', $businessUnit->id)
            ->where('transaction_type_id', $transactionType->id)
            ->count());
    }

    public function test_commission_create_shows_edit_guidance_when_cost_is_not_tiered(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Tiered Commission Guidance BU',
            'slug' => 'tiered-guidance-'.uniqid(),
            'is_active' => true,
        ]);

        $transactionType = $this->createAssignedGlobalTransactionType($businessUnit);
        $costConfig = TransactionCostConfiguration::create([
            'business_unit_id' => $businessUnit->id,
            'transaction_type_id' => $transactionType->id,
            'cost_type' => 'fixed',
            'fixed_amount' => 100,
            'is_active' => true,
        ]);

        $user = $this->createBusinessUserWithPermissions($businessUnit, ['view-commissions']);

        $this->actingAs($user)
            ->get(route('business.config.commissions.create'))
            ->assertOk()
            ->assertSee(__('common.edit_existing_transaction_cost_for_tiered_commission'), false)
            ->assertSee('"id":'.$costConfig->id, false)
            ->assertSee('"cost_type":"fixed"', false)
            ->assertSee('"is_tiered":false', false);
    }

    public function test_transactions_filter_includes_assigned_global_type(): void
    {
        $businessUnit = BusinessUnit::create([
            'name' => 'Transactions Filter BU',
            'slug' => 'transactions-filter-'.uniqid(),
            'is_active' => true,
        ]);

        $transactionType = $this->createAssignedGlobalTransactionType($businessUnit);
        $user = $this->createBusinessUserWithPermissions($businessUnit, ['view-transactions']);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSee($transactionType->name)
            ->assertSee('value="'.$transactionType->id.'"', false);
    }
}
