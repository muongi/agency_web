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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            
            // Personal Information
            $table->string('first_name');
            $table->string('second_name')->nullable();
            $table->string('photo')->nullable();
            $table->string('email')->unique();
            $table->string('phone_number')->nullable();
            $table->string('id_passport')->nullable();
            $table->text('physical_address')->nullable();
            $table->string('po_box')->nullable();
            $table->string('username')->unique();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->index('business_unit_id');
            $table->index('username');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('agents');
    }
};
