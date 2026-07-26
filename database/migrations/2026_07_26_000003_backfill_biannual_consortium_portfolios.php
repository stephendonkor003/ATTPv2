<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('attp_consortia')
            || ! Schema::hasTable('myb_program_fundings')
            || ! Schema::hasTable('myb_programs')
            || ! Schema::hasTable('myb_sectors')
        ) {
            return;
        }

        DB::table('attp_consortia')
            ->whereNull('program_funding_id')
            ->whereNotNull('funder_id')
            ->get(['id', 'funder_id'])
            ->each(function (object $consortium): void {
                $fundingIds = DB::table('myb_program_fundings as funding')
                    ->join('myb_programs as program', 'program.id', '=', 'funding.program_id')
                    ->where('funding.funder_id', $consortium->funder_id)
                    ->whereNotNull('program.sector_id')
                    ->pluck('funding.id')
                    ->map(fn ($id): string => (string) $id)
                    ->unique()
                    ->values();

                if ($fundingIds->count() !== 1) {
                    return;
                }

                DB::table('attp_consortia')
                    ->where('id', $consortium->id)
                    ->whereNull('program_funding_id')
                    ->update([
                        'program_funding_id' => $fundingIds->first(),
                        'updated_at' => now(),
                    ]);
            });

        $this->backfillProfilePortfolioSnapshots();
    }

    public function down(): void
    {
        // This is a conservative data repair. Reversing it could erase a
        // relationship that administrators subsequently confirmed or changed.
    }

    private function backfillProfilePortfolioSnapshots(): void
    {
        if (
            ! Schema::hasTable('biannual_site_visit_profiles')
            || ! Schema::hasTable('attp_consortium_think_tanks')
        ) {
            return;
        }

        DB::table('biannual_site_visit_profiles as profile')
            ->join(
                'attp_consortium_think_tanks as think_tank',
                'think_tank.id',
                '=',
                'profile.think_tank_member_id'
            )
            ->join('attp_consortia as consortium', 'consortium.id', '=', 'think_tank.consortium_id')
            ->join(
                'myb_program_fundings as funding',
                'funding.id',
                '=',
                'consortium.program_funding_id'
            )
            ->join('myb_programs as program', 'program.id', '=', 'funding.program_id')
            ->join('myb_sectors as sector', 'sector.id', '=', 'program.sector_id')
            ->select([
                'profile.id',
                'profile.settings',
                'sector.id as portfolio_id',
                'sector.name as portfolio_name',
            ])
            ->orderBy('profile.id')
            ->chunk(100, function ($profiles): void {
                foreach ($profiles as $profile) {
                    $settings = json_decode((string) ($profile->settings ?? ''), true);
                    $settings = is_array($settings) ? $settings : [];

                    if (filled(data_get($settings, 'portfolio.id'))) {
                        continue;
                    }

                    data_set($settings, 'portfolio', [
                        'id' => (string) $profile->portfolio_id,
                        'name' => (string) $profile->portfolio_name,
                    ]);

                    DB::table('biannual_site_visit_profiles')
                        ->where('id', $profile->id)
                        ->update([
                            'settings' => json_encode(
                                $settings,
                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                            ),
                            'updated_at' => now(),
                        ]);
                }
            });
    }
};
