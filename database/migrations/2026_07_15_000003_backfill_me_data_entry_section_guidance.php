<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_GUIDANCE = 'Complete the questions in this section using the most accurate information available. Review your answers before continuing to the next section.';

    private const BACKFILL_TABLE = 'me_data_entry_section_guidance_backfills';

    private const SECTIONS_TABLE = 'me_data_entry_form_sections';

    public function up(): void
    {
        if (! Schema::hasTable(self::SECTIONS_TABLE)) {
            return;
        }

        Schema::create(self::BACKFILL_TABLE, function (Blueprint $table): void {
            $table->uuid('section_id')->primary();
            $table->text('original_description')->nullable();

            $table->foreign('section_id', 'me_section_guidance_backfill_fk')
                ->references('id')
                ->on(self::SECTIONS_TABLE)
                ->cascadeOnDelete();
        });

        DB::table(self::SECTIONS_TABLE)
            ->select(['id', 'description'])
            ->where(function ($query): void {
                $query->whereNull('description')
                    ->orWhereRaw("TRIM(description) = ''");
            })
            ->orderBy('id')
            ->chunkById(500, function ($sections): void {
                DB::table(self::BACKFILL_TABLE)->insert(
                    $sections->map(fn (object $section): array => [
                        'section_id' => $section->id,
                        'original_description' => $section->description,
                    ])->all()
                );

                DB::table(self::SECTIONS_TABLE)
                    ->whereIn('id', $sections->pluck('id'))
                    ->where(function ($query): void {
                        $query->whereNull('description')
                            ->orWhereRaw("TRIM(description) = ''");
                    })
                    ->update(['description' => self::DEFAULT_GUIDANCE]);
            }, 'id');
    }

    public function down(): void
    {
        if (Schema::hasTable(self::SECTIONS_TABLE) && Schema::hasTable(self::BACKFILL_TABLE)) {
            DB::table(self::BACKFILL_TABLE)
                ->select(['section_id', 'original_description'])
                ->orderBy('section_id')
                ->chunk(500, function ($backfills): void {
                    foreach ($backfills as $backfill) {
                        DB::table(self::SECTIONS_TABLE)
                            ->where('id', $backfill->section_id)
                            ->where('description', self::DEFAULT_GUIDANCE)
                            ->update(['description' => $backfill->original_description]);
                    }
                });
        }

        Schema::dropIfExists(self::BACKFILL_TABLE);
    }
};
