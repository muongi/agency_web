<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('user_id'); // User who requested the report
            $table->string('report_type'); // agent, client, transaction, commission, cost
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('filters')->nullable(); // Store filter parameters
            $table->string('file_path')->nullable(); // Path to generated file
            $table->string('file_name')->nullable(); // Original filename
            $table->string('file_format')->nullable(); // csv, pdf, xlsx
            $table->integer('total_records')->nullable(); // Number of records in report
            $table->text('error_message')->nullable(); // Error if failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['business_unit_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('report_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
