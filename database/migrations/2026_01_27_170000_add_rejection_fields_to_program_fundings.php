<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_program_fundings', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
            $table->uuid('rejected_by')->nullable()->after('rejection_reason');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('myb_program_fundings', function (Blueprint $table) {
            $table->dropColumn(['rejection_reason', 'rejected_by', 'rejected_at']);
        });
    }
};
