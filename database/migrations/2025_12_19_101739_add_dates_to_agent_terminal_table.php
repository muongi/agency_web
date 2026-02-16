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
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE agent_terminal ADD COLUMN assigned_at TIMESTAMP NULL AFTER terminal_id');
            DB::statement('ALTER TABLE agent_terminal ADD COLUMN unassigned_at TIMESTAMP NULL AFTER assigned_at');
        } elseif ($driver === 'pgsql') {
            Schema::table('agent_terminal', function (Blueprint $table) {
                $table->timestamp('assigned_at')->nullable()->after('terminal_id');
                $table->timestamp('unassigned_at')->nullable()->after('assigned_at');
            });
        } else {
            Schema::table('agent_terminal', function (Blueprint $table) {
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('unassigned_at')->nullable();
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
            DB::statement('ALTER TABLE agent_terminal DROP COLUMN assigned_at');
            DB::statement('ALTER TABLE agent_terminal DROP COLUMN unassigned_at');
        } else {
            Schema::table('agent_terminal', function (Blueprint $table) {
                $table->dropColumn(['assigned_at', 'unassigned_at']);
            });
        }
    }
};
