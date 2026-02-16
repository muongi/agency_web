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
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('card_number')->nullable();
            $table->string('account_number')->nullable();
            $table->string('phone_number');
            $table->string('otp_code', 5);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_used')->default(false);
            $table->string('purpose')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('set null');
            
            $table->index('otp_code');
            $table->index('phone_number');
            $table->index('expires_at');
            $table->index('is_used');
            $table->index(['phone_number', 'is_used', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('otps');
    }
};
