<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\SunuPolicy;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Product catalogue CSV import + Phase 0 import accepting SUNULIFE exports
 * (";" separator, French frequency words, SUNULIFE product codes).
 */
class SunuProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->bu = BusinessUnit::create(['name' => 'SUNU Test', 'slug' => 'sunu-pi-'.uniqid(), 'is_active' => true]);
        $this->admin = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => true]);
        $this->admin->businessUnits()->attach($this->bu->id, ['is_active' => true]);
    }

    protected function upload(string $route, string $csv)
    {
        return $this->actingAs($this->admin)->post(route($route), [
            'file' => UploadedFile::fake()->createWithContent('f.csv', $csv),
        ]);
    }

    protected function product(string $code, ?string $sunulife = null): InsuranceProduct
    {
        return InsuranceProduct::create([
            'business_unit_id' => $this->bu->id, 'code' => $code, 'name_fr' => $code, 'sunulife_product_code' => $sunulife,
            'default_premium_frequency' => 'monthly', 'default_premium_amount' => 1000, 'is_active' => true,
        ]);
    }

    public function test_product_sample_downloads_with_columns(): void
    {
        $res = $this->actingAs($this->admin)->get(route('business.sunu.products.import.sample'));
        $res->assertOk();
        $this->assertStringContainsString(implode(',', \App\Http\Controllers\SunuWebController::PRODUCT_IMPORT_COLUMNS_FR), $res->streamedContent());
        $this->assertStringContainsString('Mensuel', $res->streamedContent());
    }

    public function test_product_import_creates_derives_code_and_updates(): void
    {
        $existing = $this->product('MICRO_RETRAITE');
        $csv = "\xEF\xBB\xBFcode;sunulife_product_code;name_fr;default_premium_frequency;default_premium_amount\r\n"
            .";DCE-EXAZXX.;Capital Études;Annuel;\r\n"
            ."MICRO_RETRAITE;;Micro Épargne Retraite;;2500\r\n"
            ."BAD-CODE;;X;monthly;\r\n"
            .";CPQOT2.;;Mensuel;\r\n"
            ."ENG_ONE;;English freq;quarterly;\r\n";

        $this->upload('business.sunu.products.import', $csv)
            ->assertRedirect(route('business.sunu.products.index'))
            ->assertSessionHas('import_errors', fn ($e) => count($e) === 2);

        $new = InsuranceProduct::where('code', 'DCE_EXAZXX')->first();
        $this->assertNotNull($new);
        $this->assertSame('DCE-EXAZXX.', $new->sunulife_product_code);
        $this->assertSame('annual', $new->default_premium_frequency);
        $this->assertTrue($new->is_active);

        $existing->refresh();
        $this->assertSame('Micro Épargne Retraite', $existing->name_fr);
        $this->assertSame('2500.00', $existing->default_premium_amount);
        $this->assertSame('monthly', $existing->default_premium_frequency, 'empty cell keeps the value');
        $this->assertSame('quarterly', InsuranceProduct::where('code', 'ENG_ONE')->value('default_premium_frequency'), 'English value accepted');
        $this->assertSame(3, InsuranceProduct::where('business_unit_id', $this->bu->id)->count());
    }

    public function test_policy_import_reads_semicolon_french_frequency_and_sunulife_code(): void
    {
        $this->product('CPQOT2', 'CPQOT2.');
        $this->product('REDPXXXXX');
        $csv = "numero_police;code_produit;nom;prenom;telephone;premium_amount;premium_frequency;effective_date;sunulife_policy_id;insured_number\n"
            ."CPQOT2.337Z;CPQOT2.;IZILI;.;22666830000;850;Annuel;2026-08-13;UAVIE-1;223149\n"
            ."CPQOT2.338A;CPQOT2;IZILI;.;22666830000;11750;Unique;2026-08-14;UAVIE-2;223149\n"
            ."REP.39678;REDPXXXXX.;KABORE;DIDIER;22676140015;10000;Mensuel;2023-01-20;UAVIE-3;442859\n"
            ."MIXTEP.351J;REDPXXXXX;OUEDRAOGO;ALI;22670000000;5500;Semestriel;2023-01-20;UAVIE-4;9\n"
            ."BAD.1;REDPXXXXX;OUEDRAOGO;ALI;22670000000;5500;Biennal;2023-01-20;UAVIE-5;9\n"
            ."X.1;NOPE.;A;B;;1;Annuel;;;10\n";

        $this->upload('business.sunu.import.store', $csv)
            ->assertSessionHas('import_errors', fn ($e) => count($e) === 2
                && str_contains($e[0], 'Biennal') && str_contains($e[1], 'NOPE.'));

        $this->assertSame(4, SunuPolicy::where('business_unit_id', $this->bu->id)->count());
        $this->assertSame('semiannual', SunuPolicy::where('policy_number', 'MIXTEP.351J')->value('premium_frequency'));
        $p = SunuPolicy::where('policy_number', 'CPQOT2.337Z')->first();
        $this->assertSame('annual', $p->premium_frequency);
        $this->assertSame('annual', SunuPolicy::where('policy_number', 'CPQOT2.338A')->value('premium_frequency'));
        $this->assertSame('monthly', SunuPolicy::where('policy_number', 'REP.39678')->value('premium_frequency'));
        $izili = Client::where('insured_number', '223149')->get();
        $this->assertCount(1, $izili, 'same insured number = one customer');
        $this->assertSame(2, SunuPolicy::where('client_id', $izili[0]->id)->count());
    }

    public function test_comma_file_still_imports(): void
    {
        $this->product('MICRO_RETRAITE');
        $csv = implode(',', \App\Http\Controllers\SunuWebController::IMPORT_COLUMNS)."\n"
            ."A1,POL-1,MICRO_RETRAITE,KABORE,Awa,22670000001,,,,\n";

        $this->upload('business.sunu.import.store', $csv)->assertSessionHas('import_errors', []);
        $p = SunuPolicy::where('policy_number', 'POL-1')->first();
        $this->assertSame('monthly', $p->premium_frequency, 'empty → product default');
        $this->assertSame('1000.00', (string) $p->premium_amount);
    }

    public function test_products_page_shows_import_and_sample_buttons(): void
    {
        $this->actingAs($this->admin)->get(route('business.sunu.products.index'))
            ->assertOk()
            ->assertSee(route('business.sunu.products.import.sample'))
            ->assertSee(route('business.sunu.products.import'))
            ->assertSee('name="file"', false);
    }

    public function test_policy_sample_is_in_french_and_both_test_files_import(): void
    {
        $sample = $this->actingAs($this->admin)->get(route('business.sunu.import.sample'))->streamedContent();
        $this->assertStringContainsString('Mensuel', $sample);
        $this->assertStringNotContainsString('monthly', $sample);

        if (! is_readable('/cust/SUNU_TEST_1_products.csv') || ! is_readable('/cust/SUNU_TEST_2_customers.csv')) {
            $this->markTestSkipped('SUNU test files not on this machine');
        }
        $this->upload('business.sunu.products.import', file_get_contents('/cust/SUNU_TEST_1_products.csv'))
            ->assertSessionHas('import_errors', []);
        foreach (['DCEIGXX' => 'DCEIGXX.', 'SUPERC2XX' => 'SUPERC2XX.', 'PFMXAZXXX' => 'PFMXAZXXX.', 'DCE_EXAZXX' => 'DCE-EXAZXX.', 'REDPXXXXX' => 'REDPXXXXX.'] as $code => $sl) {
            $this->product($code, $sl); // already live before the test files
        }
        $this->upload('business.sunu.import.store', file_get_contents('/cust/SUNU_TEST_2_customers.csv'))
            ->assertSessionHas('import_errors', []);
        $this->assertSame(100, SunuPolicy::count());
        $this->assertSame(['annual' => 74, 'monthly' => 25, 'semiannual' => 1],
            SunuPolicy::selectRaw('premium_frequency, count(*) n')->groupBy('premium_frequency')->orderBy('premium_frequency')->pluck('n', 'premium_frequency')->map(fn ($n) => (int) $n)->all());
    }

    public function test_french_headers_with_accents_and_english_headers_are_read(): void
    {
        $csv = "Code;Code SUNULIFE;Nom FR;Catégorie;Périodicité;Prime par défaut;Coût de police;Actif\n"
            ."MICRO_A;MA.;Micro A;Retraite;Mensuel;2500;1000;1\n";
        $this->upload('business.sunu.products.import', $csv)->assertSessionHas('import_errors', []);
        $p = InsuranceProduct::where('code', 'MICRO_A')->first();
        $this->assertSame(['MA.', 'Micro A', 'Retraite', 'monthly', '2500.00', '1000.00'],
            [$p->sunulife_product_code, $p->name_fr, $p->category, $p->default_premium_frequency, $p->default_premium_amount, $p->policy_cost_amount]);

        $csv = "N° assuré;Numéro de police;Code produit;Nom;Prénom(s);Téléphone;Montant prime;Périodicité;Date d'effet;ID police SUNULIFE\n"
            ."A-9;POL-FR-1;MA.;KABORE;Awa;22670000009;3000;Semestriel;2026-01-15;SL-1\n";
        $this->upload('business.sunu.import.store', $csv)->assertSessionHas('import_errors', []);
        $pol = SunuPolicy::with('client')->where('policy_number', 'POL-FR-1')->first();
        $this->assertSame(['A-9', 'KABORE', 'Awa', '22670000009', '3000.00', 'semiannual', '2026-01-15', 'SL-1'],
            [$pol->client->insured_number, $pol->client->last_name, $pol->client->first_name, $pol->client->phone_number,
                (string) $pol->premium_amount, $pol->premium_frequency, $pol->effective_date->toDateString(), $pol->sunulife_policy_id]);

        $csv = implode(',', \App\Http\Controllers\SunuWebController::IMPORT_COLUMNS)."\n"
            ."A-10,POL-EN-1,MICRO_A,OUEDRAOGO,Ali,22670000010,4000,quarterly,2026-02-01,\n";
        $this->upload('business.sunu.import.store', $csv)->assertSessionHas('import_errors', []);
        $this->assertSame('quarterly', SunuPolicy::where('policy_number', 'POL-EN-1')->value('premium_frequency'));
    }

    public function test_samples_have_french_headers(): void
    {
        $this->assertStringContainsString(implode(',', \App\Http\Controllers\SunuWebController::IMPORT_COLUMNS_FR),
            $this->actingAs($this->admin)->get(route('business.sunu.import.sample'))->streamedContent());
    }

    public function test_product_import_needs_manage_permission(): void
    {
        $user = User::factory()->create(['current_business_unit_id' => $this->bu->id]);
        $user->businessUnits()->attach($this->bu->id, ['is_active' => true]);
        $this->actingAs($user)->post(route('business.sunu.products.import'), [
            'file' => UploadedFile::fake()->createWithContent('f.csv', "code,name_fr\nX,Y\n"),
        ])->assertForbidden();
        $this->actingAs($user)->get(route('business.sunu.products.import.sample'))->assertForbidden();
    }
}
