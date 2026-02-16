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
        Schema::table('cards', function (Blueprint $table) {
            $table->string('card_serial', 32)->nullable()->unique()->after('card_number');
            $table->timestamp('written_at')->nullable()->after('activated_at');
            $table->string('written_by', 255)->nullable()->after('written_at');
        });
        
        // Add index for faster lookups
        Schema::table('cards', function (Blueprint $table) {
            $table->index('card_serial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['card_serial']);
            $table->dropColumn(['card_serial', 'written_at', 'written_by']);
        });
    }
};
