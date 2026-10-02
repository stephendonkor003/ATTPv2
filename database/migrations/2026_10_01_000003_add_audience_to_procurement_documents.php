<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_documents')
            || Schema::hasColumn('procurement_documents', 'audience')) {
            return;
        }

        Schema::table('procurement_documents', function (Blueprint $table): void {
            // Existing procurement attachments have always been visible to the
            // eligible applicant audience, so preserve that behaviour safely.
            $table->string('audience', 20)->default('bidder')->after('file_size');
            $table->index(['procurement_id', 'audience'], 'proc_docs_procurement_audience_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('procurement_documents')
            || ! Schema::hasColumn('procurement_documents', 'audience')) {
            return;
        }

        Schema::table('procurement_documents', function (Blueprint $table): void {
            $table->dropIndex('proc_docs_procurement_audience_idx');
            $table->dropColumn('audience');
        });
    }
};
