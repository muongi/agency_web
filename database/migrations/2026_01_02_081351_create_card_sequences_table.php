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
        Schema::create('card_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id')->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('card_sequences');
    }
};
