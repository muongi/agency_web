<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SUNU "Écrire la carte": per-agent rights granted from the web BO.
     * can_write_cards   = first write of a blank / unassigned card.
     * can_rewrite_cards = overwrite / relink a card already written or linked.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->boolean('can_write_cards')->default(false)->after('is_first_time_pin');
            $table->boolean('can_rewrite_cards')->default(false)->after('can_write_cards');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['can_write_cards', 'can_rewrite_cards']);
        });
    }
};
