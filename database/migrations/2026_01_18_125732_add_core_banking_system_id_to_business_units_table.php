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
            $table->unsignedBigInteger('core_banking_system_id')->nullable()->after('sms_sender_number');
            $table->foreign('core_banking_system_id')->references('id')->on('core_banking_systems')->onDelete('set null');
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
            $table->dropForeign(['core_banking_system_id']);
            $table->dropColumn('core_banking_system_id');
        });
    }
};
