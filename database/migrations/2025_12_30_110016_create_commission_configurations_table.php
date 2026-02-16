<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commission_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('transaction_type_id');
            $table->enum('commission_type', ['fixed', 'percentage', 'tiered']);
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->decimal('percentage_rate', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('transaction_type_id')->references('id')->on('transaction_types')->onDelete('cascade');
            $table->unique(['business_unit_id', 'transaction_type_id'], 'comm_config_bu_tt_unique');
            $table->index('business_unit_id');
            $table->index('transaction_type_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('commission_configurations');
    }
};
