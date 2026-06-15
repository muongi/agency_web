<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('kato_transaction_id', 255)->nullable()->index()->after('reference');
            $table->text('kato_sync_error')->nullable()->after('kato_transaction_id');
            $table->timestamp('kato_synced_at')->nullable()->after('kato_sync_error');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'kato_transaction_id',
                'kato_sync_error',
                'kato_synced_at',
            ]);
        });
    }
};

