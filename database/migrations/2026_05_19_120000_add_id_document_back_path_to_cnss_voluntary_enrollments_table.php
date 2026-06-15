<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cnss_voluntary_enrollments', function (Blueprint $table) {
            $table->string('id_document_back_path')->nullable()->after('id_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('cnss_voluntary_enrollments', function (Blueprint $table) {
            $table->dropColumn('id_document_back_path');
        });
    }
};
