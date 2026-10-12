<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collection PIN (4 digits, confirms each premium on the TPE), separate from the login password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('collection_pin_hash')->nullable()->after('password');
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0)->after('collection_pin_hash');
            $table->timestamp('pin_locked_at')->nullable()->after('pin_failed_attempts');
            $table->boolean('pin_must_change')->default(false)->after('pin_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['collection_pin_hash', 'pin_failed_attempts', 'pin_locked_at', 'pin_must_change']);
        });
    }
};
