<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $types = [
            [
                'code' => '310000',
                'name' => 'Balance Enquiry',
                'description' => 'Member balance enquiry via agency terminal',
                'internal_ledger_flow' => 'balance_enquiry',
            ],
            [
                'code' => '320000',
                'name' => 'Mini Statement',
                'description' => 'Member mini-statement via agency terminal',
                'internal_ledger_flow' => 'mini_statement',
            ],
        ];

        foreach ($types as $type) {
            $exists = DB::table('transaction_types')->where('code', $type['code'])->exists();

            if ($exists) {
                DB::table('transaction_types')->where('code', $type['code'])->update([
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'internal_ledger_flow' => $type['internal_ledger_flow'],
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('transaction_types')->insert([
                    'business_unit_id' => null,
                    'name' => $type['name'],
                    'code' => $type['code'],
                    'description' => $type['description'],
                    'internal_ledger_flow' => $type['internal_ledger_flow'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('transaction_types')->whereIn('code', ['310000', '320000'])->delete();
    }
};
