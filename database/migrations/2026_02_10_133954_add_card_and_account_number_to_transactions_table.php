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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('card_number')->nullable()->after('client_id');
            $table->string('account_number')->nullable()->after('card_number');
            
            $table->index('card_number');
            $table->index('account_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['card_number']);
            $table->dropIndex(['account_number']);
            $table->dropColumn(['card_number', 'account_number']);
        });
    }
};
