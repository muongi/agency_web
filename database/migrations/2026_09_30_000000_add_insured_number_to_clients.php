<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SUNU: the INSURED number (N° assuré) identifies the person and groups all their products.
 * Typed by the back office when a proposal is accepted; unique per business unit.
 * Each product keeps its own policy number (sunu_policies.policy_number). No backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('insured_number', 64)->nullable()->after('business_unit_id');
            $table->unique(['business_unit_id', 'insured_number'], 'clients_bu_insured_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_bu_insured_number_unique');
            $table->dropColumn('insured_number');
        });
    }
};
