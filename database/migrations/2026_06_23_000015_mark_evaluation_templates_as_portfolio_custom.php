<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluations')) {
            return;
        }

        Schema::table('evaluations', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluations', 'is_portfolio_custom')) {
                $table->boolean('is_portfolio_custom')->default(false)->after('portfolio_id');
                $table->index('is_portfolio_custom', 'evaluations_portfolio_custom_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('evaluations') || ! Schema::hasColumn('evaluations', 'is_portfolio_custom')) {
            return;
        }

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropIndex('evaluations_portfolio_custom_index');
            $table->dropColumn('is_portfolio_custom');
        });
    }
};
