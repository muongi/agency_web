<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AgentPinResetTest extends TestCase
{
    use RefreshDatabase;

    protected function userWithEditAgent(BusinessUnit $bu): User
    {
        $perm = Permission::firstOrCreate(
            ['slug' => 'edit-agent'],
            [
                'name' => 'Edit Agent',
                'description' => 'Test',
                'group' => 'Agents',
            ]
        );

        $role = Role::create([
            'name' => 'Agent editor',
            'business_unit_id' => $bu->id,
        ]);
        $role->permissions()->sync([$perm->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $bu->id,
        ]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        return $user;
    }

    protected function createAgent(BusinessUnit $bu, array $overrides = []): Agent
    {
        return Agent::create(array_merge([
            'business_unit_id' => $bu->id,
            'first_name' => 'Test',
            'second_name' => 'Agent',
            'email' => 'agent-'.uniqid().'@example.com',
            'phone_number' => '237600000001',
            'id_passport' => 'ID'.uniqid(),
            'username' => 'agent_'.uniqid(),
            'password' => Hash::make('old-password'),
            'is_first_time_pin' => false,
        ], $overrides));
    }

    public function test_reset_pin_forbidden_without_permission(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'PIN BU',
            'slug' => 'pin-bu-'.uniqid(),
            'is_active' => true,
        ]);

        $role = Role::create([
            'name' => 'No perms',
            'business_unit_id' => $bu->id,
        ]);

        $user = User::factory()->create([
            'current_business_unit_id' => $bu->id,
        ]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $agent = $this->createAgent($bu);

        $this->actingAs($user)
            ->post(route('agents.reset-pin-otp', $agent->id))
            ->assertForbidden();
    }

    public function test_reset_pin_requires_phone_number(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'PIN BU 2',
            'slug' => 'pin-bu2-'.uniqid(),
            'is_active' => true,
        ]);

        $user = $this->userWithEditAgent($bu);
        $agent = $this->createAgent($bu, ['phone_number' => '']);

        $this->actingAs($user)
            ->from(route('agents.show', $agent->id))
            ->post(route('agents.reset-pin-otp', $agent->id))
            ->assertRedirect(route('agents.show', $agent->id))
            ->assertSessionHas('error');
    }

    public function test_reset_pin_updates_password_sends_sms_and_logs_pin(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'PIN BU 3',
            'slug' => 'pin-bu3-'.uniqid(),
            'is_active' => true,
            'sms_sender_number' => 'tel:+237600000000',
        ]);

        $user = $this->userWithEditAgent($bu);
        $agent = $this->createAgent($bu);

        $oldHash = $agent->password;

        $loggedPin = null;

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use (&$loggedPin) {
                if ($message === 'Agent PIN reset (web)') {
                    $loggedPin = $context['generated_pin'] ?? null;

                    return $loggedPin !== null;
                }

                return false;
            });

        $this->mock(SmsService::class, function ($mock) use ($bu, $agent) {
            $mock->shouldReceive('sendSms')
                ->once()
                ->with(
                    $bu->id,
                    $agent->phone_number,
                    \Mockery::on(fn (string $msg) => str_contains($msg, 'PIN') || str_contains($msg, 'PIN provisoire')),
                    null,
                    'AGENT-PIN-RESET-'.$agent->id
                )
                ->andReturn(['success' => true, 'message' => 'sent']);
        });

        $this->actingAs($user)
            ->from(route('agents.show', $agent->id))
            ->post(route('agents.reset-pin-otp', $agent->id))
            ->assertRedirect(route('agents.show', $agent->id))
            ->assertSessionHas('success');

        $agent->refresh();

        $this->assertTrue($agent->is_first_time_pin);
        $this->assertNotSame($oldHash, $agent->password);
        $this->assertNotNull($loggedPin);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $loggedPin);
        $this->assertTrue(Hash::check($loggedPin, $agent->password));
    }

    public function test_reset_pin_succeeds_with_warning_when_sms_fails(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'PIN BU 4',
            'slug' => 'pin-bu4-'.uniqid(),
            'is_active' => true,
        ]);

        $user = $this->userWithEditAgent($bu);
        $agent = $this->createAgent($bu);

        Log::shouldReceive('info')
            ->once()
            ->with('Agent PIN reset (web)', \Mockery::type('array'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Agent PIN reset SMS failed', \Mockery::type('array'));

        $this->mock(SmsService::class, function ($mock) {
            $mock->shouldReceive('sendSms')
                ->once()
                ->andReturn(['success' => false, 'message' => 'Insufficient SMS balance']);
        });

        $this->actingAs($user)
            ->post(route('agents.reset-pin-otp', $agent->id))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $agent->refresh();
        $this->assertTrue($agent->is_first_time_pin);
    }
}
