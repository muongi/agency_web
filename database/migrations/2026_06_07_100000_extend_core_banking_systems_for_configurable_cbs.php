<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_banking_systems', function (Blueprint $table) {
            $table->string('driver', 32)->default('configurable')->after('base_url');
            $table->string('auth_type', 32)->default('jwt_bearer')->after('driver');
            $table->json('auth_config')->nullable()->after('auth_type');
            $table->unsignedSmallInteger('timeout_seconds')->default(30)->after('auth_config');
        });

        DB::table('core_banking_systems')
            ->whereRaw('LOWER(code) = ?', ['kato'])
            ->update(['driver' => 'kato']);
    }

    public function down(): void
    {
        Schema::table('core_banking_systems', function (Blueprint $table) {
            $table->dropColumn(['driver', 'auth_type', 'auth_config', 'timeout_seconds']);
        });
    }
};
