<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eoi_report_communications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->constrained('procurements')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('subject', 180);
            $table->longText('message')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['procurement_id', 'type', 'created_at'], 'eoi_communications_procurement_type_index');
        });

        Schema::create('eoi_report_communication_recipients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('communication_id')
                ->constrained('eoi_report_communications')
                ->cascadeOnDelete();
            $table->foreignUuid('form_submission_id')->nullable()->constrained('form_submissions')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_email');
            $table->string('outcome_code', 40);
            $table->string('outcome_label', 80);
            $table->string('workflow_decision', 120);
            $table->string('delivery_status', 20)->default('pending');
            $table->text('delivery_error')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('proposal_submitted_at')->nullable();
            $table->text('proposal_message')->nullable();
            $table->string('record_file_path')->nullable();
            $table->string('record_file_name')->nullable();
            $table->string('record_mime_type', 120)->nullable();
            $table->unsignedBigInteger('record_file_size')->nullable();
            $table->string('record_sha256', 64)->nullable();
            $table->timestamps();

            $table->unique(['communication_id', 'form_submission_id'], 'eoi_communication_recipient_unique');
            $table->index(['user_id', 'created_at'], 'eoi_recipients_user_created_index');
            $table->index(['communication_id', 'delivery_status'], 'eoi_recipients_delivery_index');
        });

        Schema::create('eoi_report_communication_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('communication_id')
                ->constrained('eoi_report_communications')
                ->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->timestamps();

            $table->index(['communication_id', 'created_at'], 'eoi_attachments_communication_index');
        });

        Schema::create('eoi_report_proposal_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipient_id')
                ->constrained('eoi_report_communication_recipients')
                ->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->timestamps();

            $table->index(['recipient_id', 'created_at'], 'eoi_proposal_documents_recipient_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eoi_report_proposal_documents');
        Schema::dropIfExists('eoi_report_communication_attachments');
        Schema::dropIfExists('eoi_report_communication_recipients');
        Schema::dropIfExists('eoi_report_communications');
    }
};
