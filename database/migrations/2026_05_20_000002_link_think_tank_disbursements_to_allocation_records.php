<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_disbursements', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_disbursements', 'fund_allocation_id')) {
                $table->foreignUuid('fund_allocation_id')
                    ->nullable()
                    ->after('think_tank_member_id')
                    ->constrained('attp_fund_allocations')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('procurement_disbursements', 'consortium_disbursement_request_id')) {
                $table->foreignUuid('consortium_disbursement_request_id')
                    ->nullable()
                    ->after('fund_allocation_id')
                    ->constrained('attp_disbursement_requests')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_disbursements', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_disbursements', 'consortium_disbursement_request_id')) {
                $table->dropConstrainedForeignId('consortium_disbursement_request_id');
            }

            if (Schema::hasColumn('procurement_disbursements', 'fund_allocation_id')) {
                $table->dropConstrainedForeignId('fund_allocation_id');
            }
        });
    }
};
