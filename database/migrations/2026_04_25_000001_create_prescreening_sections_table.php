<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescreening_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prescreening_template_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::table('prescreening_criteria', function (Blueprint $table) {
            $table->foreignUuid('prescreening_section_id')->nullable()->after('prescreening_template_id');
        });

        $now = now();
        $templates = DB::table('prescreening_templates')->select('id')->get();

        foreach ($templates as $index => $template) {
            $sectionId = (string) Str::uuid();

            DB::table('prescreening_sections')->insert([
                'id' => $sectionId,
                'prescreening_template_id' => $template->id,
                'name' => 'General Requirements',
                'description' => null,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('prescreening_criteria')
                ->where('prescreening_template_id', $template->id)
                ->update([
                    'prescreening_section_id' => $sectionId,
                    'sort_order' => DB::raw('COALESCE(sort_order, 0)'),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prescreening_criteria', 'prescreening_section_id')) {
            Schema::table('prescreening_criteria', function (Blueprint $table) {
                $table->dropColumn('prescreening_section_id');
            });
        }

        Schema::dropIfExists('prescreening_sections');
    }
};
