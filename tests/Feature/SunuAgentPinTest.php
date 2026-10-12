<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\Role;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Collection PIN: separate from the login password, checked on premiums, locked after 5 wrong tries.
 */
class SunuAgentPinTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected Agent $agent;
    protected string $serial;
    protected SunuPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bu = BusinessUnit::create(['name' => 'SUNU Test', 'slug' => 'sunu-pin-'.uniqid(), 'is_active' => true]);
        $product = InsuranceProduct::create([
            'business_unit_id' => $this->bu->id, 'code' => 'MICRO_RETRAITE', 'name_fr' => 'Micro Retraite',
            'name_en' => 'Micro Retirement', 'category' => 'micro-insurance', 'default_premium_frequency' => 'monthly',
            'default_premium_amount' => 2500, 'is_active' => true,
        ]);
        $client = Client::create(['business_unit_id' => $this->bu->id, 'last_name' => 'KABORE', 'first_name' => 'Awa', 'phone_number' => '+22670000001']);
        $this->policy = SunuPolicy::create([
            'business_unit_id' => $this->bu->id, 'client_id' => $client->id, 'insurance_product_id' => $product->id,
            'policy_number' => 'POL-PIN-1', 'status' => SunuPolicy::STATUS_ACTIVE, 'premium_amount' => 2500,
            'premium_frequency' => 'monthly', 'source' => 'import',
        ]);

        $uniq = str_replace('.', '', uniqid('', true));
        $this->agent = Agent::create([
            'business_unit_id' => $this->bu->id, 'first_name' => 'Collector', 'second_name' => 'One',
            'email' => 'col-'.$uniq.'@test.local', 'username' => 'col'.$uniq, 'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-'.$uniq, 'password' => Hash::make('SunuPilot2026!'),
        ]);
        $this->serial = 'f20'.substr($uniq, 0, 13);
        $terminal = Terminal::create([
            'business_unit_id' => $this->bu->id, 'serial_number' => $this->serial, 'simcard_number' => '000',
            'device_alias' => 'F20', 'model_make' => 'Telpo',
        ]);
        $terminal->agents()->attach($this->agent->id, ['assigned_at' => now()]);
    }

    protected function premium(array $overrides = [])
    {
        static $n = 0;
        $n++;

        return $this->postJson('/api/v1/sunu/premiums', array_merge([
            'business_unit_id' => $this->bu->id,
            'device_serial' => $this->serial,
            'agent_id' => $this->agent->id,
            'policy_id' => $this->policy->id,
            'amount' => 2500,
            'collection_kind' => 'recurring_premium',
            'reference' => 'PIN-TEST-'.$n.'-'.uniqid(),
        ], $overrides));
    }

    public function test_agent_without_pin_can_still_collect(): void
    {
        $this->premium(['pin' => '9999'])->assertSuccessful();
        $this->assertSame(1, SunuPremiumCollection::count());
    }

    public function test_correct_pin_accepted_wrong_pin_refused_and_nothing_saved(): void
    {
        $this->agent->setCollectionPin('1234');

        $this->premium(['pin' => '1234'])->assertSuccessful();
        $this->premium(['pin' => '0000'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_PIN')
            ->assertJsonPath('attempts_left', 4);

        $this->assertSame(1, SunuPremiumCollection::count());
        // Login password untouched
        $this->assertTrue(Hash::check('SunuPilot2026!', $this->agent->fresh()->password));
    }

    public function test_five_wrong_pins_lock_until_reset(): void
    {
        $this->agent->setCollectionPin('1234');

        foreach (range(1, 4) as $i) {
            $this->premium(['pin' => '0000'])->assertStatus(401);
        }
        $this->premium(['pin' => '0000'])->assertStatus(423)->assertJsonPath('code', 'PIN_LOCKED');
        // Even the right PIN is refused while locked
        $this->premium(['pin' => '1234'])->assertStatus(423);
        $this->assertSame(0, SunuPremiumCollection::count());

        $this->agent->fresh()->setCollectionPin('4821');
        $this->premium(['pin' => '4821'])->assertSuccessful();
    }

    public function test_offline_replay_without_pin_is_accepted(): void
    {
        $this->agent->setCollectionPin('1234');
        $this->premium()->assertSuccessful(); // outbox strips the PIN
        $this->assertSame(1, SunuPremiumCollection::count());
    }

    public function test_pinless_replay_refused_after_wrong_pin_or_lock(): void
    {
        $this->agent->setCollectionPin('1234');

        // Old TPE: wrong PIN -> queued -> replayed without PIN
        $this->premium(['pin' => '0000'])->assertStatus(401);
        $this->premium()->assertStatus(401)->assertJsonPath('code', 'PIN_REQUIRED');

        // Right PIN clears the wrong tries; replays pass again
        $this->premium(['pin' => '1234'])->assertSuccessful();
        $this->premium()->assertSuccessful();

        $this->agent->fresh()->forceFill(['pin_locked_at' => now()])->save();
        $this->premium()->assertStatus(423);
        $this->assertSame(2, SunuPremiumCollection::count());
    }

    public function test_login_reports_pin_flags_and_change_pin(): void
    {
        $this->agent->setCollectionPin('1234', true);

        $this->postJson('/api/v1/agent/login', ['username' => $this->agent->username, 'password' => 'SunuPilot2026!'])
            ->assertOk()
            ->assertJsonPath('data.agent.pin_set', true)
            ->assertJsonPath('data.agent.pin_must_change', true);

        $change = fn ($old, $new) => $this->postJson('/api/v1/agent/pin', ['agent_id' => $this->agent->id, 'old_pin' => $old, 'new_pin' => $new]);
        $change('1234', '5678')->assertStatus(422)->assertJsonPath('code', 'PIN_TOO_SIMPLE');
        $change('1234', '1111')->assertStatus(422)->assertJsonPath('code', 'PIN_TOO_SIMPLE');
        $change('9999', '4821')->assertStatus(401);
        $change('1234', '4821')->assertOk();

        $agent = $this->agent->fresh();
        $this->assertFalse($agent->pin_must_change);
        $this->assertSame('ok', $agent->checkCollectionPin('4821'));
        $this->assertTrue(Hash::check('SunuPilot2026!', $agent->password));
    }

    protected function webUser(): User
    {
        $role = Role::create(['name' => 'SUNU admin', 'business_unit_id' => $this->bu->id]);
        $user = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => true]);
        $user->businessUnits()->attach($this->bu->id, ['role_id' => $role->id, 'is_active' => true]);

        return $user;
    }

    public function test_web_reset_changes_only_pin_and_shows_it_when_sms_fails(): void
    {
        // SMS is not configured in tests, so the send fails like on live today
        $this->agent->setCollectionPin('1234');
        $this->agent->forceFill(['pin_locked_at' => now(), 'pin_failed_attempts' => 5])->save();

        $response = $this->actingAs($this->webUser())->post(route('agents.reset-pin-otp', $this->agent->id));
        $response->assertRedirect()->assertSessionHas('pin_reveal');

        $pin = session('pin_reveal');
        $agent = $this->agent->fresh();
        $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);
        $this->assertFalse($agent->isPinLocked());
        $this->assertTrue($agent->pin_must_change);
        $this->assertSame('ok', $agent->checkCollectionPin($pin));
        $this->assertTrue(Hash::check('SunuPilot2026!', $agent->password));
    }

    public function test_web_reset_with_default_test_pin(): void
    {
        config(['sunu.default_agent_pin' => '1234']);
        $this->actingAs($this->webUser())
            ->post(route('agents.reset-pin-otp', $this->agent->id), ['use_default' => 1])
            ->assertSessionHas('success');

        $this->assertSame('ok', $this->agent->fresh()->checkCollectionPin('1234'));
    }

    public function test_default_pin_command_sets_only_missing_pins(): void
    {
        config(['sunu.default_agent_pin' => '1234']);
        $other = Agent::create([
            'business_unit_id' => $this->bu->id, 'first_name' => 'B', 'second_name' => 'Two', 'email' => 'b@test.local',
            'username' => 'agentb', 'phone_number' => '+22671111111', 'id_passport' => 'ID-B', 'password' => Hash::make('x'),
        ]);
        $other->setCollectionPin('4821', false);

        $this->artisan('sunu:agents-default-pin')->assertExitCode(0);

        $this->assertSame('ok', $this->agent->fresh()->checkCollectionPin('1234'));
        $this->assertSame('ok', $other->fresh()->checkCollectionPin('4821'));
    }
}
