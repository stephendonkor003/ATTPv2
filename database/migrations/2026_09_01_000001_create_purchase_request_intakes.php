<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_intakes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference_no', 24)->unique();
            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
            $table->foreignUuid('converted_purchase_request_id')
                ->nullable()
                ->constrained('myb_purchase_requests')
                ->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->date('needed_by')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->decimal('estimated_amount', 15, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('submitted');
            $table->foreignUuid('converted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'created_at'], 'pr_intakes_creator_created_index');
            $table->index(['status', 'created_at'], 'pr_intakes_status_created_index');
            $table->index('governance_node_id', 'pr_intakes_governance_node_index');
            $table->unique('converted_purchase_request_id', 'pr_intakes_converted_pr_unique');
        });

        Schema::create('purchase_request_intake_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('intake_id')
                ->constrained('purchase_request_intakes')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->decimal('quantity', 15, 2)->default(1);
            $table->timestamps();

            $table->index('intake_id', 'pr_intake_items_intake_index');
        });

        Schema::create('purchase_request_intake_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('intake_id')
                ->constrained('purchase_request_intakes')
                ->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->timestamps();

            $table->index('intake_id', 'pr_intake_documents_intake_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_intake_documents');
        Schema::dropIfExists('purchase_request_intake_items');
        Schema::dropIfExists('purchase_request_intakes');
    }
};
