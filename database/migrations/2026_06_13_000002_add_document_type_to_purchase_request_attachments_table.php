<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('myb_purchase_request_attachments')
            || Schema::hasColumn('myb_purchase_request_attachments', 'document_type')) {
            return;
        }

        Schema::table('myb_purchase_request_attachments', function (Blueprint $table) {
            $table->string('document_type', 80)->nullable()->after('uploaded_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('myb_purchase_request_attachments')
            || ! Schema::hasColumn('myb_purchase_request_attachments', 'document_type')) {
            return;
        }

        Schema::table('myb_purchase_request_attachments', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }
};
