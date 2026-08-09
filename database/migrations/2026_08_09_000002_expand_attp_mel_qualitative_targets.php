<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('me_indicator_targets')) {
            return;
        }

        Schema::table('me_indicator_targets', function (Blueprint $table): void {
            $table->text('baseline_value')->nullable()->change();
            $table->text('target_text')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('me_indicator_targets')) {
            return;
        }

        Schema::table('me_indicator_targets', function (Blueprint $table): void {
            $table->string('baseline_value', 100)->nullable()->change();
            $table->string('target_text', 100)->nullable()->change();
        });
    }
};
