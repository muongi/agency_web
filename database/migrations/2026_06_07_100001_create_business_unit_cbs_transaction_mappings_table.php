<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_unit_cbs_transaction_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('transaction_type_id');
            $table->string('http_method', 10)->default('POST');
            $table->string('endpoint_path');
            $table->json('request_mappings')->nullable();
            $table->json('response_mappings')->nullable();
            $table->string('success_path')->nullable();
            $table->string('success_value')->nullable();
            $table->string('error_message_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('business_unit_id', 'bu_cbs_tx_map_bu_fk')
                ->references('id')
                ->on('business_units')
                ->onDelete('cascade');

            $table->foreign('transaction_type_id', 'bu_cbs_tx_map_tt_fk')
                ->references('id')
                ->on('transaction_types')
                ->onDelete('cascade');

            $table->unique(
                ['business_unit_id', 'transaction_type_id'],
                'bu_cbs_tx_mapping_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_unit_cbs_transaction_mappings');
    }
};
