<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_deliverables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('vendor_id')->nullable();
            $table->string('title');
            $table->string('type')->default('deliverable'); // deliverable | milestone
            $table->text('description')->nullable();
            $table->date('timeline_start')->nullable();
            $table->date('timeline_end')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency')->nullable();
            $table->integer('sequence')->default(0);
            $table->string('status')->default('pending'); // pending | in_progress | completed | cancelled
            $table->string('vendor_approval_status')->default('pending'); // pending | approved | rejected
            $table->string('admin_approval_status')->default('pending'); // pending | approved | rejected
            $table->string('vendor_approved_by')->nullable();
            $table->timestamp('vendor_approved_at')->nullable();
            $table->string('admin_approved_by')->nullable();
            $table->timestamp('admin_approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['procurement_id', 'vendor_id']);
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_deliverables');
    }
};
