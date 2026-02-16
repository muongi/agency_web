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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('account_id');
            $table->string('card_number')->unique();
            $table->string('prefix');
            $table->enum('status', ['active', 'inactive', 'blocked', 'expired', 'replaced', 'unassigned'])->default('inactive');
            $table->date('expiration_date');
            $table->decimal('daily_transaction_limit', 15, 2)->nullable();
            $table->decimal('daily_withdrawal_limit', 15, 2)->nullable();
            $table->string('pin_hash')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->unsignedBigInteger('replaced_by_card_id')->nullable();
            $table->text('replacement_reason')->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('replaced_by_card_id')->references('id')->on('cards')->onDelete('set null');
            
            $table->index('business_unit_id');
            $table->index('account_id');
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
        Schema::dropIfExists('cards');
    }
};
