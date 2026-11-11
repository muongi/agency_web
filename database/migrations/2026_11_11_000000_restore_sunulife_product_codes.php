<?php

use App\Http\Controllers\SunuWebController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SUNU product codes must be exactly what their core system exports (CPQOT2., DCE-EXAZXX.).
 * Products imported before 2026-11-11 got a rewritten code (DCEIGXX); the original was kept in
 * sunulife_product_code. Put it back as the code. Policies / proposals / premiums link by id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('insurance_products')->whereNotNull('sunulife_product_code')->where('sunulife_product_code', '!=', '')->get();
        foreach ($rows as $p) {
            $original = strtoupper(trim($p->sunulife_product_code));
            if ($p->code === $original) {
                continue;
            }
            $taken = DB::table('insurance_products')->where('business_unit_id', $p->business_unit_id)
                ->where('code', $original)->where('id', '!=', $p->id)->exists();
            if ($taken) {
                Log::warning('SUNU_PRODUCT_CODE_RESTORE_SKIPPED', ['id' => $p->id, 'code' => $p->code, 'original' => $original]);
                continue;
            }
            DB::table('insurance_products')->where('id', $p->id)->update(['code' => $original, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $rows = DB::table('insurance_products')->whereNotNull('sunulife_product_code')->whereColumn('code', 'sunulife_product_code')->get();
        foreach ($rows as $p) {
            $old = SunuWebController::boProductCode($p->code);
            if ($old !== $p->code && ! DB::table('insurance_products')->where('business_unit_id', $p->business_unit_id)->where('code', $old)->exists()) {
                DB::table('insurance_products')->where('id', $p->id)->update(['code' => $old, 'updated_at' => now()]);
            }
        }
    }
};
