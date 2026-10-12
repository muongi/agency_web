<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SunuPayIn;
use App\Models\Terminal;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Deposit receipt on the TPE: declared pay-in comes back with a VER- reference
 * ("En attente de validation"), and GET /sunu/pay-ins lets the collector reprint it
 * once the office has confirmed it ("Validé").
 */
class SunuPayInReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected Agent $agent;
    protected Agent $other;
    protected string $serial;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sunu.core_banking' => false, 'sunu.show_cnss' => false]);
        $this->seed(PermissionsSeeder::class);

        $this->bu = BusinessUnit::create(['name' => 'SUNU Test', 'slug' => 'sunu-rcpt-'.uniqid(), 'is_active' => true]);
        $this->agent = $this->makeAgent('One');
        $this->other = $this->makeAgent('Two');

        $this->serial = 'f20'.substr(str_replace('.', '', uniqid('', true)), 0, 13);
        $terminal = Terminal::create([
            'business_unit_id' => $this->bu->id, 'serial_number' => $this->serial, 'simcard_number' => '000',
            'device_alias' => 'F20', 'model_make' => 'Telpo',
        ]);
        $terminal->agents()->attach($this->agent->id, ['assigned_at' => now()]);

        $role = Role::create(['name' => 'Caissier', 'slug' => 'caissier', 'business_unit_id' => $this->bu->id]);
        $role->permissions()->sync(Permission::whereIn('slug', ['view-sunu-payins', 'validate-sunu-payins'])->pluck('id'));
        $this->user = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => false]);
        $this->user->businessUnits()->attach($this->bu->id, ['role_id' => $role->id, 'is_active' => true]);
    }

    protected function makeAgent(string $name): Agent
    {
        $uniq = str_replace('.', '', uniqid('', true));

        return Agent::create([
            'business_unit_id' => $this->bu->id, 'first_name' => 'Collector', 'second_name' => $name,
            'email' => 'rcpt-'.$uniq.'@test.local', 'username' => 'rcpt'.$uniq, 'phone_number' => '+2267'.substr($uniq, -8),
            'id_passport' => 'ID-'.$uniq, 'password' => Hash::make('SunuPilot2026!'),
        ]);
    }

    protected function declare(array $overrides = [])
    {
        return $this->postJson('/api/v1/sunu/pay-ins', array_merge([
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'agent_id' => $this->agent->id,
            'declared_amount' => 50000, 'method' => 'cash_office', 'client_reference' => 'PAYIN-'.uniqid(),
        ], $overrides));
    }

    protected function list(array $overrides = [])
    {
        return $this->getJson('/api/v1/sunu/pay-ins?'.http_build_query(array_merge([
            'business_unit_id' => $this->bu->id, 'device_serial' => $this->serial, 'agent_id' => $this->agent->id,
        ], $overrides)));
    }

    public function test_declared_pay_in_returns_receipt_fields(): void
    {
        $res = $this->declare()->assertCreated();
        $id = $res->json('data.id');

        $res->assertJsonPath('data.reference', sprintf('VER-%06d', $id))
            ->assertJsonPath('data.status', SunuPayIn::STATUS_DECLARED)
            ->assertJsonPath('data.agent_name', 'Collector One')
            ->assertJsonPath('data.terminal_serial', $this->serial)
            ->assertJsonPath('data.confirmed_at', null)
            ->assertJsonMissingPath('data.deposit_slip_path');
    }

    public function test_same_client_reference_returns_the_same_receipt(): void
    {
        $first = $this->declare(['client_reference' => 'PAYIN-1'])->assertCreated();
        $this->declare(['client_reference' => 'PAYIN-1'])->assertOk()
            ->assertJsonPath('data.reference', $first->json('data.reference'));
        $this->assertSame(1, SunuPayIn::count());
    }

    public function test_list_shows_only_own_pay_ins_and_office_validation(): void
    {
        $id = $this->declare(['method' => 'bank_deposit', 'bank_reference' => 'BRD-1'])->json('data.id');
        SunuPayIn::create([
            'business_unit_id' => $this->bu->id, 'agent_id' => $this->other->id, 'declared_amount' => 1000,
            'method' => 'cash_office', 'declared_at' => now(), 'status' => SunuPayIn::STATUS_DECLARED,
        ]);

        $this->list()->assertOk()
            ->assertJsonCount(1, 'data.pay_ins')
            ->assertJsonPath('data.pay_ins.0.id', $id)
            ->assertJsonPath('data.pay_ins.0.status', SunuPayIn::STATUS_DECLARED);

        $this->actingAs($this->user)->post(route('business.sunu.payins.confirm', $id), [
            'confirmed_amount' => 49000, 'deposited_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->list()->assertOk()
            ->assertJsonPath('data.pay_ins.0.status', SunuPayIn::STATUS_CONFIRMED)
            ->assertJsonPath('data.pay_ins.0.confirmed_amount', 49000)
            ->assertJsonPath('data.pay_ins.0.confirmed_by_name', $this->user->name)
            ->assertJsonPath('data.pay_ins.0.bank_reference', 'BRD-1');
        $this->assertNotNull($this->list()->json('data.pay_ins.0.confirmed_at'));
    }

    public function test_rejected_pay_in_carries_the_office_note(): void
    {
        $id = $this->declare()->json('data.id');
        $this->actingAs($this->user)->post(route('business.sunu.payins.reject', $id), ['notes' => 'Bordereau illisible'])
            ->assertRedirect();

        $this->list()->assertOk()
            ->assertJsonPath('data.pay_ins.0.status', SunuPayIn::STATUS_REJECTED)
            ->assertJsonPath('data.pay_ins.0.reject_note', 'Bordereau illisible')
            ->assertJsonPath('data.pay_ins.0.confirmed_at', null);
    }

    public function test_list_needs_an_agent_assigned_to_the_terminal(): void
    {
        $this->list(['agent_id' => null])->assertStatus(422);
        $this->list(['agent_id' => $this->other->id])->assertStatus(403);
    }

    public function test_web_list_shows_the_reference(): void
    {
        $ref = $this->declare()->json('data.reference');
        $this->actingAs($this->user)->get(route('business.sunu.payins.index'))->assertOk()->assertSee($ref);
    }
}
