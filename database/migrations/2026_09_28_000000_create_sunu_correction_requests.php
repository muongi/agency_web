<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collector error reports from the TPE (cancel / modify a premium or a subscription):
 * request → decision (approve / reject) → manual correction by staff, every step traced
 * in sunu_correction_events (append-only). Premiums get a status so they can be cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunu_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->string('reference', 32)->unique();
            $table->string('target_type', 16);
            $table->unsignedBigInteger('target_id');
            $table->string('target_reference')->nullable();
            $table->string('request_type', 16);
            $table->string('reason_code', 32);
            $table->text('details');
            $table->json('proposed_changes')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('requested_by_agent_id')->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->string('device_serial')->nullable();
            $table->timestamp('requested_at');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->string('client_reference', 128)->nullable()->unique();
            $table->timestamps();

            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('requested_by_agent_id')->references('id')->on('agents')->onDelete('set null');
            $table->foreign('terminal_id')->references('id')->on('terminals')->onDelete('set null');
            $table->foreign('decided_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['business_unit_id', 'status']);
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('sunu_correction_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('correction_request_id');
            $table->string('event', 40);
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->text('note')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('correction_request_id')->references('id')->on('sunu_correction_requests')->onDelete('cascade');
            $table->index('correction_request_id');
        });

        Schema::table('sunu_premium_collections', function (Blueprint $table) {
            $table->string('status', 16)->default('valid')->after('collection_kind');
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            $table->unsignedBigInteger('correction_request_id')->nullable()->after('cancelled_by');

            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('correction_request_id')->references('id')->on('sunu_correction_requests')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sunu_premium_collections', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropForeign(['correction_request_id']);
            $table->dropColumn(['status', 'cancelled_at', 'cancelled_by', 'correction_request_id']);
        });
        Schema::dropIfExists('sunu_correction_events');
        Schema::dropIfExists('sunu_correction_requests');
    }
};
