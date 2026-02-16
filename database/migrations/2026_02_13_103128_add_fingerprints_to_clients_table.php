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
        Schema::table('clients', function (Blueprint $table) {
            $table->text('fingerprint1')->nullable()->after('photo');
            $table->text('fingerprint2')->nullable()->after('fingerprint1');
            $table->text('fingerprint3')->nullable()->after('fingerprint2');
            $table->text('fingerprint4')->nullable()->after('fingerprint3');
            $table->boolean('biometrics_status')->default(false)->after('fingerprint4');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'fingerprint1',
                'fingerprint2',
                'fingerprint3',
                'fingerprint4',
                'biometrics_status'
            ]);
        });
    }
};
