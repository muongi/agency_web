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
        Schema::create('card_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id')->unique();
            $table->string('card_prefix');
            $table->unsignedTinyInteger('auto_increment_length')->default(6);
            $table->unsignedTinyInteger('default_expiration_years')->default(3);
            $table->decimal('default_daily_transaction_limit', 15, 2)->nullable();
            $table->decimal('default_daily_withdrawal_limit', 15, 2)->nullable();
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
        Schema::dropIfExists('card_settings');
    }
};
