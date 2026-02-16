<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('business_units', function (Blueprint $table) {
            $table->string('kato_company_id', 255)->nullable()->after('core_banking_system_id');
            $table->unsignedBigInteger('kato_admin_user_id')->nullable()->after('kato_company_id');
            $table->timestamp('kato_registered_at')->nullable()->after('kato_admin_user_id');
            $table->text('kato_registration_error')->nullable()->after('kato_registered_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('business_units', function (Blueprint $table) {
            $table->dropColumn([
                'kato_company_id',
                'kato_admin_user_id',
                'kato_registered_at',
                'kato_registration_error',
            ]);
        });
    }
};
