<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE myb_program_fundings ALTER COLUMN funding_type TYPE VARCHAR(50) USING funding_type::VARCHAR(50)');
        DB::statement('ALTER TABLE myb_program_fundings ALTER COLUMN funding_type DROP NOT NULL');
    }

    public function down(): void
    {
        DB::table('myb_program_fundings')
            ->whereNotNull('funding_type')
            ->whereRaw("funding_type !~ '^-?[0-9]+(\\.[0-9]+)?$'")
            ->update(['funding_type' => null]);

        DB::statement('ALTER TABLE myb_program_fundings ALTER COLUMN funding_type TYPE DECIMAL(15,2) USING funding_type::DECIMAL(15,2)');
        DB::statement('ALTER TABLE myb_program_fundings ALTER COLUMN funding_type DROP NOT NULL');
    }
};
