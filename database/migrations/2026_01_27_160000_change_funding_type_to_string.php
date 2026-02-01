<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `myb_program_fundings` MODIFY `funding_type` VARCHAR(50) NULL");
    }

    public function down(): void
    {
        DB::table('myb_program_fundings')
            ->whereNotNull('funding_type')
            ->whereRaw('funding_type REGEXP \'[^0-9\\.\\-]\'')
            ->update(['funding_type' => null]);

        DB::statement("ALTER TABLE `myb_program_fundings` MODIFY `funding_type` DECIMAL(15,2) NULL");
    }
};
