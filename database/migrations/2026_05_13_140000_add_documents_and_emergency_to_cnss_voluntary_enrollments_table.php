<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cnss_voluntary_enrollments', function (Blueprint $table) {
            $table->string('id_document_path')->nullable()->after('form_data');
            $table->string('birth_certificate_path')->nullable()->after('id_document_path');
            $table->string('emergency_contact_name', 191)->nullable()->after('birth_certificate_path');
            $table->string('emergency_contact_phone', 64)->nullable()->after('emergency_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('cnss_voluntary_enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'id_document_path',
                'birth_certificate_path',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]);
        });
    }
};
