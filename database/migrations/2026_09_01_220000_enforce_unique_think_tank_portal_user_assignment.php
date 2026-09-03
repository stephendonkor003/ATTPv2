<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'attp_think_tanks_portal_user_unique';

    public function up(): void
    {
        $duplicate = DB::table('attp_consortium_think_tanks')
            ->select('portal_user_id')
            ->selectRaw('COUNT(*) AS assignment_count')
            ->whereNotNull('portal_user_id')
            ->groupBy('portal_user_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new \RuntimeException(
                'Cannot add the unique Think Tank portal-user assignment constraint: duplicate portal_user_id values exist. Run the read-only think-tank:portal-user-links:audit command and resolve them explicitly before retrying.'
            );
        }

        Schema::table('attp_consortium_think_tanks', function (Blueprint $table): void {
            $table->unique('portal_user_id', self::INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('attp_consortium_think_tanks', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });
    }
};
