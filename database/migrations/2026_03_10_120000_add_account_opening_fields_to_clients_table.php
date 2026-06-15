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
            $table->date('date_of_birth')->nullable()->after('year_of_birth');
            $table->string('place_of_birth')->nullable()->after('date_of_birth');
            $table->string('geo_location')->nullable()->after('place_of_birth');
            $table->string('distance_from_branch')->nullable()->after('geo_location');
            $table->string('occupation')->nullable()->after('distance_from_branch');
            $table->string('spouse_name')->nullable()->after('occupation');
            $table->string('emergency_contact_name')->nullable()->after('spouse_name');
            $table->text('emergency_contact_address')->nullable()->after('emergency_contact_name');
            $table->string('beneficiary')->nullable()->after('emergency_contact_address');
            $table->string('guardian_name')->nullable()->after('beneficiary');
            $table->string('mother_name')->nullable()->after('guardian_name');
            $table->unsignedInteger('number_of_dependents')->nullable()->after('mother_name');
            $table->string('housing_type')->nullable()->after('number_of_dependents');
            $table->string('housing_type_other')->nullable()->after('housing_type');
            $table->decimal('monthly_income', 15, 2)->nullable()->after('housing_type_other');
            $table->text('observation')->nullable()->after('monthly_income');
            $table->string('mobile_phone')->nullable()->after('observation');
            $table->string('location')->nullable()->after('mobile_phone');
            $table->string('po_box')->nullable()->after('location');
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
                'date_of_birth',
                'place_of_birth',
                'geo_location',
                'distance_from_branch',
                'occupation',
                'spouse_name',
                'emergency_contact_name',
                'emergency_contact_address',
                'beneficiary',
                'guardian_name',
                'mother_name',
                'number_of_dependents',
                'housing_type',
                'housing_type_other',
                'monthly_income',
                'observation',
                'mobile_phone',
                'location',
                'po_box',
            ]);
        });
    }
};
