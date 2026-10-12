<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_products', function (Blueprint $table) {
            $table->unsignedSmallInteger('display_order')->default(0)->after('code');
            $table->string('visa_number', 191)->nullable()->after('name_en');
            $table->string('category', 64)->nullable()->after('visa_number');
            $table->text('guarantees_description')->nullable()->after('category');
            $table->index(['business_unit_id', 'display_order'], 'insurance_products_bu_order_index');
        });

        // Policy fees are not agreed for every product yet, so allow blank.
        Schema::table('insurance_products', function (Blueprint $table) {
            $table->decimal('policy_cost_amount', 15, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('insurance_products', function (Blueprint $table) {
            $table->decimal('policy_cost_amount', 15, 2)->default(0)->change();
        });

        Schema::table('insurance_products', function (Blueprint $table) {
            $table->dropIndex('insurance_products_bu_order_index');
            $table->dropColumn(['display_order', 'visa_number', 'category', 'guarantees_description']);
        });
    }
};
