<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_purchase_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_purchase_requests', 'sub_activity_id')) {
                $table->foreignUuid('sub_activity_id')
                    ->nullable()
                    ->after('procurement_id')
                    ->constrained('myb_sub_activities')
                    ->nullOnDelete();

                $table->index(['sub_activity_id', 'status'], 'vendor_purchase_requests_sub_activity_status_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_purchase_requests', 'sub_activity_id')) {
                $table->dropIndex('vendor_purchase_requests_sub_activity_status_index');
                $table->dropConstrainedForeignId('sub_activity_id');
            }
        });
    }
};
