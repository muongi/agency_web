<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('business_units', function (Blueprint $table) {
            $table->boolean('cnss_enabled')->default(false)->after('is_active');
        });

        $enrollmentBuIds = DB::table('cnss_voluntary_enrollments')
            ->distinct()
            ->pluck('business_unit_id');

        $contributionBuIds = DB::table('cnss_contributions')
            ->distinct()
            ->pluck('business_unit_id');

        $cnssBuIds = $enrollmentBuIds->merge($contributionBuIds)->unique()->filter();

        if ($cnssBuIds->isNotEmpty()) {
            DB::table('business_units')
                ->whereIn('id', $cnssBuIds)
                ->update(['cnss_enabled' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_units', function (Blueprint $table) {
            $table->dropColumn('cnss_enabled');
        });
    }
};
