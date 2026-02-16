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
        Schema::create('transaction_cost_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_cost_configuration_id');
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->decimal('rate', 5, 2)->nullable(); // For tiered_percentage
            $table->decimal('amount', 15, 2)->nullable(); // For tiered_fixed
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('transaction_cost_configuration_id')->references('id')->on('transaction_cost_configurations')->onDelete('cascade');
            $table->index('transaction_cost_configuration_id');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_cost_tiers');
    }
};

