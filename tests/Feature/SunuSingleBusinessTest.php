<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\User;
use App\Support\SunuBusiness;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** SUNU-only mode (config sunu.single_business, on by default). */
class SunuSingleBusinessTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $sunu;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        SunuBusiness::forget();
        $this->sunu = BusinessUnit::create(['name' => 'SUNU Assurances Vie', 'slug' => 'sunu-'.uniqid(), 'is_active' => true]);
        $this->admin = User::factory()->create(['is_super_admin' => true, 'current_business_unit_id' => null, 'password' => Hash::make('secret123')]);
    }

    public function test_mode_is_on_by_default(): void
    {
        $this->assertTrue(config('sunu.single_business'));
        $this->assertSame($this->sunu->id, SunuBusiness::main()->id);
    }

    public function test_super_admin_login_lands_inside_sunu(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'secret123'])
            ->assertRedirect(route('business.dashboard'));
        $this->assertSame($this->sunu->id, $this->admin->fresh()->current_business_unit_id);
        $this->actingAs($this->admin->fresh())->get('/dashboard')->assertRedirect(route('business.dashboard'));
    }

    public function test_old_session_without_business_is_put_back_in_sunu(): void
    {
        $this->actingAs($this->admin)->get(route('superadmin.dashboard'))->assertRedirect(route('business.dashboard'));
        $this->assertSame($this->sunu->id, $this->admin->fresh()->current_business_unit_id);
    }

    public function test_creating_a_business_is_refused(): void
    {
        $this->actingAs($this->admin)->get(route('superadmin.business-units.create'))
            ->assertRedirect(route('superadmin.business-units.edit', $this->sunu->id))
            ->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('superadmin.business-units.store'), ['name' => 'Other'])
            ->assertRedirect(route('superadmin.business-units.edit', $this->sunu->id));
        $this->assertSame(1, BusinessUnit::count());
        $this->actingAs($this->admin)->get(route('superadmin.business-units.index'))
            ->assertRedirect(route('superadmin.business-units.edit', $this->sunu->id));
        $this->actingAs($this->admin)->get(route('superadmin.business-units.edit', $this->sunu->id))->assertOk();
    }

    public function test_no_switching_and_no_switch_to_superadmin_button(): void
    {
        $other = BusinessUnit::create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'is_active' => true]);
        $this->actingAs($this->admin)->post(route('superadmin.switch-business', $other->id))->assertNotFound();
        $this->actingAs($this->admin)->post(route('superadmin.switch-back'))->assertRedirect(route('business.dashboard'));
        $this->assertSame($this->sunu->id, $this->admin->fresh()->current_business_unit_id);

        $html = $this->actingAs($this->admin->fresh())->get(route('business.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('superadmin.switch-back'), $html);
        $this->assertStringNotContainsString(__('common.switch_to_superadmin'), $html);
        $this->assertStringContainsString(__('common.sunu_admin_menu'), $html);
        $this->assertStringContainsString(route('superadmin.business-units.edit', $this->sunu->id), $html);
        $this->assertStringContainsString(route('superadmin.sms.config.index'), $html);
        $this->assertStringContainsString(route('superadmin.user-management.permissions.index'), $html);
    }

    public function test_normal_user_does_not_see_administration(): void
    {
        $user = User::factory()->create(['current_business_unit_id' => $this->sunu->id]);
        $user->businessUnits()->attach($this->sunu->id, ['is_active' => true]);
        $html = $this->actingAs($user)->get(route('business.dashboard'))->getContent();
        $this->assertStringNotContainsString(__('common.sunu_admin_menu'), $html);
        $this->actingAs($user)->get(route('superadmin.business-units.create'))->assertForbidden();
    }

    public function test_switch_off_restores_multi_business(): void
    {
        config(['sunu.single_business' => false]);
        $this->actingAs($this->admin)->get(route('superadmin.dashboard'))->assertOk();
        $this->assertNull($this->admin->fresh()->current_business_unit_id);
        $this->actingAs($this->admin)->get(route('superadmin.business-units.create'))->assertOk();
    }

    public function test_fresh_install_without_business_can_create_the_first_one(): void
    {
        BusinessUnit::query()->delete();
        SunuBusiness::forget();
        $this->actingAs($this->admin)->get(route('superadmin.business-units.create'))->assertOk();
    }
}
