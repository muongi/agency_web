<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (! Schema::hasColumn('transaction_types', 'internal_ledger_flow')) {
            Schema::table('transaction_types', function (Blueprint $table) {
                $table->string('internal_ledger_flow', 32)->nullable()->after('is_active');
            });
        }

        if (! Schema::hasColumn('transactions', 'ledger_posted_at')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->timestamp('ledger_posted_at')->nullable()->after('status');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE accounts MODIFY COLUMN account_type ENUM('general', 'float', 'commission', 'business_fee') NOT NULL");
        }
        // SQLite stores Laravel enums as VARCHAR; no schema change required for new value.

        DB::table('transaction_types')->where('code', '0110000')->update([
            'internal_ledger_flow' => 'cash_withdrawal',
        ]);
        DB::table('transaction_types')->where('code', '210000')->update([
            'internal_ledger_flow' => 'cash_deposit',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('transactions', 'ledger_posted_at')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('ledger_posted_at');
            });
        }

        if (Schema::hasColumn('transaction_types', 'internal_ledger_flow')) {
            Schema::table('transaction_types', function (Blueprint $table) {
                $table->dropColumn('internal_ledger_flow');
            });
        }

        if (DB::table('accounts')->where('account_type', 'business_fee')->exists()) {
            throw new \RuntimeException('Cannot revert internal_ledger migration: business_fee accounts exist.');
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE accounts MODIFY COLUMN account_type ENUM('general', 'float', 'commission') NOT NULL");
        }
    }
};
