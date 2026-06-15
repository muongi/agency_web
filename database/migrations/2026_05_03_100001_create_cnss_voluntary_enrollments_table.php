<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnss_voluntary_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->string('status', 32)->default('pending');

            $table->string('voluntary_insured_number');
            $table->string('cnss_registration_affiliation_number');
            $table->string('worker_number')->nullable();

            $table->date('effective_date')->nullable();
            $table->date('registration_date')->nullable();

            $table->string('last_name')->nullable()->index();
            $table->string('first_names')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('insurance_type_art', 8)->nullable()->index();

            $table->json('form_data');

            $table->timestamps();

            $table->foreign('business_unit_id')
                ->references('id')
                ->on('business_units')
                ->onDelete('cascade');
            $table->foreign('agent_id')
                ->references('id')
                ->on('agents')
                ->onDelete('set null');
            $table->foreign('terminal_id')
                ->references('id')
                ->on('terminals')
                ->onDelete('set null');

            $table->index(['business_unit_id', 'status']);

            $table->unique('voluntary_insured_number', 'cnss_ve_voluntary_insured_uq');
            $table->unique('cnss_registration_affiliation_number', 'cnss_ve_reg_affil_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnss_voluntary_enrollments');
    }
};
