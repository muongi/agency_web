<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnss_contributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('cnss_voluntary_enrollment_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();

            $table->string('voluntary_insured_number')->nullable()->index();
            $table->string('affiliation_number')->nullable()->index();

            $table->decimal('amount', 15, 2);
            $table->string('reference_period', 32);
            $table->string('payment_frequency', 32);
            $table->string('mode', 16)->default('record');

            $table->string('reference')->nullable()->unique();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('business_unit_id')
                ->references('id')
                ->on('business_units')
                ->onDelete('cascade');
            $table->foreign('cnss_voluntary_enrollment_id')
                ->references('id')
                ->on('cnss_voluntary_enrollments')
                ->onDelete('set null');
            $table->foreign('agent_id')
                ->references('id')
                ->on('agents')
                ->onDelete('set null');
            $table->foreign('terminal_id')
                ->references('id')
                ->on('terminals')
                ->onDelete('set null');
            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions')
                ->onDelete('set null');

            $table->index(['business_unit_id', 'reference_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnss_contributions');
    }
};
