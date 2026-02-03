<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('myb_purchase_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('reference_no')->unique();

            $table->foreignUuid('program_funding_id')
                ->constrained('myb_program_fundings')
                ->cascadeOnDelete();

            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();

            $table->string('allocation_level', 50)->default('sub_activity');
            $table->foreignUuid('allocation_id')
                ->constrained('myb_sub_activities')
                ->cascadeOnDelete();

            $table->integer('start_year');
            $table->string('currency', 10)->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft');

            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['governance_node_id', 'start_year']);
            $table->index(['program_funding_id', 'start_year']);
        });

        Schema::create('myb_purchase_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('purchase_request_id')
                ->constrained('myb_purchase_requests')
                ->cascadeOnDelete();

            $table->foreignUuid('resource_category_id')
                ->nullable()
                ->constrained('myb_resource_categories')
                ->nullOnDelete();

            $table->foreignUuid('resource_id')
                ->nullable()
                ->constrained('myb_resources')
                ->nullOnDelete();

            $table->decimal('amount', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['purchase_request_id']);
        });

        Schema::table('myb_budget_commitments', function (Blueprint $table) {
            $table->foreignUuid('purchase_request_id')
                ->nullable()
                ->after('program_funding_id')
                ->constrained('myb_purchase_requests')
                ->nullOnDelete();

            $table->text('description')
                ->nullable()
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('myb_budget_commitments', function (Blueprint $table) {
            $table->dropForeign(['purchase_request_id']);
            $table->dropColumn('purchase_request_id');

            $table->dropColumn('description');
        });

        Schema::dropIfExists('myb_purchase_request_items');
        Schema::dropIfExists('myb_purchase_requests');
    }
};

