<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voluntary and affiliation numbers are allocated per business_unit_id
     * (see CnssIdentifierService). Global uniqueness on these columns caused
     * duplicate-key errors when a second BU issued its first VINS00000001.
     */
    public function up(): void
    {
        Schema::table('cnss_voluntary_enrollments', function (Blueprint $table) {
            $table->dropUnique('cnss_ve_voluntary_insured_uq');
            $table->dropUnique('cnss_ve_reg_affil_uq');
        });

        Schema::table('cnss_voluntary_enrollments', function (Blueprint $table) {
            $table->unique(
                ['business_unit_id', 'voluntary_insured_number'],
                'cnss_ve_bu_vol_uq'
            );
            $table->unique(
                ['business_unit_id', 'cnss_registration_affiliation_number'],
                'cnss_ve_bu_aff_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cnss_voluntary_enrollments', function (Blueprint $table) {
            $table->dropUnique('cnss_ve_bu_vol_uq');
            $table->dropUnique('cnss_ve_bu_aff_uq');
        });

        Schema::table('cnss_voluntary_enrollments', function (Blueprint $table) {
            $table->unique('voluntary_insured_number', 'cnss_ve_voluntary_insured_uq');
            $table->unique('cnss_registration_affiliation_number', 'cnss_ve_reg_affil_uq');
        });
    }
};
