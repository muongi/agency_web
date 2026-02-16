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
        Schema::create('sms_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('client_id');
            $table->text('client_secret'); // Will be encrypted
            $table->text('jwt_token')->nullable(); // Will be encrypted
            $table->string('api_endpoint')->default('https://api.orange.com/smsmessaging/v1');
            $table->string('sender_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_configurations');
    }
};
