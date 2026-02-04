<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_messages', 'response')) {
                $table->text('response')->nullable()->after('message');
            }
            if (!Schema::hasColumn('vendor_messages', 'responded_by')) {
                $table->foreignUuid('responded_by')->nullable()->after('response');
            }
            if (!Schema::hasColumn('vendor_messages', 'responded_at')) {
                $table->timestamp('responded_at')->nullable()->after('responded_by');
            }
        });

        Schema::table('vendor_information_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_information_requests', 'response')) {
                $table->text('response')->nullable()->after('details');
            }
            if (!Schema::hasColumn('vendor_information_requests', 'responded_by')) {
                $table->foreignUuid('responded_by')->nullable()->after('response');
            }
            if (!Schema::hasColumn('vendor_information_requests', 'responded_at')) {
                $table->timestamp('responded_at')->nullable()->after('responded_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_messages', function (Blueprint $table) {
            $columns = [];
            foreach (['response', 'responded_by', 'responded_at'] as $column) {
                if (Schema::hasColumn('vendor_messages', $column)) {
                    $columns[] = $column;
                }
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('vendor_information_requests', function (Blueprint $table) {
            $columns = [];
            foreach (['response', 'responded_by', 'responded_at'] as $column) {
                if (Schema::hasColumn('vendor_information_requests', $column)) {
                    $columns[] = $column;
                }
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
