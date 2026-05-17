<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE myb_programs ALTER COLUMN program_id TYPE VARCHAR(50) USING program_id::VARCHAR(50)');
        DB::statement('ALTER TABLE myb_programs ALTER COLUMN program_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::table('myb_programs')
            ->whereRaw("program_id !~* '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'")
            ->update(['program_id' => null]);

        DB::statement('ALTER TABLE myb_programs ALTER COLUMN program_id TYPE UUID USING program_id::UUID');
        DB::statement('ALTER TABLE myb_programs ALTER COLUMN program_id DROP NOT NULL');
    }
};
