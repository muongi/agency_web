<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SunuProposal;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Role pages show SUNU permission groups only (banking / CNSS hidden) and the
 * SUNU web pages, menus and buttons follow those permissions.
 */
class SunuPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected Role $role;
    protected User $user;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sunu.core_banking' => false, 'sunu.show_cnss' => false]);
        $this->seed(PermissionsSeeder::class);

        $this->bu = BusinessUnit::create(['name' => 'SUNU Test', 'slug' => 'sunu-perm-'.uniqid(), 'is_active' => true]);
        $this->role = Role::create(['name' => 'Caissier', 'slug' => 'caissier', 'business_unit_id' => $this->bu->id]);
        $this->user = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => false]);
        $this->user->businessUnits()->attach($this->bu->id, ['role_id' => $this->role->id, 'is_active' => true]);

        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'business_unit_id' => $this->bu->id]);
        $this->admin = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => true]);
        $this->admin->businessUnits()->attach($this->bu->id, ['role_id' => $adminRole->id, 'is_active' => true]);
    }

    protected function grant(string ...$slugs): void
    {
        $this->role->permissions()->syncWithoutDetaching(Permission::whereIn('slug', $slugs)->pluck('id'));
    }

    protected function proposal(): SunuProposal
    {
        $product = InsuranceProduct::create([
            'business_unit_id' => $this->bu->id, 'code' => 'MICRO_RETRAITE', 'name_fr' => 'Micro Retraite',
            'category' => 'micro-insurance', 'default_premium_frequency' => 'monthly', 'is_active' => true,
        ]);
        $client = Client::create(['business_unit_id' => $this->bu->id, 'last_name' => 'OUEDRAOGO', 'first_name' => 'Awa', 'phone_number' => '+22670000001']);

        return SunuProposal::create([
            'business_unit_id' => $this->bu->id, 'client_id' => $client->id, 'insurance_product_id' => $product->id,
            'proposal_number' => 'P-'.uniqid(), 'last_name' => 'OUEDRAOGO', 'first_names' => 'Awa',
            'phone' => '+22670000001', 'status' => 'submitted',
        ]);
    }

    public function test_proposal_photo_is_served_to_a_viewer(): void
    {
        Storage::fake('public');
        $this->grant('view-sunu-proposals');
        $proposal = $this->proposal();
        Storage::disk('public')->put('sunu/proposals/'.$proposal->id.'/form.jpg', 'jpeg-bytes');
        $proposal->update(['proposal_image_path' => 'sunu/proposals/'.$proposal->id.'/form.jpg']);

        $this->actingAs($this->user)
            ->get(route('business.sunu.proposals.file', [$proposal->id, 'proposal_image_path']))
            ->assertOk();
    }

    public function test_role_page_hides_banking_and_cnss_groups_and_shows_sunu_groups(): void
    {
        $html = $this->actingAs($this->admin)->get(route('business.roles.create'))->assertOk()->getContent();

        foreach (['view-sunu-proposals', 'validate-sunu-payins', 'manage-sunu-products', 'view-sunu-report-cards', 'view-agents'] as $slug) {
            $this->assertStringContainsString('value="'.Permission::where('slug', $slug)->value('id').'"', $html, $slug);
        }
        foreach (['view-accounts', 'view-transactions', 'view-cnss-members', 'view-transaction-types', 'request-agent-reports', 'view-reports'] as $slug) {
            $this->assertStringNotContainsString('value="'.Permission::where('slug', $slug)->value('id').'"', $html, $slug);
        }
    }

    public function test_banking_groups_come_back_with_core_banking(): void
    {
        config(['sunu.core_banking' => true]);
        $html = $this->actingAs($this->admin)->get(route('business.roles.create'))->getContent();

        $this->assertStringContainsString('value="'.Permission::where('slug', 'view-accounts')->value('id').'"', $html);
        $this->assertStringNotContainsString('value="'.Permission::where('slug', 'view-cnss-members')->value('id').'"', $html);
    }

    public function test_saving_a_role_keeps_hidden_permissions_it_already_had(): void
    {
        $this->grant('view-accounts', 'view-cnss-members', 'view-sunu-policies');
        $premiums = Permission::where('slug', 'view-sunu-premiums')->value('id');
        $accounts = Permission::where('slug', 'view-accounts')->value('id');
        $other = Role::create(['name' => 'Other', 'slug' => 'other', 'business_unit_id' => $this->bu->id]);

        $this->actingAs($this->admin)->put(route('business.roles.update', $this->role->id), [
            'name' => 'Caissier', 'permissions' => [$premiums],
        ])->assertRedirect(route('business.roles.index'));

        $slugs = $this->role->permissions()->pluck('slug')->sort()->values()->all();
        $this->assertSame(['view-accounts', 'view-cnss-members', 'view-sunu-premiums'], $slugs);

        // a hidden permission cannot be added from the page
        $this->actingAs($this->admin)->put(route('business.roles.update', $other->id), [
            'name' => 'Other', 'permissions' => [$premiums, $accounts],
        ]);
        $this->assertSame(['view-sunu-premiums'], $other->permissions()->pluck('slug')->all());
    }

    public function test_view_only_user_sees_proposals_but_cannot_act(): void
    {
        $this->grant('view-sunu-proposals');
        $proposal = $this->proposal();

        $this->actingAs($this->user)->get(route('business.sunu.proposals.index'))->assertOk();
        $this->actingAs($this->user)->get(route('business.sunu.proposals.show', $proposal->id))
            ->assertOk()
            ->assertDontSee(route('business.sunu.proposals.accept', $proposal->id))
            ->assertDontSee(route('business.sunu.proposals.link-card', $proposal->id));

        $this->actingAs($this->user)->post(route('business.sunu.proposals.accept', $proposal->id))->assertForbidden();
        $this->actingAs($this->user)->post(route('business.sunu.proposals.link-card', $proposal->id), ['card_uid' => 'BEEF0001'])->assertForbidden();
        $this->actingAs($this->user)->get(route('business.sunu.payins.index'))->assertForbidden();
        $this->actingAs($this->user)->get(route('business.sunu.products.index'))->assertForbidden();
        $this->actingAs($this->user)->get(route('business.sunu.import'))->assertForbidden();
        $this->actingAs($this->user)->get(route('business.sunu.reports.premiums'))->assertForbidden();
        $this->assertSame('submitted', $proposal->fresh()->status);
    }

    public function test_action_permission_allows_the_action(): void
    {
        $this->grant('view-sunu-proposals', 'accept-sunu-proposal');
        $proposal = $this->proposal();

        $this->actingAs($this->user)->get(route('business.sunu.proposals.show', $proposal->id))
            ->assertSee(route('business.sunu.proposals.accept', $proposal->id));
        $this->actingAs($this->user)->post(route('business.sunu.proposals.accept', $proposal->id), [
            'insured_number' => 'ASS-PERM-1', 'policy_number' => 'POL-PERM-1',
        ])->assertRedirect();
        $this->assertSame('accepted', $proposal->fresh()->status);
    }

    public function test_menu_shows_only_permitted_sunu_links(): void
    {
        $this->grant('view-sunu-premiums', 'view-sunu-report-reconciliation');

        $this->actingAs($this->user)->get(route('business.sunu.premiums.index'))
            ->assertOk()
            ->assertSee(route('business.sunu.premiums.index'))
            ->assertSee(route('business.sunu.reports.reconciliation'))
            ->assertDontSee(route('business.sunu.proposals.index'))
            ->assertDontSee(route('business.sunu.payins.index'))
            ->assertDontSee(route('business.sunu.reports.cards'))
            ->assertDontSee(route('business.sunu.premiums.export'));
    }

    public function test_report_export_needs_export_permission(): void
    {
        $this->grant('view-sunu-report-cards');
        $this->actingAs($this->user)->get(route('business.sunu.reports.cards'))->assertOk();
        $this->actingAs($this->user)->get(route('business.sunu.reports.cards', ['export' => 'csv']))->assertForbidden();

        $this->grant('export-sunu-reports');
        $this->actingAs($this->user)->get(route('business.sunu.reports.cards', ['export' => 'csv']))->assertOk();
    }
}
