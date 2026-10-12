<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunu_proposals', function (Blueprint $table) {
            $table->unsignedBigInteger('card_id')->nullable()->after('client_id');
            $table->foreign('card_id')->references('id')->on('cards')->nullOnDelete();
            $table->index('card_id');
        });
    }

    public function down(): void
    {
        Schema::table('sunu_proposals', function (Blueprint $table) {
            $table->dropForeign(['card_id']);
            $table->dropIndex(['card_id']);
            $table->dropColumn('card_id');
        });
    }
};
