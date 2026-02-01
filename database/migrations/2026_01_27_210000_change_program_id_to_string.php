<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `myb_programs` MODIFY `program_id` VARCHAR(50) NULL");
    }

    public function down(): void
    {
        DB::table('myb_programs')
            ->whereRaw('program_id REGEXP "[^0-9]"')
            ->update(['program_id' => null]);

        DB::statement("ALTER TABLE `myb_programs` MODIFY `program_id` BIGINT UNSIGNED NULL");
    }
};
