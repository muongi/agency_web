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
        Schema::create('commission_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commission_configuration_id');
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->decimal('rate', 5, 2)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('commission_configuration_id')->references('id')->on('commission_configurations')->onDelete('cascade');
            $table->index('commission_configuration_id');
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
        Schema::dropIfExists('commission_tiers');
    }
};
