<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('attp_ai_guide_settings')) {
            return;
        }

        $maximumId = DB::table('attp_ai_guide_settings')->max('id');
        if ($maximumId === null) {
            return;
        }

        $sequence = DB::selectOne(
            "SELECT last_value, is_called FROM public.attp_ai_guide_settings_id_seq"
        );

        if (! $sequence) {
            throw new RuntimeException('The ATTP AI guide settings sequence is missing.');
        }

        $nextValue = $sequence->is_called
            ? (int) $sequence->last_value + 1
            : (int) $sequence->last_value;

        if ($nextValue <= (int) $maximumId) {
            DB::statement(
                "SELECT setval('public.attp_ai_guide_settings_id_seq'::regclass, ?, true)",
                [(int) $maximumId]
            );
        }
    }

    public function down(): void
    {
        // Never move a live sequence backwards.
    }
};
