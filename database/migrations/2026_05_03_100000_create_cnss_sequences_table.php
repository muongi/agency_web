<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnss_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id')->unique();
            $table->unsignedBigInteger('next_voluntary_seq')->default(0);
            $table->unsignedBigInteger('next_affiliation_seq')->default(0);
            $table->timestamps();

            $table->foreign('business_unit_id')
                ->references('id')
                ->on('business_units')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnss_sequences');
    }
};
