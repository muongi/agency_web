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
        Schema::create('transaction_cost_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('transaction_type_id');
            $table->enum('cost_type', ['fixed', 'percentage', 'tiered_fixed', 'tiered_percentage']);
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->decimal('percentage_rate', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('transaction_type_id')->references('id')->on('transaction_types')->onDelete('cascade');
            $table->unique(['business_unit_id', 'transaction_type_id'], 'trans_cost_bu_tt_unique');
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
        Schema::dropIfExists('transaction_cost_configurations');
    }
};

