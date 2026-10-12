<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Card;
use App\Models\Client;
use App\Models\InsuranceProduct;
use App\Models\Role;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exports follow the selected language; French (default, Burkina Faso) gives
 * French headings and values, ";" separator and decimal comma for Excel FR.
 */
class SunuExportLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessUnit $bu;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sunu.core_banking' => false]);

        $this->bu = BusinessUnit::create(['name' => 'SUNU Test', 'slug' => 'sunu-exp-'.uniqid(), 'is_active' => true]);
        $role = Role::create(['name' => 'SUNU admin', 'business_unit_id' => $this->bu->id]);
        $this->user = User::factory()->create(['current_business_unit_id' => $this->bu->id, 'is_super_admin' => true]);
        $this->user->businessUnits()->attach($this->bu->id, ['role_id' => $role->id, 'is_active' => true]);

        $product = InsuranceProduct::create([
            'business_unit_id' => $this->bu->id, 'code' => 'MICRO_RETRAITE', 'name_fr' => 'Micro Retraite', 'name_en' => 'Micro Retirement',
            'category' => 'micro-insurance', 'default_premium_frequency' => 'monthly', 'is_active' => true,
        ]);
        $client = Client::create(['business_unit_id' => $this->bu->id, 'last_name' => 'OUEDRAOGO', 'first_name' => 'Awa', 'phone_number' => '+22670000001']);
        $agent = Agent::create([
            'business_unit_id' => $this->bu->id, 'first_name' => 'Issa', 'second_name' => 'Collecteur', 'email' => 'issa@test.local',
            'username' => 'issa', 'phone_number' => '+22670000002', 'id_passport' => 'ID-ISSA', 'password' => Hash::make('secret123'),
        ]);
        $policy = SunuPolicy::create([
            'business_unit_id' => $this->bu->id, 'client_id' => $client->id, 'insurance_product_id' => $product->id,
            'policy_number' => 'POL-1', 'status' => SunuPolicy::STATUS_ACTIVE, 'premium_amount' => 2500, 'premium_frequency' => 'monthly',
            'source' => 'import', 'next_due_date' => now()->subDays(5)->toDateString(),
        ]);
        SunuPremiumCollection::create([
            'business_unit_id' => $this->bu->id, 'policy_id' => $policy->id, 'agent_id' => $agent->id, 'amount' => 2500.5,
            'collection_kind' => 'recurring_premium', 'payment_instrument' => 'cash', 'reference' => 'REF-1', 'collected_at' => now(),
        ]);
        Card::create([
            'business_unit_id' => $this->bu->id, 'card_number' => 'C-1', 'card_serial' => 'AAAA0001', 'prefix' => 'S',
            'status' => 'blocked', 'expiration_date' => now()->addYears(3),
        ]);
    }

    protected function csv(string $route, array $params = [], string $lang = 'fr'): string
    {
        return $this->actingAs($this->user)->withSession(['locale' => $lang])->get(route($route, $params))->assertOk()->streamedContent();
    }

    public function test_premiums_list_export_is_french(): void
    {
        $csv = $this->csv('business.sunu.premiums.export');

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Référence;Date;Type;Montant;"N° assuré";Police', $csv);
        $this->assertStringContainsString('Espèces', $csv);
        $this->assertStringContainsString('2500,5', $csv);
        $this->assertStringNotContainsString('Collected At', $csv);
    }

    public function test_premiums_list_export_is_english_when_english_is_selected(): void
    {
        $csv = $this->csv('business.sunu.premiums.export', [], 'en');

        $this->assertStringContainsString('Reference,Date,Type,Amount,"Insured no.",Policy', $csv);
        $this->assertStringContainsString('Cash', $csv);
        $this->assertStringContainsString('2500.5', $csv);
    }

    public function test_report_exports_are_french(): void
    {
        $this->assertStringContainsString('Agent;Encaissements;"Primes encaissées"', $this->csv('business.sunu.reports.reconciliation', ['export' => 'csv']));

        $overdue = $this->csv('business.sunu.reports.subscriptions', ['export' => 'csv']);
        $this->assertStringContainsString('Échéance', $overdue);
        $this->assertStringContainsString(';Active', $overdue);

        $cards = $this->csv('business.sunu.reports.cards', ['export' => 'csv']);
        $this->assertStringContainsString('"N° carte";"UID carte";Client;Statut', $cards);

        $cardsEn = $this->csv('business.sunu.reports.cards', ['export' => 'csv'], 'en');
        $this->assertStringContainsString('"Card number","Card UID",Client,Status', $cardsEn);
    }

    public function test_cards_clients_agents_terminals_exports_are_french(): void
    {
        $this->assertStringContainsString(';Bloqué;', $this->csv('cards.export'));
        $this->assertStringContainsString('Nom;E-mail;Téléphone', $this->csv('clients.export'));
        $this->assertStringContainsString("Nom;\"Nom d'utilisateur\"", $this->csv('agents.export'));
        $this->assertStringContainsString('"Numéro de série"', $this->csv('terminals.export'));
    }

    public function test_default_language_is_french(): void
    {
        // no session language, no user preference, browser sends no known language
        $csv = $this->actingAs($this->user)->withHeaders(['Accept-Language' => ''])
            ->get(route('business.sunu.premiums.export'))->streamedContent();

        $this->assertStringContainsString('Référence;Date', $csv);
    }
}
