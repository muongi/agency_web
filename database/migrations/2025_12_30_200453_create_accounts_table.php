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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->string('accountable_type'); // 'App\Models\Client' or 'App\Models\Agent'
            $table->unsignedBigInteger('accountable_id');
            $table->enum('account_type', ['general', 'float', 'commission']);
            $table->string('account_number');
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->string('currency', 3)->default('CFA');
            $table->enum('status', ['active', 'inactive', 'suspended', 'closed'])->default('active');
            $table->date('opening_date');
            $table->date('closing_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->unique(['business_unit_id', 'account_number'], 'accounts_bu_number_unique');
            $table->index(['accountable_type', 'accountable_id']);
            $table->index('business_unit_id');
            $table->index('account_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accounts');
    }
};
