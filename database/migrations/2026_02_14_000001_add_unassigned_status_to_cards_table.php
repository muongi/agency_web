<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make account_id nullable for unassigned cards
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->change();
        });
        
        // Add 'unassigned' to status enum
        // Note: MySQL doesn't support ALTER ENUM directly, so we use raw SQL
        DB::statement("ALTER TABLE cards MODIFY COLUMN status ENUM('active', 'inactive', 'blocked', 'expired', 'replaced', 'unassigned') DEFAULT 'inactive'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'unassigned' from enum
        DB::statement("ALTER TABLE cards MODIFY COLUMN status ENUM('active', 'inactive', 'blocked', 'expired', 'replaced') DEFAULT 'inactive'");
        
        // Make account_id required again (but this might fail if there are unassigned cards)
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable(false)->change();
        });
    }
};
