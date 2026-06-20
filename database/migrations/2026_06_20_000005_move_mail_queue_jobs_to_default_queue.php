<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            return;
        }

        DB::table('jobs')
            ->where('queue', 'mail')
            ->update(['queue' => 'default']);
    }

    public function down(): void
    {
        // No-op: existing jobs cannot be safely distinguished from new default-queue jobs.
    }
};
