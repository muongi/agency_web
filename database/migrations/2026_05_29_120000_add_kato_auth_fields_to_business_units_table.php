<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('business_units', function (Blueprint $table) {
            $table->text('kato_token')->nullable()->after('kato_api_response');
            $table->timestamp('kato_token_expires_at')->nullable()->after('kato_token');
            $table->string('kato_admin_username')->nullable()->after('kato_token_expires_at');
            $table->text('kato_admin_password')->nullable()->after('kato_admin_username');
        });
    }

    public function down()
    {
        Schema::table('business_units', function (Blueprint $table) {
            $table->dropColumn([
                'kato_token',
                'kato_token_expires_at',
                'kato_admin_username',
                'kato_admin_password',
            ]);
        });
    }
};

