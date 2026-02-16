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
        // Drop existing foreign key if it exists
        try {
            $foreignKeyName = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'transaction_types' 
                AND COLUMN_NAME = 'business_unit_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ");
            
            if (!empty($foreignKeyName)) {
                $fkName = $foreignKeyName[0]->CONSTRAINT_NAME;
                DB::statement("ALTER TABLE transaction_types DROP FOREIGN KEY `{$fkName}`");
            }
        } catch (\Exception $e) {
            // Foreign key might not exist or already dropped, continue
        }

        // Find and drop the unique constraint on business_unit_id and code
        try {
            $uniqueIndex = DB::select("
                SELECT INDEX_NAME 
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'transaction_types' 
                AND COLUMN_NAME IN ('business_unit_id', 'code')
                AND NON_UNIQUE = 0
                GROUP BY INDEX_NAME
                HAVING COUNT(DISTINCT COLUMN_NAME) = 2
                LIMIT 1
            ");
            
            if (!empty($uniqueIndex)) {
                $indexName = $uniqueIndex[0]->INDEX_NAME;
                DB::statement("ALTER TABLE transaction_types DROP INDEX `{$indexName}`");
            }
        } catch (\Exception $e) {
            // Index might not exist, continue
        }

        // Make business_unit_id nullable and add global unique constraint on code
        Schema::table('transaction_types', function (Blueprint $table) {
            $table->unsignedBigInteger('business_unit_id')->nullable()->change();
            $table->unique('code', 'transaction_types_code_unique');
        });

        // Re-add foreign key (nullable)
        Schema::table('transaction_types', function (Blueprint $table) {
            $table->foreign('business_unit_id')
                  ->references('id')
                  ->on('business_units')
                  ->onDelete('cascade');
        });

        // Create pivot table for business unit - transaction type assignment
        Schema::create('business_unit_transaction_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('transaction_type_id');
            $table->timestamps();

            $table->foreign('business_unit_id')
                  ->references('id')
                  ->on('business_units')
                  ->onDelete('cascade');
            
            $table->foreign('transaction_type_id')
                  ->references('id')
                  ->on('transaction_types')
                  ->onDelete('cascade');
            
            $table->unique(['business_unit_id', 'transaction_type_id'], 'bu_tt_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop pivot table
        Schema::dropIfExists('business_unit_transaction_type');

        // Revert transaction_types table changes
        Schema::table('transaction_types', function (Blueprint $table) {
            $table->dropForeign(['business_unit_id']);
            $table->dropUnique('transaction_types_code_unique');
        });

        Schema::table('transaction_types', function (Blueprint $table) {
            $table->unsignedBigInteger('business_unit_id')->nullable(false)->change();
            $table->unique(['business_unit_id', 'code'], 'trans_types_bu_code_unique');
        });

        Schema::table('transaction_types', function (Blueprint $table) {
            $table->foreign('business_unit_id')
                  ->references('id')
                  ->on('business_units')
                  ->onDelete('cascade');
        });
    }
};
