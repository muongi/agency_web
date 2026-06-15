<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('kato_member_no', 255)->nullable()->index()->after('id_passport');
            $table->text('kato_registration_error')->nullable()->after('kato_member_no');
            $table->json('kato_api_response')->nullable()->after('kato_registration_error');
        });
    }

    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'kato_member_no',
                'kato_registration_error',
                'kato_api_response',
            ]);
        });
    }
};

