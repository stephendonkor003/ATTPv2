<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const DEFAULT_SECTION_NAME = 'General information';

    private const SOFT_BACKGROUND_COLORS = [
        '#EFF6FF',
        '#F0FDF4',
        '#FFFBEB',
        '#FDF2F8',
        '#F5F3FF',
        '#ECFEFF',
        '#FFF7ED',
        '#F8FAFC',
    ];

    public function up(): void
    {
        Schema::create('me_data_entry_form_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->constrained('me_data_entry_forms')->cascadeOnDelete();
            $table->string('section_key', 120);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('background_color', 7)->default('#EFF6FF');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['form_id', 'section_key'], 'me_form_sections_form_key_unique');
            $table->index(['form_id', 'sort_order'], 'me_form_sections_order_idx');
        });

        Schema::table('me_data_entry_form_fields', function (Blueprint $table) {
            $table->uuid('section_id')->nullable()->after('indicator_id');
            $table->foreign('section_id', 'me_form_fields_section_fk')
                ->references('id')
                ->on('me_data_entry_form_sections')
                ->nullOnDelete();
            $table->index('section_id', 'me_form_fields_section_idx');
        });

        $this->backfillSections();
    }

    public function down(): void
    {
        if (Schema::hasTable('me_data_entry_form_sections')
            && Schema::hasColumn('me_data_entry_form_fields', 'section_id')) {
            DB::table('me_data_entry_form_sections')
                ->select(['id', 'name'])
                ->orderBy('id')
                ->each(function (object $section): void {
                    DB::table('me_data_entry_form_fields')
                        ->where('section_id', $section->id)
                        ->update(['section' => $section->name]);
                });
        }

        if (Schema::hasColumn('me_data_entry_form_fields', 'section_id')) {
            Schema::table('me_data_entry_form_fields', function (Blueprint $table) {
                $table->dropForeign('me_form_fields_section_fk');
                $table->dropIndex('me_form_fields_section_idx');
                $table->dropColumn('section_id');
            });
        }

        Schema::dropIfExists('me_data_entry_form_sections');
    }

    private function backfillSections(): void
    {
        $now = now();

        DB::table('me_data_entry_form_fields')
            ->select('form_id')
            ->distinct()
            ->orderBy('form_id')
            ->pluck('form_id')
            ->each(function (string $formId) use ($now): void {
                $fields = DB::table('me_data_entry_form_fields')
                    ->select(['id', 'section', 'sort_order', 'created_at'])
                    ->where('form_id', $formId)
                    ->orderBy('sort_order')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get();

                $groupedFields = [];

                foreach ($fields as $field) {
                    $name = $this->normaliseSectionName($field->section);
                    $groupedFields[$name] ??= [];
                    $groupedFields[$name][] = $field->id;
                }

                $usedKeys = [];

                foreach (array_keys($groupedFields) as $sortOrder => $name) {
                    $sectionKey = $this->uniqueSectionKey($name, $usedKeys);
                    $sectionId = (string) Str::uuid();

                    DB::table('me_data_entry_form_sections')->insert([
                        'id' => $sectionId,
                        'form_id' => $formId,
                        'section_key' => $sectionKey,
                        'name' => $name,
                        'description' => null,
                        'background_color' => self::SOFT_BACKGROUND_COLORS[
                            $sortOrder % count(self::SOFT_BACKGROUND_COLORS)
                        ],
                        'sort_order' => $sortOrder,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    foreach (array_chunk($groupedFields[$name], 500) as $fieldIds) {
                        DB::table('me_data_entry_form_fields')
                            ->whereIn('id', $fieldIds)
                            ->update(['section_id' => $sectionId]);
                    }
                }
            });
    }

    private function normaliseSectionName(?string $section): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $section));

        return $name !== '' ? $name : self::DEFAULT_SECTION_NAME;
    }

    /**
     * @param  array<string, true>  $usedKeys
     */
    private function uniqueSectionKey(string $name, array &$usedKeys): string
    {
        $baseKey = Str::slug($name, '_');
        $baseKey = Str::limit($baseKey !== '' ? $baseKey : 'section', 120, '');
        $sectionKey = $baseKey;
        $suffix = 2;

        while (isset($usedKeys[$sectionKey])) {
            $suffixText = '_'.$suffix++;
            $sectionKey = Str::limit($baseKey, 120 - strlen($suffixText), '').$suffixText;
        }

        $usedKeys[$sectionKey] = true;

        return $sectionKey;
    }
};
