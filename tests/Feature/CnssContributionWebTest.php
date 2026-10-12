<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\CnssContribution;
use App\Models\CnssVoluntaryEnrollment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CnssContributionWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributions_pages_forbidden_without_permission(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Web CNSS Contrib BU',
            'slug' => 'web-cnss-contrib-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $role = Role::create([
            'name' => 'Empty role',
            'business_unit_id' => $bu->id,
        ]);

        $user = User::factory()->create([
            'current_business_unit_id' => $bu->id,
        ]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('business.cnss.contributions.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('business.cnss.contributions.dashboard'))
            ->assertForbidden();
    }

    public function test_contributions_index_and_dashboard_ok_with_permission(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', config('app.timezone')));

        $bu = BusinessUnit::create([
            'name' => 'Web CNSS Contrib BU 2',
            'slug' => 'web-cnss-contrib2-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $permList = Permission::firstOrCreate(
            ['slug' => 'view-cnss-members'],
            [
                'name' => 'View CNSS Members',
                'description' => 'Test',
                'group' => 'CNSS',
            ]
        );

        $role = Role::create([
            'name' => 'CNSS contributions reader',
            'business_unit_id' => $bu->id,
        ]);
        $role->permissions()->sync([$permList->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $bu->id,
        ]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $uniq = str_replace('.', '', uniqid('', true));
        $agent = Agent::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Alpha',
            'second_name' => 'Agent',
            'email' => 'cnss-web-agent-'.$uniq.'@test.local',
            'username' => 'cnsswebag'.$uniq,
            'phone_number' => '+2267'.substr($uniq, 0, 8),
            'id_passport' => 'ID-WEB-'.$uniq,
            'password' => Hash::make('123456'),
        ]);

        $enrollment = CnssVoluntaryEnrollment::create([
            'business_unit_id' => $bu->id,
            'status' => CnssVoluntaryEnrollment::STATUS_APPROVED,
            'voluntary_insured_number' => 'VINSWEB00001',
            'cnss_registration_affiliation_number' => 'AFFWEB00001',
            'last_name' => 'Ranked',
            'first_names' => 'Member',
            'form_data' => [],
        ]);

        CnssContribution::create([
            'business_unit_id' => $bu->id,
            'cnss_voluntary_enrollment_id' => $enrollment->id,
            'agent_id' => $agent->id,
            'voluntary_insured_number' => $enrollment->voluntary_insured_number,
            'affiliation_number' => $enrollment->cnss_registration_affiliation_number,
            'amount' => 150.50,
            'reference_period' => '2026-06',
            'payment_frequency' => 'monthly',
            'mode' => CnssContribution::MODE_RECORD,
            'reference' => 'REF-WEB-'.uniqid(),
        ]);

        $this->actingAs($user)
            ->get(route('business.cnss.contributions.index'))
            ->assertOk()
            ->assertSee('VINSWEB00001', false)
            ->assertSee('150.50', false);

        $this->actingAs($user)
            ->get(route('business.cnss.contributions.dashboard'))
            ->assertOk()
            ->assertSee(__('common.cnss_dashboard'), false)
            ->assertSee('150.50', false)
            ->assertSee('Ranked', false)
            ->assertSee('Alpha', false);

        Carbon::setTestNow();
    }

    public function test_contributions_index_scoped_to_current_business_unit(): void
    {
        $buA = BusinessUnit::create([
            'name' => 'BU A',
            'slug' => 'bu-a-cnss-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);
        $buB = BusinessUnit::create([
            'name' => 'BU B',
            'slug' => 'bu-b-cnss-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $permList = Permission::firstOrCreate(
            ['slug' => 'view-cnss-members'],
            [
                'name' => 'View CNSS Members',
                'description' => 'Test',
                'group' => 'CNSS',
            ]
        );

        $roleA = Role::create(['name' => 'Reader A', 'business_unit_id' => $buA->id]);
        $roleA->permissions()->sync([$permList->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $buA->id,
        ]);
        $user->businessUnits()->attach($buA->id, ['role_id' => $roleA->id, 'is_active' => true]);

        $enrollmentB = CnssVoluntaryEnrollment::create([
            'business_unit_id' => $buB->id,
            'status' => CnssVoluntaryEnrollment::STATUS_APPROVED,
            'voluntary_insured_number' => 'VINSOTHERBU',
            'cnss_registration_affiliation_number' => 'AFFOTHERBU',
            'form_data' => [],
        ]);

        CnssContribution::create([
            'business_unit_id' => $buB->id,
            'cnss_voluntary_enrollment_id' => $enrollmentB->id,
            'voluntary_insured_number' => $enrollmentB->voluntary_insured_number,
            'affiliation_number' => null,
            'amount' => 999.00,
            'reference_period' => '2026-01',
            'payment_frequency' => 'monthly',
            'mode' => CnssContribution::MODE_RECORD,
            'reference' => 'REF-OTHER-'.uniqid(),
        ]);

        $this->actingAs($user)
            ->get(route('business.cnss.contributions.index'))
            ->assertOk()
            ->assertDontSee('VINSOTHERBU', false);
    }

    public function test_contributions_export_forbidden_without_permission(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Export Contrib BU',
            'slug' => 'export-contrib-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $role = Role::create(['name' => 'No CNSS', 'business_unit_id' => $bu->id]);
        $user = User::factory()->create(['current_business_unit_id' => $bu->id]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('business.cnss.contributions.export', ['format' => 'csv']))
            ->assertForbidden();
    }

    public function test_contributions_export_csv_excel_pdf_with_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-20 10:00:00', config('app.timezone')));

        $bu = BusinessUnit::create([
            'name' => 'Export Contrib BU 2',
            'slug' => 'export-contrib2-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $permList = Permission::firstOrCreate(
            ['slug' => 'view-cnss-members'],
            ['name' => 'View CNSS Members', 'description' => 'Test', 'group' => 'CNSS']
        );

        $role = Role::create(['name' => 'CNSS export reader', 'business_unit_id' => $bu->id]);
        $role->permissions()->sync([$permList->id]);

        $user = User::factory()->create(['current_business_unit_id' => $bu->id]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $enrollment = CnssVoluntaryEnrollment::create([
            'business_unit_id' => $bu->id,
            'status' => CnssVoluntaryEnrollment::STATUS_APPROVED,
            'voluntary_insured_number' => 'VINSEXPCON01',
            'cnss_registration_affiliation_number' => 'AFFEXPCON01',
            'last_name' => 'Contrib',
            'first_names' => 'Export',
            'form_data' => [],
        ]);

        CnssContribution::create([
            'business_unit_id' => $bu->id,
            'cnss_voluntary_enrollment_id' => $enrollment->id,
            'voluntary_insured_number' => $enrollment->voluntary_insured_number,
            'affiliation_number' => $enrollment->cnss_registration_affiliation_number,
            'amount' => 200.00,
            'reference_period' => '2026-06',
            'payment_frequency' => 'monthly',
            'mode' => CnssContribution::MODE_RECORD,
            'reference' => 'REF-EXPORT-'.uniqid(),
        ]);

        CnssContribution::create([
            'business_unit_id' => $bu->id,
            'cnss_voluntary_enrollment_id' => $enrollment->id,
            'voluntary_insured_number' => $enrollment->voluntary_insured_number,
            'affiliation_number' => $enrollment->cnss_registration_affiliation_number,
            'amount' => 300.00,
            'reference_period' => '2026-05',
            'payment_frequency' => 'monthly',
            'mode' => CnssContribution::MODE_LEDGER,
            'reference' => 'REF-LEDGER-'.uniqid(),
        ]);

        foreach (['csv', 'excel', 'pdf'] as $format) {
            $response = $this->actingAs($user)
                ->get(route('business.cnss.contributions.export', [
                    'format' => $format,
                    'mode' => 'record',
                    'start_date' => '2026-06-01',
                    'end_date' => '2026-06-30',
                ]));

            $response->assertSuccessful();
            $this->assertStringContainsString('attachment', strtolower((string) $response->headers->get('Content-Disposition')));
        }

        Carbon::setTestNow();
    }
}
