<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_procurement_plans', function (Blueprint $table) {
            $table->foreignUuid('program_plan_id')->nullable()->after('procurement_code')->constrained('myb_procurement_program_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('myb_procurement_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_plan_id');
        });
    }
};
