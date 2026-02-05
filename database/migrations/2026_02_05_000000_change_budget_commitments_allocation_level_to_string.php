<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `myb_budget_commitments` MODIFY `allocation_level` VARCHAR(50) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `myb_budget_commitments` DROP COLUMN `allocation_level`");
        DB::statement("ALTER TABLE `myb_budget_commitments` ADD `allocation_level` DECIMAL(15,2) NULL");
    }
};
