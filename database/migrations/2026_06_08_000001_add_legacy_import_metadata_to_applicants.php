<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('applicants', 'legacy_source_table')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->string('legacy_source_table')->nullable();
            });
        }

        if (! Schema::hasColumn('applicants', 'legacy_source_id')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->string('legacy_source_id')->nullable();
            });
        }

        if (! Schema::hasColumn('applicants', 'legacy_payload')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->json('legacy_payload')->nullable();
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'legacy_payload',
            'legacy_source_id',
            'legacy_source_table',
        ], fn (string $column): bool => Schema::hasColumn('applicants', $column)));

        if ($columns) {
            Schema::table('applicants', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
