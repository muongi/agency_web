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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            
            // Personal Information
            $table->string('salutation')->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('id_passport')->nullable();
            $table->text('home_address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('photo')->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->enum('marital_status', ['Single', 'Married', 'Divorced', 'Widowed'])->nullable();
            $table->string('year_of_birth')->nullable();
            $table->string('region')->nullable();
            $table->string('home_town')->nullable();
            $table->string('nationality')->nullable();
            
            // Next of Kin Information
            $table->string('next_of_kin_first_name')->nullable();
            $table->string('next_of_kin_middle_name')->nullable();
            $table->string('next_of_kin_last_name')->nullable();
            $table->string('next_of_kin_phone_number')->nullable();
            $table->text('next_of_kin_address')->nullable();
            $table->enum('next_of_kin_gender', ['Male', 'Female', 'Other'])->nullable();
            
            $table->timestamps();
            
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->index('business_unit_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('clients');
    }
};
