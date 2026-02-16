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
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            if (!Schema::connection(config('activitylog.database_connection'))->hasColumn(config('activitylog.table_name'), 'business_unit_id')) {
                $table->unsignedBigInteger('business_unit_id')->nullable()->after('properties');
                $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('set null');
                $table->index('business_unit_id');
            }
            
            if (!Schema::connection(config('activitylog.database_connection'))->hasColumn(config('activitylog.table_name'), 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('business_unit_id');
                $table->index('ip_address');
            }
            
            if (!Schema::connection(config('activitylog.database_connection'))->hasColumn(config('activitylog.table_name'), 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            if (Schema::connection(config('activitylog.database_connection'))->hasColumn(config('activitylog.table_name'), 'user_agent')) {
                $table->dropColumn('user_agent');
            }
            
            if (Schema::connection(config('activitylog.database_connection'))->hasColumn(config('activitylog.table_name'), 'ip_address')) {
                $table->dropIndex(['ip_address']);
                $table->dropColumn('ip_address');
            }
            
            if (Schema::connection(config('activitylog.database_connection'))->hasColumn(config('activitylog.table_name'), 'business_unit_id')) {
                $table->dropForeign(['business_unit_id']);
                $table->dropIndex(['business_unit_id']);
                $table->dropColumn('business_unit_id');
            }
        });
    }
};

