<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_assignments', function (Blueprint $table) {
            $table->foreignUuid('form_submission_id')
                ->nullable()
                ->after('procurement_id')
                ->constrained('form_submissions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_assignments', function (Blueprint $table) {
            $table->dropForeign(['form_submission_id']);
            $table->dropColumn('form_submission_id');
        });
    }
};
