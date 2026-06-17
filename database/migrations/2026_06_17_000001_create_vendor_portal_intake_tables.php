<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_purchase_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('procurement_id')->nullable()->constrained('procurements')->nullOnDelete();
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('procurement_purchase_orders')->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->string('request_type', 40)->default('purchase_request')->index();
            $table->string('title');
            $table->decimal('requested_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->date('needed_by')->nullable();
            $table->string('priority', 30)->default('normal')->index();
            $table->string('status', 40)->default('submitted')->index();
            $table->text('description')->nullable();
            $table->text('business_justification')->nullable();
            $table->text('admin_response')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['procurement_id', 'status']);
        });

        Schema::create('vendor_purchase_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_purchase_request_id')
                ->constrained('vendor_purchase_requests')
                ->cascadeOnDelete();
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('delivery_date')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('procurement_id')->nullable()->constrained('procurements')->nullOnDelete();
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('procurement_purchase_orders')->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->string('title');
            $table->string('report_type', 60)->default('progress')->index();
            $table->date('reporting_period_start')->nullable();
            $table->date('reporting_period_end')->nullable();
            $table->string('status', 40)->default('submitted')->index();
            $table->text('summary');
            $table->text('challenges')->nullable();
            $table->text('next_steps')->nullable();
            $table->text('admin_feedback')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['procurement_id', 'status']);
        });

        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type', 80)->default('manual_upload')->index();
            $table->uuid('source_id')->nullable()->index();
            $table->string('title');
            $table->string('document_type', 80)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
        Schema::dropIfExists('vendor_reports');
        Schema::dropIfExists('vendor_purchase_request_items');
        Schema::dropIfExists('vendor_purchase_requests');
    }
};
