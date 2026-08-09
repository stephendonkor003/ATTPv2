<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('procurements', 'cover_image_path')) {
            Schema::table('procurements', function (Blueprint $table): void {
                $table->string('cover_image_path')->nullable()->after('visibility_type');
            });
        }

        Schema::table('dynamic_form_fields', function (Blueprint $table): void {
            if (! Schema::hasColumn('dynamic_form_fields', 'help_text')) {
                $table->text('help_text')->nullable()->after('label');
            }
            if (! Schema::hasColumn('dynamic_form_fields', 'placeholder')) {
                $table->string('placeholder')->nullable()->after('help_text');
            }
            if (! Schema::hasColumn('dynamic_form_fields', 'validation_rules')) {
                $table->json('validation_rules')->nullable()->after('options');
            }
        });

        Schema::table('dynamic_form_fields', function (Blueprint $table): void {
            $table->text('options')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dynamic_form_fields', function (Blueprint $table): void {
            foreach (['validation_rules', 'placeholder', 'help_text'] as $column) {
                if (Schema::hasColumn('dynamic_form_fields', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('procurements', 'cover_image_path')) {
            Schema::table('procurements', function (Blueprint $table): void {
                $table->dropColumn('cover_image_path');
            });
        }
    }
};
