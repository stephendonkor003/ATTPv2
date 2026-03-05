<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('world_bank_indicators')) {
            return;
        }

        // Using raw SQL keeps this migration independent from doctrine/dbal.
        DB::statement('ALTER TABLE world_bank_indicators MODIFY name TEXT NOT NULL');
        DB::statement('ALTER TABLE world_bank_indicators MODIFY source_name TEXT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('world_bank_indicators')) {
            return;
        }

        DB::statement('ALTER TABLE world_bank_indicators MODIFY name VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE world_bank_indicators MODIFY source_name VARCHAR(255) NULL');
    }
};

