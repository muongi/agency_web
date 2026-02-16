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
            $table->string('account')->nullable()->after('address');
            $table->string('tax_number')->nullable()->after('account');
            $table->text('mailing_address')->nullable()->after('tax_number');
            $table->string('relation_manager_phone')->nullable()->after('mailing_address');
            $table->string('relation_manager_email')->nullable()->after('relation_manager_phone');
            $table->string('logo')->nullable()->after('relation_manager_email');
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
                'account',
                'tax_number',
                'mailing_address',
                'relation_manager_phone',
                'relation_manager_email',
                'logo',
            ]);
        });
    }
};
