<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\CnssContribution;
use App\Models\CnssVoluntaryEnrollment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CnssMemberWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_forbidden_without_permission(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Web CNSS BU',
            'slug' => 'web-cnss-bu-'.uniqid(),
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
            ->get(route('business.cnss.members.index'))
            ->assertForbidden();
    }

    public function test_index_and_show_ok_with_permissions(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Web CNSS BU 2',
            'slug' => 'web-cnss-bu2-'.uniqid(),
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
        $permShow = Permission::firstOrCreate(
            ['slug' => 'view-cnss-member'],
            [
                'name' => 'View CNSS Member',
                'description' => 'Test',
                'group' => 'CNSS',
            ]
        );

        $role = Role::create([
            'name' => 'CNSS reader',
            'business_unit_id' => $bu->id,
        ]);
        $role->permissions()->sync([$permList->id, $permShow->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $bu->id,
        ]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $enrollment = CnssVoluntaryEnrollment::create([
            'business_unit_id' => $bu->id,
            'status' => CnssVoluntaryEnrollment::STATUS_PENDING,
            'voluntary_insured_number' => 'VINS00000001',
            'cnss_registration_affiliation_number' => 'AFF00000001',
            'form_data' => ['identification' => ['last_name' => 'Test']],
        ]);

        CnssContribution::create([
            'business_unit_id' => $bu->id,
            'cnss_voluntary_enrollment_id' => $enrollment->id,
            'voluntary_insured_number' => $enrollment->voluntary_insured_number,
            'affiliation_number' => null,
            'amount' => 100.00,
            'reference_period' => '2026-01',
            'payment_frequency' => 'monthly',
            'mode' => CnssContribution::MODE_RECORD,
            'reference' => 'REF-'.uniqid(),
        ]);

        $this->actingAs($user)
            ->get(route('business.cnss.members.index'))
            ->assertOk()
            ->assertSee('VINS00000001', false);

        $this->actingAs($user)
            ->get(route('business.cnss.members.show', $enrollment->id))
            ->assertOk()
            ->assertSee('100.00', false);
    }

    public function test_show_forbidden_with_list_permission_only(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Web CNSS BU 3',
            'slug' => 'web-cnss-bu3-'.uniqid(),
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
            'name' => 'CNSS list only',
            'business_unit_id' => $bu->id,
        ]);
        $role->permissions()->sync([$permList->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $bu->id,
        ]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $enrollment = CnssVoluntaryEnrollment::create([
            'business_unit_id' => $bu->id,
            'status' => CnssVoluntaryEnrollment::STATUS_PENDING,
            'voluntary_insured_number' => 'VINS00000002',
            'cnss_registration_affiliation_number' => 'AFF00000002',
            'form_data' => [],
        ]);

        $this->actingAs($user)
            ->get(route('business.cnss.members.show', $enrollment->id))
            ->assertForbidden();
    }

    public function test_enrollment_document_streams_with_permission(): void
    {
        Storage::fake('local');

        $bu = BusinessUnit::create([
            'name' => 'Web CNSS BU docs',
            'slug' => 'web-cnss-bu-docs-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $permShow = Permission::firstOrCreate(
            ['slug' => 'view-cnss-member'],
            [
                'name' => 'View CNSS Member',
                'description' => 'Test',
                'group' => 'CNSS',
            ]
        );

        $role = Role::create([
            'name' => 'CNSS doc viewer',
            'business_unit_id' => $bu->id,
        ]);
        $role->permissions()->sync([$permShow->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $bu->id,
        ]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $enrollment = CnssVoluntaryEnrollment::create([
            'business_unit_id' => $bu->id,
            'status' => CnssVoluntaryEnrollment::STATUS_APPROVED,
            'voluntary_insured_number' => 'VINSDOC00001',
            'cnss_registration_affiliation_number' => 'AFFDOC00001',
            'form_data' => [],
        ]);

        $path = 'cnss/enrollments/'.$enrollment->id.'/id.jpg';
        $backPath = 'cnss/enrollments/'.$enrollment->id.'/id_back.jpg';
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=');
        Storage::disk('local')->put($path, $jpeg);
        Storage::disk('local')->put($backPath, $jpeg);
        $enrollment->update([
            'id_document_path' => $path,
            'id_document_back_path' => $backPath,
        ]);

        $response = $this->actingAs($user)
            ->get(route('business.cnss.members.document', [$enrollment->id, 'id_document']));

        $response->assertOk();
        $this->assertStringContainsString('image/jpeg', (string) $response->headers->get('Content-Type'));

        $this->actingAs($user)
            ->get(route('business.cnss.members.document', [$enrollment->id, 'id_document_back']))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('business.cnss.members.show', $enrollment->id))
            ->assertOk()
            ->assertSee(route('business.cnss.members.document', [$enrollment->id, 'id_document']), false)
            ->assertSee(route('business.cnss.members.document', [$enrollment->id, 'id_document_back']), false);
    }

    public function test_enrollment_document_forbidden_without_view_member_permission(): void
    {
        Storage::fake('local');

        $bu = BusinessUnit::create([
            'name' => 'Web CNSS BU docs 2',
            'slug' => 'web-cnss-bu-docs2-'.uniqid(),
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
            'name' => 'CNSS list only docs',
            'business_unit_id' => $bu->id,
        ]);
        $role->permissions()->sync([$permList->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $bu->id,
        ]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $enrollment = CnssVoluntaryEnrollment::create([
            'business_unit_id' => $bu->id,
            'status' => CnssVoluntaryEnrollment::STATUS_APPROVED,
            'voluntary_insured_number' => 'VINSDOC00002',
            'cnss_registration_affiliation_number' => 'AFFDOC00002',
            'form_data' => [],
            'id_document_path' => 'cnss/enrollments/1/id.jpg',
        ]);
        Storage::disk('local')->put($enrollment->id_document_path, 'fake-image');

        $this->actingAs($user)
            ->get(route('business.cnss.members.document', [$enrollment->id, 'id_document']))
            ->assertForbidden();
    }

    public function test_enrollment_document_not_found_when_path_missing(): void
    {
        Storage::fake('local');

        $bu = BusinessUnit::create([
            'name' => 'Web CNSS BU docs 3',
            'slug' => 'web-cnss-bu-docs3-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $permShow = Permission::firstOrCreate(
            ['slug' => 'view-cnss-member'],
            [
                'name' => 'View CNSS Member',
                'description' => 'Test',
                'group' => 'CNSS',
            ]
        );

        $role = Role::create([
            'name' => 'CNSS doc viewer 3',
            'business_unit_id' => $bu->id,
        ]);
        $role->permissions()->sync([$permShow->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $bu->id,
        ]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $enrollment = CnssVoluntaryEnrollment::create([
            'business_unit_id' => $bu->id,
            'status' => CnssVoluntaryEnrollment::STATUS_APPROVED,
            'voluntary_insured_number' => 'VINSDOC00003',
            'cnss_registration_affiliation_number' => 'AFFDOC00003',
            'form_data' => [],
        ]);

        $this->actingAs($user)
            ->get(route('business.cnss.members.document', [$enrollment->id, 'id_document']))
            ->assertNotFound();
    }

    public function test_edit_and_update_forbidden_without_edit_permission(): void
    {
        [$bu, $user, $enrollment] = $this->createCnssReaderUser();

        $this->actingAs($user)
            ->get(route('business.cnss.members.edit', $enrollment->id))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('business.cnss.members.update', $enrollment->id), $this->validEnrollmentUpdatePayload())
            ->assertForbidden();
    }

    public function test_update_ok_with_edit_permission(): void
    {
        [$bu, $user, $enrollment] = $this->createCnssEditorUser();

        $this->actingAs($user)
            ->put(route('business.cnss.members.update', $enrollment->id), $this->validEnrollmentUpdatePayload([
                'identification' => [
                    'last_name' => 'UpdatedLast',
                    'first_names' => 'UpdatedFirst',
                    'gender' => 'M',
                    'date_of_birth' => '1990-05-15',
                    'phone' => '+22670123456',
                ],
            ]))
            ->assertRedirect(route('business.cnss.members.show', $enrollment->id));

        $enrollment->refresh();
        $this->assertSame('UpdatedLast', $enrollment->last_name);
        $this->assertSame('UpdatedFirst', $enrollment->first_names);
        $this->assertSame('approved', $enrollment->status);
    }

    public function test_destroy_forbidden_without_delete_permission(): void
    {
        [, $user, $enrollment] = $this->createCnssReaderUser();

        $this->actingAs($user)
            ->delete(route('business.cnss.members.destroy', $enrollment->id))
            ->assertForbidden();
    }

    public function test_destroy_blocked_when_contributions_exist(): void
    {
        [, $user, $enrollment] = $this->createCnssDeleterUser(withContribution: true);

        $this->actingAs($user)
            ->from(route('business.cnss.members.show', $enrollment->id))
            ->delete(route('business.cnss.members.destroy', $enrollment->id))
            ->assertRedirect(route('business.cnss.members.show', $enrollment->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('cnss_voluntary_enrollments', ['id' => $enrollment->id]);
    }

    public function test_destroy_ok_when_no_contributions(): void
    {
        [, $user, $enrollment] = $this->createCnssDeleterUser(withContribution: false);

        $this->actingAs($user)
            ->delete(route('business.cnss.members.destroy', $enrollment->id))
            ->assertRedirect(route('business.cnss.members.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('cnss_voluntary_enrollments', ['id' => $enrollment->id]);
    }

    public function test_members_export_requires_permission(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Export BU',
            'slug' => 'export-bu-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $role = Role::create(['name' => 'No CNSS', 'business_unit_id' => $bu->id]);
        $user = User::factory()->create(['current_business_unit_id' => $bu->id]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('business.cnss.members.export', ['format' => 'csv']))
            ->assertForbidden();
    }

    public function test_members_export_csv_excel_pdf_with_filters(): void
    {
        [, $user] = $this->createCnssReaderUser();

        CnssVoluntaryEnrollment::create([
            'business_unit_id' => $user->current_business_unit_id,
            'status' => CnssVoluntaryEnrollment::STATUS_PENDING,
            'voluntary_insured_number' => 'VINSEXPORT01',
            'cnss_registration_affiliation_number' => 'AFFEXPORT01',
            'last_name' => 'ExportMe',
            'form_data' => [],
        ]);

        CnssVoluntaryEnrollment::create([
            'business_unit_id' => $user->current_business_unit_id,
            'status' => CnssVoluntaryEnrollment::STATUS_APPROVED,
            'voluntary_insured_number' => 'VINSEXPORT02',
            'cnss_registration_affiliation_number' => 'AFFEXPORT02',
            'last_name' => 'Other',
            'form_data' => [],
        ]);

        foreach (['csv', 'excel', 'pdf'] as $format) {
            $response = $this->actingAs($user)
                ->get(route('business.cnss.members.export', [
                    'format' => $format,
                    'status' => 'pending',
                    'search' => 'ExportMe',
                ]));

            $response->assertSuccessful();
            $this->assertStringContainsString('attachment', strtolower((string) $response->headers->get('Content-Disposition')));
        }
    }

    /**
     * @return array{0: BusinessUnit, 1: User, 2: CnssVoluntaryEnrollment}
     */
    protected function createCnssReaderUser(): array
    {
        $bu = BusinessUnit::create([
            'name' => 'Reader BU',
            'slug' => 'reader-bu-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => true,
        ]);

        $permList = Permission::firstOrCreate(
            ['slug' => 'view-cnss-members'],
            ['name' => 'View CNSS Members', 'description' => 'Test', 'group' => 'CNSS']
        );
        $permShow = Permission::firstOrCreate(
            ['slug' => 'view-cnss-member'],
            ['name' => 'View CNSS Member', 'description' => 'Test', 'group' => 'CNSS']
        );

        $role = Role::create(['name' => 'CNSS reader', 'business_unit_id' => $bu->id]);
        $role->permissions()->sync([$permList->id, $permShow->id]);

        $user = User::factory()->create(['current_business_unit_id' => $bu->id]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $enrollment = CnssVoluntaryEnrollment::create([
            'business_unit_id' => $bu->id,
            'status' => CnssVoluntaryEnrollment::STATUS_PENDING,
            'voluntary_insured_number' => 'VINSREAD'.substr(uniqid(), -6),
            'cnss_registration_affiliation_number' => 'AFFREAD'.substr(uniqid(), -6),
            'form_data' => [
                'identification' => [
                    'last_name' => 'Original',
                    'first_names' => 'Member',
                    'gender' => 'F',
                    'date_of_birth' => '1985-01-01',
                    'phone' => '+22670000001',
                ],
                'other_information' => [
                    'branch_of_activity' => 'services',
                    'insurance_start_date' => '2026-01-01',
                    'payment_frequency' => 'monthly',
                    'insurance_type_art' => '4',
                ],
                'beneficiary' => [],
            ],
        ]);

        return [$bu, $user, $enrollment];
    }

    /**
     * @return array{0: BusinessUnit, 1: User, 2: CnssVoluntaryEnrollment}
     */
    protected function createCnssEditorUser(): array
    {
        [$bu, $user, $enrollment] = $this->createCnssReaderUser();

        $permEdit = Permission::firstOrCreate(
            ['slug' => 'edit-cnss-member'],
            ['name' => 'Edit CNSS Member', 'description' => 'Test', 'group' => 'CNSS']
        );

        $role = Role::where('business_unit_id', $bu->id)->first();
        $role->permissions()->attach($permEdit->id);

        return [$bu, $user, $enrollment];
    }

    /**
     * @return array{0: BusinessUnit, 1: User, 2: CnssVoluntaryEnrollment}
     */
    protected function createCnssDeleterUser(bool $withContribution): array
    {
        [$bu, $user, $enrollment] = $this->createCnssReaderUser();

        $permDelete = Permission::firstOrCreate(
            ['slug' => 'delete-cnss-member'],
            ['name' => 'Delete CNSS Member', 'description' => 'Test', 'group' => 'CNSS']
        );

        $role = Role::where('business_unit_id', $bu->id)->first();
        $role->permissions()->attach($permDelete->id);

        if ($withContribution) {
            CnssContribution::create([
                'business_unit_id' => $bu->id,
                'cnss_voluntary_enrollment_id' => $enrollment->id,
                'voluntary_insured_number' => $enrollment->voluntary_insured_number,
                'affiliation_number' => $enrollment->cnss_registration_affiliation_number,
                'amount' => 50.00,
                'reference_period' => '2026-06',
                'payment_frequency' => 'monthly',
                'mode' => CnssContribution::MODE_RECORD,
                'reference' => 'REF-DEL-'.uniqid(),
            ]);
        }

        return [$bu, $user, $enrollment];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validEnrollmentUpdatePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'status' => 'approved',
            'identification' => [
                'last_name' => 'Original',
                'first_names' => 'Member',
                'gender' => 'F',
                'date_of_birth' => '1985-01-01',
                'phone' => '+22670000001',
            ],
            'other_information' => [
                'branch_of_activity' => 'services',
                'insurance_start_date' => '2026-01-01',
                'payment_frequency' => 'monthly',
                'insurance_type_art' => '4',
            ],
            'beneficiary' => [],
        ], $overrides);
    }

    public function test_index_forbidden_when_cnss_module_disabled(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'CNSS Disabled BU',
            'slug' => 'cnss-disabled-bu-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => false,
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
            'name' => 'CNSS reader',
            'business_unit_id' => $bu->id,
        ]);
        $role->permissions()->sync([$permList->id]);

        $user = User::factory()->create([
            'current_business_unit_id' => $bu->id,
        ]);
        $user->businessUnits()->attach($bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('business.cnss.members.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_enable_cnss_module_on_business_unit(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Toggle CNSS BU',
            'slug' => 'toggle-cnss-bu-'.uniqid(),
            'is_active' => true,
            'cnss_enabled' => false,
        ]);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->put(route('superadmin.business-units.update', $bu->id), [
            'name' => $bu->name,
            'cnss_enabled' => '1',
        ])->assertRedirect();

        $bu->refresh();
        $this->assertTrue($bu->cnss_enabled);
    }
}
