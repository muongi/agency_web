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
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('member_code')->nullable()->after('account_number');
            $table->string('manual_number')->nullable()->after('member_code');
            $table->unsignedInteger('signatures_required')->nullable()->after('manual_number');
            $table->date('registration_date')->nullable()->after('opening_date');
            $table->string('manager_name')->nullable()->after('registration_date');
            $table->string('status_1')->nullable()->after('status');
            $table->string('status_2')->nullable()->after('status_1');
            $table->date('signature_date')->nullable()->after('status_2');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'member_code',
                'manual_number',
                'signatures_required',
                'registration_date',
                'manager_name',
                'status_1',
                'status_2',
                'signature_date',
            ]);
        });
    }
};
