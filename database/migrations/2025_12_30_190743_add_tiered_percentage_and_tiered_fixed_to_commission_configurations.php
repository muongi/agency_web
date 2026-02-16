<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Modify the enum to include new tiered types
        DB::statement("ALTER TABLE `commission_configurations` MODIFY COLUMN `commission_type` ENUM('fixed', 'percentage', 'tiered', 'tiered_percentage', 'tiered_fixed') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE `commission_configurations` MODIFY COLUMN `commission_type` ENUM('fixed', 'percentage', 'tiered') NOT NULL");
    }
};
