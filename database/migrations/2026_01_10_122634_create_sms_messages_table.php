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
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->string('sender_number');
            $table->string('recipient_number');
            $table->unsignedBigInteger('recipient_client_id')->nullable();
            $table->text('message');
            $table->enum('status', ['pending', 'sent', 'failed', 'delivered'])->default('pending');
            $table->json('api_response')->nullable();
            $table->string('api_message_id')->nullable();
            $table->unsignedBigInteger('sent_by_user_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('recipient_client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('sent_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['business_unit_id', 'status', 'sent_at']);
            $table->index(['recipient_client_id', 'sent_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_messages');
    }
};
