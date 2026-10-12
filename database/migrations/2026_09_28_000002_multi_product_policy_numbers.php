<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SUNU: one customer may hold several products under the same policy number.
 * A policy (and a proposal) is now identified by (number, product), not by the number alone.
 * The new unique indexes are added before the old ones are dropped (the business_unit_id
 * foreign key needs an index starting with that column at all times).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunu_policies', function (Blueprint $table) {
            $table->unique(['business_unit_id', 'policy_number', 'insurance_product_id'], 'sunu_policies_bu_number_product_unique');
        });
        Schema::table('sunu_policies', function (Blueprint $table) {
            $table->dropUnique(['business_unit_id', 'policy_number']);
        });

        Schema::table('sunu_proposals', function (Blueprint $table) {
            $table->unique(['business_unit_id', 'proposal_number', 'insurance_product_id'], 'sunu_proposals_bu_number_product_unique');
            $table->index(['business_unit_id', 'sunulife_policy_number'], 'sunu_proposals_bu_existing_policy_index');
            $table->json('identity_mismatch')->nullable()->after('sunulife_policy_number');
        });
        Schema::table('sunu_proposals', function (Blueprint $table) {
            $table->dropUnique(['business_unit_id', 'proposal_number']);
        });
    }

    /** Fails if a number is already used for two products (expected: data must be merged first). */
    public function down(): void
    {
        Schema::table('sunu_proposals', function (Blueprint $table) {
            $table->unique(['business_unit_id', 'proposal_number']);
        });
        Schema::table('sunu_proposals', function (Blueprint $table) {
            $table->dropUnique('sunu_proposals_bu_number_product_unique');
            $table->dropIndex('sunu_proposals_bu_existing_policy_index');
            $table->dropColumn('identity_mismatch');
        });

        Schema::table('sunu_policies', function (Blueprint $table) {
            $table->unique(['business_unit_id', 'policy_number']);
        });
        Schema::table('sunu_policies', function (Blueprint $table) {
            $table->dropUnique('sunu_policies_bu_number_product_unique');
        });
    }
};
