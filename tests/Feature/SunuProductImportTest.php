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

    public function test_product_import_keeps_sunu_code_verbatim_and_updates(): void
    {
        $existing = $this->product('MICRO_RETRAITE');
        $csv = "\xEF\xBB\xBFcode;sunulife_product_code;name_fr;default_premium_frequency;default_premium_amount\r\n"
            .";DCE-EXAZXX.;Capital Études;Annuel;\r\n"
            ."MICRO_RETRAITE;;Micro Épargne Retraite;;2500\r\n"
            ."bad code;;X;monthly;\r\n"
            .";CPQOT2.;;Mensuel;\r\n"
            ."ENG_ONE;;English freq;quarterly;\r\n";

        $this->upload('business.sunu.products.import', $csv)
            ->assertRedirect(route('business.sunu.products.index'))
            ->assertSessionHas('import_errors', fn ($e) => count($e) === 2);

        $new = InsuranceProduct::where('code', 'DCE-EXAZXX.')->first();
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
        foreach (['DCEIGXX.', 'SUPERC2XX.', 'PFMXAZXXX.', 'DCE-EXAZXX.', 'REDPXXXXX.'] as $code) {
            $this->product($code, $code); // already live (codes restored by the 2026-11-11 migration)
        }
        $this->upload('business.sunu.import.store', file_get_contents('/cust/SUNU_TEST_2_customers.csv'))
            ->assertSessionHas('import_errors', []);
        $this->assertSame(100, SunuPolicy::count());
        // Every policy points at the product whose code is exactly the code in SUNU's file.
        $rows = array_map('str_getcsv', array_slice(file('/cust/SUNU_TEST_2_customers.csv', FILE_IGNORE_NEW_LINES), 1));
        foreach ($rows as $r) {
            $this->assertSame($r[2], SunuPolicy::with('product')->where('policy_number', $r[1])->first()->product->code);
        }
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

    public function test_product_form_accepts_sunu_codes_with_dot_and_dash(): void
    {
        $base = ['name_fr' => 'X', 'default_premium_frequency' => 'annual', 'is_active' => 1];
        $this->actingAs($this->admin)->post(route('business.sunu.products.store'), $base + ['code' => 'CPQOT2.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('business.sunu.products.store'), $base + ['code' => 'DCE-EXAZXX.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('business.sunu.products.store'), $base + ['code' => 'bad code'])
            ->assertSessionHasErrors('code');
        $p = InsuranceProduct::where('code', 'CPQOT2.')->first();
        $this->actingAs($this->admin)->put(route('business.sunu.products.update', $p->id), array_merge($base, ['code' => 'CPQOT2.', 'name_fr' => 'Renamed']))
            ->assertSessionHasNoErrors();
        $this->assertSame(['CPQOT2.', 'Renamed'], [$p->fresh()->code, $p->fresh()->name_fr]);
        $this->actingAs($this->admin)->get(route('business.sunu.products.edit', $p->id))->assertSee('value="CPQOT2."', false);
    }

    public function test_customer_import_finds_dotted_product_from_old_style_codes(): void
    {
        $this->product('CPQOT2.', 'CPQOT2.');
        $this->product('DCE-EXAZXX.', 'DCE-EXAZXX.');
        $csv = implode(',', \App\Http\Controllers\SunuWebController::IMPORT_COLUMNS_FR)."\n"
            ."1,P-1,CPQOT2.,A,B,,100,Annuel,2026-01-01,\n"
            ."1,P-2,CPQOT2,A,B,,100,Annuel,2026-01-01,\n"
            ."2,P-3,DCE_EXAZXX,C,D,,100,Mensuel,2026-01-01,\n";
        $this->upload('business.sunu.import.store', $csv)->assertSessionHas('import_errors', []);
        $this->assertSame(['P-1' => 'CPQOT2.', 'P-2' => 'CPQOT2.', 'P-3' => 'DCE-EXAZXX.'],
            SunuPolicy::with('product')->orderBy('policy_number')->get()->mapWithKeys(fn ($p) => [$p->policy_number => $p->product->code])->all());
    }

    public function test_restore_migration_puts_original_code_back_and_skips_collisions(): void
    {
        $a = $this->product('DCEIGXX', 'DCEIGXX.');
        $b = $this->product('DCE_EXAZXX', 'DCE-EXAZXX.');
        $c = $this->product('TAKEN', 'CPQOT2.');
        $this->product('CPQOT2.');
        $plain = $this->product('MICRO_QUIETUDE');
        $migration = require base_path('database/migrations/2026_11_11_000000_restore_sunulife_product_codes.php');
        $migration->up();
        $this->assertSame(['DCEIGXX.', 'DCE-EXAZXX.', 'TAKEN', 'MICRO_QUIETUDE'],
            [$a->fresh()->code, $b->fresh()->code, $c->fresh()->code, $plain->fresh()->code]);
        $migration->down();
        $this->assertSame(['DCEIGXX', 'DCE_EXAZXX'], [$a->fresh()->code, $b->fresh()->code]);
    }

    public function test_products_export_round_trips_with_codes_unchanged(): void
    {
        InsuranceProduct::create(['business_unit_id' => $this->bu->id, 'code' => 'CPQOT2.', 'sunulife_product_code' => 'CPQOT2.', 'name_fr' => 'Quiétude',
            'category' => 'Retraite', 'default_premium_frequency' => 'monthly', 'default_premium_amount' => 2500, 'policy_cost_amount' => 1000, 'is_active' => true]);
        $this->product('DCE-EXAZXX.', 'DCE-EXAZXX.')->update(['is_active' => false, 'default_premium_frequency' => 'semiannual']);
        $csv = $this->actingAs($this->admin)->get(route('business.sunu.products.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $flat = str_replace(';', ',', $csv); // ";" in French, "," otherwise (ExportService::csvDelimiter)
        $this->assertStringContainsString('code,code_sunulife,nom_fr', $flat);
        $this->assertStringContainsString('CPQOT2.,CPQOT2.,Quiétude,,Retraite,Mensuel,2500,1000', $flat);
        $this->assertStringContainsString('DCE-EXAZXX.,DCE-EXAZXX.,DCE-EXAZXX.,,,Semestriel', $flat);

        $this->upload('business.sunu.products.import', $csv)->assertSessionHas('import_errors', []);
        $this->assertSame(2, InsuranceProduct::where('business_unit_id', $this->bu->id)->count(), 'nothing duplicated');
        $p = InsuranceProduct::where('code', 'CPQOT2.')->first();
        $this->assertSame(['monthly', '2500.00', '1000.00', true], [$p->default_premium_frequency, $p->default_premium_amount, $p->policy_cost_amount, $p->is_active]);
        $this->assertFalse(InsuranceProduct::where('code', 'DCE-EXAZXX.')->value('is_active'));

        $this->actingAs($this->admin)->get(route('business.sunu.products.export', ['format' => 'excel']))->assertOk();
        $this->actingAs($this->admin)->get(route('business.sunu.products.index'))->assertSee(route('business.sunu.products.export', ['format' => 'csv']));
    }

    public function test_products_export_needs_view_permission(): void
    {
        $user = User::factory()->create(['current_business_unit_id' => $this->bu->id]);
        $user->businessUnits()->attach($this->bu->id, ['is_active' => true]);
        $this->actingAs($user)->get(route('business.sunu.products.export'))->assertForbidden();
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
