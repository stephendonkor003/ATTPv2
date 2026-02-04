<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            if (!Schema::hasColumn('procurements', 'awarded_submission_id')) {
                $table->foreignUuid('awarded_submission_id')->nullable();
            }
            if (!Schema::hasColumn('procurements', 'awarded_vendor_id')) {
                $table->foreignUuid('awarded_vendor_id')->nullable();
            }
            if (!Schema::hasColumn('procurements', 'awarded_at')) {
                $table->timestamp('awarded_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            if (Schema::hasColumn('procurements', 'awarded_at')) {
                $table->dropColumn('awarded_at');
            }
            if (Schema::hasColumn('procurements', 'awarded_vendor_id')) {
                $table->dropColumn('awarded_vendor_id');
            }
            if (Schema::hasColumn('procurements', 'awarded_submission_id')) {
                $table->dropColumn('awarded_submission_id');
            }
        });
    }
};
