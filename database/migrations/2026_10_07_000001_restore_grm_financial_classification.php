<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUB_ACTIVITY_ID = '019ea974-4ab8-71f8-b329-e73d161c84dd';

    private const ACTIVITY_ID = '019ea974-4a90-703b-a2d3-38ff8109c95e';

    public function up(): void
    {
        if (
            ! Schema::hasTable('myb_sub_activities')
            || ! Schema::hasTable('myb_activities')
            || ! Schema::hasTable('myb_budget_commitments')
        ) {
            return;
        }

        if (DB::table('myb_sub_activities')->where('id', self::SUB_ACTIVITY_ID)->exists()) {
            return;
        }

        $hasFinancialHistory = DB::table('myb_budget_commitments')
            ->where('allocation_level', 'sub_activity')
            ->where('allocation_id', self::SUB_ACTIVITY_ID)
            ->exists();

        if (! $hasFinancialHistory) {
            return;
        }

        if (! DB::table('myb_activities')->where('id', self::ACTIVITY_ID)->exists()) {
            throw new RuntimeException(
                'Cannot restore the GRM Consultant financial classification because its parent activity is missing.'
            );
        }

        DB::table('myb_sub_activities')->insert([
            'id' => self::SUB_ACTIVITY_ID,
            'activity_id' => self::ACTIVITY_ID,
            'governance_node_id' => '019e5f76-16e4-70b0-82e1-472eac39965a',
            'name' => 'GRM Consultant',
            'description' => 'ATTP AWPB FY2025 Excel',
            'expected_outcome_type' => 'text',
            'expected_outcome_value' => '',
            'created_by' => '019e478d-5e9e-7157-8b87-d783c797a58f',
            'created_at' => '2026-06-08 22:57:11',
            'updated_at' => '2026-07-25 05:23:51',
        ]);
    }

    public function down(): void
    {
        // This classification anchors posted financial history. Removing it
        // during rollback would recreate the reporting and audit corruption.
    }
};
