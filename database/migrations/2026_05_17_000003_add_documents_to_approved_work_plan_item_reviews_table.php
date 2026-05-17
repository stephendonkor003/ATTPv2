<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approved_work_plan_item_reviews', function (Blueprint $table) {
            $table->string('document_type', 60)->nullable()->after('review_notes');
            $table->string('document_path')->nullable()->after('document_type');
            $table->string('document_name')->nullable()->after('document_path');
            $table->foreignUuid('document_uploaded_by')
                ->nullable()
                ->after('document_name')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('document_uploaded_at')->nullable()->after('document_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('approved_work_plan_item_reviews', function (Blueprint $table) {
            $table->dropForeign(['document_uploaded_by']);
            $table->dropColumn([
                'document_type',
                'document_path',
                'document_name',
                'document_uploaded_by',
                'document_uploaded_at',
            ]);
        });
    }
};
