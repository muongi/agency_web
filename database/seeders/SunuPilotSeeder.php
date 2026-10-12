<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\CollectorBalance;
use App\Models\CollectorCap;
use App\Models\InsuranceProduct;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class SunuPilotSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SunuSuperAdminSeeder::class);
        $this->call(PermissionsSeeder::class);

        $bu = BusinessUnit::updateOrCreate(
            ['slug' => 'sunu-assurances-vie-bf'],
            [
                'name' => 'SUNU Assurances Vie Burkina Faso',
                'description' => 'Collecte de primes micro-assurance via TPE',
                'email' => 'ops@sunu.bf',
                'phone' => '+22600000000',
                'is_active' => true,
                'cnss_enabled' => false,
                'logo' => $this->seedLogo(),
            ]
        );

        $admin = User::where('email', SunuSuperAdminSeeder::EMAIL)->first();
        if ($admin && ! $admin->businessUnits()->where('business_units.id', $bu->id)->exists()) {
            $admin->businessUnits()->attach($bu->id, ['is_active' => true]);
        }

        foreach ($this->catalogue() as $p) {
            InsuranceProduct::updateOrCreate(
                ['business_unit_id' => $bu->id, 'code' => $p['code']],
                array_merge($p, [
                    'family' => 'micro-insurance',
                    'default_premium_frequency' => 'monthly',
                    'is_active' => true,
                ])
            );
        }

        $agent = Agent::updateOrCreate(
            ['business_unit_id' => $bu->id, 'username' => 'collector1@sunu.bf'],
            [
                'first_name' => 'Collecteur',
                'second_name' => 'SUNU',
                'email' => 'collector1@sunu.bf',
                'phone_number' => '+22670000001',
                'id_passport' => 'BF-SUNU-001',
                'password' => Hash::make('SunuPilot2026!'),
                'is_first_time_pin' => false,
            ]
        );

        $terminal = Terminal::updateOrCreate(
            ['business_unit_id' => $bu->id, 'serial_number' => 'SUNU-TPE-001'],
            [
                'device_alias' => 'Pilot TPE 1',
                'simcard_number' => '0000000000',
                'model_make' => 'Telpo',
            ]
        );

        if (! $agent->terminals()->where('terminals.id', $terminal->id)->exists()) {
            $agent->terminals()->attach($terminal->id, [
                'assigned_at' => now(),
            ]);
        }

        CollectorCap::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'cap_amount' => 500000,
                'warning_threshold_pct' => 80,
                'currency' => 'XOF',
                'is_active' => true,
            ]
        );
        CollectorBalance::updateOrCreate(
            ['agent_id' => $agent->id],
            ['uncollected_amount' => 0, 'is_blocked' => false]
        );

        $this->command?->info('SUNU pilot seeded.');
        $this->command?->info("BU id={$bu->id} slug={$bu->slug}");
        $this->command?->info('Agent login: collector1@sunu.bf / SunuPilot2026!');
        $this->command?->info('Terminal serial: SUNU-TPE-001');
        $this->command?->info('Web admin: '.SunuSuperAdminSeeder::EMAIL.' / 123456789');
    }

    /**
     * Products registered with the DGTCP visas supplied by SUNU Vie.
     * Premium amounts are only pre-filled where the pilot already agreed them;
     * the rest are left blank for the back office to complete.
     */
    private function catalogue(): array
    {
        return [
            [
                'code' => 'MICRO_EPARGNE_RETRAITE',
                'name_fr' => 'Micro Épargne Retraite',
                'name_en' => 'Micro Retirement Savings',
                'display_order' => 1,
                'visa_number' => '2021-08/MINIFID/SG/DGTCP/DA/SUNU Vie',
                'category' => 'Retraite',
                'default_premium_amount' => 5000,
                'policy_cost_amount' => 1000,
                'guarantees_description' => "Contrat individuel de micro-assurance sur la vie, destiné aux personnes à faible revenu. "
                    ."À terme : l'assuré reçoit le capital constitué pour sa retraite. "
                    ."Avant terme : en cas de décès ou d'invalidité totale et définitive, le solde du compte est versé aux bénéficiaires désignés ou, à défaut, aux ayants droit. "
                    ."Suivant la réglementation en microassurance et le seuil de prime en microassurance, le règlement effectué en cas de rachat est au moins égal au cumul des primes versées.",
            ],
            [
                'code' => 'MIXTE_PLUS_HORIZON',
                'name_fr' => 'Mixte Plus Horizon',
                'name_en' => 'Mixte Plus Horizon',
                'display_order' => 2,
                'visa_number' => '2025-01/MEF/SG/DGTCP/DA/SUNU-Vie',
                'category' => 'Projet',
                'default_premium_amount' => null,
                'policy_cost_amount' => null,
                'guarantees_description' => "Garanties principales obligatoires : "
                    ."au terme du contrat et en cas de vie, versement au bénéficiaire d'un capital dont le montant est égal à la somme capitalisée de la prime « Épargne » nette de frais ; "
                    ."en cas de décès ou d'invalidité totale et définitive de l'assuré avant le terme, versement du capital décès défini aux Conditions Particulières du contrat ; "
                    ."en cas de fracture d'un membre, versement du capital défini aux Conditions Particulières du contrat ; "
                    ."en cas de maladie grave et redoutée, versement du capital défini aux Conditions Particulières du contrat.",
            ],
            [
                'code' => 'RENTE_EDUCATION_PLUS',
                'name_fr' => 'Rente Éducation Plus',
                'name_en' => 'Education Annuity Plus',
                'display_order' => 3,
                'visa_number' => '2022-07/MEFP/SG/DGTCP/DA/SUNU-Vie',
                'category' => 'Education',
                'default_premium_amount' => null,
                'policy_cost_amount' => null,
                'guarantees_description' => "En cas de vie de l'assuré à la fin de la période de cotisation (différé), une rente annuelle est versée chaque année jusqu'au terme du contrat : "
                    ."le premier versement intervient un an après la fin du différé et le dernier à l'échéance du contrat, soit 5 rentes versées. "
                    ."En cas de décès de l'assuré pendant la période du différé : un capital immédiat égal à 50 % d'une année de rente est versé au bénéficiaire ; "
                    ."une rente annuelle égale à la valeur de la rente prévue est versée jusqu'à la fin de la période du différé, à compter de l'anniversaire du contrat suivant le décès ; "
                    ."à l'issue du différé, la rente annuelle complète continue d'être versée jusqu'au terme du contrat. "
                    ."En cas d'invalidité totale et définitive de l'assuré, les cotisations cessent d'être dues et le versement de la rente prévue en cas de décès commence immédiatement dès la reconnaissance de l'invalidité, sans attendre la fin de la période du différé.",
            ],
            [
                'code' => 'MICRO_QUIETUDE',
                'name_fr' => 'Micro Quiétude',
                'name_en' => 'Micro Quietude',
                'display_order' => 4,
                'visa_number' => '2022-14/MEFP/SG/DGTCP/DA/SUNU-Vie',
                'category' => 'Funérailles',
                'default_premium_amount' => 2000,
                'policy_cost_amount' => 500,
                'guarantees_description' => "Le contrat est proposé en formule individuelle ou familiale afin de permettre à l'assuré (à faible revenu) de protéger non seulement sa propre personne, "
                    ."mais également ses proches (conjoint, enfants, ascendants et beaux-parents) en cas de décès. "
                    ."L'assureur garantit le versement d'un capital destiné à couvrir les frais d'obsèques de toute personne assurée. "
                    ."Un délai de carence de 90 jours s'applique à compter de la date d'effet du contrat : durant cette période, seuls les décès consécutifs à un accident ou à une agression ouvrent droit au versement des prestations garanties.",
            ],
        ];
    }

    /**
     * Publish the SUNU logo into the public disk and return its relative path,
     * or null when the packaged asset is missing.
     */
    private function seedLogo(): ?string
    {
        $source = public_path('images/sunu-logo.png');
        if (! File::exists($source)) {
            return null;
        }

        $relative = 'business-units/sunu-logo.png';
        $target = storage_path('app/public/'.$relative);
        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);

        return $relative;
    }
}
