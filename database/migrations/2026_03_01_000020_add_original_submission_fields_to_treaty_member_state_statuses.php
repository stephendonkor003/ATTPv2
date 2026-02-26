<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_treaty_member_state_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('myb_treaty_member_state_statuses', 'is_original_submitted')) {
                $table->boolean('is_original_submitted')->default(false)->after('is_ratified');
            }
            if (!Schema::hasColumn('myb_treaty_member_state_statuses', 'original_submitted_at')) {
                $table->timestamp('original_submitted_at')->nullable()->after('ratified_at');
            }
            if (!Schema::hasColumn('myb_treaty_member_state_statuses', 'original_submitted_by_user_id')) {
                $table->foreignUuid('original_submitted_by_user_id')->nullable()
                    ->after('ratified_by_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('myb_treaty_member_state_statuses', 'original_document_path')) {
                $table->string('original_document_path')->nullable()->after('ratified_document_path');
            }
            if (!Schema::hasColumn('myb_treaty_member_state_statuses', 'original_document_name')) {
                $table->string('original_document_name')->nullable()->after('ratified_document_name');
            }
            if (!Schema::hasColumn('myb_treaty_member_state_statuses', 'original_notes')) {
                $table->text('original_notes')->nullable()->after('ratified_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('myb_treaty_member_state_statuses', function (Blueprint $table) {
            if (Schema::hasColumn('myb_treaty_member_state_statuses', 'original_submitted_by_user_id')) {
                $table->dropForeign(['original_submitted_by_user_id']);
            }

            $columns = [];
            foreach ([
                'is_original_submitted',
                'original_submitted_at',
                'original_submitted_by_user_id',
                'original_document_path',
                'original_document_name',
                'original_notes',
            ] as $column) {
                if (Schema::hasColumn('myb_treaty_member_state_statuses', $column)) {
                    $columns[] = $column;
                }
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
