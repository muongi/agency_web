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
        // Use raw SQL to modify columns without Doctrine DBAL
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE agents MODIFY second_name VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE agents MODIFY phone_number VARCHAR(50) NOT NULL');
            DB::statement('ALTER TABLE agents MODIFY id_passport VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE agents ALTER COLUMN second_name SET NOT NULL');
            DB::statement('ALTER TABLE agents ALTER COLUMN phone_number SET NOT NULL');
            DB::statement('ALTER TABLE agents ALTER COLUMN id_passport SET NOT NULL');
        } else {
            // For SQLite or other drivers, use Schema
            Schema::table('agents', function (Blueprint $table) {
                $table->string('second_name')->nullable(false)->change();
                $table->string('phone_number')->nullable(false)->change();
                $table->string('id_passport')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE agents MODIFY second_name VARCHAR(255) NULL');
            DB::statement('ALTER TABLE agents MODIFY phone_number VARCHAR(50) NULL');
            DB::statement('ALTER TABLE agents MODIFY id_passport VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE agents ALTER COLUMN second_name DROP NOT NULL');
            DB::statement('ALTER TABLE agents ALTER COLUMN phone_number DROP NOT NULL');
            DB::statement('ALTER TABLE agents ALTER COLUMN id_passport DROP NOT NULL');
        } else {
            Schema::table('agents', function (Blueprint $table) {
                $table->string('second_name')->nullable()->change();
                $table->string('phone_number')->nullable()->change();
                $table->string('id_passport')->nullable()->change();
            });
        }
    }
};
