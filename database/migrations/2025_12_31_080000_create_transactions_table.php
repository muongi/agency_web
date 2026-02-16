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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('transaction_type_id');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            
            // Transaction amounts
            $table->decimal('transaction_amount', 15, 2);
            $table->decimal('transaction_cost', 15, 2)->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->decimal('business_share', 15, 2)->default(0);
            
            // Transaction details
            $table->datetime('transaction_date');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('completed');
            $table->string('reference')->nullable()->unique();
            $table->json('metadata')->nullable();
            
            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('transaction_type_id')->references('id')->on('transaction_types')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('set null');
            $table->foreign('terminal_id')->references('id')->on('terminals')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('business_unit_id');
            $table->index('transaction_type_id');
            $table->index('agent_id');
            $table->index('terminal_id');
            $table->index('client_id');
            $table->index('transaction_date');
            $table->index('status');
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};

