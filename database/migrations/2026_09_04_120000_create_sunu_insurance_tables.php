<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->string('code', 64);
            $table->string('name_fr');
            $table->string('name_en')->nullable();
            $table->string('family', 32)->default('micro-insurance');
            $table->string('default_premium_frequency', 32)->default('monthly');
            $table->decimal('default_premium_amount', 15, 2)->nullable();
            $table->decimal('policy_cost_amount', 15, 2)->default(0);
            $table->string('sunulife_product_code', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->unique(['business_unit_id', 'code']);
        });

        Schema::create('sunu_proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('insurance_product_id');
            $table->string('proposal_number');
            $table->string('status', 32)->default('captured');
            $table->timestamp('subscribed_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('last_name')->nullable()->index();
            $table->string('first_names')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->json('form_data')->nullable();
            $table->string('proposal_image_path')->nullable();
            $table->string('id_document_front_path')->nullable();
            $table->string('id_document_back_path')->nullable();
            $table->string('client_reference', 128)->nullable()->unique();
            $table->string('sunulife_policy_number')->nullable();
            $table->timestamps();

            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('set null');
            $table->foreign('terminal_id')->references('id')->on('terminals')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('insurance_product_id')->references('id')->on('insurance_products')->onDelete('restrict');
            $table->unique(['business_unit_id', 'proposal_number']);
            $table->index(['business_unit_id', 'status']);
        });

        Schema::create('sunu_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('insurance_product_id');
            $table->unsignedBigInteger('proposal_id')->nullable();
            $table->string('policy_number');
            $table->string('sunulife_policy_id')->nullable();
            $table->string('status', 32)->default('active');
            $table->date('effective_date')->nullable();
            $table->decimal('premium_amount', 15, 2)->nullable();
            $table->string('premium_frequency', 32)->default('monthly');
            $table->date('next_due_date')->nullable();
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->string('source', 32)->default('local_proposal');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('insurance_product_id')->references('id')->on('insurance_products')->onDelete('restrict');
            $table->foreign('proposal_id')->references('id')->on('sunu_proposals')->onDelete('set null');
            $table->unique(['business_unit_id', 'policy_number']);
            $table->index(['business_unit_id', 'status']);
        });

        Schema::create('sunu_premium_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->unsignedBigInteger('proposal_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('collection_kind', 32);
            $table->string('reference_period', 64)->nullable();
            $table->string('payment_frequency', 32)->nullable();
            $table->string('payment_instrument', 32)->default('cash');
            $table->string('reference')->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->string('receipt_number')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sunulife_pushed_at')->nullable();
            $table->timestamps();

            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('policy_id')->references('id')->on('sunu_policies')->onDelete('set null');
            $table->foreign('proposal_id')->references('id')->on('sunu_proposals')->onDelete('set null');
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('set null');
            $table->foreign('terminal_id')->references('id')->on('terminals')->onDelete('set null');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->index(['business_unit_id', 'collection_kind']);
        });

        Schema::create('collector_caps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->unique();
            $table->decimal('cap_amount', 15, 2);
            $table->unsignedTinyInteger('warning_threshold_pct')->default(80);
            $table->string('currency', 8)->default('XOF');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
        });

        Schema::create('collector_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->unique();
            $table->decimal('uncollected_amount', 15, 2)->default(0);
            $table->timestamp('last_pay_in_at')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
        });

        Schema::create('sunu_pay_ins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->decimal('declared_amount', 15, 2);
            $table->string('method', 32);
            $table->string('bank_reference')->nullable();
            $table->string('deposit_slip_path')->nullable();
            $table->timestamp('declared_at');
            $table->string('status', 32)->default('declared');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('confirmed_amount', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('client_reference', 128)->nullable()->unique();
            $table->timestamps();

            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('terminal_id')->references('id')->on('terminals')->onDelete('set null');
            $table->foreign('confirmed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['business_unit_id', 'status']);
        });

        Schema::create('card_policy_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_id');
            $table->unsignedBigInteger('policy_id');
            $table->unsignedTinyInteger('slot_index')->default(0);
            $table->timestamps();

            $table->foreign('card_id')->references('id')->on('cards')->onDelete('cascade');
            $table->foreign('policy_id')->references('id')->on('sunu_policies')->onDelete('cascade');
            $table->unique(['card_id', 'policy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_policy_links');
        Schema::dropIfExists('sunu_pay_ins');
        Schema::dropIfExists('collector_balances');
        Schema::dropIfExists('collector_caps');
        Schema::dropIfExists('sunu_premium_collections');
        Schema::dropIfExists('sunu_policies');
        Schema::dropIfExists('sunu_proposals');
        Schema::dropIfExists('insurance_products');
    }
};
