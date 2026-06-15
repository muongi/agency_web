<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_unit_cbs_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id')->unique();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->text('bearer_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('extra_headers')->nullable();
            $table->json('extra_fields')->nullable();
            $table->timestamps();

            $table->foreign('business_unit_id', 'bu_cbs_cred_bu_fk')
                ->references('id')
                ->on('business_units')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_unit_cbs_credentials');
    }
};
