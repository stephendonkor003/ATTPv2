<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_purchase_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('myb_purchase_requests', 'approved_by')) {
                $table->string('approved_by')->nullable()->after('status');
            }

            if (! Schema::hasColumn('myb_purchase_requests', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('myb_purchase_requests', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('myb_purchase_requests', 'rejected_by')) {
                $table->string('rejected_by')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('myb_purchase_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
        });
    }

    public function down(): void
    {
        $columns = collect([
            'approved_by',
            'approved_at',
            'rejection_reason',
            'rejected_by',
            'rejected_at',
        ])->filter(fn ($column) => Schema::hasColumn('myb_purchase_requests', $column))->values()->all();

        if ($columns === []) {
            return;
        }

        Schema::table('myb_purchase_requests', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
