<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_contract_negotiations', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_contract_negotiations', 'termination_reason')) {
                $table->text('termination_reason')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('procurement_contract_negotiations', 'terminated_by')) {
                $table->string('terminated_by')->nullable()->after('termination_reason');
            }
            if (!Schema::hasColumn('procurement_contract_negotiations', 'terminated_at')) {
                $table->timestamp('terminated_at')->nullable()->after('terminated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_contract_negotiations', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_contract_negotiations', 'termination_reason')) {
                $table->dropColumn('termination_reason');
            }
            if (Schema::hasColumn('procurement_contract_negotiations', 'terminated_by')) {
                $table->dropColumn('terminated_by');
            }
            if (Schema::hasColumn('procurement_contract_negotiations', 'terminated_at')) {
                $table->dropColumn('terminated_at');
            }
        });
    }
};
