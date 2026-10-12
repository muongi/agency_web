<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\CollectorBalance;
use App\Models\CollectorCap;
use App\Models\InsuranceProduct;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SunuPayIn;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Models\Terminal;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Collector collection limit (TDR): premium crossing the limit accepted, then blocked;
 * blocked collector refused; full deposit confirmed in the back office → TPE released.
 */
class SunuCollectorCapTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected Agent $agent;
    protected string $serial;
    protected SunuPolicy $policy;
    protected Role $role;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sunu.core_banking' => false, 'sunu.show_cnss' => false]);
        $this->seed(PermissionsSeeder::class);

        $this->bu = BusinessUnit::create(['name' => 'SUNU Test', 'slug' => 'sunu-cap-'.uniqid(), 'is_active' => true]);
        $product = InsuranceProduct::create([
            'business_unit_id' => $this->bu->id, 'code' => 'MICRO_RETRAITE', 'name_fr' => 'Micro Retraite',
            'name_en' => 'Micro Retirement', 'category' => 'micro-insurance', 'default_premium_frequency' => 'monthly',
            'default_premium_amount' => 2500, 'is_active' => true,
        ]);
        $client = Client::create(['business_unit_id' => $this->bu->id, 'last_name' => 'KABORE', 'first_name' => 'Awa', 'phone_number' => '+22670000001']);
        $this->policy = SunuPolicy::create([
            'business_unit_id' => $this->bu->id, 'client_id' => $client->id, 'insurance_product_id' => $product->id,
            'policy_number' => 'POL-CAP-1', 'status' => SunuPolicy::STATUS_ACTIVE, 'premium_amount' => 2500,
            'premium_frequency' => 'monthly', 'source' => 'import', 'sunulife_policy_id' => 'SL-777',
        ]);

        $uniq = str_replace('.', '', uniqid('', true));
        $this->agent = Agent::create([
            'business_unit_id' => $this->bu->id, 'first_name' => 'Collector', 'second_name' => 'One',
            'email' => 'cap-'.$uniq.'@test.local', 'username' => 'cap'.$uniq, 'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-'.$uniq, 'password' => Hash::make('SunuPilot2026!'),
        ]);
        $this->serial = 'f20'.substr($uniq, 0, 13);
        $terminal = Terminal::create([
            'business_unit_id' => $this->bu->id, 'serial_number' => $this->serial, 'simcard_number' => '000',
            'device_alias' => 'F20', 'model_make' => 'Telpo',
        ]);
        $terminal->agents()->attach($this->agent->id, ['assigned_at' => now()]);

        $this->role = Role::create(['name' => 'Caissier', 'slug' => 'caissier', 'business_unit_id' => $this->bu->id]);
        $this->user = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => false]);
        $this->user->businessUnits()->attach($this->bu->id, ['role_id' => $this->role->id, 'is_active' => true]);
    }

    protected function grant(string ...$slugs): void
    {
        $this->role->permissions()->syncWithoutDetaching(Permission::whereIn('slug', $slugs)->pluck('id'));
    }

    protected function premium(float $amount, array $overrides = [])
    {
        static $n = 0;
        $n++;

        return $this->postJson('/api/v1/sunu/premiums', array_merge([
            'business_unit_id' => $this->bu->id,
            'device_serial' => $this->serial,
            'agent_id' => $this->agent->id,
            'policy_id' => $this->policy->id,
            'amount' => $amount,
            'collection_kind' => 'recurring_premium',
            'reference' => 'CAP-TEST-'.$n.'-'.uniqid(),
        ], $overrides));
    }

    protected function balance(): CollectorBalance
    {
        return CollectorBalance::where('agent_id', $this->agent->id)->firstOrFail();
    }

    public function test_no_limit_means_unlimited(): void
    {
        $this->premium(1000000)->assertCreated()->assertJsonPath('collector.cap_amount', null);
    }

    public function test_business_unit_default_applies_and_agent_override_wins(): void
    {
        $this->bu->update(['sunu_collector_cap_amount' => 10000]);
        $this->premium(2500)->assertCreated()
            ->assertJsonPath('collector.cap_amount', 10000)
            ->assertJsonPath('collector.cap_source', 'default')
            ->assertJsonPath('collector.remaining_amount', 7500);

        // Agent limit 3000 wins over the default: 2500 held + 1000 crosses it → accepted, then blocked.
        CollectorCap::create(['agent_id' => $this->agent->id, 'cap_amount' => 3000, 'is_active' => true]);
        $this->premium(1000)->assertCreated()
            ->assertJsonPath('collector.cap_source', 'agent')
            ->assertJsonPath('collector.is_blocked', true);
    }

    public function test_premium_that_crosses_the_limit_is_accepted_then_collector_is_blocked(): void
    {
        $this->bu->update(['sunu_collector_cap_amount' => 5000]);
        $this->premium(2500)->assertCreated();

        $this->premium(3000)->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('collector.uncollected_amount', 5500)
            ->assertJsonPath('collector.is_blocked', true);

        $this->assertSame(2, SunuPremiumCollection::count());
        $this->assertEquals(5500, (float) $this->balance()->uncollected_amount);
        $this->assertTrue($this->balance()->is_blocked);

        // Next premium (online or outbox replay) refused until the deposit is confirmed.
        $this->premium(100)->assertStatus(423)->assertJsonPath('code', 'COLLECTOR_BLOCKED');
        $this->premium(100, ['pin' => ''])->assertStatus(423)->assertJsonPath('code', 'COLLECTOR_BLOCKED');
        $this->assertSame(2, SunuPremiumCollection::count());
    }

    public function test_reaching_the_limit_blocks_until_full_deposit_is_confirmed(): void
    {
        $this->bu->update(['sunu_collector_cap_amount' => 5000]);
        $this->premium(2500)->assertCreated();
        $this->premium(2500)->assertCreated()->assertJsonPath('collector.is_blocked', true);

        $this->premium(100)->assertStatus(423)->assertJsonPath('code', 'COLLECTOR_BLOCKED');
        // Outbox replay (no PIN) is refused too.
        $this->premium(100, ['pin' => ''])->assertStatus(423)->assertJsonPath('code', 'COLLECTOR_BLOCKED');

        $this->grant('view-sunu-payins', 'validate-sunu-payins');

        // Partial deposit: still blocked.
        $this->actingAs($this->user)->post(route('business.sunu.payins.store'), [
            'agent_id' => $this->agent->id, 'amount' => 3000, 'method' => 'cash_office',
            'deposited_at' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertEquals(2000, (float) $this->balance()->uncollected_amount);
        $this->assertTrue($this->balance()->is_blocked);

        // Rest deposited: released.
        $this->actingAs($this->user)->post(route('business.sunu.payins.store'), [
            'agent_id' => $this->agent->id, 'amount' => 2000, 'method' => 'cash_office',
            'deposited_at' => now()->toDateString(),
        ])->assertRedirect();
        $this->assertEquals(0, (float) $this->balance()->uncollected_amount);
        $this->assertFalse($this->balance()->is_blocked);

        $this->premium(2500)->assertCreated();
    }

    public function test_bank_deposit_needs_reference_and_keeps_details_and_slip(): void
    {
        Storage::fake('local');
        $this->grant('view-sunu-payins', 'validate-sunu-payins');
        $this->premium(2500)->assertCreated();

        $this->actingAs($this->user)->from(route('business.sunu.payins.index'))->post(route('business.sunu.payins.store'), [
            'agent_id' => $this->agent->id, 'amount' => 2500, 'method' => 'bank_deposit',
            'deposited_at' => now()->toDateString(),
        ])->assertSessionHasErrors('bank_reference');
        $this->assertSame(0, SunuPayIn::count());

        $this->actingAs($this->user)->post(route('business.sunu.payins.store'), [
            'agent_id' => $this->agent->id, 'amount' => 2500, 'method' => 'bank_deposit',
            'bank_reference' => 'BRD-2026-0042', 'bank_name' => 'Coris Bank', 'bank_account' => 'BF123',
            'deposited_at' => now()->toDateString(),
            'deposit_slip' => UploadedFile::fake()->image('slip.jpg'),
        ])->assertRedirect();

        $payIn = SunuPayIn::firstOrFail();
        $this->assertSame(SunuPayIn::STATUS_CONFIRMED, $payIn->status);
        $this->assertSame(SunuPayIn::SOURCE_BO, $payIn->source);
        $this->assertSame('BRD-2026-0042', $payIn->bank_reference);
        $this->assertSame($this->user->id, $payIn->recorded_by);
        Storage::disk('local')->assertExists($payIn->deposit_slip_path);
        $this->actingAs($this->user)->get(route('business.sunu.payins.slip', $payIn->id))->assertOk();

        $this->actingAs($this->user)->get(route('business.sunu.payins.index'))->assertOk()
            ->assertSee('BRD-2026-0042')->assertSee('Collector One');
    }

    public function test_tpe_declared_pay_in_confirm_asks_reference_and_reject_needs_note(): void
    {
        $this->grant('view-sunu-payins', 'validate-sunu-payins');
        $this->bu->update(['sunu_collector_cap_amount' => 2500]);
        $this->premium(2500)->assertCreated();

        $this->postJson('/api/v1/sunu/pay-ins', [
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'agent_id' => $this->agent->id,
            'declared_amount' => 2500, 'method' => 'bank_deposit',
        ])->assertCreated();
        $payIn = SunuPayIn::firstOrFail();

        $this->actingAs($this->user)->post(route('business.sunu.payins.reject', $payIn->id), [])->assertSessionHasErrors('notes');
        $this->actingAs($this->user)->post(route('business.sunu.payins.confirm', $payIn->id), [])->assertSessionHasErrors('bank_reference');

        $this->actingAs($this->user)->post(route('business.sunu.payins.confirm', $payIn->id), [
            'bank_reference' => 'BRD-9', 'deposited_at' => now()->toDateString(),
        ])->assertRedirect();
        $payIn->refresh();
        $this->assertSame(SunuPayIn::STATUS_CONFIRMED, $payIn->status);
        $this->assertSame('BRD-9', $payIn->bank_reference);
        $this->assertFalse($this->balance()->is_blocked);
    }

    public function test_manual_release_and_limit_changes(): void
    {
        $this->bu->update(['sunu_collector_cap_amount' => 2500]);
        $this->premium(2500)->assertCreated();
        $this->assertTrue($this->balance()->is_blocked);

        $this->actingAs($this->user)->post(route('business.sunu.collectors.release', $this->agent->id), ['note' => 'x'])->assertForbidden();

        $this->grant('view-sunu-payins', 'manage-sunu-collector-caps');
        $this->actingAs($this->user)->post(route('business.sunu.collectors.release', $this->agent->id), [])->assertSessionHasErrors('note');
        $this->actingAs($this->user)->post(route('business.sunu.collectors.release', $this->agent->id), ['note' => 'Dépôt vu, bordereau demain'])->assertRedirect();
        $this->assertFalse($this->balance()->is_blocked);
        $this->assertSame($this->user->id, $this->balance()->released_by);

        // Raise the default: stays open; lower below held: blocked again.
        $this->actingAs($this->user)->post(route('business.sunu.collectors.cap-default'), ['cap_amount' => 10000, 'warning_pct' => 80])->assertRedirect();
        $this->premium(2500)->assertCreated();
        $this->actingAs($this->user)->post(route('business.sunu.collectors.cap-default'), ['cap_amount' => 4000, 'warning_pct' => 80])->assertRedirect();
        $this->assertTrue($this->balance()->is_blocked);

        // Agent own limit above the held amount releases.
        $this->actingAs($this->user)->post(route('agents.collector-cap', $this->agent->id), ['mode' => 'custom', 'cap_amount' => 20000, 'warning_pct' => 90])
            ->assertRedirect();
        $this->assertFalse($this->balance()->is_blocked);
        $this->premium(2500)->assertCreated()->assertJsonPath('collector.cap_source', 'agent');

        // Back to default (4000 < 7500 held): blocked.
        $this->actingAs($this->user)->post(route('agents.collector-cap', $this->agent->id), ['mode' => 'default'])->assertRedirect();
        $this->assertTrue($this->balance()->is_blocked);
    }

    public function test_agent_page_shows_limit_panel(): void
    {
        $this->bu->update(['sunu_collector_cap_amount' => 50000]);
        $this->grant('view-agent', 'manage-sunu-collector-caps');
        $admin = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => true]);
        $admin->businessUnits()->attach($this->bu->id, ['role_id' => $this->role->id, 'is_active' => true]);

        $this->actingAs($admin)->get(route('agents.show', ['agent' => $this->agent->id, 'tab' => 'terminals']))
            ->assertOk()->assertSee(__('common.collector_cap'))->assertSee('50 000');
    }

    public function test_premiums_excel_export_has_sunulife_columns(): void
    {
        $this->grant('view-sunu-premiums', 'export-sunu-premiums');
        $this->premium(2500)->assertCreated();

        $csv = $this->actingAs($this->user)->get(route('business.sunu.premiums.export', ['agent_id' => $this->agent->id]))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('SL-777', $csv);
        $this->assertStringContainsString('MICRO_RETRAITE', $csv);

        $xlsx = $this->actingAs($this->user)->get(route('business.sunu.premiums.export', ['format' => 'excel']))->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $xlsx->headers->get('content-type'));
    }

    public function test_login_returns_collector_status(): void
    {
        $this->bu->update(['sunu_collector_cap_amount' => 10000]);
        $this->postJson('/api/v1/agent/login', [
            'username' => $this->agent->username, 'password' => 'SunuPilot2026!', 'device_serial' => $this->serial,
        ])->assertOk()->assertJsonPath('data.agent.collector.cap_amount', 10000);
    }
}
