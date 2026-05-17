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

        DB::statement('ALTER TABLE world_bank_indicators ALTER COLUMN name TYPE TEXT');
        DB::statement('ALTER TABLE world_bank_indicators ALTER COLUMN name SET NOT NULL');
        DB::statement('ALTER TABLE world_bank_indicators ALTER COLUMN source_name TYPE TEXT');
        DB::statement('ALTER TABLE world_bank_indicators ALTER COLUMN source_name DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('world_bank_indicators')) {
            return;
        }

        DB::statement('ALTER TABLE world_bank_indicators ALTER COLUMN name TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE world_bank_indicators ALTER COLUMN name SET NOT NULL');
        DB::statement('ALTER TABLE world_bank_indicators ALTER COLUMN source_name TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE world_bank_indicators ALTER COLUMN source_name DROP NOT NULL');
    }
};
