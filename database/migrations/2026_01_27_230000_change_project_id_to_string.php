<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE myb_projects ALTER COLUMN project_id TYPE VARCHAR(50) USING project_id::VARCHAR(50)');
        DB::statement('ALTER TABLE myb_projects ALTER COLUMN project_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::table('myb_projects')
            ->whereRaw("project_id !~* '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'")
            ->update(['project_id' => null]);

        DB::statement('ALTER TABLE myb_projects ALTER COLUMN project_id TYPE UUID USING project_id::UUID');
        DB::statement('ALTER TABLE myb_projects ALTER COLUMN project_id DROP NOT NULL');
    }
};
