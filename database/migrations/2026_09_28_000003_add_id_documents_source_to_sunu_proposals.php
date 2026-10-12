<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New product for an existing customer: the CNIB photos are not taken again, the proposal
 * points to the same stored files and records which proposal they came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunu_proposals', function (Blueprint $table) {
            $table->unsignedBigInteger('id_documents_source_proposal_id')->nullable()->after('id_document_back_path');
            $table->foreign('id_documents_source_proposal_id')->references('id')->on('sunu_proposals')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sunu_proposals', function (Blueprint $table) {
            $table->dropForeign(['id_documents_source_proposal_id']);
            $table->dropColumn('id_documents_source_proposal_id');
        });
    }
};
